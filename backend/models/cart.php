<?php
/**
 * Food Ordering System - Cart Model
 * Manages shopping cart operations for users:
 * adding items, updating quantities, removing items,
 * fetching full cart with product details, clearing cart,
 * and calculating totals.
 *
 * One active cart per user (enforced by UNIQUE constraint on user_id).
 * Uses prepared statements for all database operations.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Product.php';

class Cart
{
    private PDO $db;
    private Product $productModel;

    /**
     * Constructor - initializes PDO and Product model for fetching product details
     *
     * @param PDO|null $pdo Optional PDO instance
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
        $this->productModel = new Product($this->db);
    }

    /**
     * Add a product to the user's cart
     * If product already exists, increments quantity
     *
     * @param int $userId    Authenticated user ID
     * @param int $productId Product ID
     * @param int $quantity  Quantity to add (default 1)
     * @return bool Success status
     */
    public function addItem(int $userId, int $productId, int $quantity = 1): bool
    {
        if ($quantity < 1) {
            return false;
        }

        // Check if product already in cart
        $existing = $this->getItem($userId, $productId);

        if ($existing) {
            // Update quantity
            $newQuantity = $existing['quantity'] + $quantity;
            return $this->updateItem($userId, $productId, $newQuantity);
        }

        // Get current product price
        $product = $this->productModel->findById($productId);
        if (!$product || $product['status'] !== 'available') {
            return false;
        }

        $priceAtTime = (float)$product['price'];

        $stmt = $this->db->prepare("
            INSERT INTO cart_items (cart_id, product_id, quantity, price_at_time)
            SELECT id, :product_id, :quantity, :price_at_time
            FROM carts
            WHERE user_id = :user_id
        ");

        return $stmt->execute([
            ':user_id'       => $userId,
            ':product_id'    => $productId,
            ':quantity'      => $quantity,
            ':price_at_time' => $priceAtTime
        ]);
    }

    /**
     * Update quantity of a specific item in cart
     * If quantity reaches 0, removes the item
     *
     * @param int $userId    User ID
     * @param int $productId Product ID
     * @param int $quantity  New quantity (0 = remove)
     * @return bool Success
     */
    public function updateItem(int $userId, int $productId, int $quantity): bool
    {
        if ($quantity < 0) {
            return false;
        }

        if ($quantity === 0) {
            return $this->removeItem($userId, $productId);
        }

        $stmt = $this->db->prepare("
            UPDATE cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            SET ci.quantity = :quantity
            WHERE c.user_id = :user_id
              AND ci.product_id = :product_id
        ");

        return $stmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $productId,
            ':quantity'   => $quantity
        ]);
    }

    /**
     * Remove a specific product from user's cart
     *
     * @param int $userId    User ID
     * @param int $productId Product ID to remove
     * @return bool Success
     */
    public function removeItem(int $userId, int $productId): bool
    {
        $stmt = $this->db->prepare("
            DELETE ci
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE c.user_id = :user_id
              AND ci.product_id = :product_id
        ");

        return $stmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $productId
        ]);
    }

    /**
     * Get full cart contents for a user with product details
     *
     * @param int $userId User ID
     * @return array Cart items with product info and calculated subtotal
     */
    public function getCart(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                ci.id AS cart_item_id,
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
            JOIN carts cart ON ci.cart_id = cart.id
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE cart.user_id = :user_id
            ORDER BY p.name ASC
        ");

        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Remove all items from user's cart (clear cart)
     *
     * @param int $userId User ID
     * @return bool Success
     */
    public function clearCart(int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE ci
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE c.user_id = :user_id
        ");

        return $stmt->execute([':user_id' => $userId]);
    }

    /**
     * Calculate total cart amount for the user
     *
     * @param int $userId User ID
     * @return float Total price (0 if cart empty)
     */
    public function getTotal(int $userId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(ci.quantity * ci.price_at_time), 0) AS total
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE c.user_id = :user_id
        ");

        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();

        return (float)($result['total'] ?? 0.0);
    }

    /**
     * Internal helper: Get single cart item for a user/product
     *
     * @param int $userId
     * @param int $productId
     * @return array|null
     */
    private function getItem(int $userId, int $productId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ci.*
            FROM cart_items ci
            JOIN carts c ON ci.cart_id = c.id
            WHERE c.user_id = :user_id
              AND ci.product_id = :product_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id'    => $userId,
            ':product_id' => $productId
        ]);

        return $stmt->fetch() ?: null;
    }

    /*
     * Typical usage in CartController:
     *
     * $cartModel = new Cart();
     *
     * // Add to cart (POST /cart)
     * $cartModel->addItem($userId, $request->body('product_id'), $request->body('quantity') ?? 1);
     *
     * // View cart (GET /cart)
     * $cartItems = $cartModel->getCart($userId);
     * $total = $cartModel->getTotal($userId);
     * Response::success([
     *     'items' => $cartItems,
     *     'total' => $total
     * ]);
     *
     * // Update quantity (PATCH /cart/items/{productId})
     * $cartModel->updateItem($userId, $productId, $request->body('quantity'));
     *
     * // Remove item (DELETE /cart/items/{productId})
     * $cartModel->removeItem($userId, $productId);
     *
     * // Clear cart before checkout or logout
     * $cartModel->clearCart($userId);
     */
}