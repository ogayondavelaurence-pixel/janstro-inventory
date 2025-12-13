<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * AUTHENTICATION CONTROLLER - FIXED v2.0
 * ============================================================================
 * 
 * ✅ FIXES APPLIED:
 * - getCurrentUser() now returns profile_picture, last_login, created_at
 * - Added contact_no field
 * - Proper SQL query with all necessary fields
 * ============================================================================
 */
class AuthController
{
    private $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * USER LOGIN - Handles authentication
     */
    public function login()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            error_log("🔐 Login attempt with data: " . json_encode([
                'has_username' => isset($data['username']),
                'has_email' => isset($data['email']),
                'has_password' => isset($data['password'])
            ]));

            $identifier = $data['username'] ?? $data['email'] ?? null;
            $password = $data['password'] ?? null;

            if (empty($identifier)) {
                error_log("❌ Login failed: Missing username/email");
                Response::badRequest('Username or email is required');
                return;
            }

            if (empty($password)) {
                error_log("❌ Login failed: Missing password");
                Response::badRequest('Password is required');
                return;
            }

            error_log("🔍 Attempting login for identifier: {$identifier}");

            $result = $this->authService->login($identifier, $password);

            if ($result['success']) {
                error_log("✅ Login successful for: {$identifier}");
                Response::success($result['data'], 'Login successful');
            } else {
                error_log("⚠️ Login returned success=false for: {$identifier}");
                Response::unauthorized($result['message'] ?? 'Invalid credentials');
            }
        } catch (\Exception $e) {
            error_log("❌ Login exception: " . $e->getMessage());
            Response::unauthorized($e->getMessage());
        }
    }

    /**
     * USER LOGOUT
     */
    public function logout()
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;

            if ($userId) {
                $this->authService->logout($userId);
                error_log("✅ User {$userId} logged out");
            }

            Response::success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            error_log("❌ Logout exception: " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * ========================================================================
     * GET CURRENT USER INFO - ✅ FIXED VERSION
     * ========================================================================
     * 
     * CHANGES:
     * 1. SQL query now selects profile_picture, last_login, created_at, contact_no
     * 2. Response includes all these fields
     * 3. Proper handling of NULL values
     * ========================================================================
     */
    public function getCurrentUser()
    {
        try {
            // STEP 1: Extract token from Authorization header
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            if (empty($authHeader)) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? '';
            }

            if (empty($authHeader)) {
                error_log("❌ Get current user: No Authorization header");
                Response::unauthorized('No token provided');
                return;
            }

            // STEP 2: Extract token from "Bearer <token>" format
            if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                error_log("❌ Get current user: Invalid Authorization header format");
                Response::unauthorized('Invalid token format');
                return;
            }

            $token = $matches[1];

            // STEP 3: Validate token and get payload
            $payload = $this->authService->validateToken($token);

            // STEP 4: Get fresh user data from database
            $db = \Janstro\InventorySystem\Config\Database::connect();

            // ✅ FIX: Added profile_picture, last_login, created_at, contact_no
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
                    u.last_login,
                    u.created_at,
                    r.role_name
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Check if user still exists and is active
            if (!$user) {
                error_log("❌ Get current user: User not found in database");
                Response::notFound('User not found');
                return;
            }

            if ($user['status'] !== 'active') {
                error_log("❌ Get current user: User account inactive");
                Response::unauthorized('Account is inactive');
                return;
            }

            // STEP 5: Return user data with ALL fields
            // ✅ FIX: Added profile_picture, last_login, created_at, contact_no
            Response::success([
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role_name'],
                'role_name' => $user['role_name'],
                'role_id' => (int)$user['role_id'],
                'status' => $user['status'],
                'contact_no' => $user['contact_no'] ?? null,
                'profile_picture' => $user['profile_picture'] ?? null,  // ✅ CRITICAL FIX
                'last_login' => $user['last_login'] ?? null,             // ✅ CRITICAL FIX
                'created_at' => $user['created_at'] ?? null               // ✅ CRITICAL FIX
            ], 'User retrieved');

            error_log("✅ getCurrentUser: Returned profile_picture = " . ($user['profile_picture'] ? 'YES' : 'NULL'));
        } catch (\Exception $e) {
            error_log("❌ Get current user exception: " . $e->getMessage());
            Response::unauthorized($e->getMessage());
        }
    }
}
