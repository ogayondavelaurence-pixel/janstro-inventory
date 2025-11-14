<?php

declare(strict_types=1);

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Utils\Security;

class AuthService
{
    private UserRepository $userRepo;

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION   = 900;

    public function __construct()
    {
        $this->userRepo = new UserRepository();
    }

    public function login(string $username, string $password): ?array
    {
        if (trim($username) === '' || trim($password) === '') {
            Security::logSecurityEvent('LOGIN_FAILED', [
                'reason' => 'Empty credentials',
                'ip'     => Security::getClientIp()
            ]);
            return null;
        }

        $clientIp = Security::getClientIp();

        if (!Security::checkRateLimit("login_$clientIp", self::MAX_LOGIN_ATTEMPTS, self::LOCKOUT_DURATION)) {
            Security::logSecurityEvent('LOGIN_RATE_LIMITED', [
                'username' => $username,
                'ip'       => $clientIp
            ]);
            sleep(2);
            return null;
        }

        $user = $this->userRepo->findByUsername($username);
        if (!$user) {
            Security::logSecurityEvent('LOGIN_FAILED', [
                'reason'   => 'User not found',
                'username' => $username,
                'ip'       => $clientIp
            ]);
            sleep(2);
            return null;
        }

        if (($user['status'] ?? '') !== 'active') {
            Security::logSecurityEvent('LOGIN_FAILED', [
                'reason'   => 'Account inactive',
                'username' => $username,
                'ip'       => $clientIp
            ]);
            return null;
        }

        if (!password_verify($password, (string)$user['password_hash'])) {
            $this->userRepo->logAudit((int)$user['user_id'], "Failed login attempt from IP: $clientIp");

            Security::logSecurityEvent('LOGIN_FAILED', [
                'reason'   => 'Invalid password',
                'username' => $username,
                'ip'       => $clientIp
            ]);
            sleep(2);
            return null;
        }

        Security::resetRateLimit("login_$clientIp");

        $tokenPayload = [
            'user_id'    => $user['user_id'],
            'username'   => $user['username'],
            'role'       => $user['role_name'] ?? null,
            'role_id'    => $user['role_id'] ?? null,
            'ip'         => $clientIp,
            'session_id' => bin2hex(random_bytes(16))
        ];

        $token = JWT::generate($tokenPayload);

        $this->userRepo->logAudit((int)$user['user_id'], "Successful login from IP: $clientIp");

        Security::logSecurityEvent('LOGIN_SUCCESS', [
            'username' => $username,
            'ip'       => $clientIp
        ]);

        unset($user['password_hash']);

        return [
            'user'       => $user,
            'token'      => $token,
            'expires_in' => (int)($_ENV['JWT_EXPIRATION'] ?? 3600)
        ];
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            return false;
        }

        $userData = $this->userRepo->findByUsername($user['username']);
        if (!$userData) {
            return false;
        }

        if (!password_verify($currentPassword, (string)$userData['password_hash'])) {
            Security::logSecurityEvent('PASSWORD_CHANGE_FAILED', [
                'user_id' => $userId,
                'reason'  => 'Invalid current password'
            ]);
            return false;
        }

        if (!Security::validatePasswordStrength($newPassword)) {
            Security::logSecurityEvent('PASSWORD_CHANGE_FAILED', [
                'user_id' => $userId,
                'reason'  => 'Weak password'
            ]);
            return false;
        }

        if (password_verify($newPassword, (string)$userData['password_hash'])) {
            Security::logSecurityEvent('PASSWORD_CHANGE_FAILED', [
                'user_id' => $userId,
                'reason'  => 'Password reuse'
            ]);
            return false;
        }

        $hashedNewPassword = Security::hashPassword($newPassword);

        $success = $this->userRepo->update($userId, [
            'password_hash' => $hashedNewPassword
        ]);

        if ($success) {
            $this->userRepo->logAudit($userId, "Password changed successfully");

            Security::logSecurityEvent('PASSWORD_CHANGED', [
                'user_id' => $userId
            ]);
        }

        return $success;
    }

    public function validateToken(string $token): ?object
    {
        $decoded = JWT::validate($token);
        if (!$decoded) {
            return null;
        }

        $currentIp = Security::getClientIp();

        if (isset($decoded->ip) && $decoded->ip !== $currentIp) {
            Security::logSecurityEvent('TOKEN_IP_MISMATCH', [
                'user_id'    => $decoded->user_id ?? 'unknown',
                'token_ip'   => $decoded->ip,
                'current_ip' => $currentIp
            ]);
            // Enable strict mode if desired:
            // return null;
        }

        return $decoded;
    }

    public function logout(int $userId): bool
    {
        Security::logSecurityEvent('LOGOUT', [
            'user_id' => $userId,
            'ip'      => Security::getClientIp()
        ]);

        $this->userRepo->logAudit(
            $userId,
            "User logged out from IP: " . Security::getClientIp()
        );

        return true;
    }

    public function hasRole(object|array $user, array $allowedRoles): bool
    {
        $role = is_object($user)
            ? ($user->role ?? $user->role_name ?? null)
            : ($user['role'] ?? $user['role_name'] ?? null);

        return in_array($role, $allowedRoles, true);
    }
}
