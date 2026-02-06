<?php
/**
 * Food Ordering System - Authentication Middleware
 * Protects routes by validating JWT (Bearer token) from Authorization header
 *
 * Stops unauthenticated requests with proper 401 JSON response.
 * On success, attaches authenticated user data to the request object.
 *
 * @package FoodOrderingSystem
 * @subpackage Middleware
 */

declare(strict_types=1);

require_once __DIR__ . '/../utils/jwt.php';          // Assuming your JWT helper exists
require_once __DIR__ . '/../models/User.php';         // User model for fetching user data

class AuthMiddleware extends Middleware
{
    /**
     * Handle authentication check for protected routes
     *
     * @param Request  $request The incoming request
     * @param callable $next    Next middleware or controller action
     * @return mixed            Response on failure, or result of $next on success
     */
    public function handle(Request $request, callable $next)
    {
        $authHeader = $request->header('Authorization');

        // Missing or malformed Authorization header
        if (!$authHeader || !preg_match('/^Bearer\s+(\S+)/', $authHeader, $matches)) {
            return Response::unauthorized('Authentication required. Please provide a valid Bearer token.');
        }

        $token = $matches[1];

        try {
            // Verify and decode JWT
            $payload = validateToken($token); // Assume Jwt::verify() throws on failure

            if (!isset($payload['sub']) || !is_numeric($payload['sub'])) {
                return Response::unauthorized('Invalid token payload');
            }

            $userId = (int)$payload['sub'];
            $User = new User();

            // Fetch user (you may cache this in production)
            $user = $User->findById($userId);

            if (!$user || $user['status'] !== 'active') {
                return Response::unauthorized('User account not found or inactive');
            }

            // Attach authenticated user to request for controllers to use
            // Option 1: Add property dynamically (common pattern in lightweight systems)
            $request->user = $user;

            // Option 2: You could also use a setter if you extend Request class later
            // $request->setUser($user);

            // Token is valid → proceed to next middleware or controller
            return $this->next($request, $next);

        } catch (Exception $e) {
            // Catch token verification exceptions (expired, invalid signature, etc.)
            error_log("JWT verification failed: " . $e->getMessage());

            return Response::unauthorized('Invalid or expired token');
        }
    }

    /*
     * Usage in route definitions or controller wrappers:
     *
     * Middleware::run(
     *     [new AuthMiddleware()],
     *     $request,
     *     function (Request $req) {
     *         // $req->user is now available
     *         $user = $req->user;
     *         return Response::success([
     *             'user' => $user['full_name'],
     *             'message' => 'Welcome back!'
     *         ]);
     *     }
     * );
     *
     * // Or in Router usage:
     * $router->get('/profile', Middleware::run(
     *     [new AuthMiddleware()],
     *     fn($req) => (new UserController())->profile($req)
     * ));
     */
}