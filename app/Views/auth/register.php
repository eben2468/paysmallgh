<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1>Create your account</h1>
  <p>Just your name, your MoMo number, and a PIN you'll remember.</p>
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
      <label for="pin">Choose a PIN (4–6 digits)</label>
      <input id="pin" name="pin" type="password" required inputmode="numeric" pattern="\d{4,6}" maxlength="6">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Create account</button>
  </form>
  <p class="form-alt">Already have an account? <a href="<?= url('/login') ?>">Log in</a>.</p>
</section>
