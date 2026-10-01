<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\SmsLog;

/**
 * The ONLY class that sends SMS. Paystack handles payments but has no SMS
 * product, so text messages still go through Moolre's SMS API
 * (POST /open/sms/send, auth: X-API-VASKEY).
 *
 * SMS_MODE runs independently of PAYMENTS_MODE:
 *   live -> always send real SMS (needs MOOLRE_VAS_KEY + approved sender ID)
 *   mock -> never send, just log to sms_log
 *   unset -> follow PAYMENTS_MODE (mock logs, sandbox/live sends)
 */
final class SmsService
{
    public function isLive(): bool
    {
        $mode = strtolower(trim((string) Config::get('SMS_MODE', '')));
        if ($mode === 'live') {
            return true;
        }
        if ($mode === 'mock') {
            return false;
        }
        return Config::get('PAYMENTS_MODE', 'mock') !== 'mock';
    }

    public function hasKey(): bool
    {
        return Config::get('MOOLRE_VAS_KEY', '') !== '';
    }

    /** The endpoint messages are sent to, for the admin status card. */
    public function endpoint(): string
    {
        return rtrim((string) Config::get('MOOLRE_BASE_URL', 'https://api.moolre.com'), '/')
            . Config::get('MOOLRE_PATH_SMS', '/open/sms/send');
    }

    /** Approved Moolre Sender ID (max 11 chars). */
    public function sender(): string
    {
        $s = trim((string) Config::get('MOOLRE_SMS_SENDER', ''));
        if ($s === '') {
            $s = 'PaySmall';
        }
        return substr($s, 0, 11);
    }

    /**
     * Send an SMS. Always logged to sms_log. Never throws — an SMS failure must
     * not break a payment flow. Returns true if the provider accepted it.
     *
     * $forceLive lets the admin "send test SMS" tool hit the real API even when
     * SMS_MODE is mock, so the integration can be verified on demand.
     */
    public function send(string $phone, string $body, bool $forceLive = false): bool
    {
        $ref = 'SMS-' . strtoupper(bin2hex(random_bytes(6)));

        if (!$forceLive && !$this->isLive()) {
            SmsLog::create($phone, $body, 'sent', 'MOCK-' . $ref);
            return true;
        }

        try {
            $res = $this->call(Config::get('MOOLRE_PATH_SMS', '/open/sms/send'), [
                'type' => 1,
                'senderid' => $this->sender(),
                'messages' => [
                    ['recipient' => $phone, 'message' => $body, 'ref' => $ref],
                ],
            ]);
            SmsLog::create($phone, $body, $res['ok'] ? 'sent' : 'failed', $ref);
            return $res['ok'];
        } catch (\Throwable $e) {
            SmsLog::create($phone, $body, 'failed', '');
            return false;
        }
    }

    /**
     * Query delivery status of previously-sent messages by their refs
     * (POST /open/sms/status, type 5). Sends nothing.
     */
    public function status(array $refs): array
    {
        return $this->call(Config::get('MOOLRE_PATH_SMS_STATUS', '/open/sms/status'), [
            'type' => 5,
            'ref' => array_values($refs),
        ]);
    }

    /**
     * Poll for the delivery outcome of accepted-but-not-final SMS and write it
     * back to sms_log. Per-message status codes:
     *   0 = Unknown, 1 = Sent, 2 = Delivered, 3 = Failed.
     * Returns a summary of what changed. Never throws.
     */
    public function refreshDelivery(int $limit = 100): array
    {
        $summary = ['checked' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0];

        if (!$this->isLive() || !$this->hasKey()) {
            return $summary;
        }

        $rows = SmsLog::pendingDelivery($limit);
        if (!$rows) {
            return $summary;
        }

        try {
            $res = $this->status(array_column($rows, 'provider_ref'));
        } catch (\Throwable $e) {
            return $summary;
        }

        // Index the returned {ref, status} items by ref.
        $byRef = [];
        foreach ((array) ($res['raw']['data'] ?? []) as $item) {
            if (isset($item['ref'])) {
                $byRef[(string) $item['ref']] = (int) ($item['status'] ?? 0);
            }
        }

        foreach ($rows as $row) {
            $ref = (string) $row['provider_ref'];
            if (!array_key_exists($ref, $byRef)) {
                $summary['pending']++;
                continue;
            }
            $summary['checked']++;
            switch ($byRef[$ref]) {
                case 2:
                    SmsLog::setStatusByRef($ref, 'delivered');
                    $summary['delivered']++;
                    break;
                case 3:
                    SmsLog::setStatusByRef($ref, 'failed');
                    $summary['failed']++;
                    break;
                case 1:
                    SmsLog::setStatusByRef($ref, 'sent');
                    $summary['pending']++;
                    break;
                default: // 0 = unknown — leave as-is and retry next sweep
                    $summary['pending']++;
            }
        }

        return $summary;
    }

    private function call(string $path, array $body): array
    {
        $url = rtrim((string) Config::get('MOOLRE_BASE_URL', 'https://api.moolre.com'), '/') . $path;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-VASKEY: ' . Config::get('MOOLRE_VAS_KEY', ''),
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($response) && $response !== '' ? (json_decode($response, true) ?: []) : [];
        // Moolre envelopes an accepted request as status == 1.
        $ok = $httpStatus >= 200 && $httpStatus < 300 && (int) ($decoded['status'] ?? 0) === 1;

        return [
            'ok' => $ok,
            'raw' => $decoded ?: ['http_status' => $httpStatus, 'error' => $err, 'body' => $response],
        ];
    }
}
