<?php
declare(strict_types=1);

/**
 * Reconcile pending payments — the status-check fallback for missed webhooks.
 * Polls Moolre for every pending transaction and applies the result (crediting
 * installments, paying out merchants, sending SMS) exactly as a webhook would.
 *
 * Run every couple of minutes from cron:
 *   * * * * * cd /path/to/app && php scripts/reconcile.php >> ~/reconcile.log 2>&1
 *
 * Safe to run in mock mode (nothing is pending there) and idempotent.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
require BASE_PATH . '/app/Core/helpers.php';

use App\Core\Config;
use App\Services\PlanService;

Config::load(BASE_PATH . '/.env');

$minutes = Config::int('RECONCILE_AFTER_MINUTES', 2);
$actions = (new PlanService())->reconcilePending($minutes);

echo date('c') . ' reconcile: ' . (count($actions) ? implode('; ', $actions) : 'nothing to settle') . "\n";
