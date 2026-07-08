<?php use App\Core\Csrf; ?>
<?php
  $paid = (int) $plan['installments_paid'];
  $total = (int) $plan['installments_total'];
  $pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
  $left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
?>
<section class="wrap detail-grid">
  <div>
    <p class="small muted mb-2"><a href="<?= url('/plans') ?>">&larr; All my plans</a></p>
    <h1 class="product-title"><?= e($plan['product_name']) ?></h1>
    <p class="product-meta">
      <?= e($plan['shop_name']) ?> &middot;
      <span class="tag tag-<?= e($plan['status']) ?>"><?= e($plan['status']) ?></span>
      <?php if ($plan['grace_state'] === 'grace'): ?><span class="tag tag-grace">grace period</span><?php endif; ?>
      <?php if ($plan['grace_state'] === 'flagged'): ?><span class="tag tag-flagged">behind schedule</span><?php endif; ?>
    </p>

    <div class="mt-3">
      <div class="progress <?= $plan['status'] === 'completed' ? 'progress-done' : '' ?>" style="--pct: <?= $pct ?>%"></div>
      <div class="progress-row">
        <span><strong><?= $paid ?> of <?= $total ?></strong> paid</span>
        <span><?= $plan['status'] === 'completed' ? 'Fully paid!' : '<strong>' . ghs($left) . '</strong> to go' ?></span>
      </div>
    </div>

    <p class="mt-3 plan-math"><?= ghs((int) $plan['installment_pesewas']) ?> &times; <?= $total ?> weeks = <?= ghs((int) $plan['installment_pesewas'] * $total) ?></p>

    <?php if ($plan['status'] === 'active'): ?>
      <form method="post" action="<?= url('/plan/' . $plan['id'] . '/pay') ?>" class="mt-2">
        <?= Csrf::field() ?>
        <button class="btn btn-primary" type="submit">Pay this week's <?= ghs((int) $plan['installment_pesewas']) ?></button>
      </form>
      <form method="post" action="<?= url('/plan/' . $plan['id'] . '/cancel') ?>" class="mt-3"
            data-confirm="Cancel this plan? You'll get back what you've paid minus a 5% fee.">
        <?= Csrf::field() ?>
        <button class="btn btn-quiet btn-sm" type="submit">Cancel plan &amp; refund me</button>
      </form>
    <?php elseif ($plan['status'] === 'completed'): ?>
      <p class="mt-3"><strong>This one's done — go collect it from <?= e($plan['shop_name']) ?>.</strong> Show them the SMS we sent you.</p>
    <?php endif; ?>
  </div>

  <div>
    <h2 style="font-size:1.25rem" class="mb-2">Payment schedule</h2>
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
