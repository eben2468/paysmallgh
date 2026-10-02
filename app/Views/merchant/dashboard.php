<?php
use App\Core\Csrf;

$toRelease = array_values(array_filter($plans, fn($p) => $p['status'] === 'completed' && ($p['released_at'] ?? null) === null));
$first = explode(' ', (string) $merchant['owner_name'])[0];
?>
<div class="pg-head">
  <div>
    <h1>Hello, <?= e($first) ?></h1>
    <p>Here's where your sales stand. Money from customers sits in escrow and comes to you when each plan is paid in full.</p>
  </div>
  <div class="pg-actions">
    <a class="btn btn-sm btn-ghost" href="<?= url('/merchant/payouts') ?>"><?= micon('account_balance_wallet', ['size' => 18]) ?> Payouts</a>
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add product</a>
  </div>
</div>

<?php if ($merchant['status'] === 'pending'): ?>
  <div class="banner"><?= micon('hourglass_top', ['size' => 22]) ?><div><b>Your shop is under review.</b>Customers can't see your products yet. Add them now so you're ready the moment we approve you &mdash; we'll text you.</div></div>
<?php elseif ($merchant['status'] === 'rejected'): ?>
  <div class="banner is-bad"><?= micon('error', ['size' => 22]) ?><div>
    <b>Your shop wasn't approved yet.</b>
    <?= $merchant['review_note'] !== '' ? 'Reason: ' . e($merchant['review_note']) . '. ' : '' ?>Fix it in <a href="<?= url('/merchant/settings') ?>">shop settings</a>, then ask us to look again.
    <form class="inline-form mt-1" method="post" action="<?= url('/merchant/request-review') ?>">
      <?= \App\Core\Csrf::field() ?>
      <button class="btn btn-sm btn-primary" type="submit"><?= micon('refresh', ['size' => 16]) ?> Ask for review again</button>
    </form>
  </div></div>
<?php elseif ($merchant['status'] === 'suspended'): ?>
  <div class="banner is-bad"><?= micon('block', ['size' => 22]) ?><div><b>Your shop is suspended.</b>Your products are hidden from customers. Running plans continue. Call us to sort it out.</div></div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">In escrow for you</span><span class="kpi-ic"><?= micon('lock', ['size' => 20, 'fill' => true]) ?></span></div>
    <div class="kpi-value"><?= e(ghs((int) $stats['in_escrow'])) ?></div>
    <span class="kpi-sub">Paid out as each plan completes</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Active plans</span><span class="kpi-ic"><?= micon('receipt_long') ?></span></div>
    <div class="kpi-value"><?= (int) $stats['active'] ?></div>
    <span class="kpi-sub">Customers paying small small</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Completed</span><span class="kpi-ic"><?= micon('task_alt') ?></span></div>
    <div class="kpi-value"><?= (int) $stats['completed'] ?></div>
    <span class="kpi-sub">Plans paid in full</span>
  </div>
  <a class="kpi" href="<?= url('/merchant/products') ?>">
    <div class="kpi-top"><span class="kpi-label">Products</span><span class="kpi-ic"><?= micon('inventory_2') ?></span></div>
    <div class="kpi-value"><?= (int) $stats['products'] ?></div>
    <span class="kpi-sub">Listed in your shop</span>
  </a>
</div>

<?php if ($toRelease): ?>
  <section class="panel mb-3">
    <div class="panel-head">
      <h2><?= micon('local_shipping', ['size' => 20]) ?> Ready to hand over</h2>
      <span class="panel-sub">Paid in full and paid out to you &mdash; give the customer their item, then mark it.</span>
    </div>
    <ul class="attn-list">
      <?php foreach ($toRelease as $p): ?>
        <li><div class="attn-item">
          <span class="attn-ic is-ok"><?= micon('inventory_2', ['size' => 20]) ?></span>
          <span class="attn-text"><b><?= e(plan_item($p)) ?></b><span><?= e($p['customer_name']) ?> &middot; <?= e(pretty_phone($p['customer_phone'])) ?></span>
            <?php if (!empty($p['delivery'])): ?>
              <span class="attn-addr"><?= micon('location_on', ['size' => 14]) ?> <?= e(\App\Models\Address::oneLine($p['delivery'])) ?><?= $p['delivery']['phone'] !== $p['customer_phone'] ? ' &middot; for ' . e($p['delivery']['recipient']) . ', ' . e(pretty_phone($p['delivery']['phone'])) : '' ?></span>
            <?php endif; ?></span>
          <form class="inline-form" method="post" action="<?= url('/merchant/plan/' . $p['id'] . '/release') ?>"
                data-confirm="Confirm you've handed <?= e(plan_item($p)) ?> to <?= e($p['customer_name']) ?>?">
            <?= Csrf::field() ?>
            <button class="btn btn-sm btn-green" type="submit"><?= micon('check', ['size' => 16]) ?> Mark handed over</button>
          </form>
        </div></li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>

<section class="panel">
  <div class="panel-head">
    <h2><?= micon('groups', ['size' => 20]) ?> Customer plans</h2>
    <span class="panel-sub"><?= count($plans) ?> in total</span>
  </div>
  <?php if (empty($plans)): ?>
    <div class="panel-empty"><?= micon('receipt_long') ?>No plans yet. Once a customer starts paying for one of your products, it shows up here.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Customer &amp; item</th><th>Progress</th><th class="right">Paid so far</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <?php
              $paid = (int) $p['installments_paid'];
              $tot = (int) $p['installments_total'];
              $pc = $tot > 0 ? (int) round($paid / $tot * 100) : 0;
              $grace = ($p['grace_state'] ?? 'ok') !== 'ok' && $p['status'] === 'active';
            ?>
            <tr>
              <td>
                <span class="cell-main"><?= e($p['customer_name']) ?></span>
                <span class="cell-sub"><?= e(plan_item($p)) ?> &middot; <span class="mono"><?= e(pretty_phone($p['customer_phone'])) ?></span></span>
              </td>
              <td class="cell-mini-bar">
                <span class="small"><?= $paid ?> of <?= $tot ?> &middot; <?= e(plan_rate($p)) ?></span>
                <?= progress_bar($pc, $p['status'] === 'completed' ? 'success' : ($grace ? 'warn' : 'primary')) ?>
              </td>
              <td class="right nowrap"><strong><?= e(ghs($paid * (int) $p['installment_pesewas'])) ?></strong></td>
              <td>
                <?php if ($p['status'] === 'completed'): ?>
                  <?= ($p['released_at'] ?? null) !== null ? '<span class="tag tag-completed">Handed over</span>' : '<span class="tag tag-completed">Paid out</span>' ?>
                <?php else: ?>
                  <?= status_tag($p['status']) ?>
                <?php endif; ?>
                <?php if ($grace): ?><?= status_tag($p['grace_state'], $p['grace_state'] === 'flagged' ? 'Stopped paying' : 'Missed a payment') ?><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
