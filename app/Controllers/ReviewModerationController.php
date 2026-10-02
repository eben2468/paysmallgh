<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Review;
use App\Services\ImageUpload;

/** Admin: approve, reject or delete reviews, check photos, deal with reports. */
final class ReviewModerationController extends Controller
{
    private const TABS = ['queue', 'reported', 'approved', 'rejected', 'all'];

    public function index(): void
    {
        $this->requireAdmin();
        $tab = in_array($_GET['tab'] ?? '', self::TABS, true) ? (string) $_GET['tab'] : 'queue';
        $this->renderPortal('admin', 'admin/reviews', [
            'title' => 'Reviews — Admin',
            'tab' => $tab,
            'reviews' => Review::forAdmin($tab),
            'counts' => Review::moderationCounts(),
        ]);
    }

    public function approve(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        if (Review::find((int) $id)) {
            Review::approve((int) $id);
            flash('success', 'Review published.');
        }
        redirect_back('/admin/reviews');
    }

    public function reject(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 255);
        if (Review::find((int) $id)) {
            Review::reject((int) $id, $note !== '' ? $note : 'It doesn\'t meet our review guidelines.');
            flash('success', 'Review rejected. The customer sees the reason on the product page.');
        }
        redirect_back('/admin/reviews');
    }

    public function delete(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        if (Review::find((int) $id)) {
            foreach (Review::delete((int) $id) as $path) {
                ImageUpload::delete($path);
            }
            flash('success', 'Review and its photos deleted.');
        }
        redirect_back('/admin/reviews');
    }

    public function dismissReports(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        if (Review::find((int) $id)) {
            Review::dismissReports((int) $id);
            flash('success', 'Reports closed. The review stays up.');
        }
        redirect_back('/admin/reviews');
    }

    public function approvePhoto(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        if (Review::findPhoto((int) $id)) {
            Review::approvePhoto((int) $id);
            flash('success', 'Photo approved.');
        }
        redirect_back('/admin/reviews');
    }

    /** A photo that's not OK is removed for good (row and file). */
    public function rejectPhoto(string $id): void
    {
        $this->requireAdmin();
        Csrf::check();
        $path = Review::deletePhoto((int) $id);
        if ($path !== null) {
            ImageUpload::delete($path);
            flash('success', 'Photo removed.');
        }
        redirect_back('/admin/reviews');
    }
}
