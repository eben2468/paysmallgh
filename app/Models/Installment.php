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
