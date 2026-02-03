<?php

// backend/models/Cart.php

class Cart {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Get existing cart for user or create a new one if none exists
     *
     * @param int $user_id
     * @return int|null cart_id or null on failure
     */
    public function getOrCreateCart(int $user_id): ?int {
        if ($user_id <= 0) {
            return null;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT cart_id FROM carts WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cart) {
                $cart_id = (int)$cart['cart_id'];
            } else {
                $stmt = $this->conn->prepare("INSERT INTO carts (user_id, created_at) VALUES (?, NOW())");
                $stmt->execute([$user_id]);
                $cart_id = (int)$this->conn->lastInsertId();
            }

            $this->conn->commit();
            return $cart_id;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Cart::getOrCreateCart error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all items in a cart with food details and subtotal
     *
     * @param int $cart_id
     * @return array
     */
    public function getCartItems(int $cart_id): array {
        if ($cart_id <= 0) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    ci.cart_item_id,
                    ci.food_id,
                    f.food_name,
                    f.price,
                    f.image_url,
                    ci.quantity,
                    ROUND(ci.quantity * f.price, 2) AS subtotal
                FROM cart_items ci
                JOIN foods f ON ci.food_id = f.food_id
                WHERE ci.cart_id = ?
                ORDER BY ci.cart_item_id ASC
            ");
            $stmt->execute([$cart_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Cart::getCartItems error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Add item to cart (increase quantity if already exists)
     *
     * @param int $cart_id
     * @param int $food_id
     * @param int $quantity
     * @return bool
     */
    public function addItem(int $cart_id, int $food_id, int $quantity = 1): bool {
        if ($cart_id <= 0 || $food_id <= 0 || $quantity <= 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            // Check if item already exists
            $stmt = $this->conn->prepare("
                SELECT cart_item_id, quantity 
                FROM cart_items 
                WHERE cart_id = ? AND food_id = ?
                LIMIT 1
            ");
            $stmt->execute([$cart_id, $food_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $new_quantity = (int)$existing['quantity'] + $quantity;
                $stmt = $this->conn->prepare("
                    UPDATE cart_items 
                    SET quantity = ? 
                    WHERE cart_item_id = ?
                ");
                $stmt->execute([$new_quantity, $existing['cart_item_id']]);
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO cart_items (cart_id, food_id, quantity) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$cart_id, $food_id, $quantity]);
            }

            $this->conn->commit();
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Cart::addItem error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update quantity of a cart item
     * Deletes item if quantity <= 0
     *
     * @param int $cart_item_id
     * @param int $quantity
     * @return bool
     */
    public function updateItem(int $cart_item_id, int $quantity): bool {
        if ($cart_item_id <= 0) {
            return false;
        }

        try {
            if ($quantity <= 0) {
                // Remove item
                $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
                $stmt->execute([$cart_item_id]);
                return $stmt->rowCount() > 0;
            }

            $stmt = $this->conn->prepare("
                UPDATE cart_items 
                SET quantity = ? 
                WHERE cart_item_id = ?
            ");
            $stmt->execute([$quantity, $cart_item_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Cart::updateItem error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a single item from cart
     *
     * @param int $cart_item_id
     * @return bool
     */
    public function removeItem(int $cart_item_id): bool {
        if ($cart_item_id <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE cart_item_id = ?");
            $stmt->execute([$cart_item_id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Cart::removeItem error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all items from a cart (does not delete cart record)
     *
     * @param int $cart_id
     * @return bool
     */
    public function clearCart(int $cart_id): bool {
        if ($cart_id <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM cart_items WHERE cart_id = ?");
            $stmt->execute([$cart_id]);
            return true; // even if no rows affected, it's still "successful"
        } catch (PDOException $e) {
            error_log("Cart::clearCart error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate total amount of items in cart
     *
     * @param int $cart_id
     * @return float|null
     */
    public function getCartTotal(int $cart_id): ?float {
        if ($cart_id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COALESCE(SUM(ci.quantity * f.price), 0) AS total
                FROM cart_items ci
                JOIN foods f ON ci.food_id = f.food_id
                WHERE ci.cart_id = ?
            ");
            $stmt->execute([$cart_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return round((float)$result['total'], 2);
        } catch (PDOException $e) {
            error_log("Cart::getCartTotal error: " . $e->getMessage());
            return null;
        }
    }
}