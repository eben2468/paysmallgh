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

    public static function create(array $d): int
    {
        DB::run(
            'INSERT INTO merchants (shop_name, owner_name, phone, location, password_hash, payout_channel, payout_number, payout_bank_code, id_number, business_reg)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['shop_name'], $d['owner_name'], $d['phone'], $d['location'],
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
                shop_name = ?, owner_name = ?, location = ?, payout_channel = ?, payout_number = ?, payout_bank_code = ?
             WHERE id = ?",
            [
                $d['payout_channel'], $d['payout_number'], $d['payout_bank_code'],
                $d['shop_name'], $d['owner_name'], $d['location'],
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
        DB::run("UPDATE merchants SET status = 'approved' WHERE id = ?", [$id]);
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
