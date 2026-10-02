<?php
use App\Core\Csrf;

ob_start(); ?>
<form method="post" action="<?= url('/verify-phone') ?>">
  <?= Csrf::field() ?>
  <div class="field">
    <label for="code">6-digit code</label>
    <input id="code" name="code" class="code-input" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code" autofocus>
  </div>
  <button class="btn btn-primary btn-block btn-lg" type="submit">Confirm my number</button>
</form>
<form method="post" action="<?= url('/verify-phone/resend') ?>" class="mt-2">
  <?= Csrf::field() ?>
  <p class="form-alt">No SMS after a minute? <button class="link-btn" type="submit">Send a new code</button></p>
</form>
<p class="form-alt">Wrong number? <a href="<?= url('/account/phone') ?>">Change it</a> · <a href="<?= url('/shop') ?>">Do this later</a> (you'll need it to start a plan)</p>
<?php
echo (new App\Core\View())->partial('partials/auth-simple', [
    'eyebrow' => 'One quick step',
    'heading' => 'Confirm your number',
    'sub' => 'We texted a 6-digit code to ' . pretty_phone($phone) . '. Payment receipts and MoMo prompts go to this number, so we make sure it\'s really yours.',
    'demoCode' => $demoCode ?? null,
    'body' => (string) ob_get_clean(),
]);
