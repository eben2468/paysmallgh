<?php
use App\Models\Product;

$categoryCounts = $categoryCounts ?? [];
$popular = $popular ?? [];
$budgets = $budgets ?? [];
$shops = $shops ?? [];
$recent = $recent ?? [];
$view = new App\Core\View();

// Marquee of real listings: repeat short lists so the strip is always full,
// then double it so the CSS loop (translate -50%) never shows a jump.
$ticker = [];
if (!empty($marquee)) {
    while (count($ticker) < 8) {
        $ticker = array_merge($ticker, $marquee);
    }
    $ticker = array_merge($ticker, $ticker);
}
?>
<section class="hero">
  <div class="wrap hero-layout">
    <div class="hero-copy">
      <span class="hero-kicker"><?= micon('lock', ['size' => 14, 'fill' => true]) ?> Secure layaway for Ghana</span>
      <h1 class="hero-title">Own what matters,<br><span class="accent">one installment</span> at a time.</h1>
      <p class="hero-lead">That phone you've been eyeing? Pay small small — GHS 100 a week — and it's yours. No lump sum, no borrowing. Your money sits safe in escrow till you finish.</p>

      <form class="hero-search" action="<?= url('/shop') ?>" method="get" role="search">
        <label class="sr-only" for="hero-q">What do you want to own?</label>
        <?= micon('search', ['class' => 'hero-search-ic']) ?>
        <input id="hero-q" type="search" name="q" placeholder="Phone, bed, kaba, fridge…" autocomplete="off">
        <button class="btn btn-primary" type="submit">Search</button>
      </form>

      <?php if ($categoryCounts): ?>
      <div class="hero-chips" aria-label="Popular categories">
        <span class="hero-chips-lbl">Popular:</span>
        <?php foreach (array_slice(array_keys($categoryCounts), 0, 4) as $slug): ?>
          <a class="chip" href="<?= url('/shop?category=' . urlencode((string) $slug)) ?>"><?= micon(product_micon((string) $slug), ['size' => 16]) ?> <?= e(Product::categoryLabel((string) $slug)) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="hero-trust">
        <?= micon('verified_user', ['size' => 20, 'fill' => true]) ?>
        <span>The shop only gets paid the day you finish. Nobody can chop your money. <a href="<?= url('/how-it-works') ?>">See how it works</a></span>
      </div>
    </div>

    <div class="hero-visual">
      <div class="hero-card">
        <div class="hero-phone-photo">
          <img src="<?= url('/assets/img/phone-hero.jpg') ?>" alt="A shopper's phone — their layaway plan is fully paid" width="260" height="347">
          <span class="hero-phone-caption"><b>Payment complete</b>Tecno Spark 30C · fully yours</span>
        </div>
        <div class="hero-badge">
          <div class="dot"><?= micon('check_circle', ['size' => 24, 'fill' => true]) ?></div>
          <div>
            <div class="k">Goal reached</div>
            <div class="v">GHS 1,200</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="benefit-strip">
  <div class="wrap benefit-grid">
    <div class="benefit"><?= micon('shield', ['fill' => true]) ?><div><b>Held in escrow</b><span>Shop gets paid only when you finish</span></div></div>
    <div class="benefit"><?= micon('sms', ['fill' => true]) ?><div><b>SMS every payment</b><span>You always know where you've reached</span></div></div>
    <div class="benefit"><?= micon('schedule', ['fill' => true]) ?><div><b>Miss a week? No penalty</b><span>3-day grace and a friendly reminder</span></div></div>
    <div class="benefit"><?= micon('verified', ['fill' => true]) ?><div><b>Checked shops</b><span>We approve every shop before it can sell</span></div></div>
  </div>
</div>

<?php if ($categoryCounts): ?>
<section class="section section-tight">
  <div class="wrap">
    <div class="section-bar">
      <h2>Shop by category</h2>
      <a class="see-all" href="<?= url('/shop') ?>">All products <?= micon('arrow_forward', ['size' => 16]) ?></a>
    </div>
    <div class="cat-tiles">
      <?php foreach ($categoryCounts as $slug => $n): ?>
        <a class="cat-tile" href="<?= url('/shop?category=' . urlencode((string) $slug)) ?>">
          <span class="cat-ic"><?= micon(product_micon((string) $slug), ['size' => 28]) ?></span>
          <b><?= e(Product::categoryLabel((string) $slug)) ?></b>
          <span><?= (int) $n ?> item<?= (int) $n === 1 ? '' : 's' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (count($popular) >= 2): ?>
<section class="section section-tight section-dim">
  <div class="wrap">
    <div class="section-bar">
      <div>
        <h2>People are paying for these</h2>
        <p class="muted">Ranked by how many shoppers have a plan running on them right now.</p>
      </div>
      <div class="rail-nav" data-rail-nav="popular">
        <button type="button" class="rail-btn" data-rail-prev aria-label="Scroll back"><?= micon('chevron_left') ?></button>
        <button type="button" class="rail-btn" data-rail-next aria-label="Scroll forward"><?= micon('chevron_right') ?></button>
      </div>
    </div>
    <div class="rail" data-rail="popular">
      <?php foreach ($popular as $p): ?>
        <?= $view->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($ticker): ?>
<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <?php foreach ($ticker as $t): ?>
      <span><?= e($t['name']) ?> — <b><?= ghs(Product::cardWeekly((int) $t['cash_price_pesewas'])) ?>/wk &times; <?= Product::CARD_WEEKS ?></b></span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($budgets): ?>
<section class="section section-tight">
  <div class="wrap">
    <div class="section-bar">
      <div>
        <h2>What can you put down a week?</h2>
        <p class="muted">Start from your chop money, not the price tag. Weekly amounts are over <?= Product::CARD_WEEKS ?> weeks.</p>
      </div>
    </div>
    <div class="budget-tiles">
      <?php foreach ($budgets as $slug => $b): ?>
        <a class="budget-tile budget-<?= e($slug) ?>" href="<?= url('/shop?budget=' . urlencode($slug)) ?>">
          <span class="budget-k"><?= $b['max'] === null ? 'Over' : 'Up to' ?></span>
          <span class="budget-v"><?= ghs($b['max'] ?? ($b['min'] - 1)) ?></span>
          <span class="budget-per">a week</span>
          <span class="budget-n"><?= (int) $b['count'] ?> item<?= (int) $b['count'] === 1 ? '' : 's' ?> <?= micon('arrow_forward', ['size' => 16]) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($products)): ?>
<section class="section section-tight<?= $budgets ? ' section-rule' : '' ?>">
  <div class="wrap">
    <div class="section-bar">
      <div>
        <h2>Just landed in the shops</h2>
        <p class="muted">Cash price and weekly price, side by side. No hidden anything.</p>
      </div>
      <a class="see-all" href="<?= url('/shop') ?>">See everything <?= micon('arrow_forward', ['size' => 16]) ?></a>
    </div>
    <div class="product-grid home-latest">
      <?php foreach ($products as $p): ?>
        <?= $view->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($shops): ?>
<section class="section section-tight section-dim">
  <div class="wrap">
    <div class="section-bar">
      <div>
        <h2>Shops selling this way</h2>
        <p class="muted">Every shop is approved by us before it can list. Verified means we've checked the owner's Ghana Card too.</p>
      </div>
    </div>
    <div class="shop-tiles">
      <?php foreach ($shops as $s): ?>
        <a class="shop-tile" href="<?= url('/shop?q=' . urlencode($s['shop_name'])) ?>">
          <span class="avatar shop-avatar"><?= e(mb_strtoupper(mb_substr($s['shop_name'], 0, 1))) ?></span>
          <span class="shop-tile-body">
            <b><?= e($s['shop_name']) ?></b>
            <?php if (!empty($s['verified'])): ?>
              <span class="verified-badge"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Verified</span>
            <?php endif; ?>
            <?php if ($s['location'] !== ''): ?>
              <span class="shop-tile-loc"><?= micon('location_on', ['size' => 15]) ?> <?= e($s['location']) ?></span>
            <?php endif; ?>
            <span class="shop-tile-meta"><?= (int) $s['product_count'] ?> item<?= (int) $s['product_count'] === 1 ? '' : 's' ?><?php if ((int) $s['plan_count'] > 0): ?> · <?= (int) $s['plan_count'] ?> plan<?= (int) $s['plan_count'] === 1 ? '' : 's' ?> running<?php endif; ?></span>
          </span>
          <?= micon('chevron_right', ['class' => 'shop-tile-go']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section section-rule">
  <div class="wrap">
    <div class="section-head center reveal">
      <span class="section-eyebrow">How it works</span>
      <h2>Three steps. Then <span class="ital-em">it's yours</span>.</h2>
      <p>No loan and no interest — just small payments till it's yours.</p>
    </div>
    <div class="steps reveal stagger">
      <div class="step step-1">
        <div class="step-ic"><?= micon('search', ['fill' => true]) ?></div>
        <h3>Browse &amp; select</h3>
        <p>Phone, bed, sewing machine — from real shops we've checked. You see the cash price and the weekly price side by side. No hidden anything.</p>
      </div>
      <div class="step step-2">
        <div class="step-ic"><?= micon('calendar_month', ['fill' => true]) ?></div>
        <h3>Commit &amp; pay</h3>
        <p>Approve one MoMo prompt and your plan is live. Pay weekly at your own pace — every payment gets you an SMS receipt on the spot.</p>
      </div>
      <div class="step step-3">
        <div class="step-ic"><?= micon('inventory_2', ['fill' => true]) ?></div>
        <h3>Finish &amp; collect</h3>
        <p>Last payment lands, the shop gets paid, you get an SMS — go collect your item. Money never touches the shop till you're done.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-dim reveal">
  <div class="wrap <?= $recent ? 'proof-split' : '' ?>">
    <div>
      <div class="section-head <?= $recent ? '' : 'center' ?>">
        <span class="section-eyebrow">Since we opened the doors</span>
        <h2>Small payments, <span class="ital-em">big</span> things.</h2>
      </div>
      <div class="stat-band <?= $recent ? 'stat-band-2' : '' ?>">
        <div class="stat-big"><b data-count="1.2" data-decimals="1" data-prefix="GHS " data-plus="M+">GHS 0</b><span>paid off small small by shoppers like you</span></div>
        <div class="stat-big green"><b data-count="312">0</b><span>plans finished — items collected, shops paid</span></div>
        <div class="stat-big"><b data-count="0" data-literal="GHS 0">GHS 0</b><span>lost to a shop vanishing — escrow won't allow it</span></div>
        <div class="stat-big green"><b data-count="48">0</b><span>shops across Accra, Kumasi &amp; Takoradi selling this way</span></div>
      </div>
    </div>

    <?php if ($recent): ?>
    <div class="feed">
      <p class="feed-head"><span class="live-dot" aria-hidden="true"></span> Recent payments</p>
      <ul class="feed-list">
        <?php foreach ($recent as $r): ?>
          <?php
            $once = $r['frequency'] === 'once';
            $last = (int) $r['number'] >= (int) $r['installments_total'];
          ?>
          <li>
            <span class="feed-ic<?= ($once || $last) ? ' done' : '' ?>"><?= micon(($once || $last) ? 'task_alt' : 'payments', ['size' => 20, 'fill' => true]) ?></span>
            <span class="feed-body">
              <b><?= e(masked_name($r['customer_name'])) ?> paid <?= ghs((int) $r['amount_pesewas']) ?></b>
              <a href="<?= url('/product/' . (int) $r['product_id']) ?>"><?= e($r['product_name']) ?></a>
              <span class="feed-meta"><?= $once ? 'Paid in full' : ($last ? 'Final payment — all done' : 'Payment ' . (int) $r['number'] . ' of ' . (int) $r['installments_total']) ?> · <?= e(ago((int) $r['mins_ago'])) ?></span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</section>

<section class="merchant-band reveal">
  <div class="wrap merchant-inner on-dark">
    <?= micon('storefront', ['fill' => true]) ?>
    <span class="merchant-eyebrow">For shop owners</span>
    <p class="merchant-quote">Grow your shop <span class="ital-em">without</span> the credit risk.</p>
    <p class="merchant-attr">Customers who can't drop GHS 1,200 today pay GHS 100 every Friday. You still make the sale. We hold the money in escrow — nobody runs off with your stock, and you're paid in full the moment the plan finishes.</p>
    <div class="hero-actions" style="justify-content:center">
      <a class="btn btn-light btn-lg" href="<?= url('/merchant/register') ?>">Become a merchant</a>
      <a class="btn btn-ghost btn-lg" href="<?= url('/merchant') ?>">Read merchant FAQ</a>
    </div>
  </div>
</section>
