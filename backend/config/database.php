<?php
/**
 * Food Ordering System - Database Configuration
 * Centralized PDO database connection using Singleton pattern
 *
 * @package FoodOrderingSystem
 * @subpackage Config
 */

class Database
{
    /**
     * @var Database|null Singleton instance
     */
    private static ?Database $instance = null;

    /**
     * @var PDO The PDO database connection
     */
    private ?PDO $connection = null;

    // Database configuration constants
    private const DB_HOST    = 'localhost';
    private const DB_NAME    = 'food_ordering_system';
    private const DB_USER    = 'root';
    private const DB_PASS    = '';
    private const DB_CHARSET = 'utf8mb4';

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->connect();
    }

    /**
     * Prevent cloning of the instance (Singleton)
     */
    private function __clone()
    {
    }

    /**
     * Prevent unserializing of the instance (Singleton)
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Get the single instance of Database
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Establish the PDO database connection
     *
     * @return void
     * @throws Exception When connection fails
     */
    private function connect(): void
    {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            self::DB_HOST,
            self::DB_NAME,
            self::DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+03:00'", // EAT timezone
        ];

        try {
            $this->connection = new PDO(
                $dsn,
                self::DB_USER,
                self::DB_PASS,
                $options
            );
        } catch (PDOException $e) {
            // Log detailed error privately
            error_log("Database connection failed: " . $e->getMessage());

            // Never expose detailed error to client in production
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    /**
     * Get the PDO connection instance
     *
     * @return PDO
     * @throws Exception If connection is not established
     */
    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            throw new Exception("Database connection not initialized");
        }

        return $this->connection;
    }
}

/*
 * Usage example:
 *
 * require_once __DIR__ . '/database.php';
 *
 * try {
 *     $db = Database::getInstance()->getConnection();
 *
 *     // Example query
 *     $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
 *     $stmt->execute([1]);
 *     $user = $stmt->fetch();
 *
 * } catch (Exception $e) {
 *     // Handle error gracefully
 *     http_response_code(500);
 *     echo json_encode(['error' => 'Internal server error']);
 *     exit;
 * }
 */