<?php
/**
 * Small one-column auth card (verify / forgot / reset). Expects:
 *   $eyebrow, $heading, $sub (plain text), $body (HTML of the form area),
 *   $demoCode (?string, shown only when SMS is in mock/demo mode).
 */
?>
<section class="auth wrap">
  <div class="auth-card auth-card-narrow">
    <div class="auth-form">
      <span class="auth-eyebrow auth-eyebrow-dark"><?= e($eyebrow) ?></span>
      <h1><?= e($heading) ?></h1>
      <p class="sub"><?= e($sub) ?></p>
      <?php if (!empty($demoCode)): ?>
        <p class="demo-code"><?= micon('science', ['size' => 18]) ?> Demo mode — no real SMS is sent. Your code is <b class="mono"><?= e($demoCode) ?></b></p>
      <?php endif; ?>
      <?= $body ?>
    </div>
  </div>
</section>
