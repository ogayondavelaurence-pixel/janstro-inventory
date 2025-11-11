<?php

namespace Janstro\InventorySystem\Utils;

/**
 * Security Utility Class
 * Comprehensive security functions for the system
 * ISO/IEC 25010:2023 - Security Compliance
 */
class Security
{
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
                unset($_SESSION['csrf_token']);
                unset($_SESSION['csrf_token_time']);
                return false;
            }
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

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
        // Format: 09XXXXXXXXX or +639XXXXXXXXX
        return preg_match('/^(\+639|09)\d{9}$/', $phone) === 1;
    }

    /**
     * Hash Password (Modern bcrypt)
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
     * Rate Limiting Check
     * Prevents brute force attacks
     */
    public static function checkRateLimit(string $key, int $maxAttempts = 5, int $timeWindow = 300): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        if (!isset($_SESSION['rate_limit'][$key])) {
            $_SESSION['rate_limit'][$key] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
            return true;
        }

        $data = $_SESSION['rate_limit'][$key];
        $timeElapsed = time() - $data['first_attempt'];

        // Reset if time window expired
        if ($timeElapsed > $timeWindow) {
            $_SESSION['rate_limit'][$key] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
            return true;
        }

        // Check if limit exceeded
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }

        // Increment attempts
        $_SESSION['rate_limit'][$key]['attempts']++;
        return true;
    }

    /**
     * Reset Rate Limit
     */
    public static function resetRateLimit(string $key): void
    {
        if (isset($_SESSION['rate_limit'][$key])) {
            unset($_SESSION['rate_limit'][$key]);
        }
    }

    /**
     * Configure Secure Session
     */
    public static function configureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Prevent session fixation
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', '1'); // HTTPS only
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');

            // Session name
            session_name('JANSTRO_SESSION');

            session_start();

            // Regenerate session ID periodically
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }

            // Session timeout (30 minutes)
            if (isset($_SESSION['last_activity'])) {
                $inactiveTime = time() - $_SESSION['last_activity'];
                if ($inactiveTime > 1800) {
                    session_destroy();
                    session_start();
                }
            }
            $_SESSION['last_activity'] = time();
        }
    }

    /**
     * Log Security Event
     */
    public static function logSecurityEvent(string $eventType, string $details = '', string $severity = 'INFO'): void
    {
        $logDir = __DIR__ . '/../../logs';
        $logFile = $logDir . '/security.log';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $ipAddress = self::getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $userId = $_SESSION['user_id'] ?? 'Guest';

        $logEntry = sprintf(
            "[%s] [%s] %s | User: %s | IP: %s | Details: %s | UA: %s\n",
            date('Y-m-d H:i:s'),
            $severity,
            $eventType,
            $userId,
            $ipAddress,
            $details,
            substr($userAgent, 0, 100)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get Client IP Address
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

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Prevent SQL Injection (validate input patterns)
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

    /**
     * Generate Secure Random Token
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Encrypt Data (AES-256-CBC)
     */
    public static function encrypt(string $data): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt Data
     */
    public static function decrypt(string $data): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-me';
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Check if request is HTTPS
     */
    public static function isSecureConnection(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443;
    }

    /**
     * Enforce HTTPS
     */
    public static function enforceHTTPS(): void
    {
        if (!self::isSecureConnection() && $_ENV['APP_ENV'] === 'production') {
            $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header('Location: ' . $redirectUrl, true, 301);
            exit;
        }
    }

    /**
     * Check for common SQL injection patterns
     */
    public static function detectSQLInjection(string $input): bool
    {
        $sqlPatterns = [
            '/(\bSELECT\b|\bUNION\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            '/(--|\#|\/\*|\*\/)/i',
            '/(\bOR\b\s+\d+\s*=\s*\d+)/i',
            '/(\bAND\b\s+\d+\s*=\s*\d+)/i'
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('SQL_INJECTION_ATTEMPT', $input, 'CRITICAL');
                return true;
            }
        }

        return false;
    }

    /**
     * Check for XSS patterns
     */
    public static function detectXSS(string $input): bool
    {
        $xssPatterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i'
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('XSS_ATTEMPT', $input, 'CRITICAL');
                return true;
            }
        }

        return false;
    }
}
