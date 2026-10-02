<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** A customer's saved delivery addresses. The default one is shared with a shop when an item is ready. */
final class Address
{
    public const REGIONS = [
        'Greater Accra', 'Ashanti', 'Central', 'Eastern', 'Western', 'Western North', 'Volta', 'Oti',
        'Northern', 'Savannah', 'North East', 'Upper East', 'Upper West', 'Bono', 'Bono East', 'Ahafo',
    ];

    public static function forUser(int $userId): array
    {
        return DB::run('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC', [$userId])->fetchAll();
    }

    public static function find(int $id, int $userId): ?array
    {
        return DB::run('SELECT * FROM user_addresses WHERE id = ? AND user_id = ?', [$id, $userId])->fetch() ?: null;
    }

    public static function defaultFor(int $userId): ?array
    {
        return DB::run('SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC LIMIT 1', [$userId])->fetch() ?: null;
    }

    public static function count(int $userId): int
    {
        return (int) DB::run('SELECT COUNT(*) FROM user_addresses WHERE user_id = ?', [$userId])->fetchColumn();
    }

    /** Insert or update; $id = null to create. The first address is always the default. */
    public static function save(int $userId, ?int $id, array $d, bool $makeDefault): int
    {
        $cols = ['label', 'recipient', 'phone', 'region', 'town', 'area', 'landmark', 'gps'];
        $vals = array_map(static fn (string $c) => $d[$c], $cols);
        if ($id === null) {
            DB::run(
                'INSERT INTO user_addresses (' . implode(', ', $cols) . ', user_id) VALUES (' . implode(', ', array_fill(0, count($cols) + 1, '?')) . ')',
                array_merge($vals, [$userId])
            );
            $id = DB::lastId();
        } else {
            DB::run(
                'UPDATE user_addresses SET ' . implode(', ', array_map(static fn (string $c): string => "{$c} = ?", $cols)) . ' WHERE id = ? AND user_id = ?',
                array_merge($vals, [$id, $userId])
            );
        }
        if ($makeDefault || self::count($userId) === 1) {
            self::makeDefault($id, $userId);
        }
        return $id;
    }

    public static function makeDefault(int $id, int $userId): void
    {
        DB::run('UPDATE user_addresses SET is_default = (id = ?) WHERE user_id = ?', [$id, $userId]);
    }

    /** Delete; if it was the default, the newest remaining address takes over. */
    public static function delete(int $id, int $userId): void
    {
        DB::run('DELETE FROM user_addresses WHERE id = ? AND user_id = ?', [$id, $userId]);
        $next = DB::run('SELECT id FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1', [$userId])->fetch();
        if (!$next) {
            $newest = DB::run('SELECT id FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1', [$userId])->fetch();
            if ($newest) {
                self::makeDefault((int) $newest['id'], $userId);
            }
        }
    }

    /** One line for display: "Near Total filling station, 12 Ring Rd, Osu, Greater Accra · GA-123-4567". */
    public static function oneLine(array $a): string
    {
        $parts = array_filter([$a['area'], $a['town'], $a['region']], static fn ($v) => (string) $v !== '');
        $s = implode(', ', $parts);
        if ((string) $a['landmark'] !== '') {
            $s = $a['landmark'] . ' — ' . $s;
        }
        if ((string) $a['gps'] !== '') {
            $s .= ' · ' . $a['gps'];
        }
        return $s;
    }
}
