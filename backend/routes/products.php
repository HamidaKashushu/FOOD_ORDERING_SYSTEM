<?php
/**
 * Food Ordering System - Product Routes
 * Defines all API endpoints related to product catalog management.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/products for API versioning and clarity.
 *
 * Public routes: list all, view single, by category, search
 * Protected routes (admin only): create, update, delete, change status
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// Products Routes Group
// ────────────────────────────────────────────────

$router->group('/api/products', function ($router) {

    // ────────────────────────────────────────────────
    // Public Routes (no authentication required)
    // ────────────────────────────────────────────────

    /**
     * Get all products
     * GET /api/products
     */
    $router->get('/', [ProductController::class, 'getAll']);

    /**
     * Get single product by ID
     * GET /api/products/{id}
     */
    $router->get('/{id}', function (Request $request, int $id) {
        $controller = new ProductController($request);
        $controller->getById($id);
    });

    /**
     * Get products by category
     * GET /api/products/category/{categoryId}
     */
    $router->get('/category/{categoryId}', function (Request $request, int $categoryId) {
        $controller = new ProductController($request);
        $controller->getByCategory($categoryId);
    });

    /**
     * Search products by keyword
     * GET /api/products/search/{keyword}
     */
    $router->get('/search/{keyword}', function (Request $request, string $keyword) {
        $controller = new ProductController($request);
        $controller->search($keyword);
    });

    // ────────────────────────────────────────────────
    // Admin-Only Product Management Routes
    // Requires: AuthMiddleware + RoleMiddleware('admin')
    // ────────────────────────────────────────────────

    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Create new product
     * POST /api/products
     */
    $router->post('/', Middleware::run(
        $adminMiddleware,
        [ProductController::class, 'create']
    ));

    /**
     * Update product details
     * PUT /api/products/{id}
     */
    $router->put('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new ProductController($request);
            $controller->update($id);
        }
    ));

    /**
     * Delete product
     * DELETE /api/products/{id}
     */
    $router->delete('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new ProductController($request);
            $controller->delete($id);
        }
    ));

    /**
     * Update product availability status (available/unavailable)
     * PATCH /api/products/{id}/status
     */
    $router->patch('/{id}/status', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $status = $request->body('status') ?? '';
            $controller = new ProductController($request);
            $controller->setStatus($id, $status);
        }
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/products.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */