<?php

// backend/routes/orders.php
// Order Management Endpoints for Food Ordering System

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
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

switch ($action) {

    // ────────────────────────────────────────────────
    // POST ?route=orders&action=place
    // ────────────────────────────────────────────────
    case 'place':
        if ($method !== 'POST') {
            sendResponse(405, false, 'Method not allowed');
        }

        $data = getJsonInput();

        $user_id       = $data['user_id']       ?? null;
        $payment_method = trim($data['payment_method'] ?? '');

        if (!isValidId($user_id) || empty($payment_method)) {
            sendResponse(400, false, 'user_id and payment_method are required');
        }

        $user_id = (int)$user_id;

        try {
            $conn->beginTransaction();

            // Get user's cart
            $stmt = $conn->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $cart = $stmt->fetch();

            if (!$cart) {
                throw new Exception('No cart found for user', 400);
            }

            $cart_id = (int)$cart['cart_id'];

            // Get cart items with food details
            $stmt = $conn->prepare("
                SELECT 
                    ci.food_id, 
                    f.price, 
                    ci.quantity 
                FROM cart_items ci
                JOIN foods f ON ci.food_id = f.food_id
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cart_id]);
            $cartItems = $stmt->fetchAll();

            if (empty($cartItems)) {
                throw new Exception('Cart is empty', 400);
            }

            // Calculate total
            $total_amount = 0;
            foreach ($cartItems as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }
            $total_amount = round($total_amount, 2);

            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders (user_id, total_amount, status, order_date)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $total_amount]);
            $order_id = (int)$conn->lastInsertId();

            // Insert order items
            $stmt = $conn->prepare("
                INSERT INTO order_items (order_id, food_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($cartItems as $item) {
                $stmt->execute([
                    $order_id,
                    $item['food_id'],
                    $item['quantity'],
                    $item['price']
                ]);
            }

            // Create payment record (for cash → pending, for others might be 'paid' immediately)
            $payment_status = ($payment_method === 'cash') ? 'pending' : 'paid';

            $stmt = $conn->prepare("
                INSERT INTO payments (order_id, payment_method, payment_status, paid_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$order_id, $payment_method, $payment_status]);

            // Clear cart items
            $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart_id]);

            $conn->commit();

            sendResponse(201, true, 'Order placed successfully', ['order_id' => $order_id]);

        } catch (Exception $e) {
            $conn->rollBack();
            $code = $e->getCode() ?: 500;
            if ($code < 400 || $code > 599) $code = 500;
            sendResponse($code, false, $e->getMessage());
        }
        break;

    // ────────────────────────────────────────────────
    // GET ?route=orders&action=user&user_id=...
    // ────────────────────────────────────────────────
    case 'user':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        $user_id = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

        if (!isValidId($user_id)) {
            sendResponse(400, false, 'Invalid user_id');
        }

        $user_id = (int)$user_id;

        try {
            $stmt = $conn->prepare("
                SELECT 
                    order_id, 
                    total_amount, 
                    status, 
                    order_date 
                FROM orders 
                WHERE user_id = ?
                ORDER BY order_date DESC
            ");
            $stmt->execute([$user_id]);
            $orders = $stmt->fetchAll();

            sendResponse(200, true, 'User orders retrieved', $orders);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch user orders');
        }
        break;

    // ────────────────────────────────────────────────
    // GET ?route=orders&action=single&id=...
    // ────────────────────────────────────────────────
    case 'single':
        if ($method !== 'GET') {
            sendResponse(405, false, 'Method not allowed');
        }

        $order_id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($order_id)) {
            sendResponse(400, false, 'Invalid order ID');
        }

        $order_id = (int)$order_id;

        try {
            // Order details
            $stmt = $conn->prepare("
                SELECT 
                    o.order_id, 
                    o.user_id, 
                    o.total_amount, 
                    o.status, 
                    o.order_date,
                    p.payment_method,
                    p.payment_status,
                    p.paid_at
                FROM orders o
                LEFT JOIN payments p ON o.order_id = p.order_id
                WHERE o.order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                sendResponse(404, false, 'Order not found');
            }

            // Order items
            $stmt = $conn->prepare("
                SELECT 
                    oi.order_item_id,
                    oi.food_id,
                    f.food_name,
                    oi.quantity,
                    oi.price,
                    (oi.quantity * oi.price) AS subtotal
                FROM order_items oi
                JOIN foods f ON oi.food_id = f.food_id
                WHERE oi.order_id = ?
                ORDER BY oi.order_item_id ASC
            ");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();

            $order['items'] = $items;

            sendResponse(200, true, 'Order details retrieved', $order);
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to fetch order details');
        }
        break;

    // ────────────────────────────────────────────────
    // PUT ?route=orders&action=status&id=...
    // ────────────────────────────────────────────────
    case 'status':
        if ($method !== 'PUT' && $method !== 'PATCH') {
            sendResponse(405, false, 'Method not allowed');
        }

        // TODO: Add admin authorization check in production

        $order_id = isset($_GET['id']) ? trim($_GET['id']) : '';

        if (!isValidId($order_id)) {
            sendResponse(400, false, 'Invalid order ID');
        }

        $data = getJsonInput();
        $status = trim($data['status'] ?? '');

        $allowedStatuses = ['pending', 'preparing', 'delivered', 'cancelled'];

        if (!in_array($status, $allowedStatuses)) {
            sendResponse(400, false, 'Invalid order status. Allowed: ' . implode(', ', $allowedStatuses));
        }

        $order_id = (int)$order_id;

        try {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET status = ? 
                WHERE order_id = ?
            ");
            $stmt->execute([$status, $order_id]);

            if ($stmt->rowCount() === 0) {
                sendResponse(404, false, 'Order not found');
            }

            sendResponse(200, true, 'Order status updated successfully');
        } catch (Exception $e) {
            sendResponse(500, false, 'Failed to update order status');
        }
        break;

    default:
        sendResponse(400, false, 'Invalid action');
}