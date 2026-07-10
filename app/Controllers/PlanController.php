<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\MoolreService;
use App\Services\PlanService;

final class PlanController extends Controller
{
    public function start(): void
    {
        $user = $this->requireUser();
        Csrf::check();

        $product = Product::find((int) ($_POST['product_id'] ?? 0));
        $frequency = (string) ($_POST['frequency'] ?? 'weekly');
        $count = (int) ($_POST['count'] ?? 0);
        if (!in_array($frequency, ['daily', 'weekly', 'monthly'], true)) {
            $frequency = 'weekly';
        }
        if (!$product || !$product['active'] || $count < 1 || $count > 120) {
            flash('error', 'Something went wrong with that plan. Try again.');
            redirect('/shop');
        }

        // Server-side recompute — never trust a posted amount.
        $per = (int) ceil((int) $product['cash_price_pesewas'] / $count);

        $svc = new PlanService();
        [$planId, $result] = $svc->startPlan($user, $product, $per, $frequency, $count);

        if ($result['status'] === 'failed') {
            flash('error', 'Couldn\'t open the payment page, so no plan was started. Try again in a moment.');
            redirect('/product/' . $product['id']);
        }
        // Send the customer to the payment page (Moolre hosted URL, or the local
        // mock checkout). Absolute URLs go out as-is; relative ones resolve
        // against the current host.
        if (!empty($result['redirect'])) {
            $this->goToCheckout($result['redirect']);
        }
        // Fallback (shouldn't normally happen): payment already settled.
        flash('success', 'First payment received — your plan don start!');
        redirect('/plan/' . $planId);
    }

    /**
     * Redirect to a checkout URL. An absolute Moolre URL (https://pos.moolre…)
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
        $this->render('plans/show', [
            'title' => $plan['product_name'] . ' plan — PaySmallSmall',
            'plan' => $plan,
            'installments' => Installment::forPlan((int) $plan['id']),
            'pendingTx' => Transaction::latestPendingForPlan((int) $plan['id'], 'collection'),
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

        // Don't fire a second charge while one is still awaiting confirmation.
        if (Transaction::latestPendingForPlan((int) $plan['id'], 'collection')) {
            flash('error', 'A payment on this plan is still being confirmed. Give it a moment, then check its status.');
            redirect('/plan/' . $plan['id']);
        }

        $svc = new PlanService();
        $result = $svc->checkoutInstallment((int) $plan['id']);

        if ($result['status'] === 'failed') {
            flash('error', 'Couldn\'t open the payment page. Give it a moment and try again.');
            redirect('/plan/' . $plan['id']);
        }
        // Send the customer to the payment page (Moolre hosted URL, or the local
        // mock checkout).
        if (!empty($result['redirect'])) {
            $this->goToCheckout($result['redirect']);
        }
        flash('success', 'Payment received. Check your SMS receipt.');
        redirect('/plan/' . $plan['id']);
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

        match ($result) {
            'completed' => flash('success', 'Payment confirmed — that was the last one! The item is fully yours. Check your SMS.'),
            'active' => flash('success', 'Payment confirmed — your plan is up to date. Check your SMS receipt.'),
            'pending' => flash('error', 'Not confirmed yet. If you\'ve approved the prompt, wait a minute and check again.'),
            'failed' => flash('error', 'That payment didn\'t go through. You can try paying again.'),
            default => flash('error', 'Nothing to confirm on this plan right now.'),
        };
        if (in_array($result, ['active', 'completed'], true)) {
            flash('stamped', '1'); // fire the PAID stamp on the receipt
        }
        redirect('/plan/' . $plan['id']);
    }

    /**
     * Background poll for the plan page (JSON). Reconciles the latest pending
     * collection against Moolre and reports where the plan stands, so the UI can
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
     * Local stand-in for Moolre's hosted payment page — mock mode only. Lets the
     * full redirect checkout be demoed end-to-end without spending money.
     */
    public function mockCheckout(): void
    {
        $user = $this->requireUser();
        if (!(new MoolreService())->isMock()) {
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
        if (!(new MoolreService())->isMock()) {
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
            flash('success', 'Plan cancelled. Your refund is on its way to your MoMo.');
        } else {
            flash('error', 'This plan can\'t be cancelled right now.');
        }
        redirect('/plans');
    }
}
