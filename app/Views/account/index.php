<?php
use App\Core\Csrf;
use App\Models\User;

$verified = User::isVerified($user);
?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <div class="account-head">
      <h1>Profile</h1>
      <p>Member since <?= e(when((string) $user['created_at'], false)) ?>.</p>
    </div>

    <div class="account-stats">
      <a href="<?= url('/plans') ?>"><b><?= (int) $stats['active'] ?></b><span>active plan<?= $stats['active'] === 1 ? '' : 's' ?></span></a>
      <a href="<?= url('/plans?status=completed') ?>"><b><?= (int) $stats['completed'] ?></b><span>finished</span></a>
      <a href="<?= url('/account/history') ?>"><b><?= ghs((int) $stats['paid']) ?></b><span>paid in so far</span></a>
      <a href="<?= url('/wishlist') ?>"><b><?= (int) $counts['saved'] ?></b><span>saved item<?= $counts['saved'] === 1 ? '' : 's' ?></span></a>
    </div>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('badge', ['size' => 20]) ?> Your details</h2></div>
      <div class="panel-body">
        <form method="post" action="<?= url('/account/profile') ?>" class="inline-field">
          <?= Csrf::field() ?>
          <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="120" required value="<?= e((string) $user['name']) ?>" autocomplete="name">
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="190" required value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="you@example.com" autocomplete="email" inputmode="email">
            <?php if (empty($user['email'])): ?><p class="field-hint">Add one so we can send you payment receipts.</p><?php endif; ?>
          </div>
          <button class="btn btn-primary" type="submit">Save</button>
        </form>

        <div class="detail-row">
          <div>
            <span class="detail-label">Phone (your login)</span>
            <b><?= e(pretty_phone((string) $user['phone'])) ?></b>
            <?php if ($verified): ?>
              <span class="tag tag-approved"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Confirmed</span>
            <?php else: ?>
              <span class="tag tag-pending"><?= micon('error', ['size' => 14]) ?> Not confirmed</span>
            <?php endif; ?>
          </div>
          <div class="detail-actions">
            <?php if (!$verified): ?>
              <form class="inline-form" method="post" action="<?= url('/verify-phone/resend') ?>">
                <?= Csrf::field() ?><button class="btn btn-sm btn-primary" type="submit">Confirm it now</button>
              </form>
            <?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?= url('/account/phone') ?>">Change number</a>
          </div>
        </div>
      </div>
    </section>

    <div class="account-links">
      <a href="<?= url('/account/addresses') ?>"><?= micon('home_pin', ['size' => 24]) ?><span><b>Addresses</b><small><?= $counts['addresses'] ? $counts['addresses'] . ' saved' : 'Add where items should go' ?></small></span></a>
      <a href="<?= url('/account/payment-methods') ?>"><?= micon('account_balance_wallet', ['size' => 24]) ?><span><b>Payment methods</b><small><?= $counts['cards'] ? $counts['cards'] . ' card' . ($counts['cards'] === 1 ? '' : 's') . ' + MoMo' : 'MoMo wallet and cards' ?></small></span></a>
      <a href="<?= url('/account/security') ?>"><?= micon('lock', ['size' => 24]) ?><span><b>PIN &amp; security</b><small>Change your PIN</small></span></a>
      <a href="<?= url('/account/history') ?>"><?= micon('history', ['size' => 24]) ?><span><b>Payment history</b><small>Every receipt and refund</small></span></a>
    </div>
  </div>
</div>
