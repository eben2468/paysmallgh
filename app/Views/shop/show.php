<?php
use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Wishlist;

$view = new App\Core\View();
$pid = (int) $product['id'];
$variants = $variants ?? [];
$optionNames = $optionNames ?? [];
$selected = $selected ?? null;

$unit = Product::unitPrice($product, $selected);
$compareAt = $product['compare_at_pesewas'] !== null ? (int) $product['compare_at_pesewas'] : null;
$discount = Product::discountPct($compareAt, $unit);
$stock = Product::stockFor($product, $selected);
$soldOut = $stock !== null && $stock < 1;
$sku = $selected !== null && !empty($selected['sku']) ? (string) $selected['sku'] : (string) ($product['sku'] ?? '');
$maxQty = $stock === null ? Product::MAX_QTY : max(1, min(Product::MAX_QTY, $stock));
$saved = Auth::userId() !== null && Wishlist::has((int) Auth::userId(), $pid);
$cancelFee = Config::int('CANCEL_FEE_PCT', 5);

// Everything the in-page picker needs to recompute prices without a reload.
$allowed = Product::allowedFrequencies($product);
$pickerData = [
    'defs' => array_intersect_key(Product::PLAN_DEFS, array_flip($allowed)),
    'floor' => Product::MIN_INSTALLMENT,
    'maxQty' => Product::MAX_QTY,
    'basePrice' => (int) $product['cash_price_pesewas'],
    'compareAt' => $compareAt,
    'stock' => $product['stock'] === null ? null : (int) $product['stock'],
    'sku' => (string) ($product['sku'] ?? ''),
    'optionCount' => count($optionNames),
    'variants' => array_map(static fn (array $v): array => [
        'id' => (int) $v['id'],
        'o' => [(string) $v['option1'], (string) $v['option2'], (string) $v['option3']],
        'price' => $v['price_pesewas'] === null ? null : (int) $v['price_pesewas'],
        'stock' => $v['stock'] === null ? null : (int) $v['stock'],
        'sku' => (string) ($v['sku'] ?? ''),
    ], $variants),
];

// Distinct values per option, in the order the merchant listed them.
$optionValues = [];
foreach ($optionNames as $i => $_) {
    foreach ($variants as $v) {
        $val = (string) $v["option{$i}"];
        if ($val !== '' && !in_array($val, $optionValues[$i] ?? [], true)) {
            $optionValues[$i][] = $val;
        }
    }
}

$defaultFreq = isset($plans['weekly']) ? 'weekly' : array_key_first($plans);
$firstOpt = $plans[$defaultFreq]['options'][0];
$freqLabels = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'once' => 'Pay in full'];
$fullPrice = ghs($unit);
$collectAt = trim($product['shop_name'] . ($product['merchant_location'] !== '' ? ', ' . $product['merchant_location'] : ''));
?>
<nav class="crumbs wrap" aria-label="Breadcrumb">
  <a href="<?= url('/') ?>">Home</a><span class="sep">/</span>
  <a href="<?= url('/shop') ?>">Shop</a><span class="sep">/</span>
  <a href="<?= url('/shop?category=' . urlencode($product['category'])) ?>"><?= e(Product::categoryLabel($product['category'])) ?></a><span class="sep">/</span>
  <span class="here"><?= e($product['name']) ?></span>
</nav>

<section class="wrap product-hero">
  <div>
    <?php if (!empty($images)): ?>
      <div class="gallery" data-gallery>
        <div class="gallery-main" data-zoom>
          <button type="button" class="gallery-open" data-lightbox-open aria-label="View photos full screen">
            <img src="<?= url('/' . $images[0]['path']) ?>" alt="<?= e($product['name']) ?>" data-gallery-main
                 onerror="this.onerror=null;this.style.display='none';this.closest('.gallery-main').querySelector('.photo-placeholder').style.display='';">
          </button>
          <div class="photo-placeholder" style="display:none"><?= micon(product_micon($product['category']), ['size' => 48]) ?><b><?= e($product['name']) ?></b><span class="small">photo coming from the shop</span></div>
          <?php if ($discount > 0): ?><span class="gallery-discount" data-discount-badge>-<?= $discount ?>%</span><?php endif; ?>
          <span class="gallery-hint" aria-hidden="true"><?= micon('zoom_in', ['size' => 16]) ?> <span class="hide-touch">Hover to zoom · </span>tap for full screen</span>
          <?php if (count($images) > 1): ?>
            <span class="gallery-count"><?= micon('photo_library', ['size' => 15]) ?> <?= count($images) ?> photos</span>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" data-gallery-thumb data-index="<?= $i ?>"
                      data-full="<?= url('/' . $img['path']) ?>" aria-label="View photo <?= $i + 1 ?>">
                <img src="<?= url('/' . $img['path']) ?>" alt="<?= e($product['name']) ?> photo <?= $i + 1 ?>" loading="lazy"
                     onerror="this.onerror=null;this.closest('.gallery-thumb').style.display='none';">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <dialog class="lightbox" data-lightbox aria-label="Product photos">
        <div class="lightbox-stage" data-lightbox-stage>
          <img src="" alt="<?= e($product['name']) ?>" data-lightbox-img>
        </div>
        <button type="button" class="lightbox-btn lightbox-close" data-lightbox-close aria-label="Close"><?= micon('close') ?></button>
        <?php if (count($images) > 1): ?>
          <button type="button" class="lightbox-btn lightbox-prev" data-lightbox-prev aria-label="Previous photo"><?= micon('chevron_left') ?></button>
          <button type="button" class="lightbox-btn lightbox-next" data-lightbox-next aria-label="Next photo"><?= micon('chevron_right') ?></button>
        <?php endif; ?>
        <p class="lightbox-count"><span data-lightbox-count>1 / <?= count($images) ?></span> · tap the photo to zoom</p>
      </dialog>
    <?php else: ?>
      <div class="detail-photo">
        <div class="photo-placeholder"><?= micon(product_micon($product['category']), ['size' => 48]) ?><b><?= e($product['name']) ?></b><span class="small">photo coming from the shop</span></div>
      </div>
    <?php endif; ?>
    <div class="seller-card">
      <?= micon('storefront', ['size' => 26, 'class' => 'seller-ic']) ?>
      <div>
        <b><?= e($product['shop_name']) ?><?php if (!empty($product['merchant_verified'])): ?> <span class="verified-badge" title="Identity verified by PaySmallSmall"><?= micon('verified', ['size' => 15, 'fill' => true]) ?> Verified</span><?php endif; ?></b>
        <span><?= e($product['merchant_location']) ?> — you collect from the shop when your plan finishes.</span>
      </div>
    </div>
  </div>

  <div>
    <div class="title-row">
      <h1 class="product-title"><?= e($product['name']) ?></h1>
      <form method="post" action="<?= url('/wishlist/toggle') ?>" data-wish>
        <?= Csrf::field() ?>
        <input type="hidden" name="product_id" value="<?= $pid ?>">
        <input type="hidden" name="back" value="<?= e('/product/' . $pid) ?>">
        <button type="submit" class="wish-btn wish-btn-lg<?= $saved ? ' is-saved' : '' ?>" aria-pressed="<?= $saved ? 'true' : 'false' ?>"
                data-auth="<?= Auth::userId() ? '1' : '0' ?>" aria-label="<?= $saved ? 'Remove from saved items' : 'Save for later' ?>" title="Save for later">
          <?= micon('favorite', ['fill' => $saved]) ?>
        </button>
      </form>
    </div>
    <p class="product-meta">
      <?= e($product['shop_name']) ?> &middot; <?= e(Product::categoryLabel($product['category'])) ?>
      <span data-sku-wrap<?= $sku === '' ? ' hidden' : '' ?>> &middot; SKU <span data-sku><?= e($sku) ?></span></span>
    </p>
    <?php if (($reviewSummary['count'] ?? 0) > 0): ?>
      <p class="rating-line"><?= stars((float) $reviewSummary['avg'], 18) ?>
        <a href="#reviews"><strong><?= number_format((float) $reviewSummary['avg'], 1) ?></strong>
        &middot; <?= (int) $reviewSummary['count'] ?> review<?= $reviewSummary['count'] === 1 ? '' : 's' ?></a>
      </p>
    <?php endif; ?>

    <div class="price-block">
      <div class="price-row">
        <span class="price-now" data-price><?= ghs($unit) ?></span>
        <s class="price-old" data-compare<?= $discount > 0 ? '' : ' hidden' ?>><?= $compareAt !== null ? ghs($compareAt) : '' ?></s>
        <span class="price-off" data-discount<?= $discount > 0 ? '' : ' hidden' ?>>-<?= $discount ?>%</span>
      </div>
      <p class="price-save" data-save<?= $discount > 0 ? '' : ' hidden' ?>>You save <?= $compareAt !== null && $discount > 0 ? ghs($compareAt - $unit) : '' ?></p>
      <p class="stock-line" data-stock-line>
        <?php if ($soldOut): ?>
          <span class="stock stock-out"><?= micon('block', ['size' => 18]) ?> Sold out for now</span>
        <?php elseif ($stock !== null && $stock <= 5): ?>
          <span class="stock stock-low"><?= micon('local_fire_department', ['size' => 18, 'fill' => true]) ?> Only <?= $stock ?> left</span>
        <?php else: ?>
          <span class="stock stock-in"><?= micon('check_circle', ['size' => 18, 'fill' => true]) ?> In stock</span>
        <?php endif; ?>
      </p>
    </div>

    <div class="picker" data-picker data-product='<?= e(json_encode($pickerData)) ?>'>
      <form id="plan-form" method="post" action="<?= url('/plan/start') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="product_id" value="<?= $pid ?>">
        <input type="hidden" name="variant_id" value="<?= $selected !== null ? (int) $selected['id'] : '' ?>" data-variant-id>
        <input type="hidden" name="frequency" value="<?= e($defaultFreq) ?>" data-frequency>
        <input type="hidden" name="count" value="<?= (int) $firstOpt['count'] ?>" data-count>

        <?php foreach ($optionNames as $i => $name): ?>
          <fieldset class="opt-group" data-opt-group="<?= $i ?>">
            <legend><?= e($name) ?>: <b data-opt-current><?= e($selected !== null ? (string) $selected["option{$i}"] : '') ?></b></legend>
            <div class="opt-chips">
              <?php foreach ($optionValues[$i] ?? [] as $value): ?>
                <label class="opt-chip">
                  <input type="radio" name="opt<?= $i ?>" value="<?= e($value) ?>" <?= $selected !== null && (string) $selected["option{$i}"] === $value ? 'checked' : '' ?>>
                  <span><?= e($value) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
        <p class="opt-missing" data-opt-missing hidden><?= micon('info', ['size' => 18]) ?> That combination isn't available. Try another.</p>

        <div class="qty-row">
          <label for="qty">Quantity</label>
          <div class="qty" data-qty>
            <button type="button" class="qty-btn" data-qty-step="-1" aria-label="One less"><?= micon('remove', ['size' => 18]) ?></button>
            <input id="qty" name="quantity" type="number" inputmode="numeric" min="1" max="<?= $maxQty ?>" value="1" data-qty-input>
            <button type="button" class="qty-btn" data-qty-step="1" aria-label="One more"><?= micon('add', ['size' => 18]) ?></button>
          </div>
          <span class="qty-total" data-qty-total hidden></span>
        </div>

        <h2>Choose how you'll pay</h2>
        <div class="freq-tabs" role="tablist" aria-label="How often you pay">
          <?php foreach ($plans as $freq => $fp): ?>
            <button type="button" class="freq-tab<?= $freq === $defaultFreq ? ' active' : '' ?><?= $freq === 'once' ? ' is-full' : '' ?>" data-freq="<?= e($freq) ?>">
              <?= e($freqLabels[$freq] ?? ucfirst($freq)) ?>
            </button>
          <?php endforeach; ?>
        </div>

        <div class="picker-options" data-duration-options>
          <?php foreach ($plans[$defaultFreq]['options'] as $i => $opt): ?>
            <div class="picker-option">
              <input type="radio" name="_dur" id="opt-<?= e($defaultFreq) ?>-<?= $opt['count'] ?>" value="<?= $opt['count'] ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <label for="opt-<?= e($defaultFreq) ?>-<?= $opt['count'] ?>">
                <span class="picker-per"><?= $opt['perLabel'] ?><span class="muted"> / <?= e($plans[$defaultFreq]['unit']) ?></span></span>
                <span class="picker-weeks">for <?= $opt['count'] ?> <?= e($plans[$defaultFreq]['noun']) ?></span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>

        <p class="picker-first" data-mode-plan>You pay the first <strong data-first-amount><?= $firstOpt['perLabel'] ?></strong> today by MoMo — that's what starts the plan.</p>
        <p class="picker-first" data-mode-full hidden>You pay <strong data-full-amount><?= $fullPrice ?></strong> today, once. The shop is paid and the item is yours — collect it from <?= e($product['shop_name']) ?>.</p>
        <button class="btn btn-primary btn-block btn-lg" type="submit" data-submit data-label-plan="Start my plan" data-label-full="Pay <?= e($fullPrice) ?> now"<?= $soldOut ? ' disabled' : '' ?>>Start my plan</button>
        <div class="buy-alt">
          <button class="btn btn-ghost" type="submit" formaction="<?= url('/cart/add') ?>" data-add-cart<?= $soldOut ? ' disabled' : '' ?>><?= micon('add_shopping_cart', ['size' => 20]) ?> Add to cart</button>
          <button class="btn btn-gold" type="submit" name="buy_now" value="1" data-buy-now<?= $soldOut ? ' disabled' : '' ?>><?= micon('bolt', ['size' => 20, 'fill' => true]) ?> Buy now</button>
        </div>
        <p class="cart-toast" data-cart-toast role="status" aria-live="polite" hidden></p>
        <p class="picker-note" data-mode-plan>Change your mind? Cancel anytime and get a refund (minus <?= $cancelFee ?>%). <b>Buy now</b> pays the full price today.</p>
        <p class="picker-note" data-mode-full hidden>Got the money now? Skip the plan and own it today.</p>
      </form>
    </div>

    <ul class="assure">
      <li><?= micon('shield', ['size' => 20, 'fill' => true]) ?> Your money sits in escrow — the shop only gets paid when you finish.</li>
      <li><?= micon('sms', ['size' => 20, 'fill' => true]) ?> SMS receipt after every single payment.</li>
      <li><?= micon('schedule', ['size' => 20, 'fill' => true]) ?> Miss a week? 3-day grace, friendly reminder, no penalty.</li>
    </ul>
  </div>
</section>

<section class="wrap product-info">
  <details class="info-block" open>
    <summary><h2>Description</h2><?= micon('expand_more', ['class' => 'info-chev']) ?></summary>
    <div class="info-body product-desc"><?= trim((string) $product['description']) !== '' ? nl2br(e($product['description'])) : '<span class="muted">The shop hasn\'t added a description yet.</span>' ?></div>
  </details>

  <?php if (!empty($specs)): ?>
    <details class="info-block" open>
      <summary><h2>Specifications</h2><?= micon('expand_more', ['class' => 'info-chev']) ?></summary>
      <div class="info-body">
        <table class="spec-table">
          <tbody>
            <?php foreach ($specs as [$k, $v]): ?>
              <tr><?php if ($k === ''): ?><td colspan="2"><?= e($v) ?></td><?php else: ?><th scope="row"><?= e($k) ?></th><td><?= e($v) ?></td><?php endif; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endif; ?>

  <details class="info-block">
    <summary><h2>Delivery &amp; collection</h2><?= micon('expand_more', ['class' => 'info-chev']) ?></summary>
    <div class="info-body">
      <ul class="info-list">
        <li><?= micon('storefront', ['size' => 20]) ?><span><b>Collect from the shop</b> once your plan is fully paid: <?= e($collectAt) ?>. We text you the moment it's ready — show the SMS at the shop.</span></li>
        <li><?= micon('inventory', ['size' => 20]) ?><span><b>Your item is held for you.</b> When your first payment lands, the shop sets it aside for you.</span></li>
        <?php if (trim((string) ($product['delivery_info'] ?? '')) !== ''): ?>
          <li><?= micon('local_shipping', ['size' => 20]) ?><span><b>From the shop:</b> <?= nl2br(e($product['delivery_info'])) ?></span></li>
        <?php else: ?>
          <li><?= micon('local_shipping', ['size' => 20]) ?><span>Need it delivered? Ask <?= e($product['shop_name']) ?> when you collect — they haven't listed delivery for this item.</span></li>
        <?php endif; ?>
      </ul>
    </div>
  </details>

  <details class="info-block">
    <summary><h2>Returns &amp; refunds</h2><?= micon('expand_more', ['class' => 'info-chev']) ?></summary>
    <div class="info-body">
      <ul class="info-list">
        <li><?= micon('undo', ['size' => 20]) ?><span><b>Before you finish paying:</b> cancel any time from My plans. Everything you've paid comes back to your MoMo or card, minus a <?= $cancelFee ?>% cancellation fee.</span></li>
        <li><?= micon('shield', ['size' => 20]) ?><span><b>Shop doesn't deliver?</b> The shop only gets the money after you finish, so your payments are never at risk.</span></li>
        <?php if (trim((string) ($product['return_policy'] ?? '')) !== ''): ?>
          <li><?= micon('assignment_return', ['size' => 20]) ?><span><b>After you collect (shop's policy):</b> <?= nl2br(e($product['return_policy'])) ?></span></li>
        <?php else: ?>
          <li><?= micon('assignment_return', ['size' => 20]) ?><span><b>After you collect:</b> returns are between you and the shop. Check the item well before you leave.</span></li>
        <?php endif; ?>
      </ul>
    </div>
  </details>
</section>

<section class="wrap reviews-section" id="reviews">
  <div class="reviews-head">
    <h2>What buyers say</h2>
  </div>

  <div class="reviews-grid">
    <div class="reviews-list">
      <?php if (($reviewSummary['count'] ?? 0) > 0): ?>
        <div class="rating-summary">
          <div class="rating-summary-score">
            <span class="score-big"><?= number_format((float) $reviewSummary['avg'], 1) ?></span>
            <?= stars((float) $reviewSummary['avg'], 20) ?>
            <span class="small muted"><?= (int) $reviewSummary['count'] ?> review<?= $reviewSummary['count'] === 1 ? '' : 's' ?></span>
          </div>
          <ul class="rating-bars">
            <?php foreach (($ratingBars ?? []) as $star => $n): ?>
              <?php $pct = (int) round($n * 100 / max(1, (int) $reviewSummary['count'])); ?>
              <li><span class="rb-star"><?= $star ?> <?= micon('star', ['size' => 14, 'fill' => true]) ?></span>
                <span class="rb-track"><span class="rb-fill" style="width:<?= $pct ?>%"></span></span>
                <span class="rb-n"><?= (int) $n ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (empty($reviews)): ?>
        <div class="empty-card" style="text-align:left">
          <h3 style="font-size:1.05rem;margin:0 0 .3rem">No reviews yet</h3>
          <p class="muted" style="margin:0">Be the first to tell people how this went.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
          <article class="review">
            <div class="review-top">
              <span class="review-name"><?= e($rev['user_name']) ?></span>
              <?php if (!empty($rev['verified_purchase'])): ?>
                <span class="verified-badge" title="Bought on a plan"><?= micon('verified', ['size' => 13, 'fill' => true]) ?> Verified buyer</span>
              <?php endif; ?>
              <span class="review-date small muted"><?= e(date('j M Y', strtotime((string) $rev['created_at']))) ?></span>
            </div>
            <div class="review-stars"><?= stars((float) $rev['rating'], 16) ?></div>
            <?php if (trim((string) $rev['body']) !== ''): ?>
              <p class="review-body"><?= nl2br(e($rev['body'])) ?></p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="review-form-card">
      <?php if (empty($_SESSION['user_id'])): ?>
        <h3>Bought this? Say your piece.</h3>
        <p class="small muted">Log in to leave a review.</p>
        <a class="btn btn-outline btn-block" href="<?= url('/login') ?>">Log in to review</a>
      <?php else: ?>
        <h3><?= $myReview ? 'Update your review' : 'Leave a review' ?></h3>
        <form method="post" action="<?= url('/product/' . $pid . '/review') ?>">
          <?= Csrf::field() ?>
          <?php $cur = (int) ($myReview['rating'] ?? 0); ?>
          <div class="star-input" role="radiogroup" aria-label="Your rating">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <input type="radio" id="star-<?= $s ?>" name="rating" value="<?= $s ?>" <?= $cur === $s ? 'checked' : '' ?> required>
              <label for="star-<?= $s ?>" title="<?= $s ?> star<?= $s === 1 ? '' : 's' ?>"><?= micon('star', ['size' => 30, 'fill' => true]) ?></label>
            <?php endfor; ?>
          </div>
          <div class="field">
            <label for="review-body">Your review <span class="muted">(optional)</span></label>
            <textarea id="review-body" name="body" rows="4" maxlength="600" placeholder="How was the shop? Did the item match? Would you buy again?"><?= e($myReview['body'] ?? '') ?></textarea>
          </div>
          <button class="btn btn-primary btn-block" type="submit"><?= $myReview ? 'Update review' : 'Post review' ?></button>
        </form>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?php foreach ([['related', 'You might also like', $related ?? []], ['recent', 'You looked at these', $recent ?? []]] as [$railId, $railTitle, $railItems]): ?>
  <?php if (!empty($railItems)): ?>
  <section class="wrap section-tight">
    <div class="section-bar">
      <h2><?= e($railTitle) ?></h2>
      <div class="rail-nav" data-rail-nav="<?= e($railId) ?>">
        <button type="button" class="rail-btn" data-rail-prev aria-label="Scroll back"><?= micon('chevron_left') ?></button>
        <button type="button" class="rail-btn" data-rail-next aria-label="Scroll forward"><?= micon('chevron_right') ?></button>
      </div>
    </div>
    <div class="rail" data-rail="<?= e($railId) ?>">
      <?php foreach ($railItems as $p): ?>
        <?= $view->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
<?php endforeach; ?>

<div class="buy-bar-spacer" aria-hidden="true"></div>
<div class="buy-bar">
  <div class="buy-price"><span data-buy-amount><?= $firstOpt['perLabel'] ?></span> <small data-buy-note data-note-plan="first payment today" data-note-full="pay once, own it today">first payment today</small></div>
  <button class="btn btn-primary" type="submit" form="plan-form" data-buy-submit data-label-plan="Start plan" data-label-full="Pay now"<?= $soldOut ? ' disabled' : '' ?>>Start plan</button>
</div>
