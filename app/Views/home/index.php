<section class="hero">
  <div class="wrap hero-layout">
    <div class="hero-copy">
      <span class="hero-kicker"><?= micon('lock', ['size' => 14, 'fill' => true]) ?> Secure layaway for Ghana</span>
      <h1 class="hero-title">Own what matters,<br><span class="accent">one installment</span> at a time.</h1>
      <p class="hero-lead">That phone you've been eyeing? Pay small small — GHS 100 a week — and it's yours. No lump sum, no borrowing. Your money sits safe in escrow till you finish.</p>

      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= url('/shop') ?>">Start browsing <?= micon('arrow_forward', ['size' => 20, 'fill' => true]) ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= url('/how-it-works') ?>">How it works</a>
      </div>

      <div class="hero-trust">
        <?= micon('verified_user', ['size' => 20, 'fill' => true]) ?>
        <span>The shop only gets paid the day you finish. Nobody can chop your money.</span>
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

<div class="benefit-strip reveal">
  <div class="wrap benefit-grid">
    <div class="benefit"><?= micon('shield', ['fill' => true]) ?><div><b>Held in escrow</b><span>Shop gets paid only when you finish</span></div></div>
    <div class="benefit"><?= micon('sms', ['fill' => true]) ?><div><b>SMS every payment</b><span>You always know where you've reached</span></div></div>
    <div class="benefit"><?= micon('schedule', ['fill' => true]) ?><div><b>Miss a week? No penalty</b><span>3-day grace and a friendly reminder</span></div></div>
    <div class="benefit"><?= micon('verified', ['fill' => true]) ?><div><b>Verified shops</b><span>We check every shop before it can sell</span></div></div>
  </div>
</div>

<div class="marquee" aria-hidden="true">
  <div class="marquee-track">
    <span>Samsung A15 — <b>GHS 95/wk &times; 12</b></span>
    <span>Sewing machine — <b>GHS 60/wk &times; 10</b></span>
    <span>School trunk — <b>GHS 25/wk &times; 8</b></span>
    <span>Double bed, real mahogany — <b>GHS 200/wk &times; 12</b></span>
    <span>Kaba &amp; slit, sewn to fit — <b>GHS 100/wk &times; 10</b></span>
    <span>JBL earbuds (original o!) — <b>GHS 50/wk &times; 8</b></span>
    <span>Samsung A15 — <b>GHS 95/wk &times; 12</b></span>
    <span>Sewing machine — <b>GHS 60/wk &times; 10</b></span>
    <span>School trunk — <b>GHS 25/wk &times; 8</b></span>
    <span>Double bed, real mahogany — <b>GHS 200/wk &times; 12</b></span>
    <span>Kaba &amp; slit, sewn to fit — <b>GHS 100/wk &times; 10</b></span>
    <span>JBL earbuds (original o!) — <b>GHS 50/wk &times; 8</b></span>
  </div>
</div>

<section class="section">
  <div class="wrap">
    <div class="section-head center reveal">
      <span class="section-eyebrow">How it works</span>
      <h2>Three steps. Then <span class="ital-em">it's yours</span>.</h2>
      <p>A simple, transparent process to get what you need — without the stress of paying everything today, and without a loan.</p>
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
  <div class="wrap">
    <div class="section-head center">
      <span class="section-eyebrow">Since we opened the doors</span>
      <h2>Small payments, <span class="ital-em">big</span> things.</h2>
    </div>
    <div class="stat-band">
      <div class="stat-big"><b data-count="1.2" data-decimals="1" data-prefix="GHS " data-plus="M+">GHS 0</b><span>paid off small small by shoppers like you</span></div>
      <div class="stat-big green"><b data-count="312">0</b><span>plans finished — items collected, shops paid</span></div>
      <div class="stat-big"><b data-count="0" data-literal="GHS 0">GHS 0</b><span>lost to a shop vanishing — escrow won't allow it</span></div>
      <div class="stat-big green"><b data-count="48">0</b><span>shops across Accra, Kumasi &amp; Takoradi selling this way</span></div>
    </div>
  </div>
</section>

<?php if (!empty($products)): ?>
<section class="section reveal">
  <div class="wrap">
    <div class="section-bar">
      <div>
        <h2>In the shops now</h2>
        <p class="muted">See how manageable big purchases become with structured layaway plans.</p>
      </div>
      <a class="see-all" href="<?= url('/shop') ?>">See everything <?= micon('arrow_forward', ['size' => 16]) ?></a>
    </div>
    <div class="product-grid">
      <?php foreach (array_slice($products, 0, 8) as $p): ?>
        <?= (new App\Core\View())->partial('partials/product-card', ['p' => $p]) ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

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
