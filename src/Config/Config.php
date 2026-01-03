<?php

// ============================================================================
// JANSTRO IMS - SECURE CONFIGURATION v2.0
// ============================================================================
// ⚠️ DEPLOYMENT CHECKLIST:
// 1. Set JWT_SECRET in .env (minimum 32 characters)
// 2. Set ENCRYPTION_KEY in .env (exactly 32 characters)
// 3. Update COMPANY_PHONE and COMPANY_EMAIL
// 4. Set APP_ENV=production in .env
// ============================================================================

namespace Janstro\InventorySystem\Config;

// Prevent direct file access
if (!defined('BASE_PATH')) {
    http_response_code(403);
    die('Direct access forbidden');
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
// ============================================================================
// APPLICATION PATHS
// ============================================================================
// BASE_PATH already defined in autoload.php - do not redefine

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
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$baseUrl = $protocol . '://' . $host . ($scriptPath !== '/' ? $scriptPath : '');

define('BASE_URL', getenv('APP_URL') ?: $baseUrl);
define('API_BASE_URL', BASE_URL . '/api');
define('ASSETS_URL', BASE_URL . '/assets');

// ============================================================================
// SECURITY SETTINGS - CRITICAL
// ============================================================================

// JWT Configuration
$jwtSecret = getenv('JWT_SECRET');
if (!$jwtSecret || strlen($jwtSecret) < 32) {
    if (IS_PRODUCTION) {
        // In production, FAIL HARD if JWT_SECRET is missing
        error_log("FATAL: JWT_SECRET not configured or too short");
        http_response_code(500);
        die('Server configuration error. Contact administrator.');
    } else {
        // Development fallback with warning
        $jwtSecret = 'dev-secret-' . bin2hex(random_bytes(16));
        error_log("WARNING: Using auto-generated JWT_SECRET for development");
    }
}
define('JWT_SECRET', $jwtSecret);
define('JWT_EXPIRATION', 86400); // 24 hours in seconds

// Encryption Key
$encryptionKey = getenv('ENCRYPTION_KEY');
if (!$encryptionKey || strlen($encryptionKey) !== 32) {
    if (IS_PRODUCTION) {
        error_log("FATAL: ENCRYPTION_KEY not configured or invalid length");
        http_response_code(500);
        die('Server configuration error. Contact administrator.');
    } else {
        $encryptionKey = bin2hex(random_bytes(16)); // 32 chars
        error_log("WARNING: Using auto-generated ENCRYPTION_KEY for development");
    }
}
define('ENCRYPTION_KEY', $encryptionKey);

// Session Configuration
define('SESSION_LIFETIME', 86400); // 24 hours in seconds

// Password Requirements
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_DURATION', 900); // 15 minutes in seconds

// ============================================================================
// FILE UPLOAD SETTINGS
// ============================================================================
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB in bytes
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'csv']);
define('PROFILE_PICTURE_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('PROFILE_PICTURE_ALLOWED_TYPES', ['jpg', 'jpeg', 'png']);

// ============================================================================
// EMAIL CONFIGURATION
// ============================================================================
define('MAIL_ENABLED', (bool)(getenv('MAIL_ENABLED') ?: false));
define('MAIL_FROM', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@janstro.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Janstro IMS');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 587));
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
define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'Janstro Prime Renewable Energy Solutions Corporation');
define('COMPANY_ADDRESS', getenv('COMPANY_ADDRESS') ?: 'Palo Alto Bay Hill Executive Subdivision Calamba, Laguna, Philippines');
define('COMPANY_PHONE', getenv('COMPANY_PHONE') ?: '+63 999 759 4616');
define('COMPANY_EMAIL', getenv('COMPANY_EMAIL') ?: 'janstroprime@gmail.com');
define('COMPANY_WEBSITE', getenv('COMPANY_WEBSITE') ?: 'https://janstrosolar.wixsite.com/website');

// Inventory Defaults
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
define('CACHE_ENABLED', IS_PRODUCTION);
define('CACHE_TTL', 3600); // 1 hour in seconds
define('CACHE_DRIVER', 'file'); // Options: file, redis, memcached

// ============================================================================
// API SETTINGS
// ============================================================================
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 100); // Requests per minute
define('API_KEY_REQUIRED', false);
define('API_CORS_ENABLED', true);
define('API_CORS_ORIGINS', BASE_URL);

// ============================================================================
// MAINTENANCE MODE
// ============================================================================
define('MAINTENANCE_MODE', (bool)(getenv('MAINTENANCE_MODE') ?: false));
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
function isMaintenanceMode(): bool
{
    if (!MAINTENANCE_MODE) {
        return false;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    return !in_array($clientIp, MAINTENANCE_ALLOWED_IPS, true);
}

/**
 * Log message to file
 */
function logMessage(string $level, string $message, array $context = []): void
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
    $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
    $logLine = "[{$timestamp}] [{$level}] {$message} {$contextJson}\n";

    $logFile = LOG_PATH . '/' . date('Y-m-d') . '.log';

    // Create log directory if it doesn't exist
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0755, true);
    }

    // Check log file size and rotate if needed
    if (file_exists($logFile) && filesize($logFile) > LOG_MAX_SIZE) {
        rename($logFile, $logFile . '.' . time() . '.old');
    }

    error_log($logLine, 3, $logFile);
}

/**
 * Generate secure random token
 */
function generateSecureToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

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
        @mkdir($dir, 0755, true);
    }
}

// ============================================================================
// VALIDATE CRITICAL CONFIGURATION
// ============================================================================
if (IS_PRODUCTION) {
    $criticalChecks = [
        'JWT_SECRET' => strlen(JWT_SECRET) >= 32,
        'ENCRYPTION_KEY' => strlen(ENCRYPTION_KEY) === 32,
        'DB_NAME' => !empty(DB_NAME),
        'DB_USER' => !empty(DB_USER),
    ];

    foreach ($criticalChecks as $setting => $isValid) {
        if (!$isValid) {
            error_log("FATAL: Invalid configuration for {$setting}");
            http_response_code(500);
            die('Server misconfigured. Contact administrator.');
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
        'database' => DB_NAME,
        'mail_enabled' => MAIL_ENABLED,
        'cache_enabled' => CACHE_ENABLED
    ]);
}
