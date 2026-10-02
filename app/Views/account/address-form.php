<?php
use App\Core\Csrf;
use App\Models\Address;

/** Current value: what was just typed (after an error), else the saved one, else $default. */
$v = static function (string $k, string $default = '') use ($old, $address): string {
    if ($old !== null) {
        return (string) ($old[$k] ?? '');
    }
    if ($address !== null) {
        return $k === 'phone' ? '0' . substr((string) $address['phone'], 3) : (string) ($address[$k] ?? '');
    }
    return $default;
};
$isDefault = $old !== null ? isset($old['is_default']) : (bool) ($address['is_default'] ?? false);
?>
<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <a class="pg-back" href="<?= url('/account/addresses') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Addresses</a>
    <div class="account-head">
      <h1><?= $address ? 'Edit address' : 'Add an address' ?></h1>
      <p>Write it the way you'd direct a delivery rider.</p>
    </div>

    <section class="panel">
      <div class="panel-body">
        <form method="post" action="<?= url($address ? '/account/addresses/' . (int) $address['id'] . '/edit' : '/account/addresses/new') ?>">
          <?= Csrf::field() ?>
          <div class="form-grid">
            <div class="field">
              <label for="label">Name this address <span class="muted">(optional)</span></label>
              <input id="label" name="label" type="text" maxlength="40" placeholder="Home, Work, Mum's house…" value="<?= e($v('label')) ?>">
            </div>
            <div class="field">
              <label for="region">Region</label>
              <select id="region" name="region" required>
                <?php $region = $v('region', 'Greater Accra'); ?>
                <?php foreach (Address::REGIONS as $r): ?>
                  <option value="<?= e($r) ?>" <?= $region === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="recipient">Who receives it</label>
              <input id="recipient" name="recipient" type="text" maxlength="120" required value="<?= e($v('recipient', (string) $user['name'])) ?>" autocomplete="name">
            </div>
            <div class="field">
              <label for="phone">Their phone</label>
              <input id="phone" name="phone" type="tel" required placeholder="024 XXX XXXX" value="<?= e($v('phone', '0' . substr((string) $user['phone'], 3))) ?>" autocomplete="tel">
            </div>
            <div class="field">
              <label for="town">Town or city</label>
              <input id="town" name="town" type="text" maxlength="80" required placeholder="e.g. Madina" value="<?= e($v('town')) ?>">
            </div>
            <div class="field">
              <label for="gps">GhanaPost GPS <span class="muted">(optional)</span></label>
              <input id="gps" name="gps" type="text" maxlength="20" placeholder="GA-123-4567" value="<?= e($v('gps')) ?>" autocapitalize="characters">
            </div>
            <div class="field span-2">
              <label for="area">Area, street and house</label>
              <input id="area" name="area" type="text" maxlength="160" required placeholder="e.g. Zongo Junction, 4th lane, blue gate" value="<?= e($v('area')) ?>" autocomplete="street-address">
            </div>
            <div class="field span-2">
              <label for="landmark">Closest landmark <span class="muted">(optional)</span></label>
              <input id="landmark" name="landmark" type="text" maxlength="160" placeholder="e.g. Opposite the Goil filling station" value="<?= e($v('landmark')) ?>">
            </div>
          </div>
          <div class="field">
            <label class="check-line"><input type="checkbox" name="is_default" <?= $isDefault ? 'checked' : '' ?>> Make this my default address</label>
          </div>
          <div class="form-foot">
            <a class="btn btn-ghost" href="<?= url('/account/addresses') ?>">Cancel</a>
            <button class="btn btn-primary" type="submit"><?= $address ? 'Save changes' : 'Save address' ?></button>
          </div>
        </form>
      </div>
    </section>
  </div>
</div>
