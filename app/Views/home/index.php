<?php
use App\Core\Config;

try {
    $cats = \App\Models\Product::categoryCounts();
} catch (\Throwable) {
    $cats = [];
}
?>

<section class="hero">
  <div class="wrap hero-grid">

    <aside class="hero-cats" aria-label="Shop by category">
      <h2>Shop by category</h2>
      <?php foreach ($cats as $cat => $n): ?>
        <a href="<?= url('/shop?category=' . urlencode((string) $cat)) ?>">
          <?= category_icon((string) $cat, 18) ?> <?= e(ucfirst((string) $cat)) ?>
          <span class="count"><?= (int) $n ?></span>
        </a>
      <?php endforeach; ?>
      <a class="hero-cats-all" href="<?= url('/shop') ?>">All products <?= svg_icon('arrow', 15) ?></a>
    </aside>

    <div class="hero-panel">
      <span class="hero-kicker">Lay-away, the MoMo way</span>
      <h1>Big things, paid
        <span class="squiggle">small small<svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9 Q 20 2, 40 8 T 78 8 T 116 9 T 154 7 T 198 9" fill="none" stroke="#F2B705" stroke-width="4" stroke-linecap="round"/></svg></span>
      </h1>
      <p class="hero-lead">That phone you've been eyeing? GHS 100 a week and it's yours. No lump sum. No borrowing from anybody. Your money sits safe in escrow until you finish.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= url('/shop') ?>">Start shopping</a>
        <a class="btn btn-ghost" href="<?= url('/how-it-works') ?>">How it works</a>
      </div>
      <div class="hero-chips" aria-hidden="true">
        <span class="chip">Tecno Spark 30C <b>GHS 105/wk</b></span>
        <span class="chip">Mahogany bed <b>GHS 200/wk</b></span>
        <span class="chip">Kaba &amp; slit <b>GHS 100/wk</b></span>
        <span class="chip chip-more">+ plenty more inside</span>
      </div>
    </div>

    <div class="hero-side">
      <div class="plan-card">
        <span class="plan-card-tag">Live plan — real math</span>
        <div class="plan-card-head">
          <h3>Tecno Spark 30C</h3>
          <span class="plan-card-price">GHS 1,250</span>
        </div>
        <p class="plan-card-sub">Kofi Mensah Phones, Circle</p>
        <p class="plan-math">GHS 105 &times; 12 weeks</p>
        <div class="progress" style="--pct: 42%"></div>
        <div class="progress-row">
          <span><strong>5 of 12</strong> paid</span>
          <span><strong>GHS 735</strong> to go</span>
        </div>
        <p class="plan-card-note">Ama pays every Friday after market. Seven more Fridays and the phone is hers.</p>
      </div>
      <div class="hero-ussd-card">
        <?= svg_icon('dialpad', 30) ?>
        <p><b><?= e(Config::get('USSD_CODE', '*920*77#')) ?></b><br>No smartphone? Dial this on any phone to check your plan or pay.</p>
      </div>
    </div>

  </div>
</section>

<div class="benefit-strip">
  <div class="wrap benefit-grid">
    <div class="benefit"><?= svg_icon('shield', 26) ?><div><b>Money held in escrow</b><span>The shop only gets paid when you finish</span></div></div>
    <div class="benefit"><?= svg_icon('receipt', 26) ?><div><b>SMS receipt, every payment</b><span>You always know where you've reached</span></div></div>
    <div class="benefit"><?= svg_icon('clock', 26) ?><div><b>Miss a week? No penalty</b><span>3-day grace and a friendly reminder</span></div></div>
    <div class="benefit"><?= svg_icon('dialpad', 26) ?><div><b>Works on any phone</b><span>Dial <?= e(Config::get('USSD_CODE', '*920*77#')) ?> — no data needed</span></div></div>
  </div>
</div>

<?php if (!empty($cats)): ?>
<section class="section" style="padding-bottom: 1.4rem;">
  <div class="wrap">
    <div class="section-bar">
      <h2>Shop by category</h2>
      <a class="see-all" href="<?= url('/shop') ?>">See all <?= svg_icon('arrow', 15) ?></a>
    </div>
    <div class="cat-tiles">
      <?php foreach ($cats as $cat => $n): ?>
        <a class="cat-tile" href="<?= url('/shop?category=' . urlencode((string) $cat)) ?>">
          <?= category_icon((string) $cat, 28) ?>
          <div><b><?= e(ucfirst((string) $cat)) ?></b><span><?= (int) $n ?> product<?= $n == 1 ? '' : 's' ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($products)): ?>
<section class="section">
  <div class="wrap">
    <div class="section-bar">
      <h2>In the shops now</h2>
      <a class="see-all" href="<?= url('/shop') ?>">See everything <?= svg_icon('arrow', 15) ?></a>
    </div>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
        <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <span>Tecno Spark 30C — <b>GHS 105/week</b></span>
    <span>Double bed, real mahogany — <b>GHS 200/week</b></span>
    <span>School uniforms x3 — <b>GHS 70/week</b></span>
    <span>Kaba and slit, sewn to fit — <b>GHS 100/week</b></span>
    <span>JBL earbuds (original o!) — <b>GHS 50/week</b></span>
    <span>Dining table + 4 chairs — <b>GHS 190/week</b></span>
    <span>Tecno Spark 30C — <b>GHS 105/week</b></span>
    <span>Double bed, real mahogany — <b>GHS 200/week</b></span>
    <span>School uniforms x3 — <b>GHS 70/week</b></span>
    <span>Kaba and slit, sewn to fit — <b>GHS 100/week</b></span>
    <span>JBL earbuds (original o!) — <b>GHS 50/week</b></span>
    <span>Dining table + 4 chairs — <b>GHS 190/week</b></span>
  </div>
</div>

<section class="section section-steps">
  <div class="wrap">
    <div class="section-head">
      <h2>How it works</h2>
      <p>Three steps. The first payment starts your plan — from there, you just keep going.</p>
    </div>
    <div class="steps">
      <div class="step">
        <h3>Pick your thing</h3>
        <p>Phone, bed, sewing — from real shops we've checked. You see the cash price and the weekly price side by side. No hidden anything.</p>
      </div>
      <div class="step">
        <h3>Pay the first one</h3>
        <p>Approve one MoMo prompt and your plan is live. Every payment after that gets you an SMS receipt on the spot.</p>
      </div>
      <div class="step">
        <h3>Finish and collect</h3>
        <p>Last payment lands, the shop gets paid, you get an SMS — go collect your item. Money never touches the shop till you're done, so nobody can chop it.</p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="pitch wrap">
    <h2>Own a shop? Stop watching customers walk away.</h2>
    <p>Customers who can't pay GHS 1,200 today can pay GHS 100 every Friday. You still make the sale. We hold the money — nobody runs off with your stock, and you get paid out the moment the plan finishes.</p>
    <a class="btn btn-primary" href="<?= url('/merchant') ?>">Sell on PaySmallSmall</a>
  </div>
</section>

<section class="ussd-callout">
  <div class="wrap">
    <p class="small muted">Any phone works — yam phone included.</p>
    <div class="ussd-code"><?= e(Config::get('USSD_CODE', '*920*77#')) ?></div>
    <p class="small">Dial it to check your plan, see what's left, or pay this week's installment. Under a minute, no data needed.</p>
  </div>
</section>
