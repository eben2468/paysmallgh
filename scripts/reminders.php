<?php
declare(strict_types=1);

/**
 * Daily cron: grace-period reminders and flagging.
 *   php scripts/reminders.php
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
use App\Services\MoolreService;
use App\Services\PlanService;

Config::load(BASE_PATH . '/.env');

$actions = (new PlanService())->runReminders();
echo date('c') . ' reminder sweep: ' . (count($actions) ? implode('; ', $actions) : 'nothing due') . "\n";

// Update delivery status of any SMS still awaiting confirmation.
$sms = (new MoolreService())->refreshSmsDelivery(200);
echo date('c') . " sms delivery poll: {$sms['delivered']} delivered, {$sms['failed']} failed, {$sms['pending']} pending\n";
