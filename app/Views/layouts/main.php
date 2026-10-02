<?php
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Strip the app's base dir (e.g. /paysspaystack/public) so matching works anywhere.
$basePath = rtrim(url('/'), '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}

/** Active-nav helper. */
$is = static function (string $prefix) use ($currentPath): string {
    if ($prefix === '/') {
        return $currentPath === '/' ? 'active' : '';
    }
    return str_starts_with($currentPath, $prefix) ? 'active' : '';
};

// Everyone signed in on this browser (a person can be customer, merchant and
// admin at once). Each gets its own dashboard links and its own Log out.
$accounts = [];
if (Auth::userId() && ($u = Auth::user())) {
    $accounts[] = ['name' => $u['name'], 'role' => 'Customer', 'links' => [
        ['My plans', 'receipt_long', '/plans'],
        ['My account', 'manage_accounts', '/account'],
        ['Saved items', 'favorite', '/wishlist'],
        ['Cart', 'shopping_cart', '/cart'],
        ['Browse products', 'storefront', '/shop'],
    ], 'logout' => ['get', '/logout']];
}
if (Auth::merchantId() && ($mm = Auth::merchant())) {
    $accounts[] = ['name' => $mm['shop_name'], 'role' => 'Merchant', 'links' => [
        ['Shop dashboard', 'space_dashboard', '/merchant/dashboard'],
        ['My products', 'inventory_2', '/merchant/products'],
    ], 'logout' => ['get', '/merchant/logout']];
}
if (Auth::isAdmin()) {
    $accounts[] = ['name' => 'Administrator', 'role' => 'Admin', 'links' => [
        ['Admin dashboard', 'admin_panel_settings', '/admin'],
    ], 'logout' => ['post', '/admin/logout']];
}
$primary = $accounts[0] ?? null;
$merchantHref = Auth::merchantId() ? '/merchant/dashboard' : '/merchant';

// --- SEO -------------------------------------------------------------------
// Controllers can pass: title, metaDescription, canonical, ogType, ogImage,
// jsonLd (array, or list of arrays) and robots. Sensible defaults otherwise.
$siteName = (string) Config::get('APP_NAME', 'PaySmallSmall');
$pageTitle = (string) ($title ?? $siteName);
$pageDescription = meta_excerpt((string) ($metaDescription ?? "That thing you've been eyeing? Pay small small — weekly or daily MoMo payments — and it's yours. Your money sits in escrow till you finish. Built for Ghana."));
$pageCanonical = (string) ($canonical ?? canonical_url($currentPath));
// Account, checkout and login pages are for the person using them, not for Google.
$privatePrefixes = ['/account', '/plans', '/plan/', '/cart', '/wishlist', '/login', '/logout', '/register', '/verify-phone', '/forgot-pin', '/reset-pin', '/checkout', '/merchant/', '/admin'];
$isPrivate = false;
foreach ($privatePrefixes as $pre) {
    if ($currentPath === rtrim($pre, '/') || str_starts_with($currentPath, $pre)) {
        $isPrivate = true;
        break;
    }
}
$pageRobots = (string) ($robots ?? ($isPrivate ? 'noindex, nofollow' : 'index, follow, max-image-preview:large'));
$pageImage = absolute_media_url((string) ($ogImage ?? 'assets/img/hero-bg.jpg'));
$schemas = $jsonLd ?? [];
if ($schemas && !array_is_list($schemas)) {
    $schemas = [$schemas];
}
if ($currentPath === '/') {
    // Who we are + the site search box Google can show under our result.
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $siteName,
        'url' => canonical_url('/'),
        'logo' => absolute_media_url('assets/img/logo.png'),
    ];
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $siteName,
        'url' => canonical_url('/'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => canonical_url('/shop') . '?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ];
}
$cdnParts = parse_url((string) Config::get('CDN_URL', ''));
$cdnOrigin = isset($cdnParts['scheme'], $cdnParts['host']) ? $cdnParts['scheme'] . '://' . $cdnParts['host'] : '';

/** One account's block in the menu: name, links, Log out. */
$accountBlock = static function (array $a, bool $showHead): string {
    $h = '';
    if ($showHead) {
        $h .= '<div class="acct-head"><b>' . e($a['name']) . '</b><span>' . e($a['role']) . '</span></div>';
    }
    foreach ($a['links'] as [$label, $icon, $href]) {
        $h .= '<a href="' . url($href) . '">' . micon($icon, ['size' => 20]) . ' ' . e($label) . '</a>';
    }
    $outLabel = 'Log out' . ($showHead ? ' of ' . strtolower($a['role']) : '');
    if ($a['logout'][0] === 'post') {
        $h .= '<form method="post" action="' . url($a['logout'][1]) . '">' . Csrf::field()
            . '<button class="acct-out" type="submit">' . micon('logout', ['size' => 20]) . ' ' . e($outLabel) . '</button></form>';
    } else {
        $h .= '<a class="acct-out" href="' . url($a['logout'][1]) . '">' . micon('logout', ['size' => 20]) . ' ' . e($outLabel) . '</a>';
    }
    return $h;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="robots" content="<?= e($pageRobots) ?>">
<link rel="canonical" href="<?= e($pageCanonical) ?>">
<?php if (!empty($prevUrl)): ?><link rel="prev" href="<?= e($prevUrl) ?>"><?php endif; ?>
<?php if (!empty($nextUrl)): ?><link rel="next" href="<?= e($nextUrl) ?>"><?php endif; ?>
<?php if ($gsc = (string) Config::get('GOOGLE_SITE_VERIFICATION', '')): ?>
<meta name="google-site-verification" content="<?= e($gsc) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:type" content="<?= e((string) ($ogType ?? 'website')) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:url" content="<?= e($pageCanonical) ?>">
<meta property="og:image" content="<?= e($pageImage) ?>">
<meta property="og:image:alt" content="<?= e($pageTitle) ?>">
<meta property="og:locale" content="en_GH">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDescription) ?>">
<meta name="twitter:image" content="<?= e($pageImage) ?>">
<?php foreach ($schemas as $schema): ?>
<script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endforeach; ?>
<?php if ($cdnOrigin !== ''): ?><link rel="preconnect" href="<?= e($cdnOrigin) ?>" crossorigin><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<?php /* Only the icon axes the CSS uses (weight 400, grade 0, size 24, filled or not) — a fraction of the full variable font. */ ?>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0..1,0&display=block" rel="stylesheet">
<link rel="icon" href="<?= asset('/assets/img/favicon.ico') ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/img/favicon-32.png') ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= asset('/assets/img/favicon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('/assets/img/apple-touch-icon.png') ?>">
<meta name="theme-color" content="#00342b">
<link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
  <div class="wrap topbar-row">
    <span class="topbar-note"><?= micon('verified_user', ['size' => 16, 'fill' => true]) ?> Money held in escrow till you finish — nobody can chop it</span>
    <span class="topbar-ussd">SMS receipt after every payment</span>
  </div>
</div>

<header class="site-header">
  <div class="wrap header-row">
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false" data-nav-toggle><?= micon('menu', ['size' => 26]) ?></button>

    <a class="logo logo-img" href="<?= url('/') ?>"><?= picture('assets/img/logo-header.png', 'PaySmallSmall', ['width' => 283, 'height' => 60, 'loading' => 'eager', 'fetchpriority' => 'high']) ?></a>

    <nav class="primary-nav" aria-label="Primary">
      <a class="<?= $is('/shop') ?>" href="<?= url('/shop') ?>">Browse</a>
      <a class="<?= $is('/how-it-works') ?>" href="<?= url('/how-it-works') ?>">How it works</a>
      <a class="<?= $is('/plan') ?>" href="<?= url('/plans') ?>">My plans</a>
      <a class="<?= $is('/merchant') ?>" href="<?= url($merchantHref) ?>">Merchant portal</a>
    </nav>

    <form class="search" action="<?= url('/shop') ?>" method="get" role="search" data-suggest-form>
      <?= micon('search', ['class' => 'search-ic']) ?>
      <input type="search" name="q" value="<?= e(is_string($_GET['q'] ?? null) ? $_GET['q'] : '') ?>" placeholder="Search products or SKU…" aria-label="Search products" autocomplete="off" data-suggest>
      <button type="submit" aria-label="Search"><?= micon('arrow_forward', ['size' => 18]) ?></button>
    </form>

    <?php
      $cartCount = \App\Services\Cart::count();
      $savedCount = Auth::userId() ? \App\Models\Wishlist::count((int) Auth::userId()) : 0;
    ?>
    <div class="header-icons">
      <a class="header-icon-btn <?= $is('/wishlist') ?>" href="<?= url('/wishlist') ?>" aria-label="Saved items<?= $savedCount ? ' (' . $savedCount . ')' : '' ?>" title="Saved items">
        <?= micon('favorite') ?><span class="icon-badge" data-saved-count<?= $savedCount ? '' : ' hidden' ?>><?= $savedCount ?></span>
      </a>
      <a class="header-icon-btn <?= $is('/cart') ?>" href="<?= url('/cart') ?>" aria-label="Cart<?= $cartCount ? ' (' . $cartCount . ' item' . ($cartCount === 1 ? '' : 's') . ')' : '' ?>" title="Cart">
        <?= micon('shopping_cart') ?><span class="icon-badge" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= $cartCount ?></span>
      </a>
    </div>

    <nav class="header-actions" id="site-nav" aria-label="Account">
      <div class="acct-mobile">
        <a class="<?= $is('/how-it-works') ?>" href="<?= url('/how-it-works') ?>"><?= micon('help', ['size' => 20]) ?> How it works</a>
        <a class="<?= $is('/merchant') ?>" href="<?= url($merchantHref) ?>"><?= micon('storefront', ['size' => 20]) ?> Merchant portal</a>
      </div>
      <?php if ($primary): ?>
        <?php if ($primary['links'][0][2] !== '/plans'): /* My plans is already in the main nav */ ?>
        <a class="nav-cta hide-mobile" href="<?= url($primary['links'][0][2]) ?>"><?= micon($primary['links'][0][1], ['size' => 20]) ?> <?= e($primary['links'][0][0]) ?></a>
        <?php endif; ?>
        <details class="acct">
          <summary aria-label="Account menu">
            <span class="avatar avatar-sm"><?= e(strtoupper(mb_substr($primary['name'], 0, 1))) ?></span>
            <span><?= e(explode(' ', $primary['name'])[0]) ?><?= count($accounts) > 1 ? ' +' . (count($accounts) - 1) : '' ?></span>
            <?= micon('expand_more', ['size' => 18]) ?>
          </summary>
          <div class="acct-menu">
            <?php foreach ($accounts as $i => $a): ?>
              <?php if ($i > 0): ?><div class="acct-sep"></div><?php endif; ?>
              <?= $accountBlock($a, true) ?>
            <?php endforeach; ?>
          </div>
        </details>
        <div class="acct-mobile">
          <?php foreach ($accounts as $a): ?>
            <?= $accountBlock($a, true) ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <a href="<?= url('/login') ?>">Log in</a>
        <a class="btn btn-primary btn-sm" href="<?= url('/register') ?>">Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if ($msg = flash('success')): ?>
  <div class="wrap"><div class="flash flash-success" role="status"><?= micon('check_circle', ['size' => 20, 'fill' => true]) ?> <?= e($msg) ?></div></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
  <div class="wrap"><div class="flash flash-error" role="alert"><?= micon('error', ['size' => 20, 'fill' => true]) ?> <?= e($msg) ?></div></div>
<?php endif; ?>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-logo"><?= picture('assets/img/logo.png', 'PaySmallSmall — secure layaway for Ghana', ['width' => 224, 'height' => 150]) ?></p>
      <p class="footer-note">Lay-away for the MoMo age. Your money sits safe in escrow until the item is fully yours.</p>
    </div>
    <div>
      <p class="footer-head">How it works</p>
      <p class="footer-note">Pick an item, pay a small amount each week, and collect it when you're done. Your money stays in escrow the whole time.</p>
    </div>
    <div class="footer-links">
      <a href="<?= url('/shop') ?>">Browse products</a>
      <a href="<?= url('/how-it-works') ?>">How it works</a>
      <a href="<?= url('/merchant') ?>">Sell on PaySmallSmall</a>
      <a href="<?= url('/admin/login') ?>">Admin</a>
    </div>
  </div>
  <div class="wrap footer-base">
    <p>Payments run on Paystack. Built in Ghana. &copy; <?= date('Y') ?> PaySmallSmall — Secure layaway for Ghana.</p>
  </div>
</footer>

<nav class="bottom-nav" aria-label="Quick navigation">
  <a href="<?= url('/') ?>" class="<?= $is('/') ?>"><?= micon('home') ?><span>Home</span></a>
  <a href="<?= url('/shop') ?>" class="<?= ($is('/shop') || $is('/product')) ? 'active' : '' ?>"><?= micon('storefront') ?><span>Browse</span></a>
  <?php if (Auth::merchantId() && !Auth::userId()): ?>
    <a href="<?= url('/merchant/dashboard') ?>" class="<?= $is('/merchant') ?>"><?= micon('space_dashboard') ?><span>My shop</span></a>
  <?php else: ?>
    <a href="<?= url('/plans') ?>" class="<?= $is('/plan') ?>"><?= micon('receipt_long') ?><span>My plans</span></a>
  <?php endif; ?>
  <?php if ($primary): ?>
    <button type="button" data-nav-toggle aria-controls="site-nav" aria-expanded="false"><?= micon('account_circle') ?><span>Account</span></button>
  <?php else: ?>
    <a href="<?= url('/login') ?>" class="<?= $is('/login') ?>"><?= micon('login') ?><span>Log in</span></a>
  <?php endif; ?>
</nav>

<script src="<?= asset('/assets/js/app.js') ?>" defer></script>
</body>
</html>
