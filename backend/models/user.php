<?php
/**
 * Food Ordering System - User Model
 * Handles all database operations for users: registration, authentication,
 * profile management, role checks, and listing.
 *
 * Uses PDO with prepared statements for security.
 * Integrates with password hashing/verification utilities.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/password.php';

class User
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
     * Register a new user
     *
     * @param array $data Associative array with: full_name, email, phone, password, role_id (optional)
     * @return bool Success status
     */
    public function register(array $data): bool
    {
        $required = ['full_name', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // Check email uniqueness
        if ($this->findByEmail($data['email']) !== null) {
            return false; // Email already exists
        }

        $hashedPassword = hashPassword($data['password']);

        $stmt = $this->db->prepare("
            INSERT INTO users (full_name, email, phone, password_hash, role_id, status)
            VALUES (:full_name, :email, :phone, :password_hash, :role_id, 'active')
        ");

        return $stmt->execute([
            ':full_name'     => $data['full_name'],
            ':email'         => $data['email'],
            ':phone'         => $data['phone'] ?? null,
            ':password_hash' => $hashedPassword,
            ':role_id'       => $data['role_id'] ?? 2 // 2 = customer by default
        ]);
    }

    /**
     * Authenticate user and return user data (without password_hash)
     *
     * @param string $email
     * @param string $password
     * @return array|false User data array or false on failure
     */
    public function login(string $email, string $password): array|false
    {
        $user = $this->findByEmail($email);
        if (!$user) {
            return false;
        }

        if (!verifyPassword($password, $user['password_hash'])) {
            return false;
        }

        // Remove sensitive data before returning
        unset($user['password_hash']);

        return $user;
    }

    /**
     * Find user by ID
     *
     * @param int $id
     * @return array|null User data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.full_name, u.email, u.phone, u.role_id, r.name AS role_name, u.status,
                   u.created_at, u.updated_at
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Find user by email
     *
     * @param string $email
     * @return array|null User data (including password_hash) or null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            WHERE u.email = :email
            LIMIT 1
        ");

        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Update user profile
     *
     * @param int   $id   User ID
     * @param array $data Fields to update (full_name, phone, password, role_id, status)
     * @return bool Success
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = [':id' => $id];

        if (isset($data['full_name'])) {
            $updates[] = 'full_name = :full_name';
            $params[':full_name'] = $data['full_name'];
        }

        if (isset($data['phone'])) {
            $updates[] = 'phone = :phone';
            $params[':phone'] = $data['phone'];
        }

        if (!empty($data['password'])) {
            $updates[] = 'password_hash = :password_hash';
            $params[':password_hash'] = hashPassword($data['password']);
        }

        if (isset($data['role_id'])) {
            $updates[] = 'role_id = :role_id';
            $params[':role_id'] = (int)$data['role_id'];
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'blocked'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($updates)) {
            return false; // Nothing to update
        }

        $query = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete a user
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all users with role names
     *
     * @return array List of users
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.full_name, u.email, u.phone, u.role_id, r.name AS role_name,
                   u.status, u.created_at, u.updated_at
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ");

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Check if user has specific role name
     *
     * @param int    $id       User ID
     * @param string $roleName Role name (e.g. 'admin', 'customer')
     * @return bool
     */
    public function checkRole(int $id, string $roleName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id AND r.name = :role_name
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $id,
            ':role_name' => $roleName
        ]);

        return $stmt->fetch() !== false;
    }

    /*
     * Typical usage in controllers:
     *
     * // Registration
     * $userModel = new User();
     * if ($userModel->register([
     *     'full_name' => $request->body('full_name'),
     *     'email'     => $request->body('email'),
     *     'password'  => $request->body('password'),
     *     'phone'     => $request->body('phone')
     * ])) {
     *     Response::created(['message' => 'User registered successfully']);
     * } else {
     *     Response::error('Registration failed. Email may already exist.', 400);
     * }
     *
     * // Login
     * $user = $userModel->login($email, $password);
     * if ($user) {
     *     $token = generateToken([
     *         'sub'  => $user['id'],
     *         'role' => $user['role_name']
     *     ]);
     *     Response::success(['token' => $token, 'user' => $user]);
     * }
     */
}