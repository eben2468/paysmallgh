<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Product
{
    public static function find(int $id): ?array
    {
        return DB::run(
            'SELECT p.*, m.shop_name, m.location AS merchant_location, m.status AS merchant_status,
                    m.verified AS merchant_verified, m.owner_name AS merchant_owner
             FROM products p JOIN merchants m ON m.id = p.merchant_id
             WHERE p.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    /** Active products from approved merchants, filtered by category and/or search term. */
    public static function browse(?string $category = null, ?string $q = null): array
    {
        $sql = "SELECT p.*, m.shop_name, m.location AS merchant_location, m.verified AS merchant_verified
                FROM products p JOIN merchants m ON m.id = p.merchant_id
                WHERE p.active = 1 AND m.status = 'approved'";
        $params = [];
        if ($category !== null && $category !== '') {
            $sql .= ' AND p.category = ?';
            $params[] = $category;
        }
        if ($q !== null && trim($q) !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.description LIKE ? OR m.shop_name LIKE ?)';
            $like = '%' . trim($q) . '%';
            array_push($params, $like, $like, $like);
        }
        $sql .= ' ORDER BY p.created_at DESC';
        return DB::run($sql, $params)->fetchAll();
    }

    /** Category => product count, for nav and tiles. */
    public static function categoryCounts(): array
    {
        return DB::run(
            "SELECT p.category, COUNT(*) AS n FROM products p
             JOIN merchants m ON m.id = p.merchant_id
             WHERE p.active = 1 AND m.status = 'approved'
             GROUP BY p.category ORDER BY n DESC, p.category"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function categories(): array
    {
        return DB::run(
            "SELECT DISTINCT p.category FROM products p
             JOIN merchants m ON m.id = p.merchant_id
             WHERE p.active = 1 AND m.status = 'approved' ORDER BY p.category"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function forMerchant(int $merchantId): array
    {
        return DB::run('SELECT * FROM products WHERE merchant_id = ? ORDER BY created_at DESC', [$merchantId])->fetchAll();
    }

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO products (merchant_id, name, description, photo, cash_price_pesewas, category, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['merchant_id'], $d['name'], $d['description'], $d['photo'], $d['cash_price_pesewas'], $d['category'], $d['active']]
        );
        return DB::lastId();
    }

    /** Update everything except the cover photo (managed via the images below). */
    public static function updateDetails(int $id, array $d): void
    {
        DB::run(
            'UPDATE products SET name = ?, description = ?, cash_price_pesewas = ?, category = ?, active = ? WHERE id = ?',
            [$d['name'], $d['description'], $d['cash_price_pesewas'], $d['category'], $d['active'], $id]
        );
    }

    public static function toggle(int $id, int $merchantId): void
    {
        DB::run('UPDATE products SET active = 1 - active WHERE id = ? AND merchant_id = ?', [$id, $merchantId]);
    }

    /** True if any layaway plan references this product (so it can't be deleted). */
    public static function hasPlans(int $id): bool
    {
        return (bool) DB::run('SELECT 1 FROM plans WHERE product_id = ? LIMIT 1', [$id])->fetchColumn();
    }

    /** Delete a product (image rows cascade; caller unlinks the files). */
    public static function delete(int $id, int $merchantId): void
    {
        DB::run('DELETE FROM products WHERE id = ? AND merchant_id = ?', [$id, $merchantId]);
    }

    /* ---------- Product images (gallery) ---------- */

    /** All images for a product, cover first. Falls back to products.photo for older single-photo rows. */
    public static function images(int $productId): array
    {
        $rows = DB::run(
            'SELECT id, path, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order, id',
            [$productId]
        )->fetchAll();
        if ($rows) {
            return $rows;
        }
        // Legacy fallback: a single-photo product with no product_images rows.
        $p = DB::run('SELECT photo FROM products WHERE id = ?', [$productId])->fetch();
        if ($p && $p['photo'] !== '') {
            return [['id' => 0, 'path' => $p['photo'], 'sort_order' => 0]];
        }
        return [];
    }

    public static function addImage(int $productId, string $path, int $sort = 0): int
    {
        DB::run('INSERT INTO product_images (product_id, path, sort_order) VALUES (?, ?, ?)', [$productId, $path, $sort]);
        return DB::lastId();
    }

    /** Delete one image (scoped to its product) and return its stored path so the file can be removed. */
    public static function deleteImage(int $imageId, int $productId): ?string
    {
        $row = DB::run('SELECT path FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $productId])->fetch();
        if (!$row) {
            return null;
        }
        DB::run('DELETE FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $productId]);
        return $row['path'];
    }

    /** Highest sort_order in use (or -1 when there are none). */
    public static function maxImageSort(int $productId): int
    {
        $r = DB::run('SELECT COALESCE(MAX(sort_order), -1) AS m FROM product_images WHERE product_id = ?', [$productId])->fetch();
        return (int) $r['m'];
    }

    /** Sync products.photo to the first gallery image (or '' when there are none). */
    public static function refreshCover(int $productId): void
    {
        $r = DB::run(
            'SELECT path FROM product_images WHERE product_id = ? ORDER BY sort_order, id LIMIT 1',
            [$productId]
        )->fetch();
        DB::run('UPDATE products SET photo = ? WHERE id = ?', [$r['path'] ?? '', $productId]);
    }
}
