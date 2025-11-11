<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AuthService;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\JWT;

/**
 * Authentication Controller
 * Handles authentication API endpoints
 * ISO/IEC 25010: Security, Usability
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
     * User login endpoint
     */
    public function login(): void
    {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['username']) || !isset($input['password'])) {
            Response::error('Username and password are required', null, 400);
            return;
        }

        // Attempt login
        $result = $this->authService->login($input['username'], $input['password']);

        if ($result) {
            Response::success($result, 'Login successful', 200);
        } else {
            Response::error('Invalid username or password', null, 401);
        }
    }

    /**
     * POST /api/auth/logout
     * User logout endpoint
     */
    public function logout(): void
    {
        // Get user from token
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

        // Log logout
        $this->authService->logout($user->user_id);

        Response::success(null, 'Logout successful', 200);
    }

    /**
     * GET /api/auth/me
     * Get current user profile
     */
    public function getCurrentUser(): void
    {
        // Get user from token
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
     * Change user password
     */
    public function changePassword(): void
    {
        // Get user from token
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

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['current_password']) || !isset($input['new_password'])) {
            Response::error('Current and new password are required', null, 400);
            return;
        }

        // Validate new password strength
        if (strlen($input['new_password']) < 8) {
            Response::error('New password must be at least 8 characters', null, 400);
            return;
        }

        // Change password
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

    /**
     * POST /api/auth/verify-token
     * Verify JWT token validity
     */
    public function verifyToken(): void
    {
        // Get user from token
        $token = JWT::getFromHeader();

        if (!$token) {
            Response::error('No token provided', null, 401);
            return;
        }

        $user = JWT::validate($token);

        if (!$user) {
            Response::error('Invalid or expired token', null, 401);
            return;
        }

        Response::success([
            'valid' => true,
            'user' => $user
        ], 'Token is valid', 200);
    }
}
