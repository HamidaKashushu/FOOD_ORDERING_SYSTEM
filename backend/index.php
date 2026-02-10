<?php
/**
 * Food Ordering System - API Entry Point (index.php)
 * Single entry point for all REST API requests.
 *
 * Handles:
 * - CORS configuration
 * - Autoloading / requiring core files
 * - Router initialization
 * - Route loading
 * - Request dispatching
 * - Global error handling (JSON format)
 *
 * All requests should point to this file via .htaccess rewrite.
 *
 * @package FoodOrderingSystem
 */

declare(strict_types=1);

// ────────────────────────────────────────────────
// 1. Environment & Error Reporting
// ────────────────────────────────────────────────
ini_set('display_errors', '0');               // Never display errors in production
error_reporting(E_ALL);                        // Still log everything

// Development mode toggle (set to false in production!)
define('APP_DEBUG', true);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// ────────────────────────────────────────────────
// 2. Timezone & Session (if needed)
// ────────────────────────────────────────────────
date_default_timezone_set('Africa/Dar_es_Salaam');

// Session not required for pure JWT API, but kept optional
// session_start();

// ────────────────────────────────────────────────
// 3. Load Core Dependencies & Configuration
// ────────────────────────────────────────────────
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/cors.php';

// Core classes
require_once __DIR__ . '/core/Router.php';
require_once __DIR__ . '/core/Request.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Middleware.php';

// Utils
require_once __DIR__ . '/utils/helpers.php';
require_once __DIR__ . '/utils/validator.php';
require_once __DIR__ . '/utils/sanitizer.php';
require_once __DIR__ . '/utils/jwt.php';
require_once __DIR__ . '/utils/password.php';
require_once __DIR__ . '/utils/upload.php';

// Middleware
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/middleware/RoleMiddleware.php';
require_once __DIR__ . '/middleware/ValidationMiddleware.php';

// Models (load all - or autoload in production)
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Role.php';
require_once __DIR__ . '/models/Category.php';
require_once __DIR__ . '/models/Product.php';
require_once __DIR__ . '/models/Cart.php';
require_once __DIR__ . '/models/CartItem.php';
require_once __DIR__ . '/models/Order.php';
require_once __DIR__ . '/models/OrderItem.php';
require_once __DIR__ . '/models/Payment.php';
require_once __DIR__ . '/models/Address.php';

// Controllers
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/ProductController.php';
require_once __DIR__ . '/controllers/CartController.php';
require_once __DIR__ . '/controllers/OrderController.php';
require_once __DIR__ . '/controllers/PaymentController.php';
require_once __DIR__ . '/controllers/ReportController.php';

// ────────────────────────────────────────────────
// 4. Initialize Router & Load Routes
// ────────────────────────────────────────────────
$router = new Router();

// Load all route groups
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/users.php';
require_once __DIR__ . '/routes/categories.php';
require_once __DIR__ . '/routes/products.php';
require_once __DIR__ . '/routes/cart.php';
require_once __DIR__ . '/routes/orders.php';
require_once __DIR__ . '/routes/payments.php';
require_once __DIR__ . '/routes/addresses.php';
require_once __DIR__ . '/routes/reports.php';

// ────────────────────────────────────────────────
// 5. Dispatch the request
// ────────────────────────────────────────────────
try {
    $router->dispatch();
} catch (Exception $e) {
    if (APP_DEBUG) {
        error_log("Router exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }

    Response::error('Internal server error', 500);
}