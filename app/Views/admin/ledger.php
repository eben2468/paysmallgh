<section class="page-head wrap">
  <h1>Ledger</h1>
  <p>Append-only record of every money movement, plus the SMS log.</p>
</section>

<section class="wrap" style="padding-bottom:3rem">
  <div class="admin-nav">
    <a class="btn btn-sm" href="<?= url('/admin') ?>">&larr; Admin home</a>
  </div>

  <h2 style="font-size:1.35rem" class="mb-2">Transactions</h2>
  <div class="table-scroll mb-3">
    <table class="data">
      <thead><tr><th>#</th><th>When</th><th>Type</th><th>Amount</th><th>Phone</th><th>Plan</th><th>Our ref</th><th>Provider ref</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($transactions as $t): ?>
          <tr>
            <td><?= (int) $t['id'] ?></td>
            <td class="small"><?= date('j M H:i', strtotime($t['created_at'])) ?></td>
            <td><?= e($t['type']) ?></td>
            <td><strong><?= ghs((int) $t['amount_pesewas']) ?></strong></td>
            <td class="small"><?= e(pretty_phone($t['phone'])) ?></td>
            <td><?= $t['plan_id'] ? '#' . (int) $t['plan_id'] : '—' ?></td>
            <td class="small muted"><?= e($t['provider_ref']) ?></td>
            <td class="small muted"><?= e($t['external_ref']) ?></td>
            <td><span class="tag tag-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php
    // Map an SMS status to a pill class + icon + label.
    $smsTag = static function (string $status): array {
        return match ($status) {
            'delivered' => ['tag-completed', 'mark_email_read', 'delivered'],
            'sent'      => ['tag-pending', 'send', 'sent · awaiting delivery'],
            'failed'    => ['tag-flagged', 'error', 'failed'],
            default     => ['tag-pending', 'schedule', $status], // queued
        };
    };
  ?>
  <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap" class="mb-2">
    <h2 style="font-size:1.35rem">SMS log</h2>
    <form method="post" action="<?= url('/admin/poll-sms') ?>">
      <?= \App\Core\Csrf::field() ?>
      <button class="btn btn-sm" type="submit"><?= micon('sync', ['size' => 16]) ?> Check delivery status</button>
    </form>
  </div>
  <div class="table-scroll">
    <table class="data">
      <thead><tr><th>#</th><th>When</th><th>To</th><th>Message</th><th>Ref</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($sms as $s): ?>
          <?php [$cls, $ic, $label] = $smsTag($s['status']); ?>
          <tr>
            <td><?= (int) $s['id'] ?></td>
            <td class="small"><?= date('j M H:i', strtotime($s['created_at'])) ?></td>
            <td class="small"><?= e(pretty_phone($s['recipient'])) ?></td>
            <td style="white-space:normal; min-width:20rem"><?= e($s['body']) ?></td>
            <td class="small muted mono"><?= e($s['provider_ref'] ?: '—') ?></td>
            <td><span class="tag <?= $cls ?>"><?= micon($ic, ['size' => 14]) ?> <?= e($label) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
