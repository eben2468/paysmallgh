<?php
use App\Core\Csrf;

$int = $integrations;
$ps = $int['paystack'];
$sms = $int['sms'];
?>
<div class="pg-head">
  <div>
    <h1>System</h1>
    <p>Payment and SMS connections, plus the jobs that normally run on their own.</p>
  </div>
</div>

<div class="grid-2-even">
  <section class="panel">
    <div class="panel-head">
      <h2><?= micon('payments', ['size' => 20]) ?> Payments &middot; Paystack</h2>
      <?php if ($int['mode'] === 'mock'): ?>
        <span class="tag tag-pending">Mock</span>
      <?php elseif (!$ps['has_key']): ?>
        <span class="tag tag-flagged">No secret key</span>
      <?php elseif ($int['mode'] === 'live' && $ps['key_kind'] !== 'live'): ?>
        <span class="tag tag-flagged">Live mode, test key</span>
      <?php else: ?>
        <span class="tag tag-active"><?= e(ucfirst($int['mode'])) ?></span>
      <?php endif; ?>
    </div>
    <div class="panel-body">
      <div class="status-row"><span>Payments mode</span><span class="mono"><?= e($int['mode']) ?></span></div>
      <div class="status-row"><span>Secret key</span><span class="mono"><?= $ps['has_key'] ? e($ps['key_kind']) . ' key set' : 'missing' ?></span></div>
      <div class="status-row"><span>Webhook URL</span><span class="mono"><?= e($ps['webhook']) ?></span></div>
      <p class="small muted mt-2">
        <?php if ($int['mode'] === 'mock'): ?>
          Mock mode: payments succeed instantly and no real money moves. Set <span class="mono">PAYMENTS_MODE</span> in <span class="mono">.env</span> to go live.
        <?php else: ?>
          Paste the webhook URL into Paystack &rarr; Settings &rarr; API Keys &amp; Webhooks, and turn off the OTP for transfers so payouts go out automatically.
        <?php endif; ?>
      </p>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2><?= micon('sms', ['size' => 20]) ?> SMS &middot; Moolre</h2>
      <?php if (!$sms['has_key']): ?>
        <span class="tag tag-flagged">No VAS key</span>
      <?php elseif ($sms['live']): ?>
        <span class="tag tag-active">Live</span>
      <?php else: ?>
        <span class="tag tag-pending">Mock</span>
      <?php endif; ?>
    </div>
    <div class="panel-body">
      <div class="status-row"><span>Sender ID</span><span class="mono"><?= e($sms['sender']) ?></span></div>
      <div class="status-row"><span>VAS key</span><span class="mono"><?= $sms['has_key'] ? 'set' : 'missing' ?></span></div>
      <div class="status-row"><span>Sends to</span><span class="mono"><?= e($sms['endpoint']) ?></span></div>
    </div>
  </section>
</div>

<div class="grid-2-even section-gap">
  <section class="panel" id="test-sms">
    <div class="panel-head"><h2><?= micon('send', ['size' => 20]) ?> Send a test SMS</h2></div>
    <div class="panel-body">
      <p class="small muted mb-2">Sends a real text through Moolre right now (even in mock mode). Use your own number.</p>
      <form method="post" action="<?= url('/admin/test-sms') ?>">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="sms_phone">Phone</label>
          <input id="sms_phone" name="phone" type="tel" required placeholder="024 XXX XXXX">
        </div>
        <div class="field">
          <label for="sms_message">Message (max 160 characters)</label>
          <input id="sms_message" name="message" type="text" maxlength="160" value="PaySmallSmall test: your SMS setup is working.">
        </div>
        <button class="btn btn-primary" type="submit"><?= micon('send', ['size' => 18]) ?> Send test SMS</button>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head"><h2><?= micon('build', ['size' => 20]) ?> Run jobs now</h2></div>
    <ul class="attn-list">
      <li><div class="attn-item">
        <span class="attn-ic"><?= micon('sync', ['size' => 20]) ?></span>
        <span class="attn-text"><b>Reconcile payments</b><span>Ask Paystack about every pending payment<?= $pending > 0 ? ' (' . (int) $pending . ' now)' : '' ?>. Runs every 2 minutes by cron.</span></span>
        <form class="inline-form" method="post" action="<?= url('/admin/reconcile') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Run</button>
        </form>
      </div></li>
      <li><div class="attn-item">
        <span class="attn-ic"><?= micon('notifications', ['size' => 20]) ?></span>
        <span class="attn-text"><b>Send payment reminders</b><span>Due-soon and missed-payment texts; flags plans past grace. Runs daily.</span></span>
        <form class="inline-form" method="post" action="<?= url('/admin/run-reminders') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Run</button>
        </form>
      </div></li>
      <li><div class="attn-item">
        <span class="attn-ic"><?= micon('mark_email_read', ['size' => 20]) ?></span>
        <span class="attn-text"><b>Check SMS delivery</b><span>Update the SMS log with delivered/failed results.</span></span>
        <form class="inline-form" method="post" action="<?= url('/admin/poll-sms') ?>">
          <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Run</button>
        </form>
      </div></li>
    </ul>
  </section>
</div>
