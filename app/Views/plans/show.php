<?php use App\Core\Csrf; ?>
<?php
  $paid = (int) $plan['installments_paid'];
  $total = (int) $plan['installments_total'];
  $pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
  $left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
  $done = $plan['status'] === 'completed';
  $grace = ($plan['grace_state'] ?? 'ok') !== 'ok';
  $variant = $done ? 'success' : ($grace ? 'warn' : 'primary');
  $stamped = flash('stamped') !== null;
  $unitNoun = ['daily' => 'days', 'weekly' => 'weeks', 'monthly' => 'months'][$plan['frequency'] ?? 'weekly'] ?? 'payments';
?>
<section class="wrap detail-grid">
  <div>
    <p class="small muted mb-2"><a href="<?= url('/plans') ?>">&larr; All my plans</a></p>

    <div class="receipt reveal">
      <?php if ($paid > 0 || $done): ?>
        <span class="stamp <?= $done ? '' : 'stamp-orange' ?>" style="position:absolute;top:1.2rem;right:1.2rem"
              <?= $stamped ? 'data-stamp="fresh"' : '' ?>>
          <?= micon('check_circle', ['size' => 14, 'fill' => true]) ?> <?= $done ? 'Paid in full' : 'Paid ' . $paid . ' of ' . $total ?>
        </span>
      <?php endif; ?>

      <span class="receipt-tag">Your plan</span>
      <h1 class="receipt-title"><?= e($plan['product_name']) ?></h1>
      <p class="receipt-sub"><?= e($plan['shop_name']) ?></p>
      <p class="mt-1">
        <span class="tag tag-<?= e($plan['status']) ?>"><?= e($plan['status']) ?></span>
        <?php if (($plan['grace_state'] ?? '') === 'grace'): ?><span class="tag tag-grace">grace period</span><?php endif; ?>
        <?php if (($plan['grace_state'] ?? '') === 'flagged'): ?><span class="tag tag-flagged">behind schedule</span><?php endif; ?>
      </p>

      <div class="perf"></div>

      <div class="receipt-math"><?= ghs((int) $plan['installment_pesewas']) ?> &times; <?= $total ?> <?= $unitNoun ?> = <?= ghs((int) $plan['installment_pesewas'] * $total) ?></div>

      <div class="progress-legend">
        <span><b><?= $paid ?> of <?= $total ?></b> paid</span>
        <span><?= $done ? 'Fully paid!' : ghs($left) . ' left' ?></span>
      </div>
      <?= progress_bar($pct, $variant) ?>
    </div>

    <?php if (!empty($pendingTx)): ?>
      <?php $firstEver = $plan['status'] === 'pending'; ?>
      <div class="pay-pending mt-3" data-poll-status="<?= url('/plan/' . $plan['id'] . '/status') ?>">
        <p class="pay-pending-title"><?= micon('schedule', ['size' => 20]) ?> Waiting for your payment</p>
        <p class="small">You're paying <strong><?= ghs((int) $pendingTx['amount_pesewas']) ?></strong> on the payment page. Once it goes through, we'll confirm it here automatically.<?= $firstEver ? ' Your plan starts the moment it clears.' : '' ?></p>
        <p class="small muted" data-poll-note style="display:none"><?= micon('autorenew', ['size' => 14]) ?> Checking for your payment&hellip;</p>
        <form method="post" action="<?= url('/plan/' . $plan['id'] . '/check') ?>" class="mt-2">
          <?= Csrf::field() ?>
          <button class="btn btn-green" type="submit"><?= micon('refresh', ['size' => 18]) ?> I've paid — check now</button>
        </form>
      </div>
    <?php elseif ($plan['status'] === 'active'): ?>
      <form method="post" action="<?= url('/plan/' . $plan['id'] . '/pay') ?>" class="mt-3">
        <?= Csrf::field() ?>
        <button class="btn btn-momo btn-lg btn-block" type="submit"><?= micon('smartphone', ['size' => 20]) ?> Pay this week's <?= ghs((int) $plan['installment_pesewas']) ?></button>
      </form>
      <form method="post" action="<?= url('/plan/' . $plan['id'] . '/cancel') ?>" class="mt-2"
            data-confirm="Cancel this plan? You'll get back what you've paid minus a 5% fee.">
        <?= Csrf::field() ?>
        <button class="btn btn-quiet btn-sm" type="submit">Cancel plan &amp; refund me</button>
      </form>
    <?php elseif ($plan['status'] === 'pending'): ?>
      <div class="pay-pending mt-3">
        <p class="pay-pending-title"><?= micon('schedule', ['size' => 20]) ?> This plan hasn't started yet</p>
        <p class="small">The first payment wasn't completed. Start it now to lock in your plan.</p>
        <form method="post" action="<?= url('/plan/' . $plan['id'] . '/pay') ?>" class="mt-2">
          <?= Csrf::field() ?>
          <button class="btn btn-primary" type="submit">Pay first <?= ghs((int) $plan['installment_pesewas']) ?> now</button>
        </form>
      </div>
    <?php elseif ($done): ?>
      <div class="info-card mt-3">
        <div class="info-ic"><?= micon('inventory_2', ['size' => 20, 'fill' => true]) ?></div>
        <div>
          <h4>This one's done — go collect it</h4>
          <p>Collect from <strong><?= e($plan['shop_name']) ?></strong>. Show them the SMS we sent you.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="reveal">
    <h2 class="mb-2" style="font-size:1.35rem">Payment schedule</h2>
    <ul class="schedule">
      <?php foreach ($installments as $inst): ?>
        <?php
          $isPaid = $inst['paid_at'] !== null;
          $isDue = !$isPaid && days_until($inst['due_date']) <= 0;
        ?>
        <li class="<?= $isPaid ? 'paid' : ($isDue ? 'due' : '') ?>">
          <span>#<?= (int) $inst['number'] ?> &middot; due <?= date('j M', strtotime($inst['due_date'])) ?></span>
          <span class="sch-amount"><?= ghs((int) $inst['amount_pesewas']) ?></span>
          <span class="sch-status"><?= $isPaid ? 'paid ' . date('j M', strtotime($inst['paid_at'])) : ($isDue ? 'due now' : 'coming up') ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
