<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

/**
 * ============================================================================
 * NOTIFICATION SERVICE v3.1 - PRODUCTION READY
 * ============================================================================
 * FIXES:
 * ✅ Uses EmailService for all email sending
 * ✅ Fixed nullable parameter deprecation
 * ✅ Enhanced error handling
 * ============================================================================
 */
class NotificationService
{
    private PDO $db;
    private EmailService $emailService;
    private array $config;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->emailService = new EmailService();
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'enabled' => 1,
                'notify_low_stock' => 1,
                'notify_new_order' => 1,
                'notify_po_delivered' => 1
            ];
        } catch (\Exception $e) {
            error_log("NotificationService config error: " . $e->getMessage());
            $this->config = ['enabled' => 0];
        }
    }

    // ========================================================================
    // PURCHASE REQUISITION NOTIFICATIONS
    // ========================================================================

    public function notifyPRCreated(int $prId, array $prData): array
    {
        $results = [];

        try {
            $admins = $this->getAdminEmails();
            $subject = "⚠️ New Purchase Requisition - {$prData['pr_number']} ({$prData['urgency']})";
            $body = $this->buildPRNotificationEmail($prData);

            foreach ($admins as $email) {
                $results[$email] = $this->emailService->send($email, $subject, $body, 'purchase_requisition');
            }

            $this->broadcastInAppNotification(
                ['admin', 'superadmin'],
                'purchase_requisition',
                'New PR Created',
                "PR {$prData['pr_number']}: {$prData['item_name']} ({$prData['quantity']} units) - Urgency: {$prData['urgency']}",
                $prData['urgency'] === 'critical' ? 'critical' : 'high'
            );

            $this->logNotification([
                'type' => 'pr_created',
                'pr_id' => $prId,
                'urgency' => $prData['urgency'],
                'success' => in_array(true, $results)
            ]);
        } catch (\Exception $e) {
            error_log("PR notification error: " . $e->getMessage());
        }

        return $results;
    }

    public function notifyShortageResolved(int $salesOrderId, array $data): array
    {
        $results = [];

        try {
            $staff = $this->getAllStaffEmails();
            $subject = "✅ Stock Shortage Resolved - SO #{$salesOrderId}";
            $body = $this->buildShortageResolvedEmail($data);

            foreach ($staff as $email) {
                $results[$email] = $this->emailService->send($email, $subject, $body, 'shortage_resolved');
            }

            $this->broadcastInAppNotification(
                ['staff', 'admin', 'superadmin'],
                'inventory',
                'Shortage Resolved',
                "Stock now available for {$data['customer_name']}: {$data['item_name']} (Stock: {$data['new_stock']})",
                'medium'
            );
        } catch (\Exception $e) {
            error_log("Shortage notification error: " . $e->getMessage());
        }

        return $results;
    }

    // ========================================================================
    // INVENTORY NOTIFICATIONS
    // ========================================================================

    public function notifyLowStock(array $items): array
    {
        if (!$this->config['notify_low_stock'] || empty($items)) {
            return ['skipped' => true];
        }

        $results = [];

        try {
            $admins = $this->getAdminEmails();
            $subject = "⚠️ Low Stock Alert - " . count($items) . " Items";
            $body = $this->buildLowStockEmail($items);

            foreach ($admins as $email) {
                $results[$email] = $this->emailService->send($email, $subject, $body, 'low_stock');
            }

            $this->broadcastInAppNotification(
                ['staff', 'admin', 'superadmin'],
                'inventory',
                'Low Stock Alert',
                count($items) . ' items below reorder level',
                'warning'
            );

            $this->logNotification([
                'type' => 'low_stock',
                'item_count' => count($items),
                'success' => in_array(true, $results)
            ]);
        } catch (\Exception $e) {
            error_log("Low stock notification error: " . $e->getMessage());
        }

        return $results;
    }

    public function notifyNewOrder(string $orderType, int $orderId, array $orderData): array
    {
        if (!$this->config['notify_new_order']) {
            return ['skipped' => true];
        }

        $results = [];

        try {
            $recipients = $orderType === 'purchase_order'
                ? $this->getAdminEmails()
                : $this->getAllStaffEmails();

            $subject = $orderType === 'purchase_order'
                ? "📦 New Purchase Order #PO-{$orderId}"
                : "🛒 New Sales Order #SO-{$orderId}";

            $body = $this->buildOrderNotificationEmail($orderType, $orderId, $orderData);

            foreach ($recipients as $email) {
                $results[$email] = $this->emailService->send($email, $subject, $body, 'new_order');
            }

            $this->logNotification([
                'type' => 'new_order',
                'order_type' => $orderType,
                'order_id' => $orderId,
                'success' => in_array(true, $results)
            ]);
        } catch (\Exception $e) {
            error_log("New order notification error: " . $e->getMessage());
        }

        return $results;
    }

    public function notifyPODelivered(int $poId, array $poData): array
    {
        if (!$this->config['notify_po_delivered']) {
            return ['skipped' => true];
        }

        $results = [];

        try {
            $admins = $this->getAdminEmails();
            $subject = "✅ PO Delivered - #PO-{$poId}";
            $body = $this->buildPODeliveredEmail($poId, $poData);

            foreach ($admins as $email) {
                $results[$email] = $this->emailService->send($email, $subject, $body, 'po_delivered');
            }

            $this->broadcastInAppNotification(
                ['admin', 'superadmin'],
                'purchase_order',
                'PO Delivered',
                "PO #{$poId}: {$poData['item_name']} x {$poData['quantity']} received. Stock updated to {$poData['new_stock']}",
                'medium'
            );

            $this->logNotification([
                'type' => 'po_delivered',
                'po_id' => $poId,
                'success' => in_array(true, $results)
            ]);
        } catch (\Exception $e) {
            error_log("PO delivered notification error: " . $e->getMessage());
        }

        return $results;
    }

    // ========================================================================
    // IN-APP NOTIFICATIONS
    // ========================================================================

    public function createInAppNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        string $priority = 'medium'
    ): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO in_app_notifications 
                (user_id, type, title, message, priority, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([$userId, $type, $title, $message, $priority]);
        } catch (\Exception $e) {
            error_log("In-app notification error: " . $e->getMessage());
            return false;
        }
    }

    public function broadcastInAppNotification(
        array $roles,
        string $type,
        string $title,
        string $message,
        string $priority = 'medium'
    ): int {
        try {
            $placeholders = str_repeat('?,', count($roles) - 1) . '?';
            $stmt = $this->db->prepare("
                SELECT user_id FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE r.role_name IN ($placeholders)
                AND u.status = 'active'
            ");
            $stmt->execute($roles);
            $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            foreach ($users as $userId) {
                if ($this->createInAppNotification($userId, $type, $title, $message, $priority)) {
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            error_log("Broadcast notification error: " . $e->getMessage());
            return 0;
        }
    }

    public function getUnreadNotifications(int $userId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM in_app_notifications
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE in_app_notifications
                SET is_read = 1, read_at = NOW()
                WHERE notification_id = ? AND user_id = ?
            ");
            return $stmt->execute([$notificationId, $userId]);
        } catch (\Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // EMAIL TEMPLATES
    // ========================================================================

    private function buildPRNotificationEmail(array $prData): string
    {
        $urgencyColor = $prData['urgency'] === 'critical' ? '#dc3545' : '#ff9800';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8080/janstro-inventory/frontend';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,{$urgencyColor},#d32f2f);padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>⚠️ Purchase Requisition</h1></div><div style='padding:20px;background:#f5f5f5;'><table style='width:100%;border-collapse:collapse;'><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>PR Number:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$prData['pr_number']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Item:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$prData['item_name']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Quantity:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$prData['quantity']} units</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Urgency:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'><span style='color:{$urgencyColor};font-weight:bold;'>{$prData['urgency']}</span></td></tr></table><div style='margin-top:20px;padding:15px;background:#fff;border-radius:8px;'><p><strong>Action Required:</strong> Review and approve this requisition.</p><p><a href='{$appUrl}/purchase-requisitions.html' style='display:inline-block;padding:10px 20px;background:#667eea;color:white;text-decoration:none;border-radius:5px;'>View in System</a></p></div></div></body></html>";
    }

    private function buildShortageResolvedEmail(array $data): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,#4caf50,#2e7d32);padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>✅ Stock Shortage Resolved</h1></div><div style='padding:20px;background:#f5f5f5;'><p><strong>Stock is now available for:</strong></p><table style='width:100%;border-collapse:collapse;'><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Customer:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$data['customer_name']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Item:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$data['item_name']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>New Stock:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'><strong style='color:#4caf50;'>{$data['new_stock']} units</strong></td></tr></table></div></body></html>";
    }

    private function buildLowStockEmail(array $items): string
    {
        $rows = '';
        foreach ($items as $item) {
            $rows .= "<tr><td style='padding:8px;border-bottom:1px solid #ddd;'>{$item['name']}</td><td style='padding:8px;border-bottom:1px solid #ddd;text-align:center;'><strong style='color:#dc3545;'>{$item['quantity']}</strong></td><td style='padding:8px;border-bottom:1px solid #ddd;text-align:center;'>{$item['reorder_level']}</td></tr>";
        }

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,#ff9800,#f57c00);padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>⚠️ Low Stock Alert</h1></div><div style='padding:20px;background:#f5f5f5;'><p><strong>" . count($items) . " items</strong> are below reorder level:</p><table style='width:100%;border-collapse:collapse;background:white;'><thead><tr style='background:#333;color:white;'><th style='padding:10px;text-align:left;'>Item</th><th style='padding:10px;text-align:center;'>Current</th><th style='padding:10px;text-align:center;'>Reorder</th></tr></thead><tbody>{$rows}</tbody></table></div></body></html>";
    }

    private function buildPODeliveredEmail(int $poId, array $poData): string
    {
        $resolvedText = isset($poData['resolved_requirements']) && $poData['resolved_requirements'] > 0
            ? "<div style='margin-top:20px;padding:15px;background:#d1f2eb;border-left:4px solid #4caf50;border-radius:4px;'><p><strong>✅ {$poData['resolved_requirements']} stock requirement(s)</strong> updated.</p></div>"
            : '';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,#4caf50,#2e7d32);padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>✅ Purchase Order Delivered</h1></div><div style='padding:20px;background:#f5f5f5;'><table style='width:100%;border-collapse:collapse;'><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>PO Number:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>PO-{$poId}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Item:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$poData['item_name']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Quantity:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$poData['quantity']} {$poData['unit']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>New Stock:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'><strong style='color:#4caf50;'>{$poData['new_stock']} {$poData['unit']}</strong></td></tr></table>{$resolvedText}</div></body></html>";
    }

    private function buildOrderNotificationEmail(string $orderType, int $orderId, array $orderData): string
    {
        $title = $orderType === 'purchase_order' ? 'New Purchase Order' : 'New Sales Order';
        $icon = $orderType === 'purchase_order' ? '📦' : '🛒';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,#667eea,#764ba2);padding:20px;text-align:center;'><h1 style='color:white;margin:0;'>{$icon} {$title}</h1></div><div style='padding:20px;background:#f5f5f5;'><table style='width:100%;border-collapse:collapse;'><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Order ID:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>#{$orderId}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Item:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$orderData['item_name']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Quantity:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$orderData['quantity']}</td></tr><tr><td style='padding:10px;border-bottom:1px solid #ddd;'><strong>Total:</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>₱" . number_format($orderData['total_amount'], 2) . "</td></tr></table></div></body></html>";
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    private function getAdminEmails(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT u.email FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE r.role_name IN ('admin', 'superadmin')
                AND u.status = 'active'
                AND u.email IS NOT NULL
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            error_log("Get admin emails error: " . $e->getMessage());
            return [];
        }
    }

    private function getAllStaffEmails(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT email FROM users
                WHERE status = 'active'
                AND email IS NOT NULL
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            error_log("Get staff emails error: " . $e->getMessage());
            return [];
        }
    }

    private function logNotification(array $data): void
    {
        $logFile = __DIR__ . '/../../logs/notifications.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $entry = json_encode(array_merge($data, [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'system'
        ]));

        @file_put_contents($logFile, $entry . "\n", FILE_APPEND);
    }
}
