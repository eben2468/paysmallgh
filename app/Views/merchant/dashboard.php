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
    <a class="btn btn-sm" href="<?= url('/merchant/products') ?>">My products</a>
    <a class="btn btn-sm" href="<?= url('/merchant/payouts') ?>">Payouts</a>
    <a class="btn btn-sm btn-primary" href="<?= url('/merchant/products/new') ?>">+ Add product</a>
  </div>

  <div class="stat-row">
    <div class="stat"><b><?= (int) $stats['active'] ?></b><span>active plans</span></div>
    <div class="stat stat-money"><b><?= ghs((int) $stats['in_escrow']) ?></b><span>in escrow, coming to you</span></div>
    <div class="stat"><b><?= (int) $stats['completed'] ?></b><span>completed plans</span></div>
    <div class="stat"><b><?= (int) $stats['products'] ?></b><span>products listed</span></div>
  </div>

  <h2 style="font-size:1.35rem" class="mb-2">Customer plans</h2>
  <?php if (empty($plans)): ?>
    <p class="muted">No plans yet. Once a customer starts paying for one of your products, it shows up here.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data">
        <thead>
          <tr><th>Customer</th><th>Product</th><th>Progress</th><th>Paid so far</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($plans as $p): ?>
            <?php $paidAmt = (int) $p['installments_paid'] * (int) $p['installment_pesewas']; ?>
            <tr>
              <td><?= e($p['customer_name']) ?><br><span class="muted small"><?= e(pretty_phone($p['customer_phone'])) ?></span></td>
              <td><?= e($p['product_name']) ?></td>
              <td class="nowrap"><?= (int) $p['installments_paid'] ?> / <?= (int) $p['installments_total'] ?> &middot; <?= ghs((int) $p['installment_pesewas']) ?>/wk</td>
              <td><?= ghs($paidAmt) ?></td>
              <td>
                <span class="tag tag-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                <?php if ($p['grace_state'] === 'flagged'): ?><span class="tag tag-flagged">stalled</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
