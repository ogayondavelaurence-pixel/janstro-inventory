<?php

namespace Janstro\InventorySystem\Middleware;

class SecurityMiddleware
{

    // CSRF Token Management
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!isset($_SESSION['csrf_token']) || !$token) return false;

        // Token expires after 1 hour
        if (isset($_SESSION['csrf_time']) && (time() - $_SESSION['csrf_time']) > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    // Input Sanitization
    public static function sanitize($input)
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return is_string($input)
            ? htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8')
            : $input;
    }

    // SQL Injection Detection
    public static function detectSQLInjection(string $input): bool
    {
        $patterns = [
            '/(\bSELECT\b|\bUNION\b|\bINSERT\b|\bDROP\b)/i',
            '/(--|#|\/\*|\*\/)/i',
            '/(\bOR\b\s+\d+\s*=\s*\d+)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('SQL_INJECTION_ATTEMPT', $input);
                return true;
            }
        }
        return false;
    }

    // XSS Detection
    public static function detectXSS(string $input): bool
    {
        $patterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('XSS_ATTEMPT', $input);
                return true;
            }
        }
        return false;
    }

    // Rate Limiting
    public static function rateLimit(string $identifier, int $maxAttempts = 10, int $window = 60): bool
    {
        $cacheFile = sys_get_temp_dir() . '/rate_' . md5($identifier) . '.json';
        $data = ['attempts' => 0, 'first' => time()];

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }

        // Reset if window expired
        if ((time() - $data['first']) > $window) {
            $data = ['attempts' => 0, 'first' => time()];
        }

        // Check limit
        if ($data['attempts'] >= $maxAttempts) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', $identifier);
            return false;
        }

        $data['attempts']++;
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }

    // Security Event Logger
    private static function logSecurityEvent(string $type, string $details): void
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $logFile = $logDir . '/security.log';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $entry = sprintf(
            "[%s] [%s] IP:%s | %s\n",
            date('Y-m-d H:i:s'),
            $type,
            $ip,
            substr($details, 0, 200)
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    // Validate Request
    public static function validateRequest(array $data): array
    {
        $errors = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                if (self::detectSQLInjection($value)) {
                    $errors[] = "SQL injection detected in $key";
                }
                if (self::detectXSS($value)) {
                    $errors[] = "XSS attempt detected in $key";
                }
            }
        }

        return $errors;
    }

    // Apply to Request
    public static function protect(): void
    {
        // Rate limit check
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!self::rateLimit($ip, 100, 60)) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Rate limit exceeded. Please wait.'
            ]);
            exit;
        }

        // Validate POST/PUT requests
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                $errors = self::validateRequest($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => 'Security validation failed',
                        'errors' => $errors
                    ]);
                    exit;
                }
            }
        }
    }
}
