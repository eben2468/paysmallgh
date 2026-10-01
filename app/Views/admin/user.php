<?php
  $totalPaid = 0;
  $active = 0;
  foreach ($plans as $pl) {
      $totalPaid += (int) $pl['installments_paid'] * (int) $pl['installment_pesewas'];
      $active += $pl['status'] === 'active' ? 1 : 0;
  }
?>
<a class="pg-back" href="<?= url('/admin/users') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Customers</a>
<div class="pg-head">
  <div>
    <h1><?= e($user['name']) ?></h1>
    <p class="mono"><?= e(pretty_phone($user['phone'])) ?> &middot; joined <?= e(when($user['created_at'], false)) ?></p>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">Paid into escrow</span></div>
    <div class="kpi-value"><?= e(ghs($totalPaid)) ?></div>
    <span class="kpi-sub">Across all their plans</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Plans</span></div>
    <div class="kpi-value"><?= count($plans) ?></div>
    <span class="kpi-sub"><?= $active ?> active</span>
  </div>
</div>

<section class="panel">
  <div class="panel-head"><h2><?= micon('receipt_long', ['size' => 20]) ?> Plans</h2></div>
  <?php if (empty($plans)): ?>
    <div class="panel-empty"><?= micon('receipt_long') ?>This customer hasn't started any plans yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Plan</th><th>Shop</th><th>Progress</th><th class="right">Paid</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $pl): ?>
            <?php
              $paidAmt = (int) $pl['installments_paid'] * (int) $pl['installment_pesewas'];
              $pc = (int) $pl['installments_total'] > 0 ? (int) round($pl['installments_paid'] / $pl['installments_total'] * 100) : 0;
            ?>
            <tr>
              <td><a class="cell-main" href="<?= url('/admin/plan/' . $pl['id']) ?>">#<?= (int) $pl['id'] ?></a><span class="cell-sub"><?= e($pl['product_name']) ?></span></td>
              <td><?= e($pl['shop_name']) ?></td>
              <td class="cell-mini-bar"><span class="small"><?= (int) $pl['installments_paid'] ?> of <?= (int) $pl['installments_total'] ?></span><?= progress_bar($pc, $pl['status'] === 'completed' ? 'success' : 'primary') ?></td>
              <td class="right nowrap"><?= e(ghs($paidAmt)) ?></td>
              <td><?= status_tag($pl['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
