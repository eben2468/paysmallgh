<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * Cards a customer paid with, saved as Paystack reusable authorizations.
 * We keep only Paystack's token and display details (brand, last 4, expiry) —
 * never the card number or CVV. One row per card (Paystack's signature).
 */
final class SavedCard
{
    public static function forUser(int $userId): array
    {
        return DB::run('SELECT * FROM saved_cards WHERE user_id = ? ORDER BY created_at DESC', [$userId])->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        return DB::run('SELECT * FROM saved_cards WHERE id = ? AND user_id = ?', [$id, $userId])->fetch() ?: null;
    }

    public static function delete(int $id, int $userId): void
    {
        DB::run('DELETE FROM saved_cards WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public static function deleteAll(int $userId): void
    {
        DB::run('DELETE FROM saved_cards WHERE user_id = ?', [$userId]);
    }

    /** Still in date? (Expires at the end of its expiry month.) */
    public static function isExpired(array $card): bool
    {
        $y = (int) $card['exp_year'];
        $m = (int) $card['exp_month'];
        if ($y < 1 || $m < 1) {
            return false; // unknown — let Paystack decide
        }
        return (int) date('Y') * 12 + (int) date('n') > $y * 12 + $m;
    }

    /** "Visa •••• 4081 · 12/30" */
    public static function label(array $card): string
    {
        $brand = ucfirst(strtolower(trim((string) $card['brand']))) ?: 'Card';
        $exp = $card['exp_month'] !== '' ? ' · ' . str_pad((string) $card['exp_month'], 2, '0', STR_PAD_LEFT) . '/' . substr((string) $card['exp_year'], -2) : '';
        return $brand . ' •••• ' . $card['last4'] . $exp;
    }

    /**
     * Remember the card from a successful Paystack payment, if it's a card,
     * Paystack says it can be charged again, and the customer allows saving.
     * Safe to call on every success: the same card (signature) is stored once.
     *
     * $authorization: Paystack's data.authorization; $email: the email that payment used.
     * Returns true when a new card was saved.
     */
    public static function rememberFrom(array $user, array $authorization, string $email): bool
    {
        if (empty($user['save_cards'])) {
            return false;
        }
        $code = (string) ($authorization['authorization_code'] ?? '');
        $signature = (string) ($authorization['signature'] ?? '');
        $reusable = filter_var($authorization['reusable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (($authorization['channel'] ?? '') !== 'card' || !$reusable || $code === '' || $signature === '') {
            return false;
        }
        $res = DB::run(
            'INSERT INTO saved_cards (user_id, authorization_code, signature, email, brand, last4, exp_month, exp_year, bank)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE authorization_code = VALUES(authorization_code), email = VALUES(email),
                                     exp_month = VALUES(exp_month), exp_year = VALUES(exp_year)',
            [
                (int) $user['id'], $code, $signature, $email,
                mb_substr((string) ($authorization['brand'] ?? $authorization['card_type'] ?? ''), 0, 30),
                substr(preg_replace('/\D/', '', (string) ($authorization['last4'] ?? '')) ?? '', -4),
                substr((string) ($authorization['exp_month'] ?? ''), 0, 2),
                substr((string) ($authorization['exp_year'] ?? ''), 0, 4),
                mb_substr((string) ($authorization['bank'] ?? ''), 0, 80),
            ]
        );
        return $res->rowCount() === 1; // 1 = inserted, 2 = updated an existing card
    }
}
