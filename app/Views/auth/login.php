<?php use App\Core\Csrf; ?>
<section class="auth wrap">
  <div class="auth-card">
    <aside class="auth-aside is-buyer">
      <span class="auth-logo">Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span>
      <span class="auth-eyebrow">For shoppers</span>
      <h2>Welcome back. Your plans are waiting.</h2>
      <ul class="auth-points">
        <li class="auth-point">
          <span class="ic"><?= micon('shield', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>Money held in escrow</b><span>The shop only gets paid the day you finish.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('sms', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>SMS after every payment</b><span>You always know where you've reached.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('schedule', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>Miss a week? No penalty</b><span>3-day grace and a friendly reminder.</span></div>
        </li>
      </ul>
      <p class="auth-foot"><?= micon('dialpad', ['size' => 16]) ?> No smartphone? Dial <?= e(\App\Core\Config::get('USSD_CODE', '*920*77#')) ?></p>
    </aside>

    <div class="auth-form">
      <h1>Log in</h1>
      <p class="sub">Phone and PIN — same as always.</p>
      <form method="post" action="<?= url('/login') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="phone">Phone</label>
          <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" autocomplete="tel">
        </div>
        <div class="field">
          <label for="pin">PIN</label>
          <input id="pin" name="pin" type="password" required inputmode="numeric" maxlength="6" autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
      </form>
      <p class="form-alt">New here? <a href="<?= url('/register') ?>">Create an account</a> — it takes a minute.</p>
    </div>
  </div>
</section>
