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

        if (empty($rawInput)) {
            error_log("❌ Empty request body");
            Response::error('Request body is empty', null, 400);
            return;
        }

        $input = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ JSON decode error: " . json_last_error_msg());
            Response::error('Invalid JSON input: ' . json_last_error_msg(), null, 400);
            return;
        }

        error_log("📊 Parsed input keys: " . implode(', ', array_keys($input)));

        // Validate input
        if (!isset($input['username']) || !isset($input['password'])) {
            error_log("❌ Missing credentials in input");
            Response::error('Username and password are required', null, 400);
            return;
        }

        $username = trim($input['username']);
        $password = $input['password'];

        error_log("👤 Login attempt for username: " . $username);

        // Attempt login
        try {
            $result = $this->authService->login($username, $password);

            if ($result && isset($result['user']) && isset($result['token'])) {
                error_log("✅ Login successful for: " . $username);
                error_log("✅ Token generated: " . substr($result['token'], 0, 20) . "...");
                error_log("✅ User role: " . ($result['user']['role_name'] ?? 'unknown'));

                Response::success($result, 'Login successful', 200);
                error_log("✅ Response sent successfully");
            } else {
                error_log("❌ AuthService returned invalid result structure");
                error_log("Result: " . json_encode($result));
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
}
