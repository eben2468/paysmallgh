<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * Product reviews, their photos and reports.
 *
 * Publishing rules:
 *  - A review shows (and counts in ratings) only when status = 'approved'.
 *  - Verified buyers (an active or finished plan on the product) are
 *    published straight away; anyone else waits for an admin.
 *  - Every photo waits for an admin, one by one.
 *  - A review reported by REPORT_HIDE_AT different customers is hidden
 *    until an admin decides.
 */
final class Review
{
    public const MAX_PHOTOS = 4;
    public const REPORT_HIDE_AT = 3;
    public const REPORT_REASONS = [
        'spam' => 'Spam or advertising',
        'offensive' => 'Rude, hateful or abusive',
        'fake' => 'Fake or not about a real purchase',
        'off_topic' => 'Not about this product',
        'private_info' => 'Shares someone\'s private details',
        'other' => 'Something else',
    ];

    /** Did this customer actually buy it on PaySmallSmall (a plan that started)? */
    public static function isVerifiedBuyer(int $productId, int $userId): bool
    {
        return (bool) DB::run(
            "SELECT 1 FROM plans WHERE product_id = ? AND customer_id = ? AND status IN ('active','completed') LIMIT 1",
            [$productId, $userId]
        )->fetchColumn();
    }

    private const VERIFIED_SQL = "EXISTS(SELECT 1 FROM plans pl WHERE pl.product_id = r.product_id AND pl.customer_id = r.user_id
                                         AND pl.status IN ('active','completed'))";

    /**
     * Reviews to show on a product page, newest first: the published ones,
     * plus — for the viewer only — their own review whatever its state.
     * Each row carries `photos` (approved ones; plus pending ones on the
     * viewer's own review) and `verified_purchase`.
     */
    public static function forProduct(int $productId, ?int $viewerId = null): array
    {
        $rows = DB::run(
            'SELECT r.*, u.name AS user_name, ' . self::VERIFIED_SQL . ' AS verified_purchase
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ? AND (r.status = \'approved\' OR r.user_id = ?)
             ORDER BY (r.user_id = ?) DESC, r.created_at DESC',
            [$productId, $viewerId ?? 0, $viewerId ?? 0]
        )->fetchAll();
        return self::attachPhotos($rows, $viewerId);
    }

    /** Average rating (1 dp) and count — published reviews only. */
    public static function summary(int $productId): array
    {
        $row = DB::run(
            "SELECT COUNT(*) AS n, COALESCE(ROUND(AVG(rating), 1), 0) AS avg FROM reviews WHERE product_id = ? AND status = 'approved'",
            [$productId]
        )->fetch();
        return ['count' => (int) $row['n'], 'avg' => (float) $row['avg']];
    }

    /** How many published reviews gave each star rating: [5 => n, … 1 => n]. */
    public static function distribution(int $productId): array
    {
        $out = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $rows = DB::run("SELECT rating, COUNT(*) AS n FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating", [$productId])->fetchAll();
        foreach ($rows as $r) {
            if (isset($out[(int) $r['rating']])) {
                $out[(int) $r['rating']] = (int) $r['n'];
            }
        }
        return $out;
    }

    /** This user's review of a product (any state), with all its photos. */
    public static function byUser(int $productId, int $userId): ?array
    {
        $row = DB::run('SELECT * FROM reviews WHERE product_id = ? AND user_id = ?', [$productId, $userId])->fetch();
        if (!$row) {
            return null;
        }
        $row['photos'] = self::photos((int) $row['id']);
        return $row;
    }

    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM reviews WHERE id = ?', [$id])->fetch() ?: null;
    }

    /**
     * Create or update this customer's single review. Returns [reviewId, status].
     * A rejected or held review that's edited goes back to the admin; a
     * verified buyer's published review stays published.
     */
    public static function save(int $productId, int $userId, int $rating, string $body): array
    {
        $existing = DB::run('SELECT id, status FROM reviews WHERE product_id = ? AND user_id = ?', [$productId, $userId])->fetch();
        $verified = self::isVerifiedBuyer($productId, $userId);
        $status = $verified && (!$existing || $existing['status'] === 'approved') ? 'approved' : 'pending';

        if ($existing) {
            DB::run(
                "UPDATE reviews SET rating = ?, body = ?, status = ?, moderation_note = IF(? = 'approved', '', moderation_note), updated_at = NOW() WHERE id = ?",
                [$rating, $body, $status, $status, $existing['id']]
            );
            return [(int) $existing['id'], $status];
        }
        DB::run(
            'INSERT INTO reviews (product_id, user_id, rating, body, status) VALUES (?, ?, ?, ?, ?)',
            [$productId, $userId, $rating, $body, $status]
        );
        return [DB::lastId(), $status];
    }

    /** Kept for the demo seed: create/update a review, published straight away. */
    public static function upsert(int $productId, int $userId, int $rating, string $body): void
    {
        DB::run(
            "INSERT INTO reviews (product_id, user_id, rating, body, status) VALUES (?, ?, ?, ?, 'approved')
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), body = VALUES(body), updated_at = NOW()",
            [$productId, $userId, $rating, $body]
        );
    }

    /** Delete a review; returns the photo paths so the caller can remove the files. */
    public static function delete(int $id): array
    {
        $paths = array_column(self::photos($id), 'path');
        DB::run('DELETE FROM reviews WHERE id = ?', [$id]);
        return $paths;
    }

    /* ---------- Photos ---------- */

    public static function photos(int $reviewId): array
    {
        return DB::run('SELECT * FROM review_photos WHERE review_id = ? ORDER BY id', [$reviewId])->fetchAll();
    }

    public static function addPhoto(int $reviewId, string $path): void
    {
        DB::run('INSERT INTO review_photos (review_id, path) VALUES (?, ?)', [$reviewId, $path]);
    }

    public static function findPhoto(int $photoId): ?array
    {
        return DB::run('SELECT * FROM review_photos WHERE id = ?', [$photoId])->fetch() ?: null;
    }

    public static function approvePhoto(int $photoId): void
    {
        DB::run("UPDATE review_photos SET status = 'approved' WHERE id = ?", [$photoId]);
    }

    /** Remove one photo row; returns its path for file removal (null if not found). */
    public static function deletePhoto(int $photoId, ?int $reviewId = null): ?string
    {
        $p = self::findPhoto($photoId);
        if (!$p || ($reviewId !== null && (int) $p['review_id'] !== $reviewId)) {
            return null;
        }
        DB::run('DELETE FROM review_photos WHERE id = ?', [$photoId]);
        return (string) $p['path'];
    }

    /** Photos per review in one query: approved ones, plus pending ones on the viewer's own review. */
    private static function attachPhotos(array $rows, ?int $viewerId): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $photos = DB::run(
            'SELECT * FROM review_photos WHERE review_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id',
            $ids
        )->fetchAll();
        $by = [];
        foreach ($photos as $p) {
            $by[(int) $p['review_id']][] = $p;
        }
        foreach ($rows as &$r) {
            $own = $viewerId !== null && (int) $r['user_id'] === $viewerId;
            $r['photos'] = array_values(array_filter(
                $by[(int) $r['id']] ?? [],
                static fn (array $p): bool => $p['status'] === 'approved' || $own
            ));
        }
        unset($r);
        return $rows;
    }

    /* ---------- Reports ---------- */

    /** Ids of reviews this customer has already reported (for the product page). */
    public static function reportedBy(int $userId, int $productId): array
    {
        return array_map('intval', DB::run(
            'SELECT rr.review_id FROM review_reports rr JOIN reviews r ON r.id = rr.review_id WHERE rr.user_id = ? AND r.product_id = ?',
            [$userId, $productId]
        )->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Record a report (once per customer). Hides the review for an admin
     * to look at once enough different customers have reported it.
     * Returns 'reported' | 'already' | 'hidden'.
     */
    public static function report(int $reviewId, int $userId, string $reason, string $note): string
    {
        $added = DB::run(
            'INSERT IGNORE INTO review_reports (review_id, user_id, reason, note) VALUES (?, ?, ?, ?)',
            [$reviewId, $userId, $reason, $note]
        )->rowCount() > 0;
        if (!$added) {
            return 'already';
        }
        $open = (int) DB::run("SELECT COUNT(*) FROM review_reports WHERE review_id = ? AND status = 'open'", [$reviewId])->fetchColumn();
        if ($open >= self::REPORT_HIDE_AT) {
            $hid = DB::run(
                "UPDATE reviews SET status = 'pending', moderation_note = 'Hidden after reports from other customers — an admin will check it.'
                 WHERE id = ? AND status = 'approved'",
                [$reviewId]
            )->rowCount() > 0;
            if ($hid) {
                return 'hidden';
            }
        }
        return 'reported';
    }

    /* ---------- Admin moderation ---------- */

    /** Publish the review, clear its note and close its reports. */
    public static function approve(int $id): void
    {
        DB::run("UPDATE reviews SET status = 'approved', moderation_note = '' WHERE id = ?", [$id]);
        DB::run("UPDATE review_reports SET status = 'closed' WHERE review_id = ?", [$id]);
    }

    /** Hide it with a reason the author sees; reports are dealt with. */
    public static function reject(int $id, string $note): void
    {
        DB::run("UPDATE reviews SET status = 'rejected', moderation_note = ? WHERE id = ?", [$note, $id]);
        DB::run("UPDATE review_reports SET status = 'closed' WHERE review_id = ?", [$id]);
    }

    /** Reports were unfounded: close them and put the review back if they hid it. */
    public static function dismissReports(int $id): void
    {
        DB::run("UPDATE review_reports SET status = 'closed' WHERE review_id = ?", [$id]);
        DB::run(
            "UPDATE reviews SET status = 'approved', moderation_note = '' WHERE id = ? AND status = 'pending'
             AND moderation_note LIKE 'Hidden after reports%'",
            [$id]
        );
    }

    /** Counts for the admin menu badge and tabs. */
    public static function moderationCounts(): array
    {
        $row = DB::run(
            "SELECT
                (SELECT COUNT(*) FROM reviews WHERE status = 'pending') AS pending,
                (SELECT COUNT(*) FROM review_photos WHERE status = 'pending') AS photos,
                (SELECT COUNT(DISTINCT review_id) FROM review_reports WHERE status = 'open') AS reported,
                (SELECT COUNT(*) FROM reviews WHERE status = 'approved') AS approved,
                (SELECT COUNT(*) FROM reviews WHERE status = 'rejected') AS rejected,
                (SELECT COUNT(*) FROM reviews) AS total"
        )->fetch();
        return array_map('intval', $row);
    }

    /**
     * Reviews for the admin page. $tab: queue (waiting review or photos),
     * reported (open reports), approved, rejected, all.
     */
    public static function forAdmin(string $tab, int $limit = 100): array
    {
        $where = match ($tab) {
            'queue' => "(r.status = 'pending' OR EXISTS (SELECT 1 FROM review_photos ph WHERE ph.review_id = r.id AND ph.status = 'pending'))",
            'reported' => "EXISTS (SELECT 1 FROM review_reports rr WHERE rr.review_id = r.id AND rr.status = 'open')",
            'approved' => "r.status = 'approved'",
            'rejected' => "r.status = 'rejected'",
            default => '1=1',
        };
        $rows = DB::run(
            'SELECT r.*, u.name AS user_name, u.phone AS user_phone, p.name AS product_name, m.shop_name,
                    ' . self::VERIFIED_SQL . ' AS verified_purchase
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             JOIN products p ON p.id = r.product_id
             JOIN merchants m ON m.id = p.merchant_id
             WHERE ' . $where . '
             ORDER BY COALESCE(r.updated_at, r.created_at) DESC
             LIMIT ' . max(1, $limit)
        )->fetchAll();
        if (!$rows) {
            return [];
        }

        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $photos = DB::run("SELECT * FROM review_photos WHERE review_id IN ({$in}) ORDER BY id", $ids)->fetchAll();
        $reports = DB::run(
            "SELECT rr.*, u.name AS reporter FROM review_reports rr JOIN users u ON u.id = rr.user_id
             WHERE rr.review_id IN ({$in}) AND rr.status = 'open' ORDER BY rr.created_at",
            $ids
        )->fetchAll();
        $ph = [];
        foreach ($photos as $p) {
            $ph[(int) $p['review_id']][] = $p;
        }
        $rp = [];
        foreach ($reports as $x) {
            $rp[(int) $x['review_id']][] = $x;
        }
        foreach ($rows as &$r) {
            $r['photos'] = $ph[(int) $r['id']] ?? [];
            $r['reports'] = $rp[(int) $r['id']] ?? [];
        }
        unset($r);
        return $rows;
    }
}
