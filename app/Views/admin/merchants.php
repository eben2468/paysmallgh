<?php
use App\Core\Csrf;

$tabs = ['all' => 'All shops', 'pending' => 'Waiting approval', 'approved' => 'Live', 'suspended' => 'Suspended'];
?>
<div class="pg-head">
  <div>
    <h1>Merchants</h1>
    <p>Every shop on PaySmallSmall. Check the Ghana Card before you approve — approved shops go live straight away.</p>
  </div>
</div>

<nav class="tabs" aria-label="Filter merchants">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="<?= $filter === $key ? 'active' : '' ?>" href="<?= url('/admin/merchants' . ($key === 'all' ? '' : '?status=' . $key)) ?>">
      <?= e($label) ?> <span class="tab-count"><?= (int) ($counts[$key] ?? 0) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<section class="panel">
  <?php if (empty($merchants)): ?>
    <div class="panel-empty"><?= micon('storefront') ?>No shops here.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Shop</th><th>Owner</th><th>Payout account</th><th>Identity</th><th>Status</th><th class="right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($merchants as $m): ?>
            <tr>
              <td>
                <a class="cell-main" href="<?= url('/admin/merchant/' . $m['id']) ?>"><?= e($m['shop_name']) ?></a>
                <span class="cell-sub"><?= e($m['location'] ?: '—') ?></span>
              </td>
              <td>
                <?= e($m['owner_name']) ?>
                <span class="cell-sub mono"><?= e(pretty_phone($m['phone'])) ?></span>
              </td>
              <td>
                <?= $m['payout_channel'] === 'bank' ? 'Bank' : 'MoMo' ?><?= ($m['payout_bank_code'] ?? '') !== '' ? ' &middot; ' . e($m['payout_bank_code']) : '' ?>
                <span class="cell-sub mono"><?= e(pretty_phone($m['payout_number'])) ?></span>
              </td>
              <td>
                <?php if ($m['verified']): ?>
                  <span class="tag tag-verified"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Verified</span>
                <?php elseif (!empty($m['id_card_path'])): ?>
                  <a class="small" href="<?= url('/admin/merchant/' . $m['id'] . '/id-card') ?>" target="_blank" rel="noopener"><?= micon('badge', ['size' => 14]) ?> View Ghana Card</a>
                <?php else: ?>
                  <span class="small muted">No card uploaded</span>
                <?php endif; ?>
                <span class="cell-sub mono"><?= e($m['id_number'] ?: '—') ?></span>
              </td>
              <td><?= status_tag($m['status']) ?></td>
              <td>
                <div class="row-actions">
                  <?php if ($m['status'] === 'pending'): ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/approve') ?>">
                      <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit">Approve</button>
                    </form>
                  <?php elseif ($m['status'] === 'approved'): ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/suspend') ?>"
                          data-confirm="Suspend <?= e($m['shop_name']) ?>? Their products stop showing to customers.">
                      <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Suspend</button>
                    </form>
                  <?php else: ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/reactivate') ?>">
                      <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit">Reactivate</button>
                    </form>
                  <?php endif; ?>
                  <?php if (!$m['verified']): ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/verify') ?>">
                      <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= micon('verified', ['size' => 16]) ?> Verify</button>
                    </form>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-quiet" href="<?= url('/admin/merchant/' . $m['id']) ?>">Open</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
