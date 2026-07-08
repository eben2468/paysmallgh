<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router. Routes are "METHOD /path" with {param} placeholders.
 */
final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, string $controller, string $method): void
    {
        $this->routes['GET'][$path] = [$controller, $method];
    }

    public function post(string $path, string $controller, string $method): void
    {
        $this->routes['POST'][$path] = [$controller, $method];
    }

    public function dispatch(string $httpMethod, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // Strip the base path when the app is served from a subdirectory (XAMPP dev).
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir)) ?: '/';
        }
        $path = '/' . trim($path, '/');

        $table = $this->routes[$httpMethod] ?? [];

        // Exact match first
        if (isset($table[$path])) {
            $this->invoke($table[$path], []);
            return;
        }

        // Placeholder match
        foreach ($table as $route => $handler) {
            if (!str_contains($route, '{')) {
                continue;
            }
            $pattern = '#^' . preg_replace('#\{[a-z_]+\}#', '([^/]+)', $route) . '$#';
            if (preg_match($pattern, $path, $m)) {
                array_shift($m);
                $this->invoke($handler, $m);
                return;
            }
        }

        http_response_code(404);
        $view = new View();
        echo $view->render('errors/404', ['title' => 'Page not found']);
    }

    private function invoke(array $handler, array $params): void
    {
        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method(...$params);
    }
}
