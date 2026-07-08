<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\SmsLog;
use App\Models\Transaction;
use App\Services\MoolreService;
use App\Services\PlanService;
use App\Services\SmsTemplates;

final class AdminController extends Controller
{
    public function loginForm(): void
    {
        $this->render('admin/login', ['title' => 'Admin — PaySmallSmall']);
    }

    public function login(): void
    {
        Csrf::check();
        $phone = normalize_phone((string) ($_POST['phone'] ?? '')) ?? (string) ($_POST['phone'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($phone === Config::get('ADMIN_PHONE') && hash_equals(Config::get('ADMIN_PASSWORD', ''), $password)) {
            Auth::loginAdmin();
            redirect('/admin');
        }
        flash('error', 'No.');
        redirect('/admin/login');
    }

    public function dashboard(): void
    {
        $this->requireAdmin();
        $this->render('admin/dashboard', [
            'title' => 'Admin — PaySmallSmall',
            'merchants' => Merchant::all(),
            'mode' => (new MoolreService())->mode(),
        ]);
    }

    public function approveMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        if ($merchant && $merchant['status'] === 'pending') {
            Merchant::approve((int) $id);
            (new MoolreService())->sms($merchant['phone'], SmsTemplates::merchantApproved($merchant['shop_name']));
            flash('success', $merchant['shop_name'] . ' approved.');
        }
        redirect('/admin');
    }

    public function plans(): void
    {
        $this->requireAdmin();
        $this->render('admin/plans', [
            'title' => 'All plans — Admin',
            'plans' => Plan::all(),
            'mode' => (new MoolreService())->mode(),
        ]);
    }

    public function ledger(): void
    {
        $this->requireAdmin();
        $this->render('admin/ledger', [
            'title' => 'Ledger — Admin',
            'transactions' => Transaction::ledger(),
            'sms' => SmsLog::recent(50),
        ]);
    }

    /**
     * Mock-mode demo button: pay the next installment on a plan as if the
     * customer had approved a MoMo prompt.
     */
    public function simulatePayment(string $planId): void
    {
        $this->requireAdmin();
        Csrf::check();
        if ((new MoolreService())->mode() !== 'mock') {
            flash('error', 'Simulate is only available in mock mode.');
            redirect('/admin/plans');
        }

        $svc = new PlanService();
        $result = $svc->collectInstallment((int) $planId);
        flash($result === 'failed' ? 'error' : 'success', "Plan #{$planId}: {$result}");
        redirect('/admin/plans');
    }

    /** Run the grace-period reminder sweep by hand. */
    public function runReminders(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $actions = (new PlanService())->runReminders();
        flash('success', $actions ? implode(' · ', $actions) : 'Nothing overdue — all plans on track.');
        redirect('/admin/plans');
    }
}
