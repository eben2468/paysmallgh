<?php use App\Core\Csrf; ?>
<section class="auth wrap">
  <div class="auth-card">
    <aside class="auth-aside is-merchant">
      <span class="auth-logo">Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span>
      <span class="auth-eyebrow">For shop owners</span>
      <h2>Turn "I'll come back" into money.</h2>
      <ul class="auth-points">
        <li class="auth-point">
          <span class="ic"><?= micon('trending_up', ['size' => 18]) ?></span>
          <div><b>Make the sale today</b><span>Customers who can't pay in full pay you every Friday.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('verified_user', ['size' => 18, 'fill' => true]) ?></span>
          <div><b>Zero deposit risk</b><span>We hold every payment in escrow until the plan is done.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('percent', ['size' => 18]) ?></span>
          <div><b>5% only when you sell</b><span>No monthly fee, no listing fee, no hardware.</span></div>
        </li>
      </ul>
      <p class="auth-foot"><?= micon('schedule', ['size' => 16]) ?> We review new shops fast — usually same day.</p>
    </aside>

    <div class="auth-form">
      <h1>Register your shop</h1>
      <p class="sub">Takes two minutes. We review new shops before they go live.</p>
      <form method="post" action="<?= url('/merchant/register') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="shop_name">Shop name</label>
          <input id="shop_name" name="shop_name" type="text" required maxlength="160" placeholder="e.g. Kofi Mensah Phones">
        </div>
        <div class="field">
          <label for="owner_name">Your name (owner)</label>
          <input id="owner_name" name="owner_name" type="text" required maxlength="120">
        </div>
        <div class="field">
          <label for="phone">Business phone</label>
          <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX">
        </div>
        <div class="field">
          <label for="location">Where's the shop?</label>
          <input id="location" name="location" type="text" maxlength="160" placeholder="e.g. Circle, near the overhead">
        </div>
        <div class="field">
          <label for="payout_channel">How should we pay you?</label>
          <select id="payout_channel" name="payout_channel">
            <option value="momo">Mobile Money</option>
            <option value="bank">Bank account</option>
          </select>
        </div>
        <div class="field">
          <label for="payout_number">Payout number (MoMo or account no.)</label>
          <input id="payout_number" name="payout_number" type="text" placeholder="Leave empty to use business phone">
        </div>
        <div class="field">
          <label for="password">Password (8+ characters)</label>
          <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Register shop</button>
      </form>
      <p class="form-alt">Already registered? <a href="<?= url('/merchant/login') ?>">Log in</a>.</p>
    </div>
  </div>
</section>
