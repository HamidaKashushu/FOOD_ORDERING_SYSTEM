<?php
/**
 * Food Ordering System - Address Model
 * Manages user delivery addresses:
 * creation, updates, deletion, retrieval by user or ID,
 * and status management (active/inactive).
 *
 * One user can have multiple addresses (home, work, etc.).
 * All database operations use prepared statements for security.
 *
 * @package FoodOrderingSystem
 * @subpackage Models
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Address
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
     * Create a new address for a user
     *
     * @param array $data Required: user_id, street, city, country
     *                    Optional: state, zip, type, status (default 'active')
     * @return bool Success status
     */
    public function create(array $data): bool
    {
        $required = ['user_id', 'street', 'city', 'country'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return false;
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO addresses (
                user_id, street, city, region, zip, country, type, status
            ) VALUES (
                :user_id, :street, :city, :region, :zip, :country, :type, :status
            )
        ");

        return $stmt->execute([
            ':user_id' => (int)$data['user_id'],
            ':street'  => trim($data['street']),
            ':city'    => trim($data['city']),
            ':region'  => trim($data['state'] ?? $data['region'] ?? ''),
            ':zip'     => trim($data['zip'] ?? ''),
            ':country' => trim($data['country']),
            ':type'    => $data['type'] ?? 'home',
            ':status'  => $data['status'] ?? 'active'
        ]);
    }

    /**
     * Update an existing address
     *
     * @param int   $id   Address ID
     * @param array $data Fields to update (street, city, region, zip, country, type, status)
     * @return bool Success status
     */
    public function update(int $id, array $data): bool
    {
        $updates = [];
        $params = [':id' => $id];

        if (isset($data['street']) && trim($data['street']) !== '') {
            $updates[] = 'street = :street';
            $params[':street'] = trim($data['street']);
        }

        if (isset($data['city']) && trim($data['city']) !== '') {
            $updates[] = 'city = :city';
            $params[':city'] = trim($data['city']);
        }

        if (array_key_exists('region', $data) || array_key_exists('state', $data)) {
            $updates[] = 'region = :region';
            $params[':region'] = trim($data['region'] ?? $data['state'] ?? '');
        }

        if (array_key_exists('zip', $data)) {
            $updates[] = 'zip = :zip';
            $params[':zip'] = trim($data['zip'] ?? '');
        }

        if (isset($data['country']) && trim($data['country']) !== '') {
            $updates[] = 'country = :country';
            $params[':country'] = trim($data['country']);
        }

        if (isset($data['type']) && in_array($data['type'], ['home', 'work', 'other'])) {
            $updates[] = 'type = :type';
            $params[':type'] = $data['type'];
        }

        if (isset($data['status']) && in_array($data['status'], ['active', 'inactive'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }

        if (empty($updates)) {
            return false;
        }

        $query = "UPDATE addresses SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Delete an address by ID
     *
     * @param int $id Address ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM addresses WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get a single address by ID
     *
     * @param int $id Address ID
     * @return array|null Address data or null if not found
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, street, city, region, zip, country, type, status, created_at
            FROM addresses
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $address = $stmt->fetch();

        return $address ?: null;
    }

    /**
     * Get all addresses belonging to a specific user
     *
     * @param int $userId User ID
     * @return array List of addresses (most recent first)
     */
    public function getByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, street, city, region, zip, country, type, status, created_at
            FROM addresses
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");

        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Change address status (active/inactive)
     *
     * @param int    $id     Address ID
     * @param string $status 'active' or 'inactive'
     * @return bool Success status
     */
    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE addresses
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
     * Typical usage in AddressController:
     *
     * $addressModel = new Address();
     *
     * // Add new delivery address (POST /addresses)
     * $addressModel->create([
     *     'user_id' => $userId,
     *     'street'  => $request->body('street'),
     *     'city'    => $request->body('city'),
     *     'region'  => $request->body('region'),
     *     'zip'     => $request->body('zip'),
     *     'country' => $request->body('country'),
     *     'type'    => $request->body('type') ?? 'home'
     * ]);
     *
     * // List user addresses (GET /addresses)
     * $addresses = $addressModel->getByUserId($userId);
     * Response::success($addresses);
     *
     * // Update address (PUT /addresses/{id})
     * $addressModel->update($addressId, $request->all());
     *
     * // Set as inactive (soft delete) (PATCH /addresses/{id}/status)
     * $addressModel->setStatus($addressId, 'inactive');
     */
}