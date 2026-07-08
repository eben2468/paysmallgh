<?php
/** Expects $p (product row with shop_name). */
$weekly12 = (int) ceil((int) $p['cash_price_pesewas'] / 12);
?>
<a class="product-card" href="<?= url('/product/' . $p['id']) ?>">
  <span class="card-badge"><?= ghs($weekly12) ?>/wk</span>
  <div class="product-photo">
    <?php if (!empty($p['photo'])): ?>
      <img src="<?= url('/' . $p['photo']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="photo-placeholder"><?= category_icon($p['category'] ?? 'general', 34) ?><b><?= e($p['name']) ?></b>photo coming from the shop</div>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-name"><?= e($p['name']) ?></span>
    <span class="product-shop"><?= e($p['shop_name']) ?></span>
    <div class="product-price-row">
      <div class="product-weekly"><?= ghs($weekly12) ?> <small>/ week &times; 12</small></div>
      <div class="product-cash">or <?= ghs((int) $p['cash_price_pesewas']) ?> cash</div>
    </div>
  </div>
</a>
