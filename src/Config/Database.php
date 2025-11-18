<?php

namespace Janstro\InventorySystem\Config;

use PDO;
use PDOException;

/**
 * Database Connection Manager - FIXED (No Dotenv Required)
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
        // Load .env if not already loaded (using manual loader from autoload.php)
        if (!isset($_ENV['DB_HOST'])) {
            $envPath = __DIR__ . '/../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        $key = trim($key);
                        $value = trim($value, '"\'');
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
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
            error_log("Database Connection Error: " . $e->getMessage());
            throw new \RuntimeException(
                "Database connection failed: " . $e->getMessage(),
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
}
