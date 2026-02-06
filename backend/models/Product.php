<?php
/**
 * Food Ordering System - Product Model
 * Handles all database operations for food products:
 * CRUD, category filtering, search, status management, and image handling.
 *
 * Used by ProductController and menu/cart endpoints.
 * All database interactions use prepared statements.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Product
{
    private PDO $db;

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
     * Get all products with category information
     *
     * @return array List of products including category name
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.description, p.price, p.category_id, c.name AS category_name,
                   p.image, p.status, p.stock, p.created_at, p.updated_at
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.name ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find product by ID with category name
     *
     * @param int $id Product ID
     * @return array|null Product data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();

        return $product ?: null;
    }

    /**
     * Create a new product
     *
     * @param array $data Associative array with required: name, price, category_id
     *                    Optional: description, image, status, stock
     * @return bool Success status
     */
    public function create(array $data): bool
    {
        $required = ['name', 'price', 'category_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                return false;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO products (
                category_id, name, description, price, image, stock, status
            ) VALUES (
                :category_id, :name, :description, :price, :image, :stock, :status
            )
        ");

        return $stmt->execute([
            ':category_id'  => (int)$data['category_id'],
            ':name'         => trim($data['name']),
            ':description'  => $data['description'] ?? null,
            ':price'        => (float)$data['price'],
            ':image'        => $data['image'] ?? null,
            ':stock'        => (int)($data['stock'] ?? 0),
            ':status'       => $data['status'] ?? 'available'
        ]);
    }

    /**
     * Update existing product
     *
     * @param int   $id   Product ID
     * @param array $data Fields to update (name, description, price, category_id, image, status, stock)
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = [':id' => $id];

        if (isset($data['name']) && trim($data['name']) !== '') {
            $updates[] = 'name = :name';
            $params[':name'] = trim($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $updates[] = 'description = :description';
            $params[':description'] = $data['description'];
        }

        if (isset($data['price']) && is_numeric($data['price'])) {
            $updates[] = 'price = :price';
            $params[':price'] = (float)$data['price'];
        }

        if (isset($data['category_id']) && is_numeric($data['category_id'])) {
            $updates[] = 'category_id = :category_id';
            $params[':category_id'] = (int)$data['category_id'];
        }

        if (array_key_exists('image', $data)) {
            $updates[] = 'image = :image';
            $params[':image'] = $data['image'];
        }

        if (isset($data['stock']) && is_numeric($data['stock'])) {
            $updates[] = 'stock = :stock';
            $params[':stock'] = (int)$data['stock'];
        }

        if (isset($data['status']) && in_array($data['status'], ['available', 'unavailable'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE products SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete a product
     *
     * @param int $id Product ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Search products by keyword in name or description
     *
     * @param string $keyword Search term
     * @return array Matching products with category name
     */
    public function search(string $keyword): array
    {
        $keyword = '%' . trim($keyword) . '%';

        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.name LIKE :keyword
               OR p.description LIKE :keyword
            ORDER BY p.name ASC
        ");

        $stmt->execute([':keyword' => $keyword]);
        return $stmt->fetchAll();
    }

    /**
     * Get all products belonging to a specific category
     *
     * @param int $categoryId Category ID
     * @return array Products in the category
     */
    public function getByCategory(int $categoryId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, c.name AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.category_id = :category_id
              AND p.status = 'available'
              AND c.status = 'active'
            ORDER BY p.name ASC
        ");

        $stmt->execute([':category_id' => $categoryId]);
        return $stmt->fetchAll();
    }

    /**
     * Change product availability status
     *
     * @param int    $id     Product ID
     * @param string $status 'available' or 'unavailable'
     * @return bool Success status
     */
    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['available', 'unavailable'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE products
            SET status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':status' => $status,
            ':id'     => $id
        ]);
    }

    /*
     * Typical usage examples:
     *
     * $productModel = new Product();
     *
     * // Frontend menu - get all available products
     * $products = $productModel->getAll();
     * $available = array_filter($products, fn($p) => $p['status'] === 'available');
     *
     * // Category page
     * $pizzaProducts = $productModel->getByCategory(1);
     *
     * // Admin - create new product
     * $productModel->create([
     *     'name'        => 'Margherita Pizza',
     *     'description' => 'Classic tomato and mozzarella',
     *     'price'       => 9.99,
     *     'category_id' => 1,
     *     'image'       => '/uploads/products/margherita.jpg',
     *     'stock'       => 50,
     *     'status'      => 'available'
     * ]);
     *
     * // Search functionality
     * $results = $productModel->search($request->query('q') ?? '');
     */
}