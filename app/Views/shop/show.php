<?php use App\Core\Csrf; ?>
<nav class="crumbs wrap" aria-label="Breadcrumb">
  <a href="<?= url('/') ?>">Home</a><span class="sep">/</span>
  <a href="<?= url('/shop') ?>">Shop</a><span class="sep">/</span>
  <a href="<?= url('/shop?category=' . urlencode($product['category'])) ?>"><?= e(ucfirst($product['category'])) ?></a><span class="sep">/</span>
  <span class="here"><?= e($product['name']) ?></span>
</nav>

<section class="wrap product-hero">
  <div>
    <div class="product-photo">
      <?php if (!empty($product['photo'])): ?>
        <img src="<?= url('/' . $product['photo']) ?>" alt="<?= e($product['name']) ?>">
      <?php else: ?>
        <div class="photo-placeholder"><?= category_icon($product['category'], 44) ?><b><?= e($product['name']) ?></b>photo coming from the shop</div>
      <?php endif; ?>
    </div>
    <div class="seller-card">
      <?= svg_icon('store', 26) ?>
      <div>
        <b><?= e($product['shop_name']) ?></b>
        <span><?= e($product['merchant_location']) ?> — you collect from the shop when your plan finishes.</span>
      </div>
    </div>
  </div>

  <div>
    <h1 class="product-title"><?= e($product['name']) ?></h1>
    <p class="product-meta"><?= e($product['shop_name']) ?> &middot; <?= e(ucfirst($product['category'])) ?></p>
    <p class="mb-2"><?= nl2br(e($product['description'])) ?></p>
    <p class="cash-price">Cash price: <b><?= ghs((int) $product['cash_price_pesewas']) ?></b></p>

    <div class="picker" data-picker>
      <h2>Choose how you'll pay</h2>
      <form method="post" action="<?= url('/plan/start') ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
        <div class="picker-options">
          <?php foreach ($options as $i => $opt): ?>
            <div class="picker-option">
              <input type="radio" id="opt-<?= $opt['weeks'] ?>" name="weeks" value="<?= $opt['weeks'] ?>"
                     data-per="<?= ghs($opt['per']) ?>" <?= $i === 0 ? 'checked' : '' ?>>
              <label for="opt-<?= $opt['weeks'] ?>">
                <span class="picker-per"><?= ghs($opt['per']) ?><span class="muted small"> / week</span></span>
                <span class="picker-weeks">for <?= $opt['weeks'] ?> weeks</span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="picker-first">You pay the first <strong data-first-amount><?= ghs($options[0]['per']) ?></strong> today by MoMo — that's what starts the plan.</p>
        <button class="btn btn-primary btn-block" type="submit">Start my plan</button>
        <p class="picker-note">Change your mind? Cancel anytime and get a refund (minus 5%).</p>
      </form>
    </div>

    <ul class="assure">
      <li><?= svg_icon('shield', 18) ?> Your money sits in escrow — the shop only gets paid when you finish.</li>
      <li><?= svg_icon('receipt', 18) ?> SMS receipt after every single payment.</li>
      <li><?= svg_icon('clock', 18) ?> Miss a week? 3-day grace, friendly reminder, no penalty.</li>
    </ul>
  </div>
</section>
