<?php

namespace Janstro\InventorySystem\Config;

use PDO;
use PDOException;

/**
 * ============================================================================
 * DATABASE CONNECTION - PRODUCTION STABLE v3.0
 * ============================================================================
 * FIXES:
 * ✅ Removed persistent connections (causes stale connection bugs)
 * ✅ Added automatic reconnection logic
 * ✅ Better error handling with fallback
 * ✅ Connection pooling via singleton pattern
 * ============================================================================
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
    private static int $reconnectAttempts = 0;
    private static int $maxReconnectAttempts = 3;

    /**
     * Get database connection (singleton with auto-reconnect)
     */
    public static function connect(): PDO
    {
        // Test existing connection
        if (self::$connection instanceof PDO) {
            try {
                self::$connection->query("SELECT 1");
                return self::$connection;
            } catch (PDOException $e) {
                error_log("⚠️ Connection lost, reconnecting...");
                self::$connection = null;
                self::$reconnectAttempts++;
            }
        }

        // Prevent infinite reconnection loop
        if (self::$reconnectAttempts > self::$maxReconnectAttempts) {
            throw new \RuntimeException("Database connection failed after " . self::$maxReconnectAttempts . " attempts");
        }

        // Load config and create new connection
        self::loadConfig();
        self::createConnection();

        return self::$connection ?? throw new \RuntimeException("Failed to establish database connection");
    }

    /**
     * Load database configuration from .env
     */
    private static function loadConfig(): void
    {
        // Load .env file if not already loaded
        if (!isset($_ENV['DB_HOST'])) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;

                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value);
                        $value = trim($value, '"\'');

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
     * Create PDO connection with optimal settings
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
                    // ✅ REMOVED: PDO::ATTR_PERSISTENT (causes stale connections)
                    PDO::MYSQL_ATTR_INIT_COMMAND =>
                    "SET NAMES " . self::$config['charset'] .
                        " COLLATE " . self::$config['collation'] .
                        ", SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'",
                    PDO::ATTR_TIMEOUT => 5
                ]
            );

            // Test connection
            self::$connection->query("SELECT 1");

            // Reset reconnect counter on success
            self::$reconnectAttempts = 0;

            error_log("✅ Database connected: " . self::$config['database']);
        } catch (PDOException $e) {
            error_log("❌ Database connection failed: " . $e->getMessage());
            throw new \RuntimeException("Database unavailable: " . $e->getMessage(), 500, $e);
        }
    }

    /**
     * Close database connection
     */
    public static function disconnect(): void
    {
        self::$connection = null;
        self::$reconnectAttempts = 0;
    }

    /**
     * Check connection status
     */
    public static function isConnected(): bool
    {
        try {
            if (self::$connection === null) return false;
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
