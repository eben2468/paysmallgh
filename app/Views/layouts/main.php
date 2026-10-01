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
<title><?= e($title ?? 'PaySmallSmall') ?></title>
<meta name="description" content="Pay for what you need small small — weekly MoMo payments, money held safe in escrow until you finish. Built for Ghana.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
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

    <a class="logo" href="<?= url('/') ?>">Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></a>

    <nav class="primary-nav" aria-label="Primary">
      <a class="<?= $is('/shop') ?>" href="<?= url('/shop') ?>">Browse</a>
      <a class="<?= $is('/how-it-works') ?>" href="<?= url('/how-it-works') ?>">How it works</a>
      <a class="<?= $is('/plan') ?>" href="<?= url('/plans') ?>">My plans</a>
      <a class="<?= $is('/merchant') ?>" href="<?= url($merchantHref) ?>">Merchant portal</a>
    </nav>

    <form class="search" action="<?= url('/shop') ?>" method="get" role="search">
      <?= micon('search', ['class' => 'search-ic']) ?>
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search products…" aria-label="Search products">
      <button type="submit" aria-label="Search"><?= micon('arrow_forward', ['size' => 18]) ?></button>
    </form>

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
      <p class="footer-logo">PaySmallSmall</p>
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

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
