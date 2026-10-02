<?php use App\Core\Csrf; ?>
<section class="auth wrap">
  <div class="auth-card">
    <aside class="auth-aside is-buyer">
      <span class="auth-logo"><?= picture('assets/img/logo-mark.png', '', ['class' => 'logo-mark-img', 'width' => 32, 'height' => 32, 'loading' => 'eager']) ?><span>Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span></span>
      <span class="auth-eyebrow">For shoppers</span>
      <h2>Own what you need — small small.</h2>
      <ul class="auth-points">
        <li class="auth-point">
          <span class="ic"><?= micon('search', ['size' => 18]) ?></span>
          <div><b>Pick your thing</b><span>Phone, bed, sewing machine — from real shops.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('smartphone', ['size' => 18]) ?></span>
          <div><b>Pay weekly by MoMo</b><span>One prompt starts your plan. No lump sum.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('verified_user', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>Your money stays safe</b><span>Held in escrow till the item is fully yours.</span></div>
        </li>
      </ul>
      <p class="auth-foot"><?= micon('lock', ['size' => 16]) ?> Just your name, MoMo number, email and a PIN.</p>
    </aside>

    <div class="auth-form">
      <h1>Create your account</h1>
      <p class="sub">Your name, MoMo number and email, plus a PIN you'll remember.</p>
      <form method="post" action="<?= url('/register') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="name">Your name</label>
          <input id="name" name="name" type="text" required maxlength="120" autocomplete="name">
        </div>
        <div class="field">
          <label for="phone">Phone (your MoMo number)</label>
          <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" autocomplete="tel">
          <p class="field-hint">This is the number we'll charge and text receipts to.</p>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" required maxlength="190" placeholder="you@example.com" autocomplete="email" inputmode="email">
          <p class="field-hint">For payment receipts and your plan details.</p>
        </div>
        <div class="field">
          <label for="pin">Choose a PIN (4–6 digits)</label>
          <input id="pin" name="pin" type="password" required inputmode="numeric" pattern="\d{4,6}" maxlength="6">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
      </form>
      <p class="form-alt">Already have an account? <a href="<?= url('/login') ?>">Log in</a>.</p>
    </div>
  </div>
</section>
