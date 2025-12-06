<?php

namespace Janstro\InventorySystem\Config;

use PDO;
use PDOException;

/**
 * Database Connection Manager - FIXED v2.0
 * Handles all database connections with proper error handling
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    /**
     * Get database connection
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
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Skip comments
                    if (strpos(trim($line), '#') === 0) {
                        continue;
                    }

                    // Parse KEY=VALUE
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);

                        // Remove quotes
                        $value = trim($value, '"\'');

                        // Stop at inline comment
                        if (strpos($value, '#') !== false) {
                            $value = trim(substr($value, 0, strpos($value, '#')));
                        }

                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }

        self::$config = [
            'host' => trim($_ENV['DB_HOST'] ?? 'localhost'),
            'database' => trim($_ENV['DB_DATABASE'] ?? 'janstro_inventory'),
            'username' => trim($_ENV['DB_USERNAME'] ?? 'root'),
            'password' => trim($_ENV['DB_PASSWORD'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * Create PDO connection
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

                    /**
                     * PATCHED:
                     * Enable persistent connection to reuse the same PDO link,
                     * reducing overhead and stabilizing high-request workflows.
                     */
                    PDO::ATTR_PERSISTENT => true,

                    PDO::MYSQL_ATTR_INIT_COMMAND =>
                    "SET NAMES " . self::$config['charset'] .
                        " COLLATE " . self::$config['collation']
                ]
            );

            // Test connection
            self::$connection->query("SELECT 1");

            error_log("✅ Database connected: " . self::$config['database'] . " as " . self::$config['username']);
        } catch (PDOException $e) {
            $errorMsg = "Database connection failed: " . $e->getMessage();

            error_log("❌ " . $errorMsg);
            error_log("Connection details - Host: " . self::$config['host'] . ", DB: " . self::$config['database'] . ", User: '" . self::$config['username'] . "'");

            throw new \RuntimeException($errorMsg, 500, $e);
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
     * Get current configuration (for debugging)
     */
    public static function getConfig(): array
    {
        return [
            'host' => self::$config['host'] ?? 'not loaded',
            'database' => self::$config['database'] ?? 'not loaded',
            'username' => self::$config['username'] ?? 'not loaded',
            'charset' => self::$config['charset'] ?? 'not loaded'
        ];
    }
}
