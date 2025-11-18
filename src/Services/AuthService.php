<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Utils\JWT;

/**
 * FIXED Authentication Service
 * Handles all authentication with proper error logging
 */
class AuthService
{
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    /**
     * FIXED Login with detailed error logging
     */
    public function login(string $username, string $password): ?array
    {
        // Validate inputs
        if (empty($username) || empty($password)) {
            error_log("❌ Login failed: Empty username or password");
            return null;
        }

        // CRITICAL: Log the attempt
        error_log("🔐 Login attempt for username: " . $username);

        // Find user by username
        $user = $this->userRepo->findByUsername($username);

        if (!$user) {
            error_log("❌ Login failed: User '$username' not found in database");
            return null;
        }

        // CRITICAL: Log what we got from database
        error_log("✅ User found: " . $username);
        error_log("📊 User data: " . json_encode([
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role_name'] ?? 'unknown',
            'status' => $user['status'],
            'has_password_hash' => !empty($user['password_hash'])
        ]));

        // CRITICAL: Verify password with detailed logging
        $passwordValid = password_verify($password, $user['password_hash']);

        error_log("🔑 Password verification result: " . ($passwordValid ? 'SUCCESS' : 'FAILED'));

        if (!$passwordValid) {
            // CRITICAL: Log the exact error for debugging
            error_log("❌ Password verification failed for user '$username'");
            error_log("💡 Expected password: admin123 (if default user)");
            error_log("💡 Hash from DB: " . substr($user['password_hash'], 0, 20) . "...");

            // Log failed attempt
            $this->userRepo->logAudit($user['user_id'], "Failed login attempt - invalid password");
            return null;
        }

        // SUCCESS: Generate JWT token
        error_log("✅ Login successful for user: " . $username);

        $tokenPayload = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role_name'],
            'role_id' => $user['role_id'],
            'name' => $user['name'] ?? $user['username']
        ];

        try {
            $token = JWT::generate($tokenPayload);
            error_log("✅ JWT token generated successfully");
        } catch (\Exception $e) {
            error_log("❌ JWT generation failed: " . $e->getMessage());
            return null;
        }

        // Log successful login
        $this->userRepo->logAudit($user['user_id'], "Successful login");

        // Update last_login timestamp
        $this->userRepo->update($user['user_id'], ['last_login' => date('Y-m-d H:i:s')]);

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
     */
    public function validateToken(string $token): ?object
    {
        return JWT::validate($token);
    }

    /**
     * Logout user
     */
    public function logout(int $userId): bool
    {
        return $this->userRepo->logAudit($userId, "User logged out");
    }

    /**
     * Change user password
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepo->findById($userId);

        if (!$user) {
            return false;
        }

        $userData = $this->userRepo->findByUsername($user->username);

        if (!password_verify($currentPassword, $userData['password_hash'])) {
            error_log("Password change failed: Invalid current password for user ID $userId");
            return false;
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

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
     */
    public function hasRole(object $user, array $allowedRoles): bool
    {
        return in_array($user->role, $allowedRoles);
    }
}
