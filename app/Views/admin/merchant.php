<?php
use App\Core\Csrf;

$activePlans = count(array_filter($plans, fn($p) => $p['status'] === 'active'));
$escrow = 0;
foreach ($plans as $pl) {
    if ($pl['status'] === 'active') {
        $escrow += (int) $pl['installments_paid'] * (int) $pl['installment_pesewas'];
    }
}
?>
<a class="pg-back" href="<?= url('/admin/merchants') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Merchants</a>
<div class="pg-head">
  <div>
    <h1><?= e($merchant['shop_name']) ?></h1>
    <p>
      <?= status_tag($merchant['status']) ?>
      <?php if ($merchant['verified']): ?><span class="tag tag-verified"><?= micon('verified', ['size' => 14, 'fill' => true]) ?> Verified</span><?php endif; ?>
      &nbsp;Joined <?= e(when($merchant['created_at'], false)) ?>
    </p>
  </div>
  <div class="pg-actions">
    <?php if (in_array($merchant['status'], ['pending', 'rejected'], true)): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/approve') ?>">
        <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit"><?= micon('check', ['size' => 18]) ?> <?= $merchant['status'] === 'rejected' ? 'Approve anyway' : 'Approve shop' ?></button>
      </form>
    <?php elseif ($merchant['status'] === 'approved'): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/suspend') ?>" data-confirm="Suspend this shop? Its products stop showing to customers.">
        <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit"><?= micon('block', ['size' => 18]) ?> Suspend</button>
      </form>
    <?php else: ?>
      <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/reactivate') ?>">
        <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit"><?= micon('restart_alt', ['size' => 18]) ?> Reactivate</button>
      </form>
    <?php endif; ?>
    <?php if ($merchant['verified']): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/unverify') ?>" data-confirm="Remove the verified badge?">
        <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Remove verified badge</button>
      </form>
    <?php else: ?>
      <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/verify') ?>">
        <?= Csrf::field() ?><button class="btn btn-sm btn-primary" type="submit"><?= micon('verified', ['size' => 18]) ?> Mark identity verified</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($merchant['status'] === 'pending'): ?>
  <section class="panel review-panel">
    <div class="panel-head"><h2><?= micon('fact_check', ['size' => 20]) ?> Review this shop</h2></div>
    <div class="panel-body">
      <p class="small muted mb-2">Check the Ghana Card below against the owner's name and number. Approve to put the shop live, or decline with a clear reason — the owner gets it by SMS, fixes it, and asks for another look.</p>
      <form method="post" action="<?= url('/admin/merchant/' . $merchant['id'] . '/decline') ?>" class="decline-form">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="decline-note">Reason for declining</label>
          <input id="decline-note" name="note" type="text" maxlength="255" required placeholder="e.g. Ghana Card photo is blurry — upload a clear one">
        </div>
        <button class="btn btn-sm btn-danger" type="submit"><?= micon('block', ['size' => 18]) ?> Decline for now</button>
      </form>
    </div>
  </section>
<?php elseif ($merchant['status'] === 'rejected'): ?>
  <div class="banner is-bad"><?= micon('block', ['size' => 22]) ?><div><b>Declined.</b>Reason given: <?= e($merchant['review_note'] !== '' ? $merchant['review_note'] : '—') ?>. The owner can fix it and ask for review again; it'll come back to "Waiting approval".</div></div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">In escrow for this shop</span></div>
    <div class="kpi-value"><?= e(ghs($escrow)) ?></div>
    <span class="kpi-sub">Paid out when each plan completes</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Active plans</span></div>
    <div class="kpi-value"><?= $activePlans ?></div>
    <span class="kpi-sub"><?= count($plans) ?> plans in total</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Products</span></div>
    <div class="kpi-value"><?= count($products) ?></div>
    <span class="kpi-sub"><?= count(array_filter($products, fn($pr) => $pr['active'])) ?> visible in the shop</span>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2><?= micon('receipt_long', ['size' => 20]) ?> Customer plans</h2></div>
      <?php if (empty($plans)): ?>
        <div class="panel-empty"><?= micon('receipt_long') ?>No plans on this shop's products yet.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Plan</th><th>Customer</th><th>Progress</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($plans as $pl): ?>
                <?php $pc = (int) $pl['installments_total'] > 0 ? (int) round($pl['installments_paid'] / $pl['installments_total'] * 100) : 0; ?>
                <tr>
                  <td><a class="cell-main" href="<?= url('/admin/plan/' . $pl['id']) ?>">#<?= (int) $pl['id'] ?></a><span class="cell-sub"><?= e($pl['product_name']) ?></span></td>
                  <td><?= e($pl['customer_name']) ?></td>
                  <td class="cell-mini-bar"><span class="small"><?= (int) $pl['installments_paid'] ?> of <?= (int) $pl['installments_total'] ?></span><?= progress_bar($pc, $pl['status'] === 'completed' ? 'success' : 'primary') ?></td>
                  <td><?= status_tag($pl['status']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('inventory_2', ['size' => 20]) ?> Products</h2></div>
      <?php if (empty($products)): ?>
        <div class="panel-empty"><?= micon('inventory_2') ?>No products listed yet.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Product</th><th>Category</th><th class="right">Cash price</th><th>In shop</th></tr></thead>
            <tbody>
              <?php foreach ($products as $pr): ?>
                <tr>
                  <td><a class="cell-main" href="<?= url('/product/' . $pr['id']) ?>" target="_blank" rel="noopener"><?= e($pr['name']) ?></a></td>
                  <td class="small muted"><?= e(\App\Models\Product::categoryLabel((string) $pr['category'])) ?></td>
                  <td class="right nowrap"><?= e(ghs((int) $pr['cash_price_pesewas'])) ?></td>
                  <td><?= $pr['active'] ? '<span class="tag tag-active">Visible</span>' : '<span class="tag">Hidden</span>' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2><?= micon('storefront', ['size' => 20]) ?> Shop details</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <div><dt>Owner</dt><dd><?= e($merchant['owner_name']) ?></dd></div>
          <div><dt>Phone</dt><dd class="mono"><?= e(pretty_phone($merchant['phone'])) ?></dd></div>
          <div><dt>Location</dt><dd><?= e($merchant['location'] ?: '—') ?></dd></div>
          <div><dt>Payout</dt><dd><?= $merchant['payout_channel'] === 'bank' ? 'Bank' : 'MoMo' ?><?= ($merchant['payout_bank_code'] ?? '') !== '' ? ' (' . e($merchant['payout_bank_code']) . ')' : '' ?> &middot; <span class="mono"><?= e(pretty_phone($merchant['payout_number'])) ?></span></dd></div>
          <div><dt>Ghana Card</dt><dd class="mono"><?= e($merchant['id_number'] ?: '—') ?></dd></div>
          <div><dt>Business reg</dt><dd><?= e($merchant['business_reg'] ?: '—') ?></dd></div>
          <?php if (!empty($merchant['verified_at'])): ?>
            <div><dt>Verified</dt><dd><?= e(when($merchant['verified_at'], false)) ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('badge', ['size' => 20]) ?> Ghana Card</h2></div>
      <div class="panel-body">
        <?php if (!empty($merchant['id_card_path'])): ?>
          <div class="id-doc">
            <a href="<?= url('/admin/merchant/' . $merchant['id'] . '/id-card') ?>" target="_blank" rel="noopener">
              <img src="<?= url('/admin/merchant/' . $merchant['id'] . '/id-card') ?>" alt="Ghana Card for <?= e($merchant['shop_name']) ?>">
            </a>
            <p class="small muted mt-1"><?= micon('lock', ['size' => 13, 'fill' => true]) ?> Confidential — only admins can see this. Click to open full size.</p>
          </div>
        <?php else: ?>
          <p class="muted small">No card uploaded. Verify with care, or ask the owner to send it.</p>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
