<?php
/**
 * Reviews on a product page. Expects: $product, $reviews, $reviewSummary,
 * $ratingBars, $myReview (?array), $reportedIds (list<int>), $isBuyer (bool).
 */
use App\Core\Auth;
use App\Core\Csrf;
use App\Models\Review;

$pid = (int) $product['id'];
$uid = Auth::userId();
$reportedIds = $reportedIds ?? [];
$statusNote = [
    'pending' => ['schedule', 'Waiting for approval — only you can see it for now.'],
    'rejected' => ['block', 'Not published.'],
];
?>
<section class="wrap reviews-section" id="reviews">
  <div class="reviews-head">
    <h2>What buyers say</h2>
  </div>

  <div class="reviews-grid">
    <div class="reviews-list">
      <?php if (($reviewSummary['count'] ?? 0) > 0): ?>
        <div class="rating-summary">
          <div class="rating-summary-score">
            <span class="score-big"><?= number_format((float) $reviewSummary['avg'], 1) ?></span>
            <?= stars((float) $reviewSummary['avg'], 20) ?>
            <span class="small muted"><?= (int) $reviewSummary['count'] ?> review<?= $reviewSummary['count'] === 1 ? '' : 's' ?></span>
          </div>
          <ul class="rating-bars">
            <?php foreach (($ratingBars ?? []) as $star => $n): ?>
              <?php $pct = (int) round($n * 100 / max(1, (int) $reviewSummary['count'])); ?>
              <li><span class="rb-star"><?= $star ?> <?= micon('star', ['size' => 14, 'fill' => true]) ?></span>
                <span class="rb-track"><span class="rb-fill" style="width:<?= $pct ?>%"></span></span>
                <span class="rb-n"><?= (int) $n ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (empty($reviews)): ?>
        <div class="empty-card" style="text-align:left">
          <h3 style="font-size:1.05rem;margin:0 0 .3rem">No reviews yet</h3>
          <p class="muted" style="margin:0">Be the first to tell people how this went.</p>
        </div>
      <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
          <?php $mine = $uid !== null && (int) $rev['user_id'] === $uid; ?>
          <article class="review<?= $mine ? ' is-mine' : '' ?>">
            <div class="review-top">
              <span class="review-name"><?= e($rev['user_name']) ?><?= $mine ? ' <span class="muted">(you)</span>' : '' ?></span>
              <?php if (!empty($rev['verified_purchase'])): ?>
                <span class="verified-badge" title="Bought this on a PaySmallSmall plan"><?= micon('verified', ['size' => 13, 'fill' => true]) ?> Verified purchase</span>
              <?php endif; ?>
              <span class="review-date small muted"><?= e(date('j M Y', strtotime((string) $rev['created_at']))) ?><?= !empty($rev['updated_at']) ? ' · edited' : '' ?></span>
            </div>
            <div class="review-stars"><?= stars((float) $rev['rating'], 16) ?></div>
            <?php if ($mine && isset($statusNote[$rev['status']])): ?>
              <p class="review-status is-<?= e($rev['status']) ?>"><?= micon($statusNote[$rev['status']][0], ['size' => 16]) ?>
                <?= e($statusNote[$rev['status']][1]) ?><?= $rev['moderation_note'] !== '' ? ' ' . e($rev['moderation_note']) : '' ?></p>
            <?php endif; ?>
            <?php if (trim((string) $rev['body']) !== ''): ?>
              <p class="review-body"><?= nl2br(e($rev['body'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($rev['photos'])): ?>
              <div class="review-photos">
                <?php foreach ($rev['photos'] as $ph): ?>
                  <a class="review-photo<?= $ph['status'] !== 'approved' ? ' is-pending' : '' ?>" href="<?= media_url($ph['path']) ?>" target="_blank" rel="noopener">
                    <?= picture($ph['path'], 'Photo from ' . $rev['user_name'] . "'s review") ?>
                    <?php if ($ph['status'] !== 'approved'): ?><span>Being checked</span><?php endif; ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!$mine && $rev['status'] === 'approved'): ?>
              <?php if (in_array((int) $rev['id'], $reportedIds, true)): ?>
                <p class="review-reported small muted"><?= micon('flag', ['size' => 14]) ?> You reported this review</p>
              <?php else: ?>
                <details class="review-report">
                  <summary><?= micon('flag', ['size' => 15]) ?> Report</summary>
                  <form method="post" action="<?= url('/review/' . (int) $rev['id'] . '/report') ?>">
                    <?= Csrf::field() ?>
                    <p class="small muted">What's wrong with this review?</p>
                    <?php foreach (Review::REPORT_REASONS as $key => $label): ?>
                      <label class="check-line"><input type="radio" name="reason" value="<?= e($key) ?>" required> <?= e($label) ?></label>
                    <?php endforeach; ?>
                    <input type="text" name="note" maxlength="255" placeholder="Anything else we should know? (optional)">
                    <button class="btn btn-sm btn-danger" type="submit">Send report</button>
                  </form>
                </details>
              <?php endif; ?>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <aside class="review-form-card">
      <?php if ($uid === null): ?>
        <h3>Bought this? Say your piece.</h3>
        <p class="small muted">Log in to leave a review.</p>
        <a class="btn btn-outline btn-block" href="<?= url('/login') ?>">Log in to review</a>
      <?php else: ?>
        <h3><?= $myReview ? 'Edit your review' : 'Leave a review' ?></h3>
        <?php if (!$myReview): ?>
          <p class="small muted mb-1"><?= !empty($isBuyer)
              ? 'You bought this here, so your review goes up straight away with a Verified purchase badge.'
              : 'You haven\'t bought this here yet, so we check your review before it shows.' ?></p>
        <?php endif; ?>
        <form method="post" action="<?= url('/product/' . $pid . '/review') ?>" enctype="multipart/form-data">
          <?= Csrf::field() ?>
          <?php $cur = (int) ($myReview['rating'] ?? 0); ?>
          <div class="star-input" role="radiogroup" aria-label="Your rating">
            <?php for ($s = 5; $s >= 1; $s--): ?>
              <input type="radio" id="star-<?= $s ?>" name="rating" value="<?= $s ?>" <?= $cur === $s ? 'checked' : '' ?> required>
              <label for="star-<?= $s ?>" title="<?= $s ?> star<?= $s === 1 ? '' : 's' ?>"><?= micon('star', ['size' => 30, 'fill' => true]) ?></label>
            <?php endfor; ?>
          </div>
          <div class="field">
            <label for="review-body">Your review <span class="muted">(optional)</span></label>
            <textarea id="review-body" name="body" rows="4" maxlength="600" placeholder="How was the shop? Did the item match? Would you buy again?"><?= e($myReview['body'] ?? '') ?></textarea>
          </div>

          <?php $have = $myReview['photos'] ?? []; ?>
          <?php if ($have): ?>
            <div class="field">
              <label>Your photos</label>
              <div class="review-photo-manage">
                <?php foreach ($have as $ph): ?>
                  <label class="img-manage-item">
                    <?= picture($ph['path'], 'Your review photo') ?>
                    <span class="img-remove"><input type="checkbox" name="remove_photos[]" value="<?= (int) $ph['id'] ?>"><span><?= micon('delete', ['size' => 15]) ?> Remove</span></span>
                    <?php if ($ph['status'] !== 'approved'): ?><span class="img-pending">Being checked</span><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
          <?php if (count($have) < Review::MAX_PHOTOS): ?>
            <div class="field">
              <label for="review-photos">Add photos <span class="muted">(optional, up to <?= Review::MAX_PHOTOS - count($have) ?>)</span></label>
              <input id="review-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-image-input>
              <p class="field-hint">Show the real item. We remove location data from photos, and check each one before it shows.</p>
              <div class="img-preview" data-image-preview aria-live="polite"></div>
            </div>
          <?php endif; ?>
          <button class="btn btn-primary btn-block" type="submit"><?= $myReview ? 'Update review' : 'Post review' ?></button>
        </form>
        <?php if ($myReview): ?>
          <form method="post" action="<?= url('/product/' . $pid . '/review/delete') ?>" class="mt-1" data-confirm="Delete your review and its photos?">
            <?= Csrf::field() ?>
            <button class="btn btn-quiet btn-sm btn-block" type="submit"><?= micon('delete', ['size' => 16]) ?> Delete my review</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </aside>
  </div>
</section>
