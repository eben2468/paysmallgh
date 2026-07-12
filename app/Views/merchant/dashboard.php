<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1><?= e($merchant['shop_name']) ?></h1>
  <p>
    <?php if ($merchant['status'] === 'pending'): ?>
      Your shop is under review — customers can't see your products yet. You can still add them now so you're ready.
    <?php elseif ($merchant['status'] === 'suspended'): ?>
      Your shop is suspended. Call us to sort it out.
    <?php else: ?>
      Live and selling. Here's where things stand.
    <?php endif; ?>
  </p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/merchant/products') ?>"><?= micon('inventory_2', ['size' => 18]) ?> My products</a>
    <a class="btn btn-sm" href="<?= url('/merchant/payouts') ?>"><?= micon('account_balance', ['size' => 18]) ?> Payouts</a>
    <a class="btn btn-sm" href="<?= url('/merchant/settings') ?>"><?= micon('settings', ['size' => 18]) ?> Settings</a>
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>"><?= micon('add', ['size' => 18]) ?> Add product</a>
  </div>

  <div class="stat-row reveal stagger">
    <div class="stat stat-money">
      <div class="stat-ic"><?= micon('account_balance_wallet') ?></div>
      <b><span class="cur">GHS </span><?= number_format((int) $stats['in_escrow'] / 100) ?></b>
      <span>In escrow, coming to you</span>
    </div>
    <div class="stat stat-accent">
      <div class="stat-ic"><?= micon('payments') ?></div>
      <b><?= (int) $stats['active'] ?></b>
      <span>Active plans</span>
    </div>
    <div class="stat">
      <div class="stat-ic"><?= micon('receipt_long') ?></div>
      <b><?= (int) $stats['completed'] ?></b>
      <span>Completed plans</span>
    </div>
    <div class="stat">
      <div class="stat-ic"><?= micon('storefront') ?></div>
      <b><?= (int) $stats['products'] ?></b>
      <span>Products listed</span>
    </div>
  </div>

  <div class="table-scroll">
    <div style="padding:1.1rem 1.2rem;border-bottom:1px solid var(--surface-variant);display:flex;justify-content:space-between;align-items:center">
      <h2 style="font-size:1.2rem">Customer plans</h2>
    </div>
    <?php if (empty($plans)): ?>
      <p class="muted" style="padding:1.5rem 1.2rem">No plans yet. Once a customer starts paying for one of your products, it shows up here.</p>
    <?php else: ?>
      <table class="data">
        <thead>
          <tr><th>Customer &amp; item</th><th>Progress</th><th>Paid so far</th><th>Status</th><th>Item</th></tr>
        </thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <?php
              $paid = (int) $p['installments_paid'];
              $tot = (int) $p['installments_total'];
              $pc = $tot > 0 ? (int) round($paid / $tot * 100) : 0;
              $paidAmt = $paid * (int) $p['installment_pesewas'];
              $grace = ($p['grace_state'] ?? 'ok') !== 'ok';
            ?>
            <tr>
              <td>
                <div style="font-weight:700"><?= e($p['customer_name']) ?></div>
                <div class="small muted mono"><?= e($p['product_name']) ?> · <?= e(pretty_phone($p['customer_phone'])) ?></div>
              </td>
              <td style="min-width:180px">
                <div class="progress-legend"><span>Payment <?= $paid ?> of <?= $tot ?></span><span><b><?= $pc ?>%</b></span></div>
                <?= progress_bar($pc, $grace ? 'warn' : 'primary') ?>
              </td>
              <td class="nowrap"><strong><?= ghs($paidAmt) ?></strong><br><span class="small muted mono"><?= ghs((int) $p['installment_pesewas']) ?>/wk</span></td>
              <td>
                <?php if ($p['status'] === 'completed'): ?>
                  <span class="tag tag-completed"><?= micon('check_circle', ['size' => 14, 'fill' => true]) ?> paid out</span>
                <?php else: ?>
                  <span class="tag tag-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                <?php endif; ?>
                <?php if (($p['grace_state'] ?? '') === 'flagged'): ?><span class="tag tag-flagged">stalled</span><?php endif; ?>
              </td>
              <td class="nowrap">
                <?php if ($p['status'] !== 'completed'): ?>
                  <span class="small muted">&mdash;</span>
                <?php elseif (($p['released_at'] ?? null) !== null): ?>
                  <span class="tag tag-completed"><?= micon('inventory_2', ['size' => 14, 'fill' => true]) ?> released</span>
                <?php else: ?>
                  <form class="inline-form" method="post" action="<?= url('/merchant/plan/' . $p['id'] . '/release') ?>"
                        data-confirm="Confirm you've handed <?= e($p['product_name']) ?> to <?= e($p['customer_name']) ?>?">
                    <?= Csrf::field() ?>
                    <button class="btn btn-sm btn-green" type="submit"><?= micon('check', ['size' => 16]) ?> Mark released</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</section>
