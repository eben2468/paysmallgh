<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1>Register your shop</h1>
  <p>Takes two minutes. We review new shops before they go live — usually same day.</p>
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
    <button class="btn btn-primary btn-block" type="submit">Register shop</button>
  </form>
  <p class="form-alt">Already registered? <a href="<?= url('/merchant/login') ?>">Log in</a>.</p>
</section>
