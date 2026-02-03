<?php

// backend/api/index.php - Single Entry Point API Router

// Set global response headers
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Get requested route
$route = isset($_GET['route']) ? trim($_GET['route']) : '';

// Sanitize route input (remove slashes, dots, etc.)
$route = preg_replace('/[^a-zA-Z0-9_-]/', '', $route);
$route = strtolower($route);

// Remove trailing slash if present
$route = rtrim($route, '/');

// Define allowed routes
$allowedRoutes = [
    'auth',
    'foods',
    'cart',
    'orders',
    'admin'
];

// Default response structure
function sendResponse($statusCode, $data) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Route dispatching
if ($route === '') {
    sendResponse(400, [
        'success' => false,
        'message' => 'No route specified. Please provide a ?route= parameter.'
    ]);
}

$routeFile = __DIR__ . '/../routes/' . $route . '.php';

if (in_array($route, $allowedRoutes) && file_exists($routeFile)) {
    // Pass the PDO connection to the route file
    $pdo = require_once __DIR__ . '/../config/database.php';
    
    // Include the route handler
    require_once $routeFile;
} else {
    sendResponse(404, [
        'success' => false,
        'message' => 'Route not found'
    ]);
}