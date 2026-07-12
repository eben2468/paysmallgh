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

    /**
     * All customers with per-person activity for the admin users page:
     * how many plans, how many active, and total paid into escrow (pesewas).
     */
    public static function allWithStats(): array
    {
        return DB::run(
            "SELECT u.id, u.name, u.phone, u.created_at,
                    COUNT(pl.id) AS plans_total,
                    SUM(pl.status = 'active') AS plans_active,
                    COALESCE(SUM(pl.installments_paid * pl.installment_pesewas), 0) AS paid_pesewas
             FROM users u
             LEFT JOIN plans pl ON pl.customer_id = u.id AND pl.status <> 'pending'
             GROUP BY u.id, u.name, u.phone, u.created_at
             ORDER BY u.created_at DESC"
        )->fetchAll();
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
