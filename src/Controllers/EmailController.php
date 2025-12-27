<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Services\EmailService;
use Janstro\InventorySystem\Utils\Response;

class EmailController
{
    private $db;
    private $emailService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->emailService = new EmailService();
    }

    /* Send low stock alerts */
    public function sendLowStockAlerts()
    {
        try {
            // Get low stock items
            $stmt = $this->db->query("
                SELECT name, quantity, reorder_level
                FROM items
                WHERE quantity <= reorder_level
                AND status = 'active'
            ");
            $lowStockItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($lowStockItems)) {
                Response::success(null, 'No low stock items found');
                return;
            }

            // Get admin emails
            $stmt = $this->db->query("
                SELECT email 
                FROM users 
                WHERE role IN ('admin', 'superadmin') 
                AND status = 'active'
                AND email IS NOT NULL
            ");
            $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $sentCount = 0;
            foreach ($admins as $admin) {
                if ($this->emailService->sendLowStockAlert($lowStockItems, $admin['email'])) {
                    $sentCount++;
                }
            }

            Response::success([
                'low_stock_count' => count($lowStockItems),
                'emails_sent' => $sentCount
            ], 'Low stock alerts sent successfully');
        } catch (\Exception $e) {
            error_log("Send low stock alerts failed: " . $e->getMessage());
            Response::serverError('Failed to send alerts: ' . $e->getMessage());
        }
    }

    /* Send daily summary */
    public function sendDailySummary()
    {
        try {
            $today = date('Y-m-d');

            // Get today's stats
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM transactions WHERE DATE(movement_date) = ?) as total_transactions,
                    (SELECT COUNT(*) FROM sales_orders WHERE DATE(created_at) = ?) as new_orders,
                    (SELECT COUNT(*) FROM items WHERE quantity <= reorder_level) as low_stock_items,
                    (SELECT COALESCE(SUM(total_amount), 0) FROM sales_orders WHERE DATE(created_at) = ?) as total_sales
            ");
            $stmt->execute([$today, $today, $today]);
            $summary = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get admin emails
            $stmt = $this->db->query("
                SELECT email 
                FROM users 
                WHERE role IN ('admin', 'superadmin') 
                AND status = 'active'
                AND email IS NOT NULL
            ");
            $admins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $sentCount = 0;
            foreach ($admins as $admin) {
                if ($this->emailService->sendDailySummary($summary, $admin['email'])) {
                    $sentCount++;
                }
            }

            Response::success([
                'summary' => $summary,
                'emails_sent' => $sentCount
            ], 'Daily summary sent successfully');
        } catch (\Exception $e) {
            error_log("Send daily summary failed: " . $e->getMessage());
            Response::serverError('Failed to send summary: ' . $e->getMessage());
        }
    }

    /* Test email configuration */
    public function testEmail()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $testEmail = $data['email'] ?? null;

            if (!$testEmail) {
                Response::badRequest('Email address required');
                return;
            }

            // Send test email
            $subject = "✅ Janstro IMS - Email Test";
            $body = "
                <h2>Email Configuration Test</h2>
                <p>If you're reading this, your email configuration is working correctly!</p>
                <p>Tested on: " . date('F j, Y g:i A') . "</p>
            ";

            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $mailer->SMTPAuth = true;
            $mailer->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $mailer->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = $_ENV['MAIL_PORT'] ?? 587;

            $mailer->setFrom($_ENV['MAIL_FROM'] ?? 'noreply@janstro-ims.com', 'Janstro IMS');
            $mailer->addAddress($testEmail);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $body;

            if ($mailer->send()) {
                Response::success(null, 'Test email sent successfully');
            } else {
                Response::serverError('Failed to send test email');
            }
        } catch (\Exception $e) {
            error_log("Test email failed: " . $e->getMessage());
            Response::serverError('Email test failed: ' . $e->getMessage());
        }
    }

    /* Get email logs */
    public function getEmailLogs()
    {
        try {
            $limit = $_GET['limit'] ?? 50;

            $stmt = $this->db->prepare("
                SELECT 
                    log_id,
                    recipient,
                    subject,
                    sent_at,
                    status
                FROM email_logs
                ORDER BY sent_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($logs, 'Email logs retrieved');
        } catch (\Exception $e) {
            error_log("Get email logs failed: " . $e->getMessage());
            Response::serverError('Failed to retrieve logs: ' . $e->getMessage());
        }
    }

    /* Process email queue */
    public function processQueue()
    {
        try {
            // Get pending emails
            $stmt = $this->db->query("
                SELECT * FROM email_queue
                WHERE status = 'pending'
                AND attempts < 3
                ORDER BY created_at ASC
                LIMIT 10
            ");
            $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $processed = 0;
            foreach ($emails as $email) {
                // Attempt to send
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
                // ... configure mailer ...

                try {
                    $mailer->send();

                    // Mark as sent
                    $stmt = $this->db->prepare("
                        UPDATE email_queue 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE queue_id = ?
                    ");
                    $stmt->execute([$email['queue_id']]);
                    $processed++;
                } catch (\Exception $e) {
                    // Increment attempts
                    $stmt = $this->db->prepare("
                        UPDATE email_queue 
                        SET attempts = attempts + 1, last_error = ?
                        WHERE queue_id = ?
                    ");
                    $stmt->execute([$e->getMessage(), $email['queue_id']]);
                }
            }

            Response::success(['processed' => $processed], 'Queue processed');
        } catch (\Exception $e) {
            error_log("Process queue failed: " . $e->getMessage());
            Response::serverError('Failed to process queue: ' . $e->getMessage());
        }
    }
}
