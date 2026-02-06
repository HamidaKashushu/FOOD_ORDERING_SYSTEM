<?php
/**
 * Food Ordering System - Simple REST Router
 * Lightweight, custom router for MVC-like REST API (no frameworks)
 *
 * Handles route registration, dynamic parameters, method matching,
 * controller instantiation and 404/405 error responses in JSON.
 *
 * @package FoodOrderingSystem
 * @subpackage Core
 */

class Router
{
    /** @var array<string, array<string, array>> Registered routes by HTTP method */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'PATCH'  => [],
        'DELETE' => [],
        'OPTIONS'=> [], // usually handled by CORS, but kept for completeness
    ];

    /** @var array<string, string> Route pattern → compiled regex cache */
    private array $patternCache = [];

    /**
     * Register a GET route
     *
     * @param string $path    Route path (supports {param} placeholders)
     * @param callable|array $handler  Closure or [Controller::class, 'method']
     * @return void
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable|array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Register a PATCH route
     */
    public function patch(string $path, callable|array $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable|array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Internal method to register a route
     */
    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        // Normalize path: remove trailing slash, except for root
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        $this->routes[$method][$path] = [
            'handler' => $handler,
            'original' => $path,
        ];
    }

    /**
     * Main dispatch method - called from index.php
     * Matches request and executes handler or returns error
     *
     * @return void
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normalize URI
        $uri = rtrim($uri, '/');
        if ($uri === '') {
            $uri = '/';
        }

        // Try to find exact match first (fast path)
        if (isset($this->routes[$method][$uri])) {
            $this->executeHandler($this->routes[$method][$uri]['handler'], []);
            return;
        }

        // Try dynamic routes (slow path)
        foreach ($this->routes[$method] as $pattern => $route) {
            if (str_contains($pattern, '{')) {
                $regex = $this->getRouteRegex($pattern);

                if (preg_match($regex, $uri, $matches)) {
                    array_shift($matches); // remove full match
                    $this->executeHandler($route['handler'], $matches);
                    return;
                }
            }
        }

        // No route matched → 404
        $this->sendJsonError(404, 'Route not found');
    }

    /**
     * Convert route pattern with {params} to regex and cache it
     */
    private function getRouteRegex(string $pattern): string
    {
        if (isset($this->patternCache[$pattern])) {
            return $this->patternCache[$pattern];
        }

        // Escape literal parts, convert {param} → ([^/]+)
        $regex = preg_replace_callback(
            '#\{([a-zA-Z][a-zA-Z0-9_]*)\}#',
            fn($m) => '(?P<' . $m[1] . '>[^/]+)',
            preg_quote($pattern, '#')
        );

        $regex = '#^' . $regex . '$#';

        $this->patternCache[$pattern] = $regex;
        return $regex;
    }

    /**
     * Execute the matched handler (closure or controller method)
     *
     * @param callable|array $handler
     * @param array $params Extracted route parameters
     * @return void
     */
    private function executeHandler(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) {
            // Closure / anonymous function
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (!class_exists($class)) {
                $this->sendJsonError(500, "Controller class not found: $class");
                return;
            }

            $controller = new $class();

            if (!method_exists($controller, $method)) {
                $this->sendJsonError(500, "Method $method not found in $class");
                return;
            }

            call_user_func_array([$controller, $method], $params);
            return;
        }

        $this->sendJsonError(500, 'Invalid route handler');
    }

    /**
     * Send JSON error response and exit
     *
     * @param int $code HTTP status code
     * @param string $message Error message
     * @return never
     */
    private function sendJsonError(int $code, string $message): never
    {
        http_response_code($code);

        if ($code === 405) {
            header('Allow: ' . implode(', ', array_keys($this->routes)));
        }

        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'success' => false,
            'message' => $message,
            'status'  => $code
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        exit(0);
    }

    /**
     * Optional: Add support for method not allowed (405)
     * Call this before 404 check if you want stricter method checking
     */
    private function checkMethodNotAllowed(string $uri): void
    {
        $allowed = [];
        foreach ($this->routes as $method => $routes) {
            if (isset($routes[$uri]) || $this->routeMatchesAnyPattern($uri, $routes)) {
                $allowed[] = $method;
            }
        }

        if (!empty($allowed)) {
            $this->sendJsonError(405, 'Method Not Allowed');
        }
    }

    private function routeMatchesAnyPattern(string $uri, array $routes): bool
    {
        foreach ($routes as $pattern => $_) {
            if (str_contains($pattern, '{')) {
                $regex = $this->getRouteRegex($pattern);
                if (preg_match($regex, $uri)) {
                    return true;
                }
            }
        }
        return false;
    }

    /*
     * Typical usage in index.php:
     *
     * require_once __DIR__ . '/../config/cors.php';
     * require_once __DIR__ . '/../config/database.php';
     * require_once __DIR__ . '/../core/Router.php';
     * // ... require controllers ...
     *
     * $router = new Router();
     *
     * // Public routes
     * $router->get('/products', [ProductController::class, 'index']);
     * $router->get('/products/{id}', [ProductController::class, 'show']);
     * $router->post('/auth/login', [AuthController::class, 'login']);
     *
     * // Protected routes (after auth middleware)
     * $router->get('/cart', [CartController::class, 'getCart']);
     * $router->post('/orders', [OrderController::class, 'create']);
     *
     * $router->dispatch();
     */
}