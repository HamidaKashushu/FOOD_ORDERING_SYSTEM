<?php
/**
 * Food Ordering System - CartItem Model
 * Manages individual line items in a shopping cart:
 * creation, quantity updates, deletion, subtotal calculation,
 * and retrieval with product details.
 *
 * Works closely with Cart model and Product model.
 * All database operations use prepared statements.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class CartItem
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
     * Create a new cart item
     *
     * @param int $cartId    Cart ID (from carts table)
     * @param int $productId Product ID
     * @param int $quantity  Initial quantity (default 1)
     * @return bool Success status
     */
    public function create(int $cartId, int $productId, int $quantity = 1): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $product = $this->productModel->findById($productId);
        if (!$product || $product['status'] !== 'available') {
            return false;
        }

        $priceAtTime = (float)$product['price'];
        $subtotal = $priceAtTime * $quantity;

        $stmt = $this->db->prepare("
            INSERT INTO cart_items (
                cart_id, product_id, quantity, price_at_time
            ) VALUES (
                :cart_id, :product_id, :quantity, :price_at_time
            )
        ");

        return $stmt->execute([
            ':cart_id'       => $cartId,
            ':product_id'    => $productId,
            ':quantity'      => $quantity,
            ':price_at_time' => $priceAtTime
        ]);
    }

    /**
     * Update quantity of an existing cart item
     * If quantity becomes 0, deletes the item
     *
     * @param int $id       Cart item ID (cart_items.id)
     * @param int $quantity New quantity
     * @return bool Success status
     */
    public function updateQuantity(int $id, int $quantity): bool
    {
        if ($quantity < 0) {
            return false;
        }

        if ($quantity === 0) {
            return $this->delete($id);
        }

        $stmt = $this->db->prepare("
            UPDATE cart_items
            SET quantity = :quantity
            WHERE id = :id
        ");

        return $stmt->execute([
            ':quantity' => $quantity,
            ':id'       => $id
        ]);
    }

    /**
     * Delete a cart item by its primary key
     *
     * @param int $id Cart item ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM cart_items WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all items in a specific cart with product details and subtotal
     *
     * @param int $cartId Cart ID
     * @return array List of cart items with product info
     */
    public function getByCartId(int $cartId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                ci.id,
                ci.cart_id,
                ci.product_id,
                ci.quantity,
                ci.price_at_time,
                (ci.quantity * ci.price_at_time) AS subtotal,
                p.name,
                p.description,
                p.image AS image_url,
                p.status AS product_status,
                c.name AS category_name
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE ci.cart_id = :cart_id
            ORDER BY p.name ASC
        ");

        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    /**
     * Calculate subtotal for a product and quantity
     * (Used internally and for real-time calculations)
     *
     * @param int $productId Product ID
     * @param int $quantity  Quantity
     * @return float Subtotal amount or 0.0 if product not found
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

    /*
     * Typical usage in CartController or Cart model:
     *
     * $cartItemModel = new CartItem();
     *
     * // Add item (used internally by Cart::addItem)
     * $cartItemModel->create($cartId, $productId, $quantity);
     *
     * // Update item quantity
     * $cartItemModel->updateQuantity($cartItemId, 3);
     *
     * // Get full cart items with details
     * $items = $cartItemModel->getByCartId($cartId);
     *
     * // Real-time subtotal preview (before saving)
     * $previewSubtotal = $cartItemModel->calculateSubtotal($productId, $requestedQty);
     */
}