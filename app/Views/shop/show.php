<?php use App\Core\Csrf; ?>
<section class="wrap product-hero">
  <div>
    <div class="product-photo">
      <?php if (!empty($product['photo'])): ?>
        <img src="<?= url('/' . $product['photo']) ?>" alt="<?= e($product['name']) ?>">
      <?php else: ?>
        <div class="photo-placeholder"><b><?= e($product['name']) ?></b>photo coming from the shop</div>
      <?php endif; ?>
    </div>
    <p class="small muted mt-2">Sold by <strong><?= e($product['shop_name']) ?></strong>, <?= e($product['merchant_location']) ?>. You collect from the shop when your plan finishes.</p>
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
                <span class="picker-per"><?= ghs($opt['per']) ?> <span class="muted small">/ week</span></span>
                <span class="picker-weeks">&times; <?= $opt['weeks'] ?> weeks</span>
              </label>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="picker-first">You pay the first <strong data-first-amount><?= ghs($options[0]['per']) ?></strong> today by MoMo — that's what starts the plan.</p>
        <button class="btn btn-primary btn-block" type="submit">Start my plan</button>
        <p class="picker-note">Your money sits in escrow — the shop only gets paid when you finish. Change your mind? Cancel anytime and get a refund (minus 5%).</p>
      </form>
    </div>
  </div>
</section>
