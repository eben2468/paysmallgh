<?php use App\Core\Csrf; ?>
<section class="wrap form-page">
  <h1>Admin</h1>
  <p>Staff only.</p>
  <form method="post" action="<?= url('/admin/login') ?>">
    <?= Csrf::field() ?>
    <div class="field">
      <label for="phone">Phone</label>
      <input id="phone" name="phone" type="tel" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Log in</button>
  </form>
</section>
