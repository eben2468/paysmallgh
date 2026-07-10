<?php use App\Core\Csrf; ?>
<nav class="crumbs wrap" aria-label="Breadcrumb">
  <a href="<?= url('/') ?>">Home</a><span class="sep">/</span>
  <a href="<?= url('/shop') ?>">Shop</a><span class="sep">/</span>
  <a href="<?= url('/shop?category=' . urlencode($product['category'])) ?>"><?= e(ucfirst($product['category'])) ?></a><span class="sep">/</span>
  <span class="here"><?= e($product['name']) ?></span>
</nav>

<section class="wrap product-hero reveal">
  <div>
    <?php if (!empty($images)): ?>
      <div class="gallery" data-gallery>
        <div class="gallery-main">
          <img src="<?= url('/' . $images[0]['path']) ?>" alt="<?= e($product['name']) ?>" data-gallery-main>
          <?php if (count($images) > 1): ?>
            <span class="gallery-count"><?= micon('photo_library', ['size' => 15]) ?> <?= count($images) ?> photos</span>
          <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" data-gallery-thumb
                      data-full="<?= url('/' . $img['path']) ?>" aria-label="View photo <?= $i + 1 ?>">
                <img src="<?= url('/' . $img['path']) ?>" alt="<?= e($product['name']) ?> photo <?= $i + 1 ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="detail-photo">
        <div class="photo-placeholder"><?= micon(product_micon($product['category']), ['size' => 48]) ?><b><?= e($product['name']) ?></b><span class="small">photo coming from the shop</span></div>
      </div>
    <?php endif; ?>
    <div class="seller-card">
      <?= micon('storefront', ['size' => 26, 'class' => 'seller-ic']) ?>
      <div>
        <b><?= e($product['shop_name']) ?></b>
        <span><?= e($product['merchant_location']) ?> — you collect from the shop when your plan finishes.</span>
      </div>
    </div>
  </div>

  <div>
    <h1 class="product-title"><?= e($product['name']) ?></h1>
    <p class="product-meta"><?= e($product['shop_name']) ?> &middot; <?= e(ucfirst($product['category'])) ?></p>
    <p class="product-desc"><?= nl2br(e($product['description'])) ?></p>
    <p class="cash-price">Cash price: <b><?= ghs((int) $product['cash_price_pesewas']) ?></b></p>

    <div class="picker" data-picker>
      <h2>Choose how you'll pay</h2>
      <form id="plan-form" method="post" action="<?= url('/plan/start') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <div class="picker-options">
          <?php foreach ($options as $i => $opt): ?>
            <div class="picker-option">
              <input type="radio" id="opt-<?= $opt['weeks'] ?>" name="weeks" value="<?= $opt['weeks'] ?>"
                     data-per="<?= ghs($opt['per']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <label for="opt-<?= $opt['weeks'] ?>">
                <span class="picker-per"><?= ghs($opt['per']) ?><span class="muted"> / week</span></span>
                <span class="picker-weeks">for <?= $opt['weeks'] ?> weeks</span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="picker-first">You pay the first <strong data-first-amount><?= ghs($options[0]['per']) ?></strong> today by MoMo — that's what starts the plan.</p>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Start my plan</button>
        <p class="picker-note">Change your mind? Cancel anytime and get a refund (minus 5%).</p>
      </form>
    </div>

    <ul class="assure">
      <li><?= micon('shield', ['size' => 20, 'fill' => true]) ?> Your money sits in escrow — the shop only gets paid when you finish.</li>
      <li><?= micon('sms', ['size' => 20, 'fill' => true]) ?> SMS receipt after every single payment.</li>
      <li><?= micon('schedule', ['size' => 20, 'fill' => true]) ?> Miss a week? 3-day grace, friendly reminder, no penalty.</li>
    </ul>
  </div>
</section>

<div class="buy-bar-spacer" aria-hidden="true"></div>
<div class="buy-bar">
  <div class="buy-price"><span data-buy-amount><?= ghs($options[0]['per']) ?></span> <small>/week · first payment today</small></div>
  <button class="btn btn-primary" type="submit" form="plan-form">Start plan</button>
</div>
