<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Installment
{
    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM installments WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function forPlan(int $planId): array
    {
        return DB::run('SELECT * FROM installments WHERE plan_id = ? ORDER BY number', [$planId])->fetchAll();
    }

    /**
     * Latest payment on each live plan, newest first, for the homepage "just
     * paid" feed. One row per plan so a single busy customer can't fill it.
     * Minutes-ago is worked out in SQL so PHP/MySQL timezones can't disagree.
     */
    public static function recentPayments(int $limit = 6): array
    {
        $limit = max(1, $limit);
        return DB::run(
            "SELECT i.number, i.amount_pesewas, TIMESTAMPDIFF(MINUTE, i.paid_at, NOW()) AS mins_ago,
                    pl.installments_total, pl.frequency, u.name AS customer_name,
                    p.id AS product_id, p.name AS product_name, m.location AS merchant_location
             FROM installments i
             JOIN (SELECT plan_id, MAX(number) AS n FROM installments
                   WHERE paid_at IS NOT NULL GROUP BY plan_id) last ON last.plan_id = i.plan_id AND last.n = i.number
             JOIN plans pl ON pl.id = i.plan_id AND pl.status IN ('active','completed')
             JOIN users u ON u.id = pl.customer_id
             JOIN products p ON p.id = pl.product_id AND p.active = 1
             JOIN merchants m ON m.id = p.merchant_id AND m.status = 'approved'
             ORDER BY i.paid_at DESC
             LIMIT " . $limit
        )->fetchAll();
    }

    /** The next unpaid installment on a plan. */
    public static function nextUnpaid(int $planId): ?array
    {
        return DB::run(
            'SELECT * FROM installments WHERE plan_id = ? AND paid_at IS NULL ORDER BY number LIMIT 1',
            [$planId]
        )->fetch() ?: null;
    }

    public static function createSchedule(int $planId, int $count, int $amountPesewas, string $frequency): void
    {
        $interval = match ($frequency) {
            'daily' => 'P1D',
            'monthly' => 'P1M',
            default => 'P7D', // weekly
        };
        $due = new \DateTimeImmutable('today');
        for ($n = 1; $n <= $count; $n++) {
            DB::run(
                'INSERT INTO installments (plan_id, number, amount_pesewas, due_date) VALUES (?, ?, ?, ?)',
                [$planId, $n, $amountPesewas, $due->format('Y-m-d')]
            );
            $due = $due->add(new \DateInterval($interval));
        }
    }

    /** Mark paid only if still unpaid; returns true if this call did the marking (idempotency guard). */
    public static function markPaid(int $id, int $transactionId): bool
    {
        $stmt = DB::run(
            'UPDATE installments SET paid_at = NOW(), transaction_id = ? WHERE id = ? AND paid_at IS NULL',
            [$transactionId, $id]
        );
        return $stmt->rowCount() > 0;
    }
}
