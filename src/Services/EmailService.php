<?php

/**
 * ============================================================================
 * COMPLETE EMAIL SERVICE - ALL NOTIFICATION TEMPLATES
 * ============================================================================
 * Path: src/Services/EmailService.php
 * 
 * WHAT THIS ADDS:
 * ✅ Welcome email on user creation
 * ✅ Goodbye email on user deletion
 * ✅ Password change confirmation
 * ✅ Account lockout alert
 * ✅ Password reset link
 * ✅ Low stock alerts
 * ✅ Daily summaries
 * ============================================================================
 */

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

class EmailService
{
    private PDO $db;
    private array $smtpConfig;
    private bool $enabled;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->loadConfig();
    }

    /**
     * Load SMTP configuration from database
     */
    private function loadConfig(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config) {
                $this->enabled = (bool)$config['enabled'];
                $this->smtpConfig = $config;
            } else {
                // Use defaults from .env
                $this->enabled = true;
                $this->smtpConfig = [
                    'smtp_host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
                    'smtp_port' => $_ENV['MAIL_PORT'] ?? 587,
                    'smtp_encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                    'smtp_username' => $_ENV['MAIL_USERNAME'] ?? '',
                    'smtp_password' => $_ENV['MAIL_PASSWORD'] ?? '',
                    'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@janstro.com',
                    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Janstro IMS',
                ];
            }
        } catch (\Exception $e) {
            error_log("EmailService config load error: " . $e->getMessage());
            $this->enabled = false;
        }
    }

    // ========================================================================
    // 1. WELCOME EMAIL (User Creation)
    // ========================================================================

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(array $userData, string $tempPassword = null): bool
    {
        if (!$this->enabled) return false;

        $subject = "🎉 Welcome to Janstro IMS - Your Account is Ready!";

        $body = $this->getWelcomeTemplate($userData, $tempPassword);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'user_welcome',
            $userData['user_id']
        );
    }

    private function getWelcomeTemplate(array $user, ?string $tempPassword): string
    {
        $passwordSection = $tempPassword
            ? "<div class='alert'>
                <strong>Temporary Password:</strong> <code>{$tempPassword}</code><br>
                <small>⚠️ Please change this password after your first login</small>
               </div>"
            : "";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .btn { display: inline-block; background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
                code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>☀️ Welcome to Janstro IMS!</h1>
                    <p>Your account has been created</p>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <p>Your Janstro Inventory Management System account has been successfully created!</p>
                    
                    <div class='info-box'>
                        <h3 style='margin-top: 0;'>📋 Your Account Details</h3>
                        <p><strong>Username:</strong> {$user['username']}</p>
                        <p><strong>Email:</strong> {$user['email']}</p>
                        <p><strong>Role:</strong> {$user['role_name']}</p>
                    </div>
                    
                    {$passwordSection}
                    
                    <p>You can now log in to the system using your credentials:</p>
                    
                    <a href='http://localhost:8080/janstro-inventory/frontend/index.html' class='btn'>
                        🚀 Login to Janstro IMS
                    </a>
                    
                    <h3>🎯 What's Next?</h3>
                    <ul>
                        <li>Complete your profile setup</li>
                        <li>Explore the dashboard</li>
                        <li>Familiarize yourself with the system features</li>
                    </ul>
                    
                    <p>If you have any questions, please contact your system administrator.</p>
                </div>
                
                <div class='footer'>
                    <p>Janstro Prime Corporation - Solar Inventory Management</p>
                    <p>This is an automated email. Please do not reply.</p>
                    <p>Generated on " . date('F j, Y g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // 2. GOODBYE EMAIL (User Deletion)
    // ========================================================================

    /**
     * Send goodbye email when user account is deleted
     */
    public function sendGoodbyeEmail(array $userData, string $reason = null): bool
    {
        if (!$this->enabled) return false;

        $subject = "Account Deleted - Janstro IMS";

        $body = $this->getGoodbyeTemplate($userData, $reason);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'user_deletion'
        );
    }

    private function getGoodbyeTemplate(array $user, ?string $reason): string
    {
        $reasonSection = $reason
            ? "<p><strong>Reason:</strong> {$reason}</p>"
            : "";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 40px 20px; text-align: center; }
                .content { padding: 30px; }
                .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Account Deleted</h1>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <p>This email confirms that your Janstro IMS account has been deleted from our system.</p>
                    
                    <div class='info-box'>
                        <p><strong>Username:</strong> {$user['username']}</p>
                        <p><strong>Email:</strong> {$user['email']}</p>
                        <p><strong>Deleted on:</strong> " . date('F j, Y g:i A') . "</p>
                        {$reasonSection}
                    </div>
                    
                    <p>All your account data and permissions have been removed. If you believe this was done in error, please contact your system administrator immediately.</p>
                    
                    <p>Thank you for using Janstro IMS.</p>
                </div>
                
                <div class='footer'>
                    <p>Janstro Prime Corporation</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // 3. PASSWORD CHANGE CONFIRMATION
    // ========================================================================

    /**
     * Send password change confirmation email
     */
    public function sendPasswordChangeConfirmation(array $userData): bool
    {
        if (!$this->enabled) return false;

        $subject = "🔐 Password Changed - Janstro IMS";

        $body = $this->getPasswordChangeTemplate($userData);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'password_change',
            $userData['user_id']
        );
    }

    private function getPasswordChangeTemplate(array $user): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white; padding: 40px 20px; text-align: center; }
                .content { padding: 30px; }
                .alert { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0; }
                .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 13px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Changed</h1>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <div class='alert'>
                        <strong>✅ Your password was successfully changed.</strong>
                    </div>
                    
                    <p>This email confirms that your Janstro IMS password was changed on <strong>" . date('F j, Y \a\t g:i A') . "</strong>.</p>
                    
                    <div class='info-box'>
                        <strong>Security Information:</strong><br>
                        IP Address: {$ip}<br>
                        Device: " . substr($userAgent, 0, 100) . "
                    </div>
                    
                    <p><strong>⚠️ If you did NOT make this change:</strong></p>
                    <ul>
                        <li>Contact your system administrator immediately</li>
                        <li>Your account may have been compromised</li>
                        <li>All active sessions have been terminated</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>Janstro IMS Security Notification</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // 4. ACCOUNT LOCKOUT ALERT
    // ========================================================================

    /**
     * Send account lockout notification
     */
    public function sendAccountLockoutAlert(array $userData, int $failedAttempts): bool
    {
        if (!$this->enabled) return false;

        $subject = "🚨 Account Locked - Security Alert";

        $body = $this->getAccountLockoutTemplate($userData, $failedAttempts);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'account_lockout',
            $userData['user_id']
        );
    }

    private function getAccountLockoutTemplate(array $user, int $attempts): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 40px 20px; text-align: center; }
                .content { padding: 30px; }
                .alert { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 Account Locked</h1>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <div class='alert'>
                        <strong>⚠️ Your account has been temporarily locked due to multiple failed login attempts.</strong>
                    </div>
                    
                    <p><strong>Details:</strong></p>
                    <ul>
                        <li>Failed login attempts: <strong>{$attempts}</strong></li>
                        <li>IP Address: <code>{$ip}</code></li>
                        <li>Time: " . date('F j, Y g:i A') . "</li>
                    </ul>
                    
                    <p><strong>What happens next:</strong></p>
                    <ul>
                        <li>Your account will be automatically unlocked in <strong>15 minutes</strong></li>
                        <li>If you made these attempts, you can try logging in again after the lockout period</li>
                        <li>If you did NOT make these attempts, contact your administrator immediately</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>Janstro IMS Security Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // 5. PASSWORD RESET LINK
    // ========================================================================

    /**
     * Send password reset link
     */
    public function sendPasswordResetLink(string $email, string $token): bool
    {
        if (!$this->enabled) return false;

        $subject = "🔑 Password Reset Request - Janstro IMS";

        $body = $this->getPasswordResetTemplate($email, $token);

        return $this->sendEmail(
            $email,
            $subject,
            $body,
            'password_reset'
        );
    }

    private function getPasswordResetTemplate(string $email, string $token): string
    {
        $resetLink = "http://localhost:8080/janstro-inventory/frontend/reset-password.html?token={$token}";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 40px 20px; text-align: center; }
                .content { padding: 30px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 15px 40px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-size: 16px; }
                .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Password Reset Request</h1>
                </div>
                
                <div class='content'>
                    <p>Hello,</p>
                    
                    <p>We received a request to reset the password for your Janstro IMS account associated with <strong>{$email}</strong>.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetLink}' class='btn'>Reset Password</a>
                    </div>
                    
                    <div class='alert'>
                        <strong>⚠️ This link will expire in 1 hour.</strong>
                    </div>
                    
                    <p>If you did not request a password reset, please ignore this email or contact your administrator if you have concerns.</p>
                    
                    <p><small>If the button doesn't work, copy and paste this link into your browser:</small></p>
                    <p><small><a href='{$resetLink}'>{$resetLink}</a></small></p>
                </div>
                
                <div class='footer'>
                    <p>Janstro IMS</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // CORE SEND EMAIL METHOD
    // ========================================================================

    /**
     * Core method to send email via PHP mail() or SMTP
     */
    private function sendEmail(
        string $to,
        string $subject,
        string $body,
        string $type = 'general',
        ?int $referenceId = null
    ): bool {
        if (!$this->enabled) {
            error_log("Email sending disabled");
            return false;
        }

        try {
            // Use PHP mail() function
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->smtpConfig['from_name'] . ' <' . $this->smtpConfig['from_email'] . '>',
                'Reply-To: ' . $this->smtpConfig['from_email'],
                'X-Mailer: PHP/' . phpversion()
            ];

            $sent = mail($to, $subject, $body, implode("\r\n", $headers));

            // Log result
            $this->logEmail($to, $subject, $type, $sent ? 'sent' : 'failed');

            if ($sent) {
                error_log("✅ Email sent to: {$to} - Type: {$type}");
            } else {
                error_log("❌ Email failed to: {$to} - Type: {$type}");
            }

            return $sent;
        } catch (\Exception $e) {
            error_log("Email send exception: " . $e->getMessage());
            $this->logEmail($to, $subject, $type, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Log email to database
     */
    private function logEmail(
        string $recipient,
        string $subject,
        string $type,
        string $status,
        ?string $error = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs 
                (recipient, subject, email_type, status, sent_at, error_message)
                VALUES (?, ?, ?, ?, NOW(), ?)
            ");

            $stmt->execute([
                $recipient,
                $subject,
                $type,
                $status,
                $error
            ]);
        } catch (\Exception $e) {
            error_log("Email log failed: " . $e->getMessage());
        }
    }

    // ========================================================================
    // 6. LOW STOCK ALERT (Existing, Enhanced)
    // ========================================================================

    public function sendLowStockAlert(array $items, string $recipientEmail): bool
    {
        if (!$this->enabled || empty($items)) return false;

        $itemsList = '';
        foreach ($items as $item) {
            $shortage = ($item['reorder_level'] ?? 0) - ($item['quantity'] ?? 0);
            $itemsList .= "<tr>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['item_name']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd; color: #dc3545;'><strong>{$item['quantity']}</strong></td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['reorder_level']}</td>
                <td style='padding: 10px; border-bottom: 1px solid #ddd; color: #dc3545;'>{$shortage}</td>
            </tr>";
        }

        $subject = "⚠️ Low Stock Alert - " . count($items) . " Items Need Attention";

        $body = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='font-family: Arial, sans-serif;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <div style='background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px; text-align: center;'>
                    <h1>⚠️ Low Stock Alert</h1>
                </div>
                <div style='padding: 30px; background: #fff;'>
                    <p><strong>" . count($items) . " items</strong> have reached or fallen below their reorder levels:</p>
                    <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                        <thead>
                            <tr style='background: #f8f9fa;'>
                                <th style='padding: 12px; text-align: left;'>Item</th>
                                <th style='padding: 12px; text-align: left;'>Stock</th>
                                <th style='padding: 12px; text-align: left;'>Reorder</th>
                                <th style='padding: 12px; text-align: left;'>Shortage</th>
                            </tr>
                        </thead>
                        <tbody>{$itemsList}</tbody>
                    </table>
                    <p><a href='http://localhost:8080/janstro-inventory/frontend/inventory.html' style='display: inline-block; background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>View Inventory</a></p>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($recipientEmail, $subject, $body, 'low_stock');
    }
}
