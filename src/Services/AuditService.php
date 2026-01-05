<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * AUDIT SERVICE v1.0 - CENTRALIZED AUDIT LOGGING
 * ============================================================================
 * Consolidates all audit logging operations across the system
 * Replaces direct audit_logs table inserts in controllers
 * ============================================================================
 */
class AuditService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Log any system action with full context
     * 
     * @param int $userId User performing the action
     * @param string $description Human-readable action description
     * @param string $module Module name (inventory, customers, auth, etc.)
     * @param string $actionType Type of action (create, update, delete, login, etc.)
     * @param string|null $ipAddress IP address (auto-detected if null)
     * @param string|null $userAgent User agent string (auto-detected if null)
     * @return bool Success status
     */
    public function log(
        int $userId,
        string $description,
        string $module,
        string $actionType,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        try {
            // Auto-detect IP and User Agent if not provided
            $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $userAgent = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

            // Truncate to prevent database errors
            $description = substr($description, 0, 500);
            $module = substr($module, 0, 50);
            $actionType = substr($actionType, 0, 50);
            $ipAddress = substr($ipAddress, 0, 45);
            $userAgent = substr($userAgent, 0, 500);

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    user_id, action_description, module, action_type,
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $result = $stmt->execute([
                $userId,
                $description,
                $module,
                $actionType,
                $ipAddress,
                $userAgent
            ]);

            // Development logging
            if (($_ENV['APP_ENV'] ?? 'production') !== 'production') {
                error_log(sprintf(
                    "[AUDIT] User %d | %s | %s.%s",
                    $userId,
                    $description,
                    $module,
                    $actionType
                ));
            }

            return $result;
        } catch (Exception $e) {
            // Silent fail - don't break application
            error_log("AuditService error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log authentication events (login/logout)
     */
    public function logAuth(int $userId, string $action, bool $success = true): bool
    {
        $description = match ($action) {
            'login' => $success ? 'User logged in successfully' : 'Failed login attempt',
            'logout' => 'User logged out',
            'password_change' => 'Password changed successfully',
            'token_refresh' => 'JWT token refreshed',
            default => $action
        };

        return $this->log(
            $userId,
            $description,
            'auth',
            $success ? $action . '_success' : $action . '_failed'
        );
    }

    /**
     * Log CRUD operations on inventory items
     */
    public function logInventory(
        int $userId,
        string $action,
        string $itemName,
        ?array $details = null
    ): bool {
        $description = match ($action) {
            'create' => "Created item: {$itemName}",
            'update' => "Updated item: {$itemName}",
            'delete' => "Deleted item: {$itemName}",
            'stock_in' => "Stock IN: {$itemName}" . ($details ? " x{$details['quantity']}" : ''),
            'stock_out' => "Stock OUT: {$itemName}" . ($details ? " x{$details['quantity']}" : ''),
            default => "{$action}: {$itemName}"
        };

        if ($details) {
            $description .= ' | ' . json_encode($details);
        }

        return $this->log($userId, $description, 'inventory', $action);
    }

    /**
     * Log purchase order operations
     */
    public function logPurchaseOrder(
        int $userId,
        string $action,
        int $poId,
        ?array $details = null
    ): bool {
        $poNumber = 'PO-' . str_pad($poId, 6, '0', STR_PAD_LEFT);

        $description = match ($action) {
            'create' => "Created {$poNumber}",
            'approve' => "Approved {$poNumber}",
            'receive' => "Goods receipt: {$poNumber}",
            'cancel' => "Cancelled {$poNumber}",
            default => "{$action}: {$poNumber}"
        };

        if ($details) {
            if (isset($details['item_name'])) {
                $description .= " - {$details['item_name']}";
            }
            if (isset($details['quantity'])) {
                $description .= " x{$details['quantity']}";
            }
        }

        return $this->log($userId, $description, 'purchase_orders', $action);
    }

    /**
     * Log sales order operations
     */
    public function logSalesOrder(
        int $userId,
        string $action,
        int $soId,
        ?array $details = null
    ): bool {
        $soNumber = 'SO-' . str_pad($soId, 6, '0', STR_PAD_LEFT);

        $description = match ($action) {
            'create' => "Created {$soNumber}",
            'complete' => "Completed {$soNumber}",
            'cancel' => "Cancelled {$soNumber}",
            default => "{$action}: {$soNumber}"
        };

        if ($details && isset($details['customer_name'])) {
            $description .= " for {$details['customer_name']}";
        }

        return $this->log($userId, $description, 'sales_orders', $action);
    }

    /**
     * Log customer/supplier operations
     */
    public function logEntity(
        int $userId,
        string $entityType,
        string $action,
        string $entityName,
        ?int $entityId = null
    ): bool {
        $description = match ($action) {
            'create' => "Created {$entityType}: {$entityName}",
            'update' => "Updated {$entityType}: {$entityName}",
            'delete' => "Deleted {$entityType}: {$entityName}",
            default => "{$action} {$entityType}: {$entityName}"
        };

        if ($entityId) {
            $description .= " (ID: {$entityId})";
        }

        return $this->log(
            $userId,
            $description,
            $entityType === 'customer' ? 'customers' : 'suppliers',
            $action
        );
    }

    /**
     * Log security events (account lockouts, suspicious activity)
     */
    public function logSecurity(
        int $userId,
        string $event,
        string $severity = 'medium'
    ): bool {
        $description = "[{$severity}] {$event}";

        return $this->log($userId, $description, 'security', 'security_event');
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserLogs(int $userId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
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
        } catch (Exception $e) {
            error_log("Get user logs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get audit logs for a specific module
     */
    public function getModuleLogs(string $module, int $limit = 100): array
    {
        try {
            $stmt = $this->db->prepare("
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
        } catch (Exception $e) {
            error_log("Get module logs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all audit logs with filters
     */
    public function getFilteredLogs(array $filters = [], int $limit = 100): array
    {
        try {
            $sql = "
                SELECT 
                    a.*,
                    u.username,
                    u.name as user_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['user_id'])) {
                $sql .= " AND a.user_id = ?";
                $params[] = $filters['user_id'];
            }

            if (!empty($filters['module'])) {
                $sql .= " AND a.module = ?";
                $params[] = $filters['module'];
            }

            if (!empty($filters['action_type'])) {
                $sql .= " AND a.action_type = ?";
                $params[] = $filters['action_type'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(a.created_at) >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(a.created_at) <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY a.created_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get filtered logs error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cleanup old audit logs (call via CRON)
     * Deletes logs older than retention period
     */
    public function cleanup(int $retentionDays = 365): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM audit_logs
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$retentionDays]);
            $deleted = $stmt->rowCount();

            if ($deleted > 0) {
                error_log("🗑️ Audit cleanup: {$deleted} old records deleted");
            }

            return $deleted;
        } catch (Exception $e) {
            error_log("Audit cleanup error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get audit statistics
     */
    public function getStatistics(int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as active_users,
                    COUNT(DISTINCT module) as active_modules,
                    module,
                    COUNT(*) as module_count
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY module
                ORDER BY module_count DESC
            ");

            $stmt->execute([$days]);
            $moduleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(DISTINCT user_id) as active_users
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");

            $stmt->execute([$days]);
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'period_days' => $days,
                'total_actions' => (int)$overall['total_actions'],
                'active_users' => (int)$overall['active_users'],
                'by_module' => $moduleStats
            ];
        } catch (Exception $e) {
            error_log("Get audit statistics error: " . $e->getMessage());
            return [
                'period_days' => $days,
                'total_actions' => 0,
                'active_users' => 0,
                'by_module' => []
            ];
        }
    }
}
