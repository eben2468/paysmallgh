<?php use App\Core\Auth; use App\Core\Config; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'PaySmallSmall') ?></title>
<meta name="description" content="Pay for what you need small small — weekly MoMo payments, money held safe until you finish. Built for Ghana.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Instrument+Sans:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('/assets/css/app.css') ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
  <div class="wrap header-row">
    <a class="logo" href="<?= url('/') ?>">
      Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span>
    </a>
    <nav class="site-nav" id="site-nav">
      <a href="<?= url('/shop') ?>">Browse</a>
      <a href="<?= url('/how-it-works') ?>">How it works</a>
      <a href="<?= url('/merchant') ?>">For shops</a>
      <?php if (Auth::userId()): ?>
        <a class="nav-cta" href="<?= url('/plans') ?>">My plans</a>
        <a href="<?= url('/logout') ?>">Log out</a>
      <?php elseif (Auth::merchantId()): ?>
        <a class="nav-cta" href="<?= url('/merchant/dashboard') ?>">Dashboard</a>
        <a href="<?= url('/merchant/logout') ?>">Log out</a>
      <?php else: ?>
        <a href="<?= url('/login') ?>">Log in</a>
        <a class="nav-cta" href="<?= url('/register') ?>">Start</a>
      <?php endif; ?>
    </nav>
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false" data-nav-toggle>
      <span></span><span></span><span></span>
    </button>
  </div>
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

<script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
