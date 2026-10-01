<?php
/**
 * Payout account fields, shared by merchant register + settings.
 * Vars: $m (current values: payout_channel, payout_number, payout_bank_code), $banks ([code => name]).
 */
use App\Services\PaystackService;

$channel = ($m['payout_channel'] ?? 'momo') === 'bank' ? 'bank' : 'momo';
$code = (string) ($m['payout_bank_code'] ?? '');
$number = (string) ($m['payout_number'] ?? '');
?>
<div class="field">
  <label for="payout_channel">How should we pay you?</label>
  <select id="payout_channel" name="payout_channel" data-payout-channel>
    <option value="momo" <?= $channel === 'momo' ? 'selected' : '' ?>>Mobile Money</option>
    <option value="bank" <?= $channel === 'bank' ? 'selected' : '' ?>>Bank account</option>
  </select>
</div>
<div class="field" data-payout-for="momo" <?= $channel === 'momo' ? '' : 'hidden' ?>>
  <label for="payout_network">MoMo network</label>
  <select id="payout_network" name="payout_network">
    <?php foreach (PaystackService::MOMO_NETWORKS as $k => $label): ?>
      <option value="<?= e($k) ?>" <?= $channel === 'momo' && $code === $k ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="field" data-payout-for="bank" <?= $channel === 'bank' ? '' : 'hidden' ?>>
  <label for="payout_bank">Bank</label>
  <?php if (!empty($banks)): ?>
    <select id="payout_bank" name="payout_bank">
      <option value="">Choose your bank</option>
      <?php foreach ($banks as $k => $label): ?>
        <option value="<?= e((string) $k) ?>" <?= $channel === 'bank' && $code === (string) $k ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  <?php else: ?>
    <input id="payout_bank" name="payout_bank" type="text" maxlength="20" value="<?= $channel === 'bank' ? e($code) : '' ?>" placeholder="Bank code">
    <p class="small muted mt-1">Not sure of the code? Pick Mobile Money for now and switch later.</p>
  <?php endif; ?>
</div>
<div class="field">
  <label for="payout_number">Payout number (MoMo number or account no.)</label>
  <input id="payout_number" name="payout_number" type="text" inputmode="numeric" value="<?= e($number) ?>" placeholder="Leave empty to use your business phone (MoMo only)">
</div>
<script>
  (function () {
    var sel = document.querySelector('[data-payout-channel]');
    if (!sel) return;
    function sync() {
      document.querySelectorAll('[data-payout-for]').forEach(function (el) {
        el.hidden = el.getAttribute('data-payout-for') !== sel.value;
      });
    }
    sel.addEventListener('change', sync);
    sync();
  })();
</script>
