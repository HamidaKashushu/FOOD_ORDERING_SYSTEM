<?php
/**
 * Food Ordering System - CartController
 * Handles all shopping cart operations for authenticated users:
 * viewing cart, adding items, updating quantities, removing items,
 * clearing cart, and calculating totals.
 *
 * All endpoints require authentication (user_id from JWT/middleware).
 * Responses are standardized JSON via Response class.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/CartItem.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class CartController
{
    private Cart $cartModel;
    private CartItem $cartItemModel;
    private Product $productModel;
    private Request $request;

    /**
     * Constructor - initializes models and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->cartModel = new Cart();
        $this->cartItemModel = new CartItem();
        $this->productModel = new Product();
    }

    /**
     * Get user's current cart contents (GET /cart)
     *
     * @param int $userId Authenticated user ID (from JWT/middleware)
     * @return never
     */
    public function getCart(int $userId): never
    {
        $items = $this->cartModel->getCart($userId);
        $total = $this->cartModel->getTotal($userId);

        Response::success([
            'items' => $items,
            'total' => $total,
            'item_count' => count($items)
        ], 'Cart retrieved successfully');
    }

    /**
     * Add product to cart (POST /cart)
     *
     * @return never
     */
    public function addItem(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'product_id' => 'required|numeric',
            'quantity'   => 'required|numeric|min:1'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Failed to add item to cart');
        }

        $productId = (int)$data['product_id'];
        $quantity  = (int)$data['quantity'];

        // Verify product exists and is available
        $product = $this->productModel->findById($productId);
        if (!$product || $product['status'] !== 'available') {
            Response::error('Product not found or unavailable', 404);
        }

        // In real app: user_id comes from authenticated session/JWT
        $userId = $this->request->user['id'] ?? 0; // ← Replace with actual auth context

        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $success = $this->cartModel->addItem($userId, $productId, $quantity);

        if ($success) {
            $updatedCart = $this->cartModel->getCart($userId);
            $total = $this->cartModel->getTotal($userId);

            Response::success([
                'message' => 'Item added to cart',
                'cart'    => $updatedCart,
                'total'   => $total
            ], 'Item added to cart', 201);
        }

        Response::error('Failed to add item to cart', 500);
    }

    /**
     * Update item quantity in cart (PATCH/POST /cart/items)
     *
     * @return never
     */
    public function updateItem(): never
    {
        if (!$this->request->isMethod('PATCH') && !$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'product_id' => 'required|numeric',
            'quantity'   => 'required|numeric|min:0'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Failed to update cart item');
        }

        $productId = (int)$data['product_id'];
        $quantity  = (int)$data['quantity'];

        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $success = $this->cartModel->updateItem($userId, $productId, $quantity);

        if ($success) {
            $updatedCart = $this->cartModel->getCart($userId);
            $total = $this->cartModel->getTotal($userId);

            Response::success([
                'message' => $quantity === 0 ? 'Item removed from cart' : 'Cart item updated',
                'cart'    => $updatedCart,
                'total'   => $total
            ]);
        }

        Response::error('Failed to update cart item', 500);
    }

    /**
     * Remove specific item from cart (DELETE /cart/remove)
     *
     * @return never
     */
    public function removeItem(): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $data = $this->request->all();

        if (empty($data['product_id'])) {
             Response::error('Product ID is required', 400);
        }

        $productId = (int)$data['product_id'];

        $success = $this->cartModel->removeItem($userId, $productId);

        if ($success) {
            $updatedCart = $this->cartModel->getCart($userId);
            $total = $this->cartModel->getTotal($userId);

            Response::success([
                'message' => 'Item removed from cart',
                'cart'    => $updatedCart,
                'total'   => $total
            ]);
        }

        Response::error('Failed to remove item', 500);
    }

    /**
     * Clear entire cart (DELETE /cart)
     *
     * @param int $userId Authenticated user ID
     * @return never
     */
    public function clearCart(int $userId): never
    {
        $success = $this->cartModel->clearCart($userId);

        if ($success) {
            Response::success(['message' => 'Cart cleared successfully']);
        }

        Response::error('Failed to clear cart', 500);
    }

    /**
     * Get cart total amount (GET /cart/total)
     *
     * @param int $userId Authenticated user ID
     * @return never
     */
    public function getTotal(int $userId): never
    {
        $total = $this->cartModel->getTotal($userId);

        Response::success([
            'total' => $total,
            'currency' => 'TZS' // Adjust based on your system
        ], 'Cart total retrieved');
    }

    /*
     * Typical routing usage in routes/cart.php or index.php:
     *
     * $cartCtrl = new CartController($request);
     *
     * // All routes protected by AuthMiddleware
     * $router->get('/cart',        fn() => $cartCtrl->getCart($userId));
     * $router->post('/cart',       [$cartCtrl, 'addItem']);
     * $router->patch('/cart',      [$cartCtrl, 'updateItem']);
     * $router->delete('/cart/items/{id}', fn($id) => $cartCtrl->removeItem((int)$id));
     * $router->delete('/cart',     fn() => $cartCtrl->clearCart($userId));
     * $router->get('/cart/total',  fn() => $cartCtrl->getTotal($userId));
     */
}