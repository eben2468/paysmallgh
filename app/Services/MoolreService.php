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
     * Generate a hosted Moolre payment page (Web POS) for a collection and
     * return its URL. The customer opens that URL and pays there with mobile
     * money, bank transfer or card — Moolre handles the whole entry form.
     *
     * Endpoint: POST /embed/link (auth: X-API-USER + X-API-PUBKEY). Async like
     * collect() — final success arrives via the webhook/redirect and status
     * polling. Success envelope: status 1, code POS09, data.authorization_url.
     *
     * Returns ['ok' => bool, 'url' => string, 'external_ref' => string, 'raw' => array].
     */
    public function paymentLink(int $amountPesewas, string $reference, string $description, string $redirectUrl): array
    {
        if ($this->isMock()) {
            // Mock: send them to a local stand-in checkout so the redirect flow
            // demos end-to-end without spending money. Return a root-relative
            // path (not APP_URL) so it works on whatever host/port the app is
            // actually served from — the controller resolves it against the
            // current request.
            return [
                'ok' => true,
                'url' => '/checkout/mock?ref=' . urlencode($reference),
                'external_ref' => '',
                'raw' => ['mode' => 'mock'],
            ];
        }

        $res = $this->call(Config::get('MOOLRE_PATH_LINK', '/embed/link'), [
            'type' => 1,
            'amount' => $this->toCedis($amountPesewas),
            'email' => Config::get('MOOLRE_BUSINESS_EMAIL', ''),
            'externalref' => $reference,
            'reference' => $description,
            'callback' => Config::get('MOOLRE_CALLBACK_URL', ''),
            'redirect' => $redirectUrl,
            'reusable' => '0', // one plan payment per link
            'expiration_time' => Config::int('MOOLRE_LINK_EXPIRY_MIN', 60),
            'currency' => Config::get('MOOLRE_CURRENCY', 'GHS'),
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
            'metadata' => ['ref' => $reference],
        ]);

        $url = (string) ($res['raw']['data']['authorization_url'] ?? '');
        $ok = $res['ok'] && $url !== '';

        // Surface WHY it failed. Moolre puts a human message + code in the body;
        // a transport failure (blocked outbound, SSL, timeout) leaves http_status
        // and a curl error string instead. Log the full body to the server error
        // log so the exact cause is recoverable from CloudPanel → Logs.
        $reason = '';
        if (!$ok) {
            $reason = trim((string) (
                ($res['raw']['message'] ?? '')
                . (isset($res['raw']['code']) ? ' [' . $res['raw']['code'] . ']' : '')
            ));
            if ($reason === '') {
                $reason = 'no response from payment provider'
                    . (isset($res['raw']['http_status']) ? ' (HTTP ' . $res['raw']['http_status'] . ')' : '')
                    . (!empty($res['raw']['error']) ? ': ' . $res['raw']['error'] : '');
            }
            error_log('[Moolre paymentLink FAILED] ' . json_encode($res['raw']));
        }

        return [
            'ok' => $ok,
            'url' => $url,
            'reason' => $reason,
            'external_ref' => (string) ($res['raw']['data']['reference'] ?? ''),
            'raw' => $res['raw'],
        ];
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
     * Whether SMS is sent for real. Independent of PAYMENTS_MODE so real SMS can
     * run while collections/disbursements stay in mock:
     *   SMS_MODE=live  -> always send real SMS (needs MOOLRE_VAS_KEY + sender ID)
     *   SMS_MODE=mock  -> never send, just log
     *   unset          -> follow PAYMENTS_MODE
     */
    public function smsIsLive(): bool
    {
        $mode = strtolower(trim((string) Config::get('SMS_MODE', '')));
        if ($mode === 'live') {
            return true;
        }
        if ($mode === 'mock') {
            return false;
        }
        return !$this->isMock();
    }

    /** Approved Moolre Sender ID (max 11 chars). */
    public function smsSender(): string
    {
        $s = trim((string) Config::get('MOOLRE_SMS_SENDER', ''));
        if ($s === '') {
            $s = 'PaySmall';
        }
        return substr($s, 0, 11);
    }

    /**
     * Send an SMS via Moolre (POST /open/sms/send). Always logged to sms_log.
     * Never throws — an SMS failure must not break a payment flow. Returns true
     * if the provider accepted it (response status == 1 / SMS01).
     *
     * $forceLive lets the admin "send test SMS" tool hit the real API even when
     * SMS_MODE is mock, so the integration can be verified on demand.
     */
    public function sms(string $phone, string $body, bool $forceLive = false): bool
    {
        $ref = 'SMS-' . strtoupper(bin2hex(random_bytes(6)));

        if (!$forceLive && !$this->smsIsLive()) {
            SmsLog::create($phone, $body, 'sent', 'MOCK-' . $ref);
            return true;
        }

        try {
            $res = $this->call(Config::get('MOOLRE_PATH_SMS', '/open/sms/send'), [
                'type' => 1,
                'senderid' => $this->smsSender(),
                'messages' => [
                    ['recipient' => $phone, 'message' => $body, 'ref' => $ref],
                ],
            ], vas: true);
            SmsLog::create($phone, $body, $res['ok'] ? 'sent' : 'failed', $ref);
            return $res['ok'];
        } catch (\Throwable $e) {
            SmsLog::create($phone, $body, 'failed', '');
            return false;
        }
    }

    /**
     * Query delivery status of previously-sent messages by their refs
     * (POST /open/sms/status, type 5). Safe — sends nothing. Used by the admin
     * connectivity check and the delivery-status poll below.
     */
    public function smsStatus(array $refs): array
    {
        return $this->call(Config::get('MOOLRE_PATH_SMS_STATUS', '/open/sms/status'), [
            'type' => 5,
            'ref' => array_values($refs),
        ], vas: true);
    }

    /**
     * Poll Moolre for the delivery outcome of accepted-but-not-final SMS and
     * write it back to sms_log. Moolre per-message status codes:
     *   0 = Unknown, 1 = Sent, 2 = Delivered, 3 = Failed.
     * Returns a summary of what changed. Never throws.
     */
    public function refreshSmsDelivery(int $limit = 100): array
    {
        $summary = ['checked' => 0, 'delivered' => 0, 'failed' => 0, 'pending' => 0];

        if (!$this->smsIsLive() || Config::get('MOOLRE_VAS_KEY', '') === '') {
            return $summary;
        }

        $rows = SmsLog::pendingDelivery($limit);
        if (!$rows) {
            return $summary;
        }

        try {
            $res = $this->smsStatus(array_column($rows, 'provider_ref'));
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

    /**
     * Query a transaction's final status by our reference.
     * Returns ['ok' => bool (API reachable), 'state' => 'success'|'pending'|'failed', 'external_ref' => string, 'raw' => array].
     */
    public function status(string $reference): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'state' => 'success', 'external_ref' => '', 'raw' => ['mode' => 'mock']];
        }

        // Confirmed against the live API: status queries need type=1 and an
        // idtype (1 = look up by our externalref). Without both, the API rejects
        // the request (SS04) and nothing ever reconciles.
        $res = $this->call(Config::get('MOOLRE_PATH_STATUS', ''), [
            'type' => 1,
            'idtype' => Config::int('MOOLRE_STATUS_IDTYPE', 1),
            'id' => $reference,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
        ]);

        // "Transaction not found" (SS07) comes back as status:1 with txstatus:3.
        // That must NOT be read as a failure — the transaction may simply not be
        // queryable yet. Leave it pending so we retry, never wrongly fail it.
        if (strtoupper((string) ($res['raw']['code'] ?? '')) === 'SS07') {
            $res['state'] = 'pending';
            return $res;
        }

        $res['state'] = $this->readState($res['raw']);
        return $res;
    }

    /**
     * List account transactions (POST /open/account/status, type 2). Optional
     * filters: status ('0' pending, '1' success, '2' failed), startdate, enddate,
     * limit. Returns the raw call() result (data.transactions[]).
     */
    public function listTransactions(array $filters = []): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'instant' => false, 'external_ref' => '', 'raw' => ['data' => ['transactions' => []]]];
        }
        return $this->call(Config::get('MOOLRE_PATH_ACCOUNT', '/open/account/status'), array_merge([
            'type' => 2,
            'accountnumber' => Config::get('MOOLRE_ACCOUNT_NUMBER', ''),
            'limit' => '200',
        ], $filters));
    }

    /**
     * Return settled (successful) collections that could be a given payment.
     * Payment-link (POS) payments carry no usable externalref (Moolre stores
     * "0"), so we match on the exact amount within a time window and let the
     * caller pick one whose Moolre transactionid it hasn't already credited.
     * Exact externalref matches (if Moolre ever provides them) are preferred and
     * sorted first; otherwise newest first.
     *
     * @return list<array{transactionid:string, externalref:string, amount:string, ts:string, refMatch:bool, raw:array}>
     */
    public function settledCollectionCandidates(string $externalref, int $amountPesewas, string $sinceTs = ''): array
    {
        if ($this->isMock()) {
            return [];
        }

        $filters = ['status' => '1']; // successful only
        if ($sinceTs !== '') {
            $filters['startdate'] = $sinceTs;
        }
        $res = $this->listTransactions($filters);
        $list = $res['raw']['data']['transactions'] ?? [];
        if (!is_array($list)) {
            return [];
        }

        $amount = $this->toCedis($amountPesewas);
        $out = [];
        foreach ($list as $t) {
            if ((int) ($t['txstatus'] ?? 0) !== 1) {
                continue;
            }
            $ext = (string) ($t['externalref'] ?? '');
            $refMatch = $ext !== '' && $ext !== '0' && $ext === $externalref;
            $amtMatch = (string) ($t['amount'] ?? '') === $amount;
            if ($refMatch || $amtMatch) {
                $out[] = [
                    'transactionid' => (string) ($t['transactionid'] ?? ''),
                    'externalref' => $ext,
                    'amount' => (string) ($t['amount'] ?? ''),
                    'ts' => (string) ($t['ts'] ?? ''),
                    'refMatch' => $refMatch,
                    'raw' => $t,
                ];
            }
        }
        // Exact ref matches first, then newest.
        usort($out, static fn ($a, $b) => ($b['refMatch'] <=> $a['refMatch']) ?: strcmp($b['ts'], $a['ts']));
        return $out;
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
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        // The exact place Moolre puts the secret varies; check the likely spots.
        $candidates = [
            $headers['x-webhook-secret'] ?? null,
            $headers['x-moolre-secret'] ?? null,
            $headers['x-secret'] ?? null,
            $headers['secret'] ?? null,
            $payload['secret'] ?? null,
            $payload['secretkey'] ?? null,
            $payload['secret_key'] ?? null,
            $data['secret'] ?? null,
        ];
        foreach ($candidates as $sent) {
            if (is_string($sent) && $sent !== '' && hash_equals($secret, $sent)) {
                return true;
            }
        }
        return false;
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

        // Moolre txstatus numeric codes: 1 = success, 2 = pending/processing,
        // 3 = failed. Only ever credit on an explicit success and only ever fail
        // on an explicit failure — anything else (2, 0, unknown) stays pending so
        // it is retried, never wrongly credited or wrongly failed.
        $success = ['1', 'success', 'successful', 'paid', 'completed', 'complete', 'approved'];
        $failed = ['3', 'failed', 'failure', 'declined', 'cancelled', 'canceled', 'reversed', 'expired', 'rejected'];

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
        ];
        if ($vas) {
            // SMS / VAS endpoints authenticate with the VAS key only (per docs).
            $headers[] = 'X-API-VASKEY: ' . Config::get('MOOLRE_VAS_KEY', '');
        } else {
            $headers[] = 'X-API-USER: ' . Config::get('MOOLRE_API_USER', '');
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
