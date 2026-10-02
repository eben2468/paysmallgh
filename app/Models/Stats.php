<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Read-only numbers for the admin portal (overview cards + sidebar counts). */
final class Stats
{
    /** Sidebar badges: things waiting on an admin. */
    public static function adminNavCounts(): array
    {
        $reviews = Review::moderationCounts();
        return [
            'merchants' => (int) DB::run("SELECT COUNT(*) FROM merchants WHERE status = 'pending'")->fetchColumn(),
            'plans' => (int) DB::run("SELECT COUNT(*) FROM plans WHERE status = 'active' AND grace_state = 'flagged'")->fetchColumn(),
            'ledger' => Transaction::pendingCount(),
            // Reviews or photos waiting, plus reviews with open reports.
            'reviews' => $reviews['pending'] + $reviews['photos'] + $reviews['reported'],
        ];
    }

    public static function adminOverview(): array
    {
        $plans = DB::run(
            "SELECT
                SUM(status = 'active') AS active,
                SUM(status = 'completed') AS completed,
                SUM(status = 'pending') AS pending,
                SUM(status = 'cancelled') AS cancelled,
                SUM(status = 'active' AND grace_state = 'grace') AS in_grace,
                SUM(status = 'active' AND grace_state = 'flagged') AS flagged,
                COALESCE(SUM(CASE WHEN status = 'active' THEN installments_paid * installment_pesewas END), 0) AS escrow
             FROM plans"
        )->fetch();

        $money = DB::run(
            "SELECT
                COALESCE(SUM(CASE WHEN type = 'collection' AND status = 'success' THEN amount_pesewas END), 0) AS collected,
                COALESCE(SUM(CASE WHEN type = 'disbursement' AND status = 'success' THEN amount_pesewas END), 0) AS paid_out,
                COALESCE(SUM(CASE WHEN type = 'refund' AND status = 'success' THEN amount_pesewas END), 0) AS refunded
             FROM transactions"
        )->fetch();

        // Fully paid but no payout pending/done — the payout failed and needs a retry.
        $stuckPayouts = (int) DB::run(
            "SELECT COUNT(*) FROM plans p
             WHERE p.status = 'active' AND p.installments_paid >= p.installments_total
               AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.plan_id = p.id
                               AND t.type = 'disbursement' AND t.status IN ('pending','success'))"
        )->fetchColumn();

        // Cancelled plans with a paid installment that has no live refund.
        $stuckRefunds = (int) DB::run(
            "SELECT COUNT(DISTINCT c.plan_id) FROM transactions c
             JOIN plans p ON p.id = c.plan_id AND p.status = 'cancelled'
             WHERE c.type = 'collection' AND c.status = 'success' AND c.installment_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM transactions r WHERE r.installment_id = c.installment_id
                               AND r.type = 'refund' AND r.status IN ('pending','success'))"
        )->fetchColumn();

        return [
            'plans' => array_map('intval', $plans ?: []),
            'money' => array_map('intval', $money ?: []),
            'merchants_pending' => (int) DB::run("SELECT COUNT(*) FROM merchants WHERE status = 'pending'")->fetchColumn(),
            'merchants_total' => (int) DB::run('SELECT COUNT(*) FROM merchants')->fetchColumn(),
            'customers' => (int) DB::run('SELECT COUNT(*) FROM users')->fetchColumn(),
            'tx_pending' => Transaction::pendingCount(),
            'stuck_payouts' => $stuckPayouts,
            'stuck_refunds' => $stuckRefunds,
        ];
    }
}
