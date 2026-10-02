<?php
/**
 * Portal layout — admin and merchant back offices.
 * Vars: $portal ('admin'|'merchant'), $content, $title, plus
 *   admin:    $navCounts (['merchants','plans','ledger'] => int)
 *   merchant: $merchant (row)
 */
use App\Core\Config;
use App\Core\Csrf;

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
// Strip the app's base dir (e.g. /paysspaystack/public) so matching works anywhere.
$base = rtrim(url('/'), '/');
if ($base !== '' && str_starts_with($currentPath, $base)) {
    $currentPath = substr($currentPath, strlen($base)) ?: '/';
}

$isAdmin = ($portal ?? 'admin') === 'admin';
$counts = $navCounts ?? [];

// [label, icon, href, [active prefixes], badge count]
$groups = $isAdmin
    ? [
        'Overview' => [
            ['Dashboard', 'space_dashboard', '/admin', ['=/admin'], 0],
        ],
        'Manage' => [
            ['Merchants', 'storefront', '/admin/merchants', ['/admin/merchants', '/admin/merchant/'], $counts['merchants'] ?? 0],
            ['Customers', 'group', '/admin/users', ['/admin/users', '/admin/user/'], 0],
            ['Plans', 'receipt_long', '/admin/plans', ['/admin/plans', '/admin/plan/'], $counts['plans'] ?? 0],
        ],
        'Money & messages' => [
            ['Transactions', 'account_balance', '/admin/ledger', ['/admin/ledger'], $counts['ledger'] ?? 0],
            ['SMS log', 'sms', '/admin/sms', ['/admin/sms'], 0],
        ],
        'Settings' => [
            ['System', 'tune', '/admin/system', ['/admin/system'], 0],
        ],
    ]
    : [
        'My shop' => [
            ['Dashboard', 'space_dashboard', '/merchant/dashboard', ['/merchant/dashboard'], 0],
            ['Products', 'inventory_2', '/merchant/products', ['/merchant/products'], 0],
            ['Payouts', 'account_balance_wallet', '/merchant/payouts', ['/merchant/payouts'], 0],
        ],
        'Account' => [
            ['Shop settings', 'settings', '/merchant/settings', ['/merchant/settings'], 0],
        ],
    ];

$isActive = static function (array $prefixes) use ($currentPath): bool {
    foreach ($prefixes as $p) {
        if (str_starts_with($p, '=') ? $currentPath === substr($p, 1) : str_starts_with($currentPath, $p)) {
            return true;
        }
    }
    return false;
};
$section = $isAdmin ? 'Admin' : 'Merchant';
foreach ($groups as $items) {
    foreach ($items as $it) {
        if ($isActive($it[3])) {
            $section = $it[0];
        }
    }
}
if (!$isAdmin && str_starts_with($currentPath, '/merchant/products/')) {
    $section = 'Products';
}

$mode = (string) Config::get('PAYMENTS_MODE', 'mock');
$m = $merchant ?? [];
$owner = $isAdmin ? 'Administrator' : (string) ($m['owner_name'] ?? 'Merchant');
$initial = strtoupper(mb_substr($owner, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'PaySmallSmall') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
<link rel="icon" href="<?= asset('/assets/img/favicon.ico') ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="<?= asset('/assets/img/favicon-32.png') ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= asset('/assets/img/favicon-192.png') ?>">
<link rel="apple-touch-icon" href="<?= asset('/assets/img/apple-touch-icon.png') ?>">
<meta name="theme-color" content="#00342b">
<link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
</head>
<body class="portal-body">
<a class="skip-link" href="#main">Skip to content</a>

<div class="portal">
  <aside class="portal-side" id="portal-side" aria-label="<?= $isAdmin ? 'Admin' : 'Merchant' ?> navigation">
    <div class="portal-brand">
      <a class="logo logo-light" href="<?= url($isAdmin ? '/admin' : '/merchant/dashboard') ?>"><img class="logo-mark-img" src="<?= asset('/assets/img/logo-mark.png') ?>" alt="" width="32" height="32"><span>Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span></a>
      <span class="portal-tag"><?= $isAdmin ? 'Admin' : 'Merchant' ?></span>
    </div>

    <?php if (!$isAdmin): ?>
      <div class="portal-shop">
        <b><?= e($m['shop_name'] ?? '') ?></b>
        <?php $st = (string) ($m['status'] ?? 'pending'); ?>
        <span class="portal-shop-status is-<?= e($st) ?>"><?= e(['approved' => 'Live', 'pending' => 'Under review', 'rejected' => 'Not approved', 'suspended' => 'Suspended'][$st] ?? $st) ?></span>
      </div>
      <a class="btn btn-gold btn-block portal-cta" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add product</a>
    <?php endif; ?>

    <nav class="portal-nav">
      <?php foreach ($groups as $label => $items): ?>
        <p class="portal-nav-label"><?= e($label) ?></p>
        <?php foreach ($items as [$text, $icon, $href, $prefixes, $badge]): ?>
          <?php $on = $isActive($prefixes); ?>
          <a href="<?= url($href) ?>" class="<?= $on ? 'active' : '' ?>" <?= $on ? 'aria-current="page"' : '' ?>>
            <?= micon($icon, ['size' => 20, 'fill' => $on]) ?>
            <span><?= e($text) ?></span>
            <?php if ($badge > 0): ?><span class="nav-count"><?= (int) $badge ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="portal-side-foot">
      <a class="portal-side-link" href="<?= url('/') ?>"><?= micon('open_in_new', ['size' => 18]) ?> View the shop site</a>
      <div class="portal-user">
        <span class="avatar"><?= e($initial) ?></span>
        <div class="portal-user-meta">
          <b><?= e($owner) ?></b>
          <span><?= $isAdmin ? e(pretty_phone((string) Config::get('ADMIN_PHONE', ''))) : e(pretty_phone((string) ($m['phone'] ?? ''))) ?></span>
        </div>
        <?php if ($isAdmin): ?>
          <form method="post" action="<?= url('/admin/logout') ?>">
            <?= Csrf::field() ?>
            <button class="portal-logout" type="submit" title="Log out" aria-label="Log out"><?= micon('logout', ['size' => 20]) ?></button>
          </form>
        <?php else: ?>
          <a class="portal-logout" href="<?= url('/merchant/logout') ?>" title="Log out" aria-label="Log out"><?= micon('logout', ['size' => 20]) ?></a>
        <?php endif; ?>
      </div>
    </div>
  </aside>
  <div class="portal-scrim" data-portal-close hidden></div>

  <div class="portal-main">
    <header class="portal-top">
      <button class="portal-menu-btn" type="button" aria-label="Open menu" aria-controls="portal-side" aria-expanded="false" data-portal-toggle><?= micon('menu', ['size' => 24]) ?></button>
      <p class="portal-crumb"><span><?= $isAdmin ? 'Admin' : 'Merchant' ?></span> <?= micon('chevron_right', ['size' => 18]) ?> <b><?= e($section) ?></b></p>
      <div class="portal-top-right">
        <?php if ($isAdmin): ?>
          <a class="mode-pill is-<?= e($mode) ?>" href="<?= url('/admin/system') ?>" title="Payments mode — see System">
            <span class="dot"></span> Payments: <?= e($mode) ?>
          </a>
        <?php endif; ?>
        <span class="portal-top-user"><span class="avatar avatar-sm"><?= e($initial) ?></span> <span class="hide-mobile"><?= e($owner) ?></span></span>
      </div>
    </header>

    <main class="portal-content" id="main">
      <?php if ($msg = flash('success')): ?>
        <div class="flash flash-success" role="status"><?= micon('check_circle', ['size' => 20, 'fill' => true]) ?> <?= e($msg) ?></div>
      <?php endif; ?>
      <?php if ($msg = flash('error')): ?>
        <div class="flash flash-error" role="alert"><?= micon('error', ['size' => 20, 'fill' => true]) ?> <?= e($msg) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </main>
  </div>
</div>

<script>
  (function () {
    var side = document.getElementById('portal-side');
    var scrim = document.querySelector('[data-portal-close]');
    var btn = document.querySelector('[data-portal-toggle]');
    function set(open) {
      side.classList.toggle('open', open);
      scrim.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('portal-locked', open);
    }
    btn.addEventListener('click', function () { set(!side.classList.contains('open')); });
    scrim.addEventListener('click', function () { set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
  })();
</script>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
