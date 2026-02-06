<?php
/**
 * Food Ordering System - CORS Configuration
 * Centralized handling of Cross-Origin Resource Sharing headers
 * and preflight (OPTIONS) requests for the REST API
 *
 * This file should be included at the very top of index.php
 *
 * @package FoodOrderingSystem
 * @subpackage Config
 */

// ────────────────────────────────────────────────
// 1. Handle CORS preflight (OPTIONS) requests first
// ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Send success status for preflight
    http_response_code(200);

    // Set required CORS headers for preflight response
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');           // Cache preflight for 24 hours
    header('Content-Length: 0');
    header('Content-Type: text/plain');

    // Important: Stop execution after answering OPTIONS
    exit(0);
}

// ────────────────────────────────────────────────
// 2. Set CORS headers for all other requests
// ────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');               // ← Change to specific origin in production
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: false');      // Set true only if using cookies/auth with credentials

// ────────────────────────────────────────────────
// 3. Default response format (most API endpoints return JSON)
// ────────────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');

// Optional: Prevent caching of sensitive API responses
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ────────────────────────────────────────────────
// Note for production hardening:
// ────────────────────────────────────────────────
// 1. Replace '*' with your actual frontend domain(s):
//    header('Access-Control-Allow-Origin: https://yourfrontend.com');
//
// 2. If using credentials (cookies, Authorization header with session):
//    - Set Access-Control-Allow-Credentials: true
//    - NEVER use wildcard (*) origin
//
// 3. Consider adding Access-Control-Expose-Headers if frontend needs
//    to read custom response headers
//