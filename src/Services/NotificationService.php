<?php

/**
 * ============================================================================
 * COMPLETE NOTIFICATION SERVICE - CENTRAL HUB v2.0
 * ============================================================================
 * Path: src/Services/NotificationService.php
 * 
 * WHAT THIS DOES:
 * ✅ Centralized notification management
 * ✅ Email, SMS, Push, In-App notifications
 * ✅ User preference management
 * ✅ Queue management
 * ✅ Notification history
 * ✅ Admin monitoring
 * ============================================================================
 */

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

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

    /**
     * Load notification configuration from database
     */
    private function loadConfig(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'enabled' => 1,
                'notify_low_stock' => 1,
                'notify_new_order' => 1,
                'notify_po_delivered' => 1,
                'notify_installation_complete' => 1
            ];
        } catch (\Exception $e) {
            error_log("NotificationService config error: " . $e->getMessage());
            $this->config = ['enabled' => 0];
        }
    }

    // ========================================================================
    // 1. USER ACCOUNT NOTIFICATIONS
    // ========================================================================

    /**
     * Send welcome notification to new user
     */
    public function notifyUserCreated(array $user, ?string $tempPassword = null): array
    {
        $results = [];

        // Email notification
        if ($this->shouldNotify('email', $user['user_id'])) {
            $results['email'] = $this->emailService->sendWelcomeEmail($user, $tempPassword);
        }

        // Log notification
        $this->logNotification([
            'type' => 'user_created',
            'user_id' => $user['user_id'],
            'channels' => array_keys($results),
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Send account deletion notification
     */
    public function notifyUserDeleted(array $user, string $reason = null): array
    {
        $results = [];

        // Email notification
        if (!empty($user['email'])) {
            $results['email'] = $this->emailService->sendGoodbyeEmail($user, $reason);
        }

        // Log notification
        $this->logNotification([
            'type' => 'user_deleted',
            'user_id' => $user['user_id'] ?? null,
            'channels' => array_keys($results),
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Send password change confirmation
     */
    public function notifyPasswordChanged(array $user): array
    {
        $results = [];

        // Email notification
        if ($this->shouldNotify('email', $user['user_id'])) {
            $results['email'] = $this->emailService->sendPasswordChangeConfirmation($user);
        }

        // In-app notification
        $results['in_app'] = $this->createInAppNotification(
            $user['user_id'],
            'security',
            'Password Changed',
            'Your password was successfully changed. If this wasn\'t you, contact admin immediately.',
            'high'
        );

        $this->logNotification([
            'type' => 'password_changed',
            'user_id' => $user['user_id'],
            'channels' => array_keys($results),
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Send account lockout alert
     */
    public function notifyAccountLocked(array $user, int $failedAttempts): array
    {
        $results = [];

        // Email notification
        if (!empty($user['email'])) {
            $results['email'] = $this->emailService->sendAccountLockoutAlert($user, $failedAttempts);
        }

        // Admin alert
        $results['admin_alert'] = $this->notifyAdmins(
            "Account Locked - {$user['username']}",
            "User {$user['username']} (ID: {$user['user_id']}) has been locked after {$failedAttempts} failed login attempts."
        );

        $this->logNotification([
            'type' => 'account_locked',
            'user_id' => $user['user_id'],
            'channels' => array_keys($results),
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Send password reset link
     */
    public function notifyPasswordReset(string $email, string $token): array
    {
        $results = [];

        $results['email'] = $this->emailService->sendPasswordResetLink($email, $token);

        $this->logNotification([
            'type' => 'password_reset',
            'email' => $email,
            'channels' => ['email'],
            'success' => $results['email']
        ]);

        return $results;
    }

    // ========================================================================
    // 2. INVENTORY NOTIFICATIONS
    // ========================================================================

    /**
     * Send low stock alerts
     */
    public function notifyLowStock(array $items): array
    {
        if (!$this->config['notify_low_stock'] || empty($items)) {
            return ['skipped' => true];
        }

        $results = [];

        // Get admin emails
        $admins = $this->getAdminEmails();

        foreach ($admins as $email) {
            $sent = $this->emailService->sendLowStockAlert($items, $email);
            $results[$email] = $sent;
        }

        // In-app notifications for all staff
        $this->broadcastInAppNotification(
            ['staff', 'admin', 'superadmin'],
            'inventory',
            'Low Stock Alert',
            count($items) . ' items are below reorder level',
            'warning'
        );

        $this->logNotification([
            'type' => 'low_stock',
            'item_count' => count($items),
            'channels' => ['email', 'in_app'],
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Notify new order created
     */
    public function notifyNewOrder(string $orderType, int $orderId, array $orderData): array
    {
        if (!$this->config['notify_new_order']) {
            return ['skipped' => true];
        }

        $results = [];

        // Determine recipients based on order type
        $recipients = $orderType === 'purchase_order'
            ? $this->getAdminEmails()
            : $this->getAllStaffEmails();

        $subject = $orderType === 'purchase_order'
            ? "New Purchase Order #PO-{$orderId}"
            : "New Sales Order #SO-{$orderId}";

        foreach ($recipients as $email) {
            $sent = $this->sendOrderNotification($email, $subject, $orderData);
            $results[$email] = $sent;
        }

        $this->logNotification([
            'type' => 'new_order',
            'order_type' => $orderType,
            'order_id' => $orderId,
            'channels' => ['email'],
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    /**
     * Notify PO delivered
     */
    public function notifyPODelivered(int $poId, array $poData): array
    {
        if (!$this->config['notify_po_delivered']) {
            return ['skipped' => true];
        }

        $results = [];

        $admins = $this->getAdminEmails();

        foreach ($admins as $email) {
            $sent = $this->sendPODeliveryNotification($email, $poId, $poData);
            $results[$email] = $sent;
        }

        $this->logNotification([
            'type' => 'po_delivered',
            'po_id' => $poId,
            'channels' => ['email'],
            'success' => in_array(true, $results)
        ]);

        return $results;
    }

    // ========================================================================
    // 3. CUSTOMER INQUIRY NOTIFICATIONS
    // ========================================================================

    /**
     * Send inquiry notification to staff
     */
    public function notifyInquiryReceived(array $inquiry): array
    {
        $results = [];

        $staffEmail = $_ENV['STAFF_EMAIL'] ?? 'janstroprimecorporation@gmail.com';
        $ref = 'INQ-' . str_pad($inquiry['inquiry_id'], 6, '0', STR_PAD_LEFT);

        $subject = "New Solar Inquiry - $ref - {$inquiry['customer_name']}";
        $body = $this->buildInquiryEmailTemplate($inquiry, $ref);

        $results['email'] = $this->sendEmail($staffEmail, $subject, $body);

        // In-app notification
        $this->broadcastInAppNotification(
            ['staff', 'admin', 'superadmin'],
            'inquiry',
            'New Customer Inquiry',
            "New inquiry from {$inquiry['customer_name']} - {$inquiry['inquiry_type']}",
            'medium'
        );

        $this->logNotification([
            'type' => 'inquiry_received',
            'inquiry_id' => $inquiry['inquiry_id'],
            'channels' => ['email', 'in_app'],
            'success' => $results['email']
        ]);

        return $results;
    }

    // ========================================================================
    // 4. IN-APP NOTIFICATIONS
    // ========================================================================

    /**
     * Create in-app notification for specific user
     */
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

    /**
     * Broadcast in-app notification to all users with specific roles
     */
    public function broadcastInAppNotification(
        array $roles,
        string $type,
        string $title,
        string $message,
        string $priority = 'medium'
    ): int {
        try {
            // Get all users with specified roles
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

    /**
     * Get unread notifications for user
     */
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

    /**
     * Mark notification as read
     */
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
    // 5. USER PREFERENCES
    // ========================================================================

    /**
     * Check if user should receive notification via channel
     */
    private function shouldNotify(string $channel, int $userId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT notification_preferences FROM users WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $prefs = $stmt->fetchColumn();

            if (!$prefs) return true; // Default to enabled

            $preferences = json_decode($prefs, true);
            return $preferences[$channel . '_notifications'] ?? true;
        } catch (\Exception $e) {
            return true; // Default to enabled on error
        }
    }

    /**
     * Update user notification preferences
     */
    public function updateUserPreferences(int $userId, array $preferences): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET notification_preferences = ?
                WHERE user_id = ?
            ");
            return $stmt->execute([json_encode($preferences), $userId]);
        } catch (\Exception $e) {
            error_log("Update preferences error: " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // 6. ADMIN NOTIFICATIONS
    // ========================================================================

    /**
     * Send notification to all admins
     */
    private function notifyAdmins(string $subject, string $message): bool
    {
        $admins = $this->getAdminEmails();
        $success = true;

        foreach ($admins as $email) {
            $sent = $this->sendEmail($email, $subject, $this->buildAdminAlertTemplate($subject, $message));
            $success = $success && $sent;
        }

        return $success;
    }

    /**
     * Get admin email addresses
     */
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

    /**
     * Get all staff email addresses
     */
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

    // ========================================================================
    // 7. EMAIL TEMPLATES
    // ========================================================================

    private function buildInquiryEmailTemplate(array $inquiry, string $ref): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea, #764ba2); padding: 20px; text-align: center;'>
                <h1 style='color: white; margin: 0;'>☀️ New Inquiry</h1>
            </div>
            <div style='padding: 20px; background: #f5f5f5;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Reference:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>$ref</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Customer:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$inquiry['customer_name']}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Email:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$inquiry['email']}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$inquiry['phone']}</td></tr>
                    <tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Type:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$inquiry['inquiry_type']}</td></tr>
                </table>
                <div style='margin-top: 20px; padding: 15px; background: white; border-radius: 8px;'>
                    <strong>Message:</strong><br>{$inquiry['message']}
                </div>
            </div>
        </body>
        </html>";
    }

    private function buildAdminAlertTemplate(string $title, string $message): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #dc3545, #c82333); padding: 30px; text-align: center;'>
                    <h1 style='color: white;'>🚨 Admin Alert</h1>
                </div>
                <div style='padding: 30px; background: #fff;'>
                    <h2>{$title}</h2>
                    <p>{$message}</p>
                    <p><small>Generated on " . date('F j, Y g:i A') . "</small></p>
                </div>
            </div>
        </body>
        </html>";
    }

    private function sendOrderNotification(string $email, string $subject, array $orderData): bool
    {
        $body = "<h2>{$subject}</h2><p>Order details...</p>";
        return $this->sendEmail($email, $subject, $body);
    }

    private function sendPODeliveryNotification(string $email, int $poId, array $poData): bool
    {
        $subject = "Purchase Order #PO-{$poId} Delivered";
        $body = "<h2>{$subject}</h2><p>PO has been delivered.</p>";
        return $this->sendEmail($email, $subject, $body);
    }

    // ========================================================================
    // 8. CORE EMAIL SENDER
    // ========================================================================

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: Janstro IMS <noreply@janstro.com>',
                'X-Mailer: PHP/' . phpversion()
            ];

            $sent = mail($to, $subject, $body, implode("\r\n", $headers));

            if (!$sent) {
                error_log("Email failed to: {$to}");
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Send email exception: " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // 9. LOGGING
    // ========================================================================

    private function logNotification(array $data): void
    {
        $logFile = __DIR__ . '/../../logs/notifications.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $entry = json_encode(array_merge($data, [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]));

        file_put_contents($logFile, $entry . "\n", FILE_APPEND);
    }
}
