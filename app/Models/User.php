<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class User
{
    public static function find(int $id): ?array
    {
        return DB::run('SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByPhone(string $phone): ?array
    {
        return DB::run('SELECT * FROM users WHERE phone = ?', [$phone])->fetch() ?: null;
    }

    /**
     * All customers with per-person activity for the admin users page:
     * how many plans, how many active, and total paid into escrow (pesewas).
     */
    public static function allWithStats(): array
    {
        return DB::run(
            "SELECT u.id, u.name, u.phone, u.created_at,
                    COUNT(pl.id) AS plans_total,
                    SUM(pl.status = 'active') AS plans_active,
                    COALESCE(SUM(pl.installments_paid * pl.installment_pesewas), 0) AS paid_pesewas
             FROM users u
             LEFT JOIN plans pl ON pl.customer_id = u.id AND pl.status <> 'pending'
             GROUP BY u.id, u.name, u.phone, u.created_at
             ORDER BY u.created_at DESC"
        )->fetchAll();
    }

    public static function isVerified(array $user): bool
    {
        return !empty($user['phone_verified_at']);
    }

    public static function markVerified(int $id): void
    {
        DB::run('UPDATE users SET phone_verified_at = COALESCE(phone_verified_at, NOW()) WHERE id = ?', [$id]);
    }

    public static function updateName(int $id, string $name): void
    {
        DB::run('UPDATE users SET name = ? WHERE id = ?', [$name, $id]);
    }

    /** New number, already proven with a code — so it's verified too. */
    public static function updatePhone(int $id, string $phone): void
    {
        DB::run('UPDATE users SET phone = ?, phone_verified_at = NOW() WHERE id = ?', [$phone, $id]);
    }

    public static function updatePin(int $id, string $pin): void
    {
        DB::run('UPDATE users SET pin_hash = ? WHERE id = ?', [password_hash($pin, PASSWORD_DEFAULT), $id]);
    }

    /** MoMo wallet for direct prompts; null number = use the account phone. */
    public static function setMomo(int $id, ?string $number, ?string $network): void
    {
        DB::run('UPDATE users SET momo_number = ?, momo_network = ? WHERE id = ?', [$number, $network, $id]);
    }

    public static function setSaveCards(int $id, bool $on): void
    {
        DB::run('UPDATE users SET save_cards = ? WHERE id = ?', [$on ? 1 : 0, $id]);
    }

    /** The wallet to prompt: [phone, network] — the saved one, else the account phone. */
    public static function momoWallet(array $user): array
    {
        $phone = (string) ($user['momo_number'] ?? '') !== '' ? (string) $user['momo_number'] : (string) $user['phone'];
        $network = (string) ($user['momo_network'] ?? '') !== '' ? (string) $user['momo_network'] : momo_network($phone);
        return [$phone, $network];
    }

    /** Plans and money for the profile page. */
    public static function stats(int $id): array
    {
        $row = DB::run(
            "SELECT SUM(status = 'active') AS active, SUM(status = 'completed') AS completed,
                    COALESCE(SUM(CASE WHEN status <> 'pending' THEN installments_paid * installment_pesewas END), 0) AS paid
             FROM plans WHERE customer_id = ?",
            [$id]
        )->fetch();
        return ['active' => (int) $row['active'], 'completed' => (int) $row['completed'], 'paid' => (int) $row['paid']];
    }

    public static function create(string $name, string $phone, string $pin): int
    {
        DB::run(
            'INSERT INTO users (name, phone, pin_hash) VALUES (?, ?, ?)',
            [$name, $phone, password_hash($pin, PASSWORD_DEFAULT)]
        );
        return DB::lastId();
    }
}
