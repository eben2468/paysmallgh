<?php
use App\Core\Csrf;

$p = $stats['plans'];
$money = $stats['money'];
$int = $integrations;

// Things an admin should act on, most urgent first. Only non-zero rows show.
$attention = array_filter([
    ['merchants', 'storefront', 'Shops waiting for approval', 'Review their Ghana Card, then approve.', $stats['merchants_pending'], url('/admin/merchants?status=pending'), ''],
    ['payouts', 'payments', 'Payouts that failed', 'Fully paid plans whose merchant payout needs a retry.', $stats['stuck_payouts'], url('/admin/plans?status=active'), 'is-bad'],
    ['refunds', 'undo', 'Refunds that failed', 'Cancelled plans with money still to send back.', $stats['stuck_refunds'], url('/admin/plans?status=cancelled'), 'is-bad'],
    ['flagged', 'warning', 'Plans past the grace period', 'Customer stopped paying — merchant has been told.', $p['flagged'] ?? 0, url('/admin/plans?status=attention'), 'is-bad'],
    ['grace', 'schedule', 'Plans in the grace period', 'Missed a payment; friendly reminder sent.', $p['in_grace'] ?? 0, url('/admin/plans?status=attention'), ''],
    ['tx', 'sync', 'Payments awaiting confirmation', 'Reconcile to check them with Paystack now.', $stats['tx_pending'], url('/admin/ledger?type=pending'), ''],
], fn($row) => $row[4] > 0);
?>
<div class="pg-head">
  <div>
    <h1>Dashboard</h1>
    <p>How PaySmallSmall is doing today, and anything that needs you.</p>
  </div>
  <div class="pg-actions">
    <form class="inline-form" method="post" action="<?= url('/admin/reconcile') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-sm btn-ghost" type="submit"><?= micon('sync', ['size' => 18]) ?> Reconcile payments</button>
    </form>
    <a class="btn btn-sm btn-primary" href="<?= url('/admin/plans') ?>"><?= micon('receipt_long', ['size' => 18]) ?> All plans</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">Held in escrow</span><span class="kpi-ic"><?= micon('lock', ['size' => 20, 'fill' => true]) ?></span></div>
    <div class="kpi-value"><?= e(ghs((int) ($p['escrow'] ?? 0))) ?></div>
    <span class="kpi-sub">On <?= (int) ($p['active'] ?? 0) ?> active plan<?= ($p['active'] ?? 0) === 1 ? '' : 's' ?></span>
  </div>
  <a class="kpi" href="<?= url('/admin/plans?status=active') ?>">
    <div class="kpi-top"><span class="kpi-label">Active plans</span><span class="kpi-ic"><?= micon('receipt_long') ?></span></div>
    <div class="kpi-value"><?= (int) ($p['active'] ?? 0) ?></div>
    <span class="kpi-sub"><?= (int) ($p['completed'] ?? 0) ?> completed &middot; <?= (int) ($p['pending'] ?? 0) ?> awaiting first payment</span>
  </a>
  <a class="kpi" href="<?= url('/admin/ledger?type=collection') ?>">
    <div class="kpi-top"><span class="kpi-label">Collected</span><span class="kpi-ic"><?= micon('south_west') ?></span></div>
    <div class="kpi-value"><?= e(ghs((int) ($money['collected'] ?? 0))) ?></div>
    <span class="kpi-sub"><?= e(ghs((int) ($money['paid_out'] ?? 0))) ?> paid out to shops</span>
  </a>
  <a class="kpi" href="<?= url('/admin/merchants') ?>">
    <div class="kpi-top"><span class="kpi-label">Shops</span><span class="kpi-ic"><?= micon('storefront') ?></span></div>
    <div class="kpi-value"><?= (int) $stats['merchants_total'] ?></div>
    <span class="kpi-sub"><?= (int) $stats['customers'] ?> customers &middot; <?= (int) $stats['merchants_pending'] ?> shop<?= $stats['merchants_pending'] === 1 ? '' : 's' ?> to approve</span>
  </a>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel">
      <div class="panel-head">
        <h2><?= micon('notifications_active', ['size' => 20]) ?> Needs your attention</h2>
      </div>
      <?php if (!$attention): ?>
        <ul class="attn-list">
          <li><div class="attn-item">
            <span class="attn-ic is-ok"><?= micon('check_circle', ['size' => 20, 'fill' => true]) ?></span>
            <span class="attn-text"><b>All clear</b><span>No approvals, failed payouts or stalled plans right now.</span></span>
          </div></li>
        </ul>
      <?php else: ?>
        <ul class="attn-list">
          <?php foreach ($attention as [$key, $icon, $label, $hint, $count, $href, $tone]): ?>
            <li><a class="attn-item" href="<?= e($href) ?>">
              <span class="attn-ic <?= e($tone) ?>"><?= micon($icon, ['size' => 20]) ?></span>
              <span class="attn-text"><b><?= e($label) ?></b><span><?= e($hint) ?></span></span>
              <span class="attn-count"><?= (int) $count ?></span>
              <?= micon('chevron_right', ['size' => 20]) ?>
            </a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2><?= micon('account_balance', ['size' => 20]) ?> Latest money movements</h2>
        <a class="panel-link" href="<?= url('/admin/ledger') ?>">All transactions <?= micon('arrow_forward', ['size' => 16]) ?></a>
      </div>
      <?php if (empty($recent)): ?>
        <div class="panel-empty"><?= micon('receipt') ?>No transactions yet.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>When</th><th>Type</th><th>Plan</th><th class="right">Amount</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $t): ?>
                <tr>
                  <td class="small muted nowrap"><?= e(when($t['created_at'])) ?></td>
                  <td><?= e(ucfirst($t['type'])) ?></td>
                  <td><?= $t['plan_id'] ? '<a href="' . url('/admin/plan/' . (int) $t['plan_id']) . '">#' . (int) $t['plan_id'] . '</a>' : '—' ?></td>
                  <td class="right nowrap"><strong><?= e(ghs((int) $t['amount_pesewas'])) ?></strong></td>
                  <td><?= status_tag($t['status']) ?></td>
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
      <div class="panel-head">
        <h2><?= micon('how_to_reg', ['size' => 20]) ?> Shops to approve</h2>
        <a class="panel-link" href="<?= url('/admin/merchants?status=pending') ?>">See all <?= micon('arrow_forward', ['size' => 16]) ?></a>
      </div>
      <?php if (empty($pendingMerchants)): ?>
        <div class="panel-empty"><?= micon('task_alt') ?>No shops waiting.</div>
      <?php else: ?>
        <ul class="attn-list">
          <?php foreach ($pendingMerchants as $m): ?>
            <li><div class="attn-item">
              <span class="avatar avatar-sm"><?= e(strtoupper(mb_substr($m['shop_name'], 0, 1))) ?></span>
              <span class="attn-text">
                <b><a href="<?= url('/admin/merchant/' . $m['id']) ?>"><?= e($m['shop_name']) ?></a></b>
                <span><?= e($m['owner_name']) ?> &middot; <?= e($m['location'] ?: pretty_phone($m['phone'])) ?></span>
              </span>
              <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/approve') ?>">
                <?= Csrf::field() ?>
                <button class="btn btn-sm btn-green" type="submit">Approve</button>
              </form>
            </div></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2><?= micon('warning', ['size' => 20]) ?> Plans behind on payment</h2>
        <a class="panel-link" href="<?= url('/admin/plans?status=attention') ?>">See all <?= micon('arrow_forward', ['size' => 16]) ?></a>
      </div>
      <?php if (empty($attentionPlans)): ?>
        <div class="panel-empty"><?= micon('thumb_up') ?>Everyone is paying on time.</div>
      <?php else: ?>
        <ul class="attn-list">
          <?php foreach ($attentionPlans as $pl): ?>
            <li><a class="attn-item" href="<?= url('/admin/plan/' . $pl['id']) ?>">
              <span class="attn-text">
                <b><?= e($pl['customer_name']) ?></b>
                <span><?= e($pl['product_name']) ?> &middot; <?= (int) $pl['installments_paid'] ?>/<?= (int) $pl['installments_total'] ?> paid</span>
              </span>
              <?= status_tag($pl['grace_state']) ?>
            </a></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2><?= micon('hub', ['size' => 20]) ?> Connections</h2>
        <a class="panel-link" href="<?= url('/admin/system') ?>">System <?= micon('arrow_forward', ['size' => 16]) ?></a>
      </div>
      <div class="panel-body">
        <div class="status-row">
          <span>Payments (Paystack)</span>
          <?php if ($int['mode'] === 'mock'): ?>
            <span class="tag tag-pending">Mock — no real money</span>
          <?php elseif (!$int['paystack']['has_key']): ?>
            <span class="tag tag-flagged">No secret key</span>
          <?php else: ?>
            <span class="tag tag-active"><?= e(ucfirst($int['mode'])) ?> &middot; <?= e($int['paystack']['key_kind']) ?> key</span>
          <?php endif; ?>
        </div>
        <div class="status-row">
          <span>SMS (Moolre)</span>
          <?php if (!$int['sms']['has_key']): ?>
            <span class="tag tag-flagged">No VAS key</span>
          <?php elseif ($int['sms']['live']): ?>
            <span class="tag tag-active">Live &middot; <?= e($int['sms']['sender']) ?></span>
          <?php else: ?>
            <span class="tag tag-pending">Mock — logging only</span>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
