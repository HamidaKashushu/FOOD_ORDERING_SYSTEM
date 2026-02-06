<?php
/**
 * Food Ordering System - Payment Model
 * Handles payment record creation, retrieval, status updates,
 * and transaction ID generation for orders.
 *
 * Associates payments with orders and users.
 * Supports multiple payment methods and status tracking.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Payment
{
    public PDO $db;

    /**
     * Constructor - injects or obtains PDO connection
     *
     * @param PDO|null $pdo Optional PDO instance (falls back to Database singleton)
     */
    public function __construct(?PDO $pdo = null)
    {
        $this->db = $pdo ?? Database::getInstance()->getConnection();
    }

    /**
     * Create a new payment record
     *
     * @param array $data Required: order_id, user_id, amount, payment_method
     *                    Optional: status (default 'pending'), transaction_id
     * @return bool Success status
     */
    public function create(array $data): bool
    {
        $required = ['order_id', 'user_id', 'amount', 'payment_method'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return false;
            }
        }

        $transactionId = $data['transaction_id'] ?? $this->generateTransactionId();
        $status = $data['status'] ?? 'pending';

        $stmt = $this->db->prepare("
            INSERT INTO payments (
                order_id, user_id, amount, method, status, transaction_ref
            ) VALUES (
                :order_id, :user_id, :amount, :method, :status, :transaction_ref
            )
        ");

        return $stmt->execute([
            ':order_id'        => (int)$data['order_id'],
            ':user_id'         => (int)$data['user_id'],
            ':amount'          => (float)$data['amount'],
            ':method'          => $data['payment_method'],
            ':status'          => $status,
            ':transaction_ref' => $transactionId
        ]);
    }

    /**
     * Get payment record by ID
     *
     * @param int $id Payment ID
     * @return array|null Payment data or null if not found
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, o.order_number, u.full_name, u.email
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN users u ON p.user_id = u.id
            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    /**
     * Get payment record associated with a specific order
     *
     * @param int $orderId Order ID
     * @return array|null Payment data or null if not found
     */
    public function getByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, o.order_number, u.full_name
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN users u ON p.user_id = u.id
            WHERE p.order_id = :order_id
            LIMIT 1
        ");

        $stmt->execute([':order_id' => $orderId]);
        $payment = $stmt->fetch();

        return $payment ?: null;
    }

    /**
     * Get all payments made by a specific user
     *
     * @param int $userId User ID
     * @return array List of payments (latest first)
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.order_id, p.amount, p.method, p.status,
                   p.transaction_ref, p.paid_at, p.created_at,
                   o.order_number, o.total_amount
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE p.user_id = :user_id
            ORDER BY p.created_at DESC
        ");

        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Update payment status (e.g. pending → completed/failed)
     *
     * @param int    $id     Payment ID
     * @param string $status New status: pending, paid, failed
     * @return bool Success status
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['pending', 'paid', 'failed'];
        if (!in_array($status, $allowed)) {
            return false;
        }

        $paidAt = ($status === 'paid') ? 'NOW()' : 'NULL';

        $stmt = $this->db->prepare("
            UPDATE payments
            SET status = :status,
                paid_at = $paidAt,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id'     => $id
        ]);
    }

    /**
     * Generate unique transaction reference/ID
     * Format: TX + YYYYMMDD + 4 random uppercase chars
     *
     * @return string e.g. TX20260205ABCD
     */
    public function generateTransactionId(): string
    {
        $date = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $txId = "TX{$date}{$random}";

        // Check for collision (extremely rare)
        $stmt = $this->db->prepare("SELECT 1 FROM payments WHERE transaction_ref = :tx LIMIT 1");
        $stmt->execute([':tx' => $txId]);

        if ($stmt->fetch()) {
            return $this->generateTransactionId();
        }

        return $txId;
    }

    function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, o.order_number, u.full_name, u.email
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    function getUserPaymentsInRange(int $userId, string $start, string $end): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, o.order_number
            FROM payments p
            JOIN orders o ON p.order_id = o.id
            WHERE p.user_id = :user_id
              AND DATE(p.created_at) BETWEEN :start AND :end
            ORDER BY p.created_at DESC
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':start'   => $start,
            ':end'     => $end
        ]);

        return $stmt->fetchAll();
    }

    /*
     * Typical usage in PaymentController or checkout flow:
     *
     * $paymentModel = new Payment();
     *
     * // After order creation (POST /payments)
     * $paymentModel->create([
     *     'order_id'        => $newOrderId,
     *     'user_id'         => $userId,
     *     'amount'          => $totalAmount,
     *     'payment_method'  => $request->body('payment_method'),
     *     'status'          => 'pending',
     *     'transaction_id'  => $paymentModel->generateTransactionId()
     * ]);
     *
     * // Webhook or callback - mark as paid
     * $paymentModel->updateStatus($paymentId, 'paid');
     *
     * // User payment history (GET /payments)
     * $payments = $paymentModel->getByUserId($userId);
     * Response::success($payments);
     */
}