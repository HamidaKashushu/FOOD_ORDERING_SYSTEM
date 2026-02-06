<?php
/**
 * Food Ordering System - Category Model
 * Manages all database operations for food categories:
 * CRUD, status toggling, and keyword-based search.
 *
 * Used by CategoryController and menu/product listing endpoints.
 * All queries use prepared statements for security.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Category
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
     * Get all categories
     *
     * @return array List of categories (id, name, description, status, created_at)
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, status, created_at
            FROM categories
            ORDER BY name ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find category by ID
     *
     * @param int $id Category ID
     * @return array|null Category data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, status, created_at
            FROM categories
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    /**
     * Create a new category
     *
     * @param array $data Associative array: name (required), description (optional), status (optional)
     * @return bool Success status
     */
    public function create(array $data): bool
    {
        if (empty($data['name'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (name, description, status)
            VALUES (:name, :description, :status)
        ");

        return $stmt->execute([
            ':name'        => trim($data['name']),
            ':description' => $data['description'] ?? null,
            ':status'      => $data['status'] ?? 'active'
        ]);
    }

    /**
     * Update existing category
     *
     * @param int   $id   Category ID
     * @param array $data Fields to update: name, description, status
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

        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE categories SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete a category
     *
     * Note: Consider adding logic to check if category has products before deletion
     *
     * @param int $id Category ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM categories WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Search categories by keyword (name or description)
     *
     * @param string $keyword Search term
     * @return array Matching categories
     */
    public function search(string $keyword): array
    {
        $keyword = '%' . trim($keyword) . '%';

        $stmt = $this->db->prepare("
            SELECT id, name, description, status, created_at
            FROM categories
            WHERE name LIKE :keyword
               OR description LIKE :keyword
            ORDER BY name ASC
        ");

        $stmt->execute([':keyword' => $keyword]);
        return $stmt->fetchAll();
    }

    /**
     * Change category status (active/inactive)
     *
     * @param int    $id     Category ID
     * @param string $status 'active' or 'inactive'
     * @return bool Success status
     */
    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE categories
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
     * Typical usage in controllers:
     *
     * $categoryModel = new Category();
     *
     * // Menu page - list all active categories
     * $categories = array_filter($categoryModel->getAll(), fn($c) => $c['status'] === 'active');
     * Response::success($categories);
     *
     * // Admin - create category
     * if ($categoryModel->create([
     *     'name' => $request->body('name'),
     *     'description' => $request->body('description')
     * ])) {
     *     Response::created(['message' => 'Category created successfully']);
     * }
     *
     * // Admin - search categories
     * $results = $categoryModel->search($request->query('q') ?? '');
     *
     * // Toggle status
     * $categoryModel->setStatus($id, 'inactive');
     */
}