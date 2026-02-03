<?php

// backend/models/User.php

class User {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    /**
     * Create a new user (customer by default)
     *
     * @param string $full_name
     * @param string $email
     * @param string|null $phone
     * @param string $password
     * @return int|null The newly created user ID or null on failure
     */
    public function create(string $full_name, string $email, ?string $phone, string $password): ?int {
        if (empty($full_name) || empty($email) || empty($password)) {
            return null;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO users 
                (full_name, email, phone, password_hash, role_id, created_at)
                VALUES (?, ?, ?, ?, 2, NOW())
            ");

            $success = $stmt->execute([
                trim($full_name),
                trim($email),
                $phone ? trim($phone) : null,
                $passwordHash
            ]);

            if ($success) {
                return (int) $this->conn->lastInsertId();
            }

            return null;
        } catch (PDOException $e) {
            // In production: log error, do not expose
            error_log("User creation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by email (includes password_hash - only for authentication)
     *
     * @param string $email
     * @return array|null User data or null if not found
     */
    public function findByEmail(string $email): ?array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    id, full_name, email, phone, password_hash, role_id, created_at
                FROM users 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->execute([trim($email)]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (PDOException $e) {
            error_log("findByEmail error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by ID (safe version - no password_hash)
     *
     * @param int $id
     * @return array|null
     */
    public function findById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.role_id,
                    r.role_name,
                    u.created_at
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $user ?: null;
        } catch (PDOException $e) {
            error_log("findById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all users (admin view - no passwords)
     *
     * @return array List of users
     */
    public function getAll(): array {
        try {
            $stmt = $this->conn->query("
                SELECT 
                    u.id,
                    u.full_name,
                    u.email,
                    u.phone,
                    u.role_id,
                    r.role_name,
                    u.created_at
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                ORDER BY u.full_name ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("getAll users error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete a user by ID
     *
     * @param int $id
     * @return bool Success
     */
    public function delete(int $id): bool {
        if ($id <= 0) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("User delete error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email already exists
     *
     * @param string $email
     * @return bool
     */
    public function emailExists(string $email): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([trim($email)]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("emailExists error: " . $e->getMessage());
            return false;
        }
    }
}