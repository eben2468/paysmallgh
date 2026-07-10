<?php
/** Expects $p (product row with shop_name). */
$weekly12 = (int) ceil((int) $p['cash_price_pesewas'] / 12);
$cat = $p['category'] ?? 'general';
?>
<a class="product-card" href="<?= url('/product/' . $p['id']) ?>">
  <div class="product-photo">
    <span class="card-badge"><?= ghs($weekly12) ?>/wk</span>
    <?php if (!empty($p['photo'])): ?>
      <img src="<?= url('/' . $p['photo']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php else: ?>
      <div class="photo-placeholder"><?= micon(product_micon($cat), ['size' => 40]) ?><b><?= e($p['name']) ?></b><span class="small">photo coming from the shop</span></div>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-cat"><?= e(ucfirst((string) $cat)) ?></span>
    <span class="product-name"><?= e($p['name']) ?></span>
    <span class="product-shop"><?= e($p['shop_name']) ?></span>
    <div class="product-cash">
      <span class="amt"><?= ghs((int) $p['cash_price_pesewas']) ?></span>
      <span class="lbl">Cash price</span>
    </div>
    <div class="weekly-box">
      <div class="row1">
        <span class="wk-lbl">Weekly plan</span>
        <span class="wk-amt"><?= ghs($weekly12) ?></span>
      </div>
      <div class="wk-dur"><?= micon('calendar_month', ['size' => 15]) ?> 12 weeks duration</div>
    </div>
    <span class="btn btn-primary btn-block">Start plan</span>
  </div>
</a>
