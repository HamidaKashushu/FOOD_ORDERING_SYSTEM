<?php
/**
 * Food Ordering System - Role-Based Authorization Middleware
 * Ensures the authenticated user has one of the required roles to proceed.
 *
 * Should be used after AuthMiddleware in the pipeline.
 * Expects user data (with role information) to be attached to the Request object.
 *
 * @package FoodOrderingSystem
 * @subpackage Middleware
 */
declare(strict_types=1);

class RoleMiddleware extends Middleware
{
    /** @var array<string> Allowed roles for this route */
    private array $allowedRoles;

    /**
     * Constructor - accepts single role or array of roles
     *
     * @param string|array $roles Single role string or array of allowed roles
     */
    public function __construct(string|array $roles)
    {
        $this->allowedRoles = is_array($roles) ? $roles : [$roles];

        // Remove duplicates and empty values
        $this->allowedRoles = array_filter(array_unique($this->allowedRoles));
        
        if (empty($this->allowedRoles)) {
            throw new InvalidArgumentException('At least one role must be specified for RoleMiddleware');
        }
    }

    /**
     * Handle role-based authorization check
     *
     * @param Request  $request The incoming request (should contain authenticated user)
     * @param callable $next    Next middleware or controller action
     * @return mixed            Response on failure, or result of $next on success
     */
    public function handle(Request $request, callable $next)
    {
        // Ensure user was attached by AuthMiddleware
        if (!isset($request->user) || !is_array($request->user)) {
            return Response::unauthorized('Authentication required before role check');
        }

        $user = $request->user;

        // Get user role (adjust field name based on your User model / JWT payload)
        // Common patterns: 'role', 'role_name', 'role_id' + lookup, etc.
        $userRole = $user['role'] ?? $user['role_name'] ?? null;

        if ($userRole === null) {
            error_log("RoleMiddleware: No role found in authenticated user data");
            return Response::forbidden('User role information is missing');
        }

        // Normalize comparison (case-insensitive, trim)
        $userRole = trim(strtolower($userRole));

        // Check if user has at least one of the allowed roles
        $hasRequiredRole = false;
        foreach ($this->allowedRoles as $allowed) {
            if ($userRole === trim(strtolower($allowed))) {
                $hasRequiredRole = true;
                break;
            }
        }

        if (!$hasRequiredRole) {
            return Response::forbidden(
                'Access denied: You do not have permission to access this resource.'
            );
        }

        // User is authorized → proceed
        return $this->next($request, $next);
    }

    /*
     * Usage examples:
     *
     * // Single role
     * Middleware::run(
     *     [new AuthMiddleware(), new RoleMiddleware('admin')],
     *     $request,
     *     fn($req) => (new AdminController())->dashboard($req)
     * );
     *
     * // Multiple allowed roles
     * Middleware::run(
     *     [new AuthMiddleware(), new RoleMiddleware(['admin', 'manager', 'staff'])],
     *     $request,
     *     fn($req) => (new ReportController())->generateSalesReport($req)
     * );
     *
     * // In route file with Router:
     * $router->get('/admin/users', Middleware::run(
     *     [new AuthMiddleware(), new RoleMiddleware('admin')],
     *     [UserController::class, 'adminList']
     * ));
     */
}