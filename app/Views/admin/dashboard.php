<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>Admin</h1>
  <p>Payments mode: <span class="mode-banner"><?= e($mode) ?></span></p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin/plans') ?>">All plans</a>
    <a class="btn btn-sm" href="<?= url('/admin/ledger') ?>">Ledger &amp; SMS</a>
  </div>

  <h2 style="font-size:1.35rem" class="mb-2">Merchants</h2>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Shop</th><th>Owner</th><th>Phone</th><th>Location</th><th>Payout</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($merchants as $m): ?>
          <tr>
            <td><?= e($m['shop_name']) ?></td>
            <td><?= e($m['owner_name']) ?></td>
            <td><?= e(pretty_phone($m['phone'])) ?></td>
            <td><?= e($m['location']) ?></td>
            <td><?= e($m['payout_channel']) ?> &middot; <?= e(pretty_phone($m['payout_number'])) ?></td>
            <td><span class="tag tag-<?= $m['status'] === 'approved' ? 'completed' : ($m['status'] === 'pending' ? 'pending' : 'cancelled') ?>"><?= e($m['status']) ?></span></td>
            <td>
              <?php if ($m['status'] === 'pending'): ?>
                <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/approve') ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-green" type="submit">Approve</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
