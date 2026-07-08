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

    public static function ledger(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        return DB::run("SELECT * FROM transactions ORDER BY id DESC LIMIT {$limit}")->fetchAll();
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
