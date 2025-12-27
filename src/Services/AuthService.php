<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;

/**
 * ============================================================================
 * AUTHENTICATION SERVICE - PRODUCTION READY v4.0
 * ============================================================================
 * FIXES:
 * ✅ Conditional debug logging (only in development)
 * ✅ Profile picture fields included
 * ✅ Rate limiting with file-based persistence
 * ✅ Account lockout after failed attempts
 * ✅ Clean, maintainable code
 * ============================================================================
 */
class AuthService
{
    private $db;
    private $jwtSecret;
    private $jwtExpiry;
    private static bool $debugMode = false;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'janstro_inventory_secret_key_2025';
        $this->jwtExpiry = (int)($_ENV['JWT_EXPIRATION'] ?? 86400);

        // Initialize debug mode once
        self::$debugMode = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

        if (self::$debugMode) {
            self::log("AuthService initialized | JWT Expiry: {$this->jwtExpiry}s");
        }
    }

    /**
     * Conditional logging - only in development
     */
    private static function log(string $message, string $level = 'INFO'): void
    {
        if (self::$debugMode) {
            error_log("[{$level}] AuthService: {$message}");
        }
    }

    /**
     * ========================================================================
     * LOGIN - WITH JWT TOKEN + PROFILE PICTURES + RATE LIMITING
     * ========================================================================
     */
    public function login($identifier, $password)
    {
        self::log("Login attempt for: {$identifier}");

        // Validate inputs
        if (empty($identifier)) {
            throw new \Exception("Username or email is required");
        }

        if (empty($password)) {
            throw new \Exception("Password is required");
        }

        // Rate limiting check
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey = "login_attempts_{$ip}";

        if (!$this->checkRateLimit($rateLimitKey)) {
            self::log("Rate limit exceeded for IP: {$ip}", 'WARNING');
            throw new \Exception("Too many login attempts. Please try again in 15 minutes.");
        }

        // Query database with profile picture fields
        $sql = "
            SELECT 
                u.user_id, 
                u.username, 
                u.email, 
                u.password_hash, 
                u.role_id,
                u.name,
                u.status,
                u.failed_login_attempts,
                u.last_failed_login,
                u.profile_picture,
                u.profile_picture_thumb,
                r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.username = ? OR u.email = ?
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            self::log("Database error: " . $e->getMessage(), 'ERROR');
            throw new \Exception("Database connection failed");
        }

        // Check if user exists
        if (!$user) {
            self::log("User not found: {$identifier}", 'WARNING');
            $this->incrementRateLimitCounter($rateLimitKey);
            throw new \Exception("Invalid credentials");
        }

        self::log("User found: {$user['username']} (Role: {$user['role_name']})");

        // Check account lockout
        $failedAttempts = (int)($user['failed_login_attempts'] ?? 0);
        $lastFailed = $user['last_failed_login'] ?? null;

        if ($failedAttempts >= 5 && $lastFailed) {
            $lockoutExpiry = strtotime($lastFailed) + 900; // 15 minutes
            if (time() < $lockoutExpiry) {
                $minutesLeft = ceil(($lockoutExpiry - time()) / 60);
                self::log("Account locked: {$user['username']}", 'WARNING');
                throw new \Exception("Account locked due to multiple failed login attempts. Try again in {$minutesLeft} minutes.");
            } else {
                // Lockout expired, reset counter
                $this->resetFailedAttempts($user['user_id']);
            }
        }

        // Check account status
        if ($user['status'] !== 'active') {
            self::log("Inactive account: {$user['username']}", 'WARNING');
            throw new \Exception("Account is inactive. Contact administrator.");
        }

        // Verify password
        $passwordValid = password_verify($password, $user['password_hash']);

        if (!$passwordValid) {
            self::log("Invalid password for: {$user['username']}", 'WARNING');

            $this->incrementFailedAttempts($user['user_id']);
            $this->incrementRateLimitCounter($rateLimitKey);

            $remainingAttempts = 5 - ($failedAttempts + 1);
            if ($remainingAttempts > 0) {
                throw new \Exception("Invalid credentials. {$remainingAttempts} attempts remaining before lockout.");
            } else {
                throw new \Exception("Invalid credentials. Account has been locked for 15 minutes.");
            }
        }

        self::log("Login successful: {$user['username']}");

        // Reset failed attempts on success
        $this->resetFailedAttempts($user['user_id']);
        $this->resetRateLimitCounter($rateLimitKey);

        // Update last login
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
        } catch (\PDOException $e) {
            self::log("Could not update last_login: " . $e->getMessage(), 'WARNING');
        }

        // Create audit log
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address, module, action_type)
                VALUES (?, ?, ?, 'auth', 'login_success')
            ");
            $stmt->execute([
                $user['user_id'],
                "User logged in successfully",
                $ip
            ]);
        } catch (\PDOException $e) {
            self::log("Could not create audit log: " . $e->getMessage(), 'WARNING');
        }

        // Generate JWT token
        $tokenPayload = [
            'user_id' => (int)$user['user_id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role_name'],
            'role_id' => (int)$user['role_id']
        ];

        try {
            $token = $this->generateToken($tokenPayload);
            self::log("JWT token generated successfully");
        } catch (\Exception $e) {
            self::log("Token generation error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }

        // Build response with profile pictures
        return [
            'success' => true,
            'data' => [
                'token' => $token,
                'user' => [
                    'user_id' => (int)$user['user_id'],
                    'username' => $user['username'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role_name'],
                    'role_id' => (int)$user['role_id'],
                    'role_name' => $user['role_name'],
                    'profile_picture' => $user['profile_picture'] ?? null,
                    'profile_picture_thumb' => $user['profile_picture_thumb'] ?? null
                ]
            ]
        ];
    }

    /**
     * ========================================================================
     * RATE LIMITING HELPER METHODS (File-based persistence)
     * ========================================================================
     */
    private function checkRateLimit(string $key): bool
    {
        $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.json';

        if (!file_exists($cacheFile)) {
            return true;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        $attempts = $data['attempts'] ?? 0;
        $firstAttempt = $data['first_attempt'] ?? time();

        // Reset if 15 minutes passed
        if (time() - $firstAttempt > 900) {
            unlink($cacheFile);
            return true;
        }

        // Block if >= 10 attempts in 15 minutes
        return $attempts < 10;
    }

    private function incrementRateLimitCounter(string $key): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.json';

        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $data['attempts']++;
        } else {
            $data = ['attempts' => 1, 'first_attempt' => time()];
        }

        file_put_contents($cacheFile, json_encode($data));
    }

    private function resetRateLimitCounter(string $key): void
    {
        $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.json';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    /**
     * ========================================================================
     * FAILED LOGIN ATTEMPTS TRACKING
     * ========================================================================
     */
    private function incrementFailedAttempts(int $userId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET failed_login_attempts = failed_login_attempts + 1,
                    last_failed_login = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            self::log("Failed to increment login attempts: " . $e->getMessage(), 'ERROR');
        }
    }

    private function resetFailedAttempts(int $userId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET failed_login_attempts = 0,
                    last_failed_login = NULL
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } catch (\PDOException $e) {
            self::log("Failed to reset login attempts: " . $e->getMessage(), 'ERROR');
        }
    }

    /**
     * ========================================================================
     * JWT TOKEN METHODS
     * ========================================================================
     */
    private function generateToken($payload)
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + $this->jwtExpiry;

        $tokenPayload = [
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'data' => $payload
        ];

        if (self::$debugMode) {
            self::log("Token will expire at: " . date('Y-m-d H:i:s', $expiresAt));
        }

        return \SimpleJWT::encode($tokenPayload, $this->jwtSecret, 'HS256');
    }

    /**
     * Validate JWT token
     */
    public function validateToken($token)
    {
        try {
            $decoded = \SimpleJWT::decode($token, $this->jwtSecret);

            if (isset($decoded->exp) && $decoded->exp < time()) {
                self::log("Token expired", 'WARNING');
                throw new \Exception("Token expired");
            }

            if (!isset($decoded->data)) {
                self::log("Token missing 'data' wrapper", 'WARNING');
                throw new \Exception("Invalid token structure");
            }

            return (array) $decoded->data;
        } catch (\Exception $e) {
            self::log("Token validation failed: " . $e->getMessage(), 'WARNING');
            throw new \Exception("Invalid or expired token: " . $e->getMessage());
        }
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(string $token): array
    {
        try {
            $payload = $this->validateToken($token);

            // Generate new token
            $newToken = $this->generateToken($payload);

            return [
                'success' => true,
                'data' => [
                    'token' => $newToken
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * ========================================================================
     * LOGOUT
     * ========================================================================
     */
    public function logout($userId)
    {
        self::log("User {$userId} logging out");

        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                "User logged out",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\PDOException $e) {
            self::log("Could not create logout audit log: " . $e->getMessage(), 'WARNING');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();

        return ['success' => true, 'message' => 'Logged out successfully'];
    }
}
