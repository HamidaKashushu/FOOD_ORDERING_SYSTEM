<?php
/**
 * Food Ordering System - User Routes
 * Defines all API endpoints related to user management and profile operations.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/users for API versioning and clarity.
 *
 * Protected routes require valid JWT (AuthMiddleware).
 * Admin-only routes additionally require 'admin' role (RoleMiddleware).
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// User Routes Group
// ────────────────────────────────────────────────

$router->group('/api/users', function ($router) {

    // ────────────────────────────────────────────────
    // Authenticated User Profile Routes
    // Requires: AuthMiddleware
    // ────────────────────────────────────────────────

    /**
     * Get authenticated user's profile
     * GET /api/users/profile
     */
    $router->get('/profile', Middleware::run(
        [new AuthMiddleware()],
        function (Request $request) {
            $userId = $request->user['id']; // from JWT payload
            $controller = new UserController($request);
            $controller->getProfile($userId);
        }
    ));

    /**
     * Update authenticated user's profile
     * PUT /api/users/profile
     */
    $router->put('/profile', Middleware::run(
        [new AuthMiddleware()],
        function (Request $request) {
            $userId = $request->user['id'];
            $controller = new UserController($request);
            $controller->updateProfile($userId);
        }
    ));

    // ────────────────────────────────────────────────
    // Admin-Only User Management Routes
    // Requires: AuthMiddleware + RoleMiddleware('admin')
    // ────────────────────────────────────────────────

    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Get all users (admin only)
     * GET /api/users
     */
    $router->get('/', Middleware::run(
        $adminMiddleware,
        [UserController::class, 'getAllUsers']
    ));

    /**
     * Delete a user by ID (admin only)
     * DELETE /api/users/{id}
     */
    $router->delete('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new UserController($request);
            $controller->deleteUser($id);
        }
    ));

    /**
     * Assign role to a user (admin only)
     * POST /api/users/{id}/role
     * Body: { "role": "admin" | "customer" | ... }
     */
    $router->post('/{id}/role', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $roleName = $request->body('role') ?? '';
            $controller = new UserController($request);
            $controller->assignRole($id, $roleName);
        }
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/users.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */