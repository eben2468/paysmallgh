<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\SmsLog;

/**
 * The ONLY class that talks to Moolre. Everything else goes through here.
 *
 * PAYMENTS_MODE:
 *   mock    — no network. Collections/disbursements succeed instantly so the
 *             whole product can be demoed end-to-end. Build/demo path.
 *   sandbox — real HTTP calls to Moolre test environment.
 *   live    — real HTTP calls, production keys.
 *
 * Endpoint paths come from .env (MOOLRE_PATH_*). They are placeholders until
 * confirmed against docs.moolre.com — do NOT flip to sandbox/live before
 * checking the docs. Auth uses Moolre's header scheme (X-API-USER, X-API-KEY,
 * X-API-PUBKEY / X-API-VASKEY depending on product).
 */
final class MoolreService
{
    public function mode(): string
    {
        return Config::get('PAYMENTS_MODE', 'mock');
    }

    /**
     * Ask the customer's MoMo wallet for money (Collections).
     * Returns ['ok' => bool, 'instant' => bool, 'external_ref' => string, 'raw' => array].
     * In mock mode ok+instant. In sandbox/live the charge is pending until the
     * webhook confirms — 'ok' means "request accepted".
     */
    public function collect(string $phone, int $amountPesewas, string $reference, string $description): array
    {
        if ($this->mode() === 'mock') {
            return [
                'ok' => true,
                'instant' => true,
                'external_ref' => 'MOCK-C-' . strtoupper(bin2hex(random_bytes(5))),
                'raw' => ['mode' => 'mock', 'note' => 'simulated collection', 'desc' => $description],
            ];
        }

        return $this->call(Config::get('MOOLRE_PATH_COLLECT', ''), [
            'type' => 1,
            'channel' => 13, // MoMo; confirm channel codes in docs.moolre.com
            'currency' => 'GHS',
            'payer' => $phone,
            'amount' => $this->toCedis($amountPesewas),
            'externalref' => $reference,
            'reference' => $description,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
        ]);
    }

    /**
     * Send money out (Disbursements) — merchant payouts and customer refunds.
     */
    public function disburse(string $channel, string $destination, int $amountPesewas, string $reference, string $description): array
    {
        if ($this->mode() === 'mock') {
            return [
                'ok' => true,
                'instant' => true,
                'external_ref' => 'MOCK-D-' . strtoupper(bin2hex(random_bytes(5))),
                'raw' => ['mode' => 'mock', 'note' => 'simulated disbursement', 'channel' => $channel, 'desc' => $description],
            ];
        }

        return $this->call(Config::get('MOOLRE_PATH_DISBURSE', ''), [
            'type' => 1,
            'channel' => $channel === 'bank' ? 14 : 13, // confirm channel codes in docs
            'currency' => 'GHS',
            'receiver' => $destination,
            'amount' => $this->toCedis($amountPesewas),
            'externalref' => $reference,
            'reference' => $description,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
        ]);
    }

    /**
     * Send an SMS. Always logged to sms_log regardless of mode.
     */
    public function sms(string $phone, string $body): void
    {
        if ($this->mode() === 'mock') {
            SmsLog::create($phone, $body, 'sent', 'MOCK-S-' . strtoupper(bin2hex(random_bytes(4))));
            return;
        }

        $res = $this->call(Config::get('MOOLRE_PATH_SMS', ''), [
            'recipient' => $phone,
            'message' => $body,
        ], vas: true);
        SmsLog::create($phone, $body, $res['ok'] ? 'sent' : 'failed', $res['external_ref']);
    }

    /** Query a transaction's status by our reference. */
    public function status(string $reference): array
    {
        if ($this->mode() === 'mock') {
            return ['ok' => true, 'status' => 'success', 'raw' => ['mode' => 'mock']];
        }
        return $this->call(Config::get('MOOLRE_PATH_STATUS', ''), [
            'externalref' => $reference,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
        ]);
    }

    /**
     * Verify an incoming webhook is genuinely from Moolre. We require a shared
     * secret (set in the Moolre dashboard callback URL or header) AND that the
     * reference matches a transaction we created.
     */
    public function verifyWebhook(array $headers, array $payload): bool
    {
        $secret = Config::get('MOOLRE_WEBHOOK_SECRET', '');
        $sent = $headers['x-webhook-secret'] ?? ($payload['secret'] ?? '');
        return $secret !== '' && hash_equals($secret, (string) $sent);
    }

    // ---- internals ----

    private function toCedis(int $pesewas): string
    {
        return number_format($pesewas / 100, 2, '.', '');
    }

    private function call(string $path, array $body, bool $vas = false): array
    {
        $url = rtrim(Config::get('MOOLRE_BASE_URL', ''), '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'X-API-USER: ' . Config::get('MOOLRE_API_USER', ''),
        ];
        if ($vas) {
            $headers[] = 'X-API-VASKEY: ' . Config::get('MOOLRE_VAS_KEY', '');
        } else {
            $headers[] = 'X-API-PUBKEY: ' . Config::get('MOOLRE_API_PUBKEY', '');
            $headers[] = 'X-API-KEY: ' . Config::get('MOOLRE_API_KEY', '');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($response) ? (json_decode($response, true) ?? []) : [];
        $ok = $status >= 200 && $status < 300 && ($decoded['status'] ?? 0) == 1;

        return [
            'ok' => $ok,
            'instant' => false,
            'external_ref' => (string) ($decoded['data']['transactionid'] ?? ''),
            'raw' => $decoded ?: ['http_status' => $status, 'error' => $err],
        ];
    }
}
