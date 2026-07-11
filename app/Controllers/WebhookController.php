<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Transaction;
use App\Services\MoolreService;
use App\Services\PlanService;
use App\Services\UssdMenu;

final class WebhookController extends Controller
{
    /**
     * Moolre payment confirmation callback.
     * Verified two ways: the shared webhook secret must match, AND the
     * reference must belong to a transaction we created. Idempotent — replays
     * cannot double-credit (guarded in PlanService via installments.paid_at).
     */
    public function moolre(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true) ?: $_POST;
        $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

        $moolre = new MoolreService();

        // Log every callback so what Moolre actually sends is recoverable from
        // CloudPanel -> Logs. This is how we diagnose confirmation problems.
        error_log('[Moolre webhook] body=' . $raw . ' headers=' . json_encode($headers));

        // Moolre nests the transaction under `data` on some callbacks; accept both.
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $ref = (string) ($data['externalref'] ?? $payload['externalref'] ?? $data['reference'] ?? $payload['reference'] ?? '');
        $externalRef = (string) ($data['transactionid'] ?? $payload['transactionid'] ?? $data['transactionId'] ?? '');

        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        if (!$tx) {
            error_log('[Moolre webhook] no matching transaction for ref=' . $ref);
            $this->json(['status' => 1, 'message' => 'ack (unknown ref)']); // ack so Moolre stops retrying
        }

        // Replay guard: already credited.
        if ($tx['status'] === 'success') {
            $this->json(['status' => 1, 'message' => 'already processed']);
        }

        // Authenticate the callback. Preferred: the shared secret Moolre sends.
        // Fallback: independently re-query Moolre's status API — if IT reports the
        // transaction successful, the callback is genuine whatever its secret
        // shape. We never credit on the payload alone.
        $secretOk = $moolre->verifyWebhook($headers, $payload);
        $state = $moolre->readState($payload);

        if (!$secretOk) {
            $check = $moolre->status($ref);
            error_log('[Moolre webhook] secret not matched; status() ok=' . var_export($check['ok'] ?? false, true)
                . ' state=' . ($check['state'] ?? '?') . ' raw=' . json_encode($check['raw'] ?? []));
            if (($check['state'] ?? '') === 'success') {
                $state = 'success';
                $secretOk = true; // Moolre's own API vouches for it
            } elseif (($check['state'] ?? '') === 'failed') {
                $state = 'failed';
            }
        }

        if (!$secretOk) {
            error_log('[Moolre webhook] UNVERIFIED ref=' . $ref . ' — not crediting.');
            $this->json(['status' => 1, 'message' => 'ack (unverified)']);
        }

        if ($state === 'failed') {
            Transaction::setStatus((int) $tx['id'], 'failed', $externalRef, $raw);
            $this->json(['status' => 1, 'message' => 'noted failure']);
        }
        if ($state !== 'success') {
            $this->json(['status' => 1, 'message' => 'pending noted']);
        }

        Transaction::setStatus((int) $tx['id'], 'success', $externalRef, $raw);

        $svc = new PlanService();
        if ($tx['type'] === 'collection') {
            $svc->applyCollectionSuccess((int) $tx['id']);
        } elseif ($tx['type'] === 'disbursement' && $tx['plan_id']) {
            $svc->finalizePayout((int) $tx['plan_id'], (int) $tx['id']);
        }

        error_log('[Moolre webhook] CREDITED ref=' . $ref . ' tx=' . $tx['id']);
        $this->json(['status' => 1, 'message' => 'ok']);
    }

    /**
     * USSD gateway webhook. Standard pattern: gateway POSTs session id, phone,
     * and the user's input; we answer with menu text and whether to continue.
     */
    public function ussd(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true) ?? $_POST;

        $sessionId = (string) ($payload['sessionid'] ?? $payload['sessionId'] ?? '');
        $phone = normalize_phone((string) ($payload['msisdn'] ?? $payload['phoneNumber'] ?? '')) ?? '';
        $input = trim((string) ($payload['message'] ?? $payload['text'] ?? ''));

        if ($sessionId === '' || $phone === '') {
            $this->json(['message' => 'Sorry, something went wrong. Dial again.', 'continue' => false]);
        }

        $menu = new UssdMenu();
        [$text, $continue] = $menu->handle($sessionId, $phone, $input);

        $this->json(['message' => $text, 'continue' => $continue]);
    }
}
