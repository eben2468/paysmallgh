<?php
declare(strict_types=1);

use App\Core\Config;

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Root-relative app URL for a path, e.g. url('/shop').
 *
 * The base is derived from the current request (the same way the Router strips
 * it), so links and assets resolve whether the app is served from a dev-server
 * root (php -S localhost:8080) or an Apache subdirectory
 * (http://localhost/payss/public). Root-relative paths also inherit the page's
 * scheme/host, so they work behind Cloudflare HTTPS in production with no config.
 */
function url(string $path = '/'): string
{
    static $base = null;
    if ($base === null) {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') ? '' : rtrim($scriptDir, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

/**
 * Absolute URL for a path, for links that leave the site and come back (e.g.
 * Paystack's return-after-payment URL). Uses the host the visitor is actually
 * on when it's our own domain (with or without "www."), so they land back on
 * the same host and their login cookie is sent. Otherwise falls back to APP_URL.
 */
function absolute_url(string $path = '/'): string
{
    $appUrl = rtrim((string) Config::get('APP_URL', ''), '/');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $appHost = strtolower((string) parse_url($appUrl, PHP_URL_HOST));
    $bare = static fn (string $h): string => preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', $h));

    if ($host !== '' && $appHost !== '' && $bare($host) === $bare($appHost)) {
        $scheme = \App\Core\Auth::isHttps() ? 'https' : 'http';
        return $scheme . '://' . $host . url($path);
    }
    return $appUrl . '/' . ltrim($path, '/');
}

/**
 * URL for a file in /public with a version stamp (?v=<last modified time>), so
 * browsers fetch a fresh copy the moment the file changes instead of reusing
 * a cached old stylesheet or script.
 *
 * For app.css / app.js, the minified build (app.min.css, made by
 * scripts/build-assets.php) is used when it exists and is newer than the
 * source — so an edit to the source is never hidden by a stale build.
 * With CDN_URL set, the link points at the CDN instead of this server.
 */
function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    if (preg_match('#^(.+)\.(css|js)$#', $path, $m)) {
        $src = BASE_PATH . '/public' . $path;
        $min = BASE_PATH . '/public' . $m[1] . '.min.' . $m[2];
        if (is_file($min) && (!is_file($src) || filemtime($min) >= filemtime($src))) {
            $path = $m[1] . '.min.' . $m[2];
        }
    }
    $file = BASE_PATH . '/public' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '';
    return cdn_url($path) . ($v !== '' ? '?v=' . $v : '');
}

/** A /public path on the CDN when CDN_URL is set, else a normal app URL. */
function cdn_url(string $path): string
{
    $cdn = rtrim((string) Config::get('CDN_URL', ''), '/');
    return $cdn !== '' ? $cdn . '/' . ltrim($path, '/') : url($path);
}

/**
 * URL for an uploaded file ("uploads/abc.jpg" as stored in the database).
 * Goes through the CDN when one is configured. Uploads get a random name
 * each time, so they never change and can be cached forever.
 */
function media_url(string $path): string
{
    return cdn_url('/' . ltrim($path, '/'));
}

/**
 * An <img> for an uploaded or bundled image, wrapped in <picture> with AVIF and
 * WebP versions when they exist next to it (made by Services\ImageOptimizer).
 * Lazy-loaded and async-decoded unless $attrs says otherwise; pass
 * ['loading' => 'eager', 'fetchpriority' => 'high'] for the main image above
 * the fold. $alt is required on purpose — every image says what it shows.
 */
function picture(string $path, string $alt, array $attrs = []): string
{
    $path = ltrim($path, '/');
    $attrs += ['loading' => 'lazy', 'decoding' => 'async'];
    $isAsset = str_starts_with($path, 'assets/');
    $src = $isAsset ? asset($path) : media_url($path);

    $sources = '';
    if (preg_match('#^(.+)\.(jpe?g|png)$#i', $path, $m)) {
        foreach (['avif' => 'image/avif', 'webp' => 'image/webp'] as $ext => $type) {
            $alt_file = $m[1] . '.' . $ext;
            if (is_file(BASE_PATH . '/public/' . $alt_file)) {
                $srcset = $isAsset ? asset($alt_file) : media_url($alt_file);
                $sources .= '<source type="' . $type . '" srcset="' . e($srcset) . '">';
            }
        }
    }

    $html = '<img src="' . e($src) . '" alt="' . e($alt) . '"';
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === false) {
            continue;
        }
        $html .= ' ' . e((string) $k) . ($v === true ? '' : '="' . e((string) $v) . '"');
    }
    $html .= '>';
    return $sources !== '' ? '<picture>' . $sources . $html . '</picture>' : $html;
}

/** "Samsung Galaxy A16 (128GB)" -> "samsung-galaxy-a16-128gb". */
function slugify(string $text): string
{
    $text = mb_strtolower($text);
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii) && $ascii !== '') {
            $text = $ascii;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if (strlen($text) > 70) {
        $text = rtrim(substr($text, 0, 70), '-');
    }
    return $text;
}

/**
 * Public URL path for a product: "/product/12-samsung-galaxy-a16". The id leads
 * so the page still finds the product if it's renamed — old links then 301 to
 * the new slug (see ShopController::show).
 */
function product_path(array $product): string
{
    $id = (int) ($product['id'] ?? $product['product_id'] ?? 0);
    $name = (string) ($product['name'] ?? $product['product_name'] ?? '');
    $slug = slugify($name);
    return '/product/' . $id . ($slug !== '' ? '-' . $slug : '');
}

function product_url(array $product): string
{
    return url(product_path($product));
}

/**
 * The site's public address for canonical links, sitemaps and share cards.
 * Uses APP_URL so every page names ONE host and scheme, whichever one the
 * visitor came in on (www or not, http or https).
 */
function canonical_url(string $path = '/'): string
{
    $base = rtrim((string) Config::get('APP_URL', ''), '/');
    if ($base === '') {
        return absolute_url($path);
    }
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '/' : $path);
}

/** Absolute URL for an image (share cards need full URLs). */
function absolute_media_url(string $path): string
{
    $u = str_starts_with(ltrim($path, '/'), 'assets/') ? asset($path) : media_url($path);
    if (preg_match('#^https?://#', $u)) {
        return $u; // already on the CDN
    }
    $base = rtrim(url('/'), '/');
    if ($base !== '' && str_starts_with($u, $base)) {
        $u = substr($u, strlen($base)); // canonical_url() adds the base back via APP_URL
    }
    return canonical_url($u);
}

/** Plain-text snippet for meta descriptions: tags stripped, spaces squashed, cut at a word. */
function meta_excerpt(string $text, int $max = 158): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > $max * 0.6) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, " ,.;:-") . '…';
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Redirect back to the page the request came from (same host only), so an
 * action taken from a list or detail page returns you there. Falls back to
 * $fallback when there's no usable referrer.
 */
function redirect_back(string $fallback): never
{
    $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $parts = $ref !== '' ? parse_url($ref) : false;
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (is_array($parts) && isset($parts['host'], $parts['path'])) {
        $refHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        if ($refHost === $host) {
            header('Location: ' . $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : ''));
            exit;
        }
    }
    redirect($fallback);
}

/**
 * Redirect to an absolute external URL (e.g. Paystack's hosted checkout) and
 * stop. Unlike redirect(), the URL is used as-is — no app base is prepended.
 */
function redirect_external(string $absoluteUrl): never
{
    header('Location: ' . $absoluteUrl);
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

/** Trim and lower-case an email address. Returns null if it isn't a valid one. */
function normalize_email(string $raw): ?string
{
    $email = mb_strtolower(trim($raw));
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }
    return $email;
}

/** 233244000000 -> 0244000000 (the local format Paystack's MoMo endpoints take). */
function local_phone(string $phone): string
{
    return preg_match('/^233(\d{9})$/', $phone, $m) ? '0' . $m[1] : $phone;
}

/**
 * Paystack mobile-money network code for a Ghana number, from its prefix:
 * MTN, VOD (Telecel) or ATL (AirtelTigo). Null if the prefix is unknown.
 * Ported numbers keep their old prefix, so merchants pick their network
 * explicitly on the payout form; this is the default/fallback.
 */
function momo_network(string $phone): ?string
{
    $normalized = normalize_phone($phone);
    if ($normalized === null) {
        return null;
    }
    return match (substr($normalized, 3, 2)) {
        '24', '25', '53', '54', '55', '59' => 'MTN',
        '20', '50' => 'VOD',
        '26', '27', '56', '57' => 'ATL',
        default => null,
    };
}

/**
 * Words for a plan's payment rhythm, so every page says it the same way.
 * 'once' = paid in full in a single payment.
 * @return array{label:string, unit:string, units:string, per:string, short:string, this:string}
 */
function freq_words(string $frequency): array
{
    return match ($frequency) {
        'daily' => ['label' => 'Daily', 'unit' => 'day', 'units' => 'days', 'per' => 'a day', 'short' => 'day', 'this' => "today's"],
        'monthly' => ['label' => 'Monthly', 'unit' => 'month', 'units' => 'months', 'per' => 'a month', 'short' => 'mo', 'this' => "this month's"],
        'once' => ['label' => 'Pay in full', 'unit' => 'payment', 'units' => 'payment', 'per' => 'once', 'short' => '', 'this' => 'the full'],
        default => ['label' => 'Weekly', 'unit' => 'week', 'units' => 'weeks', 'per' => 'a week', 'short' => 'wk', 'this' => "this week's"],
    };
}

/** "GHS 100 × 12 weeks = GHS 1,200", or "One payment of GHS 1,200" for pay-in-full. */
function plan_math(array $plan): string
{
    $per = (int) $plan['installment_pesewas'];
    $n = (int) $plan['installments_total'];
    if (($plan['frequency'] ?? '') === 'once') {
        return 'One payment of ' . ghs($per);
    }
    $w = freq_words((string) ($plan['frequency'] ?? 'weekly'));
    return ghs($per) . ' × ' . $n . ' ' . ($n === 1 ? $w['unit'] : $w['units']) . ' = ' . ghs($per * $n);
}

/** Short rhythm for tables: "GHS 100/wk", or "Paid in full". */
function plan_rate(array $plan): string
{
    if (($plan['frequency'] ?? '') === 'once') {
        return 'Paid in full';
    }
    return ghs((int) $plan['installment_pesewas']) . '/' . freq_words((string) ($plan['frequency'] ?? 'weekly'))['short'];
}

/** A status pill (plans, merchants, transactions) with consistent colours and wording. */
function status_tag(string $status, ?string $label = null): string
{
    $class = [
        'suspended' => 'cancelled',
        'rejected' => 'cancelled',
        'approved' => 'approved',
        'ok' => 'on-track',
    ][$status] ?? $status;
    $text = $label ?? [
        'approved' => 'Live',
        'pending' => 'Pending',
        'rejected' => 'Declined',
        'grace' => 'In grace',
        'flagged' => 'Stalled',
    ][$status] ?? ucfirst($status);
    return '<span class="tag tag-' . e($class) . '">' . e($text) . '</span>';
}

/** Consistent short date/time for back-office tables: "3 Oct 2026, 2:15pm". */
function when(?string $datetime, bool $withTime = true): string
{
    if ($datetime === null || $datetime === '') {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts === false ? '—' : date($withTime ? 'j M Y, g:ia' : 'j M Y', $ts);
}

/** The current page as an app-relative path with its query ("/shop?sort=new"), for "come back here" links. */
function current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $base = rtrim(url('/'), '/');
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base)) ?: '/';
    }
    return '/' . ltrim($uri, '/');
}

/**
 * What a plan is for, in full: "Samsung Galaxy A16 (Black / 128GB) x2".
 * Expects product_name plus the plan's variant_label and quantity.
 */
function plan_item(array $plan): string
{
    $s = (string) ($plan['product_name'] ?? '');
    $label = trim((string) ($plan['variant_label'] ?? ''));
    if ($label !== '') {
        $s .= ' (' . $label . ')';
    }
    $qty = (int) ($plan['quantity'] ?? 1);
    if ($qty > 1) {
        $s .= ' x' . $qty;
    }
    return $s;
}

/** "just now", "12 min ago", "3 hours ago", "yesterday", "5 days ago", else a date. */
function ago(int $minutes): string
{
    $minutes = max(0, $minutes);
    if ($minutes < 2) {
        return 'just now';
    }
    if ($minutes < 60) {
        return $minutes . ' min ago';
    }
    $hours = intdiv($minutes, 60);
    if ($hours < 24) {
        return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
    }
    $days = intdiv($hours, 24);
    if ($days === 1) {
        return 'yesterday';
    }
    if ($days < 14) {
        return $days . ' days ago';
    }
    return date('j M', time() - $minutes * 60);
}

/**
 * First name with the middle hidden ("Kwame Boateng" -> "K***e"), for public
 * activity feeds: shows a real person paid without saying who.
 */
function masked_name(string $fullName): string
{
    $first = (string) (preg_split('/\s+/', trim($fullName))[0] ?? '');
    $len = mb_strlen($first);
    if ($len === 0) {
        return 'Someone';
    }
    if ($len < 3) {
        return mb_strtoupper(mb_substr($first, 0, 1)) . '***';
    }
    return mb_strtoupper(mb_substr($first, 0, 1)) . '***' . mb_substr($first, -1);
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
        // decorative motifs (poster / editorial flourishes)
        'spark' => '<path d="M12 2c.6 4.8 2.2 6.4 7 7-4.8.6-6.4 2.2-7 7-.6-4.8-2.2-6.4-7-7 4.8-.6 6.4-2.2 7-7z"/>',
        'burst' => '<path d="M12 2v5M12 17v5M2 12h5M17 12h5M5 5l3.5 3.5M15.5 15.5L19 19M19 5l-3.5 3.5M8.5 15.5L5 19"/>',
        'check' => '<path d="M4 12.5l5 5 11-11"/>',
        'tag' => '<path d="M3.5 12.5V4.5h8l9 9-7.5 7.5-9-9z"/><circle cx="7.5" cy="8.5" r="1.4"/>',
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/**
 * Material Symbols (Outlined) icon — the icon system for the new UI.
 * $opts: ['size' => 20, 'fill' => true, 'class' => 'text-primary']
 */
function micon(string $name, array $opts = []): string
{
    $cls = 'material-symbols-outlined';
    if (!empty($opts['fill'])) {
        $cls .= ' fill';
    }
    if (!empty($opts['class'])) {
        $cls .= ' ' . $opts['class'];
    }
    $style = '';
    if (!empty($opts['size'])) {
        $style = ' style="font-size:' . (int) $opts['size'] . 'px"';
    }
    return '<span class="' . $cls . '"' . $style . ' aria-hidden="true">' . e($name) . '</span>';
}

/**
 * Flat, softly-rounded progress bar (fintech style). $pct is 0–100.
 * $variant: primary (green) | warn (gold) | success. Animates in via JS.
 */
function progress_bar(int $pct, string $variant = 'primary'): string
{
    $pct = max(0, min(100, $pct));
    $v = in_array($variant, ['primary', 'warn', 'success'], true) ? $variant : 'primary';
    return '<div class="progress" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100">'
        . '<div class="progress-fill progress-fill--' . $v . '" data-pct="' . $pct . '" style="width:' . $pct . '%"></div>'
        . '</div>';
}

/**
 * Marker-stroke progress bar (matches the receipt motif). $pct is 0–100.
 * $flag rides the tip (e.g. "GHS 735 to go"); $done paints it green.
 */
function marker_bar(int $pct, string $flag = '', bool $done = false): string
{
    $pct = max(0, min(100, $pct));
    $cls = 'marker-bar' . ($done ? ' done' : '');
    $d = 'M8 12 C 70 7, 110 17, 170 12 S 250 8, 292 13';
    $flagHtml = $flag !== '' ? '<span class="marker-flag">' . e($flag) . '</span>' : '';
    return '<div class="' . $cls . '" style="--pct: ' . $pct . '">'
        . '<svg viewBox="0 0 300 22" preserveAspectRatio="none" aria-hidden="true">'
        . '<path class="track" d="' . $d . '" pathLength="100"/>'
        . '<path class="fill" d="' . $d . '" pathLength="100"/>'
        . '</svg>' . $flagHtml . '</div>';
}

/** Material Symbol name for a product category (used with micon()). */
function product_micon(string $category): string
{
    $map = [
        'phones' => 'smartphone',
        'smartphones' => 'smartphone',
        'electronics' => 'devices_other',
        'appliances' => 'kitchen',
        'home appliances' => 'kitchen',
        'furniture' => 'chair',
        'fashion' => 'checkroom',
        'school' => 'school',
        'school items' => 'school',
        'general' => 'inventory_2',
        'accessories' => 'watch',
        'beauty' => 'spa',
        'kitchen' => 'blender',
        'building' => 'construction',
    ];
    return $map[strtolower($category)] ?? 'inventory_2';
}

/**
 * Render a 0–5 star rating as filled/half/empty Material star icons.
 * Read-only display (the review form uses its own interactive star inputs).
 */
function stars(float $rating, int $size = 18): string
{
    $out = '<span class="stars" aria-label="' . number_format($rating, 1) . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $out .= micon('star', ['size' => $size, 'fill' => true, 'class' => 'star-on']);
        } elseif ($rating >= $i - 0.5) {
            $out .= micon('star_half', ['size' => $size, 'fill' => true, 'class' => 'star-on']);
        } else {
            $out .= micon('star', ['size' => $size, 'class' => 'star-off']);
        }
    }
    return $out . '</span>';
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
