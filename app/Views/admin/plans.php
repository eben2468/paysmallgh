<?php
use App\Core\Csrf;

$tabs = [
    'all' => 'All',
    'active' => 'Active',
    'attention' => 'Behind on payment',
    'pending' => 'Awaiting first payment',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];
?>
<div class="pg-head">
  <div>
    <h1>Plans</h1>
    <p>Every layaway plan, newest first.<?php if ($pending > 0): ?> <strong><?= (int) $pending ?></strong> payment<?= $pending === 1 ? '' : 's' ?> waiting for confirmation.<?php endif; ?></p>
  </div>
  <div class="pg-actions">
    <form class="inline-form" method="post" action="<?= url('/admin/run-reminders') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-sm btn-ghost" type="submit"><?= micon('notifications', ['size' => 18]) ?> Send reminders</button>
    </form>
    <form class="inline-form" method="post" action="<?= url('/admin/reconcile') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-sm btn-ghost" type="submit"><?= micon('sync', ['size' => 18]) ?> Reconcile payments<?= $pending > 0 ? ' (' . (int) $pending . ')' : '' ?></button>
    </form>
  </div>
</div>

<nav class="tabs" aria-label="Filter plans">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $filter === $key ? 'active' : '' ?>" href="<?= url('/admin/plans' . ($key === 'all' ? '' : '?status=' . $key)) ?>">
      <?= e($label) ?> <span class="tab-count"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<section class="panel">
  <?php if (empty($plans)): ?>
    <div class="panel-empty"><?= micon('receipt_long') ?>No plans here.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Plan</th><th>Customer</th><th>Shop</th><th>Progress</th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <?php
              $tot = (int) $p['installments_total'];
              $pc = $tot > 0 ? (int) round($p['installments_paid'] / $tot * 100) : 0;
              $grace = $p['grace_state'] !== 'ok' && $p['status'] === 'active';
            ?>
            <tr>
              <td><a class="cell-main" href="<?= url('/admin/plan/' . $p['id']) ?>">#<?= (int) $p['id'] ?></a><span class="cell-sub"><?= e($p['product_name']) ?></span></td>
              <td><?= e($p['customer_name']) ?></td>
              <td><?= e($p['shop_name']) ?></td>
              <td class="cell-mini-bar">
                <span class="small"><?= (int) $p['installments_paid'] ?> of <?= $tot ?> &middot; <?= e(plan_rate($p)) ?></span>
                <?= progress_bar($pc, $p['status'] === 'completed' ? 'success' : ($grace ? 'warn' : 'primary')) ?>
              </td>
              <td>
                <?= status_tag($p['status']) ?>
                <?php if ($grace): ?><?= status_tag($p['grace_state']) ?><?php endif; ?>
              </td>
              <td>
                <div class="row-actions">
                  <?php if ($p['status'] === 'active' && $mode === 'mock'): ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/simulate-payment/' . $p['id']) ?>">
                      <?= Csrf::field() ?>
                      <button class="btn btn-sm btn-green" type="submit">Simulate payment</button>
                    </form>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-quiet" href="<?= url('/admin/plan/' . $p['id']) ?>">Open</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
