<?php

namespace Janstro\InventorySystem\Utils;

/**
 * ============================================================================
 * SECURITY UTILITIES - PRODUCTION GRADE v2.0
 * ============================================================================
 * Comprehensive security toolkit for Janstro IMS
 * ============================================================================
 */
class Security
{
    private static ?string $encryptionKey = null;
    private static bool $isProduction = false;

    /**
     * Initialize security settings
     */
    private static function init(): void
    {
        if (self::$encryptionKey === null) {
            self::$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
            self::$encryptionKey = self::getEncryptionKey();
        }
    }

    /**
     * Get encryption key with base64 prefix handling
     */
    private static function getEncryptionKey(): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');

        if (!$key) {
            if (self::$isProduction) {
                error_log('FATAL: ENCRYPTION_KEY not configured');
                throw new \RuntimeException('Server misconfigured');
            }
            // Development fallback
            $key = bin2hex(random_bytes(16));
            error_log('WARNING: Using auto-generated encryption key');
        }

        // Handle "base64:" prefix (Laravel-style)
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }

        return $key;
    }

    // ========================================================================
    // CSRF PROTECTION
    // ========================================================================

    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF Token
     */
    public static function verifyCSRFToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['csrf_token']) || !$token) {
            return false;
        }

        // Check token age (expire after 1 hour)
        if (isset($_SESSION['csrf_token_time'])) {
            $tokenAge = time() - $_SESSION['csrf_token_time'];
            if ($tokenAge > 3600) {
                unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
                return false;
            }
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // ========================================================================
    // INPUT SANITIZATION & VALIDATION
    // ========================================================================

    /**
     * Sanitize Input (XSS Prevention)
     */
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }

        if (is_string($input)) {
            return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
        }

        return $input;
    }

    /**
     * Sanitize Input - Alias for sanitize() for backwards compatibility
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    public static function sanitizeInput($input)
    {
        return self::sanitize($input);
    }

    /**
     * Sanitize Output - HTML-safe output
     * @param string $output Output to sanitize
     * @return string Safe HTML output
     */
    public static function sanitizeOutput(string $output): string
    {
        return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate Email
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate Philippine Phone Number
     */
    public static function validatePhone(string $phone): bool
    {
        // Formats: 09XXXXXXXXX or +639XXXXXXXXX
        return preg_match('/^(\+639|09)\d{9}$/', $phone) === 1;
    }

    /**
     * Validate Alphanumeric (for IDs, usernames)
     */
    public static function validateAlphanumeric(string $input): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $input) === 1;
    }

    /**
     * Validate Integer ID
     */
    public static function validateId($id): bool
    {
        return is_numeric($id) && $id > 0;
    }

    // ========================================================================
    // PASSWORD MANAGEMENT
    // ========================================================================

    /**
     * Hash Password (bcrypt with cost 12)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify Password
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password needs rehashing
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ========================================================================
    // RATE LIMITING
    // ========================================================================

    /**
     * Check Rate Limit (Session-based)
     */
    public static function checkRateLimit(
        string $key,
        int $maxAttempts = 5,
        int $timeWindow = 300
    ): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $now = time();
        $limitKey = 'rl_' . md5($key);

        // Initialize if not exists
        if (!isset($_SESSION['rate_limit'][$limitKey])) {
            $_SESSION['rate_limit'][$limitKey] = [
                'attempts' => 1,
                'first_attempt' => $now,
                'last_attempt' => $now
            ];
            return true;
        }

        $data = $_SESSION['rate_limit'][$limitKey];
        $timeElapsed = $now - $data['first_attempt'];

        // Reset if time window expired
        if ($timeElapsed > $timeWindow) {
            $_SESSION['rate_limit'][$limitKey] = [
                'attempts' => 1,
                'first_attempt' => $now,
                'last_attempt' => $now
            ];
            return true;
        }

        // Check if limit exceeded
        if ($data['attempts'] >= $maxAttempts) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', "Key: {$key}", 'WARNING');
            return false;
        }

        // Increment attempts
        $_SESSION['rate_limit'][$limitKey]['attempts']++;
        $_SESSION['rate_limit'][$limitKey]['last_attempt'] = $now;

        return true;
    }

    /**
     * Reset Rate Limit
     */
    public static function resetRateLimit(string $key): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $limitKey = 'rl_' . md5($key);
        if (isset($_SESSION['rate_limit'][$limitKey])) {
            unset($_SESSION['rate_limit'][$limitKey]);
        }
    }

    /**
     * Cleanup expired rate limits (call periodically)
     */
    public static function cleanupRateLimits(): void
    {
        if (!isset($_SESSION['rate_limit'])) {
            return;
        }

        $now = time();
        $cleaned = 0;

        foreach ($_SESSION['rate_limit'] as $key => $data) {
            if (($now - $data['first_attempt']) > 3600) { // 1 hour
                unset($_SESSION['rate_limit'][$key]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            error_log("Cleaned {$cleaned} expired rate limit entries");
        }
    }

    // ========================================================================
    // SESSION MANAGEMENT
    // ========================================================================

    /**
     * Configure Secure Session
     */
    public static function configureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return; // Session already started
        }

        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
        $isHttps = self::isSecureConnection();

        // Session security settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Strict');

        // ✅ FIXED: Only enable secure cookie if HTTPS is available
        ini_set('session.cookie_secure', ($isProduction && $isHttps) ? '1' : '0');

        // Session name
        session_name('JANSTRO_SESSION');

        session_start();

        // Regenerate session ID periodically (every 5 minutes)
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }

        // Session timeout (30 minutes of inactivity)
        if (isset($_SESSION['last_activity'])) {
            $inactiveTime = time() - $_SESSION['last_activity'];
            if ($inactiveTime > 1800) {
                session_destroy();
                session_start();
            }
        }
        $_SESSION['last_activity'] = time();
    }

    // ========================================================================
    // ENCRYPTION
    // ========================================================================

    /**
     * Encrypt Data (AES-256-GCM - most secure)
     */
    public static function encrypt(string $data): string
    {
        self::init();

        $iv = random_bytes(16);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            'AES-256-GCM',
            self::$encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Format: base64(iv + tag + encrypted)
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt Data
     */
    public static function decrypt(string $data): string
    {
        self::init();

        $decoded = base64_decode($data, true);

        if ($decoded === false || strlen($decoded) < 33) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($decoded, 0, 16);
        $tag = substr($decoded, 16, 16);
        $encrypted = substr($decoded, 32);

        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-GCM',
            self::$encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    // ========================================================================
    // THREAT DETECTION
    // ========================================================================

    /**
     * Detect SQL Injection Patterns
     */
    public static function detectSQLInjection(string $input): bool
    {
        $sqlPatterns = [
            '/(\bSELECT\b|\bUNION\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            '/(--|\#|\/\*|\*\/)/i',
            '/(\bOR\b\s+\d+\s*=\s*\d+)/i',
            '/(\bAND\b\s+\d+\s*=\s*\d+)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('SQL_INJECTION_ATTEMPT', substr($input, 0, 100), 'CRITICAL');
                return true;
            }
        }

        return false;
    }

    /**
     * Detect XSS Patterns
     */
    public static function detectXSS(string $input): bool
    {
        $xssPatterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('XSS_ATTEMPT', substr($input, 0, 100), 'CRITICAL');
                return true;
            }
        }

        return false;
    }

    // ========================================================================
    // LOGGING & MONITORING
    // ========================================================================

    /**
     * Log Security Event
     */
    public static function logSecurityEvent(
        string $eventType,
        string $details = '',
        string $severity = 'INFO'
    ): void {
        $logDir = __DIR__ . '/../../logs';
        $logFile = $logDir . '/security.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $ipAddress = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $userId = $_SESSION['user_id'] ?? 'Guest';
        $uri = $_SERVER['REQUEST_URI'] ?? 'Unknown';

        $logEntry = sprintf(
            "[%s] [%s] %s | User: %s | IP: %s | URI: %s | Details: %s | UA: %s\n",
            date('Y-m-d H:i:s'),
            $severity,
            $eventType,
            $userId,
            $ipAddress,
            $uri,
            substr($details, 0, 200),
            substr($userAgent, 0, 100)
        );

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also log critical events to system log
        if (in_array($severity, ['CRITICAL', 'ERROR'])) {
            error_log("[SECURITY {$severity}] {$eventType}: {$details}");
        }
    }

    // ========================================================================
    // NETWORK & CONNECTION
    // ========================================================================

    /**
     * Get Client IP Address (handles proxies)
     */
    public static function getClientIP(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                // Validate IP and exclude private/reserved ranges
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Check if connection is HTTPS
     */
    public static function isSecureConnection(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Enforce HTTPS (redirect if not secure)
     */
    public static function enforceHTTPS(): void
    {
        $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        if ($isProduction && !self::isSecureConnection()) {
            $redirectUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
    }

    // ========================================================================
    // UTILITIES
    // ========================================================================

    /**
     * Generate Secure Random Token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate UUID v4
     */
    public static function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Constant-time string comparison (timing attack prevention)
     */
    public static function compareStrings(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
