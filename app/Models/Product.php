<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Product
{
    public static function find(int $id): ?array
    {
        return DB::run(
            'SELECT p.*, m.shop_name, m.location AS merchant_location, m.status AS merchant_status
             FROM products p JOIN merchants m ON m.id = p.merchant_id
             WHERE p.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    /** Active products from approved merchants, filtered by category and/or search term. */
    public static function browse(?string $category = null, ?string $q = null): array
    {
        $sql = "SELECT p.*, m.shop_name, m.location AS merchant_location
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

    public static function update(int $id, array $d): void
    {
        DB::run(
            'UPDATE products SET name = ?, description = ?, photo = ?, cash_price_pesewas = ?, category = ?, active = ? WHERE id = ?',
            [$d['name'], $d['description'], $d['photo'], $d['cash_price_pesewas'], $d['category'], $d['active'], $id]
        );
    }

    public static function toggle(int $id, int $merchantId): void
    {
        DB::run('UPDATE products SET active = 1 - active WHERE id = ? AND merchant_id = ?', [$id, $merchantId]);
    }
}
