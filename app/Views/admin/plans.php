<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>All plans</h1>
  <p>Payments mode: <span class="mode-banner"><?= e($mode) ?></span></p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin') ?>">&larr; Admin home</a>
    <form class="inline-form" method="post" action="<?= url('/admin/run-reminders') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-sm" type="submit">Run reminder sweep</button>
    </form>
  </div>

  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>#</th><th>Customer</th><th>Product</th><th>Shop</th><th>Progress</th><th>Status</th><th>Grace</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($plans as $p): ?>
          <tr>
            <td><?= (int) $p['id'] ?></td>
            <td><?= e($p['customer_name']) ?></td>
            <td><?= e($p['product_name']) ?></td>
            <td><?= e($p['shop_name']) ?></td>
            <td class="nowrap"><?= (int) $p['installments_paid'] ?>/<?= (int) $p['installments_total'] ?> &middot; <?= ghs((int) $p['installment_pesewas']) ?></td>
            <td><span class="tag tag-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
            <td><?= $p['grace_state'] === 'ok' ? '—' : '<span class="tag tag-' . e($p['grace_state']) . '">' . e($p['grace_state']) . '</span>' ?></td>
            <td>
              <?php if ($p['status'] === 'active' && $mode === 'mock'): ?>
                <form class="inline-form" method="post" action="<?= url('/admin/simulate-payment/' . $p['id']) ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-green" type="submit">Simulate payment</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
