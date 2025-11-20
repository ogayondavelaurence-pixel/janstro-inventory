<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\JWT;

/**
 * FIXED Authentication Controller v2.0
 * Enhanced logging for debugging 401 errors
 */
class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * POST /api/auth/login
     * CRITICAL FIX: Enhanced error logging + proper JSON response
     */
    public function login(): void
    {
        error_log("========================================");
        error_log("🔐 AuthController::login() called");
        error_log("Method: " . $_SERVER['REQUEST_METHOD']);
        error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

        // Get JSON input
        $rawInput = file_get_contents('php://input');
        error_log("📥 Raw input length: " . strlen($rawInput));

        $input = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ JSON decode error: " . json_last_error_msg());
            Response::error('Invalid JSON input', null, 400);
            return;
        }

        error_log("📊 Parsed input: " . json_encode($input));

        // Validate input
        if (!isset($input['username']) || !isset($input['password'])) {
            error_log("❌ Missing credentials in input");
            Response::error('Username and password are required', null, 400);
            return;
        }

        $username = $input['username'];
        error_log("👤 Login attempt for username: " . $username);

        // Attempt login
        try {
            $result = $this->authService->login($username, $input['password']);

            if ($result) {
                error_log("✅ AuthService returned success");
                error_log("📊 Result structure: " . json_encode(array_keys($result)));

                // CRITICAL: Ensure proper response structure
                Response::success($result, 'Login successful', 200);
                error_log("✅ Response sent successfully");
            } else {
                error_log("❌ AuthService returned null/false");
                Response::error('Invalid username or password', null, 401);
            }
        } catch (\Exception $e) {
            error_log("🚨 Login exception: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            Response::error('Login failed: ' . $e->getMessage(), null, 500);
        }

        error_log("========================================");
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        $token = JWT::getFromHeader();

        if (!$token) {
            Response::unauthorized('No token provided');
            return;
        }

        $user = JWT::validate($token);

        if (!$user) {
            Response::unauthorized('Invalid token');
            return;
        }

        $this->authService->logout($user->user_id);
        Response::success(null, 'Logout successful', 200);
    }

    /**
     * GET /api/auth/me
     */
    public function getCurrentUser(): void
    {
        $token = JWT::getFromHeader();

        if (!$token) {
            Response::unauthorized('No token provided');
            return;
        }

        $user = JWT::validate($token);

        if (!$user) {
            Response::unauthorized('Invalid token');
            return;
        }

        Response::success($user, 'User profile retrieved', 200);
    }

    /**
     * POST /api/auth/change-password
     */
    public function changePassword(): void
    {
        $token = JWT::getFromHeader();

        if (!$token) {
            Response::unauthorized('No token provided');
            return;
        }

        $user = JWT::validate($token);

        if (!$user) {
            Response::unauthorized('Invalid token');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['current_password']) || !isset($input['new_password'])) {
            Response::error('Current and new password are required', null, 400);
            return;
        }

        if (strlen($input['new_password']) < 8) {
            Response::error('New password must be at least 8 characters', null, 400);
            return;
        }

        $success = $this->authService->changePassword(
            $user->user_id,
            $input['current_password'],
            $input['new_password']
        );

        if ($success) {
            Response::success(null, 'Password changed successfully', 200);
        } else {
            Response::error('Current password is incorrect', null, 400);
        }
    }
}
