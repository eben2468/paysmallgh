<?php
use App\Core\Csrf;

$isMerchant = ($role ?? 'customer') === 'merchant';
ob_start(); ?>
<form method="post" action="<?= url($isMerchant ? '/merchant/reset-password' : '/reset-pin') ?>">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="code">6-digit code</label>
    <input id="code" name="code" class="code-input" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code" autofocus>
  </div>
  <?php if ($isMerchant): ?>
    <div class="field">
      <label for="password">New password</label>
      <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">
      <p class="field-hint">At least 8 characters.</p>
    </div>
    <div class="field">
      <label for="password_confirm">Type it again</label>
      <input id="password_confirm" name="password_confirm" type="password" minlength="8" required autocomplete="new-password">
    </div>
  <?php else: ?>
    <div class="form-grid">
      <div class="field">
        <label for="pin">New PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="\d{4,6}" minlength="4" maxlength="6" required autocomplete="new-password">
      </div>
      <div class="field">
        <label for="pin_confirm">Type it again</label>
        <input id="pin_confirm" name="pin_confirm" type="password" inputmode="numeric" pattern="\d{4,6}" minlength="4" maxlength="6" required autocomplete="new-password">
      </div>
    </div>
    <p class="field-hint mb-2">4 to 6 digits. Avoid 1234 or your birthday.</p>
  <?php endif; ?>
  <button class="btn btn-primary btn-block btn-lg" type="submit">Set new <?= $isMerchant ? 'password' : 'PIN' ?></button>
</form>
<p class="form-alt">No code? <a href="<?= url($isMerchant ? '/merchant/forgot-password' : '/forgot-pin') ?>">Ask for another</a> (one a minute).</p>
<?php
echo (new App\Core\View())->partial('partials/auth-simple', [
    'eyebrow' => 'Check your SMS',
    'heading' => 'Set a new ' . ($isMerchant ? 'password' : 'PIN'),
    'sub' => 'Type the code we sent to ' . pretty_phone($phone) . '. It works for 10 minutes.',
    'demoCode' => $demoCode ?? null,
    'body' => (string) ob_get_clean(),
]);
