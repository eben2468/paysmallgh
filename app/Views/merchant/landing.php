<section class="hero">
  <div class="wrap hero-layout">
    <div class="hero-copy">
      <span class="hero-kicker"><?= micon('storefront', ['size' => 14, 'fill' => true]) ?> For shop owners</span>
      <h1 class="hero-title">Stop losing the sale to <span class="accent">price</span>.</h1>
      <p class="hero-lead">Customers who can't drop GHS 1,200 today can pay GHS 100 every Friday instead. You still make the sale — we hold the money in escrow till the plan finishes.</p>
      <div class="hero-actions">
        <a class="btn btn-primary btn-lg" href="<?= url('/merchant/register') ?>">Register your shop</a>
        <a class="btn btn-ghost btn-lg" href="<?= url('/merchant/login') ?>">Merchant log in</a>
      </div>
      <div class="hero-trust">
        <?= micon('verified_user', ['size' => 20, 'fill' => true]) ?>
        <span>5% only when you sell. No monthly fee, no listing fee, no hardware to buy.</span>
      </div>
    </div>

    <div class="hero-visual">
      <div class="hero-card">
        <span class="receipt-tag">Your payout · plain numbers</span>
        <h3 style="font-size:1.15rem;margin:.3rem 0 0">A GHS 1,250 plan finishes</h3>
        <div class="perf"></div>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:.7rem">
          <li style="display:flex;justify-content:space-between"><span class="muted">Collected over 12 weeks</span> <strong><?= ghs(126000) ?></strong></li>
          <li style="display:flex;justify-content:space-between"><span class="muted">Platform fee (5%)</span> <strong>&minus; <?= ghs(6300) ?></strong></li>
          <li style="display:flex;justify-content:space-between;border-top:1px solid var(--outline-variant);padding-top:.7rem">
            <span>Paid to your MoMo</span> <strong style="color:var(--primary);font-size:1.15rem"><?= ghs(119700) ?></strong>
          </li>
        </ul>
        <p class="small muted mt-2">Paid out automatically the moment the last installment lands. You release the item after we pay you — never before.</p>
      </div>
    </div>
  </div>
</section>

<section class="section reveal">
  <div class="wrap">
    <div class="section-head center">
      <span class="section-eyebrow">Why shops join</span>
      <h2>The sale you'd have <span class="ital-em">lost</span>.</h2>
    </div>
    <div class="steps reveal stagger">
      <div class="step step-1">
        <div class="step-ic"><?= micon('trending_up', ['fill' => true]) ?></div>
        <h3>Sales walking out today</h3>
        <p>The customer who priced that bed three times and left? They could've been paying for it for a month already. Small small turns "I'll come back" into money.</p>
      </div>
      <div class="step step-2">
        <div class="step-ic"><?= micon('lock', ['fill' => true]) ?></div>
        <h3>Zero deposit risk</h3>
        <p>We hold every payment in escrow. You never chase anybody for money, and you never hand over goods that aren't fully paid.</p>
      </div>
      <div class="step step-3">
        <div class="step-ic"><?= micon('smartphone', ['fill' => true]) ?></div>
        <h3>No new hardware</h3>
        <p>You need a phone number. That's the setup. List your products, and we handle payments, receipts and reminders.</p>
      </div>
    </div>
  </div>
</section>

<section class="merchant-band reveal">
  <div class="wrap merchant-inner on-dark">
    <span class="merchant-eyebrow">What it costs</span>
    <p class="merchant-quote">5% of a completed plan. <span class="ital-em">Nothing</span> else.</p>
    <p class="merchant-attr">No monthly fee, no listing fee, no signup fee. The 5% comes out of the payout, only when a plan finishes. If you don't sell, you don't pay — simple as that.</p>
    <a class="btn btn-light btn-lg" href="<?= url('/merchant/register') ?>">Register your shop — free</a>
  </div>
</section>
