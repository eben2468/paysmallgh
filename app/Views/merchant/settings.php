<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>Shop settings</h1>
  <p>Update your shop details and where we send your payouts. Changes take effect right away.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/merchant/dashboard') ?>">&larr; Dashboard</a>
  </div>

  <div class="table-scroll" style="padding:1.4rem 1.3rem;max-width:560px">
    <form method="post" action="<?= url('/merchant/settings') ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="shop_name">Shop name</label>
        <input id="shop_name" name="shop_name" type="text" required maxlength="160" value="<?= e($merchant['shop_name']) ?>">
      </div>
      <div class="field">
        <label for="owner_name">Your name (owner)</label>
        <input id="owner_name" name="owner_name" type="text" required maxlength="120" value="<?= e($merchant['owner_name']) ?>">
      </div>
      <div class="field">
        <label>Business phone (your login)</label>
        <input type="tel" value="<?= e(pretty_phone($merchant['phone'])) ?>" disabled>
        <p class="small muted mt-1">This is how you log in — call us if you need it changed.</p>
      </div>
      <div class="field">
        <label for="location">Where's the shop?</label>
        <input id="location" name="location" type="text" maxlength="160" value="<?= e($merchant['location']) ?>" placeholder="e.g. Circle, near the overhead">
      </div>
      <div class="field">
        <label for="payout_channel">How should we pay you?</label>
        <select id="payout_channel" name="payout_channel">
          <option value="momo" <?= $merchant['payout_channel'] === 'momo' ? 'selected' : '' ?>>Mobile Money</option>
          <option value="bank" <?= $merchant['payout_channel'] === 'bank' ? 'selected' : '' ?>>Bank account</option>
        </select>
      </div>
      <div class="field">
        <label for="payout_number">Payout number (MoMo or account no.)</label>
        <input id="payout_number" name="payout_number" type="text" value="<?= e($merchant['payout_number']) ?>" placeholder="Leave empty to use business phone">
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit">Save changes</button>
    </form>
  </div>
</section>
