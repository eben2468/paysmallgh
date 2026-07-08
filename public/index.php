<?php
declare(strict_types=1);

/**
 * PaySmallSmall front controller.
 */

define('BASE_PATH', dirname(__DIR__));

// PSR-4-ish autoloader for the App namespace. No Composer needed for the skeleton.
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

use App\Core\Auth;
use App\Core\Config;
use App\Core\Router;

Config::load(BASE_PATH . '/.env');

if (Config::bool('APP_DEBUG')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

Auth::start();

$router = new Router();
require BASE_PATH . '/app/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
