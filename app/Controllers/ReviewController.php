<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Product;
use App\Models\Review;
use App\Services\ImageUpload;

final class ReviewController extends Controller
{
    /**
     * A signed-in customer leaves (or updates) their review of a product:
     * stars, words, and up to MAX_PHOTOS photos (each checked by an admin).
     */
    public function store(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();

        $product = Product::find((int) $id);
        if (!$product || !$product['active'] || $product['merchant_status'] !== 'approved') {
            flash('error', 'That product is no longer on sale.');
            redirect('/shop');
        }
        $back = '/product/' . (int) $product['id'] . '#reviews';

        $rating = (int) ($_POST['rating'] ?? 0);
        $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 600);
        if ($rating < 1 || $rating > 5) {
            flash('error', 'Pick a star rating from 1 to 5.');
            redirect($back);
        }

        $before = Review::byUser((int) $product['id'], (int) $user['id']);
        $newFiles = $this->uploadedFiles();
        $removing = array_map('intval', (array) ($_POST['remove_photos'] ?? []));
        $keeping = $before ? count(array_filter($before['photos'], static fn (array $p): bool => !in_array((int) $p['id'], $removing, true))) : 0;
        if ($keeping + count($newFiles) > Review::MAX_PHOTOS) {
            flash('error', 'You can add up to ' . Review::MAX_PHOTOS . ' photos to a review. Remove some and try again.');
            redirect($back);
        }

        [$reviewId, $status] = Review::save((int) $product['id'], (int) $user['id'], $rating, $body);

        // Photos the customer ticked to remove (only their own review's photos).
        foreach ($removing as $photoId) {
            $path = Review::deletePhoto($photoId, $reviewId);
            if ($path !== null) {
                ImageUpload::delete($path);
            }
        }
        // New photos: cleaned of camera/location data, then held for an admin.
        $added = 0;
        $skipped = 0;
        foreach ($newFiles as [$tmp, $size]) {
            $path = ImageUpload::store($tmp, $size, 'reviews', 'r' . $reviewId);
            if ($path === null) {
                $skipped++;
                continue;
            }
            Review::addPhoto($reviewId, $path);
            $added++;
        }

        $msg = $status === 'approved'
            ? ($before ? 'Review updated — it\'s live.' : 'Thanks! Your review is live.')
            : (($before['status'] ?? '') === 'rejected'
                ? 'Thanks — your edited review goes back to our team, and shows once it\'s approved.'
                : 'Thanks! We check reviews from people who haven\'t bought here yet. Yours shows once it\'s approved.');
        if ($added > 0) {
            $msg .= ' Your photo' . ($added === 1 ? '' : 's') . ' will show once we\'ve checked ' . ($added === 1 ? 'it' : 'them') . '.';
        }
        if ($skipped > 0) {
            $msg .= ' ' . $skipped . ' file' . ($skipped === 1 ? '' : 's') . ' couldn\'t be used — photos must be JPG, PNG or WebP under 5MB.';
        }
        flash('success', $msg);
        redirect($back);
    }

    /** The author deletes their own review (and its photos). */
    public function destroy(string $id): void
    {
        $user = $this->requireUser();
        Csrf::check();
        $review = Review::byUser((int) $id, (int) $user['id']);
        if ($review) {
            foreach (Review::delete((int) $review['id']) as $path) {
                ImageUpload::delete($path);
            }
            flash('success', 'Your review was deleted.');
        }
        redirect('/product/' . (int) $id . '#reviews');
    }

    /** Report someone else's review to the admins. */
    public function report(string $id): void
    {
        $review = Review::find((int) $id);
        $back = $review ? '/product/' . (int) $review['product_id'] . '#reviews' : '/shop';
        if (Auth::userId() === null) {
            $_SESSION['after_login'] = $back;
            flash('error', 'Log in to report a review — it stops the same person reporting again and again.');
            redirect('/login');
        }
        Csrf::check();
        if (!$review || $review['status'] !== 'approved') {
            redirect($back);
        }
        if ((int) $review['user_id'] === Auth::userId()) {
            flash('error', 'That\'s your own review — edit or delete it instead.');
            redirect($back);
        }
        $reason = (string) ($_POST['reason'] ?? '');
        if (!isset(Review::REPORT_REASONS[$reason])) {
            flash('error', 'Pick what\'s wrong with the review.');
            redirect($back);
        }
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);

        $result = Review::report((int) $review['id'], (int) Auth::userId(), $reason, $note);
        flash('success', match ($result) {
            'already' => 'You\'ve already reported this review. Our team will look at it.',
            'hidden' => 'Thanks for telling us. Other customers flagged it too, so it\'s hidden until our team checks it.',
            default => 'Thanks for telling us. Our team will check this review.',
        });
        redirect($back);
    }

    /** Files from photos[] that actually arrived: list of [tmp path, size]. Max MAX_PHOTOS looked at. */
    private function uploadedFiles(): array
    {
        $f = $_FILES['photos'] ?? null;
        if (!$f || !is_array($f['tmp_name'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($f['tmp_name'] as $i => $tmp) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && count($out) < Review::MAX_PHOTOS + 1) {
                $out[] = [(string) $tmp, (int) ($f['size'][$i] ?? 0)];
            }
        }
        return $out;
    }
}
