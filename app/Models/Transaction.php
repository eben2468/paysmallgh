<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * Append-only ledger. Insert rows; update only status/external_ref/raw_payload
 * when the provider reports back. Nothing is ever deleted.
 */
final class Transaction
{
    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM transactions WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByRef(string $providerRef): ?array
    {
        return DB::run('SELECT * FROM transactions WHERE provider_ref = ?', [$providerRef])->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO transactions (type, status, amount_pesewas, phone, plan_id, installment_id, merchant_id, provider_ref, external_ref, raw_payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['type'], $d['status'] ?? 'pending', $d['amount_pesewas'], $d['phone'] ?? '',
                $d['plan_id'] ?? null, $d['installment_id'] ?? null, $d['merchant_id'] ?? null,
                $d['provider_ref'], $d['external_ref'] ?? '', $d['raw_payload'] ?? null,
            ]
        );
        return DB::lastId();
    }

    public static function setStatus(int $id, string $status, string $externalRef = '', ?string $rawPayload = null): void
    {
        DB::run(
            'UPDATE transactions SET status = ?, external_ref = IF(? = \'\', external_ref, ?), raw_payload = COALESCE(?, raw_payload), updated_at = NOW() WHERE id = ?',
            [$status, $externalRef, $externalRef, $rawPayload, $id]
        );
    }

    /** True if the plan already has a payout pending or paid — never start a second one. */
    public static function openPayoutForPlan(int $planId): bool
    {
        return (bool) DB::run(
            "SELECT 1 FROM transactions WHERE plan_id = ? AND type = 'disbursement' AND status IN ('pending','success') LIMIT 1",
            [$planId]
        )->fetchColumn();
    }

    /** True if an installment's payment already has a refund pending or done. */
    public static function hasOpenRefund(int $installmentId): bool
    {
        return (bool) DB::run(
            "SELECT 1 FROM transactions WHERE installment_id = ? AND type = 'refund' AND status IN ('pending','success') LIMIT 1",
            [$installmentId]
        )->fetchColumn();
    }

    /** Failed refunds on a plan with no successful/pending retry — shown to admin. */
    public static function outstandingRefundCount(int $planId): int
    {
        return (int) DB::run(
            "SELECT COUNT(*) FROM transactions c
             WHERE c.plan_id = ? AND c.type = 'collection' AND c.status = 'success' AND c.installment_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM transactions r WHERE r.installment_id = c.installment_id
                               AND r.type = 'refund' AND r.status IN ('pending','success'))",
            [$planId]
        )->fetchColumn();
    }

    public static function ledger(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        return DB::run("SELECT * FROM transactions ORDER BY id DESC LIMIT {$limit}")->fetchAll();
    }

    /** The most recent still-pending transaction of a type on a plan, if any. */
    public static function latestPendingForPlan(int $planId, string $type = 'collection'): ?array
    {
        return DB::run(
            "SELECT * FROM transactions WHERE plan_id = ? AND type = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$planId, $type]
        )->fetch() ?: null;
    }

    /** All pending transactions at least $minutes old — the reconcile queue. */
    public static function pendingOlderThan(int $minutes): array
    {
        return DB::run(
            "SELECT * FROM transactions WHERE status = 'pending' AND created_at <= (NOW() - INTERVAL ? MINUTE) ORDER BY id ASC",
            [max(0, $minutes)]
        )->fetchAll();
    }

    public static function pendingCount(): int
    {
        return (int) DB::run("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn();
    }

    /** Every ledger row tied to a plan, oldest first — the plan's money history. */
    public static function forPlan(int $planId): array
    {
        return DB::run(
            'SELECT * FROM transactions WHERE plan_id = ? ORDER BY id ASC',
            [$planId]
        )->fetchAll();
    }

    public static function payoutsForMerchant(int $merchantId): array
    {
        return DB::run(
            "SELECT t.*, pl.id AS plan_ref, pr.name AS product_name
             FROM transactions t
             LEFT JOIN plans pl ON pl.id = t.plan_id
             LEFT JOIN products pr ON pr.id = pl.product_id
             WHERE t.merchant_id = ? AND t.type = 'disbursement'
             ORDER BY t.id DESC",
            [$merchantId]
        )->fetchAll();
    }
}
