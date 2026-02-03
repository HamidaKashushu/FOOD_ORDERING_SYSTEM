<?php

// backend/routes/admin.php
// ADMIN-ONLY endpoints for Food Ordering System management

if (!isset($conn) || !$conn instanceof PDO) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection not available'
    ]);
    exit;
}

function sendResponse($status, $success, $message, $data = []) {
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ────────────────────────────────────────────────
// Security: Check if user is admin
// ────────────────────────────────────────────────
function isAdmin(PDO $conn, $admin_id) {
    if (!is_numeric($admin_id) || (int)$admin_id <= 0) {
        sendResponse(400, false, 'Invalid admin_id');
    }

    $admin_id = (int)$admin_id;

    $stmt = $conn->prepare("
        SELECT r.role_name 
        FROM users u
        JOIN roles r ON u.role_id = r.role_id
        WHERE u.id = ? 
        LIMIT 1
    ");
    $stmt->execute([$admin_id]);
    $user = $stmt->fetch();

    if (!$user || strtolower($user['role_name']) !== 'admin') {
        sendResponse(403, false, 'Access denied: Admin privileges required');
    }

    return true;
}

// ────────────────────────────────────────────────
// Helper: Get JSON input safely
// ────────────────────────────────────────────────
function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, false, 'Invalid JSON format');
    }
    return $data ?: [];
}

// ────────────────────────────────────────────────
// Helper: Validate positive integer
// ────────────────────────────────────────────────
function isValidId($value) {
    return is_numeric($value) && (int)$value > 0;
}

// ────────────────────────────────────────────────
// Main logic
// ────────────────────────────────────────────────
$method   = $_SERVER['REQUEST_METHOD'];
$action   = isset($_GET['action'])   ? trim($_GET['action'])   : '';
$admin_id = isset($_GET['admin_id']) ? trim($_GET['admin_id']) : '';

// Require admin_id for all admin actions
if ($admin_id === '') {
    sendResponse(400, false, 'admin_id parameter is required');
}

// Verify admin privileges (runs for every request in this file)
isAdmin($conn, $admin_id);

switch ($action) {

    // ────────────────────────────────────────────────
    // GET ?route=admin&action=dashboard&admin_id=...
    // ────────────────────────────────────────────────
    case 'dashboard':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        try {
            $stats = [];

            // Total users
            $stmt = $conn->query("SELECT COUNT(*) as total_users FROM users");
            $stats['total_users'] = (int)$stmt->fetchColumn();

            // Total orders
            $stmt = $conn->query("SELECT COUNT(*) as total_orders FROM orders");
            $stats['total_orders'] = (int)$stmt->fetchColumn();

            // Total foods
            $stmt = $conn->query("SELECT COUNT(*) as total_foods FROM foods");
            $stats['total_foods'] = (int)$stmt->fetchColumn();

            // Total categories
            $stmt = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
            $stats['total_categories'] = (int)$stmt->fetchColumn();

            // Total revenue (only completed/paid orders)
            $stmt = $conn->query("
                SELECT COALESCE(SUM(o.total_amount), 0) as total_revenue
                FROM orders o
                JOIN payments p ON o.order_id = p.order_id
                WHERE p.payment_status = 'paid'
            ");
            $stats['total_revenue'] = round((float)$stmt->fetchColumn(), 2);

            sendResponse(200, true, 'Dashboard statistics retrieved', $stats);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch dashboard statistics');
        }
        break;

    // ────────────────────────────────────────────────
    // GET ?route=admin&action=users&admin_id=...
    // ────────────────────────────────────────────────
    case 'users':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        try {
            $stmt = $conn->query("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.created_at,
                    r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                ORDER BY u.full_name ASC
            ");
            $users = $stmt->fetchAll();

            sendResponse(200, true, 'Users list retrieved', $users);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch users list');
        }
        break;

    // ────────────────────────────────────────────────
    // DELETE ?route=admin&action=delete_user&id=...&admin_id=...
    // ────────────────────────────────────────────────
    case 'delete_user':
        if ($method !== 'DELETE') {
            sendResponse(405, false, 'Method not allowed');
        }

        $user_id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($user_id)) {
            sendResponse(400, false, 'Invalid user ID');
        }

        $user_id = (int)$user_id;

        try {
            // Prevent self-deletion (optional but recommended)
            if ($user_id === $admin_id) {
                sendResponse(403, false, 'Cannot delete your own account');
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'User not found');
            }

            sendResponse(200, true, 'User deleted successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to delete user');
        }
        break;

    // ────────────────────────────────────────────────
    // POST ?route=admin&action=create_category&admin_id=...
    // ────────────────────────────────────────────────
    case 'create_category':
        if ($method !== 'POST') {
            sendResponse(405, false, 'Method not allowed');
        }

        $data = getJsonInput();

        $category_name = trim($data['category_name'] ?? '');
        $description   = trim($data['description']   ?? '');

        if (empty($category_name)) {
            sendResponse(400, false, 'category_name is required');
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO categories (category_name, description, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$category_name, $description]);

            $category_id = (int)$conn->lastInsertId();

            sendResponse(201, true, 'Category created successfully', ['category_id' => $category_id]);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to create category');
        }
        break;

    // ────────────────────────────────────────────────
    // PUT ?route=admin&action=update_category&id=...&admin_id=...
    // ────────────────────────────────────────────────
    case 'update_category':
        if ($method !== 'PUT' && $method !== 'PATCH') {
            sendResponse(405, false, 'Method not allowed');
        }

        $category_id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($category_id)) {
            sendResponse(400, false, 'Invalid category ID');
        }

        $data = getJsonInput();

        $updates = [];
        $params  = [];

        if (isset($data['category_name']) && trim($data['category_name']) !== '') {
            $updates[] = "category_name = :category_name";
            $params[':category_name'] = trim($data['category_name']);
        }
        if (isset($data['description'])) {
            $updates[] = "description = :description";
            $params[':description'] = trim($data['description']);
        }

        if (empty($updates)) {
            sendResponse(400, false, 'No valid fields to update');
        }

        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE category_id = :id";
        $params[':id'] = (int)$category_id;

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Category not found or no changes made');
            }

            sendResponse(200, true, 'Category updated successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to update category');
        }
        break;

    // ────────────────────────────────────────────────
    // DELETE ?route=admin&action=delete_category&id=...&admin_id=...
    // ────────────────────────────────────────────────
    case 'delete_category':
        if ($method !== 'DELETE') {
            sendResponse(405, false, 'Method not allowed');
        }

        $category_id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($category_id)) {
            sendResponse(400, false, 'Invalid category ID');
        }

        $category_id = (int)$category_id;

        try {
            $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Category not found');
            }

            sendResponse(200, true, 'Category deleted successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to delete category (possibly in use)');
        }
        break;

    // ────────────────────────────────────────────────
    // GET ?route=admin&action=all_orders&admin_id=...
    // ────────────────────────────────────────────────
    case 'all_orders':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        try {
            $stmt = $conn->query("
                SELECT 
                    o.order_id,
                    o.user_id,
                    u.full_name AS user_name,
                    o.total_amount,
                    o.status,
                    o.order_date,
                    p.payment_method,
                    p.payment_status
                FROM orders o
                JOIN users u ON o.user_id = u.id
                LEFT JOIN payments p ON o.order_id = p.order_id
                ORDER BY o.order_date DESC
            ");
            $orders = $stmt->fetchAll();

            sendResponse(200, true, 'All orders retrieved', $orders);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch all orders');
        }
        break;

    default:
        sendResponse(400, false, 'Invalid action');
}