<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * AUTHENTICATION CONTROLLER - FIXED FOR LOCALHOST TESTING
 * ============================================================================
 * 
 * WHAT THIS FILE DOES:
 * - Receives login requests from the frontend
 * - Validates input data
 * - Passes credentials to AuthService for authentication
 * - Returns JWT token to frontend on success
 * 
 * KEY FIX:
 * - Removed email conversion logic (lines 28-48 OLD CODE)
 * - Now accepts username OR email directly
 * - Passes identifier as-is to AuthService
 * 
 * TEACHING NOTE FOR NEW DEVELOPERS:
 * Controllers are like "receptionists" - they receive requests,
 * validate basic input, then hand off to Services for actual work.
 * Keep controllers thin, services fat (business logic goes in services).
 * ============================================================================
 */
class AuthController
{
    private $authService;

    /**
     * Constructor - Initialize AuthService
     * 
     * This runs automatically when the controller is created.
     * We inject the AuthService dependency here (Dependency Injection pattern).
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * ========================================================================
     * USER LOGIN - MAIN ENTRY POINT (FIXED)
     * ========================================================================
     * 
     * STEP-BY-STEP PROCESS:
     * 1. Parse JSON body from request
     * 2. Extract identifier (username OR email)
     * 3. Extract password
     * 4. Validate both fields exist
     * 5. Pass to AuthService for authentication
     * 6. Return success response with token
     * 
     * HTTP REQUEST EXAMPLE:
     * POST /auth/login
     * Content-Type: application/json
     * 
     * {
     *   "username": "admin",      // Can be username
     *   "password": "Admin@2024"
     * }
     * 
     * OR
     * 
     * {
     *   "email": "admin@example.com",  // Can be email
     *   "password": "Admin@2024"
     * }
     * 
     * RESPONSE ON SUCCESS:
     * {
     *   "success": true,
     *   "message": "Login successful",
     *   "data": {
     *     "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
     *     "user": {
     *       "user_id": 1,
     *       "username": "admin",
     *       "email": "your-email@gmail.com",
     *       "role": "admin"
     *     }
     *   }
     * }
     * 
     * RESPONSE ON FAILURE (401):
     * {
     *   "success": false,
     *   "message": "Invalid credentials"
     * }
     */
    public function login()
    {
        try {
            // STEP 1: Parse JSON request body
            // file_get_contents('php://input') reads raw POST data
            // json_decode converts JSON string to PHP array
            $data = json_decode(file_get_contents('php://input'), true);

            // Log the login attempt (helps with debugging)
            error_log("🔐 Login attempt with data: " . json_encode([
                'has_username' => isset($data['username']),
                'has_email' => isset($data['email']),
                'has_password' => isset($data['password'])
            ]));

            // STEP 2: Extract identifier (username OR email)
            // The frontend can send either 'username' or 'email' field
            // We accept both and let AuthService figure it out
            $identifier = $data['username'] ?? $data['email'] ?? null;

            // STEP 3: Extract password
            $password = $data['password'] ?? null;

            // STEP 4: Validate required fields
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

            // Log what we're attempting to authenticate with
            error_log("🔍 Attempting login for identifier: {$identifier}");

            // STEP 5: Call AuthService to authenticate
            // This is where the actual password checking happens
            // AuthService will:
            // - Check rate limiting
            // - Query database for user
            // - Verify password
            // - Generate JWT token
            $result = $this->authService->login($identifier, $password);

            // STEP 6: Return success response
            // If we get here, login was successful
            if ($result['success']) {
                error_log("✅ Login successful for: {$identifier}");
                Response::success($result['data'], 'Login successful');
            } else {
                // This shouldn't happen (AuthService throws exceptions on failure)
                // But we handle it just in case
                error_log("⚠️ Login returned success=false for: {$identifier}");
                Response::unauthorized($result['message'] ?? 'Invalid credentials');
            }
        } catch (\Exception $e) {
            // EXCEPTION HANDLING
            // Any errors from AuthService (wrong password, account locked, etc.)
            // will be caught here and returned as 401 Unauthorized

            error_log("❌ Login exception: " . $e->getMessage());

            // Return user-friendly error message
            Response::unauthorized($e->getMessage());
        }
    }

    /**
     * ========================================================================
     * USER LOGOUT
     * ========================================================================
     * 
     * Logs out the current user by:
     * 1. Recording the logout in audit_logs
     * 2. Destroying the session
     * 
     * HTTP REQUEST EXAMPLE:
     * POST /auth/logout
     * Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
     * 
     * RESPONSE:
     * {
     *   "success": true,
     *   "message": "Logged out successfully"
     * }
     */
    public function logout()
    {
        try {
            // Get user_id from session (if available)
            $userId = $_SESSION['user_id'] ?? null;

            if ($userId) {
                // Call AuthService to handle logout logic
                $this->authService->logout($userId);
                error_log("✅ User {$userId} logged out");
            }

            // Return success even if no active session
            // (Logout should always succeed from user's perspective)
            Response::success(null, 'Logged out successfully');
        } catch (\Exception $e) {
            error_log("❌ Logout exception: " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * ========================================================================
     * GET CURRENT USER INFO
     * ========================================================================
     * 
     * Returns the currently authenticated user's information
     * by validating their JWT token.
     * 
     * HTTP REQUEST EXAMPLE:
     * GET /auth/me
     * Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
     * 
     * RESPONSE:
     * {
     *   "success": true,
     *   "message": "User retrieved",
     *   "data": {
     *     "user_id": 1,
     *     "username": "admin",
     *     "email": "your-email@gmail.com",
     *     "role": "admin",
     *     "status": "active"
     *   }
     * }
     */
    public function getCurrentUser()
    {
        try {
            // STEP 1: Extract token from Authorization header
            // Header format: "Authorization: Bearer <token>"
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            // If not in $_SERVER, try getallheaders()
            if (empty($authHeader)) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? '';
            }

            // Check if header exists
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
            $stmt = $db->prepare("
                SELECT u.user_id, u.username, u.name, u.email, u.status, u.role_id,
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

            // STEP 5: Return user data
            Response::success([
                'user_id' => (int)$user['user_id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role_name'],
                'role_id' => (int)$user['role_id'],
                'status' => $user['status']
            ], 'User retrieved');
        } catch (\Exception $e) {
            error_log("❌ Get current user exception: " . $e->getMessage());
            Response::unauthorized($e->getMessage());
        }
    }
}
