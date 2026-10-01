<div class="pg-head">
  <div>
    <h1>Customers</h1>
    <p>Everyone who's signed up to buy small small &mdash; <strong><?= count($users) ?></strong> in total.</p>
  </div>
</div>

<section class="panel">
  <?php if (empty($users)): ?>
    <div class="panel-empty"><?= micon('group') ?>No customers yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Customer</th><th>Joined</th><th class="right">Plans</th><th class="right">Active</th><th class="right">Paid into escrow</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <a class="cell-main" href="<?= url('/admin/user/' . $u['id']) ?>"><?= e($u['name']) ?></a>
                <span class="cell-sub mono"><?= e(pretty_phone($u['phone'])) ?></span>
              </td>
              <td class="small muted nowrap"><?= e(when($u['created_at'], false)) ?></td>
              <td class="right"><?= (int) $u['plans_total'] ?></td>
              <td class="right"><?= (int) $u['plans_active'] ?></td>
              <td class="right nowrap"><strong><?= e(ghs((int) $u['paid_pesewas'])) ?></strong></td>
              <td class="right"><a class="btn btn-sm btn-quiet" href="<?= url('/admin/user/' . $u['id']) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
