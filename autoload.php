<?php

/**
 * ============================================================================
 * JANSTRO IMS - AUTOLOADER v3.0 (LOCAL & PRODUCTION READY)
 * ============================================================================
 * Location: C:\xampp\htdocs\janstro-inventory\autoload.php
 * Date: 2025-12-17
 * ============================================================================
 */

define('BASE_PATH', __DIR__);
define('PUBLIC_PATH', __DIR__ . '/public');

// ============================================================================
// COMPOSER AUTOLOADER (CRITICAL FIX - LOADS TCPDF)
// ============================================================================
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('❌ Composer dependencies not installed. Run: composer install');
}
require_once __DIR__ . '/vendor/autoload.php';
error_log("✅ Composer autoloader loaded (TCPDF available)");

// ============================================================================
// ENVIRONMENT LOADER
// ============================================================================
function loadEnv($path = null)
{
    if ($path === null) $path = BASE_PATH . '/.env';
    if (!file_exists($path)) {
        error_log("⚠️ WARNING: .env file not found at: {$path}");
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines
        if (empty($line) || $line[0] === '#') continue;

        // Skip lines without =
        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes from value
        $value = trim($value, '"\'');

        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    error_log("✅ Environment loaded from .env");
}

// Load environment variables
loadEnv();

// ============================================================================
// CLASS AUTOLOADER (PSR-4)
// ============================================================================
spl_autoload_register(function ($class) {
    $prefix = 'Janstro\\InventorySystem\\';
    $base = BASE_PATH . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================================
// SIMPLE JWT IMPLEMENTATION
// ============================================================================
class SimpleJWT
{
    private static function b64e($d)
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function b64d($d)
    {
        $r = strlen($d) % 4;
        if ($r) $d .= str_repeat('=', 4 - $r);
        return base64_decode(strtr($d, '-_', '+/'));
    }

    public static function encode($p, $s, $a = 'HS256')
    {
        $h = ['typ' => 'JWT', 'alg' => $a];
        $seg = [self::b64e(json_encode($h)), self::b64e(json_encode($p))];
        $sig = hash_hmac('sha256', implode('.', $seg), $s, true);
        $seg[] = self::b64e($sig);
        return implode('.', $seg);
    }

    public static function decode($jwt, $s)
    {
        $p = explode('.', $jwt);
        if (count($p) !== 3) throw new Exception('Invalid JWT');

        $sig = hash_hmac('sha256', $p[0] . '.' . $p[1], $s, true);
        if ($p[2] !== self::b64e($sig)) throw new Exception('Invalid signature');

        $pay = json_decode(self::b64d($p[1]));
        if (!$pay) throw new Exception('Invalid payload');
        if (isset($pay->exp) && $pay->exp < time()) throw new Exception('Token expired');

        return $pay;
    }
}

// ============================================================================
// ENVIRONMENT DETECTION
// ============================================================================
$env = $_ENV['APP_ENV'] ?? 'development';
$isProduction = ($env === 'production');
$isLocal = !$isProduction;

define('APP_ENV', $env);
define('IS_PRODUCTION', $isProduction);
define('IS_LOCAL', $isLocal);

error_log("========================================");
error_log("🚀 JANSTRO IMS AUTOLOADER v3.0");
error_log("========================================");
error_log("Environment: " . $env);
error_log("Base Path: " . BASE_PATH);

// ============================================================================
// VALIDATE CRITICAL ENVIRONMENT VARIABLES
// ============================================================================
$criticalVars = ['DB_DATABASE', 'DB_USERNAME'];
$missingVars = [];

foreach ($criticalVars as $var) {
    if (empty($_ENV[$var] ?? '')) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars) && $isProduction) {
    error_log("❌ FATAL: Missing environment variables: " . implode(', ', $missingVars));
    http_response_code(500);
    die('Server configuration error. Contact administrator.');
}

// ============================================================================
// JWT & ENCRYPTION KEY SETUP
// ============================================================================

// JWT Secret
if (empty($_ENV['JWT_SECRET'])) {
    if ($isProduction) {
        error_log("❌ FATAL: JWT_SECRET not configured");
        http_response_code(500);
        die('Server configuration error. Contact administrator.');
    } else {
        // Development fallback
        $_ENV['JWT_SECRET'] = 'dev_jwt_' . bin2hex(random_bytes(16));
        putenv('JWT_SECRET=' . $_ENV['JWT_SECRET']);
        error_log("⚠️ WARNING: Using auto-generated JWT_SECRET for development");
    }
}

// Encryption Key
if (empty($_ENV['ENCRYPTION_KEY'])) {
    if ($isProduction) {
        error_log("❌ FATAL: ENCRYPTION_KEY not configured");
        http_response_code(500);
        die('Server configuration error. Contact administrator.');
    } else {
        // Development fallback
        $_ENV['ENCRYPTION_KEY'] = bin2hex(random_bytes(16)); // 32 characters
        putenv('ENCRYPTION_KEY=' . $_ENV['ENCRYPTION_KEY']);
        error_log("⚠️ WARNING: Using auto-generated ENCRYPTION_KEY for development");
    }
}

error_log("✅ JWT_SECRET: " . (strlen($_ENV['JWT_SECRET']) >= 32 ? 'OK' : 'TOO SHORT'));
error_log("✅ ENCRYPTION_KEY: " . (strlen($_ENV['ENCRYPTION_KEY']) >= 32 ? 'OK' : 'TOO SHORT'));

// ============================================================================
// DATABASE CONFIGURATION DISPLAY (for debugging)
// ============================================================================
error_log("📊 Database Config:");
error_log("  - Host: " . ($_ENV['DB_HOST'] ?? 'localhost'));
error_log("  - Database: " . ($_ENV['DB_DATABASE'] ?? 'janstro_inventory'));
error_log("  - Username: " . ($_ENV['DB_USERNAME'] ?? 'root'));
error_log("  - Password: " . (empty($_ENV['DB_PASSWORD']) ? '(empty)' : '***SET***'));

// ============================================================================
// TIMEZONE
// ============================================================================
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Manila');
error_log("🕐 Timezone: " . date_default_timezone_get());

// ============================================================================
// ERROR REPORTING
// ============================================================================
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_log("🔒 Production mode: Errors hidden from output");
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_log("🔓 Development mode: Full error reporting");
}

// ============================================================================
// CREATE REQUIRED DIRECTORIES
// ============================================================================
$dirs = [
    BASE_PATH . '/storage',
    BASE_PATH . '/storage/uploads',
    BASE_PATH . '/storage/uploads/profile_pictures',
    BASE_PATH . '/logs',
    BASE_PATH . '/cache',
    BASE_PATH . '/backups'
];

foreach ($dirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }
}

error_log("✅ Directory structure verified");
error_log("========================================");
error_log("✅ AUTOLOADER COMPLETE");
error_log("========================================");
