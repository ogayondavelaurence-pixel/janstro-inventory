<?php

namespace Janstro\InventorySystem\Utils;

/**
 * Security Utility Class - ENHANCED v2.0
 * ISO/IEC 25010: Security, Reliability
 * 
 * Features:
 * - XSS Protection
 * - CSRF Token Management
 * - Rate Limiting
 * - Input Sanitization
 * - SQL Injection Prevention Helpers
 */
class Security
{
    // ==============================
    // XSS PROTECTION
    // ==============================

    /**
     * Sanitize output to prevent XSS attacks
     * Converts HTML special characters to entities
     */
    public static function sanitizeOutput(string $data): string
    {
        return htmlspecialchars($data, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Sanitize array recursively
     */
    public static function sanitizeArray(array $data): array
    {
        return array_map(function ($value) {
            if (is_string($value)) {
                return self::sanitizeOutput($value);
            } elseif (is_array($value)) {
                return self::sanitizeArray($value);
            }
            return $value;
        }, $data);
    }

    // ==============================
    // CSRF PROTECTION
    // ==============================

    /**
     * Generate CSRF token
     * Stored in session for verification
     */
    public static function generateCsrfToken(): string
    {
        if (!session_id()) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     * Uses timing-safe comparison
     */
    public static function verifyCsrfToken(string $token): bool
    {
        if (!session_id()) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        // Check token age (1 hour expiry)
        if (isset($_SESSION['csrf_token_time'])) {
            $tokenAge = time() - $_SESSION['csrf_token_time'];
            if ($tokenAge > 3600) { // 1 hour
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Regenerate CSRF token
     * Called after successful form submission
     */
    public static function regenerateCsrfToken(): string
    {
        if (!session_id()) {
            session_start();
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();

        return $_SESSION['csrf_token'];
    }

    // ==============================
    // RATE LIMITING
    // ==============================

    /**
     * Check rate limit (IP-based)
     * 
     * @param string $key Unique identifier for rate limit (e.g., "login_192.168.1.1")
     * @param int $limit Maximum requests allowed
     * @param int $seconds Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public static function checkRateLimit(string $key, int $limit = 5, int $seconds = 60): bool
    {
        if (!session_id()) {
            session_start();
        }

        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $now = time();

        // Initialize key if not exists
        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [];
        }

        // Remove old timestamps outside the time window
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            fn($timestamp) => ($timestamp + $seconds) > $now
        );

        // Check if limit exceeded
        if (count($_SESSION['rate_limit'][$key]) >= $limit) {
            error_log("Rate limit exceeded for key: $key");
            return false;
        }

        // Add current timestamp
        $_SESSION['rate_limit'][$key][] = $now;

        return true;
    }

    /**
     * Reset rate limit for specific key
     */
    public static function resetRateLimit(string $key): void
    {
        if (!session_id()) {
            session_start();
        }

        if (isset($_SESSION['rate_limit'][$key])) {
            unset($_SESSION['rate_limit'][$key]);
        }
    }

    // ==============================
    // INPUT SANITIZATION
    // ==============================

    /**
     * Escape input to prevent XSS
     * Use this for user-generated content before storing
     */
    public static function escapeInput(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate and sanitize email
     */
    public static function sanitizeEmail(string $email): ?string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }

    /**
     * Sanitize integer
     */
    public static function sanitizeInt($value): ?int
    {
        $sanitized = filter_var($value, FILTER_VALIDATE_INT);
        return $sanitized !== false ? $sanitized : null;
    }

    /**
     * Sanitize float
     */
    public static function sanitizeFloat($value): ?float
    {
        $sanitized = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $sanitized !== false ? $sanitized : null;
    }

    /**
     * Sanitize string (remove HTML tags)
     */
    public static function sanitizeString(string $input): string
    {
        return strip_tags(trim($input));
    }

    // ==============================
    // SQL INJECTION PREVENTION HELPERS
    // ==============================

    /**
     * Validate table name (alphanumeric + underscore only)
     * Prevents SQL injection in dynamic table names
     */
    public static function validateTableName(string $tableName): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $tableName) === 1;
    }

    /**
     * Validate column name (alphanumeric + underscore only)
     * Prevents SQL injection in dynamic column names
     */
    public static function validateColumnName(string $columnName): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $columnName) === 1;
    }

    /**
     * Escape SQL LIKE wildcards
     * Use before binding LIKE parameters
     */
    public static function escapeLikeWildcards(string $input): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $input);
    }

    // ==============================
    // PASSWORD SECURITY
    // ==============================

    /**
     * Validate password strength
     * Minimum 8 chars, at least 1 letter and 1 number
     */
    public static function validatePasswordStrength(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        // At least one letter and one number
        return preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
    }

    /**
     * Hash password using bcrypt (recommended)
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password against hash
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // ==============================
    // FILE UPLOAD SECURITY
    // ==============================

    /**
     * Validate file extension
     */
    public static function validateFileExtension(string $filename, array $allowedExtensions): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $allowedExtensions);
    }

    /**
     * Generate safe filename
     */
    public static function generateSafeFilename(string $originalFilename): string
    {
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($originalFilename, PATHINFO_FILENAME);

        // Remove special characters
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);

        return $safe . '_' . time() . '.' . $extension;
    }

    // ==============================
    // SECURE RANDOM GENERATION
    // ==============================

    /**
     * Generate cryptographically secure random token
     */
    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Generate secure numeric code
     */
    public static function generateSecureCode(int $length = 6): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    // ==============================
    // IP ADDRESS VALIDATION
    // ==============================

    /**
     * Get client IP address (handles proxies)
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Check if IP is in whitelist
     */
    public static function isIpWhitelisted(string $ip, array $whitelist): bool
    {
        return in_array($ip, $whitelist);
    }

    // ==============================
    // SECURITY LOGGING
    // ==============================

    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $logDir = __DIR__ . '/../../logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = self::getClientIp();
        $contextJson = json_encode($context);

        $logEntry = "[$timestamp] $event - IP: $ip - Details: $contextJson\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
