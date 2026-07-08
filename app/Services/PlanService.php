<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database as DB;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\Transaction;

/**
 * All plan money movements live here: starting a plan, recording installment
 * payments, triggering the merchant payout, cancellations and reminders.
 */
final class PlanService
{
    private MoolreService $moolre;

    public function __construct(?MoolreService $moolre = null)
    {
        $this->moolre = $moolre ?? new MoolreService();
    }

    /**
     * Create a plan and charge the first installment. No payment, no plan:
     * the plan stays 'pending' until the first collection is confirmed.
     * Returns [planId, 'active'|'awaiting_payment'|'failed'].
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

        $result = $this->collectInstallment($planId);
        return [$planId, $result];
    }

    /**
     * Charge the next unpaid installment on a plan.
     * Returns 'active' | 'completed' (mock, applied instantly),
     * 'awaiting_payment' (sandbox/live, webhook will confirm) or 'failed'.
     */
    public function collectInstallment(int $planId): string
    {
        $plan = Plan::find($planId);
        $inst = Installment::nextUnpaid($planId);
        if (!$plan || !$inst) {
            return 'failed';
        }

        $ref = sprintf('PSS-C-%d-%d-%s', $planId, $inst['number'], strtoupper(bin2hex(random_bytes(3))));
        $txId = Transaction::create([
            'type' => 'collection',
            'amount_pesewas' => (int) $inst['amount_pesewas'],
            'phone' => $plan['customer_phone'],
            'plan_id' => $planId,
            'installment_id' => (int) $inst['id'],
            'provider_ref' => $ref,
        ]);

        $desc = sprintf('%s — payment %d of %d', $plan['product_name'], $inst['number'], $plan['installments_total']);
        $res = $this->moolre->collect($plan['customer_phone'], (int) $inst['amount_pesewas'], $ref, $desc);

        if (!$res['ok']) {
            Transaction::setStatus($txId, 'failed', $res['external_ref'], json_encode($res['raw']));
            return 'failed';
        }

        if ($res['instant']) {
            // Mock mode: run the same confirmation path a webhook would trigger.
            Transaction::setStatus($txId, 'success', $res['external_ref'], json_encode($res['raw']));
            return $this->applyCollectionSuccess($txId);
        }

        Transaction::setStatus($txId, 'pending', $res['external_ref'], json_encode($res['raw']));
        return 'awaiting_payment';
    }

    /**
     * A collection was confirmed (webhook or mock). Idempotent: replays and
     * double-calls cannot double-credit an installment.
     * Returns the plan's resulting status.
     */
    public function applyCollectionSuccess(int $txId): string
    {
        $tx = Transaction::find($txId);
        if (!$tx || !$tx['installment_id']) {
            return 'failed';
        }

        // markPaid only succeeds once per installment — the idempotency gate.
        if (!Installment::markPaid((int) $tx['installment_id'], $txId)) {
            $plan = Plan::find((int) $tx['plan_id']);
            return $plan['status'] ?? 'failed';
        }

        DB::run('UPDATE plans SET installments_paid = installments_paid + 1 WHERE id = ?', [$tx['plan_id']]);
        $plan = Plan::find((int) $tx['plan_id']);

        if ($plan['status'] === 'pending') {
            Plan::setStatus((int) $plan['id'], 'active');
            $this->moolre->sms($plan['customer_phone'], SmsTemplates::planStarted(
                $plan['product_name'],
                ghs((int) $plan['installment_pesewas']),
                $plan['frequency'],
                (int) $plan['installments_total']
            ));
        } else {
            $paid = (int) $plan['installments_paid'];
            $left = ((int) $plan['installments_total'] - $paid) * (int) $plan['installment_pesewas'];
            $this->moolre->sms($plan['customer_phone'], SmsTemplates::receipt(
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
     * All installments paid: pay the merchant (minus platform fee). The plan
     * is only marked completed when the disbursement succeeds.
     */
    public function completeAndPayout(int $planId): string
    {
        $plan = Plan::find($planId);
        if (!$plan || $plan['status'] === 'completed') {
            return 'completed';
        }

        $gross = (int) $plan['installment_pesewas'] * (int) $plan['installments_total'];
        $feePct = Config::int('PLATFORM_FEE_PCT', 5);
        $payout = $gross - intdiv($gross * $feePct, 100);

        $ref = sprintf('PSS-D-%d-%s', $planId, strtoupper(bin2hex(random_bytes(3))));
        $txId = Transaction::create([
            'type' => 'disbursement',
            'amount_pesewas' => $payout,
            'phone' => $plan['payout_number'] ?: $plan['merchant_phone'],
            'plan_id' => $planId,
            'merchant_id' => (int) $plan['merchant_id'],
            'provider_ref' => $ref,
        ]);

        $res = $this->moolre->disburse(
            $plan['payout_channel'],
            $plan['payout_number'] ?: $plan['merchant_phone'],
            $payout,
            $ref,
            sprintf('Payout: %s (plan #%d)', $plan['product_name'], $planId)
        );

        if (!$res['ok']) {
            Transaction::setStatus($txId, 'failed', $res['external_ref'], json_encode($res['raw']));
            return 'active'; // stays active; admin can retry via simulate/status tools
        }

        Transaction::setStatus($txId, $res['instant'] ? 'success' : 'pending', $res['external_ref'], json_encode($res['raw']));

        if ($res['instant']) {
            $this->finalizePayout($planId, $txId);
            return 'completed';
        }
        return 'active'; // completed once the disbursement webhook lands
    }

    /** Disbursement confirmed — mark the plan done and tell both sides. */
    public function finalizePayout(int $planId, int $txId): void
    {
        $plan = Plan::find($planId);
        if (!$plan || $plan['status'] === 'completed') {
            return;
        }
        DB::run('UPDATE plans SET status = \'completed\', completed_at = NOW(), payout_transaction_id = ? WHERE id = ?', [$txId, $planId]);

        $tx = Transaction::find($txId);
        $this->moolre->sms($plan['customer_phone'], SmsTemplates::planCompleteCustomer($plan['product_name'], $plan['shop_name']));
        $this->moolre->sms($plan['merchant_phone'], SmsTemplates::planCompleteMerchant(
            $plan['product_name'],
            ghs((int) $tx['amount_pesewas']),
            $plan['customer_name']
        ));
    }

    /**
     * Cancel a plan and refund the customer minus the cancellation fee.
     */
    public function cancel(int $planId): bool
    {
        $plan = Plan::find($planId);
        if (!$plan || !in_array($plan['status'], ['active'], true)) {
            return false;
        }

        $paid = (int) $plan['installments_paid'] * (int) $plan['installment_pesewas'];
        $feePct = Config::int('CANCEL_FEE_PCT', 5);
        $refund = $paid - intdiv($paid * $feePct, 100);

        if ($refund > 0) {
            $ref = sprintf('PSS-R-%d-%s', $planId, strtoupper(bin2hex(random_bytes(3))));
            $txId = Transaction::create([
                'type' => 'refund',
                'amount_pesewas' => $refund,
                'phone' => $plan['customer_phone'],
                'plan_id' => $planId,
                'provider_ref' => $ref,
            ]);
            $res = $this->moolre->disburse('momo', $plan['customer_phone'], $refund, $ref,
                sprintf('Refund: %s (plan #%d)', $plan['product_name'], $planId));
            Transaction::setStatus($txId, $res['ok'] ? ($res['instant'] ? 'success' : 'pending') : 'failed',
                $res['external_ref'], json_encode($res['raw']));
            if (!$res['ok']) {
                return false;
            }
        }

        Plan::setStatus($planId, 'cancelled');
        $this->moolre->sms($plan['customer_phone'], SmsTemplates::refund($plan['product_name'], ghs(max(0, $refund))));
        return true;
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
                    $this->moolre->sms($plan['customer_phone'],
                        SmsTemplates::missedPayment(ghs((int) $plan['installment_pesewas']), $payBy));
                    DB::run("UPDATE plans SET grace_state = 'grace', grace_notified_at = NOW() WHERE id = ?", [$plan['id']]);
                    $actions[] = "Plan #{$plan['id']}: reminder sent";
                }
            } elseif ($plan['grace_state'] !== 'flagged') {
                DB::run("UPDATE plans SET grace_state = 'flagged' WHERE id = ?", [$plan['id']]);
                $this->moolre->sms($plan['merchant_phone'],
                    SmsTemplates::planFlaggedMerchant($plan['product_name'], $plan['customer_name']));
                $actions[] = "Plan #{$plan['id']}: flagged, merchant notified";
            }
        }
        return $actions;
    }
}
