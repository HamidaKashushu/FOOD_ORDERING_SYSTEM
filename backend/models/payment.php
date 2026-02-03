<?php

// backend/models/Payment.php

class Payment {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new payment record for an order
     *
     * @param int $order_id
     * @param string $payment_method
     * @param string $payment_status Default: 'pending'
     * @return int|null The new payment_id or null on failure
     */
    public function createPayment(int $order_id, string $payment_method, string $payment_status = 'pending'): ?int {
        if ($order_id <= 0 || empty(trim($payment_method))) {
            return null;
        }

        $allowedStatuses = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($payment_status, $allowedStatuses)) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO payments 
                (order_id, payment_method, payment_status, paid_at)
                VALUES (?, ?, ?, ?)
            ");

            $paid_at = ($payment_status === 'paid') ? date('Y-m-d H:i:s') : null;

            $success = $stmt->execute([
                $order_id,
                trim($payment_method),
                $payment_status,
                $paid_at
            ]);

            return $success ? (int)$this->conn->lastInsertId() : null;
        } catch (PDOException $e) {
            error_log("Payment::createPayment failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update payment status
     * Automatically sets paid_at when status becomes 'paid'
     *
     * @param int $payment_id
     * @param string $status
     * @return bool
     */
    public function updatePaymentStatus(int $payment_id, string $status): bool {
        if ($payment_id <= 0 || empty(trim($status))) {
            return false;
        }

        $allowedStatuses = ['pending', 'paid', 'failed', 'refunded'];
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }

        try {
            $paid_at = ($status === 'paid') ? date('Y-m-d H:i:s') : null;

            $stmt = $this->conn->prepare("
                UPDATE payments 
                SET payment_status = ?,
                    paid_at = ?
                WHERE payment_id = ?
            ");

            $stmt->execute([$status, $paid_at, $payment_id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Payment::updatePaymentStatus failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get payment details for a specific order
     *
     * @param int $order_id
     * @return array|null
     */
    public function getPaymentByOrder(int $order_id): ?array {
        if ($order_id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    payment_id,
                    order_id,
                    payment_method,
                    payment_status,
                    paid_at,
                    created_at
                FROM payments
                WHERE order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            return $payment ?: null;
        } catch (PDOException $e) {
            error_log("Payment::getPaymentByOrder error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all payments (admin view) with basic order and user info
     *
     * @return array
     */
    public function getAllPayments(): array {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    p.payment_id,
                    p.order_id,
                    p.payment_method,
                    p.payment_status,
                    p.paid_at,
                    p.created_at,
                    o.user_id,
                    u.full_name AS user_name,
                    o.total_amount
                FROM payments p
                JOIN orders o ON p.order_id = o.order_id
                JOIN users u ON o.user_id = u.id
                ORDER BY p.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Payment::getAllPayments error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Optional: Get payment by its own ID
     *
     * @param int $payment_id
     * @return array|null
     */
    public function getById(int $payment_id): ?array {
        if ($payment_id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    payment_id,
                    order_id,
                    payment_method,
                    payment_status,
                    paid_at,
                    created_at
                FROM payments
                WHERE payment_id = ?
                LIMIT 1
            ");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            return $payment ?: null;
        } catch (PDOException $e) {
            error_log("Payment::getById error: " . $e->getMessage());
            return null;
        }
    }
}