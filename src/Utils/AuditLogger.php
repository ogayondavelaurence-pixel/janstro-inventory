<?php

namespace Janstro\InventorySystem\Utils;

use Janstro\InventorySystem\Config\Database;
use PDO;

/**
 * ============================================================================
 * AUDIT LOGGER - Production Ready v1.0
 * ============================================================================
 * Centralized audit logging for all system actions
 * 
 * File: src/Utils/AuditLogger.php
 * ============================================================================
 */
class AuditLogger
{
    /**
     * Log an audit event
     * 
     * @param int $userId User ID performing the action
     * @param string $actionDescription Human-readable description
     * @param string $module Module name (customers, suppliers, inventory, etc.)
     * @param string $actionType Action type (create, update, delete, login, etc.)
     * @param string|null $ipAddress IP address (auto-detected if null)
     * @param string|null $userAgent User agent (auto-detected if null)
     * @return bool Success status
     */
    public static function log(
        int $userId,
        string $actionDescription,
        string $module = 'system',
        string $actionType = 'action',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        try {
            // Auto-detect IP and User Agent if not provided
            if ($ipAddress === null) {
                $ipAddress = Security::getClientIP();
            }

            if ($userAgent === null) {
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            }

            // Truncate long values to prevent database errors
            $actionDescription = substr($actionDescription, 0, 500);
            $module = substr($module, 0, 50);
            $actionType = substr($actionType, 0, 50);
            $ipAddress = substr($ipAddress, 0, 45);
            $userAgent = substr($userAgent, 0, 255);

            $db = Database::connect();

            $stmt = $db->prepare("
                INSERT INTO audit_logs (
                    user_id,
                    action_description,
                    module,
                    action_type,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $userId,
                $actionDescription,
                $module,
                $actionType,
                $ipAddress,
                $userAgent
            ]);

            // Log to file in development
            if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
                error_log(sprintf(
                    "[AUDIT] User %d | %s | %s.%s | IP: %s",
                    $userId,
                    $actionDescription,
                    $module,
                    $actionType,
                    $ipAddress
                ));
            }

            return $result;
        } catch (\Exception $e) {
            // Don't let audit logging break the application
            error_log("❌ AuditLogger failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log user authentication event
     * 
     * @param int $userId User ID
     * @param bool $success Login success or failure
     * @return bool
     */
    public static function logAuth(int $userId, bool $success = true): bool
    {
        $description = $success
            ? 'User logged in successfully'
            : 'Failed login attempt';

        return self::log(
            $userId,
            $description,
            'auth',
            $success ? 'login_success' : 'login_failed'
        );
    }

    /**
     * Log data access event
     * 
     * @param int $userId User ID
     * @param string $resource Resource accessed (e.g., "customer ID 5")
     * @param string $module Module name
     * @return bool
     */
    public static function logAccess(
        int $userId,
        string $resource,
        string $module = 'system'
    ): bool {
        return self::log(
            $userId,
            "Accessed: {$resource}",
            $module,
            'view'
        );
    }

    /**
     * Log data modification event
     * 
     * @param int $userId User ID
     * @param string $resource Resource modified
     * @param string $module Module name
     * @param string $actionType create/update/delete
     * @return bool
     */
    public static function logModification(
        int $userId,
        string $resource,
        string $module,
        string $actionType = 'update'
    ): bool {
        return self::log(
            $userId,
            "Modified: {$resource}",
            $module,
            $actionType
        );
    }

    /**
     * Log security event (suspicious activity)
     * 
     * @param int $userId User ID (0 for anonymous)
     * @param string $eventDescription Event description
     * @param string $severity low/medium/high/critical
     * @return bool
     */
    public static function logSecurity(
        int $userId,
        string $eventDescription,
        string $severity = 'medium'
    ): bool {
        // Also log to security.log
        Security::logSecurityEvent(
            'SECURITY_EVENT',
            $eventDescription,
            strtoupper($severity)
        );

        return self::log(
            $userId,
            $eventDescription,
            'security',
            'security_event'
        );
    }

    /**
     * Log error event
     * 
     * @param int $userId User ID
     * @param string $errorMessage Error message
     * @param string $module Module where error occurred
     * @return bool
     */
    public static function logError(
        int $userId,
        string $errorMessage,
        string $module = 'system'
    ): bool {
        error_log("[ERROR] User {$userId} in {$module}: {$errorMessage}");

        return self::log(
            $userId,
            "Error: {$errorMessage}",
            $module,
            'error'
        );
    }

    /**
     * Get recent audit logs for a user
     * 
     * @param int $userId User ID
     * @param int $limit Number of records
     * @return array Audit log entries
     */
    public static function getUserLogs(int $userId, int $limit = 50): array
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT 
                    log_id,
                    action_description,
                    module,
                    action_type,
                    ip_address,
                    created_at
                FROM audit_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("❌ Failed to fetch user logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent logs for a specific module
     * 
     * @param string $module Module name
     * @param int $limit Number of records
     * @return array Audit log entries
     */
    public static function getModuleLogs(string $module, int $limit = 100): array
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                SELECT 
                    a.log_id,
                    a.user_id,
                    a.action_description,
                    a.action_type,
                    a.ip_address,
                    a.created_at,
                    u.username,
                    u.name as user_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.module = ?
                ORDER BY a.created_at DESC
                LIMIT ?
            ");

            $stmt->execute([$module, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("❌ Failed to fetch module logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cleanup old audit logs (retention policy)
     * Call this via cron job monthly
     * 
     * @param int $retentionDays Keep logs for this many days (default: 365)
     * @return int Number of deleted records
     */
    public static function cleanup(int $retentionDays = 365): int
    {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("
                DELETE FROM audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$retentionDays]);
            $deleted = $stmt->rowCount();

            if ($deleted > 0) {
                error_log("🗑️ Audit log cleanup: {$deleted} old records deleted");
            }

            return $deleted;
        } catch (\Exception $e) {
            error_log("❌ Audit log cleanup failed: " . $e->getMessage());
            return 0;
        }
    }
}
