<?php
/**
 * Food Ordering System - Address Routes
 * Defines API endpoints for address management.
 */

require_once __DIR__ . '/../controllers/AddressController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

$router->group('/api/addresses', function ($router) {

    $protected = [new AuthMiddleware()];

    /**
     * Get user addresses
     * GET /api/addresses
     */
    $router->get('/', Middleware::run(
        $protected,
        [AddressController::class, 'getUserAddresses']
    ));

    /**
     * Create new address
     * POST /api/addresses
     */
    $router->post('/', Middleware::run(
        $protected,
        [AddressController::class, 'createAddress']
    ));

});
