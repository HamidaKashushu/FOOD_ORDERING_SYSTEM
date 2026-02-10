<?php
/**
 * Food Ordering System - ReportController
 * Provides admin-only reporting endpoints for sales, orders,
 * popular products, user activity, and revenue summaries.
 *
 * All methods require admin role (protected by RoleMiddleware).
 * Date parameters expected in 'YYYY-MM-DD' format.
 *
 * @package FoodOrderingSystem
 * @subpackage Controllers
 */
declare(strict_types=1);

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/OrderItem.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../core/Request.php';
require_once __DIR__ . '/../core/Response.php';

class ReportController
{
    private Order $orderModel;
    private OrderItem $orderItemModel;
    private Product $productModel;
    private User $userModel;
    private Payment $paymentModel;
    private Request $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request ?? new Request();
        $this->orderModel      = new Order();
        $this->orderItemModel  = new OrderItem();
        $this->productModel    = new Product();
        $this->userModel       = new User();
        $this->paymentModel    = new Payment();
    }

    /**
     * Generate sales summary report for date range
     * GET /admin/reports/sales?start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * @return never
     */
    public function salesReport(): never
    {
        $start = $this->request->query('start', '');
        $end   = $this->request->query('end', date('Y-m-d'));

        if (!$this->validateDateRange($start, $end)) {
            Response::error('Invalid date range. Use YYYY-MM-DD format.', 400);
        }

        $stats = $this->getSalesStats($start, $end);

        Response::success($stats, 'Sales report generated');
    }

    /**
     * List orders in date range with basic details
     * GET /admin/reports/orders?start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * @return never
     */
    public function ordersReport(): never
    {
        $start = $this->request->query('start', '');
        $end   = $this->request->query('end', date('Y-m-d'));

        if (!$this->validateDateRange($start, $end)) {
            Response::error('Invalid date range. Use YYYY-MM-DD format.', 400);
        }

        $orders = $this->orderModel->getOrdersInRange($start, $end);

        Response::success($orders, 'Orders report generated');
    }

    /**
     * Top selling products report
     * GET /admin/reports/popular-products?start=YYYY-MM-DD&end=YYYY-MM-DD&limit=10
     *
     * @return never
     */
    public function popularProductsReport(): never
    {
        $start = $this->request->query('start', '');
        $end   = $this->request->query('end', date('Y-m-d'));
        $limit = (int)$this->request->query('limit', 10);

        if (!$this->validateDateRange($start, $end)) {
            Response::error('Invalid date range. Use YYYY-MM-DD format.', 400);
        }

        if ($limit < 1 || $limit > 50) {
            $limit = 10;
        }

        $topProducts = $this->getTopProducts($start, $end, $limit);

        Response::success($topProducts, 'Popular products report generated');
    }

    /**
     * User activity report (orders + payments)
     * GET /admin/reports/user-activity/{userId}?start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * @param int $userId
     * @return never
     */
    public function userActivityReport(int $userId): never
    {
        $start = $this->request->query('start', '');
        $end   = $this->request->query('end', date('Y-m-d'));

        if (!$this->validateDateRange($start, $end)) {
            Response::error('Invalid date range. Use YYYY-MM-DD format.', 400);
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            Response::notFound('User not found');
        }

        $orders   = $this->orderModel->getUserOrdersInRange($userId, $start, $end);
        $payments = $this->paymentModel->getUserPaymentsInRange($userId, $start, $end);

        Response::success([
            'user'     => ['id' => $user['id'], 'name' => $user['full_name'], 'email' => $user['email']],
            'orders'   => $orders,
            'payments' => $payments
        ], 'User activity report generated');
    }

    /**
     * Revenue summary (total revenue, completed orders, pending payments)
     * GET /admin/reports/revenue?start=YYYY-MM-DD&end=YYYY-MM-DD
     *
     * @return never
     */
    public function revenueSummary(): never
    {
        $start = $this->request->query('start', '');
        $end   = $this->request->query('end', date('Y-m-d'));

        if (!$this->validateDateRange($start, $end)) {
            Response::error('Invalid date range. Use YYYY-MM-DD format.', 400);
        }

        $summary = $this->getRevenueSummary($start, $end);

        Response::success($summary, 'Revenue summary generated');
    }

    /**
     * Dashboard: Count total users
     * GET /admin/reports/users-count
     */
    public function getUsersCount(): never
    {
        $stmt = $this->userModel->db->query("SELECT COUNT(*) as count FROM users WHERE role_id != (SELECT id FROM roles WHERE name='admin')");
        Response::success($stmt->fetch());
    }

    /**
     * Dashboard: Count total orders
     * GET /admin/reports/orders-count
     */
    public function getOrdersCount(): never
    {
        $stmt = $this->orderModel->db->query("SELECT COUNT(*) as count FROM orders");
        Response::success($stmt->fetch());
    }

    /**
     * Dashboard: Total revenue
     * GET /admin/reports/revenue-total
     */
    public function getTotalRevenue(): never
    {
        $stmt = $this->orderModel->db->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status = 'completed'");
        Response::success($stmt->fetch());
    }

    /**
     * Dashboard: Total products
     * GET /admin/reports/products-count
     */
    public function getProductsCount(): never
    {
        $stmt = $this->productModel->db->query("SELECT COUNT(*) as count FROM products");
        Response::success($stmt->fetch());
    }

    /**
     * Dashboard: Orders trend (last 7 days, for example)
     * GET /admin/reports/orders-trend
     */
    public function getOrdersTrend(): never
    {
        // Get last 7 days stats
        $stmt = $this->orderModel->db->query("
            SELECT DATE(created_at) as date, COUNT(*) as count 
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $rows = $stmt->fetchAll();
        
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $row['date'];
            $values[] = (int)$row['count'];
        }

        Response::success(['labels' => $labels, 'values' => $values]);
    }

    /**
     * Dashboard: Revenue trend (last 7 days)
     * GET /admin/reports/revenue-trend
     */
    public function getRevenueTrend(): never
    {
        $stmt = $this->orderModel->db->query("
            SELECT DATE(created_at) as date, SUM(total_amount) as total
            FROM orders 
            WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $rows = $stmt->fetchAll();
        
        $labels = [];
        $values = [];
        foreach ($rows as $row) {
            $labels[] = $row['date'];
            $values[] = (float)$row['total'];
        }

        Response::success(['labels' => $labels, 'values' => $values]);
    }

    private function validateDateRange(string $start, string $end): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return false;
        }

        $startDate = strtotime($start);
        $endDate   = strtotime($end);

        return $startDate !== false && $endDate !== false && $startDate <= $endDate;
    }

    private function getSalesStats(string $start, string $end): array
    {
        $stmt = $this->orderModel->db->prepare("
            SELECT 
                COUNT(*) AS total_orders,
                COALESCE(SUM(total_amount), 0) AS total_revenue,
                COALESCE(AVG(total_amount), 0) AS avg_order_value
            FROM orders
            WHERE DATE(created_at) BETWEEN :start AND :end
              AND status = 'completed'
        ");

        $stmt->execute([':start' => $start, ':end' => $end]);
        return $stmt->fetch() ?: ['total_orders' => 0, 'total_revenue' => 0.0, 'avg_order_value' => 0.0];
    }

    private function getTopProducts(string $start, string $end, int $limit): array
    {
        $stmt = $this->orderItemModel->db->prepare("
            SELECT 
                p.id, p.name, p.price,
                SUM(oi.quantity) AS total_quantity,
                SUM(oi.subtotal) AS total_revenue
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            WHERE DATE(o.created_at) BETWEEN :start AND :end
              AND o.status = 'completed'
            GROUP BY p.id, p.name, p.price
            ORDER BY total_quantity DESC
            LIMIT :limit
        ");

        $stmt->bindValue(':start', $start, PDO::PARAM_STR);
        $stmt->bindValue(':end',   $end,   PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function getRevenueSummary(string $start, string $end): array
    {
        $stmt = $this->orderModel->db->prepare("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS total_revenue,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_orders,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_orders
            FROM orders
            WHERE DATE(created_at) BETWEEN :start AND :end
        ");

        $stmt->execute([':start' => $start, ':end' => $end]);
        $orderStats = $stmt->fetch();

        $pendingStmt = $this->paymentModel->db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS pending_payments
            FROM payments
            WHERE status = 'pending'
              AND DATE(created_at) BETWEEN :start AND :end
        ");

        $pendingStmt->execute([':start' => $start, ':end' => $end]);
        $pending = $pendingStmt->fetch();

        return array_merge($orderStats, $pending);
    }

    /*
     * Typical routing usage (in routes/reports.php or index.php):
     *
     * $reportCtrl = new ReportController($request);
     *
     * // All routes protected by AuthMiddleware + RoleMiddleware('admin')
     * $router->get('/admin/reports/sales',           [$reportCtrl, 'salesReport']);
     * $router->get('/admin/reports/orders',          [$reportCtrl, 'ordersReport']);
     * $router->get('/admin/reports/popular-products',[$reportCtrl, 'popularProductsReport']);
     * $router->get('/admin/reports/user-activity/{id}', fn($id) => $reportCtrl->userActivityReport((int)$id));
     * $router->get('/admin/reports/revenue',         [$reportCtrl, 'revenueSummary']);
     */
}