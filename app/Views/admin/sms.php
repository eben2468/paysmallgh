<?php
use App\Core\Csrf;

// Map an SMS status to a pill class + icon + label.
$smsTag = static function (string $status): array {
    return match ($status) {
        'delivered' => ['tag-completed', 'mark_email_read', 'Delivered'],
        'sent'      => ['tag-pending', 'send', 'Sent'],
        'failed'    => ['tag-flagged', 'error', 'Failed'],
        default     => ['tag-pending', 'schedule', ucfirst($status)], // queued
    };
};
?>
<div class="pg-head">
  <div>
    <h1>SMS log</h1>
    <p>Every text we've sent &mdash; receipts, reminders and payout notices. <?= $smsLive ? 'Messages go out for real through Moolre.' : 'SMS is in mock mode, so messages are logged here but not sent.' ?></p>
  </div>
  <div class="pg-actions">
    <form class="inline-form" method="post" action="<?= url('/admin/poll-sms') ?>">
      <?= Csrf::field() ?>
      <button class="btn btn-sm btn-ghost" type="submit"><?= micon('sync', ['size' => 18]) ?> Check delivery status</button>
    </form>
    <a class="btn btn-sm btn-primary" href="<?= url('/admin/system#test-sms') ?>"><?= micon('send', ['size' => 18]) ?> Send a test SMS</a>
  </div>
</div>

<section class="panel">
  <?php if (empty($sms)): ?>
    <div class="panel-empty"><?= micon('sms') ?>No messages yet.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>When</th><th>To</th><th>Message</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach ($sms as $s): ?>
            <?php [$cls, $ic, $label] = $smsTag($s['status']); ?>
            <tr>
              <td class="small nowrap"><?= e(when($s['created_at'])) ?></td>
              <td class="small mono nowrap"><?= e(pretty_phone($s['recipient'])) ?></td>
              <td style="white-space:normal;min-width:18rem"><?= e($s['body']) ?><span class="cell-sub mono"><?= e($s['provider_ref'] ?: '—') ?></span></td>
              <td><span class="tag <?= $cls ?>"><?= micon($ic, ['size' => 14]) ?> <?= e($label) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
