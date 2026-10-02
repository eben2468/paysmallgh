<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;

/**
 * Stops PIN guessing: 5 wrong tries for a number within 15 minutes locks
 * logins for that number until the oldest of them is 15 minutes old.
 * A successful login (or a PIN reset) clears the count.
 */
final class LoginThrottle
{
    private const MAX_FAILURES = 5;
    private const WINDOW_MINUTES = 15;

    /** Minutes until this number may try again (0 = allowed now). */
    public static function lockedFor(string $phone, string $role): int
    {
        $row = DB::run(
            'SELECT COUNT(*) AS n, MIN(created_at) AS oldest FROM login_failures
             WHERE phone = ? AND role = ? AND created_at > DATE_SUB(NOW(), INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)',
            [$phone, $role]
        )->fetch();
        if ((int) $row['n'] < self::MAX_FAILURES) {
            return 0;
        }
        $left = DB::run(
            'SELECT TIMESTAMPDIFF(MINUTE, NOW(), DATE_ADD(?, INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE))',
            [$row['oldest']]
        )->fetchColumn();
        return max(1, (int) $left + 1);
    }

    public static function fail(string $phone, string $role): void
    {
        DB::run('INSERT INTO login_failures (phone, role) VALUES (?, ?)', [$phone, $role]);
        // Keep the table small.
        DB::run('DELETE FROM login_failures WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');
    }

    public static function clear(string $phone, string $role): void
    {
        DB::run('DELETE FROM login_failures WHERE phone = ? AND role = ?', [$phone, $role]);
    }
}
