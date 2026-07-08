<?php
declare(strict_types=1);

use App\Core\Config;

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** App URL for a path, e.g. url('/shop'). */
function url(string $path = '/'): string
{
    $base = rtrim(Config::get('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Format pesewas as "GHS 1,200" or "GHS 1,200.50" when there are pesewas. */
function ghs(int $pesewas): string
{
    $cedis = intdiv($pesewas, 100);
    $rem = $pesewas % 100;
    $out = 'GHS ' . number_format($cedis);
    if ($rem !== 0) {
        $out .= '.' . str_pad((string) $rem, 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

/** Normalize a Ghana phone number to 233XXXXXXXXX. Returns null if invalid. */
function normalize_phone(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (preg_match('/^0(\d{9})$/', $digits, $m)) {
        return '233' . $m[1];
    }
    if (preg_match('/^233\d{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

/** Show 233244000000 as 024 400 0000 for display. */
function pretty_phone(string $phone): string
{
    if (preg_match('/^233(\d{2})(\d{3})(\d{4})$/', $phone, $m)) {
        return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }
    return $phone;
}

/** One-shot flash messages. */
function flash(string $key, ?string $value = null): ?string
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/** Days between today and a date string (negative = overdue). */
function days_until(string $date): int
{
    $today = new DateTimeImmutable('today');
    $target = new DateTimeImmutable($date);
    return (int) $today->diff($target)->format('%r%a');
}
