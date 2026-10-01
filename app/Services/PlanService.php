<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database as DB;
use App\Models\Installment;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Transaction;

/**
 * All plan money movements live here: starting a plan, recording installment
 * payments, triggering the merchant payout, cancellations and reminders.
 * Payments go through PaystackService; texts through SmsService.
 */
final class PlanService
{
    private PaystackService $paystack;
    private SmsService $sms;

    public function __construct(?PaystackService $paystack = null, ?SmsService $sms = null)
    {
        $this->paystack = $paystack ?? new PaystackService();
        $this->sms = $sms ?? new SmsService();
    }

    /**
     * Create a plan and set up its first installment as a hosted checkout. No
     * payment, no plan: the plan stays 'pending' until the first collection is
     * confirmed. Returns [planId, ['status' => ..., 'redirect' => ?url]] — the
     * caller redirects the browser to `redirect` (Paystack's payment page).
     */
    public function startPlan(array $user, array $product, int $installmentPesewas, string $frequency, int $count): array
    {
        $planId = Plan::create([
            'product_id' => (int) $product['id'],
            'customer_id' => (int) $user['id'],
            'total_pesewas' => (int) $product['cash_price_pesewas'],
            'installment_pesewas' => $installmentPesewas,
            'frequency' => $frequency,
            'installments_total' => $count,
        ]);
        Installment::createSchedule($planId, $count, $installmentPesewas, $frequency);

        $result = $this->checkoutInstallment($planId);
        return [$planId, $result];
    }

    /**
     * Set up the next unpaid installment as a hosted Paystack checkout and
     * return the payment-page URL for the browser to open. This is the WEB path
     * (MoMo / card / bank, chosen on Paystack's page). USSD/feature phones use
     * collectInstallment() instead (direct MoMo prompt).
     *
     * Returns ['status' => 'awaiting_payment'|'failed', 'redirect' => ?url].
     * The installment is only credited later, when the payment is confirmed via
     * the webhook, the callback redirect or a status poll (applyCollectionSuccess).
     */
    public function checkoutInstallment(int $planId): array
    {
        $plan = Plan::find($planId);
        $inst = Installment::nextUnpaid($planId);
        if (!$plan || !$inst) {
            return ['status' => 'failed', 'redirect' => null];
        }

        $ref = PaystackService::newReference('c', $planId, (int) $inst['number']);
        $txId = Transaction::create([
            'type' => 'collection',
            'amount_pesewas' => (int) $inst['amount_pesewas'],
            'phone' => $plan['customer_phone'],
            'plan_id' => $planId,
            'installment_id' => (int) $inst['id'],
            'provider_ref' => $ref,
        ]);

        $desc = sprintf('%s — payment %d of %d', $plan['product_name'], $inst['number'], $plan['installments_total']);
        // Paystack sends the customer back here with ?reference=… appended;
        // PlanController::show() verifies it on arrival.
        $callback = rtrim((string) Config::get('APP_URL', ''), '/') . '/plan/' . $planId;
        $link = $this->paystack->paymentLink(
            $plan['customer_phone'],
            (int) $inst['amount_pesewas'],
            $ref,
            $desc,
            $callback,
            ['plan_id' => $planId, 'installment' => (int) $inst['number']]
        );

        if (!$link['ok']) {
            Transaction::setStatus($txId, 'failed', $link['external_ref'], json_encode($link['raw']));
            return ['status' => 'failed', 'redirect' => null, 'reason' => $link['reason'] ?? ''];
        }

        Transaction::setStatus($txId, 'pending', $link['external_ref'], json_encode($link['raw']));
        return ['status' => 'awaiting_payment', 'redirect' => $link['url']];
    }

    /**
     * The payment page of a plan's still-open web checkout, if there is one —
     * so "Pay" sends the customer back to the same checkout instead of opening
     * a second charge. Null when nothing is pending or it can't be resumed.
     */
    public function resumableCheckout(int $planId): ?string
    {
        $tx = Transaction::latestPendingForPlan($planId, 'collection');
        return $tx ? $this->paystack->checkoutUrlFor($tx) : null;
    }

    /**
     * Charge the next unpaid installment straight to the customer's MoMo wallet
     * (USSD path, and the admin simulate button in mock mode).
     * Returns 'active' | 'completed' (paid instantly),
     * 'awaiting_payment' (customer must approve the prompt; webhook confirms),
     * 'needs_voucher' (Telecel Cash — can't finish inside USSD) or 'failed'.
     */
    public function collectInstallment(int $planId): string
    {
        $plan = Plan::find($planId);
        $inst = Installment::nextUnpaid($planId);
        if (!$plan || !$inst) {
            return 'failed';
        }

        $ref = PaystackService::newReference('c', $planId, (int) $inst['number']);
        $txId = Transaction::create([
            'type' => 'collection',
            'amount_pesewas' => (int) $inst['amount_pesewas'],
            'phone' => $plan['customer_phone'],
            'plan_id' => $planId,
            'installment_id' => (int) $inst['id'],
            'provider_ref' => $ref,
        ]);

        $desc = sprintf('%s — payment %d of %d', $plan['product_name'], $inst['number'], $plan['installments_total']);
        $res = $this->paystack->collect(
            $plan['customer_phone'],
            (int) $inst['amount_pesewas'],
            $ref,
            $desc,
            ['plan_id' => $planId, 'installment' => (int) $inst['number']]
        );

        if (!$res['ok']) {
            Transaction::setStatus($txId, 'failed', $res['external_ref'], json_encode($res['raw']));
            return $res['reason'] === 'send_otp' ? 'needs_voucher' : 'failed';
        }

        if ($res['instant']) {
            // Mock mode (or an instant Paystack success): run the same
            // confirmation path a webhook would trigger.
            Transaction::setStatus($txId, 'success', $res['external_ref'], json_encode($res['raw']));
            return $this->applyCollectionSuccess($txId);
        }

        Transaction::setStatus($txId, 'pending', $res['external_ref'], json_encode($res['raw']));
        return 'awaiting_payment';
    }

    /**
     * A collection was confirmed (webhook, poll or mock). Idempotent: replays
     * and double-calls cannot double-credit an installment.
     * Returns the plan's resulting status.
     */
    public function applyCollectionSuccess(int $txId): string
    {
        $tx = Transaction::find($txId);
        if (!$tx || !$tx['installment_id']) {
            return 'failed';
        }

        // Paid into a plan that was cancelled meanwhile (an old checkout page
        // completed late): don't credit it — send the money back.
        $plan = Plan::find((int) $tx['plan_id']);
        if ($plan && $plan['status'] === 'cancelled') {
            error_log("[payments] tx #{$txId} settled on cancelled plan #{$plan['id']} — refunding");
            $this->refundCollections($plan);
            return 'cancelled';
        }

        // markPaid only succeeds once per installment — the idempotency gate.
        if (!Installment::markPaid((int) $tx['installment_id'], $txId)) {
            $inst = Installment::find((int) $tx['installment_id']);
            if ($inst && (int) $inst['transaction_id'] !== $txId) {
                // Two different payments for one installment (e.g. web checkout
                // and USSD at the same time). Flag it for a manual refund.
                error_log("[payments] DOUBLE PAYMENT: tx #{$txId} paid installment #{$tx['installment_id']} already paid by tx #{$inst['transaction_id']} — refund manually");
            }
            return $plan['status'] ?? 'failed';
        }

        DB::run('UPDATE plans SET installments_paid = installments_paid + 1 WHERE id = ?', [$tx['plan_id']]);
        $plan = Plan::find((int) $tx['plan_id']);

        if ($plan['status'] === 'pending') {
            Plan::setStatus((int) $plan['id'], 'active');
            $this->sms->send($plan['customer_phone'], SmsTemplates::planStarted(
                $plan['product_name'],
                ghs((int) $plan['installment_pesewas']),
                $plan['frequency'],
                (int) $plan['installments_total']
            ));
        } else {
            $paid = (int) $plan['installments_paid'];
            $left = ((int) $plan['installments_total'] - $paid) * (int) $plan['installment_pesewas'];
            $this->sms->send($plan['customer_phone'], SmsTemplates::receipt(
                $plan['product_name'],
                $paid,
                (int) $plan['installments_total'],
                ghs((int) $plan['installment_pesewas']),
                ghs(max(0, $left))
            ));
        }

        // Clear any grace flag — they've paid.
        DB::run("UPDATE plans SET grace_state = 'ok', grace_notified_at = NULL WHERE id = ?", [$plan['id']]);

        if ((int) $plan['installments_paid'] >= (int) $plan['installments_total']) {
            return $this->completeAndPayout((int) $plan['id']);
        }
        return 'active';
    }

    /**
     * All installments paid: pay the merchant (minus platform fee) by Paystack
     * transfer. The plan is only marked completed when the transfer succeeds.
     * Safe to call again (admin "retry payout"): it won't start a second payout
     * while one is pending or done.
     */
    public function completeAndPayout(int $planId): string
    {
        $plan = Plan::find($planId);
        if (!$plan || $plan['status'] === 'completed') {
            return 'completed';
        }
        if ($plan['status'] !== 'active' || (int) $plan['installments_paid'] < (int) $plan['installments_total']) {
            return (string) $plan['status'];
        }
        if (Transaction::openPayoutForPlan($planId)) {
            return 'active'; // a payout is already in flight
        }
        $merchant = Merchant::find((int) $plan['merchant_id']);
        if (!$merchant) {
            return 'active';
        }

        $gross = (int) $plan['installment_pesewas'] * (int) $plan['installments_total'];
        $feePct = Config::int('PLATFORM_FEE_PCT', 5);
        $payout = $gross - intdiv($gross * $feePct, 100);

        $ref = PaystackService::newReference('d', $planId);
        $txId = Transaction::create([
            'type' => 'disbursement',
            'amount_pesewas' => $payout,
            'phone' => $merchant['payout_number'] ?: $merchant['phone'],
            'plan_id' => $planId,
            'merchant_id' => (int) $merchant['id'],
            'provider_ref' => $ref,
        ]);

        if (($merchant['payout_number'] ?? '') === '') {
            $merchant['payout_number'] = $merchant['phone'];
        }
        $res = $this->paystack->payout(
            $merchant,
            $payout,
            $ref,
            sprintf('Payout: %s (plan #%d)', $plan['product_name'], $planId)
        );

        // Remember the Paystack recipient so later payouts skip registering it.
        if ($res['recipient_code'] !== '' && $res['recipient_code'] !== (string) ($merchant['paystack_recipient_code'] ?? '')) {
            Merchant::setRecipientCode((int) $merchant['id'], $res['recipient_code']);
        }

        if (!$res['ok']) {
            Transaction::setStatus($txId, 'failed', $res['external_ref'], json_encode($res['raw']));
            error_log("[payout] plan #{$planId} payout failed: {$res['reason']}");
            return 'active'; // stays active; admin can retry from the plan page
        }

        Transaction::setStatus($txId, $res['instant'] ? 'success' : 'pending', $res['external_ref'], json_encode($res['raw']));

        if ($res['instant']) {
            $this->finalizePayout($planId, $txId);
            return 'completed';
        }
        return 'active'; // completed once the transfer webhook / verify lands
    }

    /** Payout confirmed — mark the plan done and tell both sides. */
    public function finalizePayout(int $planId, int $txId): void
    {
        $plan = Plan::find($planId);
        if (!$plan || $plan['status'] === 'completed') {
            return;
        }
        DB::run('UPDATE plans SET status = \'completed\', completed_at = NOW(), payout_transaction_id = ? WHERE id = ?', [$txId, $planId]);

        $tx = Transaction::find($txId);
        $this->sms->send($plan['customer_phone'], SmsTemplates::planCompleteCustomer($plan['product_name'], $plan['shop_name']));
        $this->sms->send($plan['merchant_phone'], SmsTemplates::planCompleteMerchant(
            $plan['product_name'],
            ghs((int) $tx['amount_pesewas']),
            $plan['customer_name']
        ));
    }

    /**
     * Status check for a single transaction. Used by the webhook, the Paystack
     * callback redirect, the customer "check payment" button, the admin
     * reconcile button and the cron. Asks Paystack for the final state and
     * applies the SAME confirmation path every time. Idempotent.
     *
     * $recheckFailed: also re-check a collection we already marked failed (a
     * signed charge.success webhook for an expired checkout that was paid late).
     *
     * Returns 'active'|'completed'|'pending'|'failed'|'success'|'cancelled'.
     */
    public function reconcileTransaction(array $tx, bool $recheckFailed = false): string
    {
        $status = (string) ($tx['status'] ?? '');
        $type = (string) ($tx['type'] ?? '');
        $canRecheck = $recheckFailed && $status === 'failed' && $type === 'collection';
        if ($status !== 'pending' && !$canRecheck) {
            return $status !== '' ? $status : 'failed';
        }

        $res = match ($type) {
            'collection' => $this->paystack->verifyCollection((string) $tx['provider_ref'], (int) $tx['amount_pesewas']),
            'disbursement' => $this->paystack->verifyTransfer((string) $tx['provider_ref']),
            'refund' => $this->paystack->verifyRefund((string) $tx['external_ref']),
            default => ['ok' => false, 'state' => 'pending', 'external_ref' => '', 'raw' => []],
        };
        $state = ($res['ok'] ?? false) ? (string) ($res['state'] ?? 'pending') : 'pending';
        error_log('[reconcile] tx=' . $tx['id'] . ' ' . $type . ' ref=' . $tx['provider_ref'] . ' -> ' . $state);

        // A checkout nobody paid for would block the plan forever. Once it's
        // old enough, close it so the customer can start a fresh payment. (If
        // they somehow pay the old page later, the signed webhook re-checks it.)
        if ($state === 'pending' && $type === 'collection' && $status === 'pending' && ($res['ok'] ?? false)
            && $this->isOlderThanHours((string) $tx['created_at'], Config::int('PAYSTACK_PENDING_EXPIRY_HOURS', 24))) {
            $state = 'failed';
        }

        $extId = (string) ($res['external_ref'] ?? '');
        $rawJson = json_encode($res['raw'] ?? []);

        if ($state === 'success') {
            Transaction::setStatus((int) $tx['id'], 'success', $extId, $rawJson);
            if ($type === 'collection') {
                return $this->applyCollectionSuccess((int) $tx['id']);
            }
            if ($type === 'disbursement' && $tx['plan_id']) {
                $this->finalizePayout((int) $tx['plan_id'], (int) $tx['id']);
                return 'completed';
            }
            return 'success'; // refund confirmed; plan already cancelled
        }

        if ($state === 'failed' && $status === 'pending') {
            Transaction::setStatus((int) $tx['id'], 'failed', $extId, $rawJson);
            return 'failed';
        }

        return $state === 'failed' ? 'failed' : 'pending';
    }

    /** Customer-facing: reconcile the latest pending collection on a plan. */
    public function checkPlanPayment(int $planId): string
    {
        $tx = Transaction::latestPendingForPlan($planId, 'collection');
        if (!$tx) {
            $plan = Plan::find($planId);
            return (string) ($plan['status'] ?? 'failed');
        }
        return $this->reconcileTransaction($tx);
    }

    /**
     * What Paystack currently says about a plan's pending collection — used to
     * show a concrete reason when a payment hasn't confirmed yet, instead of a
     * vague "not confirmed". Null when there's no pending collection.
     */
    public function pendingPaymentDetail(int $planId): ?array
    {
        $tx = Transaction::latestPendingForPlan($planId, 'collection');
        if (!$tx) {
            return null;
        }
        $res = $this->paystack->verifyCollection((string) $tx['provider_ref'], (int) $tx['amount_pesewas']);
        return [
            'reachable' => (bool) ($res['ok'] ?? false),
            'state' => (string) ($res['state'] ?? 'pending'),
            'paystack_status' => (string) ($res['paystack_status'] ?? ''),
            'message' => (string) ($res['message'] ?? ''),
        ];
    }

    /**
     * Sweep every pending transaction (older than a grace window) and reconcile
     * it. Belt-and-braces for missed webhooks; run from cron and the admin UI.
     */
    public function reconcilePending(int $olderThanMinutes = 0): array
    {
        $actions = [];
        foreach (Transaction::pendingOlderThan($olderThanMinutes) as $tx) {
            $result = $this->reconcileTransaction($tx);
            if ($result !== 'pending') {
                $actions[] = "Tx #{$tx['id']} ({$tx['type']}) -> {$result}";
            }
        }
        return $actions;
    }

    /** Re-check every pending refund on a plan (refund webhooks carry only the charge reference). */
    public function reconcilePlanRefunds(int $planId): void
    {
        foreach (Transaction::forPlan($planId) as $tx) {
            if ($tx['type'] === 'refund' && $tx['status'] === 'pending') {
                $this->reconcileTransaction($tx);
            }
        }
    }

    /**
     * Cancel a plan and refund the customer minus the cancellation fee. Each
     * paid installment is refunded through Paystack back to whatever the
     * customer paid with (MoMo wallet or card).
     */
    public function cancel(int $planId): bool
    {
        // Claim the cancellation atomically so a double-tap can't refund twice.
        $claimed = DB::run("UPDATE plans SET status = 'cancelled' WHERE id = ? AND status = 'active'", [$planId])->rowCount() > 0;
        if (!$claimed) {
            return false;
        }
        $plan = Plan::find($planId);

        $r = $this->refundCollections($plan);
        if ($r['accepted'] === 0 && $r['failed'] > 0) {
            // Nothing could be refunded (provider down?) — keep the plan as it was.
            Plan::setStatus($planId, 'active');
            return false;
        }

        $this->sms->send($plan['customer_phone'], SmsTemplates::refund($plan['product_name'], ghs($r['amount'])));
        return true;
    }

    /**
     * Admin: retry any refunds on a cancelled plan that failed earlier.
     * Returns ['accepted' => int, 'failed' => int, 'amount' => pesewas].
     */
    public function retryRefunds(int $planId): array
    {
        $plan = Plan::find($planId);
        if (!$plan || $plan['status'] !== 'cancelled') {
            return ['accepted' => 0, 'failed' => 0, 'amount' => 0];
        }
        return $this->refundCollections($plan);
    }

    /**
     * Refund every settled collection on the plan that doesn't already have a
     * pending/successful refund. Re-runnable: already-refunded payments are skipped.
     */
    private function refundCollections(array $plan): array
    {
        $feePct = Config::int('CANCEL_FEE_PCT', 5);
        $out = ['accepted' => 0, 'failed' => 0, 'amount' => 0];

        foreach (Transaction::forPlan((int) $plan['id']) as $c) {
            if ($c['type'] !== 'collection' || $c['status'] !== 'success' || !$c['installment_id']
                || Transaction::hasOpenRefund((int) $c['installment_id'])) {
                continue;
            }
            $amount = (int) $c['amount_pesewas'];
            $refund = $amount - intdiv($amount * $feePct, 100);
            if ($refund <= 0) {
                continue;
            }

            $ref = PaystackService::newReference('r', (int) $plan['id'], (int) $c['installment_id']);
            $txId = Transaction::create([
                'type' => 'refund',
                'amount_pesewas' => $refund,
                'phone' => $plan['customer_phone'],
                'plan_id' => (int) $plan['id'],
                'installment_id' => (int) $c['installment_id'],
                'provider_ref' => $ref,
            ]);
            $res = $this->paystack->refund(
                (string) $c['provider_ref'],
                $refund,
                sprintf('Refund: %s (plan #%d)', $plan['product_name'], $plan['id'])
            );
            Transaction::setStatus(
                $txId,
                $res['ok'] ? ($res['instant'] ? 'success' : 'pending') : 'failed',
                $res['external_ref'],
                json_encode($res['raw'])
            );

            if ($res['ok']) {
                $out['accepted']++;
                $out['amount'] += $refund;
            } else {
                $out['failed']++;
                error_log("[refund] plan #{$plan['id']} tx #{$c['id']} refund failed: {$res['reason']}");
            }
        }
        return $out;
    }

    /**
     * Upcoming-payment sweep: text customers a friendly reminder when their next
     * installment is due within $withinDays days. Sent once per installment
     * (guarded by installments.due_reminded_at). Runs automatically each day.
     */
    public function runDueReminders(int $withinDays = 1): array
    {
        $actions = [];
        foreach (Plan::installmentsDueSoon($withinDays) as $i) {
            $days = days_until($i['due_date']);
            $when = match (true) {
                $days <= 0 => 'today',
                $days === 1 => 'tomorrow',
                default => 'on ' . (new \DateTimeImmutable($i['due_date']))->format('l'),
            };
            $this->sms->send(
                $i['customer_phone'],
                SmsTemplates::paymentDueSoon($i['product_name'], ghs((int) $i['amount_pesewas']), $when)
            );
            DB::run('UPDATE installments SET due_reminded_at = NOW() WHERE id = ?', [$i['installment_id']]);
            $actions[] = "Plan #{$i['plan_id']}: due-soon reminder sent";
        }
        return $actions;
    }

    /**
     * Grace-period sweep. Run daily (cron) or from the admin button.
     * Within grace: friendly reminder once. Past grace: flag + notify merchant.
     */
    public function runReminders(): array
    {
        $graceDays = Config::int('GRACE_DAYS', 3);
        $actions = [];

        foreach (Plan::activeWithOverdue() as $plan) {
            $daysOver = -days_until($plan['oldest_due']);
            if ($daysOver <= 0) {
                continue;
            }

            if ($daysOver <= $graceDays) {
                if ($plan['grace_state'] === 'ok') {
                    $payBy = (new \DateTimeImmutable($plan['oldest_due']))
                        ->add(new \DateInterval('P' . $graceDays . 'D'))->format('l');
                    $this->sms->send($plan['customer_phone'],
                        SmsTemplates::missedPayment(ghs((int) $plan['installment_pesewas']), $payBy));
                    DB::run("UPDATE plans SET grace_state = 'grace', grace_notified_at = NOW() WHERE id = ?", [$plan['id']]);
                    $actions[] = "Plan #{$plan['id']}: reminder sent";
                }
            } elseif ($plan['grace_state'] !== 'flagged') {
                DB::run("UPDATE plans SET grace_state = 'flagged' WHERE id = ?", [$plan['id']]);
                $this->sms->send($plan['merchant_phone'],
                    SmsTemplates::planFlaggedMerchant($plan['product_name'], $plan['customer_name']));
                $actions[] = "Plan #{$plan['id']}: flagged, merchant notified";
            }
        }
        return $actions;
    }

    private function isOlderThanHours(string $createdAt, int $hours): bool
    {
        if ($createdAt === '' || $hours <= 0) {
            return false;
        }
        try {
            return (new \DateTimeImmutable($createdAt)) < new \DateTimeImmutable('-' . $hours . ' hours');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
