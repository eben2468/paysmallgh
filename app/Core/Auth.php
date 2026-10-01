<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Merchant;
use App\Models\User;

final class Auth
{
    /**
     * Start the session. Logins last SESSION_LIFETIME_DAYS (default 30) and the
     * clock resets on every visit, so people stay signed in across payments,
     * browser restarts and idle days — until they log out themselves.
     *
     * Sessions live in storage/sessions (not the system temp dir), so the
     * server's own cleanup job can't delete them early using PHP's 24-minute
     * default; PHP's garbage collector uses our lifetime for this folder.
     */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $lifetime = max(1, Config::int('SESSION_LIFETIME_DAYS', 30)) * 86400;
        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) || @mkdir($dir, 0700, true)) {
            session_save_path($dir);
        }
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');

        $params = [
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax', // Lax: still sent when Paystack redirects back
            'secure' => self::isHttps(),
        ];
        session_set_cookie_params($params);
        session_start();

        // Slide the expiry forward on every visit, so active users never hit
        // the limit. (A brand-new session already got its cookie from PHP.)
        if (($_COOKIE[session_name()] ?? '') === session_id()) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + $lifetime,
            ] + array_diff_key($params, ['lifetime' => 1]));
        }
    }

    public static function isHttps(): bool
    {
        // Behind Cloudflare/Nginx the original scheme arrives in X-Forwarded-Proto.
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    }

    // ---- Customer ----

    public static function loginUser(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
    }

    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function user(): ?array
    {
        $id = self::userId();
        return $id ? User::find($id) : null;
    }

    // ---- Merchant ----

    public static function loginMerchant(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['merchant_id'] = $id;
    }

    public static function merchantId(): ?int
    {
        return isset($_SESSION['merchant_id']) ? (int) $_SESSION['merchant_id'] : null;
    }

    public static function merchant(): ?array
    {
        $id = self::merchantId();
        return $id ? Merchant::find($id) : null;
    }

    // ---- Admin ----

    public static function loginAdmin(): void
    {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
    }

    public static function isAdmin(): bool
    {
        return !empty($_SESSION['is_admin']);
    }

    /**
     * Log out one role ('user' | 'merchant' | 'admin'). Other roles signed in
     * on the same browser stay signed in — logging out of the shop doesn't
     * throw the admin out. The session is destroyed once no role is left.
     */
    public static function logout(string $role): void
    {
        $key = ['user' => 'user_id', 'merchant' => 'merchant_id', 'admin' => 'is_admin'][$role] ?? null;
        if ($key !== null) {
            unset($_SESSION[$key]);
        }

        if (!isset($_SESSION['user_id']) && !isset($_SESSION['merchant_id']) && empty($_SESSION['is_admin'])) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
            return;
        }
        session_regenerate_id(true);
    }
}
