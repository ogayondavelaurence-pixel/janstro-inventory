<?php

namespace Janstro\InventorySystem\Middleware;

use Janstro\InventorySystem\Utils\JWT;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * AUTHENTICATION MIDDLEWARE v2.0 - QUERY PARAM TOKEN SUPPORT
 * ============================================================================
 * FIXES:
 * ✅ Added query parameter token support for PDF downloads
 * ✅ Maintains backward compatibility with Authorization header
 * ✅ Proper security validation for both methods
 * ============================================================================
 */
class AuthMiddleware
{
    private static bool $debugMode = false;

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
        self::initDebugMode();

        if (self::$debugMode) {
            error_log("[{$level}] AuthMiddleware: {$message}");
        }
    }

    /**
     * ========================================================================
     * AUTHENTICATE - WITH QUERY PARAM TOKEN SUPPORT (v2.0)
     * ========================================================================
     * Now supports tokens from:
     * 1. Authorization: Bearer <token> (priority)
     * 2. Query parameter: ?token=<token> (fallback)
     * 
     * @return object|null User object if authenticated, null otherwise
     */
    public static function authenticate(): ?object
    {
        self::log("Authentication attempt - URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
        self::log("Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

        // ✅ PRIORITY 1: Try Authorization header first
        $token = JWT::getFromHeader();

        if ($token) {
            self::log("Token found in Authorization header");
        } else {
            // ✅ PRIORITY 2: Fall back to query parameter
            $token = $_GET['token'] ?? null;

            if ($token) {
                self::log("Token found in query parameter");
            } else {
                self::log("Authentication failed: No token provided", 'WARNING');
                Response::unauthorized('No token provided');
                return null;
            }
        }

        // Validate token (works for both header and query param)
        $user = JWT::validate($token);

        if (!$user) {
            self::log("Authentication failed: Invalid or expired token", 'WARNING');
            Response::unauthorized('Invalid or expired token');
            return null;
        }

        // Log successful authentication
        $username = $user->username ?? 'unknown';
        $role = $user->role ?? 'unknown';
        self::log("Authentication successful - User: {$username}, Role: {$role}");

        return $user;
    }

    /**
     * Require user to have specific role(s)
     * 
     * @param array $allowedRoles Array of allowed role names (case-insensitive)
     * @return object|null User object if authorized, null otherwise
     */
    public static function requireRole(array $allowedRoles): ?object
    {
        // First authenticate the user
        $user = self::authenticate();

        if (!$user) {
            return null;
        }

        // Normalize roles for case-insensitive comparison
        $userRole = strtolower($user->role ?? '');
        $normalizedAllowed = array_map('strtolower', $allowedRoles);

        self::log("RBAC Check - User role: '{$userRole}' vs allowed: [" . implode(', ', $normalizedAllowed) . "]");

        // Check if user's role is in allowed roles
        if (!in_array($userRole, $normalizedAllowed, true)) {
            self::log("RBAC Denied - User role '{$userRole}' not authorized", 'WARNING');

            Response::forbidden(
                "Insufficient permissions. Required role: " .
                    implode(' or ', array_map('strtoupper', $allowedRoles))
            );
            return null;
        }

        self::log("RBAC Granted - User has required role");
        return $user;
    }

    /**
     * Check if user has any of the specified permissions
     * 
     * @param array $permissions Array of permission names
     * @return bool True if user has any of the permissions
     */
    public static function hasPermission(array $permissions): bool
    {
        $user = self::authenticate();

        if (!$user) {
            return false;
        }

        // Get user permissions (if stored in token)
        $userPermissions = $user->permissions ?? [];

        // Check if user has any of the required permissions
        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current authenticated user without enforcing authentication
     * 
     * @return object|null User object or null if not authenticated
     */
    public static function getCurrentUser(): ?object
    {
        // Try header first
        $token = JWT::getFromHeader();

        // Fall back to query param
        if (!$token) {
            $token = $_GET['token'] ?? null;
        }

        if (!$token) {
            return null;
        }

        return JWT::validate($token);
    }

    /**
     * Check if request is authenticated
     * 
     * @return bool True if authenticated
     */
    public static function isAuthenticated(): bool
    {
        return self::getCurrentUser() !== null;
    }
}
