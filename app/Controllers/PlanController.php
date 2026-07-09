<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\PlanService;

final class PlanController extends Controller
{
    public function start(): void
    {
        $user = $this->requireUser();
        Csrf::check();

        $product = Product::find((int) ($_POST['product_id'] ?? 0));
        $weeks = (int) ($_POST['weeks'] ?? 0);
        if (!$product || !$product['active'] || $weeks < 1 || $weeks > 52) {
            flash('error', 'Something went wrong with that plan. Try again.');
            redirect('/shop');
        }

        // Server-side recompute — never trust a posted amount.
        $per = (int) ceil((int) $product['cash_price_pesewas'] / $weeks);

        $svc = new PlanService();
        [$planId, $result] = $svc->startPlan($user, $product, $per, 'weekly', $weeks);

        if ($result === 'failed') {
            flash('error', 'The first payment didn\'t go through, so no plan was started. Check your MoMo balance and try again.');
            redirect('/product/' . $product['id']);
        }
        if ($result === 'awaiting_payment') {
            flash('success', 'Almost there — approve the MoMo prompt on your phone to start the plan.');
        } else {
            flash('success', 'First payment received — your plan don start!');
        }
        redirect('/plan/' . $planId);
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
        $result = $svc->collectInstallment((int) $plan['id']);

        match ($result) {
            'completed' => flash('success', 'That was your last payment — the item is fully yours! Check your SMS.'),
            'active' => flash('success', 'Payment received. Check your SMS receipt.'),
            'awaiting_payment' => flash('success', 'Approve the MoMo prompt on your phone, then tap "I\'ve paid" to confirm.'),
            default => flash('error', 'Payment didn\'t go through. Check your MoMo balance and try again.'),
        };
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
