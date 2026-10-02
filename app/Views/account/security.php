<?php use App\Core\Csrf; ?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <div class="account-head">
      <h1>PIN &amp; security</h1>
      <p>Your PIN guards your plans and payment methods. Never share it — PaySmallSmall will never ask for it by phone or SMS.</p>
    </div>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('password', ['size' => 20]) ?> Change your PIN</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= url('/account/pin') ?>" class="narrow-form">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="current_pin">Current PIN</label>
            <input id="current_pin" name="current_pin" type="password" inputmode="numeric" maxlength="6" required autocomplete="current-password">
          </div>
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
          <p class="field-hint mb-2">4 to 6 digits. Avoid 1234, 0000 or your birthday.</p>
          <button class="btn btn-primary" type="submit">Change PIN</button>
        </form>
        <p class="small muted mt-2">Forgot the current one? <a href="<?= url('/forgot-pin') ?>">Reset it by SMS</a>.</p>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('shield_lock', ['size' => 20]) ?> How we keep your account safe</h2></div>
      <div class="panel-body">
        <ul class="info-list">
          <li><?= micon('block', ['size' => 20]) ?><span>Five wrong PINs in a row locks logins for your number for 15 minutes.</span></li>
          <li><?= micon('sms', ['size' => 20]) ?><span>PIN resets and number changes need a code sent by SMS to your phone.</span></li>
          <li><?= micon('credit_card', ['size' => 20]) ?><span>We never see or store card numbers — saved cards are held by Paystack, and you can remove them any time.</span></li>
        </ul>
      </div>
    </section>
  </div>
</div>
