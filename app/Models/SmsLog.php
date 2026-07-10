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

    /**
     * Real (non-mock) messages that were accepted but haven't reached a final
     * delivery state yet — candidates for a delivery-status poll.
     * @return array<array{id:int, provider_ref:string}>
     */
    public static function pendingDelivery(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return DB::run(
            "SELECT id, provider_ref FROM sms_log
             WHERE provider_ref <> '' AND provider_ref NOT LIKE 'MOCK-%'
               AND status IN ('queued','sent')
             ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    /** Update the delivery status of the message(s) carrying this provider ref. */
    public static function setStatusByRef(string $ref, string $status): void
    {
        DB::run('UPDATE sms_log SET status = ? WHERE provider_ref = ?', [$status, $ref]);
    }
}
