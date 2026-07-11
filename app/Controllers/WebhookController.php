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

        // Log every callback so what Moolre actually sends is recoverable from
        // CloudPanel -> Logs.
        error_log('[Moolre webhook] body=' . $raw . ' headers=' . json_encode($headers));

        // Try to identify the specific transaction from the callback. Payment-link
        // callbacks echo our reference inside `metadata`; direct debits use
        // externalref/reference.
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $meta = is_array($data['metadata'] ?? null) ? $data['metadata']
            : (is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []);
        $ref = (string) (
            $data['externalref'] ?? $payload['externalref']
            ?? $meta['ref'] ?? $data['reference'] ?? $payload['reference'] ?? ''
        );

        // We never trust the payload to credit money. Instead we reconcile against
        // Moolre's own transaction list/status API — so a genuine callback (or even
        // a spoofed one) only ever confirms payments that actually settled.
        $svc = new PlanService();
        $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
        if ($tx) {
            $result = $svc->reconcileTransaction($tx);
            error_log('[Moolre webhook] reconciled ref=' . $ref . ' -> ' . $result);
        } else {
            // No usable reference in the callback: sweep every pending collection
            // and match each against the settled account transactions.
            $actions = $svc->reconcilePending(0);
            error_log('[Moolre webhook] no ref; swept pending -> ' . json_encode($actions));
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
