<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Plan
{
    public static function find(int $id): ?array
    {
        return DB::run(
            'SELECT pl.*, pr.name AS product_name, pr.photo AS product_photo, pr.merchant_id,
                    m.shop_name, m.phone AS merchant_phone, m.payout_channel, m.payout_number,
                    u.name AS customer_name, u.phone AS customer_phone
             FROM plans pl
             JOIN products pr ON pr.id = pl.product_id
             JOIN merchants m ON m.id = pr.merchant_id
             JOIN users u ON u.id = pl.customer_id
             WHERE pl.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    public static function forCustomer(int $customerId): array
    {
        // Includes 'pending' plans (first payment not yet confirmed) so the
        // customer can come back and finish/check them. Awaiting-payment first.
        return DB::run(
            "SELECT pl.*, pr.name AS product_name, pr.photo AS product_photo, m.shop_name
             FROM plans pl
             JOIN products pr ON pr.id = pl.product_id
             JOIN merchants m ON m.id = pr.merchant_id
             WHERE pl.customer_id = ?
             ORDER BY (pl.status = 'pending') DESC, pl.created_at DESC",
            [$customerId]
        )->fetchAll();
    }

    public static function forMerchant(int $merchantId): array
    {
        return DB::run(
            "SELECT pl.*, pr.name AS product_name, u.name AS customer_name, u.phone AS customer_phone
             FROM plans pl
             JOIN products pr ON pr.id = pl.product_id
             JOIN users u ON u.id = pl.customer_id
             WHERE pr.merchant_id = ? AND pl.status <> 'pending'
             ORDER BY pl.created_at DESC",
            [$merchantId]
        )->fetchAll();
    }

    public static function all(): array
    {
        return DB::run(
            "SELECT pl.*, pr.name AS product_name, m.shop_name, u.name AS customer_name
             FROM plans pl
             JOIN products pr ON pr.id = pl.product_id
             JOIN merchants m ON m.id = pr.merchant_id
             JOIN users u ON u.id = pl.customer_id
             ORDER BY pl.created_at DESC"
        )->fetchAll();
    }

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO plans (product_id, customer_id, total_pesewas, installment_pesewas, frequency, installments_total)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$d['product_id'], $d['customer_id'], $d['total_pesewas'], $d['installment_pesewas'], $d['frequency'], $d['installments_total']]
        );
        return DB::lastId();
    }

    public static function setStatus(int $id, string $status): void
    {
        $extra = $status === 'completed' ? ', completed_at = NOW()' : '';
        DB::run("UPDATE plans SET status = ?{$extra} WHERE id = ?", [$status, $id]);
    }

    /** Plans past due beyond the grace window, still active. */
    public static function activeWithOverdue(): array
    {
        return DB::run(
            "SELECT pl.*, u.phone AS customer_phone, u.name AS customer_name,
                    m.phone AS merchant_phone, pr.name AS product_name,
                    MIN(i.due_date) AS oldest_due
             FROM plans pl
             JOIN installments i ON i.plan_id = pl.id AND i.paid_at IS NULL
             JOIN users u ON u.id = pl.customer_id
             JOIN products pr ON pr.id = pl.product_id
             JOIN merchants m ON m.id = pr.merchant_id
             WHERE pl.status = 'active' AND i.due_date < CURDATE()
             GROUP BY pl.id"
        )->fetchAll();
    }
}
