<?php
use App\Core\Csrf;
use App\Models\Product;

$lines = $lines ?? [];
$ready = array_filter($lines, static fn (array $l): bool => $l['problem'] === '');
$cashTotal = array_sum(array_map(static fn (array $l): int => (int) ($l['total'] ?? 0), $ready));
$freqNames = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
?>
<div class="wrap page-head">
  <h1>Your cart</h1>
  <p>Each item becomes its own plan — its own escrow, its own schedule, collected from its own shop. Pick how you'll pay for each one.</p>
</div>

<div class="wrap cart-layout">
  <div class="cart-lines">
    <?php if (!$lines): ?>
      <div class="empty-card">
        <h2>Your cart is empty</h2>
        <p>Find something you've been eyeing and tap <b>Add to cart</b>.</p>
        <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
      </div>
    <?php endif; ?>

    <?php foreach ($lines as $line): ?>
      <?php $p = $line['product']; ?>
      <article class="cart-line<?= $line['problem'] !== '' ? ' has-problem' : '' ?>">
        <a class="cart-photo" href="<?= $p ? product_url($p) : '#' ?>">
          <?php if ($p && $p['photo'] !== ''): ?>
            <?= picture($p['photo'], $p['name']) ?>
          <?php else: ?>
            <?= micon(product_micon((string) ($p['category'] ?? 'general')), ['size' => 32]) ?>
          <?php endif; ?>
        </a>

        <div class="cart-main">
          <div class="cart-top">
            <div>
              <h2 class="cart-name"><?= $p ? '<a href="' . product_url($p) . '">' . e($p['name']) . '</a>' : 'Item no longer available' ?></h2>
              <?php if (!empty($line['label'])): ?><p class="cart-variant"><?= e($line['label']) ?></p><?php endif; ?>
              <?php if ($p): ?><p class="cart-shop"><?= e($p['shop_name']) ?> · <?= e($p['merchant_location']) ?></p><?php endif; ?>
            </div>
            <form method="post" action="<?= url('/cart/remove') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="key" value="<?= e($line['key']) ?>">
              <button class="btn btn-sm btn-quiet" type="submit" aria-label="Remove <?= e($p['name'] ?? 'item') ?> from cart"><?= micon('delete', ['size' => 18]) ?> <span class="hide-mobile">Remove</span></button>
            </form>
          </div>

          <?php if ($p): ?>
          <div class="cart-money">
            <form class="cart-qty" method="post" action="<?= url('/cart/update') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="key" value="<?= e($line['key']) ?>">
              <label for="q-<?= e($line['key']) ?>">Qty</label>
              <?php $maxQ = $line['stock'] === null ? Product::MAX_QTY : max(1, min(Product::MAX_QTY, (int) $line['stock'])); ?>
              <select id="q-<?= e($line['key']) ?>" name="quantity" data-autosubmit>
                <?php for ($n = 1; $n <= max($maxQ, $line['qty']); $n++): ?>
                  <option value="<?= $n ?>" <?= $n === $line['qty'] ? 'selected' : '' ?>><?= $n ?></option>
                <?php endfor; ?>
              </select>
              <noscript><button class="btn btn-sm btn-ghost" type="submit">Update</button></noscript>
            </form>
            <span class="cart-math"><?= ghs((int) $line['unit']) ?><?= $line['qty'] > 1 ? ' &times; ' . $line['qty'] : '' ?></span>
            <b class="cart-total"><?= ghs((int) $line['total']) ?></b>
          </div>
          <?php endif; ?>

          <?php if ($line['problem'] !== ''): ?>
            <p class="cart-problem"><?= micon('error', ['size' => 18, 'fill' => true]) ?> <?= e($line['problem']) ?>
              <?php if ($p): ?><a href="<?= product_url($p) ?>">Open item</a><?php endif; ?></p>
          <?php else: ?>
            <form class="cart-start" method="post" action="<?= url('/plan/start') ?>">
              <?= Csrf::field() ?>
              <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
              <input type="hidden" name="variant_id" value="<?= $line['variant'] ? (int) $line['variant']['id'] : '' ?>">
              <input type="hidden" name="quantity" value="<?= (int) $line['qty'] ?>">
              <input type="hidden" name="cart_key" value="<?= e($line['key']) ?>">
              <label for="plan-<?= e($line['key']) ?>">How you'll pay</label>
              <?php
                // Default: weekly over 12 weeks when offered, else the first option listed.
                $default = '';
                foreach ($line['plans'] as $freq => $fp) {
                    foreach ($freq === 'once' ? [] : $fp['options'] as $opt) {
                        $default = $default !== '' ? $default : $freq . ':' . $opt['count'];
                        if ($freq === 'weekly' && $opt['count'] === 12) {
                            $default = 'weekly:12';
                        }
                    }
                }
              ?>
              <select id="plan-<?= e($line['key']) ?>" name="plan">
                <?php foreach ($line['plans'] as $freq => $fp): ?>
                  <?php if ($freq === 'once') { continue; } ?>
                  <optgroup label="<?= e($freqNames[$freq] ?? ucfirst($freq)) ?>">
                    <?php foreach ($fp['options'] as $opt): ?>
                      <?php $value = $freq . ':' . $opt['count']; ?>
                      <option value="<?= e($value) ?>" <?= $value === $default ? 'selected' : '' ?>>
                        <?= e($opt['perLabel']) ?> / <?= e($fp['unit']) ?> × <?= (int) $opt['count'] ?> <?= e($fp['noun']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <div class="cart-actions">
                <button class="btn btn-primary" type="submit">Start plan</button>
                <button class="btn btn-gold" type="submit" name="buy_now" value="1"><?= micon('bolt', ['size' => 18, 'fill' => true]) ?> Buy now · <?= ghs((int) $line['total']) ?></button>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if ($lines): ?>
  <aside class="cart-summary">
    <h2>Summary</h2>
    <dl>
      <div><dt>Items ready</dt><dd><?= count($ready) ?> of <?= count($lines) ?></dd></div>
      <div><dt>Cash value</dt><dd><?= ghs($cashTotal) ?></dd></div>
    </dl>
    <ul class="assure">
      <li><?= micon('shield', ['size' => 20, 'fill' => true]) ?> Every plan sits in escrow. Each shop is only paid when you finish its plan.</li>
      <li><?= micon('event_repeat', ['size' => 20, 'fill' => true]) ?> Starting a plan takes its first payment. The rest stay in your cart for later.</li>
    </ul>
    <a class="btn btn-ghost btn-block" href="<?= url('/shop') ?>">Keep shopping</a>
  </aside>
  <?php endif; ?>
</div>
