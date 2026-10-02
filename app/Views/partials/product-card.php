<?php
/**
 * Product card. Expects $p: a listing row from Product::browse()/popular()/
 * related()/byIds() (price_from, price_to, discount_pct, in_stock, plan_count,
 * avg_rating, review_count, merchant_location …).
 */
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Wishlist;

$pid = (int) $p['id'];
$price = (int) ($p['price_from'] ?? $p['cash_price_pesewas']);
$isRange = (int) ($p['price_to'] ?? $price) > $price;
$weekly = Product::cardWeekly($price);
$cat = $p['category'] ?? 'general';
$planCount = (int) ($p['plan_count'] ?? 0);
$discount = (int) ($p['discount_pct'] ?? 0);
$inStock = (int) ($p['in_stock'] ?? 1) === 1;
$reviews = (int) ($p['review_count'] ?? 0);
$uid = Auth::userId();
$saved = $uid !== null && Wishlist::has((int) $uid, $pid);
$placeholder = '<div class="photo-placeholder"' . (!empty($p['photo']) ? ' style="display:none"' : '') . '>'
    . micon(product_micon($cat), ['size' => 40]) . '<b>' . e($p['name']) . '</b>'
    . '<span class="small">photo coming from the shop</span></div>';
?>
<div class="card-wrap<?= $inStock ? '' : ' is-sold-out' ?>">
  <a class="product-card" href="<?= product_url($p) ?>">
    <div class="product-photo">
      <?php if ($discount > 0): ?><span class="card-discount">-<?= $discount ?>%</span><?php endif; ?>
      <?php if (!empty($p['photo'])): ?>
        <?= picture((string) $p['photo'], $p['name'] . ' — ' . Product::categoryLabel((string) $cat) . ' from ' . $p['shop_name'], [
            'onerror' => "this.onerror=null;this.style.display='none';this.closest('.product-photo').querySelector('.photo-placeholder').style.display='';",
        ]) ?>
      <?php endif; ?>
      <?= $placeholder ?>
      <?php if (!$inStock): ?>
        <span class="card-soldout">Sold out</span>
      <?php elseif ($planCount > 0): ?>
        <span class="card-social"><?= micon('group', ['fill' => true]) ?> <?= $planCount ?> paying</span>
      <?php endif; ?>
    </div>
    <div class="product-body">
      <span class="product-cat"><?= e(ucfirst((string) $cat)) ?></span>
      <span class="product-name"><?= e($p['name']) ?></span>
      <?php if ($reviews > 0): ?>
        <span class="product-rating"><?= micon('star', ['fill' => true]) ?> <?= number_format((float) $p['avg_rating'], 1) ?> <span>(<?= $reviews ?>)</span></span>
      <?php endif; ?>
      <span class="product-shop"><?= e($p['shop_name']) ?><?php if (!empty($p['merchant_verified'])): ?><span class="verified-badge" title="Verified shop"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Verified</span><?php endif; ?></span>
      <?php if (!empty($p['merchant_location'])): ?>
        <span class="product-loc"><?= micon('location_on', ['size' => 14]) ?> <?= e($p['merchant_location']) ?></span>
      <?php endif; ?>
      <div class="product-cash">
        <span class="amt"><?= $isRange ? '<small>From</small> ' : '' ?><?= ghs($price) ?></span>
        <?php if ($discount > 0 && !empty($p['compare_at_pesewas'])): ?>
          <s class="lbl"><?= ghs((int) $p['compare_at_pesewas']) ?></s>
        <?php else: ?>
          <span class="lbl">Cash price</span>
        <?php endif; ?>
      </div>
      <div class="weekly-box">
        <div class="row1">
          <span class="wk-lbl">Weekly plan</span>
          <span class="wk-amt"><?= ghs($weekly) ?></span>
        </div>
        <div class="wk-dur"><?= micon('calendar_month', ['size' => 15]) ?> <?= Product::CARD_WEEKS ?> weeks duration</div>
      </div>
      <span class="btn btn-primary btn-block"><?= $inStock ? 'Start plan' : 'See details' ?></span>
    </div>
  </a>
  <form class="card-wish" method="post" action="<?= url('/wishlist/toggle') ?>" data-wish>
    <?= Csrf::field() ?>
    <input type="hidden" name="product_id" value="<?= $pid ?>">
    <input type="hidden" name="back" value="<?= e(current_path()) ?>">
    <button type="submit" class="wish-btn<?= $saved ? ' is-saved' : '' ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"
            data-auth="<?= $uid ? '1' : '0' ?>" aria-label="<?= $saved ? 'Remove ' . e($p['name']) . ' from saved items' : 'Save ' . e($p['name']) . ' for later' ?>">
      <?= micon('favorite', ['fill' => $saved]) ?>
    </button>
  </form>
</div>
