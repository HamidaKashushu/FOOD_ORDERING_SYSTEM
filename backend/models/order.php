<?php

// backend/models/Order.php

class Order {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Place a new order from cart items
     * Uses transaction for atomicity
     *
     * @param int $user_id
     * @param array $cart_items Array of items: [['food_id' => int, 'quantity' => int, 'price' => float], ...]
     * @param string $payment_method e.g. "cash", "mobile", "card"
     * @return int|null The new order_id or null on failure
     */
    public function placeOrder(int $user_id, array $cart_items, string $payment_method): ?int {
        if ($user_id <= 0 || empty($cart_items)) {
            return null;
        }

        $total_amount = 0.0;
        foreach ($cart_items as $item) {
            if (!isset($item['food_id'], $item['quantity'], $item['price']) ||
                !is_numeric($item['food_id']) || !is_numeric($item['quantity']) || !is_numeric($item['price'])) {
                return null;
            }
            $total_amount += (float)$item['price'] * (int)$item['quantity'];
        }

        $total_amount = round($total_amount, 2);

        try {
            $this->conn->beginTransaction();

            // Create order
            $stmt = $this->conn->prepare("
                INSERT INTO orders 
                (user_id, total_amount, status, order_date)
                VALUES (?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $total_amount]);
            $order_id = (int)$this->conn->lastInsertId();

            // Insert order items
            $stmt_item = $this->conn->prepare("
                INSERT INTO order_items 
                (order_id, food_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($cart_items as $item) {
                $stmt_item->execute([
                    $order_id,
                    (int)$item['food_id'],
                    (int)$item['quantity'],
                    (float)$item['price']
                ]);
            }

            // Create payment record
            $payment_status = ($payment_method === 'cash') ? 'pending' : 'paid';

            $stmt_payment = $this->conn->prepare("
                INSERT INTO payments 
                (order_id, payment_method, payment_status, paid_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt_payment->execute([$order_id, $payment_method, $payment_status]);

            $this->conn->commit();
            return $order_id;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Order::placeOrder failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all orders for a specific user
     *
     * @param int $user_id
     * @return array
     */
    public function getUserOrders(int $user_id): array {
        if ($user_id <= 0) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Order::getUserOrders error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed information for a single order
     *
     * @param int $order_id
     * @return array|null
     */
    public function getOrderById(int $order_id): ?array {
        if ($order_id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
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
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return null;
            }

            // Get order items
            $stmt_items = $this->conn->prepare("
                SELECT 
                    oi.order_item_id,
                    oi.food_id,
                    f.food_name,
                    oi.quantity,
                    oi.price,
                    ROUND(oi.quantity * oi.price, 2) AS subtotal
                FROM order_items oi
                JOIN foods f ON oi.food_id = f.food_id
                WHERE oi.order_id = ?
                ORDER BY oi.order_item_id ASC
            ");
            $stmt_items->execute([$order_id]);
            $order['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            return $order;
        } catch (PDOException $e) {
            error_log("Order::getOrderById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update the status of an order
     *
     * @param int $order_id
     * @param string $status (pending, preparing, delivered, cancelled, etc.)
     * @return bool
     */
    public function updateOrderStatus(int $order_id, string $status): bool {
        if ($order_id <= 0 || empty($status)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE orders 
                SET status = ? 
                WHERE order_id = ?
            ");
            $stmt->execute([trim($status), $order_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Order::updateOrderStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all orders (admin view) with user information
     *
     * @return array
     */
    public function getAllOrders(): array {
        try {
            $stmt = $this->conn->query("
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Order::getAllOrders error: " . $e->getMessage());
            return [];
        }
    }
}