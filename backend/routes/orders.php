<?php
/**
 * Food Ordering System - Order Routes
 * Defines all API endpoints related to order management and history.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/orders for logical grouping.
 *
 * User-protected routes: create order, view own orders, view single order
 * Admin-protected routes: view all orders, update status, delete order
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// Orders Routes Group
// ────────────────────────────────────────────────

$router->group('/api/orders', function ($router) {

    // ────────────────────────────────────────────────
    // Authenticated User Order Routes
    // Requires: AuthMiddleware
    // ────────────────────────────────────────────────

    $userMiddleware = [new AuthMiddleware()];

    /**
     * Create new order from cart
     * POST /api/orders
     */
    $router->post('/', Middleware::run(
        $userMiddleware,
        [OrderController::class, 'createOrder']
    ));

    /**
     * Get details of a specific order
     * GET /api/orders/{id}
     * User can only view their own orders (checked in controller)
     */
    $router->get('/{id}', Middleware::run(
        $userMiddleware,
        function (Request $request, int $id) {
            $controller = new OrderController($request);
            $controller->getOrder($id);
        }
    ));

    /**
     * Get all orders for the authenticated user
     * GET /api/orders/user
     */
    $router->get('/user', Middleware::run(
        $userMiddleware,
        [OrderController::class, 'getOrdersByUser']
    ));

    // ────────────────────────────────────────────────
    // Admin-Only Order Management Routes
    // Requires: AuthMiddleware + RoleMiddleware('admin')
    // ────────────────────────────────────────────────

    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Get all orders in the system (admin only)
     * GET /api/orders
     */
    $router->get('/', Middleware::run(
        $adminMiddleware,
        [OrderController::class, 'getAllOrders']
    ));

    /**
     * Update order status (admin only)
     * PATCH /api/orders/{id}/status
     * Body: { "status": "preparing" | "delivering" | ... }
     */
    $router->patch('/{id}/status', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new OrderController($request);
            $controller->updateStatus($id);
        }
    ));

    /**
     * Delete an order (admin only)
     * DELETE /api/orders/{id}
     */
    $router->delete('/{id}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new OrderController($request);
            $controller->deleteOrder($id);
        }
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/orders.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */