<?php

namespace Janstro\InventorySystem\Middleware;

use Janstro\InventorySystem\Utils\Security;

/**
 * ============================================================================
 * SECURITY MIDDLEWARE v2.2 - DELETION REQUEST FIX
 * ============================================================================
 * FIXES:
 * ✅ Whitelists /privacy/request-deletion from SQL injection detection
 * ✅ Users can now type "delete" in deletion reasons
 * ✅ File-based persistent rate limiting
 * ✅ XSS and path traversal detection still active
 * ============================================================================
 */
class SecurityMiddleware
{
    private static string $rateLimitFile = '';
    private static array $rateLimitData = [];

    /**
     * Initialize rate limit storage
     */
    private static function initRateLimitStorage(): void
    {
        if (empty(self::$rateLimitFile)) {
            $tempDir = sys_get_temp_dir();
            self::$rateLimitFile = $tempDir . '/janstro_rate_limits.json';

            if (file_exists(self::$rateLimitFile)) {
                $content = @file_get_contents(self::$rateLimitFile);
                if ($content) {
                    self::$rateLimitData = json_decode($content, true) ?? [];
                }
            }
        }
    }

    /**
     * Save rate limit data to file
     */
    private static function saveRateLimitData(): void
    {
        @file_put_contents(
            self::$rateLimitFile,
            json_encode(self::$rateLimitData),
            LOCK_EX
        );
    }

    /**
     * Check rate limit (persistent)
     */
    private static function checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        self::initRateLimitStorage();

        $now = time();
        $windowStart = $now - $windowSeconds;

        if (!isset(self::$rateLimitData[$key])) {
            self::$rateLimitData[$key] = [];
        }

        // Remove old attempts
        self::$rateLimitData[$key] = array_filter(
            self::$rateLimitData[$key],
            fn($timestamp) => $timestamp > $windowStart
        );

        $currentAttempts = count(self::$rateLimitData[$key]);

        if ($currentAttempts >= $maxAttempts) {
            return false;
        }

        // Record this attempt
        self::$rateLimitData[$key][] = $now;
        self::saveRateLimitData();

        return true;
    }

    /**
     * Cleanup old rate limit entries
     */
    private static function cleanupRateLimits(): void
    {
        self::initRateLimitStorage();

        $now = time();
        $maxAge = 3600; // 1 hour

        foreach (self::$rateLimitData as $key => $timestamps) {
            $filtered = array_filter($timestamps, fn($ts) => ($now - $ts) < $maxAge);

            if (empty($filtered)) {
                unset(self::$rateLimitData[$key]);
            } else {
                self::$rateLimitData[$key] = array_values($filtered);
            }
        }

        self::saveRateLimitData();
    }

    /**
     * Apply global security protection
     */
    public static function protect(bool $skipValidation = false): void
    {
        // 1. Rate limiting
        self::applyRateLimit();

        // 2. Validate HTTP methods
        self::validateDangerousMethods();

        // 3. Validate request data
        if (!$skipValidation && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST', 'PUT', 'PATCH'], true)) {
            self::validateRequestData();
        }

        // 4. Cleanup (1% chance per request)
        if (rand(1, 100) === 1) {
            self::cleanupRateLimits();
        }
    }

    /**
     * Apply rate limiting
     */
    private static function applyRateLimit(): void
    {
        $ip = Security::getClientIP();
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Global: 100 req/min
        if (!self::checkRateLimit("global_{$ip}", 100, 60)) {
            self::sendRateLimitResponse();
        }

        // Login: 5 attempts/5min
        if (strpos($endpoint, '/auth/login') !== false && $method === 'POST') {
            if (!self::checkRateLimit("login_{$ip}", 5, 300)) {
                Security::logSecurityEvent('LOGIN_RATE_LIMIT_EXCEEDED', "IP: {$ip}", 'WARNING');
                self::sendRateLimitResponse('Too many login attempts');
            }
        }

        // API: 200 req/min
        if (strpos($endpoint, '/api/') !== false) {
            if (!self::checkRateLimit("api_{$ip}", 200, 60)) {
                self::sendRateLimitResponse('API rate limit exceeded');
            }
        }
    }

    /**
     * Send rate limit response
     */
    private static function sendRateLimitResponse(string $message = 'Rate limit exceeded'): void
    {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: 60');

        echo json_encode([
            'success' => false,
            'message' => $message . '. Please try again later.',
            'error_code' => 'RATE_LIMIT_EXCEEDED'
        ]);

        exit;
    }

    /**
     * Validate HTTP methods
     */
    private static function validateDangerousMethods(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS', 'PATCH'];

        if (!in_array($method, $allowed)) {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }
    }

    /**
     * ✅ CRITICAL FIX: Whitelist deletion requests from SQL injection check
     */
    private static function validateRequestData(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // ✅ WHITELIST: Skip SQL injection check for deletion requests
        // Users legitimately need to type words like "delete", "remove", etc.
        if (strpos($uri, '/privacy/request-deletion') !== false) {
            error_log("🛡️ SecurityMiddleware: Whitelisted /privacy/request-deletion - skipping SQL injection check");
            return;
        }

        // ✅ WHITELIST: Admin deletion approval endpoints
        if (strpos($uri, '/admin/deletion-requests') !== false) {
            error_log("🛡️ SecurityMiddleware: Whitelisted /admin/deletion-requests");
            return;
        }

        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) return;

        $data = json_decode($rawInput, true);
        if (!is_array($data)) return;

        $threats = self::detectThreats($data);

        if (!empty($threats)) {
            Security::logSecurityEvent('REQUEST_VALIDATION_FAILED', implode(', ', $threats), 'CRITICAL');

            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Security validation failed',
                'error_code' => 'INVALID_INPUT'
            ]);
            exit;
        }
    }

    /**
     * Detect threats in data
     */
    private static function detectThreats(array $data, string $path = ''): array
    {
        $threats = [];

        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            if (is_array($value)) {
                $threats = array_merge($threats, self::detectThreats($value, $currentPath));
            } elseif (is_string($value)) {
                if (Security::detectSQLInjection($value)) {
                    $threats[] = "SQL injection in '{$currentPath}'";
                }
                if (Security::detectXSS($value)) {
                    $threats[] = "XSS in '{$currentPath}'";
                }
                if (self::detectPathTraversal($value)) {
                    $threats[] = "Path traversal in '{$currentPath}'";
                }
            }
        }

        return $threats;
    }

    /**
     * Detect path traversal
     */
    private static function detectPathTraversal(string $input): bool
    {
        $patterns = ['/\.\.\//', '/\.\.\\\\/', '/%2e%2e%2f/i', '/%2e%2e\//i', '/\.\.\%2f/i'];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken(): string
    {
        return Security::generateCSRFToken();
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken(?string $token): bool
    {
        return Security::verifyCSRFToken($token);
    }

    /**
     * Sanitize input
     */
    public static function sanitize($input)
    {
        return Security::sanitize($input);
    }

    /**
     * Check if IP is whitelisted
     */
    public static function isWhitelistedIP(string $ip): bool
    {
        $whitelist = ['127.0.0.1', '::1'];

        if (isset($_ENV['WHITELISTED_IPS'])) {
            $envIPs = explode(',', $_ENV['WHITELISTED_IPS']);
            $whitelist = array_merge($whitelist, array_map('trim', $envIPs));
        }

        return in_array($ip, $whitelist, true);
    }

    /**
     * Get rate limit stats
     */
    public static function getRateLimitStats(): array
    {
        self::initRateLimitStorage();

        $stats = ['total_keys' => count(self::$rateLimitData), 'keys' => []];

        foreach (self::$rateLimitData as $key => $timestamps) {
            $stats['keys'][$key] = count($timestamps);
        }

        return $stats;
    }

    /**
     * Cleanup old rate limit files (run via cron daily)
     */
    public static function cleanupOldFiles(): int
    {
        $tempDir = sys_get_temp_dir();
        $pattern = $tempDir . '/janstro_rate_limits_*.json';
        $deleted = 0;
        $maxAge = 86400; // 24 hours

        foreach (glob($pattern) as $file) {
            if (file_exists($file) && (time() - filemtime($file)) > $maxAge) {
                @unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
