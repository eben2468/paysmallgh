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

    /** Is this email already on another shop? */
    public static function emailTaken(string $email, int $exceptId = 0): bool
    {
        return (bool) DB::run('SELECT 1 FROM merchants WHERE email = ? AND id <> ? LIMIT 1', [$email, $exceptId])->fetchColumn();
    }

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO merchants (shop_name, owner_name, phone, email, location, password_hash, payout_channel, payout_number, payout_bank_code, id_number, business_reg)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['shop_name'], $d['owner_name'], $d['phone'], $d['email'], $d['location'],
                password_hash($d['password'], PASSWORD_DEFAULT),
                $d['payout_channel'], $d['payout_number'], $d['payout_bank_code'] ?? '',
                $d['id_number'] ?? '', $d['business_reg'] ?? '',
            ]
        );
        return DB::lastId();
    }

    /** Record where the merchant's uploaded Ghana Card image was stored. */
    public static function setIdCardPath(int $id, string $path): void
    {
        DB::run('UPDATE merchants SET id_card_path = ? WHERE id = ?', [$path, $id]);
    }

    /** Admin marks the merchant's identity as checked — powers the trust badge. */
    public static function setVerified(int $id, bool $verified): void
    {
        DB::run(
            'UPDATE merchants SET verified = ?, verified_at = ' . ($verified ? 'NOW()' : 'NULL') . ' WHERE id = ?',
            [$verified ? 1 : 0, $id]
        );
    }

    /**
     * Approved shops with at least one product on sale, for the homepage
     * showcase: verified shops first, then the ones with the most products.
     */
    public static function showcase(int $limit = 6): array
    {
        $limit = max(1, $limit);
        return DB::run(
            "SELECT m.id, m.shop_name, m.location, m.verified,
                    COUNT(p.id) AS product_count,
                    (SELECT COUNT(*) FROM plans pl JOIN products pp ON pp.id = pl.product_id
                     WHERE pp.merchant_id = m.id AND pl.status IN ('active','completed')) AS plan_count
             FROM merchants m
             JOIN products p ON p.merchant_id = m.id AND p.active = 1
             WHERE m.status = 'approved'
             GROUP BY m.id, m.shop_name, m.location, m.verified
             ORDER BY m.verified DESC, product_count DESC, m.shop_name
             LIMIT " . $limit
        )->fetchAll();
    }

    /** Admin declines a shop for now, with a reason the owner sees. */
    public static function decline(int $id, string $note): void
    {
        DB::run("UPDATE merchants SET status = 'rejected', review_note = ? WHERE id = ?", [$note, $id]);
    }

    /** Owner fixed things and wants another look: back in the review queue. */
    public static function requestReview(int $id): bool
    {
        return DB::run("UPDATE merchants SET status = 'pending' WHERE id = ? AND status = 'rejected'", [$id])->rowCount() > 0;
    }

    public static function updatePassword(int $id, string $password): void
    {
        DB::run('UPDATE merchants SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public static function all(): array
    {
        return DB::run('SELECT * FROM merchants ORDER BY created_at DESC')->fetchAll();
    }

    /**
     * Update the editable shop details. Phone (the login) and status are not
     * touched here. If the payout account changed, the cached Paystack
     * recipient is dropped so the next payout registers the new account.
     */
    public static function updateDetails(int $id, array $d): void
    {
        DB::run(
            "UPDATE merchants SET
                paystack_recipient_code = IF(payout_channel = ? AND payout_number = ? AND payout_bank_code = ?, paystack_recipient_code, ''),
                shop_name = ?, owner_name = ?, email = ?, location = ?, payout_channel = ?, payout_number = ?, payout_bank_code = ?
             WHERE id = ?",
            [
                $d['payout_channel'], $d['payout_number'], $d['payout_bank_code'],
                $d['shop_name'], $d['owner_name'], $d['email'], $d['location'],
                $d['payout_channel'], $d['payout_number'], $d['payout_bank_code'], $id,
            ]
        );
    }

    /** Cache the Paystack transfer recipient registered for this merchant's payout account. */
    public static function setRecipientCode(int $id, string $code): void
    {
        DB::run('UPDATE merchants SET paystack_recipient_code = ? WHERE id = ?', [$code, $id]);
    }

    public static function approve(int $id): void
    {
        DB::run("UPDATE merchants SET status = 'approved', review_note = '' WHERE id = ?", [$id]);
    }

    /** Set an allowed status (pending | approved | suspended). */
    public static function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['pending', 'approved', 'suspended'], true)) {
            return;
        }
        DB::run('UPDATE merchants SET status = ? WHERE id = ?', [$status, $id]);
    }
}
