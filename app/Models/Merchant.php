<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Merchant
{
    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM merchants WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByPhone(string $phone): ?array
    {
        return DB::run('SELECT * FROM merchants WHERE phone = ?', [$phone])->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO merchants (shop_name, owner_name, phone, location, password_hash, payout_channel, payout_number)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $d['shop_name'], $d['owner_name'], $d['phone'], $d['location'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['payout_channel'], $d['payout_number'],
            ]
        );
        return DB::lastId();
    }

    public static function all(): array
    {
        return DB::run('SELECT * FROM merchants ORDER BY created_at DESC')->fetchAll();
    }

    public static function approve(int $id): void
    {
        DB::run("UPDATE merchants SET status = 'approved' WHERE id = ?", [$id]);
    }
}
