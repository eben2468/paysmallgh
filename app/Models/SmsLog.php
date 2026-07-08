<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class SmsLog
{
    public static function create(string $recipient, string $body, string $status = 'queued', string $providerRef = ''): int
    {
        DB::run(
            'INSERT INTO sms_log (recipient, body, status, provider_ref) VALUES (?, ?, ?, ?)',
            [$recipient, $body, $status, $providerRef]
        );
        return DB::lastId();
    }

    public static function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return DB::run("SELECT * FROM sms_log ORDER BY id DESC LIMIT {$limit}")->fetchAll();
    }
}
