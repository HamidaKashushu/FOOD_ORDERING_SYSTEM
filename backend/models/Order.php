<?php
/**
 * Food Ordering System - Order Model
 * Manages order creation from cart, order history retrieval,
 * status updates, and cleanup of completed/cancelled orders.
 *
 * Creates order + order_items records atomically (transaction used).
 * Generates unique order numbers and calculates totals.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Cart.php';
require_once __DIR__ . '/CartItem.php';

class Order
{
    public PDO $db;

    /**
     * Constructor - injects or obtains PDO connection
     *
     * @param PDO|null $pdo Optional PDO instance
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Create a new order from cart items
     * Uses transaction to ensure atomicity (order + items)
     *
     * @param int   $userId    Authenticated user ID
     * @param array $cartItems Array of cart items from Cart::getCart() or CartItem::getByCartId()
     * @return bool Success status
     */
    public function create(int $userId, array $cartItems): bool
    {
        if (empty($cartItems)) {
            return false;
        }

        $totalAmount = 0.0;
        foreach ($cartItems as $item) {
            $totalAmount += (float)$item['subtotal'];
        }

        if ($totalAmount <= 0) {
            return false;
        }

        $orderNumber = $this->generateOrderNumber();

        try {
            $this->db->beginTransaction();

            // Insert main order
            $stmt = $this->db->prepare("
                INSERT INTO orders (user_id, address_id, total_amount, status)
                VALUES (:user_id, :address_id, :total_amount, 'pending')
            ");

            // For simplicity, assume default/first address is used
            // In real app, pass address_id from request
            $addressId = 1; // ← Replace with actual logic: $request->body('address_id')

            $stmt->execute([
                ':user_id'      => $userId,
                ':address_id'   => $addressId,
                ':total_amount' => $totalAmount
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // Insert order items
            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (
                    order_id, product_id, quantity, price_at_time, subtotal
                ) VALUES (
                    :order_id, :product_id, :quantity, :price_at_time, :subtotal
                )
            ");

            foreach ($cartItems as $item) {
                $itemStmt->execute([
                    ':order_id'      => $orderId,
                    ':product_id'    => $item['product_id'],
                    ':quantity'      => $item['quantity'],
                    ':price_at_time' => $item['price_at_time'],
                    ':subtotal'      => $item['subtotal']
                ]);
            }

            // Clear user's cart after successful order
            $cartModel = new Cart($this->db);
            $cartModel->clearCart($userId);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Order creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get detailed order by ID including items
     *
     * @param int $id Order ID
     * @return array|null Order data with items or null
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT o.*, u.full_name, u.email,
                   a.street, a.city, a.region, a.notes AS address_notes
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN addresses a ON o.address_id = a.id
            WHERE o.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        $itemsStmt = $this->db->prepare("
            SELECT oi.*, p.name, p.image AS image_url, c.name AS category_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE oi.order_id = :order_id
            ORDER BY p.name ASC
        ");

        $itemsStmt->execute([':order_id' => $id]);
        $order['items'] = $itemsStmt->fetchAll();

        return $order;
    }

    /**
     * Get all orders for a specific user with items summary
     *
     * @param int $userId User ID
     * @return array List of orders (latest first)
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
                   COUNT(oi.id) AS item_count,
                   SUM(oi.quantity) AS total_items
            FROM orders o
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.user_id = :user_id
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");

        $stmt->execute([':user_id' => $userId]);
        $orders = $stmt->fetchAll();

        // Optionally fetch full items for each order (can be heavy)
        // For performance, usually done on demand via getById()

        return $orders;
    }

    /**
     * Update order status
     *
     * @param int    $id     Order ID
     * @param string $status New status: pending, preparing, delivering, completed, cancelled
     * @return bool Success
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['pending', 'preparing', 'delivering', 'completed', 'cancelled'];
        if (!in_array($status, $allowed)) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE orders
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id'     => $id
        ]);
    }

    /**
     * Delete an order and its items (admin use or cancelled cleanup)
     *
     * @param int $id Order ID
     * @return bool Success
     */
    public function delete(int $id): bool
    {
        try {
            $this->db->beginTransaction();

            $this->db->prepare("DELETE FROM order_items WHERE order_id = :id")
                     ->execute([':id' => $id]);

            $this->db->prepare("DELETE FROM orders WHERE id = :id")
                     ->execute([':id' => $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Generate unique order number (ORD + date + random 4 chars)
     *
     * @return string e.g. ORD20260205AB12
     */
    public function generateOrderNumber(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $number = "ORD{$date}{$random}";

        // Check uniqueness (very rare collision)
        $stmt = $this->db->prepare("SELECT 1 FROM orders WHERE order_number = :number LIMIT 1");
        $stmt->execute([':number' => $number]);

        if ($stmt->fetch()) {
            // Collision (extremely rare) → recurse
            return $this->generateOrderNumber();
        }

        return $number;
    }

    // getAll() method for admin to retrieve all orders with pagination and filters can be added here
    // e.g. getAll($status = null, $startDate = null, $endDate = null, $page = 1, $perPage = 20)    

    function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
                   u.full_name AS customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            ORDER BY o.created_at DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    function getOrdersInRange(string $start, string $end): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
                   u.full_name AS customer_name
            FROM orders o
            JOIN users u ON o.user_id = u.id
            WHERE DATE(o.created_at) BETWEEN :start AND :end
            ORDER BY o.created_at DESC
        ");

        $stmt->execute([':start' => $start, ':end' => $end]);
        return $stmt->fetchAll();
    }

    function getUserOrdersInRange(int $userId, string $start, string $end): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at
            FROM orders o
            WHERE o.user_id = :user_id
              AND DATE(o.created_at) BETWEEN :start AND :end
            ORDER BY o.created_at DESC
        ");

        $stmt->execute([':user_id' => $userId, ':start' => $start, ':end' => $end]);
        return $stmt->fetchAll();
    }

    /*
     * Typical usage in OrderController:
     *
     * $orderModel = new Order();
     *
     * // Create order from cart (POST /orders)
     * $cartModel = new Cart();
     * $cartItems = $cartModel->getCart($userId);
     *
     * if ($orderModel->create($userId, $cartItems)) {
     *     Response::created(['message' => 'Order placed successfully']);
     * } else {
     *     Response::error('Failed to create order', 500);
     * }
     *
     * // User order history (GET /orders)
     * $orders = $orderModel->getByUserId($userId);
     * Response::success($orders);
     *
     * // Admin update status (PATCH /admin/orders/{id})
     * $orderModel->updateStatus($orderId, $request->body('status'));
     */
}