<?php use App\Core\Csrf; ?>
<section class="auth wrap">
  <div class="auth-card">
    <aside class="auth-aside is-merchant">
      <span class="auth-logo">Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span>
      <span class="auth-eyebrow">For shop owners</span>
      <h2>Back to business.</h2>
      <ul class="auth-points">
        <li class="auth-point">
          <span class="ic"><?= micon('payments', ['size' => 18]) ?></span>
          <div><b>Track every plan live</b><span>See each payment your customers make, in real time.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('account_balance', ['size' => 18]) ?></span>
          <div><b>Paid automatically</b><span>Payout hits your MoMo the moment a plan finishes.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('lock', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>Zero deposit risk</b><span>We hold the money — you never hand over unpaid stock.</span></div>
        </li>
      </ul>
      <p class="auth-foot"><?= micon('storefront', ['size' => 16]) ?> 5% only when you sell. Nothing else.</p>
    </aside>

    <div class="auth-form">
      <h1>Merchant log in</h1>
      <p class="sub">Your shop dashboard is one step away.</p>
      <form method="post" action="<?= url('/merchant/login') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="phone">Business phone</label>
          <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" autocomplete="tel">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
      </form>
      <p class="form-alt">New shop? <a href="<?= url('/merchant/register') ?>">Register here</a>.</p>
    </div>
  </div>
</section>
