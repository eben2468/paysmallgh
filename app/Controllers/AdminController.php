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

    /** Suspend an approved shop (its products stop showing to customers). */
    public function suspendMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        if ($merchant && $merchant['status'] === 'approved') {
            Merchant::setStatus((int) $id, 'suspended');
            flash('success', $merchant['shop_name'] . ' suspended.');
        }
        redirect('/admin');
    }

    /** Re-approve a suspended shop. */
    public function reactivateMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        if ($merchant && $merchant['status'] === 'suspended') {
            Merchant::setStatus((int) $id, 'approved');
            flash('success', $merchant['shop_name'] . ' reactivated.');
        }
        redirect('/admin');
    }

    /** Mark a merchant's identity as verified (KYC checked) — shows the trust badge. */
    public function verifyMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        if ($merchant && !$merchant['verified']) {
            Merchant::setVerified((int) $id, true);
            flash('success', $merchant['shop_name'] . ' is now verified.');
        }
        redirect('/admin');
    }

    /** Remove a merchant's verified status. */
    public function unverifyMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        if ($merchant && $merchant['verified']) {
            Merchant::setVerified((int) $id, false);
            flash('success', $merchant['shop_name'] . '\'s verified badge removed.');
        }
        redirect('/admin');
    }

    /**
     * Stream a merchant's uploaded Ghana Card image. Admin-only — the file lives
     * outside the webroot so this is the only way to see it.
     */
    public function idCard(string $id): void
    {
        $this->requireAdmin();
        $merchant = Merchant::find((int) $id);
        $rel = $merchant['id_card_path'] ?? '';
        // Guard against path traversal; only serve from the id_cards folder.
        if ($rel === '' || !preg_match('#^id_cards/[A-Za-z0-9._-]+$#', $rel)) {
            http_response_code(404);
            exit('No ID on file.');
        }
        $path = BASE_PATH . '/storage/' . $rel;
        if (!is_file($path)) {
            http_response_code(404);
            exit('No ID on file.');
        }
        $mime = match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    /** Full profile for one merchant: KYC docs, products and customer plans. */
    public function merchantDetail(string $id): void
    {
        $this->requireAdmin();
        $merchant = Merchant::find((int) $id);
        if (!$merchant) {
            flash('error', 'No such merchant.');
            redirect('/admin');
        }
        $this->render('admin/merchant', [
            'title' => $merchant['shop_name'] . ' — Admin',
            'merchant' => $merchant,
            'products' => \App\Models\Product::forMerchant((int) $id),
            'plans' => Plan::forMerchant((int) $id),
        ]);
    }

    /** Full profile for one customer: their details and every plan they hold. */
    public function userDetail(string $id): void
    {
        $this->requireAdmin();
        $user = \App\Models\User::find((int) $id);
        if (!$user) {
            flash('error', 'No such customer.');
            redirect('/admin/users');
        }
        $this->render('admin/user', [
            'title' => $user['name'] . ' — Admin',
            'user' => $user,
            'plans' => Plan::forCustomer((int) $id),
        ]);
    }

    /** Everyone who has signed up to buy — with a bit of activity per person. */
    public function users(): void
    {
        $this->requireAdmin();
        $this->render('admin/users', [
            'title' => 'Customers — Admin',
            'users' => \App\Models\User::allWithStats(),
        ]);
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

    /** Full detail on one plan: schedule timeline + its ledger rows. */
    public function planDetail(string $id): void
    {
        $this->requireAdmin();
        $plan = Plan::find((int) $id);
        if (!$plan) {
            flash('error', 'No such plan.');
            redirect('/admin/plans');
        }
        $this->render('admin/plan', [
            'title' => 'Plan #' . (int) $id . ' — Admin',
            'plan' => $plan,
            'installments' => \App\Models\Installment::forPlan((int) $id),
            'transactions' => Transaction::forPlan((int) $id),
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
        $svc = new PlanService();
        $actions = array_merge($svc->runDueReminders(1), $svc->runReminders());
        flash('success', $actions ? implode(' · ', $actions) : 'Nothing due or overdue — all plans on track.');
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
