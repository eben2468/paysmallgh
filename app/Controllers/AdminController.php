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
        $moolre = new MoolreService();
        $this->render('admin/dashboard', [
            'title' => 'Admin — PaySmallSmall',
            'merchants' => Merchant::all(),
            'mode' => $moolre->mode(),
            'sms' => [
                'live' => $moolre->smsIsLive(),
                'sender' => $moolre->smsSender(),
                'has_key' => Config::get('MOOLRE_VAS_KEY', '') !== '',
                'endpoint' => rtrim(Config::get('MOOLRE_BASE_URL', ''), '/') . Config::get('MOOLRE_PATH_SMS', '/open/sms/send'),
            ],
        ]);
    }

    /**
     * Send a real test SMS through Moolre to confirm the integration works.
     * Always hits the live API (forceLive) so it verifies even when SMS_MODE=mock.
     */
    public function testSms(): void
    {
        $this->requireAdmin();
        Csrf::check();

        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($message === '') {
            $message = 'PaySmallSmall test: your SMS setup is working. Reply STOP to opt out.';
        }
        if ($phone === null) {
            flash('error', 'That phone number doesn\'t look right. Use 024XXXXXXX or 233XXXXXXXXX.');
            redirect('/admin');
        }
        if (mb_strlen($message) > 160) {
            $message = mb_substr($message, 0, 160);
        }

        $ok = (new MoolreService())->sms($phone, $message, forceLive: true);
        flash(
            $ok ? 'success' : 'error',
            $ok
                ? 'Test SMS accepted by Moolre for ' . pretty_phone($phone) . '. Check the phone and the SMS log.'
                : 'Moolre rejected the SMS. Check the VAS key and that your Sender ID is approved (see the SMS log for the recorded attempt).'
        );
        redirect('/admin');
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

    /** Poll Moolre for delivery status of pending SMS and update the log. */
    public function pollSms(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $s = (new MoolreService())->refreshSmsDelivery(100);
        if ($s['checked'] === 0 && $s['pending'] === 0) {
            flash('success', 'No SMS awaiting a delivery update.');
        } else {
            flash('success', "Delivery check: {$s['delivered']} delivered, {$s['failed']} failed, {$s['pending']} still pending.");
        }
        redirect('/admin/ledger');
    }

    public function plans(): void
    {
        $this->requireAdmin();
        $this->render('admin/plans', [
            'title' => 'All plans — Admin',
            'plans' => Plan::all(),
            'mode' => (new MoolreService())->mode(),
            'pending' => Transaction::pendingCount(),
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

    /** Status-check every pending payment now (fallback for missed webhooks). */
    public function reconcile(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $actions = (new PlanService())->reconcilePending(0);
        flash('success', $actions ? implode(' · ', $actions) : 'No pending payments needed settling.');
        redirect('/admin/plans');
    }
}
