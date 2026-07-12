<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>My active plans</h1>
  <p>Manage your layaway progress and upcoming payments.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <?php if (empty($plans)): ?>
    <div class="empty-card">
      <h2>No plans yet</h2>
      <p>Find something you've been putting off buying and start paying small small.</p>
      <a class="btn btn-primary" href="<?= url('/shop') ?>">Browse products</a>
    </div>
  <?php else: ?>
    <div class="dash-grid">
      <div class="plan-stack reveal stagger">
        <?php foreach ($plans as $plan): ?>
          <?php
            $paid = (int) $plan['installments_paid'];
            $total = (int) $plan['installments_total'];
            $pct = $total > 0 ? (int) round($paid / $total * 100) : 0;
            $left = max(0, ($total - $paid) * (int) $plan['installment_pesewas']);
            $totalAmt = $total * (int) $plan['installment_pesewas'];
            $done = $plan['status'] === 'completed';
            $pending = $plan['status'] === 'pending';
            $grace = ($plan['grace_state'] ?? 'ok') !== 'ok';
            $variant = $done ? 'success' : ($grace ? 'warn' : 'primary');
            // Only a plan you've never paid into can be deleted outright.
            $deletable = $paid === 0 && !$done;
          ?>
          <div class="plan-card">
            <div class="plan-card-status">
              <?php if ($done): ?>
                <span class="tag tag-completed"><?= micon('check_circle', ['size' => 14, 'fill' => true]) ?> Paid in full</span>
              <?php elseif ($pending): ?>
                <span class="tag tag-pending"><?= micon('schedule', ['size' => 14]) ?> Awaiting first payment</span>
              <?php elseif ($grace): ?>
                <span class="tag tag-grace"><?= micon('warning', ['size' => 14]) ?> Payment due soon</span>
              <?php else: ?>
                <span class="tag tag-on-track"><?= micon('schedule', ['size' => 14]) ?> On track</span>
              <?php endif; ?>
            </div>

            <div class="plan-card-body">
              <div class="plan-card-media">
                <?php if (!empty($plan['photo'])): ?>
                  <img src="<?= url('/' . $plan['photo']) ?>" alt="<?= e($plan['product_name']) ?>">
                <?php else: ?>
                  <div class="photo-placeholder"><?= micon(product_micon($plan['category'] ?? 'general'), ['size' => 34]) ?></div>
                <?php endif; ?>
              </div>

              <div class="plan-card-main">
                <h3><a href="<?= url('/plan/' . $plan['id']) ?>"><?= e($plan['product_name']) ?></a></h3>
                <p class="plan-card-shop"><?= e($plan['shop_name']) ?></p>

                <?php if ($pending): ?>
                  <p class="small muted mb-2"><?= micon('schedule', ['size' => 16]) ?> First payment not confirmed yet.</p>
                  <div class="plan-actions">
                    <a class="btn btn-primary" href="<?= url('/plan/' . $plan['id']) ?>">Finish or check it</a>
                    <?php if ($deletable): ?>
                      <form class="inline-form" method="post" action="<?= url('/plan/' . $plan['id'] . '/delete') ?>"
                            data-confirm="Delete this plan? This can't be undone.">
                        <?= Csrf::field() ?>
                        <button class="btn btn-quiet" type="submit"><?= micon('delete', ['size' => 18]) ?> Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="plan-figures">
                    <div>
                      <span class="k"><?= $done ? 'Total paid' : 'Remaining balance' ?></span>
                      <span class="big"><?= ghs($done ? $totalAmt : $left) ?></span>
                    </div>
                    <div class="right">
                      <span class="k">Total price</span>
                      <span class="total"><?= ghs($totalAmt) ?></span>
                    </div>
                  </div>

                  <div class="progress-legend">
                    <span><b><?= $paid ?> of <?= $total ?> paid</b></span>
                    <span><?= $pct ?>%</span>
                  </div>
                  <?= progress_bar($pct, $variant) ?>

                  <div class="plan-actions">
                    <?php if ($done): ?>
                      <a class="btn btn-outline" href="<?= url('/plan/' . $plan['id']) ?>">View receipt</a>
                    <?php else: ?>
                      <a class="btn btn-momo" href="<?= url('/plan/' . $plan['id']) ?>"><?= micon('smartphone', ['size' => 18]) ?> Pay via MoMo</a>
                      <a class="btn btn-outline" href="<?= url('/plan/' . $plan['id']) ?>">View schedule</a>
                    <?php endif; ?>
                    <?php if ($deletable): ?>
                      <form class="inline-form" method="post" action="<?= url('/plan/' . $plan['id'] . '/delete') ?>"
                            data-confirm="Delete this plan? This can't be undone.">
                        <?= Csrf::field() ?>
                        <button class="btn btn-quiet" type="submit"><?= micon('delete', ['size' => 18]) ?> Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <aside class="plan-stack">
        <div class="info-card">
          <div class="info-ic"><?= micon('shield', ['size' => 20, 'fill' => true]) ?></div>
          <div>
            <h4>The grace period</h4>
            <p>Life happens. If you miss a payment, your funds are safe. You have a 3-day grace period to catch up without penalties. Cancel anytime for a refund minus a 5% fee.</p>
          </div>
        </div>
        <div class="info-card">
          <div class="info-ic"><?= micon('sms', ['size' => 20, 'fill' => true]) ?></div>
          <div>
            <h4>SMS on every payment</h4>
            <p>We text you a receipt after each installment, and a reminder before the next one is due — so you always know where you've reached.</p>
          </div>
        </div>
      </aside>
    </div>
  <?php endif; ?>
</section>
