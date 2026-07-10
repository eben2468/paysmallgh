<?php use App\Core\Csrf; ?>
<section class="auth wrap">
  <div class="auth-card">
    <aside class="auth-aside is-admin">
      <span class="auth-logo">Pay<span class="logo-small">Small</span><span class="logo-small2">Small</span></span>
      <span class="auth-eyebrow">Staff only</span>
      <h2>Control room.</h2>
      <ul class="auth-points">
        <li class="auth-point">
          <span class="ic"><?= micon('verified', ['size' => 18]) ?></span>
          <div><b>Approve merchants</b><span>Review and clear new shops before they go live.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('receipt_long', ['size' => 18]) ?></span>
          <div><b>Plans &amp; ledger</b><span>Every plan, transaction and SMS in one place.</span></div>
        </li>
        <li class="auth-point">
          <span class="ic"><?= micon('sync', ['size' => 18]) ?></span>
          <div><b>Reconcile &amp; remind</b><span>Run payment reconciliation and reminder sweeps.</span></div>
        </li>
      </ul>
      <p class="auth-foot"><?= micon('lock', ['size' => 16, 'fill' => true]) ?> Authorised staff access only.</p>
    </aside>

    <div class="auth-form">
      <h1>Admin sign in</h1>
      <p class="sub">Staff only. Log in to manage the platform.</p>
      <form method="post" action="<?= url('/admin/login') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="phone">Phone</label>
          <input id="phone" name="phone" type="tel" required autocomplete="username">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" required autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-block btn-lg" type="submit">Log in</button>
      </form>
    </div>
  </div>
</section>
