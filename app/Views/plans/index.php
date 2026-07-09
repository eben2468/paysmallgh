<section class="page-head wrap">
  <h1>My plans</h1>
  <p>Everything you're paying for, and how far you've reached.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <?php if (empty($plans)): ?>
    <p class="muted mb-3">No plans yet. Find something you've been putting off buying.</p>
    <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
  <?php else: ?>
    <div class="plan-list">
      <?php foreach ($plans as $plan): ?>
        <?php
          $paid = (int) $plan['installments_paid'];
          $total = (int) $plan['installments_total'];
          $pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
          $left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
        ?>
        <div class="plan-row">
          <div class="plan-row-top">
            <div>
              <h3><a href="<?= url('/plan/' . $plan['id']) ?>"><?= e($plan['product_name']) ?></a></h3>
              <span class="plan-shop"><?= e($plan['shop_name']) ?></span>
            </div>
            <span class="tag tag-<?= e($plan['status']) ?>"><?= $plan['status'] === 'pending' ? 'awaiting payment' : e($plan['status']) ?></span>
          </div>
          <?php if ($plan['status'] === 'pending'): ?>
            <p class="small muted"><?= svg_icon('clock', 15) ?> First payment not confirmed yet. <a href="<?= url('/plan/' . $plan['id']) ?>">Finish or check it</a>.</p>
          <?php else: ?>
          <div>
            <div class="progress <?= $plan['status'] === 'completed' ? 'progress-done' : '' ?>" style="--pct: <?= $pct ?>%"></div>
            <div class="progress-row">
              <span><strong><?= $paid ?> of <?= $total ?></strong> paid (<?= ghs((int) $plan['installment_pesewas']) ?>/week)</span>
              <span><?= $plan['status'] === 'completed' ? 'Fully paid!' : '<strong>' . ghs($left) . '</strong> to go' ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
