<?php
use App\Core\Csrf;

$isMerchant = ($role ?? 'customer') === 'merchant';
ob_start(); ?>
<form method="post" action="<?= url($isMerchant ? '/merchant/forgot-password' : '/forgot-pin') ?>">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="phone"><?= $isMerchant ? 'Your shop\'s login number' : 'Your phone number' ?></label>
    <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" autocomplete="tel" autofocus>
  </div>
  <button class="btn btn-primary btn-block btn-lg" type="submit">Text me a code</button>
</form>
<p class="form-alt">Remembered it? <a href="<?= url($isMerchant ? '/merchant/login' : '/login') ?>">Back to log in</a></p>
<?php
echo (new App\Core\View())->partial('partials/auth-simple', [
    'eyebrow' => $isMerchant ? 'For shop owners' : 'Happens to everybody',
    'heading' => $isMerchant ? 'Forgot your password?' : 'Forgot your PIN?',
    'sub' => 'Type the number you log in with. We\'ll text it a 6-digit code so you can set a new ' . ($isMerchant ? 'password' : 'PIN') . '.',
    'demoCode' => null,
    'body' => (string) ob_get_clean(),
]);
