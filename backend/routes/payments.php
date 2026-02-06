<?php
/**
 * Food Ordering System - Payment Routes
 * Defines all API endpoints related to payment processing and history.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/payments for logical grouping.
 *
 * User-protected routes: create payment, view own payments, view single payment, view by order
 * Admin-protected routes: view all payments, update payment status
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// Payments Routes Group
// ────────────────────────────────────────────────

$router->group('/api/payments', function ($router) {

    // ────────────────────────────────────────────────
    // Authenticated User Payment Routes
    // Requires: AuthMiddleware
    // ────────────────────────────────────────────────

    $userMiddleware = [new AuthMiddleware()];

    /**
     * Record a new payment for an order
     * POST /api/payments
     * Body: { "order_id": 123, "amount": 25000, "payment_method": "mobile_money" }
     */
    $router->post('/', Middleware::run(
        $userMiddleware,
        [PaymentController::class, 'createPayment']
    ));

    /**
     * Get details of a specific payment
     * GET /api/payments/{id}
     * User can only view their own payments (checked in controller)
     */
    $router->get('/{id}', Middleware::run(
        $userMiddleware,
        function (Request $request, int $id) {
            $controller = new PaymentController($request);
            $controller->getPayment($id);
        }
    ));

    /**
     * Get all payments made by the authenticated user
     * GET /api/payments/user
     */
    $router->get('/user', Middleware::run(
        $userMiddleware,
        [PaymentController::class, 'getPaymentsByUser']
    ));

    /**
     * Get payment associated with a specific order
     * GET /api/payments/order/{orderId}
     */
    $router->get('/order/{orderId}', Middleware::run(
        $userMiddleware,
        function (Request $request, int $orderId) {
            $controller = new PaymentController($request);
            $controller->getPaymentsByOrder($orderId);
        }
    ));

    // ────────────────────────────────────────────────
    // Admin-Only Payment Management Routes
    // Requires: AuthMiddleware + RoleMiddleware('admin')
    // ────────────────────────────────────────────────

    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Get all payments in the system (admin only)
     * GET /api/payments
     */
    $router->get('/', Middleware::run(
        $adminMiddleware,
        [PaymentController::class, 'getAllPayments']
    ));

    /**
     * Update payment status (admin only)
     * PATCH /api/payments/{id}/status
     * Body: { "status": "paid" | "failed" }
     */
    $router->patch('/{id}/status', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $id) {
            $controller = new PaymentController($request);
            $controller->updateStatus($id);
        }
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/payments.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */