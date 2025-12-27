<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Config\Database;

/**
 * ============================================================================
 * AUTHENTICATION CONTROLLER v3.0 (OPTIMIZED)
 * ============================================================================
 * Handles user authentication, logout, and current user retrieval
 * ============================================================================
 */
class AuthController
{
    private AuthService $authService;
    private static bool $debugMode = false;

    public function __construct()
    {
        $this->authService = new AuthService();
        self::initDebugMode();
    }

    /**
     * Initialize debug mode based on environment
     */
    private static function initDebugMode(): void
    {
        if (!isset(self::$debugMode)) {
            $appEnv = $_ENV['APP_ENV'] ?? 'production';
            self::$debugMode = ($appEnv !== 'production');
        }
    }

    /**
     * Log authentication events (only in debug mode)
     */
    private static function log(string $message, string $level = 'INFO'): void
    {
        if (self::$debugMode) {
            error_log("[{$level}] AuthController: {$message}");
        }
    }

    /**
     * USER LOGIN
     * POST /api/auth/login
     * 
     * @return void
     */
    public function login(): void
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            self::log("Login attempt initiated");

            // Extract credentials
            $identifier = $data['username'] ?? $data['email'] ?? null;
            $password = $data['password'] ?? null;

            // Validate input
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
                self::log("Login successful for: {$identifier}");
                Response::success($result['data'], 'Login successful');
            } else {
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
     * 
     * @return void
     */
    public function logout(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;

            if ($userId) {
                $this->authService->logout($userId);
                self::log("User {$userId} logged out successfully");
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
     * 
     * Returns complete user profile including:
     * - Basic info (id, username, name, email)
     * - Role information
     * - Profile picture
     * - Last login timestamp
     * - Account creation date
     * - Contact information
     * 
     * @return void
     */
    public function getCurrentUser(): void
    {
        try {
            // Extract and validate token using JWT utility
            $token = JWT::getFromHeader();

            if (!$token) {
                self::log("getCurrentUser: No token provided", 'WARNING');
                Response::unauthorized('No token provided');
                return;
            }

            // Validate token and get payload
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
                    u.user_id, 
                    u.username, 
                    u.name, 
                    u.email, 
                    u.status, 
                    u.role_id,
                    u.contact_no,
                    u.profile_picture,
                    u.profile_picture_thumb,
                    u.last_login,
                    u.created_at,
                    u.last_password_change,
                    r.role_name,
                    r.description as role_description
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");

            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Validate user exists and is active
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

            // Return comprehensive user data
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
     * 
     * Quick token validation without full user data
     * 
     * @return void
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
     * 
     * Get new token using existing valid token
     * 
     * @return void
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
                Response::success($result['data'], 'Token refreshed successfully');
            } else {
                Response::unauthorized($result['message'] ?? 'Token refresh failed');
            }
        } catch (\Exception $e) {
            self::log("Token refresh exception: " . $e->getMessage(), 'ERROR');
            Response::unauthorized('Token refresh failed');
        }
    }
}
