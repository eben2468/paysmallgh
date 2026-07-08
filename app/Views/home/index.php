<?php use App\Core\Config; ?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <span class="hero-kicker">Lay-away, the MoMo way</span>
      <h1>That phone you've been eyeing? Pay
        <span class="squiggle">small small<svg viewBox="0 0 200 14" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9 Q 20 2, 40 8 T 78 8 T 116 9 T 154 7 T 198 9" fill="none" stroke="#E8590C" stroke-width="4" stroke-linecap="round"/></svg></span>
        and it's yours.</h1>
      <p class="hero-lead">GHS 100 a week. No lump sum. No borrowing from anybody. Your money sits safe until you finish — then you collect.</p>
      <div class="hero-actions">
        <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
        <a class="btn" href="<?= url('/how-it-works') ?>">How it works</a>
      </div>
      <p class="hero-ussd-hint">No smartphone? Dial <strong><?= e(Config::get('USSD_CODE', '*920*77#')) ?></strong> on any phone.</p>
    </div>

    <div class="hero-card-col">
      <div class="plan-card">
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
    </div>
  </div>
</section>

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

<?php if (!empty($products)): ?>
<section class="section section-dim">
  <div class="wrap">
    <div class="section-head">
      <h2>In the shops now</h2>
      <p>Real products, real prices, from merchants in Accra, Kumasi and Takoradi.</p>
    </div>
    <div class="product-grid">
      <?php foreach ($products as $p): ?>
        <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
    <p class="mt-3"><a class="btn" href="<?= url('/shop') ?>">See everything</a></p>
  </div>
</section>
<?php endif; ?>

<section class="section">
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
