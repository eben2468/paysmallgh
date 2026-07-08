<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\User;

final class AuthController extends Controller
{
    public function registerForm(): void
    {
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
        flash('success', 'Akwaaba, ' . explode(' ', $name)[0] . '! Your account is ready.');
        $this->afterLoginRedirect();
    }

    public function loginForm(): void
    {
        $this->render('auth/login', ['title' => 'Log in — PaySmallSmall']);
    }

    public function login(): void
    {
        Csrf::check();
        $phone = normalize_phone((string) ($_POST['phone'] ?? ''));
        $pin = (string) ($_POST['pin'] ?? '');

        $user = $phone ? User::findByPhone($phone) : null;
        if (!$user || !password_verify($pin, $user['pin_hash'])) {
            flash('error', 'Phone or PIN no match. Try again.');
            redirect('/login');
        }

        Auth::loginUser((int) $user['id']);
        $this->afterLoginRedirect();
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/');
    }

    private function afterLoginRedirect(): never
    {
        $target = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if (is_string($target) && str_starts_with($target, '/')) {
            header('Location: ' . $target);
            exit;
        }
        redirect('/plans');
    }
}
