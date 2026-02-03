<?php

// backend/middleware/authMiddleware.php
// Authentication & Authorization Middleware Helpers for Food Ordering System API

if (!isset($conn) || !$conn instanceof PDO) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available'
    ]);
    exit;
}

/**
 * Helper: Send JSON response and exit
 */
function sendAuthError($status, $message) {
    http_response_code($status);
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Fetch user by ID with role information
 * Excludes sensitive fields like password_hash
 *
 * @param PDO $conn
 * @param mixed $user_id
 * @return array|null User data or null if not found
 */
function getUser(PDO $conn, $user_id) {
    if (!is_numeric($user_id) || (int)$user_id <= 0) {
        return null;
    }

    $user_id = (int)$user_id;

    try {
        $stmt = $conn->prepare("
            SELECT 
                u.id,
                u.full_name,
                u.email,
                u.phone,
                u.role_id,
                r.role_name,
                u.created_at
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        return $user;
    } catch (PDOException $e) {
        // Log error in production (do not expose to client)
        error_log("Auth middleware error: " . $e->getMessage());
        return null;
    }
}

/**
 * Require authenticated user
 * Exits with 401 if authentication fails
 *
 * @param PDO $conn
 * @param mixed $user_id (from header/query/JSON)
 * @return array User data
 */
function requireUser(PDO $conn, $user_id) {
    if (!is_numeric($user_id) || (int)$user_id <= 0) {
        sendAuthError(401, 'Unauthorized - Invalid or missing user ID');
    }

    $user = getUser($conn, $user_id);

    if (!$user) {
        sendAuthError(401, 'Unauthorized - User not found');
    }

    return $user;
}

/**
 * Require admin privileges
 * Exits with 401 if not authenticated, 403 if not admin
 *
 * @param PDO $conn
 * @param mixed $user_id
 * @return array User data (admin)
 */
function requireAdmin(PDO $conn, $user_id) {
    $user = requireUser($conn, $user_id);

    if (strtolower($user['role_name']) !== 'admin') {
        sendAuthError(403, 'Admin access required');
    }

    return $user;
}

/**
 * Optional: Helper to get user_id from common locations
 * Use in route files if needed:
 *   $user_id = getUserIdFromRequest();
 */
function getUserIdFromRequest() {
    // Priority: Authorization header > GET > POST/JSON body
    $headers = getallheaders();

    // Bearer token style (you can extend later for JWT)
    if (isset($headers['Authorization']) && preg_match('/Bearer\s(\d+)/', $headers['Authorization'], $matches)) {
        return (int)$matches[1];
    }

    // Query param (for simplicity during development)
    if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
        return (int)$_GET['user_id'];
    }

    // JSON body (common in POST/PUT)
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['user_id']) && is_numeric($input['user_id'])) {
        return (int)$input['user_id'];
    }

    return null;
}