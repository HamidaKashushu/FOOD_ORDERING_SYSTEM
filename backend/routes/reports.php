<?php
/**
 * Food Ordering System - Admin Reports Routes
 * Defines all admin-only reporting API endpoints for sales analytics,
 * order history, popular products, user activity, and revenue summaries.
 *
 * This file should be included in the main index.php or routing bootstrap file.
 * All routes are prefixed with /api/reports for logical grouping.
 *
 * ALL ROUTES ARE ADMIN-ONLY and require:
 * - AuthMiddleware (valid JWT)
 * - RoleMiddleware('admin')
 *
 * @package FoodOrderingSystem
 * @subpackage Routes
 */

require_once __DIR__ . '/../controllers/ReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/RoleMiddleware.php';

// ────────────────────────────────────────────────
// Reports Routes Group (Admin Only)
// ────────────────────────────────────────────────

$router->group('/api/reports', function ($router) {

    // Apply both authentication and admin role check to all routes in this group
    $adminMiddleware = [new AuthMiddleware(), new RoleMiddleware('admin')];

    /**
     * Sales summary report
     * GET /api/reports/sales?start=YYYY-MM-DD&end=YYYY-MM-DD
     * Returns total revenue, order count, average order value
     */
    $router->get('/sales', Middleware::run(
        $adminMiddleware,
        [ReportController::class, 'salesReport']
    ));

    /**
     * Orders list report
     * GET /api/reports/orders?start=YYYY-MM-DD&end=YYYY-MM-DD
     * Returns detailed list of orders in the date range
     */
    $router->get('/orders', Middleware::run(
        $adminMiddleware,
        [ReportController::class, 'ordersReport']
    ));

    /**
     * Popular products report
     * GET /api/reports/popular-products?start=YYYY-MM-DD&end=YYYY-MM-DD&limit=10
     * Returns top-selling products with quantity sold and revenue
     */
    $router->get('/popular-products', Middleware::run(
        $adminMiddleware,
        [ReportController::class, 'popularProductsReport']
    ));

    /**
     * User activity report (orders + payments)
     * GET /api/reports/user-activity/{userId}?start=YYYY-MM-DD&end=YYYY-MM-DD
     * Returns all orders and payments for a specific user in date range
     */
    $router->get('/user-activity/{userId}', Middleware::run(
        $adminMiddleware,
        function (Request $request, int $userId) {
            $controller = new ReportController($request);
            $controller->userActivityReport($userId);
        }
    ));

    /**
     * Revenue summary report
     * GET /api/reports/revenue-summary?start=YYYY-MM-DD&end=YYYY-MM-DD
     * Returns total revenue, completed orders, pending payments
     */
    $router->get('/revenue-summary', Middleware::run(
        $adminMiddleware,
        [ReportController::class, 'revenueSummary']
    ));

});

/*
 * Typical inclusion in index.php or main router bootstrap:
 *
 * require_once __DIR__ . '/routes/reports.php';
 *
 * // After defining $router = new Router();
 * // Other route groups...
 *
 * $router->dispatch();
 */