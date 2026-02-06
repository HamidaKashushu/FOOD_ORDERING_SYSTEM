<?php
/**
 * Food Ordering System - Cart Routes
 * Defines all API endpoints related to shopping cart management.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/cart for logical grouping.
 *
 * All routes are protected and require a valid JWT (AuthMiddleware).
 * The authenticated user's ID is extracted from the JWT payload in the controller.
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// ────────────────────────────────────────────────
// Cart Routes Group (all require authentication)
// ────────────────────────────────────────────────

$router->group('/api/cart', function ($router) {

    // Apply AuthMiddleware to all routes in this group
    $protected = [new AuthMiddleware()];

    /**
     * Get current user's cart contents
     * GET /api/cart
     */
    $router->get('/', Middleware::run(
        $protected,
        CartController::class,
        'getCart'
    ));

    /**
     * Add item to cart
     * POST /api/cart/add
     * Body: { "product_id": 123, "quantity": 2 }
     */
    $router->post('/add', Middleware::run(
        $protected,
        CartController::class,
        'addItem'
    ));

    /**
     * Update cart item quantity (or remove if quantity = 0)
     * PUT /api/cart/update
     * Body: { "product_id": 123, "quantity": 3 }
     */
    $router->put('/update', Middleware::run(
        $protected,
        CartController::class,
        'updateItem'
    ));

    /**
     * Remove specific item from cart
     * DELETE /api/cart/remove
     * Body: { "product_id": 123 }
     */
    $router->delete('/remove', Middleware::run(
        $protected,
        CartController::class,
        'removeItem'
    ));

    /**
     * Clear entire cart
     * DELETE /api/cart/clear
     */
    $router->delete('/clear', Middleware::run(
        $protected,
        CartController::class,
        'clearCart'
    ));

    /**
     * Get cart total amount
     * GET /api/cart/total
     */
    $router->get('/total', Middleware::run(
        $protected,
        CartController::class,
        'getTotal'
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/cart.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */