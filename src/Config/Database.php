<?php

namespace Janstro\InventorySystem\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * ============================================================================
 * DATABASE CONNECTION - PRODUCTION OPTIMIZED v3.1
 * ============================================================================
 * FEATURES:
 * ✅ Singleton pattern with connection pooling
 * ✅ Automatic reconnection on connection loss
 * ✅ Connection health checking
 * ✅ Optimized PDO configuration
 * ✅ Transaction support
 * ============================================================================
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
    private static bool $configLoaded = false;
    private static int $reconnectAttempts = 0;
    private static int $maxReconnectAttempts = 3;
    private static bool $inTransaction = false;

    /**
     * Get database connection (singleton with auto-reconnect)
     */
    public static function connect(): PDO
    {
        // Test existing connection health
        if (self::$connection instanceof PDO && self::isHealthy()) {
            return self::$connection;
        }

        // Connection lost or doesn't exist
        if (self::$connection instanceof PDO) {
            error_log("⚠️ Database connection lost, reconnecting...");
            self::$connection = null;
            self::$reconnectAttempts++;
        }

        // Prevent infinite reconnection loop
        if (self::$reconnectAttempts > self::$maxReconnectAttempts) {
            throw new RuntimeException(
                "Database connection failed after " . self::$maxReconnectAttempts . " attempts"
            );
        }

        // Load config once and create connection
        if (!self::$configLoaded) {
            self::loadConfig();
        }

        self::createConnection();

        return self::$connection ?? throw new RuntimeException("Failed to establish database connection");
    }

    /**
     * Load database configuration from environment variables
     * Note: .env is already loaded by autoload.php
     */
    private static function loadConfig(): void
    {
        self::$config = [
            'host' => trim($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost'),
            'database' => trim($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'janstro_inventory'),
            'username' => trim($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'root'),
            'password' => trim($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'port' => (int)($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 3306)
        ];

        self::$configLoaded = true;
    }

    /**
     * Create PDO connection with optimal settings
     */
    private static function createConnection(): void
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                self::$config['host'],
                self::$config['port'],
                self::$config['database'],
                self::$config['charset']
            );

            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                [
                    // Error handling
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // Fetch mode
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    // Use real prepared statements (security)
                    PDO::ATTR_EMULATE_PREPARES => false,

                    // Connection timeout
                    PDO::ATTR_TIMEOUT => 5,

                    // Initialize connection with proper charset and sql_mode
                    PDO::MYSQL_ATTR_INIT_COMMAND => sprintf(
                        "SET NAMES %s COLLATE %s, SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'",
                        self::$config['charset'],
                        self::$config['collation']
                    ),

                    // String results for better compatibility
                    PDO::ATTR_STRINGIFY_FETCHES => false,

                    // Case handling for column names
                    PDO::ATTR_CASE => PDO::CASE_NATURAL,

                    // Oracle nulls handling
                    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                ]
            );

            // Verify connection works
            self::$connection->query("SELECT 1");

            // Reset reconnect counter on success
            self::$reconnectAttempts = 0;

            error_log("✅ Database connected: " . self::$config['database'] . "@" . self::$config['host']);
        } catch (PDOException $e) {
            $safeMessage = "Database connection failed";
            error_log("❌ {$safeMessage}: " . $e->getMessage());

            // Don't expose database credentials in production
            $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
            $publicMessage = $isProduction ? $safeMessage : $e->getMessage();

            throw new RuntimeException($publicMessage, 500, $e);
        }
    }

    /**
     * Check if connection is healthy (private method)
     */
    private static function isHealthy(): bool
    {
        try {
            if (self::$connection === null) {
                return false;
            }

            // Simple query to check connection
            self::$connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check connection status (public method)
     */
    public static function isConnected(): bool
    {
        return self::$connection instanceof PDO && self::isHealthy();
    }

    /**
     * Close database connection
     */
    public static function disconnect(): void
    {
        // Rollback any pending transactions
        if (self::$inTransaction && self::$connection) {
            try {
                self::$connection->rollBack();
                error_log("⚠️ Rolling back uncommitted transaction before disconnect");
            } catch (PDOException $e) {
                error_log("⚠️ Could not rollback transaction: " . $e->getMessage());
            }
        }

        self::$connection = null;
        self::$reconnectAttempts = 0;
        self::$inTransaction = false;

        error_log("🔌 Database disconnected");
    }

    /**
     * Begin database transaction
     */
    public static function beginTransaction(): bool
    {
        $connection = self::connect();

        if (self::$inTransaction) {
            error_log("⚠️ Already in transaction, cannot start nested transaction");
            return false;
        }

        try {
            $result = $connection->beginTransaction();
            self::$inTransaction = true;
            return $result;
        } catch (PDOException $e) {
            error_log("❌ Failed to begin transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Commit database transaction
     */
    public static function commit(): bool
    {
        if (!self::$inTransaction) {
            error_log("⚠️ No active transaction to commit");
            return false;
        }

        try {
            $result = self::$connection->commit();
            self::$inTransaction = false;
            return $result;
        } catch (PDOException $e) {
            error_log("❌ Failed to commit transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rollback database transaction
     */
    public static function rollback(): bool
    {
        if (!self::$inTransaction) {
            error_log("⚠️ No active transaction to rollback");
            return false;
        }

        try {
            $result = self::$connection->rollBack();
            self::$inTransaction = false;
            return $result;
        } catch (PDOException $e) {
            error_log("❌ Failed to rollback transaction: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if currently in transaction
     */
    public static function inTransaction(): bool
    {
        return self::$inTransaction;
    }

    /**
     * Get current configuration (for debugging - passwords hidden)
     */
    public static function getConfig(): array
    {
        if (!self::$configLoaded) {
            self::loadConfig();
        }

        return [
            'host' => self::$config['host'] ?? 'not loaded',
            'database' => self::$config['database'] ?? 'not loaded',
            'username' => self::$config['username'] ?? 'not loaded',
            'password' => self::$config['password'] ? '***HIDDEN***' : 'not set',
            'charset' => self::$config['charset'] ?? 'not loaded',
            'port' => self::$config['port'] ?? 'not loaded'
        ];
    }

    /**
     * Get connection statistics (for monitoring)
     */
    public static function getStats(): array
    {
        return [
            'connected' => self::isConnected(),
            'reconnect_attempts' => self::$reconnectAttempts,
            'in_transaction' => self::$inTransaction,
            'database' => self::$config['database'] ?? 'unknown'
        ];
    }

    /**
     * Reset connection (force reconnect)
     */
    public static function reset(): void
    {
        self::disconnect();
        self::$reconnectAttempts = 0;
        self::connect();
    }
}
