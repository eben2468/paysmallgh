<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database as DB;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\User;

/**
 * USSD menu state machine. Every screen must fit in 160 characters.
 *
 * Menu:
 *   1. My plans  -> pick a plan -> progress + amount left
 *   2. Pay installment -> pick a plan -> confirm -> charge
 *   3. Help
 */
final class UssdMenu
{
    /** @return array{0: string, 1: bool} [screen text, continue session] */
    public function handle(string $sessionId, string $phone, string $input): array
    {
        $session = $this->loadSession($sessionId, $phone);
        $state = $session['state'];
        $ctx = json_decode($session['context'] ?? '{}', true) ?: [];

        $user = User::findByPhone($phone);
        if (!$user) {
            return ['PaySmallSmall: this number has no account yet. Visit paysmallsmall.com or any partner shop to start a plan.', false];
        }

        // Fresh dial (no input yet) → main menu
        if ($input === '' || $state === 'new') {
            $this->saveSession($sessionId, $phone, 'menu', []);
            return [$this->mainMenu($user), true];
        }

        switch ($state) {
            case 'menu':
                return $this->fromMainMenu($sessionId, $phone, $input, $user);

            case 'plans_list':
            case 'pay_list':
                return $this->fromPlanList($sessionId, $phone, $input, $user, $state, $ctx);

            case 'pay_confirm':
                return $this->fromPayConfirm($sessionId, $phone, $input, $ctx);
        }

        return ['Sorry, lost track of where we were. Dial again.', false];
    }

    private function mainMenu(array $user): string
    {
        $first = explode(' ', $user['name'])[0];
        return "PaySmallSmall - Akwaaba {$first}!\n1. My plans\n2. Pay installment\n3. Help";
    }

    private function fromMainMenu(string $sessionId, string $phone, string $input, array $user): array
    {
        $plans = array_values(array_filter(
            Plan::forCustomer((int) $user['id']),
            fn($p) => $p['status'] === 'active'
        ));

        switch ($input) {
            case '1':
            case '2':
                if (!$plans) {
                    return ['You have no active plans right now. Start one at any partner shop or online.', false];
                }
                $lines = [];
                foreach (array_slice($plans, 0, 4) as $i => $p) {
                    $lines[] = ($i + 1) . '. ' . mb_substr($p['product_name'], 0, 22);
                }
                $ids = array_map(fn($p) => (int) $p['id'], array_slice($plans, 0, 4));
                $nextState = $input === '1' ? 'plans_list' : 'pay_list';
                $this->saveSession($sessionId, $phone, $nextState, ['ids' => $ids]);
                $head = $input === '1' ? 'Your plans:' : 'Pay which plan?';
                return [$head . "\n" . implode("\n", $lines), true];

            case '3':
                return ["Pay for items small small via MoMo. Money is safe in escrow till you finish. Questions? Call 030 000 0000. PaySmallSmall", false];
        }

        return ["Pick 1, 2 or 3.\n" . $this->mainMenu($user), true];
    }

    private function fromPlanList(string $sessionId, string $phone, string $input, array $user, string $state, array $ctx): array
    {
        $ids = $ctx['ids'] ?? [];
        $idx = (int) $input - 1;
        if (!isset($ids[$idx])) {
            return ['Pick a number from the list. Dial again.', false];
        }
        $plan = Plan::find((int) $ids[$idx]);
        if (!$plan || (int) $plan['customer_id'] !== (int) $user['id']) {
            return ['Plan not found. Dial again.', false];
        }

        $paid = (int) $plan['installments_paid'];
        $total = (int) $plan['installments_total'];
        $left = ($total - $paid) * (int) $plan['installment_pesewas'];
        $name = mb_substr($plan['product_name'], 0, 24);

        if ($state === 'plans_list') {
            return ["{$name}: {$paid}/{$total} paid. " . ghs($left) . ' left. Next: ' . ghs((int) $plan['installment_pesewas']) . '. PaySmallSmall', false];
        }

        // pay_list → confirm
        $this->saveSession($sessionId, $phone, 'pay_confirm', ['plan_id' => (int) $plan['id']]);
        return ["Pay " . ghs((int) $plan['installment_pesewas']) . " for {$name}?\n1. Yes, charge my MoMo\n2. No", true];
    }

    private function fromPayConfirm(string $sessionId, string $phone, string $input, array $ctx): array
    {
        if ($input !== '1') {
            return ['No wahala. Nothing was charged.', false];
        }
        $planId = (int) ($ctx['plan_id'] ?? 0);
        $svc = new PlanService();
        $result = $svc->collectInstallment($planId);

        return match ($result) {
            'completed' => ['That was your LAST payment - the item is fully yours! SMS receipt is coming.', false],
            'active' => ['Payment received! SMS receipt is coming. Medaase.', false],
            'awaiting_payment' => ['Approve the MoMo prompt on your phone to finish. PaySmallSmall', false],
            default => ["Payment didn't go through. Check your MoMo balance and try again.", false],
        };
    }

    private function loadSession(string $id, string $phone): array
    {
        $row = DB::run('SELECT * FROM ussd_sessions WHERE id = ?', [$id])->fetch();
        if ($row) {
            return $row;
        }
        DB::run('INSERT INTO ussd_sessions (id, phone, state, context) VALUES (?, ?, ?, ?)', [$id, $phone, 'new', '{}']);
        return ['id' => $id, 'phone' => $phone, 'state' => 'new', 'context' => '{}'];
    }

    private function saveSession(string $id, string $phone, string $state, array $context): void
    {
        DB::run(
            'INSERT INTO ussd_sessions (id, phone, state, context) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE state = VALUES(state), context = VALUES(context), updated_at = NOW()',
            [$id, $phone, $state, json_encode($context)]
        );
    }
}
