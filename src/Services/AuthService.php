<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Utils\JWT;

/**
 * Authentication Service
 * Handles all authentication business logic
 * ISO/IEC 25010: Security & Functional Suitability
 */
class AuthService
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    /**
     * Login user with username and password
     * 
     * @param string $username User's username
     * @param string $password User's plain password
     * @return array|null Returns user data with token, or null on failure
     */
    public function login(string $username, string $password): ?array
    {
        // Validate inputs
        if (empty($username) || empty($password)) {
            return null;
        }

        // Find user by username
        $user = $this->userRepo->findByUsername($username);

        if (!$user) {
            // User not found - log failed attempt
            error_log("Login attempt failed: User '$username' not found");
            return null;
        }

        // FIXED: Use bcrypt verification instead of SHA-256
        if (!password_verify($password, $user['password_hash'])) {
            // Invalid password - log failed attempt
            error_log("Login attempt failed: Invalid password for user '$username'");
            $this->userRepo->logAudit($user['user_id'], "Failed login attempt");
            return null;
        }

        // Generate JWT token
        $tokenPayload = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'role_id' => $user['role_id']
        ];

        $token = JWT::generate($tokenPayload);

        // Log successful login
        $this->userRepo->logAudit($user['user_id'], "Successful login");

        // Return user data without password
        unset($user['password_hash']);

        return [
            'user' => $user,
            'token' => $token,
            'expires_in' => (int)($_ENV['JWT_EXPIRATION'] ?? 3600)
        ];
    }

    /**
     * Validate JWT token
     * 
     * @param string $token JWT token
     * @return object|null Returns decoded token data or null
     */
    public function validateToken(string $token): ?object
    {
        return JWT::validate($token);
    }

    /**
     * Logout user (log audit trail)
     * 
     * @param int $userId User ID
     * @return bool Success status
     */
    public function logout(int $userId): bool
    {
        return $this->userRepo->logAudit($userId, "User logged out");
    }

    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        // Get user data
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            return false;
        }

        // Find raw user data for password verification
        $userData = $this->userRepo->findByUsername($user->username);

        // FIXED: Use bcrypt verification
        if (!password_verify($currentPassword, $userData['password_hash'])) {
            error_log("Password change failed: Invalid current password for user ID $userId");
            return false;
        }

        // Hash new password with bcrypt
        $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        // Update password
        $success = $this->userRepo->update($userId, [
            'password_hash' => $hashedNewPassword
        ]);

        if ($success) {
            $this->userRepo->logAudit($userId, "Password changed successfully");
        }

        return $success;
    }

    /**
     * Check if user has required role
     * 
     * @param object $user Decoded JWT user object
     * @param array $allowedRoles Array of allowed role names
     * @return bool True if user has required role
     */
    public function hasRole(object $user, array $allowedRoles): bool
    {
        return in_array($user->role, $allowedRoles);
    }
}
