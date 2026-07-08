<?php
/** Expects $p (product row with shop_name). */
$weekly12 = (int) ceil((int) $p['cash_price_pesewas'] / 12);
?>
<a class="product-card" href="<?= url('/product/' . $p['id']) ?>">
  <div class="product-photo">
    <?php if (!empty($p['photo'])): ?>
      <img src="<?= url('/' . $p['photo']) ?>" alt="<?= e($p['name']) ?>">
    <?php else: ?>
      <div class="photo-placeholder"><b><?= e($p['name']) ?></b>photo coming from the shop</div>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-shop"><?= e($p['shop_name']) ?></span>
    <span class="product-name"><?= e($p['name']) ?></span>
    <div class="product-price-row">
      <div class="product-weekly">from <?= ghs($weekly12) ?>/week</div>
      <div class="product-cash">cash price <?= ghs((int) $p['cash_price_pesewas']) ?></div>
    </div>
  </div>
</a>
