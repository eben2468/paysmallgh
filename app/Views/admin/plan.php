<?php
  $paid = (int) $plan['installments_paid'];
  $total = (int) $plan['installments_total'];
  $pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
  $left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
  $unitNoun = ['daily' => 'days', 'weekly' => 'weeks', 'monthly' => 'months'][$plan['frequency'] ?? 'weekly'] ?? 'payments';
?>
<section class="page-head wrap">
  <h1>Plan #<?= (int) $plan['id'] ?></h1>
  <p>
    <span class="tag tag-<?= e($plan['status']) ?>"><?= e($plan['status']) ?></span>
    <?php if (($plan['grace_state'] ?? 'ok') !== 'ok'): ?><span class="tag tag-<?= e($plan['grace_state']) ?>"><?= e($plan['grace_state']) ?></span><?php endif; ?>
    <?php if (($plan['released_at'] ?? null) !== null): ?><span class="tag tag-completed">item released</span><?php endif; ?>
  </p>
</section>

<section class="wrap detail-grid" style="padding-bottom:3rem">
  <div>
    <div class="admin-nav">
      <a class="btn btn-sm" href="<?= url('/admin/plans') ?>">&larr; All plans</a>
    </div>

    <div class="table-scroll" style="padding:1.3rem 1.3rem">
      <h2 style="font-size:1.2rem;margin-bottom:.8rem"><?= e($plan['product_name']) ?></h2>
      <dl class="kv">
        <div><dt>Customer</dt><dd><?= e($plan['customer_name']) ?> &middot; <span class="mono"><?= e(pretty_phone($plan['customer_phone'])) ?></span></dd></div>
        <div><dt>Shop</dt><dd><?= e($plan['shop_name']) ?> &middot; <span class="mono"><?= e(pretty_phone($plan['merchant_phone'])) ?></span></dd></div>
        <div><dt>Plan</dt><dd><?= ghs((int) $plan['installment_pesewas']) ?> &times; <?= $total ?> <?= $unitNoun ?> = <?= ghs((int) $plan['installment_pesewas'] * $total) ?></dd></div>
        <div><dt>Cash price</dt><dd><?= ghs((int) $plan['total_pesewas']) ?></dd></div>
        <div><dt>Payout to</dt><dd><?= e($plan['payout_channel']) ?> &middot; <span class="mono"><?= e($plan['payout_number'] ?: $plan['merchant_phone']) ?></span></dd></div>
        <div><dt>Started</dt><dd><?= e(date('j M Y, g:ia', strtotime((string) $plan['created_at']))) ?></dd></div>
        <?php if (($plan['completed_at'] ?? null) !== null): ?>
          <div><dt>Completed</dt><dd><?= e(date('j M Y, g:ia', strtotime((string) $plan['completed_at']))) ?></dd></div>
        <?php endif; ?>
        <?php if (($plan['released_at'] ?? null) !== null): ?>
          <div><dt>Item released</dt><dd><?= e(date('j M Y, g:ia', strtotime((string) $plan['released_at']))) ?></dd></div>
        <?php endif; ?>
      </dl>

      <div class="progress-legend mt-3">
        <span><b><?= $paid ?> of <?= $total ?></b> paid</span>
        <span><?= $left === 0 ? 'Fully paid' : ghs($left) . ' left' ?></span>
      </div>
      <?= progress_bar($pct, $plan['status'] === 'completed' ? 'success' : (($plan['grace_state'] ?? 'ok') !== 'ok' ? 'warn' : 'primary')) ?>
    </div>

    <h2 class="mt-3 mb-2" style="font-size:1.15rem">Ledger</h2>
    <div class="table-scroll">
      <?php if (empty($transactions)): ?>
        <p class="muted" style="padding:1.2rem">No transactions on this plan yet.</p>
      <?php else: ?>
        <table class="data">
          <thead><tr><th>#</th><th>Type</th><th>Amount</th><th>Status</th><th>Reference</th><th>When</th></tr></thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><?= (int) $t['id'] ?></td>
                <td><?= e($t['type']) ?></td>
                <td class="nowrap"><?= ghs((int) $t['amount_pesewas']) ?></td>
                <td><span class="tag tag-<?= $t['status'] === 'success' ? 'completed' : ($t['status'] === 'failed' ? 'flagged' : 'grace') ?>"><?= e($t['status']) ?></span></td>
                <td class="mono small"><?= e($t['provider_ref']) ?><?= $t['external_ref'] !== '' ? '<br>' . e($t['external_ref']) : '' ?></td>
                <td class="small muted nowrap"><?= e(date('j M, g:ia', strtotime((string) $t['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div>
    <h2 class="mb-2" style="font-size:1.35rem">Installment timeline</h2>
    <ul class="schedule">
      <?php foreach ($installments as $inst): ?>
        <?php
          $isPaid = $inst['paid_at'] !== null;
          $isDue = !$isPaid && days_until($inst['due_date']) <= 0;
        ?>
        <li class="<?= $isPaid ? 'paid' : ($isDue ? 'due' : '') ?>">
          <span>#<?= (int) $inst['number'] ?> &middot; due <?= date('j M', strtotime($inst['due_date'])) ?></span>
          <span class="sch-amount"><?= ghs((int) $inst['amount_pesewas']) ?></span>
          <span class="sch-status"><?= $isPaid ? 'paid ' . date('j M', strtotime($inst['paid_at'])) : ($isDue ? 'due now' : 'coming up') ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
