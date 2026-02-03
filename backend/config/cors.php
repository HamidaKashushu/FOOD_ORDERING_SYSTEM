<?php

// backend/config/cors.php
// Global CORS + Security Headers + Response Helper for Food Ordering System API

// ────────────────────────────────────────────────
// Set CORS and API headers (for all responses)
// ────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ────────────────────────────────────────────────
// Handle CORS preflight requests (OPTIONS)
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Standardized JSON response helper
 * Automatically sets HTTP status code and exits
 *
 * @param bool   $success
 * @param string $message
 * @param array  $data
 * @param int    $code    HTTP status code (default 200)
 */
function sendResponse(bool $success, string $message = '', array $data = [], int $code = 200): void {
    http_response_code($code);

    $response = [
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}