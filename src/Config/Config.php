<?php

// Prevent direct access
if (!defined('JANSTRO_IMS')) {
    die('Direct access not permitted');
}

// ============================================================================
// ENVIRONMENT DETECTION
// ============================================================================
define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
define('IS_PRODUCTION', ENVIRONMENT === 'production');
define('IS_DEVELOPMENT', ENVIRONMENT === 'development');

// ============================================================================
// ERROR REPORTING
// ============================================================================
if (IS_PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}

// ============================================================================
// TIMEZONE
// ============================================================================
date_default_timezone_set('Asia/Manila');

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_DATABASE') ?: 'janstro_inventory');
define('DB_USER', getenv('DB_USERNAME') ?: 'janstro_user');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// ============================================================================
// APPLICATION PATHS
// ============================================================================
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/src');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/logs');
define('TEMP_PATH', STORAGE_PATH . '/temp');

// ============================================================================
// URL CONFIGURATION
// ============================================================================
// Auto-detect base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . '://' . $host . ($scriptPath !== '/' ? $scriptPath : '');

define('BASE_URL', getenv('APP_URL') ?: $baseUrl);
define('API_BASE_URL', BASE_URL . '/api');
define('ASSETS_URL', BASE_URL . '/assets');

// ============================================================================
// SECURITY SETTINGS
// ============================================================================
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secret-key-change-in-production');
define('JWT_EXPIRATION', 86400); // 24 hours in seconds
define('SESSION_LIFETIME', 86400); // 24 hours in seconds
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'your-encryption-key-32-chars-long');

// Password requirements
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// Rate limiting
define('RATE_LIMIT_ENABLED', true);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes in seconds

// ============================================================================
// FILE UPLOAD SETTINGS
// ============================================================================
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'csv']);

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================
define('MAIL_ENABLED', true);
define('MAIL_FROM', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@janstro.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Janstro IMS');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');

// ============================================================================
// NOTIFICATION SETTINGS
// ============================================================================
define('LOW_STOCK_ALERT_ENABLED', true);
define('LOW_STOCK_CHECK_INTERVAL', 3600); // Check every hour (in seconds)
define('ORDER_NOTIFICATION_ENABLED', true);
define('DAILY_SUMMARY_ENABLED', true);

// ============================================================================
// BUSINESS SETTINGS
// ============================================================================
define('COMPANY_NAME', 'Janstro Prime');
define('COMPANY_ADDRESS', 'Majayjay, Calabarzon, Philippines');
define('COMPANY_PHONE', '+63-XXX-XXX-XXXX'); // ⚠️ UPDATE THIS
define('COMPANY_EMAIL', 'info@janstro.com'); // ⚠️ UPDATE THIS
define('COMPANY_WEBSITE', 'https://www.janstro.com');

// Inventory defaults
define('DEFAULT_REORDER_LEVEL', 10);
define('DEFAULT_LEAD_TIME_DAYS', 7);
define('LOW_STOCK_THRESHOLD', 20);
define('DEFAULT_CURRENCY', 'PHP');
define('TAX_RATE', 0.12); // 12% VAT in Philippines

// ============================================================================
// PAGINATION & LIMITS
// ============================================================================
define('ITEMS_PER_PAGE', 25);
define('MAX_ITEMS_PER_PAGE', 100);
define('SEARCH_MIN_LENGTH', 2);
define('SEARCH_MAX_RESULTS', 50);

// ============================================================================
// LOGGING SETTINGS
// ============================================================================
define('LOG_ENABLED', true);
define('LOG_LEVEL', IS_PRODUCTION ? 'warning' : 'debug');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_RETENTION_DAYS', 90);

// ============================================================================
// BACKUP SETTINGS
// ============================================================================
define('BACKUP_ENABLED', true);
define('BACKUP_PATH', BASE_PATH . '/backups');
define('BACKUP_RETENTION_DAYS', 30);
define('BACKUP_DATABASE', true);
define('BACKUP_FILES', true);

// ============================================================================
// CACHING SETTINGS
// ============================================================================
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 hour in seconds
define('CACHE_DRIVER', 'file'); // Options: file, redis, memcached

// ============================================================================
// API SETTINGS
// ============================================================================
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // Requests per minute
define('API_KEY_REQUIRED', false); // Set to true if using API keys
define('API_CORS_ENABLED', true);
define('API_CORS_ORIGINS', BASE_URL);

// ============================================================================
// MAINTENANCE MODE
// ============================================================================
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'System is under maintenance. Please check back soon.');
define('MAINTENANCE_ALLOWED_IPS', ['127.0.0.1', '::1']);

// ============================================================================
// DEBUG & DEVELOPMENT
// ============================================================================
define('DEBUG_MODE', !IS_PRODUCTION);
define('QUERY_LOGGING', !IS_PRODUCTION);
define('ERROR_DETAIL_LEVEL', IS_PRODUCTION ? 'low' : 'high');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get configuration value
 */
function config($key, $default = null)
{
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if in maintenance mode
 */
function isMaintenanceMode()
{
    if (!MAINTENANCE_MODE) {
        return false;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    return !in_array($clientIp, MAINTENANCE_ALLOWED_IPS);
}

/**
 * Log message
 */
function logMessage($level, $message, $context = [])
{
    if (!LOG_ENABLED) {
        return;
    }

    $logLevels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];
    $currentLevel = $logLevels[LOG_LEVEL] ?? 1;
    $messageLevel = $logLevels[$level] ?? 1;

    if ($messageLevel < $currentLevel) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextJson = !empty($context) ? json_encode($context) : '';
    $logLine = "[$timestamp] [$level] $message $contextJson\n";

    $logFile = LOG_PATH . '/' . date('Y-m-d') . '.log';

    // Create log directory if it doesn't exist
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }

    error_log($logLine, 3, $logFile);
}

// ============================================================================
// AUTOLOAD CONFIGURATION
// ============================================================================
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'Janstro\\InventorySystem\\';
    $baseDir = APP_PATH . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// ============================================================================
// INITIALIZE DIRECTORIES
// ============================================================================
$directories = [
    STORAGE_PATH,
    UPLOAD_PATH,
    LOG_PATH,
    TEMP_PATH,
    BACKUP_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ============================================================================
// LOAD ENVIRONMENT VARIABLES (if .env file exists)
// ============================================================================
$envFile = BASE_PATH . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

            if (!empty($key) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// ============================================================================
// CONFIGURATION LOADED
// ============================================================================
define('CONFIG_LOADED', true);

// Log configuration loaded (only in development)
if (IS_DEVELOPMENT) {
    logMessage('info', 'Configuration loaded successfully', [
        'environment' => ENVIRONMENT,
        'base_url' => BASE_URL,
        'database' => DB_NAME
    ]);
}
