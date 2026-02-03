<?php

// backend/routes/cart.php
// Shopping Cart Management Endpoints

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
// Helper: Get or create cart for a user
// ────────────────────────────────────────────────
function getOrCreateCart(PDO $conn, $user_id) {
    if (!is_numeric($user_id) || (int)$user_id <= 0) {
        throw new Exception('Invalid user ID', 400);
    }

    $user_id = (int)$user_id;

    $stmt = $conn->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();

    if ($cart) {
        return (int)$cart['cart_id'];
    }

    // Create new cart
    $stmt = $conn->prepare("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())");
    $stmt->execute([$user_id]);
    return (int)$conn->lastInsertId();
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
// Main logic
// ────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {

    // ────────────────────────────────────────────────
    // GET ?route=cart&action=get&user_id=...
    // ────────────────────────────────────────────────
    case 'get':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

        try {
            $cart_id = getOrCreateCart($conn, $user_id);

            $stmt = $conn->prepare("
                SELECT 
                    ci.cart_item_id,
                    ci.food_id,
                    f.food_name,
                    f.price,
                    f.image_url,
                    ci.quantity,
                    (ci.quantity * f.price) AS subtotal
                FROM cart_items ci
                JOIN foods f ON ci.food_id = f.food_id
                WHERE ci.cart_id = ?
                ORDER BY ci.cart_item_id ASC
            ");
            $stmt->execute([$cart_id]);
            $items = $stmt->fetchAll();

            // Calculate total
            $total = array_sum(array_column($items, 'subtotal'));

            sendResponse(200, true, 'Cart retrieved', [
                'cart_id' => $cart_id,
                'items'   => $items,
                'total'   => round($total, 2)
            ]);
        } catch (Exception $e) {
            $code = method_exists($e, 'getCode') ? $e->getCode() : 500;
            sendResponse($code ?: 500, false, $e->getMessage());
        }
        break;

    // ────────────────────────────────────────────────
    // POST ?route=cart&action=add
    // ────────────────────────────────────────────────
    case 'add':
        if ($method !== 'POST') {
            sendResponse(405, false, 'Method not allowed');
        }

        $data = getJsonInput();

        $user_id  = $data['user_id']  ?? null;
        $food_id  = $data['food_id']  ?? null;
        $quantity = $data['quantity'] ?? null;

        if (!is_numeric($user_id) || !is_numeric($food_id) || !is_numeric($quantity)) {
            sendResponse(400, false, 'user_id, food_id and quantity are required and must be numeric');
        }

        $user_id  = (int)$user_id;
        $food_id  = (int)$food_id;
        $quantity = (int)$quantity;

        if ($quantity <= 0) {
            sendResponse(400, false, 'Quantity must be greater than 0');
        }

        try {
            $cart_id = getOrCreateCart($conn, $user_id);

            // Check if item already exists in cart
            $stmt = $conn->prepare("
                SELECT cart_item_id, quantity 
                FROM cart_items 
                WHERE cart_id = ? AND food_id = ?
                LIMIT 1
            ");
            $stmt->execute([$cart_id, $food_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update quantity
                $new_qty = $existing['quantity'] + $quantity;
                $stmt = $conn->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE cart_item_id = ?
                ");
                $stmt->execute([$new_qty, $existing['cart_item_id']]);
            } else {
                // Insert new item
                $stmt = $conn->prepare("
                    INSERT INTO cart_items (cart_id, food_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$cart_id, $food_id, $quantity]);
            }

            sendResponse(200, true, 'Item added to cart');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to add item to cart');
        }
        break;

    // ────────────────────────────────────────────────
    // PUT ?route=cart&action=update&id=...
    // ────────────────────────────────────────────────
    case 'update':
        if ($method !== 'PUT' && $method !== 'PATCH') {
            sendResponse(405, false, 'Method not allowed');
        }

        $cart_item_id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!is_numeric($cart_item_id) || (int)$cart_item_id <= 0) {
            sendResponse(400, false, 'Invalid cart_item_id');
        }

        $data = getJsonInput();
        $quantity = $data['quantity'] ?? null;

        if (!is_numeric($quantity)) {
            sendResponse(400, false, 'quantity is required and must be numeric');
        }

        $quantity = (int)$quantity;

        try {
            if ($quantity <= 0) {
                // Delete item if quantity <= 0
                $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
                $stmt->execute([(int)$cart_item_id]);
                $actionMsg = 'Item removed from cart';
            } else {
                // Update quantity
                $stmt = $conn->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE cart_item_id = ?
                ");
                $stmt->execute([$quantity, (int)$cart_item_id]);
                $actionMsg = 'Cart item updated';
            }

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Cart item not found');
            }

            sendResponse(200, true, $actionMsg);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to update cart item');
        }
        break;

    // ────────────────────────────────────────────────
    // DELETE ?route=cart&action=remove&id=...
    // ────────────────────────────────────────────────
    case 'remove':
        if ($method !== 'DELETE') {
            sendResponse(405, false, 'Method not allowed');
        }

        $cart_item_id = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!is_numeric($cart_item_id) || (int)$cart_item_id <= 0) {
            sendResponse(400, false, 'Invalid cart_item_id');
        }

        try {
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt->execute([(int)$cart_item_id]);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Cart item not found');
            }

            sendResponse(200, true, 'Item removed from cart');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to remove item');
        }
        break;

    // ────────────────────────────────────────────────
    // DELETE ?route=cart&action=clear&user_id=...
    // ────────────────────────────────────────────────
    case 'clear':
        if ($method !== 'DELETE') {
            sendResponse(405, false, 'Method not allowed');
        }

        $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

        try {
            $cart_id = getOrCreateCart($conn, $user_id);

            $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart_id]);

            sendResponse(200, true, 'Cart cleared successfully');
        } catch (Exception $e) {
            $code = method_exists($e, 'getCode') ? $e->getCode() : 500;
            sendResponse($code ?: 500, false, $e->getMessage());
        }
        break;

    default:
        sendResponse(400, false, 'Invalid action');
}