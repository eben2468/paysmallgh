<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class User
{
    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByPhone(string $phone): ?array
    {
        return DB::run('SELECT * FROM users WHERE phone = ?', [$phone])->fetch() ?: null;
    }

    public static function create(string $name, string $phone, string $pin): int
    {
        DB::run(
            'INSERT INTO users (name, phone, pin_hash) VALUES (?, ?, ?)',
            [$name, $phone, password_hash($pin, PASSWORD_DEFAULT)]
        );
        return DB::lastId();
    }
}
