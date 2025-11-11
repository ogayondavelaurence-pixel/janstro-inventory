<?php

namespace Janstro\InventorySystem\Config;

use PDO;
use PDOException;

/**
 * Database Connection Manager
 * Singleton pattern for database connections
 * ISO/IEC 25010:2023 - Performance Efficiency & Reliability
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    /**
     * Get database connection
     * @return PDO Database connection instance
     * @throws \RuntimeException If connection fails
     */
    public static function connect(): PDO
    {
        if (self::$connection === null) {
            self::loadConfig();
            self::createConnection();
        }

        return self::$connection;
    }

    /**
     * Load database configuration from environment
     */
    private static function loadConfig(): void
    {
        // Load .env if not already loaded
        if (!isset($_ENV['DB_HOST'])) {
            $dotenvPath = __DIR__ . '/../../.env';
            if (file_exists($dotenvPath)) {
                try {
                    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
                    $dotenv->load();
                } catch (\Exception $e) {
                    error_log("Failed to load .env file: " . $e->getMessage());
                }
            }
        }

        self::$config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'database' => $_ENV['DB_DATABASE'] ?? 'janstro_inventory',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * Create PDO connection
     * @throws \RuntimeException If connection fails
     */
    private static function createConnection(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                self::$config['host'],
                self::$config['database'],
                self::$config['charset']
            );

            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . self::$config['charset'] . " COLLATE " . self::$config['collation']
                ]
            );

            // Test connection
            self::$connection->query("SELECT 1");
        } catch (PDOException $e) {
            // Log error securely (don't expose details to users)
            error_log("Database Connection Error: " . $e->getMessage());

            // Throw generic error
            throw new \RuntimeException(
                "Database connection failed. Please contact administrator.",
                500,
                $e
            );
        }
    }

    /**
     * Close database connection
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * Get connection status
     * @return bool True if connected
     */
    public static function isConnected(): bool
    {
        try {
            if (self::$connection === null) {
                return false;
            }

            self::$connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::connect()->rollBack();
    }

    /**
     * Get database configuration (without password)
     * @return array Configuration array
     */
    public static function getConfig(): array
    {
        self::loadConfig();
        return [
            'host' => self::$config['host'],
            'database' => self::$config['database'],
            'username' => self::$config['username'],
            'charset' => self::$config['charset'],
        ];
    }
}
