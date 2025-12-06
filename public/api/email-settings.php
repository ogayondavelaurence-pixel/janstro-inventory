<?php

/**
 * Email Settings API Endpoint
 * Path: public/api/email-settings.php
 * Handles: GET settings, POST save, POST test
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

// SUPERADMIN ONLY
$user = AuthMiddleware::requireRole(['superadmin']);
if (!$user) exit;

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];

// ============================================================================
// GET EMAIL SETTINGS
// ============================================================================
if ($method === 'GET' && (!isset($_GET['action']) || $_GET['action'] === 'get')) {
    try {
        $stmt = $db->query("SELECT * FROM email_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            // Return default settings if none exist
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
    } catch (PDOException $e) {
        error_log("Get email settings error: " . $e->getMessage());
        Response::serverError('Failed to retrieve settings');
    }
    exit;
}

// ============================================================================
// SAVE EMAIL SETTINGS
// ============================================================================
if ($method === 'POST' && $_POST['action'] === 'save') {
    try {
        // Check if settings exist
        $stmt = $db->query("SELECT setting_id FROM email_settings LIMIT 1");
        $existing = $stmt->fetch();

        if ($existing) {
            // UPDATE existing settings
            $stmt = $db->prepare("
                UPDATE email_settings SET
                    enabled = ?,
                    smtp_host = ?,
                    smtp_port = ?,
                    smtp_encryption = ?,
                    smtp_username = ?,
                    smtp_password = ?,
                    from_email = ?,
                    from_name = ?,
                    reply_to = ?,
                    notify_low_stock = ?,
                    notify_new_order = ?,
                    notify_po_delivered = ?,
                    notify_installation_complete = ?,
                    admin_emails = ?,
                    email_footer = ?,
                    updated_at = NOW()
                WHERE setting_id = ?
            ");

            $stmt->execute([
                $_POST['enabled'] ?? 1,
                $_POST['smtp_host'] ?? '',
                $_POST['smtp_port'] ?? 587,
                $_POST['smtp_encryption'] ?? 'tls',
                $_POST['smtp_username'] ?? '',
                $_POST['smtp_password'] ?? '',
                $_POST['from_email'] ?? '',
                $_POST['from_name'] ?? 'Janstro Inventory System',
                $_POST['reply_to'] ?? '',
                $_POST['notify_low_stock'] ?? 0,
                $_POST['notify_new_order'] ?? 0,
                $_POST['notify_po_delivered'] ?? 0,
                $_POST['notify_installation_complete'] ?? 0,
                $_POST['admin_emails'] ?? '',
                $_POST['email_footer'] ?? '',
                $existing['setting_id']
            ]);
        } else {
            // INSERT new settings
            $stmt = $db->prepare("
                INSERT INTO email_settings (
                    enabled, smtp_host, smtp_port, smtp_encryption,
                    smtp_username, smtp_password, from_email, from_name,
                    reply_to, notify_low_stock, notify_new_order,
                    notify_po_delivered, notify_installation_complete,
                    admin_emails, email_footer
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_POST['enabled'] ?? 1,
                $_POST['smtp_host'] ?? '',
                $_POST['smtp_port'] ?? 587,
                $_POST['smtp_encryption'] ?? 'tls',
                $_POST['smtp_username'] ?? '',
                $_POST['smtp_password'] ?? '',
                $_POST['from_email'] ?? '',
                $_POST['from_name'] ?? 'Janstro Inventory System',
                $_POST['reply_to'] ?? '',
                $_POST['notify_low_stock'] ?? 0,
                $_POST['notify_new_order'] ?? 0,
                $_POST['notify_po_delivered'] ?? 0,
                $_POST['notify_installation_complete'] ?? 0,
                $_POST['admin_emails'] ?? '',
                $_POST['email_footer'] ?? ''
            ]);
        }

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, ip_address)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $user->user_id,
            'Updated email settings',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        Response::success(null, 'Email settings saved successfully');
    } catch (PDOException $e) {
        error_log("Save email settings error: " . $e->getMessage());
        Response::serverError('Failed to save settings: ' . $e->getMessage());
    }
    exit;
}

// ============================================================================
// TEST EMAIL
// ============================================================================
if ($method === 'POST' && $_POST['action'] === 'test') {
    try {
        $testEmail = $_POST['test_email'] ?? null;

        if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            Response::badRequest('Valid test email address required');
            exit;
        }

        // Use PHPMailer if available, otherwise use mail()
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

            try {
                // Server settings
                $mailer->isSMTP();
                $mailer->Host = $_POST['smtp_host'] ?? 'smtp.gmail.com';
                $mailer->SMTPAuth = true;
                $mailer->Username = $_POST['smtp_username'] ?? '';
                $mailer->Password = $_POST['smtp_password'] ?? '';
                $mailer->SMTPSecure = $_POST['smtp_encryption'] ?? 'tls';
                $mailer->Port = $_POST['smtp_port'] ?? 587;

                // Recipients
                $mailer->setFrom(
                    $_POST['from_email'] ?? 'noreply@janstro.com',
                    $_POST['from_name'] ?? 'Janstro IMS'
                );
                $mailer->addAddress($testEmail);

                // Content
                $mailer->isHTML(true);
                $mailer->Subject = '✅ Janstro IMS - Email Test Successful';
                $mailer->Body = "
                    <html>
                    <body style='font-family: Arial, sans-serif; padding: 20px;'>
                        <h2 style='color: #28a745;'>✅ Email Configuration Test</h2>
                        <p>If you're reading this, your email settings are working correctly!</p>
                        <p><strong>Test Details:</strong></p>
                        <ul>
                            <li>SMTP Host: {$_POST['smtp_host']}</li>
                            <li>SMTP Port: {$_POST['smtp_port']}</li>
                            <li>Encryption: {$_POST['smtp_encryption']}</li>
                            <li>From: {$_POST['from_email']}</li>
                        </ul>
                        <p>Tested on: " . date('F j, Y g:i A') . "</p>
                        <hr>
                        <p style='font-size: 12px; color: #6c757d;'>
                            Janstro Inventory Management System<br>
                            Automated Test Email
                        </p>
                    </body>
                    </html>
                ";

                $mailer->send();
                Response::success(null, 'Test email sent successfully to ' . $testEmail);
            } catch (Exception $e) {
                Response::serverError('Email test failed: ' . $mailer->ErrorInfo);
            }
        } else {
            // Fallback to PHP mail()
            $subject = '✅ Janstro IMS - Email Test';
            $message = "Test email sent successfully at " . date('F j, Y g:i A');
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . ($_POST['from_email'] ?? 'noreply@janstro.com')
            ];

            if (mail($testEmail, $subject, $message, implode("\r\n", $headers))) {
                Response::success(null, 'Test email sent via PHP mail()');
            } else {
                Response::serverError('Email test failed via PHP mail()');
            }
        }
    } catch (Exception $e) {
        error_log("Test email error: " . $e->getMessage());
        Response::serverError('Email test failed: ' . $e->getMessage());
    }
    exit;
}

// Invalid action
Response::badRequest('Invalid action');
