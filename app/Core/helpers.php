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

/**
 * Small inline line-icons (stroke = currentColor). No emoji, no icon fonts.
 */
function svg_icon(string $name, int $size = 20): string
{
    $paths = [
        'search' => '<circle cx="9" cy="9" r="6.5"/><path d="M14 14l6 6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20c1.2-3.4 4-5 7.5-5s6.3 1.6 7.5 5"/>',
        'home' => '<path d="M4 11l8-7 8 7"/><path d="M6 9.5V20h12V9.5"/><path d="M10 20v-6h4v6"/>',
        'grid' => '<rect x="4" y="4" width="7" height="7"/><rect x="13" y="4" width="7" height="7"/><rect x="4" y="13" width="7" height="7"/><rect x="13" y="13" width="7" height="7"/>',
        'plans' => '<rect x="5" y="3.5" width="14" height="17"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'phone' => '<rect x="7" y="2.5" width="10" height="19" rx="1.5"/><path d="M11 18.5h2"/>',
        'plug' => '<path d="M9 2.5V7m6-4.5V7"/><path d="M6.5 7h11v4a5.5 5.5 0 0 1-11 0z"/><path d="M12 16.5v5"/>',
        'chair' => '<path d="M7 3.5h10v8H7z"/><path d="M5.5 11.5h13V15h-13z"/><path d="M7 15v5.5M17 15v5.5"/>',
        'dress' => '<path d="M9 3l3 3 3-3"/><path d="M12 6l-4.5 6L10 21h4l2.5-9L12 6z"/>',
        'box' => '<path d="M3.5 8L12 3.5 20.5 8v8L12 20.5 3.5 16z"/><path d="M3.5 8L12 12.5 20.5 8M12 12.5v8"/>',
        'shield' => '<path d="M12 3l7.5 3v5.5c0 4.6-3.1 7.6-7.5 9.5-4.4-1.9-7.5-4.9-7.5-9.5V6z"/><path d="M8.8 12l2.2 2.2 4.2-4.4"/>',
        'receipt' => '<path d="M6 3h12v18l-2-1.4-2 1.4-2-1.4-2 1.4-2-1.4L6 21z"/><path d="M9.5 8h5M9.5 12h5"/>',
        'dialpad' => '<circle cx="7" cy="5" r="1.6"/><circle cx="12" cy="5" r="1.6"/><circle cx="17" cy="5" r="1.6"/><circle cx="7" cy="11" r="1.6"/><circle cx="12" cy="11" r="1.6"/><circle cx="17" cy="11" r="1.6"/><circle cx="12" cy="17" r="1.6"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7v5.5l3.5 2"/>',
        'arrow' => '<path d="M4 12h15"/><path d="M13 5.5L19.5 12 13 18.5"/>',
        'store' => '<path d="M4.5 9.5L6 4h12l1.5 5.5"/><path d="M4.5 9.5h15V11a3 3 0 0 1-5 2.2A3 3 0 0 1 12 13a3 3 0 0 1-2.5.2A3 3 0 0 1 4.5 11z"/><path d="M6 13.5V20h12v-6.5"/><path d="M10 20v-4h4v4"/>',
        'menu' => '<path d="M4 6.5h16M4 12h16M4 17.5h16"/>',
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Icon for a product category. */
function category_icon(string $category, int $size = 20): string
{
    $map = [
        'phones' => 'phone',
        'electronics' => 'plug',
        'furniture' => 'chair',
        'fashion' => 'dress',
    ];
    return svg_icon($map[strtolower($category)] ?? 'box', $size);
}
