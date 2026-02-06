<?php
/**
 * Food Ordering System - Category Routes
 * Defines all API endpoints related to food categories management.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/categories for API versioning and clarity.
 *
 * Public routes: list, view single, search
 * Protected routes (admin only): create, update, delete, change status
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// Categories Routes Group
// ────────────────────────────────────────────────

$router->group('/api/categories', function ($router) {

    // ────────────────────────────────────────────────
    // Public Routes (no authentication required)
    // ────────────────────────────────────────────────

    /**
     * Get all categories
     * GET /api/categories
     */
    $router->get('/', [CategoryController::class, 'getAll']);

    /**
     * Get single category by ID
     * GET /api/categories/{id}
     */
    $router->get('/{id}', function (Request $request, int $id) {
        $controller = new CategoryController($request);
        $controller->getById($id);
    });

    /**
     * Search categories by keyword
     * GET /api/categories/search/{keyword}
     */
    $router->get('/search/{keyword}', function (Request $request, string $keyword) {
        $controller = new CategoryController($request);
        $controller->search($keyword);
    });

    // ────────────────────────────────────────────────
    // Admin-Only Category Management Routes
    // Requires: AuthMiddleware + RoleMiddleware('admin')
    // ────────────────────────────────────────────────

    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Create new category
     * POST /api/categories
     */
    $router->post('/', Middleware::run(
        $adminMiddleware,
        [CategoryController::class, 'create']
    ));

    /**
     * Update category details
     * PUT /api/categories/{id}
     */
    $router->put('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new CategoryController($request);
            $controller->update($id);
        }
    ));

    /**
     * Delete category
     * DELETE /api/categories/{id}
     */
    $router->delete('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new CategoryController($request);
            $controller->delete($id);
        }
    ));

    /**
     * Update category status (active/inactive)
     * PATCH /api/categories/{id}/status
     */
    $router->patch('/{id}/status', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $status = $request->body('status') ?? '';
            $controller = new CategoryController($request);
            $controller->setStatus($id, $status);
        }
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/categories.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */