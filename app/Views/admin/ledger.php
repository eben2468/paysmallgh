<?php
use App\Core\Csrf;

$tabs = [
    'all' => 'All',
    'collection' => 'Collections',
    'disbursement' => 'Payouts',
    'refund' => 'Refunds',
    'pending' => 'Pending',
    'failed' => 'Failed',
];
$typeLabel = ['collection' => 'Collection', 'disbursement' => 'Payout', 'refund' => 'Refund'];
?>
<div class="pg-head">
  <div>
    <h1>Transactions</h1>
    <p>Every pesewa in and out, newest first. This ledger is append-only &mdash; rows are never edited away.</p>
  </div>
  <?php if ($pending > 0): ?>
    <div class="pg-actions">
      <form class="inline-form" method="post" action="<?= url('/admin/reconcile') ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-primary" type="submit"><?= micon('sync', ['size' => 18]) ?> Check <?= (int) $pending ?> pending with Paystack</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<nav class="tabs" aria-label="Filter transactions">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $filter === $key ? 'active' : '' ?>" href="<?= url('/admin/ledger' . ($key === 'all' ? '' : '?type=' . $key)) ?>">
      <?= e($label) ?> <span class="tab-count"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<section class="panel">
  <?php if (empty($transactions)): ?>
    <div class="panel-empty"><?= micon('receipt') ?>No transactions here.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>When</th><th>Type</th><th class="right">Amount</th><th>Phone</th><th>Plan</th><th>References</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($transactions as $t): ?>
            <tr>
              <td class="muted"><?= (int) $t['id'] ?></td>
              <td class="small nowrap"><?= e(when($t['created_at'])) ?></td>
              <td><?= e($typeLabel[$t['type']] ?? $t['type']) ?></td>
              <td class="right nowrap"><strong><?= e(ghs((int) $t['amount_pesewas'])) ?></strong></td>
              <td class="small mono nowrap"><?= e(pretty_phone($t['phone'])) ?></td>
              <td><?= $t['plan_id'] ? '<a href="' . url('/admin/plan/' . (int) $t['plan_id']) . '">#' . (int) $t['plan_id'] . '</a>' : '—' ?></td>
              <td class="small mono"><?= e($t['provider_ref']) ?><?= $t['external_ref'] !== '' ? '<span class="cell-sub">' . e($t['external_ref']) . '</span>' : '' ?></td>
              <td><?= status_tag($t['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
