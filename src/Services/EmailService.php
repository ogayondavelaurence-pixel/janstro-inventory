<?php

/**
 * ============================================================================
 * COMPLETE EMAIL SERVICE v7.0 - ENHANCED DELETION NOTIFICATIONS
 * ============================================================================
 * CRITICAL FIXES:
 * ✅ Detailed logging for deletion request emails
 * ✅ Email configuration validation
 * ✅ Fallback to PHP mail() if SMTP fails
 * ✅ Better error messages
 * ✅ Configuration diagnostics
 * ============================================================================
 */

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;


// ✅ CORRECT PHPMailer imports
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


class EmailService
{
    private PDO $db;
    private array $smtpConfig;
    private bool $enabled;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->loadConfig();
        $this->logConfiguration();
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
                error_log("✅ EmailService: Configuration loaded from database");
            } else {
                // Fallback to environment variables
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
                error_log("⚠️ EmailService: No database config found, using environment/defaults");
            }
        } catch (\Exception $e) {
            error_log("❌ EmailService config load error: " . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Log current email configuration (for debugging)
     */
    private function logConfiguration(): void
    {
        error_log("========================================");
        error_log("📧 EMAIL SERVICE CONFIGURATION");
        error_log("========================================");
        error_log("Enabled: " . ($this->enabled ? 'YES' : 'NO'));
        error_log("SMTP Host: " . ($this->smtpConfig['smtp_host'] ?? 'Not set'));
        error_log("SMTP Port: " . ($this->smtpConfig['smtp_port'] ?? 'Not set'));
        error_log("From Email: " . ($this->smtpConfig['from_email'] ?? 'Not set'));
        error_log("From Name: " . ($this->smtpConfig['from_name'] ?? 'Not set'));
        error_log("========================================");
    }

    
    // ========================================================================
    // ✅ NEW METHOD: SEND INVOICE EMAIL (CRITICAL FIX)
    // ========================================================================

    /**
     * Send invoice email to customer
     * 
     * @param array $invoice Invoice data
     * @param array $lineItems Array of invoice line items
     * @param string $recipientEmail Customer email address
     * @return bool Success status
     */
    public function sendInvoiceEmail(array $invoice, array $lineItems, string $recipientEmail): bool
    {
        if (!$this->enabled) {
            error_log("❌ Email service disabled - cannot send invoice email");
            return false;
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid recipient email: {$recipientEmail}");
            return false;
        }

        error_log("========================================");
        error_log("📧 SENDING INVOICE EMAIL");
        error_log("========================================");
        error_log("Invoice: {$invoice['invoice_number']}");
        error_log("Customer: {$invoice['customer_name']}");
        error_log("To: {$recipientEmail}");
        error_log("Amount: PHP " . number_format($invoice['total_amount'], 2));
        error_log("========================================");

        $subject = "Invoice {$invoice['invoice_number']} from Janstro Prime Corporation";
        $body = $this->getInvoiceEmailTemplate($invoice, $lineItems);

        $sent = $this->sendEmail(
            $recipientEmail,
            $subject,
            $body,
            'invoice',
            $invoice['invoice_id']
        );

        error_log($sent ? "✅ Invoice email sent successfully" : "❌ Invoice email failed");
        error_log("========================================");

        return $sent;
    }

    /**
     * Generate HTML template for invoice email
     */
    private function getInvoiceEmailTemplate(array $invoice, array $lineItems): string
    {
        $invoiceNumber = $invoice['invoice_number'];
        $customerName = $invoice['customer_name'];
        $totalAmount = number_format($invoice['total_amount'], 2);
        $subtotal = number_format($invoice['subtotal'], 2);
        $taxAmount = number_format($invoice['tax_amount'], 2);
        $taxRate = $invoice['tax_rate'];
        $dueDate = date('F j, Y', strtotime($invoice['due_date']));
        $invoiceDate = date('F j, Y', strtotime($invoice['generated_at']));

        // Build line items table
        $itemsHtml = '';
        foreach ($lineItems as $item) {
            $itemTotal = number_format($item['line_total'], 2);
            $unitPrice = number_format($item['unit_price'], 2);

            $itemsHtml .= "
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb;'>{$item['item_name']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: center;'>{$item['quantity']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;'>₱{$unitPrice}</td>
                <td style='padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: right;'><strong>₱{$itemTotal}</strong></td>
            </tr>";
        }

        // Payment status styling
        $statusColor = $invoice['payment_status'] === 'paid' ? '#10b981' : '#ef4444';
        $statusText = strtoupper($invoice['payment_status']);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; line-height: 1.6; color: #1f2937; margin: 0; padding: 0; background-color: #f3f4f6; }
                .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
                .header p { margin: 10px 0 0 0; font-size: 16px; opacity: 0.95; }
                .content { padding: 40px 30px; }
                .invoice-details { background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
                .invoice-details p { margin: 8px 0; }
                .items-table { width: 100%; border-collapse: collapse; margin: 30px 0; }
                .items-table thead { background: #667eea; color: white; }
                .items-table th { padding: 12px; text-align: left; font-weight: 600; font-size: 14px; }
                .items-table tbody tr:hover { background: #f9fafb; }
                .totals { margin: 30px 0; padding: 20px; background: #f9fafb; border-radius: 8px; }
                .totals-row { display: flex; justify-content: space-between; padding: 8px 0; }
                .totals-row.final { border-top: 2px solid #667eea; padding-top: 15px; margin-top: 10px; font-size: 18px; font-weight: 700; color: #667eea; }
                .status-badge { display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; color: white; background: {$statusColor}; margin: 20px 0; }
                .btn { display: inline-block; background: #667eea; color: white; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                .btn:hover { background: #5568d3; }
                .footer { background: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb; }
                .footer p { margin: 8px 0; font-size: 13px; color: #6b7280; }
                @media only screen and (max-width: 600px) {
                    .content { padding: 20px 15px; }
                    .header { padding: 30px 15px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>☀️ Invoice Ready</h1>
                    <p>Janstro Prime Corporation</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$customerName}</strong>,</p>
                    
                    <p>Thank you for your business! Your invoice is ready for review and payment.</p>
                    
                    <div class='invoice-details'>
                        <p><strong>Invoice Number:</strong> {$invoiceNumber}</p>
                        <p><strong>Invoice Date:</strong> {$invoiceDate}</p>
                        <p><strong>Due Date:</strong> {$dueDate}</p>
                        <p><strong>Payment Terms:</strong> {$invoice['payment_terms']}</p>
                    </div>

                    <h3 style='color: #667eea; margin-top: 30px;'>Invoice Items</h3>
                    <table class='items-table'>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th style='text-align: center;'>Qty</th>
                                <th style='text-align: right;'>Unit Price</th>
                                <th style='text-align: right;'>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsHtml}
                        </tbody>
                    </table>

                    <div class='totals'>
                        <div class='totals-row'>
                            <span>Subtotal:</span>
                            <span>₱{$subtotal}</span>
                        </div>
                        <div class='totals-row'>
                            <span>Tax ({$taxRate}%):</span>
                            <span>₱{$taxAmount}</span>
                        </div>
                        <div class='totals-row final'>
                            <span>TOTAL DUE:</span>
                            <span>₱{$totalAmount}</span>
                        </div>
                    </div>

                    <div style='text-align: center;'>
                        <span class='status-badge'>STATUS: {$statusText}</span>
                    </div>

                    <p style='margin-top: 30px;'><strong>Payment Instructions:</strong></p>
                    <ul style='color: #6b7280;'>
                        <li>Payment is due by <strong>{$dueDate}</strong></li>
                        <li>Please reference invoice number <strong>{$invoiceNumber}</strong> when making payment</li>
                        <li>Contact us if you have any questions about this invoice</li>
                    </ul>

                    <p style='margin-top: 30px; color: #6b7280; font-size: 14px;'>
                        If you have any questions about this invoice, please contact us at 
                        <a href='mailto:janstroprime@gmail.com' style='color: #667eea;'>janstroprime@gmail.com</a> 
                        or call <strong>+63 999 759 4616</strong>.
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>Janstro Prime Corporation</strong></p>
                    <p>Palo Alto Bay Hill Executive Subdivision</p>
                    <p>Calamba, Laguna, Philippines</p>
                    <p style='margin-top: 15px;'>
                        <a href='https://janstrosolar.wixsite.com/website' style='color: #667eea;'>Visit Our Website</a>
                    </p>
                    <p style='margin-top: 15px; font-size: 12px; color: #9ca3af;'>
                        This is an automated email. Please do not reply directly to this message.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    // ========================================================================
    // DELETION REQUEST NOTIFICATION (Superadmin Alert) - ENHANCED v7.0
    // ========================================================================

    /**
     * Send notification to all superadmins when user requests account deletion
     */
    public function sendDeletionRequestNotification(array $userData, string $reason): bool
    {
        error_log("========================================");
        error_log("📧 SENDING DELETION REQUEST NOTIFICATION");
        error_log("========================================");

        // Check if email service is enabled
        if (!$this->enabled) {
            error_log("❌ Email service is DISABLED in configuration");
            error_log("   Fix: Enable email in email_settings table or .env");
            return false;
        }

        error_log("✅ Email service is enabled");

        try {
            // Get all superadmin emails
            error_log("📥 Fetching superadmin email addresses...");

            $stmt = $this->db->prepare("
                SELECT u.email, u.name, u.username
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE r.role_name = 'superadmin' 
                AND u.status = 'active'
                AND u.email IS NOT NULL
                AND u.email != ''
            ");
            $stmt->execute();
            $superadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("📊 Found " . count($superadmins) . " superadmin(s) with valid emails");

            if (empty($superadmins)) {
                error_log("❌ NO SUPERADMIN EMAILS FOUND");
                error_log("   Reason: No active superadmins with email addresses");
                error_log("   Fix: Add email addresses to superadmin accounts in database");
                return false;
            }

            // Display found superadmins
            foreach ($superadmins as $admin) {
                error_log("   → {$admin['name']} ({$admin['username']}) <{$admin['email']}>");
            }

            // Build email content
            error_log("📝 Building email content...");
            $subject = "🚨 Account Deletion Request - Action Required";
            $body = $this->getDeletionRequestTemplate($userData, $reason);

            error_log("✅ Email content prepared");
            error_log("   Subject: {$subject}");
            error_log("   Body Length: " . strlen($body) . " characters");

            // Send to each superadmin
            $sentCount = 0;
            $failedCount = 0;

            error_log("📤 Sending emails to superadmins...");

            foreach ($superadmins as $admin) {
                error_log("   📧 Sending to: {$admin['email']}...");

                $sent = $this->sendEmail(
                    $admin['email'],
                    $subject,
                    $body,
                    'deletion_request'
                );

                if ($sent) {
                    $sentCount++;
                    error_log("      ✅ SUCCESS");
                } else {
                    $failedCount++;
                    error_log("      ❌ FAILED");
                }
            }

            error_log("========================================");
            error_log("📊 DELETION EMAIL SUMMARY");
            error_log("========================================");
            error_log("Total Superadmins: " . count($superadmins));
            error_log("Emails Sent: {$sentCount}");
            error_log("Emails Failed: {$failedCount}");
            error_log("========================================");

            return $sentCount > 0;
        } catch (\Exception $e) {
            error_log("========================================");
            error_log("❌ DELETION EMAIL ERROR");
            error_log("========================================");
            error_log("Error Type: " . get_class($e));
            error_log("Error Message: " . $e->getMessage());
            error_log("Error File: " . $e->getFile() . ":" . $e->getLine());
            error_log("========================================");
            return false;
        }
    }

    /**
     * Deletion request email template
     */
    private function getDeletionRequestTemplate(array $user, string $reason): string
    {
        $approvalLink = "http://localhost:8080/janstro-inventory/frontend/deletion-approvals.html";
        $requestDate = date('F j, Y g:i A');

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 40px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; }
                .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .reason-box { background: #fff; border: 2px solid #dc3545; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .btn { display: inline-block; background: #dc3545; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .btn:hover { background: #c82333; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🚨 Account Deletion Request</h1>
                    <p>Superadmin Action Required</p>
                </div>
                
                <div class='content'>
                    <div class='alert-box'>
                        <strong>⚠️ A user has requested permanent account deletion.</strong><br>
                        This request requires your immediate attention and approval.
                    </div>
                    
                    <h3>📋 Request Details</h3>
                    <div class='info-box'>
                        <p><strong>User ID:</strong> {$user['user_id']}</p>
                        <p><strong>Username:</strong> {$user['username']}</p>
                        <p><strong>Name:</strong> {$user['name']}</p>
                        <p><strong>Email:</strong> {$user['email']}</p>
                        <p><strong>Role:</strong> {$user['role_name']}</p>
                        <p><strong>Request Date:</strong> {$requestDate}</p>
                    </div>
                    
                    <h3>📝 Reason for Deletion</h3>
                    <div class='reason-box'>
                        <p>{$reason}</p>
                    </div>
                    
                    <h3>⚠️ What Happens If Approved:</h3>
                    <ul>
                        <li>All personal data will be permanently deleted</li>
                        <li>Transaction history will be archived for audit purposes</li>
                        <li>All active sessions will be terminated</li>
                        <li>User will lose access immediately</li>
                        <li><strong>This action cannot be undone</strong></li>
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$approvalLink}' class='btn'>
                            🔍 Review Deletion Request
                        </a>
                    </div>
                    
                    <p style='font-size: 13px; color: #6c757d;'>
                        <strong>Note:</strong> Please review this request carefully before taking action. 
                        You can approve or reject this request from the Deletion Approvals page.
                    </p>
                </div>
                
                <div class='footer'>
                    <p><strong>Janstro IMS Security Alert</strong></p>
                    <p>This is an automated notification. Please do not reply.</p>
                    <p>Generated on {$requestDate}</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // DELETION APPROVED NOTIFICATION (User Confirmation)
    // ========================================================================

    /**
     * Send confirmation to user when deletion is approved
     */
    public function sendDeletionApprovedNotification(array $userData): bool
    {
        if (!$this->enabled) return false;

        $subject = "Account Deletion Approved - Janstro IMS";
        $body = $this->getDeletionApprovedTemplate($userData);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'deletion_approved'
        );
    }

    private function getDeletionApprovedTemplate(array $user): string
    {
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
                .info-box { background: #d4edda; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Deletion Request Approved</h1>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <div class='info-box'>
                        <strong>Your account deletion request has been approved.</strong>
                    </div>
                    
                    <p>Your Janstro IMS account (<strong>{$user['username']}</strong>) will be permanently deleted within the next 24 hours.</p>
                    
                    <h3>What's Been Removed:</h3>
                    <ul>
                        <li>Personal profile information</li>
                        <li>All active sessions and access permissions</li>
                        <li>Account preferences and settings</li>
                    </ul>
                    
                    <h3>What's Been Retained (for audit purposes):</h3>
                    <ul>
                        <li>Transaction history (archived, anonymized)</li>
                        <li>Audit logs (for compliance)</li>
                    </ul>
                    
                    <p>If you believe this was done in error, please contact your system administrator immediately at <strong>janstroprime@gmail.com</strong>.</p>
                    
                    <p>Thank you for using Janstro IMS.</p>
                </div>
                
                <div class='footer'>
                    <p>Janstro Prime Corporation</p>
                    <p>Account deleted on " . date('F j, Y g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // DELETION REJECTED NOTIFICATION (User Notification)
    // ========================================================================

    /**
     * Send notification when deletion request is rejected
     */
    public function sendDeletionRejectedNotification(array $userData): bool
    {
        if (!$this->enabled) return false;

        $subject = "Account Deletion Request Rejected - Janstro IMS";
        $body = $this->getDeletionRejectedTemplate($userData);

        return $this->sendEmail(
            $userData['email'],
            $subject,
            $body,
            'deletion_rejected'
        );
    }

    private function getDeletionRejectedTemplate(array $user): string
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 20px auto; background: #fff; }
                .header { background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%); color: white; padding: 40px 20px; text-align: center; }
                .content { padding: 30px; }
                .info-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 20px; margin: 20px 0; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Deletion Request Rejected</h1>
                </div>
                
                <div class='content'>
                    <p>Hello <strong>{$user['name']}</strong>,</p>
                    
                    <div class='info-box'>
                        <strong>Your account deletion request has been rejected by a system administrator.</strong>
                    </div>
                    
                    <p>Your Janstro IMS account (<strong>{$user['username']}</strong>) remains active and accessible.</p>
                    
                    <h3>What This Means:</h3>
                    <ul>
                        <li>Your account and data have not been modified</li>
                        <li>All permissions and access remain unchanged</li>
                        <li>You can continue using the system normally</li>
                    </ul>
                    
                    <p>If you still wish to delete your account or have questions about this decision, please contact your system administrator at <strong>janstroprime@gmail.com</strong>.</p>
                </div>
                
                <div class='footer'>
                    <p>Janstro Prime Corporation</p>
                    <p>Notification sent on " . date('F j, Y g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    // ========================================================================
    // WELCOME EMAIL (User Creation)
    // ========================================================================

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
    // PASSWORD RESET LINK
    // ========================================================================

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

    /**
     * ========================================================================
     * CORE SEND EMAIL METHOD - FIXED v7.2
     * ========================================================================
     */
    private function sendEmail(
        string $to,
        string $subject,
        string $body,
        string $type = 'general',
        ?int $referenceId = null
    ): bool {
        error_log("========================================");
        error_log("📧 SENDING EMAIL v7.2");
        error_log("========================================");
        error_log("To: {$to}");
        error_log("Subject: {$subject}");
        error_log("Type: {$type}");

        if (!$this->enabled) {
            error_log("❌ Email sending disabled in configuration");
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid email address: {$to}");
            return false;
        }

        // ✅ TRY SMTP FIRST (if configured)
        if (!empty($this->smtpConfig['smtp_host']) && !empty($this->smtpConfig['smtp_username'])) {
            error_log("📤 Attempting SMTP send...");
            error_log("   Host: " . $this->smtpConfig['smtp_host']);
            error_log("   Port: " . $this->smtpConfig['smtp_port']);
            error_log("   Username: " . $this->smtpConfig['smtp_username']);
            error_log("   From: " . $this->smtpConfig['from_email']);

            $sent = $this->sendViaSMTP($to, $subject, $body);

            if ($sent) {
                error_log("✅ Email sent via SMTP successfully");
                $this->logEmail($to, $subject, $type, 'sent');
                error_log("========================================");
                return true;
            } else {
                error_log("⚠️ SMTP failed, trying PHP mail() fallback...");
            }
        } else {
            error_log("⚠️ SMTP not fully configured:");
            error_log("   Host: " . ($this->smtpConfig['smtp_host'] ?? 'NOT SET'));
            error_log("   Username: " . ($this->smtpConfig['smtp_username'] ?? 'NOT SET'));
            error_log("   Trying PHP mail() fallback...");
        }

        // ✅ FALLBACK TO PHP mail()
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->smtpConfig['from_name'] . ' <' . $this->smtpConfig['from_email'] . '>',
                'Reply-To: ' . $this->smtpConfig['from_email'],
                'X-Mailer: PHP/' . phpversion()
            ];

            $sent = mail($to, $subject, $body, implode("\r\n", $headers));

            if ($sent) {
                error_log("✅ Email sent via PHP mail()");
                $this->logEmail($to, $subject, $type, 'sent');
            } else {
                error_log("❌ PHP mail() also failed");
                $this->logEmail($to, $subject, $type, 'failed', 'mail() returned false');
            }

            error_log("========================================");
            return $sent;
        } catch (\Exception $e) {
            error_log("❌ Email exception: " . $e->getMessage());
            $this->logEmail($to, $subject, $type, 'failed', $e->getMessage());
            error_log("========================================");
            return false;
        }
    }

    /**
     * ========================================================================
     * SEND VIA SMTP (PHPMailer) - FIXED v7.2
     * ========================================================================
     */
    private function sendViaSMTP(string $to, string $subject, string $body): bool
    {
        try {
            // ✅ Check if PHPMailer is available
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("❌ PHPMailer class not found");
                return false;
            }

            $mail = new PHPMailer(true);

            // ✅ SMTP Configuration
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['smtp_username'];
            $mail->Password = $this->smtpConfig['smtp_password'];

            // Encryption
            $encryption = strtolower($this->smtpConfig['smtp_encryption'] ?? 'tls');
            if ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->Port = (int)($this->smtpConfig['smtp_port'] ?? 587);

            // ✅ Enable debugging in development
            $appEnv = $_ENV['APP_ENV'] ?? 'production';
            if ($appEnv !== 'production') {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("SMTP Debug: $str");
                };
            }

            // ✅ Sender
            $mail->setFrom(
                $this->smtpConfig['from_email'],
                $this->smtpConfig['from_name']
            );

            // ✅ Recipient
            $mail->addAddress($to);

            // ✅ Reply-To
            if (!empty($this->smtpConfig['reply_to'])) {
                $mail->addReplyTo($this->smtpConfig['reply_to']);
            }

            // ✅ Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;

            // Plain text version
            $mail->AltBody = strip_tags($body);

            // ✅ SEND
            $result = $mail->send();

            if ($result) {
                error_log("✅ PHPMailer: Email sent successfully");
                return true;
            } else {
                error_log("❌ PHPMailer: send() returned false");
                error_log("   Error Info: " . $mail->ErrorInfo);
                return false;
            }
        } catch (Exception $e) {
            error_log("❌ PHPMailer Exception: " . $e->getMessage());
            if (isset($mail)) {
                error_log("   Error Info: " . $mail->ErrorInfo);
            }
            return false;
        } catch (\Exception $e) {
            error_log("❌ SMTP General Exception: " . $e->getMessage());
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
            (recipient, subject, status, sent_at, error_message)
            VALUES (?, ?, ?, NOW(), ?)
        ");

            $stmt->execute([
                $recipient,
                $subject,
                $status,
                $error
            ]);
        } catch (\Exception $e) {
            error_log("Email log failed: " . $e->getMessage());
        }
    }

    // ========================================================================
    // PASSWORD CHANGE CONFIRMATION
    // ========================================================================

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
    // ACCOUNT LOCKOUT ALERT
    // ========================================================================

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
    // LOW STOCK ALERT
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
                    <p><a href='http://localhost:8080/janstro-inventory/frontend/materials.html' style='display: inline-block; background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;'>View Inventory</a></p>
                </div>
            </div>
        </body>
        </html>";

        return $this->sendEmail($recipientEmail, $subject, $body, 'low_stock');
    }
}
