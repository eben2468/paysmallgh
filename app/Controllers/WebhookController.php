<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Transaction;
use App\Services\PaystackService;
use App\Services\PlanService;
use App\Services\UssdMenu;

final class WebhookController extends Controller
{
    /**
     * Paystack event webhook.
     *
     * Trust model: the x-paystack-signature header must be the HMAC-SHA512 of
     * the raw body under our secret key, or the event is ignored. Even then the
     * payload never credits money by itself — we look up the reference in our
     * own ledger and re-verify it against Paystack's API (reconcileTransaction),
     * which also checks amount and currency. Idempotent: replays can't
     * double-credit (guarded via installments.paid_at + transaction status).
     *
     * Events handled: charge.success, transfer.success|failed|reversed,
     * refund.processed|failed. Everything else is acknowledged and ignored.
     */
    public function paystack(): void
    {
        $raw = file_get_contents('php://input') ?: '';
        $signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');

        if (!(new PaystackService())->verifySignature($raw, $signature)) {
            error_log('[Paystack webhook] rejected: bad or missing signature');
            $this->json(['ok' => false], 401);
        }

        $payload = json_decode($raw, true);
        $event = is_array($payload) ? (string) ($payload['event'] ?? '') : '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        error_log('[Paystack webhook] event=' . $event . ' ref=' . (string) ($data['reference'] ?? $data['transaction_reference'] ?? ''));

        $svc = new PlanService();
        try {
            if ($event === 'charge.success' || str_starts_with($event, 'transfer.')) {
                $ref = (string) ($data['reference'] ?? '');
                $tx = $ref !== '' ? Transaction::findByRef($ref) : null;
                if ($tx) {
                    // charge.success may be a late payment on a checkout we had
                    // already expired — let the API re-check it.
                    $result = $svc->reconcileTransaction($tx, recheckFailed: $event === 'charge.success');
                    if ($event === 'transfer.reversed' && $tx['status'] === 'success') {
                        error_log('[Paystack webhook] PAYOUT REVERSED after success: tx #' . $tx['id'] . ' — follow up manually');
                    }
                    error_log('[Paystack webhook] ' . $event . ' tx #' . $tx['id'] . ' -> ' . $result);
                }
            } elseif (str_starts_with($event, 'refund.')) {
                // Refund events carry the ORIGINAL charge's reference.
                $ref = (string) ($data['transaction_reference'] ?? '');
                $charge = $ref !== '' ? Transaction::findByRef($ref) : null;
                if ($charge && $charge['plan_id']) {
                    $svc->reconcilePlanRefunds((int) $charge['plan_id']);
                }
            }
        } catch (\Throwable $e) {
            // Still answer 200 so Paystack doesn't hammer us; the reconcile cron
            // will settle the transaction.
            error_log('[Paystack webhook] error: ' . $e->getMessage());
        }

        $this->json(['ok' => true]);
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
