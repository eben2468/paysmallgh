<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1>Merchant log in</h1>
  <p>Back to business.</p>
  <form method="post" action="<?= url('/merchant/login') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="phone">Business phone</label>
      <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX">
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required autocomplete="current-password">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
  <p class="form-alt">New shop? <a href="<?= url('/merchant/register') ?>">Register here</a>.</p>
</section>
