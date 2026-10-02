<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Installment;
use App\Models\Merchant;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SmsLog;
use App\Models\Stats;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaystackService;
use App\Services\PlanService;
use App\Services\SmsService;
use App\Services\SmsTemplates;

final class AdminController extends Controller
{
    public function loginForm(): void
    {
        if (Auth::isAdmin()) {
            redirect('/admin');
        }
        $this->render('admin/login', ['title' => 'Admin log in — PaySmallSmall']);
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
        flash('error', 'That phone number and password don\'t match.');
        redirect('/admin/login');
    }

    /** Log the admin out (customer/merchant logins on this browser are kept). */
    public function logout(): void
    {
        Csrf::check();
        Auth::logout('admin');
        flash('success', 'You\'re logged out of admin.');
        redirect('/admin/login');
    }

    // ---- Overview ----

    public function dashboard(): void
    {
        $this->requireAdmin();
        $pendingMerchants = array_values(array_filter(Merchant::all(), fn($m) => $m['status'] === 'pending'));
        $flagged = array_values(array_filter(Plan::all(), fn($p) => $p['status'] === 'active' && $p['grace_state'] !== 'ok'));

        $this->renderPortal('admin', 'admin/dashboard', [
            'title' => 'Dashboard — Admin',
            'stats' => Stats::adminOverview(),
            'pendingMerchants' => array_slice($pendingMerchants, 0, 5),
            'attentionPlans' => array_slice($flagged, 0, 5),
            'recent' => Transaction::ledger(8),
            'integrations' => $this->integrations(),
        ]);
    }

    // ---- Merchants ----

    public function merchants(): void
    {
        $this->requireAdmin();
        $all = Merchant::all();
        $filter = (string) ($_GET['status'] ?? 'all');
        $counts = ['all' => count($all), 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'suspended' => 0];
        foreach ($all as $m) {
            $counts[$m['status']] = ($counts[$m['status']] ?? 0) + 1;
        }
        if (!isset($counts[$filter])) {
            $filter = 'all';
        }
        $this->renderPortal('admin', 'admin/merchants', [
            'title' => 'Merchants — Admin',
            'merchants' => $filter === 'all' ? $all : array_values(array_filter($all, fn($m) => $m['status'] === $filter)),
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function approveMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        // A declined shop can be approved straight away too (admin changed their mind).
        if ($merchant && in_array($merchant['status'], ['pending', 'rejected'], true)) {
            Merchant::approve((int) $id);
            (new SmsService())->send($merchant['phone'], SmsTemplates::merchantApproved($merchant['shop_name']));
            flash('success', $merchant['shop_name'] . ' approved — their products are now live.');
        }
        redirect_back('/admin/merchants');
    }

    /**
     * Not approved (yet): the shop stays hidden and the owner gets the reason
     * by SMS and on their dashboard, so they can fix it and ask again.
     */
    public function declineMerchant(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $merchant = Merchant::find((int) $id);
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        if (!$merchant || $merchant['status'] !== 'pending') {
            redirect_back('/admin/merchants');
        }
        if ($note === '') {
            flash('error', 'Say why, so the shop knows what to fix (e.g. "Ghana Card photo is blurry").');
            redirect_back('/admin/merchant/' . (int) $id);
        }
        Merchant::decline((int) $id, $note);
        (new SmsService())->send($merchant['phone'], SmsTemplates::merchantDeclined($merchant['shop_name'], $note));
        flash('success', $merchant['shop_name'] . ' declined. We texted them the reason.');
        redirect_back('/admin/merchants');
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
        redirect_back('/admin/merchants');
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
        redirect_back('/admin/merchants');
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
        redirect_back('/admin/merchants');
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
        redirect_back('/admin/merchants');
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
            redirect('/admin/merchants');
        }
        $this->renderPortal('admin', 'admin/merchant', [
            'title' => $merchant['shop_name'] . ' — Admin',
            'merchant' => $merchant,
            'products' => Product::forMerchant((int) $id),
            'plans' => Plan::forMerchant((int) $id),
        ]);
    }

    // ---- Customers ----

    /** Everyone who has signed up to buy — with a bit of activity per person. */
    public function users(): void
    {
        $this->requireAdmin();
        $this->renderPortal('admin', 'admin/users', [
            'title' => 'Customers — Admin',
            'users' => User::allWithStats(),
        ]);
    }

    /** Full profile for one customer: their details and every plan they hold. */
    public function userDetail(string $id): void
    {
        $this->requireAdmin();
        $user = User::find((int) $id);
        if (!$user) {
            flash('error', 'No such customer.');
            redirect('/admin/users');
        }
        $this->renderPortal('admin', 'admin/user', [
            'title' => $user['name'] . ' — Admin',
            'user' => $user,
            'plans' => Plan::forCustomer((int) $id),
        ]);
    }

    // ---- Plans ----

    public function plans(): void
    {
        $this->requireAdmin();
        $all = Plan::all();
        $buckets = [
            'all' => fn($p) => true,
            'active' => fn($p) => $p['status'] === 'active',
            'attention' => fn($p) => $p['status'] === 'active' && $p['grace_state'] !== 'ok',
            'pending' => fn($p) => $p['status'] === 'pending',
            'completed' => fn($p) => $p['status'] === 'completed',
            'cancelled' => fn($p) => $p['status'] === 'cancelled',
        ];
        $filter = (string) ($_GET['status'] ?? 'all');
        if (!isset($buckets[$filter])) {
            $filter = 'all';
        }
        $counts = [];
        foreach ($buckets as $k => $fn) {
            $counts[$k] = count(array_filter($all, $fn));
        }
        $this->renderPortal('admin', 'admin/plans', [
            'title' => 'Plans — Admin',
            'plans' => array_values(array_filter($all, $buckets[$filter])),
            'filter' => $filter,
            'counts' => $counts,
            'mode' => (new PaystackService())->mode(),
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
        $this->renderPortal('admin', 'admin/plan', [
            'title' => 'Plan #' . (int) $id . ' — Admin',
            'plan' => $plan,
            'installments' => Installment::forPlan((int) $id),
            'transactions' => Transaction::forPlan((int) $id),
            'outstandingRefunds' => $plan['status'] === 'cancelled' ? Transaction::outstandingRefundCount((int) $id) : 0,
            'canRetryPayout' => $plan['status'] === 'active'
                && (int) $plan['installments_paid'] >= (int) $plan['installments_total']
                && !Transaction::openPayoutForPlan((int) $id),
            'mode' => (new PaystackService())->mode(),
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
        if (!(new PaystackService())->isMock()) {
            flash('error', 'Simulate is only available in mock mode.');
            redirect_back('/admin/plans');
        }

        $result = (new PlanService())->collectInstallment((int) $planId);
        flash($result === 'failed' ? 'error' : 'success', "Plan #{$planId}: payment simulated — plan is now {$result}.");
        redirect_back('/admin/plans');
    }

    /**
     * Retry a merchant payout that failed (e.g. Paystack balance too low, or a
     * wrong payout account the merchant has since fixed). Won't start a second
     * payout while one is pending or done.
     */
    public function retryPayout(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $result = (new PlanService())->completeAndPayout((int) $id);
        $hasOpen = Transaction::openPayoutForPlan((int) $id);
        flash(
            $hasOpen ? 'success' : 'error',
            match (true) {
                $result === 'completed' => "Plan #{$id}: payout sent — plan completed.",
                $hasOpen => "Plan #{$id}: payout accepted by Paystack, waiting for confirmation.",
                default => "Plan #{$id}: payout failed again. Check the ledger row for Paystack's reason.",
            }
        );
        redirect('/admin/plan/' . (int) $id);
    }

    /** Retry refunds on a cancelled plan that failed the first time. */
    public function retryRefunds(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $r = (new PlanService())->retryRefunds((int) $id);
        flash(
            $r['failed'] === 0 ? 'success' : 'error',
            "Plan #{$id}: {$r['accepted']} refund(s) accepted (" . ghs($r['amount']) . "), {$r['failed']} failed."
        );
        redirect('/admin/plan/' . (int) $id);
    }

    // ---- Money & messages ----

    public function ledger(): void
    {
        $this->requireAdmin();
        $all = Transaction::ledger(500);
        $filter = (string) ($_GET['type'] ?? 'all');
        $counts = ['all' => count($all), 'collection' => 0, 'disbursement' => 0, 'refund' => 0, 'pending' => 0, 'failed' => 0];
        foreach ($all as $t) {
            $counts[$t['type']]++;
            if (isset($counts[$t['status']])) {
                $counts[$t['status']]++;
            }
        }
        if (!isset($counts[$filter])) {
            $filter = 'all';
        }
        $rows = match ($filter) {
            'all' => $all,
            'pending', 'failed' => array_filter($all, fn($t) => $t['status'] === $filter),
            default => array_filter($all, fn($t) => $t['type'] === $filter),
        };
        $this->renderPortal('admin', 'admin/ledger', [
            'title' => 'Transactions — Admin',
            'transactions' => array_values($rows),
            'filter' => $filter,
            'counts' => $counts,
            'pending' => Transaction::pendingCount(),
        ]);
    }

    public function sms(): void
    {
        $this->requireAdmin();
        $this->renderPortal('admin', 'admin/sms', [
            'title' => 'SMS log — Admin',
            'sms' => SmsLog::recent(200),
            'smsLive' => (new SmsService())->isLive(),
        ]);
    }

    /** Poll the SMS provider (Moolre) for delivery status of pending SMS and update the log. */
    public function pollSms(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $s = (new SmsService())->refreshDelivery(100);
        if ($s['checked'] === 0 && $s['pending'] === 0) {
            flash('success', 'No SMS awaiting a delivery update.');
        } else {
            flash('success', "Delivery check: {$s['delivered']} delivered, {$s['failed']} failed, {$s['pending']} still pending.");
        }
        redirect('/admin/sms');
    }

    // ---- System ----

    public function system(): void
    {
        $this->requireAdmin();
        $this->renderPortal('admin', 'admin/system', [
            'title' => 'System — Admin',
            'integrations' => $this->integrations(),
            'pending' => Transaction::pendingCount(),
        ]);
    }

    /**
     * Send a real test SMS to confirm the SMS integration works.
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
            redirect('/admin/system');
        }
        if (mb_strlen($message) > 160) {
            $message = mb_substr($message, 0, 160);
        }

        $ok = (new SmsService())->send($phone, $message, forceLive: true);
        flash(
            $ok ? 'success' : 'error',
            $ok
                ? 'Test SMS accepted by Moolre for ' . pretty_phone($phone) . '. Check the phone and the SMS log.'
                : 'Moolre rejected the SMS. Check the VAS key and that your Sender ID is approved (see the SMS log for the recorded attempt).'
        );
        redirect('/admin/system');
    }

    /** Run the grace-period reminder sweep by hand. */
    public function runReminders(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $svc = new PlanService();
        $actions = array_merge($svc->runDueReminders(1), $svc->runReminders());
        flash('success', $actions ? implode(' · ', $actions) : 'Nothing due or overdue — all plans on track.');
        redirect_back('/admin/system');
    }

    /** Status-check every pending payment now (fallback for missed webhooks). */
    public function reconcile(): void
    {
        $this->requireAdmin();
        Csrf::check();
        $actions = (new PlanService())->reconcilePending(0);
        flash('success', $actions ? implode(' · ', $actions) : 'No pending payments needed settling.');
        redirect_back('/admin/system');
    }

    /** Paystack + SMS status, shared by the dashboard and System page. */
    private function integrations(): array
    {
        $paystack = new PaystackService();
        $sms = new SmsService();
        $secret = (string) Config::get('PAYSTACK_SECRET_KEY', '');
        return [
            'mode' => $paystack->mode(),
            'paystack' => [
                'has_key' => $paystack->hasKeys(),
                'key_kind' => str_starts_with($secret, 'sk_live_') ? 'live' : (str_starts_with($secret, 'sk_test_') ? 'test' : 'unknown'),
                'webhook' => rtrim((string) Config::get('APP_URL', ''), '/') . '/webhook/paystack',
            ],
            'sms' => [
                'live' => $sms->isLive(),
                'sender' => $sms->sender(),
                'has_key' => $sms->hasKey(),
                'endpoint' => $sms->endpoint(),
            ],
        ];
    }
}
