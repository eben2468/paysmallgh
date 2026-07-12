<section class="page-head wrap">
  <h1>Customers</h1>
  <p>Everyone who's signed up to buy small small. <strong><?= count($users) ?></strong> total.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin') ?>">&larr; Admin home</a>
    <a class="btn btn-sm" href="<?= url('/admin/plans') ?>">All plans</a>
  </div>

  <?php if (empty($users)): ?>
    <p class="muted">No customers yet.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Name</th><th>Phone</th><th>Joined</th><th>Plans</th><th>Active</th><th>Paid into escrow</th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><a href="<?= url('/admin/user/' . $u['id']) ?>"><?= e($u['name']) ?></a></td>
              <td class="mono"><?= e(pretty_phone($u['phone'])) ?></td>
              <td class="small muted nowrap"><?= e(date('j M Y', strtotime((string) $u['created_at']))) ?></td>
              <td><?= (int) $u['plans_total'] ?></td>
              <td><?= (int) $u['plans_active'] ?></td>
              <td class="nowrap"><strong><?= ghs((int) $u['paid_pesewas']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
