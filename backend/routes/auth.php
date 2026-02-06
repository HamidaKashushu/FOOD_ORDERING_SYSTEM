<?php
/**
 * Food Ordering System - Authentication Routes
 * Defines all public and protected API endpoints related to user authentication.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/auth for API versioning and clarity.
 *
 * Public routes: register, login
 * Protected routes: refresh-token, logout (require valid JWT via AuthMiddleware)
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// ────────────────────────────────────────────────
// Authentication Routes Group
// ────────────────────────────────────────────────

$router->group('/api/auth', function ($router) {

    // ────────────────────────────────────────────────
    // Public Routes (no authentication required)
    // ────────────────────────────────────────────────

    /**
     * Register new user
     * POST /api/auth/register
     */
    $router->post('/register', [AuthController::class, 'register']);

    /**
     * User login & JWT issuance
     * POST /api/auth/login
     */
    $router->post('/login', [AuthController::class, 'login']);

    // ────────────────────────────────────────────────
    // Protected Routes (require valid JWT)
    // ────────────────────────────────────────────────

    /**
     * Refresh JWT token
     * POST /api/auth/refresh-token
     * Requires valid current token
     */
    $router->post('/refresh-token', Middleware::run(
        [new AuthMiddleware()],
        $router,
        [AuthController::class, 'refreshToken']
    ));

    /**
     * Logout / invalidate session (client-side token removal)
     * POST /api/auth/logout
     * Protected but stateless — mainly for client-side cleanup
     */
    $router->post('/logout', Middleware::run(
        [new AuthMiddleware()],
        $router,
        [AuthController::class, 'logout']
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/auth.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */