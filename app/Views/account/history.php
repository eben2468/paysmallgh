<div class="wrap account-layout">
  <?= (new App\Core\View())->partial('partials/account-nav', ['tab' => $tab, 'user' => $user]) ?>

  <div class="account-main">
    <div class="account-head">
      <h1>Payment history</h1>
      <p>Every payment you made and every refund we sent back. Each line has the reference to quote if you ever call us.</p>
    </div>

    <div class="account-stats account-stats-2">
      <div><b><?= ghs($paid) ?></b><span>paid in</span></div>
      <div><b><?= ghs($refunded) ?></b><span>refunded to you</span></div>
    </div>

    <?php if (!$rows): ?>
      <div class="empty-card">
        <h2>No payments yet</h2>
        <p>Your receipts will show up here after your first payment.</p>
        <a class="btn btn-primary" href="<?= url('/shop') ?>">Find something to pay for</a>
      </div>
    <?php else: ?>
      <section class="panel">
        <ul class="history-list">
          <?php foreach ($rows as $r): ?>
            <?php
              $refund = $r['type'] === 'refund';
              $pending = $r['status'] === 'pending';
              $what = $r['frequency'] === 'once' ? 'Paid in full'
                  : ($r['installment_number'] ? 'Payment ' . (int) $r['installment_number'] . ' of ' . (int) $r['installments_total'] : 'Payment');
              $item = plan_item(['product_name' => $r['product_name'], 'variant_label' => $r['variant_label'], 'quantity' => $r['quantity']]);
            ?>
            <li>
              <span class="hist-ic<?= $refund ? ' is-refund' : '' ?><?= $pending ? ' is-pending' : '' ?>"><?= micon($refund ? 'undo' : ($pending ? 'schedule' : 'payments'), ['size' => 20, 'fill' => !$pending]) ?></span>
              <span class="hist-body">
                <a href="<?= url('/plan/' . (int) $r['plan_id']) ?>"><b><?= e($item) ?></b></a>
                <small><?= $refund ? 'Refund' . ($r['installment_number'] ? ' of payment ' . (int) $r['installment_number'] : '') : e($what) ?> · <?= e($r['shop_name']) ?> · <?= e(when((string) ($r['updated_at'] ?? $r['created_at']))) ?></small>
                <small class="mono">Ref <?= e($r['provider_ref']) ?></small>
              </span>
              <span class="hist-amt<?= $refund ? ' is-refund' : '' ?>">
                <?= $refund ? '+' : '' ?><?= ghs((int) $r['amount_pesewas']) ?>
                <?php if ($pending): ?><small>processing</small><?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
  </div>
</div>
