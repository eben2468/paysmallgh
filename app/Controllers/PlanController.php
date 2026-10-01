<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\PaystackService;
use App\Services\PlanService;

final class PlanController extends Controller
{
    public function start(): void
    {
        $product = Product::find((int) ($_POST['product_id'] ?? 0));
        $choice = $this->validChoice($product, (string) ($_POST['frequency'] ?? ''), (int) ($_POST['count'] ?? 0));
        if ($choice === null) {
            flash('error', 'That payment option isn\'t available for this item. Pick again.');
            redirect($product ? '/product/' . (int) $product['id'] : '/shop');
        }
        [$frequency, $count] = $choice;

        // Guest? Remember exactly which plan they picked, send them to log in, and
        // resume the very same plan afterwards — no going back to re-select.
        if (Auth::userId() === null) {
            $_SESSION['pending_plan'] = [
                'product_id' => (int) $product['id'],
                'frequency' => $frequency,
                'count' => $count,
            ];
            $_SESSION['after_login'] = '/plan/resume';
            flash('error', 'Log in or create a quick account to start your plan — we\'ve saved your pick.');
            redirect('/login');
        }

        Csrf::check();
        $this->beginPlan($this->requireUser(), $product, $frequency, $count);
    }

    /**
     * Resume the plan a guest picked before logging in. Reached via after_login
     * once they authenticate; the intent was captured server-side in start().
     */
    public function resume(): void
    {
        $user = $this->requireUser();
        $intent = $_SESSION['pending_plan'] ?? null;
        unset($_SESSION['pending_plan']);
        if (!is_array($intent)) {
            redirect('/shop');
        }

        $product = Product::find((int) ($intent['product_id'] ?? 0));
        $choice = $this->validChoice($product, (string) ($intent['frequency'] ?? ''), (int) ($intent['count'] ?? 0));
        if ($choice === null) {
            flash('error', 'That plan is no longer available. Pick it again.');
            redirect('/shop');
        }
        [$frequency, $count] = $choice;

        $this->beginPlan($user, $product, $frequency, $count);
    }

    /**
     * Check a customer's pick against what the merchant allows for this item.
     * 'once' (pay in full) is always allowed and is always exactly 1 payment.
     * Returns [frequency, count] or null if the item/option isn't available.
     */
    private function validChoice(?array $product, string $frequency, int $count): ?array
    {
        if (!$product || !$product['active'] || ($product['merchant_status'] ?? '') !== 'approved') {
            return null;
        }
        if ($frequency === 'once') {
            return ['once', 1];
        }
        if (!in_array($frequency, Product::allowedFrequencies($product), true) || $count < 1 || $count > 120) {
            return null;
        }
        return [$frequency, $count];
    }

    /** Shared: recompute the installment, create the plan and go to checkout. */
    private function beginPlan(array $user, array $product, string $frequency, int $count): never
    {
        // Server-side recompute — never trust a posted amount.
        $per = (int) ceil((int) $product['cash_price_pesewas'] / $count);

        $svc = new PlanService();
        [$planId, $result] = $svc->startPlan($user, $product, $per, $frequency, $count);

        if ($result['status'] === 'failed') {
            $reason = !empty($result['reason']) ? ' Reason: ' . $result['reason'] : '';
            flash('error', 'Couldn\'t open the payment page, so no plan was started.' . $reason);
            redirect('/product/' . $product['id']);
        }
        // Send the customer to the payment page (Paystack hosted checkout, or
        // the local mock checkout). Absolute URLs go out as-is; relative ones
        // resolve against the current host.
        if (!empty($result['redirect'])) {
            $this->goToCheckout($result['redirect']);
        }
        // Fallback (shouldn't normally happen): payment already settled.
        flash('success', 'First payment received — your plan don start!');
        redirect('/plan/' . $planId);
    }

    /**
     * Redirect to a checkout URL. An absolute Paystack URL (https://checkout.paystack.com/…)
     * goes out as-is; a relative mock path resolves against the current host so
     * it works on any port/base the app is served from.
     */
    private function goToCheckout(string $to): never
    {
        if (str_starts_with($to, 'http://') || str_starts_with($to, 'https://')) {
            redirect_external($to);
        }
        redirect($to);
    }

    /**
     * Where Paystack sends the customer after checkout (?reference=…).
     *
     * Confirms the payment FIRST, without needing a login: on phones the
     * customer often comes back in a different browser (a MoMo or banking
     * app's in-app browser) that doesn't have their login cookie. The
     * reference only matches a payment we created, and the result always
     * comes from Paystack's API, so this is safe for anyone to hit. Then:
     * logged in as the owner -> straight to the plan; otherwise -> log in,
     * and come back to the plan after.
     */
    public function paymentReturn(): void
    {
        $ref = (string) ($_GET['reference'] ?? $_GET['trxref'] ?? '');
        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        if (!$tx || $tx['type'] !== 'collection' || !$tx['plan_id']) {
            redirect(Auth::userId() ? '/plans' : '/login');
        }

        $result = (new PlanService())->reconcileTransaction($tx);
        $plan = Plan::find((int) $tx['plan_id']);
        $planPath = '/plan/' . (int) $tx['plan_id'];

        if (Auth::userId() !== null && $plan && (int) $plan['customer_id'] === Auth::userId()) {
            $this->flashPaymentResult($result);
            redirect($planPath);
        }

        // Not logged in on this browser: the payment is already confirmed above.
        $_SESSION['after_login'] = $planPath;
        flash('error', match ($result) {
            'active', 'completed' => 'Payment received — thank you! Log in to see your plan and receipt.',
            'failed' => 'That payment didn\'t go through. Log in to try again.',
            default => 'Your payment is still processing. Log in to check on your plan.',
        });
        redirect('/login');
    }

    public function index(): void
    {
        $user = $this->requireUser();
        $this->render('plans/index', [
            'title' => 'My plans — PaySmallSmall',
            'plans' => Plan::forCustomer((int) $user['id']),
            'user' => $user,
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->requireUser();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Plan not found']);
            return;
        }

        // Back from Paystack checkout (?trxref=…&reference=…): verify that
        // payment right now so the customer sees the result immediately. Only
        // references belonging to this plan are looked at.
        $ref = (string) ($_GET['reference'] ?? $_GET['trxref'] ?? '');
        if ($ref !== '') {
            $tx = Transaction::findByRef($ref);
            if ($tx && (int) $tx['plan_id'] === (int) $plan['id'] && $tx['type'] === 'collection') {
                $result = (new PlanService())->reconcileTransaction($tx);
                $this->flashPaymentResult($result);
            }
            redirect('/plan/' . $plan['id']);
        }

        $this->render('plans/show', [
            'title' => $plan['product_name'] . ' plan — PaySmallSmall',
            'plan' => $plan,
            'installments' => Installment::forPlan((int) $plan['id']),
            'pendingTx' => Transaction::latestPendingForPlan((int) $plan['id'], 'collection'),
            'canResumeCheckout' => (new PlanService())->resumableCheckout((int) $plan['id']) !== null,
        ]);
    }

    public function pay(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']
            || !in_array($plan['status'], ['active', 'pending'], true)) {
            flash('error', 'That plan can\'t take a payment right now.');
            redirect('/plans');
        }

        $svc = new PlanService();

        // Don't fire a second charge while one is still open. If it's a web
        // checkout, send them back to that same payment page to finish it.
        if (Transaction::latestPendingForPlan((int) $plan['id'], 'collection')) {
            $resume = $svc->resumableCheckout((int) $plan['id']);
            if ($resume !== null) {
                $this->goToCheckout($resume);
            }
            flash('error', 'A payment on this plan is still being confirmed. Approve the MoMo prompt on your phone, then check its status.');
            redirect('/plan/' . $plan['id']);
        }

        $result = $svc->checkoutInstallment((int) $plan['id']);

        if ($result['status'] === 'failed') {
            $reason = !empty($result['reason']) ? ' Reason: ' . $result['reason'] : '';
            flash('error', 'Couldn\'t open the payment page.' . $reason);
            redirect('/plan/' . $plan['id']);
        }
        // Send the customer to the payment page (Paystack hosted checkout, or
        // the local mock checkout).
        if (!empty($result['redirect'])) {
            $this->goToCheckout($result['redirect']);
        }
        flash('success', 'Payment received. Check your SMS receipt.');
        redirect('/plan/' . $plan['id']);
    }

    /**
     * A concrete, human explanation of why a payment hasn't confirmed yet, pulled
     * live from Paystack — so "Not confirmed yet" isn't a dead end.
     */
    private function pendingReason(PlanService $svc, int $planId): string
    {
        $d = $svc->pendingPaymentDetail($planId);
        if (!$d) {
            return ' Wait a moment and check again.';
        }
        if (!$d['reachable']) {
            return ' We couldn\'t reach the payment provider — try again shortly.';
        }
        if ($d['paystack_status'] === 'abandoned' || $d['paystack_status'] === '') {
            return ' You haven\'t finished on the payment page yet. Tap Pay to go back to it.';
        }
        $msg = $d['message'] !== '' ? $d['message'] : 'still processing';
        return ' Payment provider says: "' . $msg . '". If you completed payment, give it a minute and check again.';
    }

    /** "I've approved the MoMo prompt — check now" — status-check fallback. */
    public function check(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            redirect('/plans');
        }

        $svc = new PlanService();
        $result = $svc->checkPlanPayment((int) $plan['id']);

        if ($result === 'pending') {
            flash('error', 'Not confirmed yet.' . $this->pendingReason($svc, (int) $plan['id']));
        } else {
            $this->flashPaymentResult($result);
        }
        redirect('/plan/' . $plan['id']);
    }

    /** Customer-facing message for the outcome of a payment check. */
    private function flashPaymentResult(string $result): void
    {
        match ($result) {
            'completed' => flash('success', 'Payment confirmed — that was the last one! The item is fully yours. Check your SMS.'),
            'active' => flash('success', 'Payment confirmed — your plan is up to date. Check your SMS receipt.'),
            'pending' => flash('error', 'Your payment is still processing. We\'ll confirm it here the moment it clears.'),
            'failed' => flash('error', 'That payment didn\'t go through. You can try paying again.'),
            default => flash('error', 'Nothing to confirm on this plan right now.'),
        };
        if (in_array($result, ['active', 'completed'], true)) {
            flash('stamped', '1'); // fire the PAID stamp on the receipt
        }
    }

    /**
     * Background poll for the plan page (JSON). Reconciles the latest pending
     * collection against Paystack and reports where the plan stands, so the UI can
     * confirm a payment the moment it clears without the customer tapping "I've
     * paid". Idempotent — same reconcile path as check(), safe to call on a timer.
     */
    public function status(string $id): void
    {
        $user = $this->requireUser();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            $this->json(['ok' => false], 404);
        }

        $pending = Transaction::latestPendingForPlan((int) $plan['id'], 'collection');
        $result = $pending ? (new PlanService())->reconcileTransaction($pending) : (string) $plan['status'];

        // "confirmed" = money landed and the UI should refresh to show it.
        $confirmed = in_array($result, ['active', 'completed', 'success'], true);
        if ($confirmed && $pending !== null) {
            // Fire the PAID stamp + receipt message on the reload the JS triggers.
            flash('stamped', '1');
            flash('success', $result === 'completed'
                ? 'Payment confirmed — that was the last one! The item is fully yours.'
                : 'Payment confirmed — your plan is up to date. Check your SMS receipt.');
        }
        $this->json([
            'ok' => true,
            'state' => $result,
            'pending' => !$confirmed && $pending !== null,
            'confirmed' => $confirmed,
        ]);
    }

    /**
     * Local stand-in for Paystack's hosted checkout — mock mode only. Lets the
     * full redirect checkout be demoed end-to-end without spending money.
     */
    public function mockCheckout(): void
    {
        $user = $this->requireUser();
        if (!(new PaystackService())->isMock()) {
            redirect('/plans');
        }
        $ref = (string) ($_GET['ref'] ?? '');
        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        $plan = $tx ? Plan::find((int) $tx['plan_id']) : null;
        if (!$tx || !$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            flash('error', 'That checkout link is not valid.');
            redirect('/plans');
        }
        $this->render('checkout/mock', [
            'title' => 'Complete payment — PaySmallSmall',
            'tx' => $tx,
            'plan' => $plan,
            'ref' => $ref,
        ]);
    }

    /** Confirm the mock payment and bounce back to the plan. Mock mode only. */
    public function mockConfirm(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        if (!(new PaystackService())->isMock()) {
            redirect('/plans');
        }
        $ref = (string) ($_POST['ref'] ?? '');
        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        $plan = $tx ? Plan::find((int) $tx['plan_id']) : null;
        if (!$tx || !$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            flash('error', 'That checkout link is not valid.');
            redirect('/plans');
        }

        if ($tx['status'] === 'pending') {
            Transaction::setStatus((int) $tx['id'], 'success', 'MOCK-' . strtoupper(bin2hex(random_bytes(4))), json_encode(['mode' => 'mock']));
            $result = (new PlanService())->applyCollectionSuccess((int) $tx['id']);
            flash('stamped', '1');
            flash('success', $result === 'completed'
                ? 'That was your last payment — the item is fully yours! Check your SMS.'
                : 'Payment received. Check your SMS receipt.');
        }
        redirect('/plan/' . $plan['id']);
    }

    public function cancel(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            redirect('/plans');
        }

        $svc = new PlanService();
        if ($svc->cancel((int) $plan['id'])) {
            flash('success', 'Plan cancelled. Your refund is on its way back to the MoMo wallet or card you paid with.');
        } else {
            flash('error', 'This plan can\'t be cancelled right now.');
        }
        redirect('/plans');
    }

    /** Delete a plan the customer never actually paid into. */
    public function delete(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $plan = Plan::find((int) $id);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            redirect('/plans');
        }

        if ((int) $plan['installments_paid'] > 0) {
            flash('error', 'This plan already has payments, so it can\'t be deleted. Cancel it for a refund instead.');
            redirect('/plans');
        }

        if (Plan::delete((int) $plan['id'])) {
            flash('success', 'Plan deleted.');
        } else {
            flash('error', 'That plan can\'t be deleted.');
        }
        redirect('/plans');
    }
}
