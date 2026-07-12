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

// --- Static assets -----------------------------------------------------------
// Serve CSS/JS/images/uploads straight from /public. This keeps styling working
// even on hosts that route EVERY request (including asset URLs) through this
// front controller — e.g. shared hosting where the document root can't be set
// to /public. On a correctly configured server the web server serves these
// files before PHP ever runs, so this is just a harmless fallback.
(static function (): void {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    // Strip the app base path when served from a subdirectory (mirror Router).
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir)) ?: '/';
    }
    $path = '/' . ltrim($path, '/');
    if (!preg_match('#^/(assets|uploads)/#', $path)) {
        return; // not an asset — hand back to the app
    }

    $publicDir = realpath(BASE_PATH . '/public');
    $file = realpath(BASE_PATH . '/public' . $path);
    // Must exist and resolve to inside /public (no path traversal).
    if ($file === false || $publicDir === false || !is_file($file) || !str_starts_with($file, $publicDir)) {
        http_response_code(404);
        exit;
    }

    $mimes = [
        'css' => 'text/css', 'js' => 'application/javascript', 'map' => 'application/json',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'avif' => 'image/avif', 'ico' => 'image/x-icon', 'json' => 'application/json',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
    ];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=2592000');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
})();

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

// Run daily background jobs (SMS reminders, payment reconciliation) AFTER the
// response is flushed, so the visitor never waits on them. Throttled to once a
// day inside the scheduler. Registered before dispatch so it still fires when a
// controller ends the request with exit()/redirect().
register_shutdown_function(static function (): void {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    \App\Core\Scheduler::runDaily();
});

$router = new Router();
require BASE_PATH . '/app/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
