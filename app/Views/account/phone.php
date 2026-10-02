<?php use App\Core\Csrf; ?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <a class="pg-back" href="<?= url('/account') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Profile</a>
    <div class="account-head">
      <h1>Change your phone number</h1>
      <p>Your number is how you log in, and where receipts, reminders and MoMo prompts go. Now: <b><?= e(pretty_phone((string) $user['phone'])) ?></b>.</p>
    </div>

    <section class="panel">
      <div class="panel-body">
        <?php if ($newPhone === ''): ?>
          <ol class="steps-mini"><li class="on">New number</li><li>Code</li><li>Done</li></ol>
          <form method="post" action="<?= url('/account/phone') ?>" class="narrow-form">
            <?= Csrf::field() ?>
            <div class="field">
              <label for="phone">New phone number</label>
              <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" autocomplete="tel">
            </div>
            <div class="field">
              <label for="pin">Your PIN</label>
              <input id="pin" name="pin" type="password" inputmode="numeric" maxlength="6" required autocomplete="current-password">
              <p class="field-hint">So nobody holding your phone can move your account.</p>
            </div>
            <button class="btn btn-primary" type="submit">Text a code to the new number</button>
          </form>
        <?php else: ?>
          <ol class="steps-mini"><li class="done">New number</li><li class="on">Code</li><li>Done</li></ol>
          <?php if (!empty($demoCode)): ?>
            <p class="demo-code"><?= micon('science', ['size' => 18]) ?> Demo mode — no real SMS is sent. Your code is <b class="mono"><?= e($demoCode) ?></b></p>
          <?php endif; ?>
          <form method="post" action="<?= url('/account/phone/confirm') ?>" class="narrow-form">
            <?= Csrf::field() ?>
            <div class="field">
              <label for="code">Code sent to <?= e(pretty_phone($newPhone)) ?></label>
              <input id="code" name="code" class="code-input" type="text" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code" autofocus>
            </div>
            <button class="btn btn-primary" type="submit">Switch to this number</button>
          </form>
          <form method="post" action="<?= url('/account/phone/cancel') ?>" class="mt-2">
            <?= Csrf::field() ?>
            <button class="link-btn" type="submit">Use a different number</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
