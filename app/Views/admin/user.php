<?php
  $totalPaid = 0;
  foreach ($plans as $pl) {
      $totalPaid += (int) $pl['installments_paid'] * (int) $pl['installment_pesewas'];
  }
?>
<section class="page-head wrap">
  <h1><?= e($user['name']) ?></h1>
  <p class="mono"><?= e(pretty_phone($user['phone'])) ?> &middot; joined <?= e(date('j M Y', strtotime((string) $user['created_at']))) ?></p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin/users') ?>">&larr; All customers</a>
  </div>

  <div class="table-scroll" style="padding:1.3rem;max-width:520px">
    <dl class="kv">
      <div><dt>Name</dt><dd><?= e($user['name']) ?></dd></div>
      <div><dt>Phone</dt><dd class="mono"><?= e(pretty_phone($user['phone'])) ?></dd></div>
      <div><dt>Joined</dt><dd><?= e(date('j M Y', strtotime((string) $user['created_at']))) ?></dd></div>
      <div><dt>Plans</dt><dd><?= count($plans) ?></dd></div>
      <div><dt>Paid into escrow</dt><dd><strong><?= ghs($totalPaid) ?></strong></dd></div>
    </dl>
  </div>

  <h2 style="font-size:1.15rem" class="mt-3 mb-2">Plans</h2>
  <div class="table-scroll">
    <?php if (empty($plans)): ?>
      <p class="muted" style="padding:1.1rem">This customer hasn't started any plans yet.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>#</th><th>Product</th><th>Shop</th><th>Progress</th><th>Paid</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $pl): ?>
            <?php $paidAmt = (int) $pl['installments_paid'] * (int) $pl['installment_pesewas']; ?>
            <tr>
              <td><a href="<?= url('/admin/plan/' . $pl['id']) ?>">#<?= (int) $pl['id'] ?></a></td>
              <td><?= e($pl['product_name']) ?></td>
              <td><?= e($pl['shop_name']) ?></td>
              <td class="nowrap"><?= (int) $pl['installments_paid'] ?>/<?= (int) $pl['installments_total'] ?></td>
              <td class="nowrap"><?= ghs($paidAmt) ?></td>
              <td><span class="tag tag-<?= e($pl['status']) ?>"><?= e($pl['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
