<?php
use App\Core\Csrf;

$paid = (int) $plan['installments_paid'];
$total = (int) $plan['installments_total'];
$pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
$left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
$grace = ($plan['grace_state'] ?? 'ok') !== 'ok';
?>
<a class="pg-back" href="<?= url('/admin/plans') ?>"><?= micon('arrow_back', ['size' => 16]) ?> Plans</a>
<div class="pg-head">
  <div>
    <h1>Plan #<?= (int) $plan['id'] ?> &middot; <?= e($plan['product_name']) ?></h1>
    <p>
      <?= status_tag($plan['status']) ?>
      <?php if ($grace): ?><?= status_tag($plan['grace_state']) ?><?php endif; ?>
      <?php if (($plan['released_at'] ?? null) !== null): ?><span class="tag tag-completed">Item handed over</span><?php endif; ?>
      &nbsp;Started <?= e(when($plan['created_at'], false)) ?>
    </p>
  </div>
  <div class="pg-actions">
    <?php if ($plan['status'] === 'active' && ($mode ?? '') === 'mock'): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/simulate-payment/' . $plan['id']) ?>">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-green" type="submit"><?= micon('bolt', ['size' => 18]) ?> Simulate payment</button>
      </form>
    <?php endif; ?>
    <?php if (!empty($canRetryPayout)): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/plan/' . (int) $plan['id'] . '/retry-payout') ?>"
            data-confirm="Send the payout for plan #<?= (int) $plan['id'] ?> again?">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-primary" type="submit"><?= micon('send', ['size' => 18]) ?> Retry payout</button>
      </form>
    <?php endif; ?>
    <?php if (!empty($outstandingRefunds)): ?>
      <form class="inline-form" method="post" action="<?= url('/admin/plan/' . (int) $plan['id'] . '/retry-refunds') ?>"
            data-confirm="Retry <?= (int) $outstandingRefunds ?> refund(s) on plan #<?= (int) $plan['id'] ?>?">
        <?= Csrf::field() ?>
        <button class="btn btn-sm btn-primary" type="submit"><?= micon('undo', ['size' => 18]) ?> Retry refunds</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($canRetryPayout)): ?>
  <div class="banner is-bad"><?= micon('error', ['size' => 22]) ?><div><b>The merchant payout didn't go through.</b>The customer has paid in full. Check the failed row below for Paystack's reason, fix it, then retry the payout.</div></div>
<?php endif; ?>
<?php if (!empty($outstandingRefunds)): ?>
  <div class="banner is-bad"><?= micon('error', ['size' => 22]) ?><div><b><?= (int) $outstandingRefunds ?> refund(s) still owed.</b>This plan was cancelled but some money hasn't gone back to the customer yet.</div></div>
<?php endif; ?>

<div class="kpi-grid">
  <div class="kpi is-money">
    <div class="kpi-top"><span class="kpi-label">Paid so far</span></div>
    <div class="kpi-value"><?= e(ghs($paid * (int) $plan['installment_pesewas'])) ?></div>
    <span class="kpi-sub"><?= $paid ?> of <?= $total ?> payments</span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Still to pay</span></div>
    <div class="kpi-value"><?= e(ghs($left)) ?></div>
    <span class="kpi-sub"><?= e(plan_math($plan)) ?></span>
  </div>
  <div class="kpi">
    <div class="kpi-top"><span class="kpi-label">Progress</span></div>
    <div class="kpi-value"><?= $pct ?>%</div>
    <?= progress_bar($pct, $plan['status'] === 'completed' ? 'success' : ($grace ? 'warn' : 'primary')) ?>
  </div>
</div>

<div class="grid-2">
  <div class="stack">
    <section class="panel">
      <div class="panel-head"><h2><?= micon('info', ['size' => 20]) ?> Plan details</h2></div>
      <div class="panel-body">
        <dl class="kv">
          <div><dt>Customer</dt><dd><a href="<?= url('/admin/user/' . (int) $plan['customer_id']) ?>"><?= e($plan['customer_name']) ?></a> &middot; <span class="mono"><?= e(pretty_phone($plan['customer_phone'])) ?></span></dd></div>
          <div><dt>Shop</dt><dd><a href="<?= url('/admin/merchant/' . (int) $plan['merchant_id']) ?>"><?= e($plan['shop_name']) ?></a> &middot; <span class="mono"><?= e(pretty_phone($plan['merchant_phone'])) ?></span></dd></div>
          <div><dt>Plan</dt><dd><?= e(plan_math($plan)) ?></dd></div>
          <div><dt>Cash price</dt><dd><?= e(ghs((int) $plan['total_pesewas'])) ?></dd></div>
          <div><dt>Payout to</dt><dd><?= $plan['payout_channel'] === 'bank' ? 'Bank' : 'MoMo' ?> &middot; <span class="mono"><?= e(pretty_phone($plan['payout_number'] ?: $plan['merchant_phone'])) ?></span></dd></div>
          <div><dt>Started</dt><dd><?= e(when($plan['created_at'])) ?></dd></div>
          <?php if (($plan['completed_at'] ?? null) !== null): ?>
            <div><dt>Completed</dt><dd><?= e(when($plan['completed_at'])) ?></dd></div>
          <?php endif; ?>
          <?php if (($plan['released_at'] ?? null) !== null): ?>
            <div><dt>Handed over</dt><dd><?= e(when($plan['released_at'])) ?></dd></div>
          <?php endif; ?>
        </dl>
      </div>
    </section>

    <section class="panel">
      <div class="panel-head"><h2><?= micon('account_balance', ['size' => 20]) ?> Money movements</h2></div>
      <?php if (empty($transactions)): ?>
        <div class="panel-empty"><?= micon('receipt') ?>No transactions on this plan yet.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>#</th><th>Type</th><th class="right">Amount</th><th>Status</th><th>Reference</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach ($transactions as $t): ?>
                <tr>
                  <td><?= (int) $t['id'] ?></td>
                  <td><?= e(ucfirst($t['type'])) ?></td>
                  <td class="right nowrap"><strong><?= e(ghs((int) $t['amount_pesewas'])) ?></strong></td>
                  <td>
                    <?= status_tag($t['status']) ?>
                    <?php if ($t['status'] === 'failed'):
                      $why = json_decode((string) ($t['raw_payload'] ?? ''), true);
                      $why = is_array($why) ? (string) ($why['data']['gateway_response'] ?? $why['message'] ?? $why['error'] ?? '') : '';
                    ?>
                      <?php if ($why !== ''): ?><span class="cell-sub"><?= e(mb_substr($why, 0, 120)) ?></span><?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td class="mono small"><?= e($t['provider_ref']) ?><?= $t['external_ref'] !== '' ? '<span class="cell-sub">' . e($t['external_ref']) . '</span>' : '' ?></td>
                  <td class="small muted nowrap"><?= e(when($t['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <section class="panel">
    <div class="panel-head"><h2><?= micon('event', ['size' => 20]) ?> Payment schedule</h2></div>
    <div class="panel-body">
      <ul class="schedule">
        <?php foreach ($installments as $inst): ?>
          <?php
            $isPaid = $inst['paid_at'] !== null;
            $isDue = !$isPaid && days_until($inst['due_date']) <= 0;
          ?>
          <li class="<?= $isPaid ? 'paid' : ($isDue ? 'due' : '') ?>">
            <span>#<?= (int) $inst['number'] ?> &middot; due <?= date('j M', strtotime($inst['due_date'])) ?></span>
            <span class="sch-amount"><?= e(ghs((int) $inst['amount_pesewas'])) ?></span>
            <span class="sch-status"><?= $isPaid ? 'paid ' . date('j M', strtotime($inst['paid_at'])) : ($isDue ? 'due now' : 'coming up') ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>
