<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Merchant;
use App\Models\User;

final class Auth
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => self::isHttps(),
            ]);
            session_start();
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

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
