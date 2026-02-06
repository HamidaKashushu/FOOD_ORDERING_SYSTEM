<?php
/**
 * Food Ordering System - PaymentController
 * Handles payment creation, retrieval, history, status updates,
 * and admin payment overview.
 *
 * User endpoints: createPayment, getPayment, getPaymentsByUser, getPaymentsByOrder
 * Admin endpoints: getAllPayments, updateStatus
 *
 * All endpoints require authentication.
 * Admin actions should be protected with RoleMiddleware('admin')
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/sanitizer.php';
require_once __DIR__ . '/../utils/validator.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class PaymentController
{
    private Payment $paymentModel;
    private Order $orderModel;
    private Request $request;

    /**
     * Constructor - initializes models and request
     *
     * @param Request|null $request Optional Request instance
     */
    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->paymentModel = new Payment();
        $this->orderModel = new Order();
    }

    /**
     * Create new payment record (POST /payments)
     *
     * @return never
     */
    public function createPayment(): never
    {
        if (!$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'order_id'        => 'required|numeric',
            'amount'          => 'required|numeric|min:0.01',
            'payment_method'  => 'required|in:cash,card,mobile_money',
            'status'          => 'optional|in:pending,paid,failed'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Payment creation failed');
        }

        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $orderId = (int)$data['order_id'];
        $order = $this->orderModel->getById($orderId);

        if (!$order) {
            Response::notFound('Order not found');
        }

        // Ensure user owns the order (unless admin)
        $isAdmin = $this->request->user['role'] === 'admin';
        if (!$isAdmin && $order['user_id'] !== $userId) {
            Response::forbidden('You do not own this order');
        }

        // Validate amount matches order total (basic check)
        if (abs((float)$data['amount'] - $order['total_amount']) > 0.01) {
            Response::error('Payment amount does not match order total', 400);
        }

        $cleanData = [
            'order_id'        => $orderId,
            'user_id'         => $userId,
            'amount'          => (float)$data['amount'],
            'payment_method'  => $data['payment_method'],
            'status'          => $data['status'] ?? 'pending'
        ];

        $success = $this->paymentModel->create($cleanData);

        if ($success) {
            // In real app: return newly created payment record
            Response::created(['message' => 'Payment recorded successfully']);
        }

        Response::error('Failed to record payment', 500);
    }

    /**
     * Get single payment details (GET /payments/{id})
     *
     * @param int $paymentId Payment ID
     * @return never
     */
    public function getPayment(int $paymentId): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $payment = $this->paymentModel->getById($paymentId);

        if (!$payment) {
            Response::notFound('Payment not found');
        }

        // Allow user to see their own payments or admin to see all
        $isAdmin = $this->request->user['role'] === 'admin';
        if (!$isAdmin && $payment['user_id'] !== $userId) {
            Response::forbidden('You do not have permission to view this payment');
        }

        Response::success($payment, 'Payment details retrieved');
    }

    /**
     * Get all payments made by authenticated user (GET /payments)
     *
     * @return never
     */
    public function getPaymentsByUser(): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $payments = $this->paymentModel->getByUserId($userId);

        Response::success($payments, 'Your payment history retrieved');
    }

    /**
     * Get payment associated with a specific order (GET /orders/{orderId}/payment)
     *
     * @param int $orderId Order ID
     * @return never
     */
    public function getPaymentsByOrder(int $orderId): never
    {
        $userId = $this->request->user['id'] ?? 0;
        if ($userId <= 0) {
            Response::unauthorized('Authentication required');
        }

        $order = $this->orderModel->getById($orderId);
        if (!$order) {
            Response::notFound('Order not found');
        }

        $isAdmin = $this->request->user['role'] === 'admin';
        if (!$isAdmin && $order['user_id'] !== $userId) {
            Response::forbidden('You do not have permission to view this order\'s payment');
        }

        $payment = $this->paymentModel->getByOrderId($orderId);

        if (!$payment) {
            Response::notFound('No payment found for this order');
        }

        Response::success($payment, 'Payment details retrieved');
    }

    /**
     * Update payment status (PATCH/POST /admin/payments/{id}/status) - admin only
     *
     * @param int $paymentId Payment ID
     * @return never
     */
    public function updateStatus(int $paymentId): never
    {
        if (!$this->request->isMethod('PATCH') && !$this->request->isMethod('POST')) {
            Response::error('Method not allowed', 405);
        }

        $data = $this->request->all();

        $errors = validate($data, [
            'status' => 'required|in:pending,paid,failed'
        ]);

        if (!empty($errors)) {
            Response::validation($errors, 'Invalid payment status');
        }

        $status = $data['status'];

        $success = $this->paymentModel->updateStatus($paymentId, $status);

        if ($success) {
            // Optional: if status = 'paid', could trigger further actions (e.g. reduce stock)
            Response::success(['message' => "Payment status updated to $status"]);
        }

        Response::error('Failed to update payment status', 500);
    }

    /**
     * Get all payments (admin only) (GET /admin/payments)
     *
     * @return never
     */
    public function getAllPayments(): never
    {
        // This route should be protected by RoleMiddleware('admin')

        $payments = $this->paymentModel->getAll(); // Add getAll() to Payment model if needed

        Response::success($payments, 'All payments retrieved');
    }

    /*
     * Typical routing usage in routes/payments.php or index.php:
     *
     * $paymentCtrl = new PaymentController($request);
     *
     * // User-protected routes (after AuthMiddleware)
     * $router->post('/payments',              [$paymentCtrl, 'createPayment']);
     * $router->get('/payments/{id}',          fn($id) => $paymentCtrl->getPayment((int)$id));
     * $router->get('/payments',               [$paymentCtrl, 'getPaymentsByUser']);
     * $router->get('/orders/{id}/payment',    fn($id) => $paymentCtrl->getPaymentsByOrder((int)$id));
     *
     * // Admin-only routes (after AuthMiddleware + RoleMiddleware('admin'))
     * $router->get('/admin/payments',         [$paymentCtrl, 'getAllPayments']);
     * $router->patch('/admin/payments/{id}/status', fn($id) => $paymentCtrl->updateStatus((int)$id));
     */
}