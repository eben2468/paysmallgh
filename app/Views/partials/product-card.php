<?php
/** Expects $p (product row with shop_name; merchant_location and plan_count when available). */
$weekly12 = App\Models\Product::cardWeekly((int) $p['cash_price_pesewas']);
$cat = $p['category'] ?? 'general';
$planCount = (int) ($p['plan_count'] ?? 0);
$placeholder = '<div class="photo-placeholder"' . (!empty($p['photo']) ? ' style="display:none"' : '') . '>'
    . micon(product_micon($cat), ['size' => 40]) . '<b>' . e($p['name']) . '</b>'
    . '<span class="small">photo coming from the shop</span></div>';
?>
<a class="product-card" href="<?= url('/product/' . $p['id']) ?>">
  <div class="product-photo">
    <span class="card-badge"><?= ghs($weekly12) ?>/wk</span>
    <?php if (!empty($p['photo'])): ?>
      <img src="<?= url('/' . $p['photo']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"
           onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='';">
    <?php endif; ?>
    <?= $placeholder ?>
    <?php if ($planCount > 0): ?>
      <span class="card-social"><?= micon('group', ['fill' => true]) ?> <?= $planCount ?> paying</span>
    <?php endif; ?>
  </div>
  <div class="product-body">
    <span class="product-cat"><?= e(ucfirst((string) $cat)) ?></span>
    <span class="product-name"><?= e($p['name']) ?></span>
    <span class="product-shop"><?= e($p['shop_name']) ?><?php if (!empty($p['merchant_verified'])): ?><span class="verified-badge" title="Verified shop"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Verified</span><?php endif; ?></span>
    <?php if (!empty($p['merchant_location'])): ?>
      <span class="product-loc"><?= micon('location_on', ['size' => 14]) ?> <?= e($p['merchant_location']) ?></span>
    <?php endif; ?>
    <div class="product-cash">
      <span class="amt"><?= ghs((int) $p['cash_price_pesewas']) ?></span>
      <span class="lbl">Cash price</span>
    </div>
    <div class="weekly-box">
      <div class="row1">
        <span class="wk-lbl">Weekly plan</span>
        <span class="wk-amt"><?= ghs($weekly12) ?></span>
      </div>
      <div class="wk-dur"><?= micon('calendar_month', ['size' => 15]) ?> <?= App\Models\Product::CARD_WEEKS ?> weeks duration</div>
    </div>
    <span class="btn btn-primary btn-block">Start plan</span>
  </div>
</a>
