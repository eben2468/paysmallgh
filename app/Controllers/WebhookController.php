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
        $payload = json_decode($raw, true) ?? $_POST;
        $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);

        $moolre = new MoolreService();
        if (!$moolre->verifyWebhook($headers, $payload)) {
            $this->json(['status' => 0, 'message' => 'unverified'], 401);
        }

        // Moolre nests the transaction under `data` on some callbacks; accept both.
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $ref = (string) ($data['externalref'] ?? $payload['externalref'] ?? $data['reference'] ?? $payload['reference'] ?? '');
        $externalRef = (string) ($data['transactionid'] ?? $payload['transactionid'] ?? '');

        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        if (!$tx) {
            $this->json(['status' => 0, 'message' => 'unknown reference'], 404);
        }

        // Same success/failed/pending mapping as status polling — never fail a
        // transaction on a pending/ambiguous callback; just acknowledge and wait
        // for the final one.
        $state = $moolre->readState($payload);
        if ($state === 'failed') {
            if ($tx['status'] !== 'success') {
                Transaction::setStatus((int) $tx['id'], 'failed', $externalRef, $raw);
            }
            $this->json(['status' => 1, 'message' => 'noted failure']);
        }
        if ($state !== 'success') {
            $this->json(['status' => 1, 'message' => 'pending noted']);
        }

        // Replay guard: if already success, acknowledge and do nothing.
        if ($tx['status'] === 'success') {
            $this->json(['status' => 1, 'message' => 'already processed']);
        }

        Transaction::setStatus((int) $tx['id'], 'success', $externalRef, $raw);

        $svc = new PlanService();
        if ($tx['type'] === 'collection') {
            $svc->applyCollectionSuccess((int) $tx['id']);
        } elseif ($tx['type'] === 'disbursement' && $tx['plan_id']) {
            $svc->finalizePayout((int) $tx['plan_id'], (int) $tx['id']);
        }

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
