<?php use App\Core\Csrf; ?>
<section class="page-head wrap">
  <h1>Admin</h1>
  <p>Payments mode: <span class="mode-banner"><?= e($mode) ?></span></p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin/plans') ?>">All plans</a>
    <a class="btn btn-sm" href="<?= url('/admin/ledger') ?>">Ledger &amp; SMS</a>
  </div>

  <!-- SMS integration -->
  <div class="card mb-3" style="max-width:760px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap">
      <div>
        <h2 style="font-size:1.2rem;color:var(--primary)"><?= micon('sms', ['size' => 20, 'fill' => true]) ?> SMS (Moolre)</h2>
        <p class="muted small mt-1">Sends via <span class="mono"><?= e($sms['endpoint']) ?></span></p>
      </div>
      <div style="text-align:right">
        <?php if ($sms['live'] && $sms['has_key']): ?>
          <span class="tag tag-active"><?= micon('check_circle', ['size' => 14, 'fill' => true]) ?> Live</span>
        <?php elseif (!$sms['has_key']): ?>
          <span class="tag tag-flagged"><?= micon('warning', ['size' => 14]) ?> No VAS key</span>
        <?php else: ?>
          <span class="tag tag-pending"><?= micon('schedule', ['size' => 14]) ?> Mock (logging only)</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="stack-gap mt-2" style="gap:.4rem">
      <div class="pay-item"><span class="muted small">Sender ID</span><span class="mono"><?= e($sms['sender']) ?></span></div>
      <div class="pay-item"><span class="muted small">VAS key</span><span class="mono"><?= $sms['has_key'] ? 'configured' : 'missing' ?></span></div>
    </div>

    <div class="perf"></div>

    <h3 style="font-size:1rem" class="mb-1">Send a test SMS</h3>
    <p class="field-hint mb-2">Hits the real Moolre API right now (even in mock mode) so you can confirm delivery. Use your own number.</p>
    <form method="post" action="<?= url('/admin/test-sms') ?>">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="sms_phone">Phone</label>
        <input id="sms_phone" name="phone" type="tel" required placeholder="024 XXX XXXX">
      </div>
      <div class="field">
        <label for="sms_message">Message (max 160 chars)</label>
        <input id="sms_message" name="message" type="text" maxlength="160"
               value="PaySmallSmall test: your SMS setup is working." >
      </div>
      <button class="btn btn-primary" type="submit"><?= micon('send', ['size' => 18]) ?> Send test SMS</button>
    </form>
  </div>

  <h2 style="font-size:1.35rem" class="mb-2">Merchants</h2>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>Shop</th><th>Owner</th><th>Phone</th><th>Location</th><th>Payout</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($merchants as $m): ?>
          <tr>
            <td><?= e($m['shop_name']) ?></td>
            <td><?= e($m['owner_name']) ?></td>
            <td><?= e(pretty_phone($m['phone'])) ?></td>
            <td><?= e($m['location']) ?></td>
            <td><?= e($m['payout_channel']) ?> &middot; <?= e(pretty_phone($m['payout_number'])) ?></td>
            <td><span class="tag tag-<?= $m['status'] === 'approved' ? 'completed' : ($m['status'] === 'pending' ? 'pending' : 'cancelled') ?>"><?= e($m['status']) ?></span></td>
            <td>
              <?php if ($m['status'] === 'pending'): ?>
                <form class="inline-form" method="post" action="<?= url('/admin/merchant/' . $m['id'] . '/approve') ?>">
                  <?= Csrf::field() ?>
                  <button class="btn btn-sm btn-green" type="submit">Approve</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
