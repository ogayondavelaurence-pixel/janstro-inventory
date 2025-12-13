<?php

namespace Janstro\InventorySystem\Middleware;

use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Utils\Response;

class AuthMiddleware
{
    /* Authenticate user via JWT */
    public static function authenticate(): ?object
    {
        error_log("========================================");
        error_log("🔐 AuthMiddleware::authenticate() called");
        error_log("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        error_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

        $token = JWT::getFromHeader();

        if (!$token) {
            error_log("❌ No token provided in request");
            Response::unauthorized('No token provided');
            return null;
        }

        error_log("✅ Token found in header");

        $user = JWT::validate($token);

        if (!$user) {
            error_log("❌ Token validation failed");
            Response::unauthorized('Invalid or expired token');
            return null;
        }

        error_log("✅ User authenticated: " . $user->username . " (role: " . $user->role . ")");
        error_log("========================================");

        return $user;
    }

    /* Require user to have a specific role */
    public static function requireRole(array $allowedRoles): ?object
    {
        $user = self::authenticate();

        if (!$user) {
            return null;
        }

        // Normalize role to lowercase for comparison
        $userRole = strtolower($user->role ?? '');
        $normalizedAllowed = array_map('strtolower', $allowedRoles);

        error_log("🔒 RBAC Check: User role '$userRole' vs allowed [" . implode(',', $normalizedAllowed) . "]");

        if (!in_array($userRole, $normalizedAllowed)) {
            error_log("❌ RBAC Denied: User role '$userRole' not in [" . implode(',', $normalizedAllowed) . "]");
            Response::forbidden('Insufficient permissions for role: ' . strtoupper($userRole));
            return null;
        }

        error_log("✅ RBAC Granted: User has required role");
        return $user;
    }
}
