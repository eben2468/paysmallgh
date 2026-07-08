<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1>Welcome back</h1>
  <p>Phone and PIN — same as always.</p>
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
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
  <p class="form-alt">New here? <a href="<?= url('/register') ?>">Create an account</a> — it takes a minute.</p>
</section>
