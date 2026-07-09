<?php
use App\Core\Auth;
use App\Core\Config;

// Categories for the header nav — tolerate a missing DB so error pages still render.
try {
    $navCats = \App\Models\Product::categoryCounts();
} catch (\Throwable) {
    $navCats = [];
}
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'PaySmallSmall') ?></title>
<meta name="description" content="Pay for what you need small small — weekly MoMo payments, money held safe until you finish. Built for Ghana.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://api.fontshare.com" crossorigin>
<link rel="preconnect" href="https://cdn.fontshare.com" crossorigin>
<!-- Display: Clash Display (Fontshare). Body: Instrument Sans. Space Grotesk is a loaded fallback so the bold headline look survives if Fontshare is slow. -->
<link href="https://api.fontshare.com/v2/css?f[]=clash-display@500,600,700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<div class="topbar">
  <div class="wrap topbar-row">
    <span class="topbar-note"><?= svg_icon('shield', 15) ?> Money held in escrow till you finish — nobody can chop it</span>
    <span class="topbar-ussd">No smartphone? Dial <b><?= e(Config::get('USSD_CODE', '*920*77#')) ?></b></span>
  </div>
</div>

<header class="site-header">
  <div class="wrap header-row">
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false" data-nav-toggle><?= svg_icon('menu', 22) ?></button>

    <a class="logo" href="<?= url('/') ?>">
      Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span>
    </a>

    <form class="search" action="<?= url('/shop') ?>" method="get" role="search">
      <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>"
             placeholder="Search phones, beds, kaba…" aria-label="Search products">
      <button type="submit" aria-label="Search"><?= svg_icon('search', 18) ?></button>
    </form>

    <nav class="header-actions" id="site-nav">
      <a href="<?= url('/how-it-works') ?>">How it works</a>
      <a href="<?= url('/merchant') ?>"><?= svg_icon('store', 17) ?> For shops</a>
      <?php if (Auth::userId()): ?>
        <a class="nav-cta" href="<?= url('/plans') ?>"><?= svg_icon('plans', 17) ?> My plans</a>
        <a href="<?= url('/logout') ?>">Log out</a>
      <?php elseif (Auth::merchantId()): ?>
        <a class="nav-cta" href="<?= url('/merchant/dashboard') ?>"><?= svg_icon('store', 17) ?> Dashboard</a>
        <a href="<?= url('/merchant/logout') ?>">Log out</a>
      <?php else: ?>
        <a href="<?= url('/login') ?>"><?= svg_icon('user', 17) ?> Log in</a>
        <a class="nav-cta" href="<?= url('/register') ?>">Start</a>
      <?php endif; ?>
    </nav>
  </div>

  <nav class="cat-bar" aria-label="Categories">
    <div class="wrap cat-bar-row">
      <a class="<?= $currentPath === '/shop' && empty($_GET['category']) ? 'active' : '' ?>" href="<?= url('/shop') ?>"><?= svg_icon('grid', 16) ?> All products</a>
      <?php foreach ($navCats as $cat => $n): ?>
        <a class="<?= ($_GET['category'] ?? '') === $cat ? 'active' : '' ?>"
           href="<?= url('/shop?category=' . urlencode((string) $cat)) ?>"><?= category_icon((string) $cat, 16) ?> <?= e(ucfirst((string) $cat)) ?></a>
      <?php endforeach; ?>
      <a class="cat-bar-sell" href="<?= url('/merchant/register') ?>">Sell on PaySmallSmall</a>
    </div>
  </nav>
</header>

<?php if ($msg = flash('success')): ?>
  <div class="flash flash-success wrap" role="status"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($msg = flash('error')): ?>
  <div class="flash flash-error wrap" role="alert"><?= e($msg) ?></div>
<?php endif; ?>

<main id="main">
<?= $content ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <p class="footer-logo">PaySmallSmall</p>
      <p class="footer-note">Lay-away for the MoMo age. Your money sits safe until the item is fully yours.</p>
    </div>
    <div>
      <p class="footer-head">No smartphone?</p>
      <p class="footer-ussd"><?= e(Config::get('USSD_CODE', '*920*77#')) ?></p>
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
    <p>Payments and SMS run on Moolre. Built in Ghana. &copy; <?= date('Y') ?> PaySmallSmall.</p>
  </div>
</footer>

<nav class="bottom-nav" aria-label="Quick navigation">
  <a href="<?= url('/') ?>" class="<?= $currentPath === '/' ? 'active' : '' ?>"><?= svg_icon('home', 21) ?><span>Home</span></a>
  <a href="<?= url('/shop') ?>" class="<?= str_starts_with($currentPath, '/shop') || str_starts_with($currentPath, '/product') ? 'active' : '' ?>"><?= svg_icon('grid', 21) ?><span>Browse</span></a>
  <?php if (Auth::merchantId()): ?>
    <a href="<?= url('/merchant/dashboard') ?>" class="<?= str_starts_with($currentPath, '/merchant') ? 'active' : '' ?>"><?= svg_icon('store', 21) ?><span>Shop</span></a>
    <a href="<?= url('/merchant/payouts') ?>"><?= svg_icon('receipt', 21) ?><span>Payouts</span></a>
  <?php else: ?>
    <a href="<?= url('/plans') ?>" class="<?= str_starts_with($currentPath, '/plan') ? 'active' : '' ?>"><?= svg_icon('plans', 21) ?><span>My plans</span></a>
    <a href="<?= Auth::userId() ? url('/logout') : url('/login') ?>"><?= svg_icon('user', 21) ?><span><?= Auth::userId() ? 'Log out' : 'Log in' ?></span></a>
  <?php endif; ?>
</nav>

<script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
