<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\EmailService;
use Janstro\InventorySystem\Services\AuditService;
use Janstro\InventorySystem\Utils\Response;
use PDO;

/**
 * ============================================================================
 * EMAIL CONTROLLER v1.0 - REFACTORED (SUPERADMIN ONLY)
 * ============================================================================
 * Thin orchestration layer for email configuration & operations
 * All business logic delegated to EmailService
 * ============================================================================
 */
class EmailController
{
    private PDO $db;
    private EmailService $emailService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->emailService = new EmailService();
        $this->auditService = new AuditService();
    }

    // ========================================================================
    // EMAIL SETTINGS MANAGEMENT
    // ========================================================================

    /**
     * GET /email-settings
     * Get current email configuration
     */
    public function getSettings(): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings) {
                // Return safe defaults if no config exists
                Response::success([
                    'enabled' => 1,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_encryption' => 'tls',
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'from_email' => 'noreply@janstro.com',
                    'from_name' => 'Janstro Inventory System',
                    'reply_to' => '',
                    'notify_low_stock' => 1,
                    'notify_new_order' => 1,
                    'notify_po_delivered' => 1,
                    'notify_installation_complete' => 1,
                    'admin_emails' => '',
                    'email_footer' => ''
                ], 'Default settings loaded');
            } else {
                Response::success($settings, 'Settings retrieved');
            }
        } catch (\PDOException $e) {
            error_log("EmailController::getSettings - " . $e->getMessage());
            Response::serverError('Failed to retrieve settings');
        }
    }

    /**
     * POST /email-settings/save
     * Save email configuration
     */
    public function saveSettings(): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            // Check if settings exist
            $stmt = $this->db->query("SELECT setting_id FROM email_settings LIMIT 1");
            $existing = $stmt->fetch();

            if ($existing) {
                // UPDATE
                $stmt = $this->db->prepare("
                    UPDATE email_settings SET
                        enabled = ?, smtp_host = ?, smtp_port = ?, smtp_encryption = ?,
                        smtp_username = ?, smtp_password = ?, from_email = ?, from_name = ?,
                        reply_to = ?, notify_low_stock = ?, notify_new_order = ?,
                        notify_po_delivered = ?, notify_installation_complete = ?,
                        admin_emails = ?, email_footer = ?, updated_at = NOW()
                    WHERE setting_id = ?
                ");

                $stmt->execute([
                    $data['enabled'] ?? 1,
                    $data['smtp_host'] ?? '',
                    $data['smtp_port'] ?? 587,
                    $data['smtp_encryption'] ?? 'tls',
                    $data['smtp_username'] ?? '',
                    $data['smtp_password'] ?? '',
                    $data['from_email'] ?? '',
                    $data['from_name'] ?? 'Janstro Inventory System',
                    $data['reply_to'] ?? '',
                    $data['notify_low_stock'] ?? 0,
                    $data['notify_new_order'] ?? 0,
                    $data['notify_po_delivered'] ?? 0,
                    $data['notify_installation_complete'] ?? 0,
                    $data['admin_emails'] ?? '',
                    $data['email_footer'] ?? '',
                    $existing['setting_id']
                ]);
            } else {
                // INSERT
                $stmt = $this->db->prepare("
                    INSERT INTO email_settings (
                        enabled, smtp_host, smtp_port, smtp_encryption,
                        smtp_username, smtp_password, from_email, from_name,
                        reply_to, notify_low_stock, notify_new_order,
                        notify_po_delivered, notify_installation_complete,
                        admin_emails, email_footer
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $data['enabled'] ?? 1,
                    $data['smtp_host'] ?? '',
                    $data['smtp_port'] ?? 587,
                    $data['smtp_encryption'] ?? 'tls',
                    $data['smtp_username'] ?? '',
                    $data['smtp_password'] ?? '',
                    $data['from_email'] ?? '',
                    $data['from_name'] ?? 'Janstro Inventory System',
                    $data['reply_to'] ?? '',
                    $data['notify_low_stock'] ?? 0,
                    $data['notify_new_order'] ?? 0,
                    $data['notify_po_delivered'] ?? 0,
                    $data['notify_installation_complete'] ?? 0,
                    $data['admin_emails'] ?? '',
                    $data['email_footer'] ?? ''
                ]);
            }

            // Audit log
            $this->auditService->log(
                $user->user_id,
                'Updated email settings',
                'email',
                'settings_update'
            );

            Response::success(null, 'Email settings saved successfully');
        } catch (\PDOException $e) {
            error_log("EmailController::saveSettings - " . $e->getMessage());
            Response::serverError('Failed to save settings');
        }
    }

    /**
     * POST /email-settings/test
     * Send test email to verify configuration
     */
    public function testEmail(): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $testEmail = $data['test_email'] ?? null;

            if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Valid test email address required');
                return;
            }

            // Attempt to send test email
            $subject = '✅ Janstro IMS - Email Configuration Test';
            $body = $this->buildTestEmailTemplate();

            $sent = $this->emailService->send($testEmail, $subject, $body, 'test');

            if ($sent) {
                // Audit log
                $this->auditService->log(
                    $user->user_id,
                    "Sent test email to {$testEmail}",
                    'email',
                    'test'
                );

                Response::success(null, 'Test email sent successfully');
            } else {
                Response::error('Email test failed', null, 500);
            }
        } catch (\Exception $e) {
            error_log("EmailController::testEmail - " . $e->getMessage());
            Response::serverError('Email test failed: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // EMAIL OPERATIONS (via EmailService)
    // ========================================================================

    /**
     * POST /email/low-stock-alerts
     * Send low stock alerts to admins
     */
    public function sendLowStockAlerts(): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            // Get low stock items
            $stmt = $this->db->query("
                SELECT item_name, quantity, reorder_level
                FROM items
                WHERE quantity <= reorder_level AND status = 'active'
            ");
            $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lowStockItems)) {
                Response::success(null, 'No low stock items found');
                return;
            }

            // Get admin emails
            $stmt = $this->db->query("
                SELECT email FROM users 
                WHERE role_id IN (SELECT role_id FROM roles WHERE role_name IN ('admin', 'superadmin'))
                AND status = 'active' AND email IS NOT NULL
            ");
            $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $sentCount = 0;
            foreach ($admins as $email) {
                if ($this->emailService->sendLowStockAlert($lowStockItems, $email)) {
                    $sentCount++;
                }
            }

            $this->auditService->log(
                $user->user_id,
                "Sent low stock alerts ({$sentCount} emails)",
                'email',
                'low_stock_alert'
            );

            Response::success([
                'low_stock_count' => count($lowStockItems),
                'emails_sent' => $sentCount
            ], 'Low stock alerts sent');
        } catch (\Exception $e) {
            error_log("EmailController::sendLowStockAlerts - " . $e->getMessage());
            Response::serverError('Failed to send alerts');
        }
    }

    /**
     * GET /email/logs
     * Get email send history
     */
    public function getEmailLogs(): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));

            $stmt = $this->db->prepare("
                SELECT log_id, recipient, subject, sent_at, status
                FROM email_logs
                ORDER BY sent_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success($logs, 'Email logs retrieved');
        } catch (\PDOException $e) {
            error_log("EmailController::getEmailLogs - " . $e->getMessage());
            Response::serverError('Failed to retrieve logs');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Build test email template
     */
    private function buildTestEmailTemplate(): string
    {
        return "
            <html>
            <body style='font-family: Arial, sans-serif; padding: 20px;'>
                <h2 style='color: #28a745;'>✅ Email Configuration Test</h2>
                <p>If you're reading this, your email settings are working correctly!</p>
                <p><strong>Test Details:</strong></p>
                <ul>
                    <li>Test performed: " . date('F j, Y g:i A') . "</li>
                    <li>System: Janstro Inventory Management System</li>
                    <li>Version: 3.0</li>
                </ul>
                <hr>
                <p style='font-size: 12px; color: #6c757d;'>
                    Janstro Prime Renewable Energy Solutions Corporation<br>
                    Automated Test Email - No Action Required
                </p>
            </body>
            </html>
        ";
    }
}
