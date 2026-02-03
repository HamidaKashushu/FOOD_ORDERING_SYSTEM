<?php

// backend/routes/foods.php
// Food and Category management endpoints

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

// Determine HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Handle OPTIONS for CORS
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get action
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// Helper: Get JSON input safely
function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, false, 'Invalid JSON format');
    }
    return $data ?: [];
}

// Helper: Check if numeric and positive
function isValidId($value) {
    return is_numeric($value) && (int)$value > 0;
}

switch ($action) {

    // ────────────────────────────────────────────────
    // GET /foods?route=foods&action=list
    // ────────────────────────────────────────────────
    case 'list':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        try {
            $search   = isset($_GET['search'])   ? trim($_GET['search'])   : '';
            $category = isset($_GET['category']) ? trim($_GET['category']) : '';

            $sql = "
                SELECT 
                    f.food_id, f.category_id, c.category_name,
                    f.food_name, f.description, f.price, f.image_url, f.is_available
                FROM foods f
                LEFT JOIN categories c ON f.category_id = c.category_id
                WHERE f.is_available = 1
            ";
            $params = [];

            if ($search !== '') {
                $sql .= " AND f.food_name LIKE :search ";
                $params[':search'] = "%$search%";
            }

            if ($category !== '' && isValidId($category)) {
                $sql .= " AND f.category_id = :category ";
                $params[':category'] = (int)$category;
            }

            $sql .= " ORDER BY f.food_name ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $foods = $stmt->fetchAll();

            sendResponse(200, true, 'Foods retrieved successfully', $foods);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch foods');
        }
        break;

    // ────────────────────────────────────────────────
    // GET /foods?route=foods&action=single&id=...
    // ────────────────────────────────────────────────
    case 'single':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        $id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($id)) {
            sendResponse(400, false, 'Invalid food ID');
        }

        try {
            $stmt = $conn->prepare("
                SELECT 
                    f.food_id, f.category_id, c.category_name,
                    f.food_name, f.description, f.price, f.image_url, f.is_available
                FROM foods f
                LEFT JOIN categories c ON f.category_id = c.category_id
                WHERE f.food_id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => (int)$id]);
            $food = $stmt->fetch();

            if (!$food) {
                sendResponse(404, false, 'Food not found');
            }

            sendResponse(200, true, 'Food retrieved', $food);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch food');
        }
        break;

    // ────────────────────────────────────────────────
    // POST /foods?route=foods&action=create   (ADMIN)
    // ────────────────────────────────────────────────
    case 'create':
        if ($method !== 'POST') {
            sendResponse(405, false, 'Method not allowed');
        }

        // TODO: Add admin authorization check here in production

        $data = getJsonInput();

        $category_id = $data['category_id'] ?? null;
        $food_name   = trim($data['food_name']   ?? '');
        $description = trim($data['description'] ?? '');
        $price       = $data['price']       ?? null;
        $image_url   = trim($data['image_url']   ?? '');

        if (!isValidId($category_id) || empty($food_name) || !is_numeric($price) || $price < 0) {
            sendResponse(400, false, 'Invalid or missing required fields');
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO foods 
                (category_id, food_name, description, price, image_url, is_available, created_at)
                VALUES (?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([(int)$category_id, $food_name, $description, (float)$price, $image_url]);

            $food_id = $conn->lastInsertId();

            sendResponse(201, true, 'Food created successfully', ['food_id' => $food_id]);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to create food');
        }
        break;

    // ────────────────────────────────────────────────
    // PUT /foods?route=foods&action=update&id=...
    // ────────────────────────────────────────────────
    case 'update':
        if ($method !== 'PUT' && $method !== 'PATCH') {
            sendResponse(405, false, 'Method not allowed');
        }

        // TODO: Add admin authorization check here

        $id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!isValidId($id)) {
            sendResponse(400, false, 'Invalid food ID');
        }

        $data = getJsonInput();

        $updates = [];
        $params  = [];

        if (isset($data['category_id']) && isValidId($data['category_id'])) {
            $updates[] = "category_id = :category_id";
            $params[':category_id'] = (int)$data['category_id'];
        }
        if (isset($data['food_name']) && trim($data['food_name']) !== '') {
            $updates[] = "food_name = :food_name";
            $params[':food_name'] = trim($data['food_name']);
        }
        if (isset($data['description'])) {
            $updates[] = "description = :description";
            $params[':description'] = trim($data['description']);
        }
        if (isset($data['price']) && is_numeric($data['price']) && $data['price'] >= 0) {
            $updates[] = "price = :price";
            $params[':price'] = (float)$data['price'];
        }
        if (isset($data['image_url'])) {
            $updates[] = "image_url = :image_url";
            $params[':image_url'] = trim($data['image_url']);
        }
        if (isset($data['is_available']) && in_array($data['is_available'], [0, 1])) {
            $updates[] = "is_available = :is_available";
            $params[':is_available'] = (int)$data['is_available'];
        }

        if (empty($updates)) {
            sendResponse(400, false, 'No valid fields to update');
        }

        $sql = "UPDATE foods SET " . implode(', ', $updates) . " WHERE food_id = :id";
        $params[':id'] = (int)$id;

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Food not found or no changes made');
            }

            sendResponse(200, true, 'Food updated successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to update food');
        }
        break;

    // ────────────────────────────────────────────────
    // DELETE /foods?route=foods&action=delete&id=...
    // ────────────────────────────────────────────────
    case 'delete':
        if ($method !== 'DELETE') {
            sendResponse(405, false, 'Method not allowed');
        }

        // TODO: Add admin authorization check

        $id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!isValidId($id)) {
            sendResponse(400, false, 'Invalid food ID');
        }

        try {
            $stmt = $conn->prepare("DELETE FROM foods WHERE food_id = ?");
            $stmt->execute([(int)$id]);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Food not found');
            }

            sendResponse(200, true, 'Food deleted successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to delete food');
        }
        break;

    // ────────────────────────────────────────────────
    // GET /foods?route=foods&action=categories
    // ────────────────────────────────────────────────
    case 'categories':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        try {
            $stmt = $conn->query("
                SELECT category_id, category_name, description 
                FROM categories 
                ORDER BY category_name ASC
            ");
            $categories = $stmt->fetchAll();

            sendResponse(200, true, 'Categories retrieved successfully', $categories);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch categories');
        }
        break;

    default:
        sendResponse(400, false, 'Invalid action');
}