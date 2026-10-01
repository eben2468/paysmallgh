<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * The ONLY class that talks to Paystack. Everything else goes through here.
 *
 * PAYMENTS_MODE:
 *   mock    — no network. Collections/payouts/refunds succeed instantly so the
 *             whole product can be demoed end-to-end. Build/demo path.
 *   sandbox — real HTTP calls with Paystack TEST keys (sk_test_…).
 *   live    — real HTTP calls with LIVE keys (sk_live_…).
 * Paystack has one base URL for both; the secret key decides test vs live.
 *
 * Wire format (confirmed against Paystack's OpenAPI spec + docs samples):
 *   - Base https://api.paystack.co, auth header "Authorization: Bearer <secret>".
 *   - Every response is {status: bool, message: string, data: …}.
 *   - Amounts are integers in the currency's subunit — pesewas for GHS — which
 *     is exactly how we store money, so there is no conversion anywhere.
 *
 * Endpoints used:
 *   POST /transaction/initialize      hosted checkout (web: MoMo / card / bank)
 *   GET  /transaction/verify/{ref}     final state of a collection
 *   POST /charge                       direct MoMo prompt (USSD flow)
 *   POST /transferrecipient            register a merchant's payout account
 *   POST /transfer                     merchant payout
 *   GET  /transfer/verify/{ref}        final state of a payout
 *   POST /refund, GET /refund/{id}     customer refund on cancellation
 *   GET  /bank?currency=GHS            Ghana banks + MoMo networks (payout forms)
 *
 * Live/sandbox money movements are ASYNCHRONOUS: an API call only *accepts*
 * the request. The final result arrives via the signed webhook
 * (/webhook/paystack) or by polling the verify endpoints (PlanService
 * reconcile). Mock mode short-circuits this by returning instant success.
 */
final class PaystackService
{
    /** Paystack's Ghana mobile-money network codes (List Banks, type=mobile_money). */
    public const MOMO_NETWORKS = [
        'MTN' => 'MTN MoMo',
        'VOD' => 'Telecel Cash',
        'ATL' => 'AirtelTigo Money',
    ];

    public function mode(): string
    {
        return Config::get('PAYMENTS_MODE', 'mock');
    }

    public function isMock(): bool
    {
        return $this->mode() === 'mock';
    }

    /** True when a secret key is set, so real API calls are possible. */
    public function hasKeys(): bool
    {
        return trim((string) Config::get('PAYSTACK_SECRET_KEY', '')) !== '';
    }

    /**
     * A unique reference for a money movement. Lowercase letters, digits and
     * dashes only, at least 16 chars — valid for every Paystack endpoint
     * (transfers are the strictest: lowercase alphanumerics, "-" and "_").
     * $kind: c = collection, d = disbursement, r = refund (ledger-only).
     */
    public static function newReference(string $kind, int ...$parts): string
    {
        $ref = 'pss-' . $kind . '-' . implode('-', $parts) . '-' . bin2hex(random_bytes(5));
        return strtolower($ref);
    }

    /**
     * The email Paystack requires for every charge. Customers sign up with a
     * phone number only, so we use a stable per-phone address on our own domain.
     */
    public function customerEmail(string $phone): string
    {
        $domain = trim((string) Config::get('PAYSTACK_EMAIL_DOMAIN', ''));
        if ($domain === '') {
            $domain = 'paysmallsmall.com';
        }
        return $phone . '@' . $domain;
    }

    /**
     * Hosted Paystack checkout for a collection. The customer opens the
     * returned URL and pays with MoMo, card or bank on Paystack's page, then is
     * sent back to $callbackUrl with ?trxref=…&reference=… appended.
     *
     * Returns ['ok' => bool, 'url' => string, 'reason' => string,
     *          'external_ref' => string, 'raw' => array].
     */
    public function paymentLink(string $phone, int $amountPesewas, string $reference, string $description, string $callbackUrl, array $metadata = []): array
    {
        if ($this->isMock()) {
            // Mock: a local stand-in checkout so the redirect flow demos end to
            // end without spending money. Root-relative so it works on whatever
            // host/port the app is served from.
            return [
                'ok' => true,
                'url' => '/checkout/mock?ref=' . urlencode($reference),
                'reason' => '',
                'external_ref' => '',
                'raw' => ['mode' => 'mock'],
            ];
        }

        $body = [
            'email' => $this->customerEmail($phone),
            'amount' => $amountPesewas,
            'currency' => $this->currency(),
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => array_merge($metadata, [
                'phone' => $phone,
                'description' => $description,
            ]),
        ];
        $channels = $this->checkoutChannels();
        if ($channels) {
            $body['channels'] = $channels;
        }

        $res = $this->request('POST', '/transaction/initialize', $body);
        $url = (string) ($res['raw']['data']['authorization_url'] ?? '');
        $ok = $res['ok'] && $url !== '';
        if (!$ok) {
            error_log('[Paystack initialize FAILED] ' . json_encode($res['raw']));
        }

        return [
            'ok' => $ok,
            'url' => $url,
            'reason' => $ok ? '' : $this->reason($res['raw']),
            'external_ref' => (string) ($res['raw']['data']['access_code'] ?? ''),
            'raw' => $res['raw'],
        ];
    }

    /**
     * The hosted checkout URL stored on a pending collection, so a customer who
     * closed the payment page can go straight back to the same one instead of
     * starting a second charge. Null if the transaction has none (e.g. USSD).
     */
    public function checkoutUrlFor(array $tx): ?string
    {
        if ($this->isMock()) {
            return '/checkout/mock?ref=' . urlencode((string) $tx['provider_ref']);
        }
        $raw = json_decode((string) ($tx['raw_payload'] ?? ''), true);
        $url = is_array($raw) ? (string) ($raw['data']['authorization_url'] ?? '') : '';
        return $url !== '' ? $url : null;
    }

    /**
     * Direct mobile-money charge: the customer gets an approval prompt on their
     * phone (used by USSD, where there's no browser). Network is inferred from
     * the number's prefix.
     *
     * Returns ['ok' => bool, 'instant' => bool, 'external_ref' => string,
     *          'reason' => string, 'raw' => array].
     * 'ok'      — charge accepted (customer will be prompted) or already paid.
     * 'instant' — money already moved (mock, or Paystack answered "success").
     */
    public function collect(string $phone, int $amountPesewas, string $reference, string $description, array $metadata = []): array
    {
        if ($this->isMock()) {
            return $this->mockOk();
        }

        $network = momo_network($phone);
        if ($network === null) {
            return $this->fail('Unknown mobile money network for ' . $phone);
        }

        $res = $this->request('POST', '/charge', [
            'email' => $this->customerEmail($phone),
            'amount' => $amountPesewas,
            'currency' => $this->currency(),
            'reference' => $reference,
            'mobile_money' => [
                'phone' => local_phone($phone),
                'provider' => strtolower($network),
            ],
            'metadata' => array_merge($metadata, [
                'phone' => $phone,
                'description' => $description,
            ]),
        ]);

        $status = strtolower((string) ($res['raw']['data']['status'] ?? ''));
        $id = (string) ($res['raw']['data']['id'] ?? '');

        if (!$res['ok']) {
            return $this->fail($this->reason($res['raw']), $res['raw']);
        }
        if ($status === 'success') {
            return ['ok' => true, 'instant' => true, 'external_ref' => $id, 'reason' => '', 'raw' => $res['raw']];
        }
        if ($status === 'failed') {
            return $this->fail($this->reason($res['raw']), $res['raw']);
        }
        if ($status === 'send_otp') {
            // Telecel Cash needs a voucher the customer generates by dialling
            // *110# — impossible to collect inside one USSD session.
            return $this->fail('send_otp', $res['raw']);
        }
        // pay_offline / pending: the customer approves the prompt on their phone.
        return ['ok' => true, 'instant' => false, 'external_ref' => $id, 'reason' => '', 'raw' => $res['raw']];
    }

    /**
     * Pay a merchant (Transfers). Registers the payout account as a Paystack
     * transfer recipient first if it hasn't been yet; the caller should save
     * the returned 'recipient_code' so later payouts skip that step.
     *
     * $merchant needs: owner_name, payout_channel (momo|bank), payout_number,
     * payout_bank_code, paystack_recipient_code.
     *
     * Returns ['ok', 'instant', 'external_ref', 'reason', 'recipient_code', 'raw'].
     */
    public function payout(array $merchant, int $amountPesewas, string $reference, string $reason): array
    {
        if ($this->isMock()) {
            return $this->mockOk() + ['recipient_code' => ''];
        }

        $recipient = (string) ($merchant['paystack_recipient_code'] ?? '');
        if ($recipient === '') {
            $created = $this->createRecipient($merchant);
            if (!$created['ok']) {
                return $this->fail('Payout account not accepted: ' . $created['reason'], $created['raw']) + ['recipient_code' => ''];
            }
            $recipient = $created['recipient_code'];
        }

        $res = $this->request('POST', '/transfer', [
            'source' => 'balance',
            'amount' => $amountPesewas,
            'recipient' => $recipient,
            'reference' => $reference,
            'reason' => mb_substr($reason, 0, 100),
            'currency' => $this->currency(),
        ]);

        if (!$res['ok']) {
            error_log('[Paystack transfer FAILED] ' . json_encode($res['raw']));
            return $this->fail($this->reason($res['raw']), $res['raw']) + ['recipient_code' => $recipient];
        }

        $state = $this->transferState((string) ($res['raw']['data']['status'] ?? ''));
        if ($state === 'failed') {
            return $this->fail($this->reason($res['raw']), $res['raw']) + ['recipient_code' => $recipient];
        }
        if (strtolower((string) ($res['raw']['data']['status'] ?? '')) === 'otp') {
            // Transfers wait for an OTP the dashboard user must enter. Payouts
            // can't be automatic until OTP is disabled (see DEPLOY.md).
            error_log('[Paystack transfer] ' . $reference . ' is waiting for OTP — disable transfer OTP in the Paystack dashboard.');
        }

        return [
            'ok' => true,
            'instant' => $state === 'success',
            'external_ref' => (string) ($res['raw']['data']['transfer_code'] ?? ''),
            'reason' => '',
            'recipient_code' => $recipient,
            'raw' => $res['raw'],
        ];
    }

    /**
     * Refund (part of) a settled collection back to whatever the customer paid
     * with — MoMo wallet or card. $transactionReference is the collection's
     * reference. Returns ['ok', 'instant', 'external_ref' (refund id), 'reason', 'raw'].
     */
    public function refund(string $transactionReference, int $amountPesewas, string $note): array
    {
        if ($this->isMock()) {
            return $this->mockOk();
        }

        $res = $this->request('POST', '/refund', [
            'transaction' => $transactionReference,
            'amount' => $amountPesewas,
            'currency' => $this->currency(),
            'customer_note' => mb_substr($note, 0, 100),
            'merchant_note' => mb_substr($note, 0, 100),
        ]);

        if (!$res['ok']) {
            error_log('[Paystack refund FAILED] ' . json_encode($res['raw']));
            return $this->fail($this->reason($res['raw']), $res['raw']);
        }

        $state = $this->refundState((string) ($res['raw']['data']['status'] ?? ''));
        if ($state === 'failed') {
            return $this->fail($this->reason($res['raw']), $res['raw']);
        }

        return [
            'ok' => true,
            'instant' => $state === 'success',
            'external_ref' => (string) ($res['raw']['data']['id'] ?? ''),
            'reason' => '',
            'raw' => $res['raw'],
        ];
    }

    /**
     * Final state of a collection (GET /transaction/verify/{reference}).
     * A success only counts when the amount AND currency match what we asked
     * for — a mismatch is treated as failed so it can never be credited.
     *
     * Returns ['ok' => bool (API reachable), 'state' => 'success'|'pending'|'failed',
     *          'external_ref' => string, 'message' => string, 'raw' => array].
     */
    public function verifyCollection(string $reference, int $expectedPesewas): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'state' => 'success', 'external_ref' => '', 'message' => '', 'raw' => ['mode' => 'mock']];
        }

        $res = $this->request('GET', '/transaction/verify/' . rawurlencode($reference));
        $data = is_array($res['raw']['data'] ?? null) ? $res['raw']['data'] : [];
        $state = $res['ok'] ? $this->collectionState((string) ($data['status'] ?? '')) : 'pending';

        if ($state === 'success') {
            $amount = (int) ($data['amount'] ?? -1);
            $currency = strtoupper((string) ($data['currency'] ?? ''));
            if ($amount !== $expectedPesewas || $currency !== $this->currency()) {
                error_log("[Paystack verify] {$reference} MISMATCH: paid {$amount} {$currency}, expected {$expectedPesewas} " . $this->currency());
                $state = 'failed';
            }
        }

        return [
            // A 404 ("reference not found") is a reachable API with no record yet.
            'ok' => $res['ok'] || $res['http_status'] === 404,
            'state' => $state,
            'external_ref' => (string) ($data['id'] ?? ''),
            'message' => (string) ($data['gateway_response'] ?? $res['raw']['message'] ?? ''),
            'paystack_status' => strtolower((string) ($data['status'] ?? '')),
            'raw' => $res['raw'],
        ];
    }

    /** Final state of a payout (GET /transfer/verify/{reference}). Same shape as verifyCollection(). */
    public function verifyTransfer(string $reference): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'state' => 'success', 'external_ref' => '', 'message' => '', 'raw' => ['mode' => 'mock']];
        }

        $res = $this->request('GET', '/transfer/verify/' . rawurlencode($reference));
        $data = is_array($res['raw']['data'] ?? null) ? $res['raw']['data'] : [];
        return [
            'ok' => $res['ok'],
            'state' => $res['ok'] ? $this->transferState((string) ($data['status'] ?? '')) : 'pending',
            'external_ref' => (string) ($data['transfer_code'] ?? ''),
            'message' => (string) ($res['raw']['message'] ?? ''),
            'raw' => $res['raw'],
        ];
    }

    /** Final state of a refund (GET /refund/{id}). Same shape as verifyCollection(). */
    public function verifyRefund(string $refundId): array
    {
        if ($this->isMock()) {
            return ['ok' => true, 'state' => 'success', 'external_ref' => $refundId, 'message' => '', 'raw' => ['mode' => 'mock']];
        }
        if ($refundId === '') {
            return ['ok' => false, 'state' => 'pending', 'external_ref' => '', 'message' => 'no refund id', 'raw' => []];
        }

        $res = $this->request('GET', '/refund/' . rawurlencode($refundId));
        $data = is_array($res['raw']['data'] ?? null) ? $res['raw']['data'] : [];
        return [
            'ok' => $res['ok'],
            'state' => $res['ok'] ? $this->refundState((string) ($data['status'] ?? '')) : 'pending',
            'external_ref' => $refundId,
            'message' => (string) ($res['raw']['message'] ?? ''),
            'raw' => $res['raw'],
        ];
    }

    /**
     * Ghana banks Paystack can pay out to, as [code => name], for the merchant
     * payout form. Cached on disk for a day. Empty when no keys are configured
     * or the API can't be reached — the form then falls back to a code field.
     */
    public function ghanaBanks(): array
    {
        if (!$this->hasKeys()) {
            return [];
        }
        $cache = BASE_PATH . '/storage/paystack_banks.json';
        if (is_file($cache) && filemtime($cache) > time() - 86400) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (is_array($cached) && $cached) {
                return $cached;
            }
        }

        $res = $this->request('GET', '/bank?currency=GHS&perPage=100');
        $banks = [];
        foreach ((array) ($res['raw']['data'] ?? []) as $b) {
            // MoMo networks are offered separately; keep real banks only.
            if (!is_array($b) || ($b['type'] ?? '') === 'mobile_money' || empty($b['active']) || empty($b['code'])) {
                continue;
            }
            $banks[(string) $b['code']] = (string) $b['name'];
        }
        asort($banks);
        if ($banks) {
            @file_put_contents($cache, json_encode($banks));
        }
        return $banks;
    }

    /**
     * Verify a webhook really came from Paystack: x-paystack-signature must be
     * the HMAC-SHA512 of the raw request body keyed with our secret key.
     */
    public function verifySignature(string $rawBody, string $signature): bool
    {
        $secret = (string) Config::get('PAYSTACK_SECRET_KEY', '');
        if ($secret === '' || $signature === '') {
            return false;
        }
        return hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    // ---- state mapping: only an explicit success credits, only an explicit
    // failure fails; anything else stays pending and is retried. ----

    private function collectionState(string $status): string
    {
        return match (strtolower($status)) {
            'success' => 'success',
            'failed', 'reversed' => 'failed',
            default => 'pending', // abandoned, ongoing, pending, processing, queued…
        };
    }

    private function transferState(string $status): string
    {
        return match (strtolower($status)) {
            'success' => 'success',
            'failed', 'reversed', 'rejected', 'abandoned', 'blocked' => 'failed',
            default => 'pending', // pending, otp, received, queued…
        };
    }

    private function refundState(string $status): string
    {
        return match (strtolower($status)) {
            'processed' => 'success',
            'failed' => 'failed',
            default => 'pending', // pending, processing, needs-attention…
        };
    }

    // ---- internals ----

    /** Register the merchant's payout account (POST /transferrecipient). */
    private function createRecipient(array $merchant): array
    {
        $isBank = ($merchant['payout_channel'] ?? 'momo') === 'bank';
        $code = strtoupper(trim((string) ($merchant['payout_bank_code'] ?? '')));
        $number = (string) ($merchant['payout_number'] ?? '');
        if (!$isBank && $code === '') {
            $code = (string) momo_network($number);
        }
        if ($code === '' || $number === '') {
            return ['ok' => false, 'recipient_code' => '', 'reason' => 'payout network/bank or number missing', 'raw' => []];
        }

        $res = $this->request('POST', '/transferrecipient', [
            'type' => $isBank ? 'ghipss' : 'mobile_money',
            'name' => (string) ($merchant['owner_name'] ?? $merchant['shop_name'] ?? 'Merchant'),
            'account_number' => $isBank ? $number : local_phone($number),
            'bank_code' => $code,
            'currency' => $this->currency(),
            'description' => 'PaySmallSmall merchant #' . (int) ($merchant['id'] ?? 0),
        ]);
        $rc = (string) ($res['raw']['data']['recipient_code'] ?? '');
        return [
            'ok' => $res['ok'] && $rc !== '',
            'recipient_code' => $rc,
            'reason' => $this->reason($res['raw']),
            'raw' => $res['raw'],
        ];
    }

    private function currency(): string
    {
        return strtoupper((string) Config::get('PAYSTACK_CURRENCY', 'GHS')) ?: 'GHS';
    }

    /** Channels shown on hosted checkout, from PAYSTACK_CHANNELS (comma list). Empty = Paystack default. */
    private function checkoutChannels(): array
    {
        $raw = (string) Config::get('PAYSTACK_CHANNELS', '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function mockOk(): array
    {
        return [
            'ok' => true,
            'instant' => true,
            'external_ref' => 'MOCK-' . strtoupper(bin2hex(random_bytes(5))),
            'reason' => '',
            'raw' => ['mode' => 'mock'],
        ];
    }

    private function fail(string $reason, array $raw = []): array
    {
        return ['ok' => false, 'instant' => false, 'external_ref' => '', 'reason' => $reason, 'raw' => $raw ?: ['error' => $reason]];
    }

    /** Human reason for a failed call: Paystack's message, or the transport error. */
    private function reason(array $raw): string
    {
        $msg = trim((string) ($raw['data']['gateway_response'] ?? $raw['message'] ?? ''));
        if ($msg !== '') {
            return $msg;
        }
        return 'no response from payment provider'
            . (isset($raw['http_status']) ? ' (HTTP ' . $raw['http_status'] . ')' : '')
            . (!empty($raw['error']) ? ': ' . $raw['error'] : '');
    }

    /**
     * One HTTP call to Paystack. Never throws.
     * Returns ['ok' => bool, 'http_status' => int, 'raw' => array] where ok means
     * HTTP 2xx AND Paystack's envelope status === true.
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $url = rtrim((string) Config::get('PAYSTACK_BASE_URL', 'https://api.paystack.co'), '/') . $path;
        $headers = [
            'Authorization: Bearer ' . Config::get('PAYSTACK_SECRET_KEY', ''),
            'Accept: application/json',
            'Cache-Control: no-cache',
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpStatus = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        $decoded = is_string($response) && $response !== '' ? json_decode($response, true) : null;
        $decoded = is_array($decoded) ? $decoded : [];
        $ok = $httpStatus >= 200 && $httpStatus < 300 && ($decoded['status'] ?? false) === true;

        if (!$decoded) {
            $decoded = ['http_status' => $httpStatus, 'error' => $err, 'body' => is_string($response) ? mb_substr($response, 0, 500) : ''];
        }
        return ['ok' => $ok, 'http_status' => $httpStatus, 'raw' => $decoded];
    }
}
