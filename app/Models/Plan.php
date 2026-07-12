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
            "SELECT pl.*, pr.name AS product_name, pr.photo AS photo, pr.category, m.shop_name, m.verified AS merchant_verified
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

    /** Merchant confirms they've handed the item to the customer. Only meaningful once paid out. */
    public static function markReleased(int $id): void
    {
        DB::run('UPDATE plans SET released_at = NOW() WHERE id = ? AND released_at IS NULL', [$id]);
    }

    public static function setStatus(int $id, string $status): void
    {
        $extra = $status === 'completed' ? ', completed_at = NOW()' : '';
        DB::run("UPDATE plans SET status = ?{$extra} WHERE id = ?", [$status, $id]);
    }

    /**
     * Delete a plan and its schedule/ledger rows — ONLY when no installment has
     * been paid (no money ever moved). Refuses otherwise. Returns true if deleted.
     */
    public static function delete(int $id): bool
    {
        $row = DB::run('SELECT installments_paid FROM plans WHERE id = ?', [$id])->fetch();
        if (!$row || (int) $row['installments_paid'] > 0) {
            return false;
        }
        // Only pending/abandoned collections exist for a 0-payment plan.
        DB::run('DELETE FROM transactions WHERE plan_id = ?', [$id]);
        DB::run('DELETE FROM installments WHERE plan_id = ?', [$id]);
        DB::run('DELETE FROM plans WHERE id = ?', [$id]);
        return true;
    }

    /**
     * The next unpaid installment of each active plan that falls due within the
     * next $withinDays days and hasn't had a "due soon" reminder sent yet.
     * Powers the automatic upcoming-payment reminder.
     */
    public static function installmentsDueSoon(int $withinDays): array
    {
        return DB::run(
            "SELECT i.id AS installment_id, i.number, i.amount_pesewas, i.due_date,
                    pl.id AS plan_id, pl.installment_pesewas,
                    u.phone AS customer_phone, u.name AS customer_name,
                    pr.name AS product_name
             FROM installments i
             JOIN plans pl ON pl.id = i.plan_id AND pl.status = 'active'
             JOIN products pr ON pr.id = pl.product_id
             JOIN users u ON u.id = pl.customer_id
             WHERE i.paid_at IS NULL
               AND i.due_reminded_at IS NULL
               AND i.number = pl.installments_paid + 1
               AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)",
            [max(0, $withinDays)]
        )->fetchAll();
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
