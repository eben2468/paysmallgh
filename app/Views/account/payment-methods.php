<?php
use App\Core\Csrf;
use App\Models\SavedCard;

$usingAccountPhone = (string) ($user['momo_number'] ?? '') === '';
?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <div class="account-head">
      <h1>Payment methods</h1>
      <p>Pay your installments in one tap. Every payment still goes into escrow — the shop is only paid when you finish.</p>
    </div>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('smartphone', ['size' => 20]) ?> MoMo wallet</h2></div>
      <div class="panel-body">
        <p class="small muted mb-2">"Send a MoMo prompt" on your plan pops an approval request on this number. You approve it with your MoMo PIN — we never see that PIN.</p>
        <form method="post" action="<?= url('/account/momo') ?>">
          <?= Csrf::field() ?>
          <div class="form-grid">
            <div class="field">
              <label for="momo_number">MoMo number</label>
              <input id="momo_number" name="momo_number" type="tel" placeholder="<?= e('0' . substr((string) $user['phone'], 3)) ?>"
                     value="<?= $usingAccountPhone ? '' : e('0' . substr((string) $walletPhone, 3)) ?>" autocomplete="tel">
              <p class="field-hint">Leave empty to use your account number (<?= e(pretty_phone((string) $user['phone'])) ?>).</p>
            </div>
            <div class="field">
              <label for="momo_network">Network</label>
              <select id="momo_network" name="momo_network">
                <option value="">Work it out from the number</option>
                <?php foreach ($networks as $code => $label): ?>
                  <option value="<?= e($code) ?>" <?= !$usingAccountPhone && $walletNet === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="field-hint">Moved your number to another network? Pick it here.</p>
            </div>
          </div>
          <p class="wallet-now"><?= micon('check_circle', ['size' => 18, 'fill' => true]) ?> Prompts go to <b><?= e(pretty_phone((string) $walletPhone)) ?></b> · <?= e($networks[$walletNet] ?? 'network unknown — pick one') ?></p>
          <button class="btn btn-primary" type="submit">Save wallet</button>
        </form>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('credit_card', ['size' => 20]) ?> Saved cards</h2></div>
      <div class="panel-body">
        <?php if (!$cards): ?>
          <p class="small muted">No cards yet. Pay any installment by card on the Paystack page and we'll remember it for next time<?= empty($user['save_cards']) ? ' — once you switch that on below' : '' ?>.</p>
        <?php else: ?>
          <ul class="card-list">
            <?php foreach ($cards as $c): ?>
              <?php $expired = SavedCard::isExpired($c); ?>
              <li class="<?= $expired ? 'is-expired' : '' ?>">
                <span class="card-chip"><?= micon('credit_card', ['size' => 22]) ?></span>
                <span class="card-text"><b><?= e(SavedCard::label($c)) ?></b><small><?= $expired ? 'Expired — remove it' : e(($c['bank'] !== '' ? $c['bank'] . ' · ' : '') . 'added ' . when((string) $c['created_at'], false)) ?></small></span>
                <form class="inline-form" method="post" action="<?= url('/account/cards/' . (int) $c['id'] . '/delete') ?>" data-confirm="Remove this card? We won't be able to charge it again.">
                  <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit"><?= micon('delete', ['size' => 16]) ?> Remove</button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <form method="post" action="<?= url('/account/cards/settings') ?>" class="mt-2 card-settings">
          <?= Csrf::field() ?>
          <label class="check-line"><input type="checkbox" name="save_cards" <?= !empty($user['save_cards']) ? 'checked' : '' ?>> Remember cards I pay with, for one-tap payments</label>
          <?php if ($cards): ?>
            <label class="check-line small"><input type="checkbox" name="forget_all"> Also remove my saved cards (only when switching this off)</label>
          <?php endif; ?>
          <button class="btn btn-sm btn-ghost" type="submit">Update</button>
        </form>
        <p class="small muted mt-2"><?= micon('lock', ['size' => 15]) ?> Card numbers never touch PaySmallSmall. Paystack keeps the card; we only hold a token that lets us charge your installments — and only when you tap pay.</p>
      </div>
    </section>
  </div>
</div>
