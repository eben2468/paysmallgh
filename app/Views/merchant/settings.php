<?php use App\Core\Csrf; ?>
<div class="pg-head">
  <div>
    <h1>Shop settings</h1>
    <p>Your shop details and where we send your payouts. Changes take effect right away.</p>
  </div>
</div>

<form method="post" action="<?= url('/merchant/settings') ?>">
  <?= Csrf::field() ?>
  <div class="grid-2-even">
    <section class="panel">
      <div class="panel-head"><h2><?= micon('storefront', ['size' => 20]) ?> Shop</h2></div>
      <div class="panel-body">
        <div class="field">
          <label for="shop_name">Shop name</label>
          <input id="shop_name" name="shop_name" type="text" required maxlength="160" value="<?= e($merchant['shop_name']) ?>">
        </div>
        <div class="field">
          <label for="owner_name">Your name (owner)</label>
          <input id="owner_name" name="owner_name" type="text" required maxlength="120" value="<?= e($merchant['owner_name']) ?>">
        </div>
        <div class="field">
          <label for="location">Where's the shop?</label>
          <input id="location" name="location" type="text" maxlength="160" value="<?= e($merchant['location']) ?>" placeholder="e.g. Circle, near the overhead">
        </div>
        <div class="field mb-1">
          <label>Business phone (your login)</label>
          <input type="tel" value="<?= e(pretty_phone($merchant['phone'])) ?>" disabled>
          <p class="field-hint">This is how you log in &mdash; call us if you need it changed.</p>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('account_balance_wallet', ['size' => 20]) ?> Payout account</h2></div>
      <div class="panel-body">
        <p class="small muted mb-2">When a customer finishes paying, the money (minus our fee) goes here.</p>
        <?= (new App\Core\View())->partial('partials/payout-fields', ['m' => $merchant, 'banks' => $banks]) ?>
      </div>
    </section>
  </div>

  <div class="form-foot mt-3">
    <a class="btn btn-ghost" href="<?= url('/merchant/dashboard') ?>">Cancel</a>
    <button class="btn btn-primary" type="submit"><?= micon('save', ['size' => 18]) ?> Save changes</button>
  </div>
</form>
