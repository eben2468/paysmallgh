<?php
/** Account section menu. Expects $tab (profile|history|addresses|payments|security) and $user. */
$items = [
    ['profile', 'person', 'Profile', '/account'],
    ['plans', 'receipt_long', 'My plans (orders)', '/plans'],
    ['history', 'history', 'Payment history', '/account/history'],
    ['addresses', 'home_pin', 'Addresses', '/account/addresses'],
    ['payments', 'account_balance_wallet', 'Payment methods', '/account/payment-methods'],
    ['security', 'lock', 'PIN & security', '/account/security'],
    ['saved', 'favorite', 'Saved items', '/wishlist'],
];
?>
<nav class="account-nav" aria-label="Your account">
  <div class="account-who">
    <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
    <span><b><?= e($user['name']) ?></b><small><?= e(pretty_phone((string) $user['phone'])) ?></small></span>
  </div>
  <?php foreach ($items as [$key, $icon, $label, $href]): ?>
    <a class="<?= ($tab ?? '') === $key ? 'active' : '' ?>" href="<?= url($href) ?>"<?= ($tab ?? '') === $key ? ' aria-current="page"' : '' ?>><?= micon($icon, ['size' => 20]) ?> <?= e($label) ?></a>
  <?php endforeach; ?>
  <a class="account-out" href="<?= url('/logout') ?>"><?= micon('logout', ['size' => 20]) ?> Log out</a>
</nav>
