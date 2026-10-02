<?php
use App\Core\Csrf;
use App\Models\Review;

$tabs = [
    'queue' => ['Waiting', $counts['pending'] + $counts['photos']],
    'reported' => ['Reported', $counts['reported']],
    'approved' => ['Published', $counts['approved']],
    'rejected' => ['Rejected', $counts['rejected']],
    'all' => ['All', $counts['total']],
];
$empty = [
    'queue' => 'Nothing waiting. New reviews from non-buyers and every new photo land here.',
    'reported' => 'No open reports.',
    'approved' => 'No published reviews yet.',
    'rejected' => 'No rejected reviews.',
    'all' => 'No reviews yet.',
];
?>
<div class="pg-head">
  <div>
    <h1>Reviews</h1>
    <p>Verified buyers' reviews go live straight away; everyone else's wait here. Every photo is checked before it shows. A review reported by <?= Review::REPORT_HIDE_AT ?> customers is hidden until you decide.</p>
  </div>
</div>

<nav class="tabs" aria-label="Filter reviews">
  <?php foreach ($tabs as $key => [$label, $n]): ?>
    <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= url('/admin/reviews' . ($key === 'queue' ? '' : '?tab=' . $key)) ?>">
      <?= e($label) ?> <span class="tab-count"><?= (int) $n ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$reviews): ?>
  <section class="panel"><div class="panel-empty"><?= micon('reviews') ?><p><?= e($empty[$tab]) ?></p></div></section>
<?php endif; ?>

<div class="mod-list">
  <?php foreach ($reviews as $r): ?>
    <?php $pendingPhotos = count(array_filter($r['photos'], static fn (array $p): bool => $p['status'] === 'pending')); ?>
    <article class="panel mod-card">
      <div class="mod-top">
        <div>
          <a class="cell-main" href="<?= product_url($r) ?>#reviews" target="_blank" rel="noopener"><?= e($r['product_name']) ?></a>
          <span class="cell-sub"><?= e($r['shop_name']) ?></span>
        </div>
        <div class="mod-tags">
          <?= status_tag($r['status'], ['pending' => 'Waiting', 'approved' => 'Published', 'rejected' => 'Rejected'][$r['status']] ?? null) ?>
          <?php if ($pendingPhotos): ?><span class="tag tag-pending"><?= $pendingPhotos ?> photo<?= $pendingPhotos === 1 ? '' : 's' ?> to check</span><?php endif; ?>
          <?php if ($r['reports']): ?><span class="tag tag-flagged"><?= micon('flag', ['size' => 13]) ?> <?= count($r['reports']) ?> report<?= count($r['reports']) === 1 ? '' : 's' ?></span><?php endif; ?>
        </div>
      </div>

      <div class="mod-body">
        <p class="mod-who">
          <a href="<?= url('/admin/user/' . (int) $r['user_id']) ?>"><b><?= e($r['user_name']) ?></b></a>
          <span class="mono small"><?= e(pretty_phone((string) $r['user_phone'])) ?></span>
          <?php if ($r['verified_purchase']): ?>
            <span class="verified-badge"><?= micon('verified', ['size' => 13, 'fill' => true]) ?> Verified purchase</span>
          <?php else: ?>
            <span class="tag">Hasn't bought it here</span>
          <?php endif; ?>
          <span class="small muted"><?= e(when((string) $r['created_at'])) ?><?= $r['updated_at'] ? ' · edited ' . e(when((string) $r['updated_at'])) : '' ?></span>
        </p>
        <?= stars((float) $r['rating'], 16) ?>
        <?php if (trim((string) $r['body']) !== ''): ?>
          <p class="mod-text"><?= nl2br(e($r['body'])) ?></p>
        <?php else: ?>
          <p class="mod-text muted">(stars only, no words)</p>
        <?php endif; ?>
        <?php if ($r['moderation_note'] !== ''): ?>
          <p class="small muted"><?= micon('sticky_note_2', ['size' => 14]) ?> Note shown to the customer: <?= e($r['moderation_note']) ?></p>
        <?php endif; ?>

        <?php if ($r['photos']): ?>
          <div class="mod-photos">
            <?php foreach ($r['photos'] as $ph): ?>
              <figure class="mod-photo<?= $ph['status'] === 'pending' ? ' is-pending' : '' ?>">
                <a href="<?= media_url($ph['path']) ?>" target="_blank" rel="noopener"><?= picture($ph['path'], 'Review photo') ?></a>
                <figcaption>
                  <?php if ($ph['status'] === 'pending'): ?>
                    <form class="inline-form" method="post" action="<?= url('/admin/review-photo/' . (int) $ph['id'] . '/approve') ?>">
                      <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit" aria-label="Approve photo"><?= micon('check', ['size' => 16]) ?></button>
                    </form>
                  <?php else: ?>
                    <span class="small muted">Live</span>
                  <?php endif; ?>
                  <form class="inline-form" method="post" action="<?= url('/admin/review-photo/' . (int) $ph['id'] . '/reject') ?>" data-confirm="Remove this photo for good?">
                    <?= Csrf::field() ?><button class="btn btn-sm btn-danger" type="submit" aria-label="Remove photo"><?= micon('delete', ['size' => 16]) ?></button>
                  </form>
                </figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($r['reports']): ?>
          <ul class="mod-reports">
            <?php foreach ($r['reports'] as $rep): ?>
              <li><?= micon('flag', ['size' => 15]) ?> <b><?= e(Review::REPORT_REASONS[$rep['reason']] ?? $rep['reason']) ?></b>
                <?= $rep['note'] !== '' ? '— “' . e($rep['note']) . '”' : '' ?>
                <span class="small muted">· <?= e($rep['reporter']) ?>, <?= e(when((string) $rep['created_at'])) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="mod-actions">
        <?php if ($r['status'] !== 'approved'): ?>
          <form class="inline-form" method="post" action="<?= url('/admin/review/' . (int) $r['id'] . '/approve') ?>">
            <?= Csrf::field() ?><button class="btn btn-sm btn-green" type="submit"><?= micon('check', ['size' => 16]) ?> Publish</button>
          </form>
        <?php endif; ?>
        <?php if ($r['reports'] && $r['status'] !== 'rejected'): ?>
          <form class="inline-form" method="post" action="<?= url('/admin/review/' . (int) $r['id'] . '/dismiss-reports') ?>">
            <?= Csrf::field() ?><button class="btn btn-sm btn-ghost" type="submit">Reports are wrong — keep it</button>
          </form>
        <?php endif; ?>
        <?php if ($r['status'] !== 'rejected'): ?>
          <details class="mod-reject">
            <summary class="btn btn-sm btn-ghost"><?= micon('block', ['size' => 16]) ?> Reject</summary>
            <form method="post" action="<?= url('/admin/review/' . (int) $r['id'] . '/reject') ?>">
              <?= Csrf::field() ?>
              <input type="text" name="note" maxlength="255" placeholder="Reason the customer will see, e.g. Please keep it about the product">
              <button class="btn btn-sm btn-danger" type="submit">Reject review</button>
            </form>
          </details>
        <?php endif; ?>
        <form class="inline-form" method="post" action="<?= url('/admin/review/' . (int) $r['id'] . '/delete') ?>" data-confirm="Delete this review and its photos for good? The customer can write a new one.">
          <?= Csrf::field() ?><button class="btn btn-sm btn-quiet" type="submit"><?= micon('delete', ['size' => 16]) ?> Delete</button>
        </form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
