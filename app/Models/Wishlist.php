<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** A customer's saved items ("wishlist"). */
final class Wishlist
{
    /** @var array<int, array<int, true>> per-request cache: userId => productId set */
    private static array $cache = [];

    /** Product ids this customer has saved, as a set. */
    public static function idSet(int $userId): array
    {
        if (!isset(self::$cache[$userId])) {
            $ids = DB::run('SELECT product_id FROM wishlists WHERE user_id = ?', [$userId])->fetchAll(\PDO::FETCH_COLUMN);
            self::$cache[$userId] = array_fill_keys(array_map('intval', $ids), true);
        }
        return self::$cache[$userId];
    }

    public static function has(int $userId, int $productId): bool
    {
        return isset(self::idSet($userId)[$productId]);
    }

    public static function add(int $userId, int $productId): void
    {
        DB::run('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)', [$userId, $productId]);
        unset(self::$cache[$userId]);
    }

    public static function remove(int $userId, int $productId): void
    {
        DB::run('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?', [$userId, $productId]);
        unset(self::$cache[$userId]);
    }

    /** Saved/unsaved after the toggle. */
    public static function toggle(int $userId, int $productId): bool
    {
        if (self::has($userId, $productId)) {
            self::remove($userId, $productId);
            return false;
        }
        self::add($userId, $productId);
        return true;
    }

    /** Listing rows for the saved items, most recently saved first. */
    public static function products(int $userId): array
    {
        $ids = DB::run('SELECT product_id FROM wishlists WHERE user_id = ? ORDER BY created_at DESC', [$userId])->fetchAll(\PDO::FETCH_COLUMN);
        return Product::byIds($ids);
    }

    public static function count(int $userId): int
    {
        return count(self::idSet($userId));
    }
}
