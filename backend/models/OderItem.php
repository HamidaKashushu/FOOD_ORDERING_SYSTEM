<?php
/**
 * Food Ordering System - OrderItem Model
 * Manages individual line items within an order:
 * creation, retrieval with product details, quantity updates,
 * deletion, and subtotal calculations.
 *
 * Works together with Order and Product models.
 * All database operations use prepared statements.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class OrderItem
{
    private PDO $db;
    private Product $productModel;

    /**
     * Constructor - initializes PDO and Product model dependency
     *
     * @param PDO|null $pdo Optional PDO instance
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
        $this->productModel = new Product($this->db);
    }

    /**
     * Create a new order item (usually called during order creation)
     *
     * @param int $orderId   Order ID (from orders table)
     * @param int $productId Product ID
     * @param int $quantity  Quantity ordered
     * @return bool Success status
     */
    public function create(int $orderId, int $productId, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $product = $this->productModel->findById($productId);
        if (!$product) {
            return false;
        }

        $priceAtTime = (float)$product['price'];
        $subtotal = $priceAtTime * $quantity;

        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, price_at_time, subtotal
            ) VALUES (
                :order_id, :product_id, :quantity, :price_at_time, :subtotal
            )
        ");

        return $stmt->execute([
            ':order_id'      => $orderId,
            ':product_id'    => $productId,
            ':quantity'      => $quantity,
            ':price_at_time' => $priceAtTime,
            ':subtotal'      => $subtotal
        ]);
    }

    /**
     * Get all items for a specific order with product details and subtotal
     *
     * @param int $orderId Order ID
     * @return array List of order items including product info
     */
    public function getByOrderId(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.quantity,
                oi.price_at_time,
                oi.subtotal,
                p.name,
                p.description,
                p.image AS image_url,
                p.status AS product_status,
                c.name AS category_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE oi.order_id = :order_id
            ORDER BY p.name ASC
        ");

        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Update quantity of an existing order item
     * Recalculates subtotal automatically
     *
     * @param int $id       Order item ID (order_items.id)
     * @param int $quantity New quantity
     * @return bool Success status
     */
    public function updateQuantity(int $id, int $quantity): bool
    {
        if ($quantity < 1) {
            return $this->delete($id);
        }

        $item = $this->getItem($id);
        if (!$item) {
            return false;
        }

        $newSubtotal = $item['price_at_time'] * $quantity;

        $stmt = $this->db->prepare("
            UPDATE order_items
            SET quantity = :quantity,
                subtotal = :subtotal
            WHERE id = :id
        ");

        return $stmt->execute([
            ':quantity'  => $quantity,
            ':subtotal'  => $newSubtotal,
            ':id'        => $id
        ]);
    }

    /**
     * Delete an order item by its primary key
     *
     * @param int $id Order item ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM order_items WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Calculate subtotal for a product and quantity
     * (Used for preview or validation before saving)
     *
     * @param int $productId Product ID
     * @param int $quantity  Quantity
     * @return float Subtotal or 0.0 if product not found
     */
    public function calculateSubtotal(int $productId, int $quantity): float
    {
        if ($quantity < 1) {
            return 0.0;
        }

        $product = $this->productModel->findById($productId);
        if (!$product) {
            return 0.0;
        }

        return (float)$product['price'] * $quantity;
    }

    /**
     * Internal helper: Fetch single order item by ID
     *
     * @param int $id Order item ID
     * @return array|null
     */
    private function getItem(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM order_items
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /*
     * Typical usage in OrderController or Order model:
     *
     * $orderItemModel = new OrderItem();
     *
     * // During order creation (called from Order::create)
     * foreach ($cartItems as $item) {
     *     $orderItemModel->create($newOrderId, $item['product_id'], $item['quantity']);
     * }
     *
     * // View order details (GET /orders/{id})
     * $orderItems = $orderItemModel->getByOrderId($orderId);
     *
     * // Admin adjust quantity (rare case)
     * $orderItemModel->updateQuantity($orderItemId, 5);
     *
     * // Real-time subtotal preview
     * $preview = $orderItemModel->calculateSubtotal($productId, $requestedQty);
     */
}