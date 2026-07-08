<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function check(): void
    {
        $sent = $_POST['_token'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
            http_response_code(419);
            exit('Session expired. Go back and try again.');
        }
    }
}
