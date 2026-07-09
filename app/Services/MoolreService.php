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
 * Everything that touches Moolre's wire format — endpoint paths, channel codes,
 * currency, callback URL — lives in .env, NOT hardcoded here. Confirm each value
 * against docs.moolre.com before flipping PAYMENTS_MODE to sandbox/live. Auth
 * uses Moolre's header scheme (X-API-USER + X-API-KEY/X-API-PUBKEY for transact,
 * X-API-VASKEY for SMS).
 *
 * Live/sandbox collections and disbursements are ASYNCHRONOUS: the API call only
 * *accepts* the request. Final success arrives later via the webhook
 * (/webhook/moolre) or by polling status() (see PlanService::reconcile). Mock
 * mode short-circuits this by returning instant success.
 */
final class MoolreService
{
    public function mode(): string
    {
        return Config::get('PAYMENTS_MODE', 'mock');
    }

    public function isMock(): bool
    {
        return $this->mode() === 'mock';
    }

    /**
     * Ask the customer's MoMo wallet for money (Collections).
     * Returns ['ok' => bool, 'instant' => bool, 'external_ref' => string, 'raw' => array].
     * 'ok'      — request accepted (mock: also means money moved).
     * 'instant' — result is final now (mock only); live/sandbox stay pending.
     */
    public function collect(string $phone, int $amountPesewas, string $reference, string $description): array
    {
        if ($this->isMock()) {
            return $this->mockOk('C');
        }

        return $this->call(Config::get('MOOLRE_PATH_COLLECT', ''), [
            'type' => 1,
            'channel' => Config::int('MOOLRE_CHANNEL_MOMO', 13),
            'currency' => Config::get('MOOLRE_CURRENCY', 'GHS'),
            'payer' => $phone,
            'amount' => $this->toCedis($amountPesewas),
            'externalref' => $reference,
            'reference' => $description,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
            'callbackurl' => Config::get('MOOLRE_CALLBACK_URL', ''),
        ]);
    }

    /**
     * Send money out (Disbursements) — merchant payouts and customer refunds.
     * $channel is our own 'momo'|'bank'; mapped to Moolre's numeric code here.
     */
    public function disburse(string $channel, string $destination, int $amountPesewas, string $reference, string $description): array
    {
        if ($this->isMock()) {
            return $this->mockOk('D');
        }

        $code = $channel === 'bank'
            ? Config::int('MOOLRE_CHANNEL_BANK', 14)
            : Config::int('MOOLRE_CHANNEL_MOMO', 13);

        return $this->call(Config::get('MOOLRE_PATH_DISBURSE', ''), [
            'type' => 1,
            'channel' => $code,
            'currency' => Config::get('MOOLRE_CURRENCY', 'GHS'),
            'receiver' => $destination,
            'amount' => $this->toCedis($amountPesewas),
            'externalref' => $reference,
            'reference' => $description,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
            'callbackurl' => Config::get('MOOLRE_CALLBACK_URL', ''),
        ]);
    }

    /**
     * Send an SMS. Always logged to sms_log. Never throws — an SMS failure must
     * not break a payment flow. Returns true if the provider accepted it.
     */
    public function sms(string $phone, string $body): bool
    {
        if ($this->isMock()) {
            SmsLog::create($phone, $body, 'sent', 'MOCK-S-' . strtoupper(bin2hex(random_bytes(4))));
            return true;
        }

        try {
            $res = $this->call(Config::get('MOOLRE_PATH_SMS', ''), [
                'sender' => Config::get('APP_NAME', 'PaySmallSmall'),
                'recipient' => $phone,
                'message' => $body,
            ], vas: true);
            SmsLog::create($phone, $body, $res['ok'] ? 'sent' : 'failed', $res['external_ref']);
            return $res['ok'];
        } catch (\Throwable $e) {
            SmsLog::create($phone, $body, 'failed', '');
            return false;
        }
    }

    /**
     * Query a transaction's final status by our reference.
     * Returns ['ok' => bool (API reachable), 'state' => 'success'|'pending'|'failed', 'external_ref' => string, 'raw' => array].
     */
    public function status(string $reference): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'state' => 'success', 'external_ref' => '', 'raw' => ['mode' => 'mock']];
        }

        $res = $this->call(Config::get('MOOLRE_PATH_STATUS', ''), [
            'externalref' => $reference,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
        ]);
        $res['state'] = $this->readState($res['raw']);
        return $res;
    }

    /**
     * Verify an incoming webhook is genuinely from Moolre. We require a shared
     * secret (header X-Webhook-Secret, or a `secret` field) that matches our
     * config. The caller additionally checks the reference maps to a known
     * transaction, so a leaked secret alone still can't credit a random plan.
     */
    public function verifyWebhook(array $headers, array $payload): bool
    {
        $secret = Config::get('MOOLRE_WEBHOOK_SECRET', '');
        if ($secret === '') {
            return false;
        }
        $sent = $headers['x-webhook-secret'] ?? ($payload['secret'] ?? '');
        return is_string($sent) && $sent !== '' && hash_equals($secret, $sent);
    }

    /**
     * Read a normalized final state out of any Moolre response/webhook body.
     * Defensive because the exact field names/codes must be confirmed against
     * docs.moolre.com — we check the common ones and default to 'pending' so an
     * ambiguous response is retried, never wrongly credited.
     */
    public function readState(array $body): string
    {
        $data = is_array($body['data'] ?? null) ? $body['data'] : $body;
        $raw = strtolower(trim((string) (
            $data['txstatus']
            ?? $data['transactionstatus']
            ?? $data['status']
            ?? $body['txstatus']
            ?? ''
        )));

        $success = ['1', 'success', 'successful', 'paid', 'completed', 'complete', 'approved'];
        $failed = ['0', '2', 'failed', 'failure', 'declined', 'cancelled', 'canceled', 'reversed', 'expired', 'rejected'];

        if (in_array($raw, $success, true)) {
            return 'success';
        }
        if (in_array($raw, $failed, true)) {
            return 'failed';
        }
        return 'pending';
    }

    // ---- internals ----

    private function mockOk(string $tag): array
    {
        return [
            'ok' => true,
            'instant' => true,
            'external_ref' => 'MOCK-' . $tag . '-' . strtoupper(bin2hex(random_bytes(5))),
            'raw' => ['mode' => 'mock'],
        ];
    }

    private function toCedis(int $pesewas): string
    {
        return number_format($pesewas / 100, 2, '.', '');
    }

    private function call(string $path, array $body, bool $vas = false): array
    {
        $url = rtrim(Config::get('MOOLRE_BASE_URL', ''), '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
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
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($response) && $response !== '' ? (json_decode($response, true) ?: []) : [];
        // Moolre envelopes a request as status == 1 (accepted).
        $ok = $httpStatus >= 200 && $httpStatus < 300 && (int) ($decoded['status'] ?? 0) === 1;

        return [
            'ok' => $ok,
            'instant' => false,
            'external_ref' => (string) (
                $decoded['data']['transactionid']
                ?? $decoded['data']['txid']
                ?? $decoded['data']['id']
                ?? ''
            ),
            'raw' => $decoded ?: ['http_status' => $httpStatus, 'error' => $err, 'body' => $response],
        ];
    }
}
