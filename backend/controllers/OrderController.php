<?php
/**
 * Food Ordering System - OrderController
 * Handles order creation from cart, order history retrieval,
 * single order details, status updates (admin), and order deletion (admin).
 *
 * User endpoints: createOrder, getOrder, getOrdersByUser
 * Admin endpoints: getAllOrders, updateStatus, deleteOrder
 *
 * All endpoints require authentication.
 * Admin actions should be protected with RoleMiddleware('admin')
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/OrderItem.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/CartItem.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class OrderController
{
    private Order $orderModel;
    private Cart $cartModel;
    private Request $request;

    /**
     * Constructor - initializes models and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->orderModel = new Order();
        $this->cartModel = new Cart();
    }

    /**
     * Create new order from current cart (POST /orders)
     *
     * @return never
     */
    public function createOrder(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $cartItems = $this->cartModel->getCart($userId);
        if (empty($cartItems)) {
            Response::error('Cart is empty. Add items before placing order.', 400);
        }

        $success = $this->orderModel->create($userId, $cartItems);

        if ($success) {
            // In real app: return the newly created order details
            // For simplicity, just success message
            Response::created(['message' => 'Order placed successfully']);
        }

        Response::error('Failed to create order. Please try again.', 500);
    }

    /**
     * Get single order details (GET /orders/{id})
     *
     * @param int $orderId Order ID
     * @return never
     */
    public function getOrder(int $orderId): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $order = $this->orderModel->getById($orderId);

        if (!$order) {
            Response::notFound('Order not found');
        }

        // Ensure user can only see their own orders (unless admin)
        $isAdmin = $this->request->user['role'] === 'admin';
        if (!$isAdmin && $order['user_id'] !== $userId) {
            Response::forbidden('You do not have permission to view this order');
        }

        Response::success($order, 'Order details retrieved');
    }

    /**
     * Get all orders for authenticated user (GET /orders)
     *
     * @return never
     */
    public function getOrdersByUser(): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $orders = $this->orderModel->getByUserId($userId);

        Response::success($orders, 'Your orders retrieved successfully');
    }

    /**
     * Get all orders (admin only) (GET /admin/orders)
     *
     * @return never
     */
    public function getAllOrders(): never
    {
        // This route should be protected by RoleMiddleware('admin')

        $orders = $this->orderModel->getAll(); // Add getAll() method to Order model if needed

        Response::success($orders, 'All orders retrieved');
    }

    /**
     * Update order status (PATCH/POST /admin/orders/{id}/status)
     *
     * @param int $orderId Order ID
     * @return never
     */
    public function updateStatus(int $orderId): never
    {
        if (!$this->request->isMethod('PATCH') && !$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'status' => 'required|in:pending,preparing,delivering,completed,cancelled'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Invalid status');
        }

        $status = $data['status'];

        $success = $this->orderModel->updateStatus($orderId, $status);

        if ($success) {
            Response::success(['message' => "Order status updated to $status"]);
        }

        Response::error('Failed to update order status', 500);
    }

    /**
     * Delete order (DELETE /admin/orders/{id}) - admin only
     *
     * @param int $orderId Order ID
     * @return never
     */
    public function deleteOrder(int $orderId): never
    {
        $order = $this->orderModel->getById($orderId);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $success = $this->orderModel->delete($orderId);

        if ($success) {
            Response::success(['message' => 'Order deleted successfully']);
        }

        Response::error('Failed to delete order', 500);
    }

    /*
     * Typical routing usage in routes/orders.php or index.php:
     *
     * $orderCtrl = new OrderController($request);
     *
     * // User-protected routes (after AuthMiddleware)
     * $router->post('/orders',           [$orderCtrl, 'createOrder']);
     * $router->get('/orders/{id}',       fn($id) => $orderCtrl->getOrder((int)$id));
     * $router->get('/orders',            [$orderCtrl, 'getOrdersByUser']);
     *
     * // Admin-only routes (after AuthMiddleware + RoleMiddleware('admin'))
     * $router->get('/admin/orders',      [$orderCtrl, 'getAllOrders']);
     * $router->patch('/admin/orders/{id}/status', fn($id) => $orderCtrl->updateStatus((int)$id));
     * $router->delete('/admin/orders/{id}', fn($id) => $orderCtrl->deleteOrder((int)$id));
     */
}