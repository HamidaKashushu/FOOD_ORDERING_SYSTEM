<?php
/**
 * Food Ordering System - Role Model
 * Handles all database operations related to user roles:
 * listing, creation, modification, deletion and user-role assignment.
 *
 * Works together with User model and RoleMiddleware.
 * Uses prepared statements for all database interactions.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Role
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
     * Get all available roles
     *
     * @return array List of roles (id, name, description, created_at)
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, created_at
            FROM roles
            ORDER BY id ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Find role by primary key (ID)
     *
     * @param int $id Role ID
     * @return array|null Role data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, created_at
            FROM roles
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();

        return $role ?: null;
    }

    /**
     * Find role by unique name
     *
     * @param string $name Role name (e.g. 'admin', 'customer')
     * @return array|null Role data or null if not found
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, created_at
            FROM roles
            WHERE name = :name
            LIMIT 1
        ");

        $stmt->execute([':name' => $name]);
        $role = $stmt->fetch();

        return $role ?: null;
    }

    /**
     * Create a new role
     *
     * @param array $data Associative array: name (required), description (optional)
     * @return bool Success status
     */
    public function create(array $data): bool
    {
        if (empty($data['name'])) {
            return false;
        }

        // Prevent duplicate role names
        if ($this->findByName($data['name']) !== null) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO roles (name, description)
            VALUES (:name, :description)
        ");

        return $stmt->execute([
            ':name'        => $data['name'],
            ':description' => $data['description'] ?? null
        ]);
    }

    /**
     * Update existing role
     *
     * @param int   $id   Role ID
     * @param array $data Fields to update: name, description
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = [':id' => $id];

        if (isset($data['name']) && $data['name'] !== '') {
            $updates[] = 'name = :name';
            $params[':name'] = $data['name'];

            // Check for name conflict (excluding current role)
            $existing = $this->findByName($data['name']);
            if ($existing && $existing['id'] !== $id) {
                return false;
            }
        }

        if (array_key_exists('description', $data)) {
            $updates[] = 'description = :description';
            $params[':description'] = $data['description'];
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE roles SET " . implode(', ', $updates) . " WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete a role
     * Note: Consider business logic — deleting roles may affect users
     *
     * @param int $id Role ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        // Optional: prevent deletion of default roles (admin/customer)
        $role = $this->findById($id);
        if (!$role || in_array($role['name'], ['admin', 'customer'])) {
            return false;
        }

        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Assign a role to a user (updates users.role_id)
     *
     * @param int $userId  User ID
     * @param int $roleId  Role ID
     * @return bool Success status
     */
    public function assignRoleToUser(int $userId, int $roleId): bool
    {
        // Verify role exists
        if ($this->findById($roleId) === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET role_id = :role_id,
                updated_at = NOW()
            WHERE id = :user_id
        ");

        return $stmt->execute([
            ':role_id' => $roleId,
            ':user_id' => $userId
        ]);
    }

    /**
     * Check if a user has a specific role by name
     *
     * @param int    $userId   User ID
     * @param string $roleName Role name (e.g. 'admin')
     * @return bool
     */
    public function checkUserRole(int $userId, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :user_id
              AND r.name = :role_name
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id'   => $userId,
            ':role_name' => $roleName
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /*
     * Typical usage examples:
     *
     * $roleModel = new Role();
     *
     * // Get role for JWT or middleware
     * $adminRole = $roleModel->findByName('admin');
     * if ($adminRole) {
     *     $roleModel->assignRoleToUser($newUserId, $adminRole['id']);
     * }
     *
     * // Protect admin routes
     * if (!$roleModel->checkUserRole($request->user['id'], 'admin')) {
     *     Response::forbidden('Admin access required');
     * }
     *
     * // In admin panel - list all roles
     * $roles = $roleModel->getAll();
     * Response::success($roles);
     */
}