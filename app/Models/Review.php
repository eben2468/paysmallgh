<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Review
{
    /**
     * Reviews for a product, newest first, with the reviewer's name and whether
     * they actually have a (non-pending) plan on it — a "verified purchase".
     */
    public static function forProduct(int $productId): array
    {
        return DB::run(
            "SELECT r.*, u.name AS user_name,
                    EXISTS(
                        SELECT 1 FROM plans pl
                        WHERE pl.product_id = r.product_id AND pl.customer_id = r.user_id
                          AND pl.status <> 'pending'
                    ) AS verified_purchase
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             WHERE r.product_id = ?
             ORDER BY r.created_at DESC",
            [$productId]
        )->fetchAll();
    }

    /** Average rating (1 dp) and count for a product. */
    public static function summary(int $productId): array
    {
        $row = DB::run(
            'SELECT COUNT(*) AS n, COALESCE(ROUND(AVG(rating), 1), 0) AS avg FROM reviews WHERE product_id = ?',
            [$productId]
        )->fetch();
        return ['count' => (int) $row['n'], 'avg' => (float) $row['avg']];
    }

    /** This user's existing review of a product, if any (to prefill the form). */
    public static function byUser(int $productId, int $userId): ?array
    {
        return DB::run(
            'SELECT * FROM reviews WHERE product_id = ? AND user_id = ?',
            [$productId, $userId]
        )->fetch() ?: null;
    }

    /** Create or update this user's single review of the product. */
    public static function upsert(int $productId, int $userId, int $rating, string $body): void
    {
        DB::run(
            'INSERT INTO reviews (product_id, user_id, rating, body) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), body = VALUES(body), created_at = NOW()',
            [$productId, $userId, $rating, $body]
        );
    }
}
