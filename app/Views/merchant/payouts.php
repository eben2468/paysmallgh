<?php
$received = 0;
$waiting = 0;
foreach ($payouts as $t) {
    if ($t['status'] === 'success') {
        $received += (int) $t['amount_pesewas'];
    } elseif ($t['status'] === 'pending') {
        $waiting += (int) $t['amount_pesewas'];
    }
}
$networks = \App\Services\PaystackService::MOMO_NETWORKS;
$dest = $merchant['payout_channel'] === 'bank'
    ? 'Bank account ' . $merchant['payout_number']
    : ($networks[$merchant['payout_bank_code'] ?? ''] ?? 'MoMo') . ' ' . pretty_phone($merchant['payout_number']);
?>
<div class="pg-head">
  <div>
    <h1>Payouts</h1>
    <p>Every pesewa we've sent you, newest first. When a customer finishes paying, we send the money (minus our fee) straight to your account.</p>
  </div>
  <div class="pg-actions">
    <a class="btn btn-sm btn-ghost" href="<?= url('/merchant/settings') ?>"><?= micon('edit', ['size' => 18]) ?> Change payout account</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">Received</span><span class="kpi-ic"><?= micon('payments', ['size' => 20]) ?></span></div>
    <div class="kpi-value"><?= e(ghs($received)) ?></div>
    <span class="kpi-sub">Paid out to you so far</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">On the way</span><span class="kpi-ic"><?= micon('schedule') ?></span></div>
    <div class="kpi-value"><?= e(ghs($waiting)) ?></div>
    <span class="kpi-sub">Sent, waiting for confirmation</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Paid to</span><span class="kpi-ic"><?= micon('account_balance_wallet') ?></span></div>
    <div class="kpi-value" style="font-size:1.1rem"><?= e($dest) ?></div>
    <span class="kpi-sub"><?= $merchant['payout_channel'] === 'bank' ? 'Bank transfer' : 'Mobile Money' ?></span>
  </div>
</div>

<section class="panel">
  <div class="panel-head"><h2><?= micon('history', ['size' => 20]) ?> Payout history</h2></div>
  <?php if (empty($payouts)): ?>
    <div class="panel-empty"><?= micon('account_balance_wallet') ?>No payouts yet. The first one lands when a customer completes their plan.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Date</th><th>For</th><th class="right">Amount</th><th>Reference</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($payouts as $t): ?>
            <tr>
              <td class="nowrap"><?= e(when($t['created_at'])) ?></td>
              <td><?= e($t['product_name'] ?? '—') ?><?= $t['plan_ref'] ? '<span class="cell-sub">Plan #' . (int) $t['plan_ref'] . '</span>' : '' ?></td>
              <td class="right nowrap"><strong><?= e(ghs((int) $t['amount_pesewas'])) ?></strong></td>
              <td class="small muted mono"><?= e($t['provider_ref']) ?></td>
              <td><?= status_tag($t['status'], ['success' => 'Paid', 'pending' => 'On the way', 'failed' => 'Failed — we\'re on it'][$t['status']] ?? null) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
