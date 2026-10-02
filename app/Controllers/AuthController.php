<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Services\Otp;

final class AuthController extends Controller
{
    public function registerForm(): void
    {
        if (Auth::userId()) {
            redirect('/plans');
        }
        $this->render('auth/register', ['title' => 'Create your account — PaySmallSmall']);
    }

    public function register(): void
    {
        Csrf::check();
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $pin = (string) ($_POST['pin'] ?? '');

        if ($name === '' || mb_strlen($name) > 120) {
            flash('error', 'Tell us your name.');
            redirect('/register');
        }
        if ($phone === null) {
            flash('error', 'That phone number doesn\'t look right. Use the one on your MoMo, like 024 XXX XXXX.');
            redirect('/register');
        }
        if (!preg_match('/^\d{4,6}$/', $pin)) {
            flash('error', 'Pick a PIN of 4 to 6 digits.');
            redirect('/register');
        }
        if (User::findByPhone($phone)) {
            flash('error', 'This number already has an account. Log in instead.');
            redirect('/login');
        }

        $id = User::create($name, $phone, $pin);
        Auth::loginUser($id);

        // Confirm the number with a code, then carry on to wherever they were headed.
        $_SESSION['after_verify'] = $this->takeAfterLogin() ?? '/plans';
        $sent = Otp::send($phone, 'verify');
        flash('success', 'Akwaaba, ' . explode(' ', $name)[0] . '! Your account is ready. '
            . ($sent['ok'] ? 'We\'ve texted you a 6-digit code to confirm your number.' : $sent['message']));
        redirect('/verify-phone');
    }

    /* ---------- Confirm phone number ---------- */

    public function verifyForm(): void
    {
        $user = $this->requireUser();
        if (User::isVerified($user)) {
            redirect($this->takeAfterVerify());
        }
        $this->render('auth/verify', [
            'title' => 'Confirm your number — PaySmallSmall',
            'phone' => (string) $user['phone'],
            'demoCode' => Otp::demoCode('verify'),
        ]);
    }

    public function verify(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $result = Otp::check((string) $user['phone'], 'verify', (string) ($_POST['code'] ?? ''));
        if ($result !== 'ok') {
            flash('error', Otp::errorMessage($result));
            redirect('/verify-phone');
        }
        User::markVerified((int) $user['id']);
        flash('success', 'Number confirmed. You\'re all set.');
        redirect($this->takeAfterVerify());
    }

    public function verifyResend(): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $sent = Otp::send((string) $user['phone'], 'verify');
        flash($sent['ok'] ? 'success' : 'error', $sent['ok'] ? 'New code sent to ' . pretty_phone((string) $user['phone']) . '.' : $sent['message']);
        redirect('/verify-phone');
    }

    /* ---------- Forgot PIN ---------- */

    public function forgotForm(): void
    {
        $this->render('auth/forgot', ['title' => 'Forgot your PIN — PaySmallSmall', 'role' => 'customer']);
    }

    /**
     * Text a reset code. The answer is the same whether or not the number has
     * an account, so this page can't be used to find out who's a customer.
     */
    public function forgot(): void
    {
        Csrf::check();
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        if ($phone === null) {
            flash('error', 'That phone number doesn\'t look right. Use the one you signed up with, like 024 XXX XXXX.');
            redirect('/forgot-pin');
        }
        if (User::findByPhone($phone)) {
            $sent = Otp::send($phone, 'reset');
            if (!$sent['ok'] && $sent['wait'] === 0) {
                flash('error', $sent['message']);
                redirect('/forgot-pin');
            }
        }
        $_SESSION['reset_phone'] = $phone;
        flash('success', 'If ' . pretty_phone($phone) . ' has an account, a 6-digit code is on its way by SMS.');
        redirect('/reset-pin');
    }

    public function resetForm(): void
    {
        $phone = (string) ($_SESSION['reset_phone'] ?? '');
        if ($phone === '') {
            redirect('/forgot-pin');
        }
        $this->render('auth/reset', [
            'title' => 'Set a new PIN — PaySmallSmall',
            'role' => 'customer',
            'phone' => $phone,
            'demoCode' => Otp::demoCode('reset'),
        ]);
    }

    public function reset(): void
    {
        Csrf::check();
        $phone = (string) ($_SESSION['reset_phone'] ?? '');
        $pin = (string) ($_POST['pin'] ?? '');
        if ($phone === '') {
            redirect('/forgot-pin');
        }
        if (!preg_match('/^\d{4,6}$/', $pin) || $pin !== (string) ($_POST['pin_confirm'] ?? '')) {
            flash('error', 'Your new PIN must be 4 to 6 digits, typed the same twice.');
            redirect('/reset-pin');
        }
        $result = Otp::check($phone, 'reset', (string) ($_POST['code'] ?? ''));
        $user = User::findByPhone($phone);
        if ($result !== 'ok' || !$user) {
            flash('error', Otp::errorMessage($result === 'ok' ? 'expired' : $result));
            redirect('/reset-pin');
        }

        User::updatePin((int) $user['id'], $pin);
        User::markVerified((int) $user['id']); // they just proved they have the phone
        LoginThrottle::clear($phone, 'customer');
        unset($_SESSION['reset_phone']);
        Auth::loginUser((int) $user['id']);
        flash('success', 'New PIN set. You\'re logged in.');
        redirect('/plans');
    }

    private function takeAfterLogin(): ?string
    {
        $t = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        return is_string($t) && str_starts_with($t, '/') && !str_starts_with($t, '//') ? $t : null;
    }

    private function takeAfterVerify(): string
    {
        $t = $_SESSION['after_verify'] ?? null;
        unset($_SESSION['after_verify']);
        if (!is_string($t) || !str_starts_with($t, '/') || str_starts_with($t, '//')) {
            return '/plans';
        }
        // REQUEST_URI-style targets may include the app's base folder — strip it.
        $base = rtrim(url('/'), '/');
        if ($base !== '' && str_starts_with($t, $base . '/')) {
            $t = substr($t, strlen($base));
        }
        return $t;
    }

    public function loginForm(): void
    {
        if (Auth::userId()) {
            redirect('/plans');
        }
        $this->render('auth/login', ['title' => 'Log in — PaySmallSmall']);
    }

    public function login(): void
    {
        Csrf::check();
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $pin = (string) ($_POST['pin'] ?? '');

        if ($phone !== null && ($mins = LoginThrottle::lockedFor($phone, 'customer')) > 0) {
            flash('error', "Too many wrong PINs. Wait {$mins} minute" . ($mins === 1 ? '' : 's') . ' and try again — or reset your PIN.');
            redirect('/login');
        }
        $user = $phone ? User::findByPhone($phone) : null;
        if (!$user || !password_verify($pin, $user['pin_hash'])) {
            if ($phone !== null) {
                LoginThrottle::fail($phone, 'customer');
            }
            flash('error', 'Phone or PIN no match. Try again.');
            redirect('/login');
        }

        LoginThrottle::clear($phone, 'customer');
        Auth::loginUser((int) $user['id']);
        $this->afterLoginRedirect();
    }

    public function logout(): void
    {
        Auth::logout('user');
        redirect('/');
    }

    private function afterLoginRedirect(): never
    {
        $target = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        // Only same-site paths ("/x", never "//host"). Stored targets may or may
        // not include the app's base folder (REQUEST_URI does) — strip it so
        // redirect() adds it back exactly once.
        if (is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//')) {
            $base = rtrim(url('/'), '/');
            if ($base !== '' && str_starts_with($target, $base . '/')) {
                $target = substr($target, strlen($base));
            }
            redirect($target);
        }
        redirect('/plans');
    }
}
