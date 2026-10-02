<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Address;
use App\Models\SavedCard;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\LoginThrottle;
use App\Services\Otp;

/**
 * The customer's own account: profile, phone number, PIN, delivery
 * addresses, payment methods and payment history. Everything here is
 * scoped to the logged-in customer.
 */
final class AccountController extends Controller
{
    private const NETWORKS = ['MTN' => 'MTN MoMo', 'VOD' => 'Telecel Cash', 'ATL' => 'AirtelTigo Money'];

    /* ---------- Profile ---------- */

    public function index(): void
    {
        $user = $this->requireUser();
        $uid = (int) $user['id'];
        $this->render('account/index', [
            'title' => 'My account — PaySmallSmall',
            'tab' => 'profile',
            'user' => $user,
            'stats' => User::stats($uid),
            'counts' => [
                'addresses' => Address::count($uid),
                'cards' => count(SavedCard::forUser($uid)),
                'saved' => Wishlist::count($uid),
            ],
        ]);
    }

    public function updateProfile(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'Your name can\'t be empty.');
            redirect('/account');
        }
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        if ($email === null) {
            flash('error', 'That email doesn\'t look right. Check it, like ama@gmail.com.');
            redirect('/account');
        }
        if (User::emailTaken($email, (int) $user['id'])) {
            flash('error', 'That email is already on another account.');
            redirect('/account');
        }
        User::updateProfile((int) $user['id'], $name, $email);
        flash('success', 'Details updated.');
        redirect('/account');
    }

    /* ---------- Change phone number (code sent to the NEW number) ---------- */

    public function phoneForm(): void
    {
        $user = $this->requireUser();
        $pending = (string) ($_SESSION['new_phone'] ?? '');
        $this->render('account/phone', [
            'title' => 'Change phone number — PaySmallSmall',
            'tab' => 'profile',
            'user' => $user,
            'newPhone' => $pending,
            'demoCode' => $pending !== '' ? Otp::demoCode('change_phone') : null,
        ]);
    }

    public function phoneSend(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        // Your PIN first, so nobody holding your unlocked phone can move the account.
        $current = (string) $user['phone'];
        if (($mins = LoginThrottle::lockedFor($current, 'customer')) > 0) {
            flash('error', "Too many wrong PINs. Wait {$mins} minute" . ($mins === 1 ? '' : 's') . ' and try again.');
            redirect('/account/phone');
        }
        if (!password_verify((string) ($_POST['pin'] ?? ''), (string) $user['pin_hash'])) {
            LoginThrottle::fail($current, 'customer');
            flash('error', 'Your PIN isn\'t right.');
            redirect('/account/phone');
        }
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if ($phone === null) {
            flash('error', 'That number doesn\'t look right. Try like 024 XXX XXXX.');
            redirect('/account/phone');
        }
        if ($phone === $user['phone']) {
            flash('error', 'That\'s already your number.');
            redirect('/account/phone');
        }
        $owner = User::findByPhone($phone);
        if ($owner) {
            flash('error', 'Another account already uses that number.');
            redirect('/account/phone');
        }
        $sent = Otp::send($phone, 'change_phone');
        if (!$sent['ok'] && $sent['wait'] === 0) {
            flash('error', $sent['message']);
            redirect('/account/phone');
        }
        $_SESSION['new_phone'] = $phone;
        flash($sent['ok'] ? 'success' : 'error', $sent['ok'] ? 'Code sent to ' . pretty_phone($phone) . '. Type it below.' : $sent['message']);
        redirect('/account/phone');
    }

    public function phoneConfirm(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $phone = (string) ($_SESSION['new_phone'] ?? '');
        if ($phone === '') {
            redirect('/account/phone');
        }
        $result = Otp::check($phone, 'change_phone', (string) ($_POST['code'] ?? ''));
        if ($result !== 'ok') {
            flash('error', Otp::errorMessage($result));
            redirect('/account/phone');
        }
        if (User::findByPhone($phone)) { // taken in the meantime
            unset($_SESSION['new_phone']);
            flash('error', 'Another account already uses that number.');
            redirect('/account/phone');
        }
        // (A MoMo wallet left as "the account phone" follows the new number by itself.)
        User::updatePhone((int) $user['id'], $phone);
        unset($_SESSION['new_phone']);
        flash('success', 'Done — your number is now ' . pretty_phone($phone) . '. Log in with it next time. Receipts and reminders go there from now on.');
        redirect('/account');
    }

    public function phoneCancel(): void
    {
        $this->requireUser();
        Csrf::check();
        unset($_SESSION['new_phone']);
        redirect('/account/phone');
    }

    /* ---------- PIN ---------- */

    public function securityForm(): void
    {
        $user = $this->requireUser();
        $this->render('account/security', [
            'title' => 'PIN & security — PaySmallSmall',
            'tab' => 'security',
            'user' => $user,
        ]);
    }

    public function changePin(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $phone = (string) $user['phone'];
        if (($mins = LoginThrottle::lockedFor($phone, 'customer')) > 0) {
            flash('error', "Too many wrong PINs. Wait {$mins} minute" . ($mins === 1 ? '' : 's') . ', or use "Forgot your PIN?".');
            redirect('/account/security');
        }
        if (!password_verify((string) ($_POST['current_pin'] ?? ''), (string) $user['pin_hash'])) {
            LoginThrottle::fail($phone, 'customer');
            flash('error', 'Your current PIN isn\'t right.');
            redirect('/account/security');
        }
        $pin = (string) ($_POST['pin'] ?? '');
        if (!preg_match('/^\d{4,6}$/', $pin) || $pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            flash('error', 'Your new PIN must be 4 to 6 digits, typed the same twice.');
            redirect('/account/security');
        }
        if (password_verify($pin, (string) $user['pin_hash'])) {
            flash('error', 'That\'s the PIN you already have. Pick a different one.');
            redirect('/account/security');
        }
        User::updatePin((int) $user['id'], $pin);
        LoginThrottle::clear($phone, 'customer');
        flash('success', 'PIN changed. Use the new one next time you log in.');
        redirect('/account/security');
    }

    /* ---------- Addresses ---------- */

    public function addresses(): void
    {
        $user = $this->requireUser();
        $this->render('account/addresses', [
            'title' => 'Addresses — PaySmallSmall',
            'tab' => 'addresses',
            'user' => $user,
            'addresses' => Address::forUser((int) $user['id']),
        ]);
    }

    public function addressForm(?string $id = null): void
    {
        $user = $this->requireUser();
        $address = null;
        if ($id !== null) {
            $address = Address::find((int) $id, (int) $user['id']);
            if (!$address) {
                redirect('/account/addresses');
            }
        }
        $old = $_SESSION['address_old'] ?? null;
        unset($_SESSION['address_old']);
        $this->render('account/address-form', [
            'title' => ($address ? 'Edit' : 'Add') . ' address — PaySmallSmall',
            'tab' => 'addresses',
            'user' => $user,
            'address' => $address,
            'old' => is_array($old) ? $old : null,
        ]);
    }

    public function addressSave(?string $id = null): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $uid = (int) $user['id'];
        if ($id !== null && !Address::find((int) $id, $uid)) {
            redirect('/account/addresses');
        }
        $back = $id !== null ? "/account/addresses/{$id}/edit" : '/account/addresses/new';
        $fail = static function (string $msg) use ($back): never {
            $_SESSION['address_old'] = $_POST;
            flash('error', $msg);
            redirect($back);
        };

        $t = static fn (string $k, int $max): string => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
        $d = [
            'label' => $t('label', 40),
            'recipient' => $t('recipient', 120),
            'phone' => normalize_phone((string) ($_POST['phone'] ?? '')),
            'region' => $t('region', 40),
            'town' => $t('town', 80),
            'area' => $t('area', 160),
            'landmark' => $t('landmark', 160),
            'gps' => strtoupper(str_replace(' ', '', $t('gps', 20))),
        ];
        if ($d['recipient'] === '' || $d['town'] === '' || $d['area'] === '') {
            $fail('Fill in who receives it, the town and the area or street.');
        }
        if ($d['phone'] === null) {
            $fail('The contact number doesn\'t look right. Try like 024 XXX XXXX.');
        }
        if (!in_array($d['region'], Address::REGIONS, true)) {
            $fail('Pick a region from the list.');
        }
        // GhanaPost GPS: two letters, 3-4 digits, then 4 digits (GA-123-4567, AK-0395-0280).
        // Typed without dashes, the last four digits are the final block.
        if ($d['gps'] !== '') {
            if (!preg_match('/^([A-Z]{2})-?(\d{3,4})-?(\d{4})$/', $d['gps'], $m)) {
                $fail('That GhanaPost GPS address doesn\'t look right. It\'s like GA-123-4567 — or leave it empty.');
            }
            $d['gps'] = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        Address::save($uid, $id !== null ? (int) $id : null, $d, isset($_POST['is_default']));
        flash('success', $id !== null ? 'Address updated.' : 'Address saved.');
        redirect('/account/addresses');
    }

    public function addressDelete(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        Address::delete((int) $id, (int) $user['id']);
        flash('success', 'Address removed.');
        redirect('/account/addresses');
    }

    public function addressDefault(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        if (Address::find((int) $id, (int) $user['id'])) {
            Address::makeDefault((int) $id, (int) $user['id']);
            flash('success', 'Default address changed.');
        }
        redirect('/account/addresses');
    }

    /* ---------- Payment methods ---------- */

    public function paymentMethods(): void
    {
        $user = $this->requireUser();
        [$walletPhone, $walletNet] = User::momoWallet($user);
        $this->render('account/payment-methods', [
            'title' => 'Payment methods — PaySmallSmall',
            'tab' => 'payments',
            'user' => $user,
            'cards' => SavedCard::forUser((int) $user['id']),
            'walletPhone' => $walletPhone,
            'walletNet' => $walletNet,
            'networks' => self::NETWORKS,
        ]);
    }

    public function saveMomo(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $raw = trim((string) ($_POST['momo_number'] ?? ''));
        $phone = $raw === '' ? (string) $user['phone'] : normalize_phone($raw);
        if ($phone === null) {
            flash('error', 'That MoMo number doesn\'t look right. Try like 024 XXX XXXX.');
            redirect('/account/payment-methods');
        }
        $network = (string) ($_POST['momo_network'] ?? '');
        if (!isset(self::NETWORKS[$network])) {
            $network = momo_network($phone) ?? '';
        }
        if ($network === '') {
            flash('error', 'Pick the network for that number.');
            redirect('/account/payment-methods');
        }
        // Same as the account phone on its usual network: store nothing (follows the account).
        $isDefault = $phone === $user['phone'] && $network === momo_network($phone);
        User::setMomo((int) $user['id'], $isDefault ? null : $phone, $isDefault ? null : $network);
        flash('success', 'MoMo wallet saved. "Send a MoMo prompt" now goes to ' . pretty_phone($phone) . '.');
        redirect('/account/payment-methods');
    }

    public function deleteCard(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        SavedCard::delete((int) $id, (int) $user['id']);
        flash('success', 'Card removed. We can\'t charge it any more.');
        redirect('/account/payment-methods');
    }

    public function cardSettings(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $on = isset($_POST['save_cards']);
        User::setSaveCards((int) $user['id'], $on);
        if (!$on && isset($_POST['forget_all'])) {
            SavedCard::deleteAll((int) $user['id']);
        }
        flash('success', $on ? 'We\'ll remember cards you pay with.' : 'We won\'t save new cards.' . (isset($_POST['forget_all']) ? ' Saved cards removed.' : ''));
        redirect('/account/payment-methods');
    }

    /* ---------- Payment history (receipts) ---------- */

    public function history(): void
    {
        $user = $this->requireUser();
        $rows = Transaction::forCustomer((int) $user['id']);
        $paid = 0;
        $refunded = 0;
        foreach ($rows as $r) {
            if ($r['status'] !== 'success') {
                continue;
            }
            if ($r['type'] === 'collection') {
                $paid += (int) $r['amount_pesewas'];
            } else {
                $refunded += (int) $r['amount_pesewas'];
            }
        }
        $this->render('account/history', [
            'title' => 'Payment history — PaySmallSmall',
            'tab' => 'history',
            'user' => $user,
            'rows' => $rows,
            'paid' => $paid,
            'refunded' => $refunded,
        ]);
    }
}
