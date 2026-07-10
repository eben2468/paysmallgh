<?php
use App\Core\Auth;
use App\Core\Config;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$ussd = Config::get('USSD_CODE', '*920*77#');

/** Active-nav helper. */
$is = static function (string $prefix) use ($currentPath): string {
    if ($prefix === '/') {
        return $currentPath === '/' ? 'active' : '';
    }
    return str_starts_with($currentPath, $prefix) ? 'active' : '';
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
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
  <div class="wrap topbar-row">
    <span class="topbar-note"><?= micon('verified_user', ['size' => 16, 'fill' => true]) ?> Money held in escrow till you finish — nobody can chop it</span>
    <span class="topbar-ussd">No smartphone? Dial <b><?= e($ussd) ?></b></span>
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
      <a class="<?= $is('/merchant') ?>" href="<?= url('/merchant') ?>">Merchant portal</a>
    </nav>

    <form class="search" action="<?= url('/shop') ?>" method="get" role="search">
      <?= micon('search', ['class' => 'search-ic']) ?>
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search products…" aria-label="Search products">
      <button type="submit" aria-label="Search"><?= micon('arrow_forward', ['size' => 18]) ?></button>
    </form>

    <nav class="header-actions" id="site-nav" aria-label="Account">
      <?php if (Auth::userId()): ?>
        <a class="nav-cta" href="<?= url('/plans') ?>"><?= micon('receipt_long', ['size' => 20]) ?> My plans</a>
        <a href="<?= url('/logout') ?>">Log out</a>
      <?php elseif (Auth::merchantId()): ?>
        <a class="nav-cta" href="<?= url('/merchant/dashboard') ?>"><?= micon('storefront', ['size' => 20]) ?> Dashboard</a>
        <a href="<?= url('/merchant/logout') ?>">Log out</a>
      <?php else: ?>
        <a class="hide-mobile" href="<?= url('/login') ?>">Log in</a>
        <a class="btn btn-primary btn-sm" href="<?= url('/register') ?>">Sign in</a>
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
      <p class="footer-head">No smartphone?</p>
      <p class="footer-ussd"><?= e($ussd) ?></p>
      <p class="footer-note">Dial it on any phone to check your plan or pay.</p>
    </div>
    <div class="footer-links">
      <a href="<?= url('/shop') ?>">Browse products</a>
      <a href="<?= url('/how-it-works') ?>">How it works</a>
      <a href="<?= url('/merchant') ?>">Sell on PaySmallSmall</a>
      <a href="<?= url('/admin/login') ?>">Admin</a>
    </div>
  </div>
  <div class="wrap footer-base">
    <p>Payments and SMS run on Moolre. Built in Ghana. &copy; <?= date('Y') ?> PaySmallSmall — Secure layaway for Ghana.</p>
  </div>
</footer>

<nav class="bottom-nav" aria-label="Quick navigation">
  <a href="<?= url('/') ?>" class="<?= $currentPath === '/' ? 'active' : '' ?>"><?= micon('home') ?><span>Home</span></a>
  <a href="<?= url('/shop') ?>" class="<?= (str_starts_with($currentPath, '/shop') || str_starts_with($currentPath, '/product')) ? 'active' : '' ?>"><?= micon('storefront') ?><span>Browse</span></a>
  <?php if (Auth::merchantId()): ?>
    <a href="<?= url('/merchant/dashboard') ?>" class="<?= str_starts_with($currentPath, '/merchant') ? 'active' : '' ?>"><?= micon('payments') ?><span>Shop</span></a>
    <a href="<?= url('/merchant/payouts') ?>"><?= micon('account_balance') ?><span>Payouts</span></a>
  <?php else: ?>
    <a href="<?= url('/plans') ?>" class="<?= str_starts_with($currentPath, '/plan') ? 'active' : '' ?>"><?= micon('receipt_long') ?><span>My plans</span></a>
    <a href="<?= Auth::userId() ? url('/logout') : url('/login') ?>"><?= micon('person') ?><span><?= Auth::userId() ? 'Log out' : 'Log in' ?></span></a>
  <?php endif; ?>
</nav>

<script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
