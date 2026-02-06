<?php
/**
 * Food Ordering System - Middleware Base Class & Pipeline Runner
 * Lightweight middleware system for REST API (authentication, validation, roles, etc.)
 *
 * Provides abstract base class for custom middleware and a static runner
 * to execute middleware chain before reaching the controller.
 *
 * @package FoodOrderingSystem
 * @subpackage Core
 */
declare(strict_types=1);

abstract class Middleware
{
    /**
     * Handle the incoming request
     *
     * Each middleware must:
     * - Either call $next($request) to pass control to the next middleware/controller
     * - Or return a Response object to short-circuit (stop execution)
     *
     * @param Request $request The current request
     * @param callable $next   Next middleware or final controller
     * @return mixed           Response object on error, or whatever $next returns
     */
    abstract public function handle(Request $request, callable $next);

    /**
     * Run a pipeline of middleware and execute the final controller if all pass
     *
     * @param array    $middlewares Array of Middleware instances
     * @param Request  $request     The incoming request
     * @param callable $controller  Final handler (usually controller method)
     * @return never
     */
    public static function run(array $middlewares, Request $request, callable $controller): never
    {
        // Build the pipeline from inside out
        $pipeline = $controller;

        // Reverse order: last middleware wraps the controller, first middleware is outermost
        foreach (array_reverse($middlewares) as $middleware) {
            if (!$middleware instanceof Middleware) {
                throw new InvalidArgumentException('All middleware must extend Middleware class');
            }

            $current = $pipeline;

            $pipeline = function (Request $req) use ($middleware, $current): mixed {
                return $middleware->handle($req, $current);
            };
        }

        try {
            $result = $pipeline($request);

            // If result is a Response instance → already sent, just exit
            if ($result instanceof Response) {
                exit(0);
            }

            // If controller returned something else (e.g. array for JSON), send it
            if (is_array($result) || is_object($result)) {
                Response::json((array)$result);
            }

            // Fallback: no response was sent
            Response::error('No response generated', 500);
        } catch (Throwable $e) {
            // Catch any unhandled exception in middleware or controller
            error_log("Middleware pipeline error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

            Response::error('Internal server error', 500);
        }
    }

    /**
     * Helper: continue to next middleware/controller
     * Can be used inside handle() method for clarity
     *
     * @param Request  $request
     * @param callable $next
     * @return mixed
     */
    protected function next(Request $request, callable $next)
    {
        return $next($request);
    }

    /*
     * Usage example in routes or index.php:
     *
     * $request = new Request();
     *
     * Middleware::run(
     *     [
     *         new AuthMiddleware(),
     *         new RoleMiddleware('admin'),
     *         new ValidationMiddleware($rules),
     *     ],
     *     $request,
     *     function (Request $req) {
     *         // This is your controller action
     *         $controller = new ProductController();
     *         return $controller->update($req, $id);
     *     }
     * );
     *
     * // Inside a custom middleware example:
     * class AuthMiddleware extends Middleware
     * {
     *     public function handle(Request $request, callable $next)
     *     {
     *         $token = $request->header('Authorization');
     *         if (!$token || !Jwt::validate($token)) {
     *             return Response::unauthorized('Invalid or missing token');
     *         }
     *
     *         // Add user to request or global if needed
     *         // $request->setUser($user);
     *
     *         return $this->next($request, $next);
     *     }
     * }
     */
}