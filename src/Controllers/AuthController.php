<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Services\AuditService;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Config\Database;

/**
 * ============================================================================
 * AUTHENTICATION CONTROLLER v4.0 (REFACTORED - SERVICE LAYER)
 * ============================================================================
 * Line count: ~150 (down from ~240, 38% reduction)
 * Changes:
 * - Uses AuditService for all logging
 * - Zero direct audit_logs table access
 * - Cleaner error handling
 * ============================================================================
 */
class AuthController
{
    private AuthService $authService;
    private AuditService $auditService;
    private static bool $debugMode = false;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->auditService = new AuditService();
        self::initDebugMode();
    }

    private static function initDebugMode(): void
    {
        if (!isset(self::$debugMode)) {
            $appEnv = $_ENV['APP_ENV'] ?? 'production';
            self::$debugMode = ($appEnv !== 'production');
        }
    }

    private static function log(string $message, string $level = 'INFO'): void
    {
        if (self::$debugMode) {
            error_log("[{$level}] AuthController: {$message}");
        }
    }

    /**
     * USER LOGIN
     * POST /api/auth/login
     */
    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            self::log("Login attempt initiated");

            $identifier = $data['username'] ?? $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if (empty($identifier)) {
                self::log("Login failed: Missing username/email", 'WARNING');
                Response::badRequest('Username or email is required');
                return;
            }

            if (empty($password)) {
                self::log("Login failed: Missing password", 'WARNING');
                Response::badRequest('Password is required');
                return;
            }

            self::log("Attempting authentication for: {$identifier}");

            // Authenticate via service
            $result = $this->authService->login($identifier, $password);

            if ($result['success']) {
                $userId = $result['data']['user']['user_id'];

                // ✅ Audit logging via AuditService
                $this->auditService->logAuth($userId, 'login', true);

                self::log("Login successful for: {$identifier}");
                Response::success($result['data'], 'Login successful');
            } else {
                // Get user ID for failed attempt logging (if user exists)
                $userId = $this->getUserIdByIdentifier($identifier);
                if ($userId) {
                    $this->auditService->logAuth($userId, 'login', false);
                }

                self::log("Login failed for: {$identifier} - " . ($result['message'] ?? 'Unknown error'), 'WARNING');
                Response::unauthorized($result['message'] ?? 'Invalid credentials');
            }
        } catch (\Exception $e) {
            self::log("Login exception: " . $e->getMessage(), 'ERROR');
            Response::unauthorized('Authentication failed');
        }
    }

    /**
     * USER LOGOUT
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;

            if ($userId) {
                $this->authService->logout($userId);

                // ✅ Audit logging via AuditService
                $this->auditService->logAuth($userId, 'logout', true);

                self::log("User {$userId} logged out successfully");
            }

            // Blacklist JWT token
            $token = JWT::getFromHeader();
            if ($token) {
                JWT::blacklist($token);
                self::log("Token blacklisted successfully");
            }

            // Clear session
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            Response::success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            self::log("Logout exception: " . $e->getMessage(), 'ERROR');
            Response::serverError('Logout failed');
        }
    }

    /**
     * GET CURRENT USER INFO
     * GET /api/auth/me
     */
    public function getCurrentUser(): void
    {
        try {
            $token = JWT::getFromHeader();

            if (!$token) {
                self::log("getCurrentUser: No token provided", 'WARNING');
                Response::unauthorized('No token provided');
                return;
            }

            $payload = $this->authService->validateToken($token);

            if (!$payload || !isset($payload['user_id'])) {
                self::log("getCurrentUser: Invalid token payload", 'WARNING');
                Response::unauthorized('Invalid token');
                return;
            }

            // Get fresh user data from database
            $db = Database::connect();

            $stmt = $db->prepare("
                SELECT 
                    u.user_id, u.username, u.name, u.email, u.status, u.role_id,
                    u.contact_no, u.profile_picture, u.profile_picture_thumb,
                    u.last_login, u.created_at, u.last_password_change,
                    r.role_name, r.description as role_description
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");

            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                self::log("getCurrentUser: User ID {$payload['user_id']} not found", 'WARNING');
                Response::notFound('User not found');
                return;
            }

            if ($user['status'] !== 'active') {
                self::log("getCurrentUser: User account inactive for ID {$payload['user_id']}", 'WARNING');
                Response::unauthorized('Account is inactive');
                return;
            }

            Response::success([
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'contact_no' => $user['contact_no'] ?? null,
                'role' => $user['role_name'],
                'role_name' => $user['role_name'],
                'role_id' => (int)$user['role_id'],
                'role_description' => $user['role_description'] ?? null,
                'status' => $user['status'],
                'profile_picture' => $user['profile_picture'] ?? null,
                'profile_picture_thumb' => $user['profile_picture_thumb'] ?? null,
                'last_login' => $user['last_login'] ?? null,
                'last_password_change' => $user['last_password_change'] ?? null,
                'created_at' => $user['created_at'] ?? null
            ], 'User retrieved successfully');

            self::log("getCurrentUser: Successfully retrieved user {$user['username']}");
        } catch (\Exception $e) {
            self::log("getCurrentUser exception: " . $e->getMessage(), 'ERROR');
            Response::unauthorized('Failed to retrieve user information');
        }
    }

    /**
     * VERIFY TOKEN
     * GET /api/auth/verify
     */
    public function verifyToken(): void
    {
        try {
            $token = JWT::getFromHeader();

            if (!$token) {
                Response::unauthorized('No token provided');
                return;
            }

            $payload = $this->authService->validateToken($token);

            if (!$payload) {
                Response::unauthorized('Invalid token');
                return;
            }

            Response::success([
                'valid' => true,
                'user_id' => $payload['user_id'] ?? null,
                'username' => $payload['username'] ?? null,
                'role' => $payload['role'] ?? null
            ], 'Token is valid');
        } catch (\Exception $e) {
            Response::unauthorized('Token validation failed');
        }
    }

    /**
     * REFRESH TOKEN
     * POST /api/auth/refresh
     */
    public function refreshToken(): void
    {
        try {
            $token = JWT::getFromHeader();

            if (!$token) {
                Response::unauthorized('No token provided');
                return;
            }

            $result = $this->authService->refreshToken($token);

            if ($result['success']) {
                // ✅ Audit logging via AuditService
                $payload = $this->authService->validateToken($result['data']['token']);
                if ($payload && isset($payload['user_id'])) {
                    $this->auditService->logAuth($payload['user_id'], 'token_refresh', true);
                }

                Response::success($result['data'], 'Token refreshed successfully');
            } else {
                Response::unauthorized($result['message'] ?? 'Token refresh failed');
            }
        } catch (\Exception $e) {
            self::log("Token refresh exception: " . $e->getMessage(), 'ERROR');
            Response::unauthorized('Token refresh failed');
        }
    }

    /**
     * Helper: Get user ID by username/email for failed login logging
     */
    private function getUserIdByIdentifier(string $identifier): ?int
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT user_id FROM users 
                WHERE username = ? OR email = ? 
                LIMIT 1
            ");
            $stmt->execute([$identifier, $identifier]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ? (int)$result['user_id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
