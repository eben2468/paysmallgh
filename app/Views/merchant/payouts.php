<section class="page-head wrap">
  <h1>Payouts</h1>
  <p>Every pesewa we've sent you, newest first. Payouts go to <?= e($merchant['payout_channel'] === 'bank' ? 'your bank account' : 'MoMo') ?> <?= e(pretty_phone($merchant['payout_number'])) ?>.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/merchant/dashboard') ?>">&larr; Dashboard</a>
  </div>

  <?php if (empty($payouts)): ?>
    <p class="muted">No payouts yet. The first one lands when a customer completes their plan.</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data">
        <thead><tr><th>Date</th><th>Product</th><th>Amount</th><th>Reference</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($payouts as $t): ?>
            <tr>
              <td><?= date('j M Y', strtotime($t['created_at'])) ?></td>
              <td><?= e($t['product_name'] ?? '—') ?></td>
              <td><strong><?= ghs((int) $t['amount_pesewas']) ?></strong></td>
              <td class="small muted"><?= e($t['provider_ref']) ?></td>
              <td><span class="tag tag-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
