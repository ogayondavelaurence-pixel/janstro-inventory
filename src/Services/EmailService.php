<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * ============================================================================
 * EMAIL SERVICE - PRODUCTION READY v7.3
 * ============================================================================
 * FIXES:
 * ✅ Environment-based SMTP debug toggle
 * ✅ Proper configuration validation
 * ✅ APCu-compatible error handling
 * ✅ Public send() method for external services
 * ============================================================================
 */
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

    private function loadConfig(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config) {
                if (!empty($config['smtp_host']) && !empty($config['smtp_username'])) {
                    if (empty($config['smtp_password'])) {
                        error_log("⚠️ SMTP password not configured");
                        $this->enabled = false;
                    }
                }

                $this->enabled = (bool)$config['enabled'];
                $this->smtpConfig = $config;
                error_log("✅ EmailService: Configuration loaded");
            } else {
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
                error_log("⚠️ Using environment/default config");
            }
        } catch (\Exception $e) {
            error_log("❌ EmailService config error: " . $e->getMessage());
            $this->enabled = false;
        }
    }

    private function logConfiguration(): void
    {
        error_log("========================================");
        error_log("📧 EMAIL SERVICE CONFIGURATION");
        error_log("========================================");
        error_log("Enabled: " . ($this->enabled ? 'YES' : 'NO'));
        error_log("SMTP Host: " . ($this->smtpConfig['smtp_host'] ?? 'Not set'));
        error_log("SMTP Port: " . ($this->smtpConfig['smtp_port'] ?? 'Not set'));
        error_log("From Email: " . ($this->smtpConfig['from_email'] ?? 'Not set'));
        error_log("========================================");
    }

    // ========================================================================
    // PUBLIC SEND METHOD (for external services)
    // ========================================================================

    public function send(string $to, string $subject, string $body, string $type = 'general'): bool
    {
        return $this->sendEmail($to, $subject, $body, $type);
    }

    // ========================================================================
    // INVOICE EMAIL
    // ========================================================================

    public function sendInvoiceEmail(array $invoice, array $lineItems, string $recipientEmail): bool
    {
        if (!$this->enabled) {
            error_log("❌ Email service disabled");
            return false;
        }

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            error_log("❌ Invalid email: {$recipientEmail}");
            return false;
        }

        $subject = "Invoice {$invoice['invoice_number']} from Janstro Prime Corporation";
        $body = $this->getInvoiceEmailTemplate($invoice, $lineItems);

        return $this->sendEmail($recipientEmail, $subject, $body, 'invoice', $invoice['invoice_id']);
    }

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

        $itemsHtml = '';
        foreach ($lineItems as $item) {
            $itemTotal = number_format($item['line_total'], 2);
            $unitPrice = number_format($item['unit_price'], 2);
            $itemsHtml .= "<tr>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;'>{$item['item_name']}</td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;'>₱{$unitPrice}</td>
                <td style='padding:12px;border-bottom:1px solid #e5e7eb;text-align:right;'><strong>₱{$itemTotal}</strong></td>
            </tr>";
        }

        $statusColor = $invoice['payment_status'] === 'paid' ? '#10b981' : '#ef4444';
        $statusText = strtoupper($invoice['payment_status']);

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{font-family:'Helvetica',Arial,sans-serif;line-height:1.6;color:#1f2937;margin:0;padding:0;background:#f3f4f6}.container{max-width:600px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1)}.header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;padding:40px 20px;text-align:center}.header h1{margin:0;font-size:28px}.content{padding:40px 30px}.invoice-details{background:#f9fafb;padding:20px;border-radius:8px;margin:20px 0;border-left:4px solid #667eea}.items-table{width:100%;border-collapse:collapse;margin:30px 0}.items-table thead{background:#667eea;color:white}.items-table th{padding:12px;text-align:left;font-weight:600}.totals{margin:30px 0;padding:20px;background:#f9fafb;border-radius:8px}.totals-row{display:flex;justify-content:space-between;padding:8px 0}.totals-row.final{border-top:2px solid #667eea;padding-top:15px;margin-top:10px;font-size:18px;font-weight:700;color:#667eea}.status-badge{display:inline-block;padding:8px 16px;border-radius:20px;font-size:12px;font-weight:700;color:white;background:{$statusColor};margin:20px 0}.footer{background:#f9fafb;padding:30px;text-align:center;border-top:1px solid #e5e7eb}.footer p{margin:8px 0;font-size:13px;color:#6b7280}</style></head><body><div class='container'><div class='header'><h1>☀️ Invoice Ready</h1><p>Janstro Prime Corporation</p></div><div class='content'><p>Dear <strong>{$customerName}</strong>,</p><p>Thank you for your business! Your invoice is ready.</p><div class='invoice-details'><p><strong>Invoice Number:</strong> {$invoiceNumber}</p><p><strong>Invoice Date:</strong> {$invoiceDate}</p><p><strong>Due Date:</strong> {$dueDate}</p><p><strong>Payment Terms:</strong> {$invoice['payment_terms']}</p></div><h3 style='color:#667eea;margin-top:30px;'>Invoice Items</h3><table class='items-table'><thead><tr><th>Item</th><th style='text-align:center;'>Qty</th><th style='text-align:right;'>Unit Price</th><th style='text-align:right;'>Total</th></tr></thead><tbody>{$itemsHtml}</tbody></table><div class='totals'><div class='totals-row'><span>Subtotal:</span><span>₱{$subtotal}</span></div><div class='totals-row'><span>Tax ({$taxRate}%):</span><span>₱{$taxAmount}</span></div><div class='totals-row final'><span>TOTAL DUE:</span><span>₱{$totalAmount}</span></div></div><div style='text-align:center;'><span class='status-badge'>STATUS: {$statusText}</span></div><p style='margin-top:30px;'><strong>Payment Instructions:</strong></p><ul style='color:#6b7280;'><li>Payment due by <strong>{$dueDate}</strong></li><li>Reference invoice <strong>{$invoiceNumber}</strong></li></ul></div><div class='footer'><p><strong>Janstro Prime Corporation</strong></p><p>Palo Alto Bay Hill, Calamba, Laguna</p></div></div></body></html>";
    }

    // ========================================================================
    // DELETION NOTIFICATIONS
    // ========================================================================

    public function sendDeletionRequestNotification(array $userData, string $reason): bool
    {
        if (!$this->enabled) return false;

        try {
            $stmt = $this->db->prepare("
                SELECT u.email, u.name, u.username
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE r.role_name = 'superadmin' 
                AND u.status = 'active'
                AND u.email IS NOT NULL
            ");
            $stmt->execute();
            $superadmins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($superadmins)) return false;

            $subject = "🚨 Account Deletion Request - Action Required";
            $body = $this->getDeletionRequestTemplate($userData, $reason);

            $sentCount = 0;
            foreach ($superadmins as $admin) {
                if ($this->sendEmail($admin['email'], $subject, $body, 'deletion_request')) {
                    $sentCount++;
                }
            }

            return $sentCount > 0;
        } catch (\Exception $e) {
            error_log("Deletion notification error: " . $e->getMessage());
            return false;
        }
    }

    private function getDeletionRequestTemplate(array $user, string $reason): string
    {
        $approvalLink = "http://localhost:8080/janstro-inventory/frontend/deletion-approvals.html";
        $requestDate = date('F j, Y g:i A');

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;'><div style='max-width:600px;margin:20px auto;background:#fff;'><div style='background:linear-gradient(135deg,#dc3545,#c82333);color:white;padding:40px 20px;text-align:center;'><h1>🚨 Account Deletion Request</h1></div><div style='padding:30px;'><div style='background:#fff3cd;border-left:4px solid #ffc107;padding:20px;margin:20px 0;'><strong>⚠️ A user has requested permanent account deletion.</strong></div><h3>📋 Request Details</h3><div style='background:#f8f9fa;padding:20px;border-radius:8px;'><p><strong>User:</strong> {$user['username']}</p><p><strong>Name:</strong> {$user['name']}</p><p><strong>Email:</strong> {$user['email']}</p><p><strong>Date:</strong> {$requestDate}</p></div><h3>📝 Reason</h3><div style='background:#fff;border:2px solid #dc3545;padding:15px;border-radius:8px;'><p>{$reason}</p></div><div style='text-align:center;margin:30px 0;'><a href='{$approvalLink}' style='display:inline-block;background:#dc3545;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;'>Review Request</a></div></div></div></body></html>";
    }

    public function sendPasswordResetLink(string $email, string $token): bool
    {
        if (!$this->enabled) return false;

        $subject = "🔑 Password Reset Request - Janstro IMS";
        $body = $this->getPasswordResetTemplate($email, $token);

        return $this->sendEmail($email, $subject, $body, 'password_reset');
    }

    private function getPasswordResetTemplate(string $email, string $token): string
    {
        $baseUrl = $_ENV['FRONTEND_URL'] ?? $_ENV['APP_URL'] ?? null;

        if (!$baseUrl) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = (strpos($host, 'localhost') !== false)
                ? 'http://localhost:8080/janstro-inventory/frontend'
                : $protocol . '://' . $host;
        }

        $resetLink = "{$baseUrl}/reset-password.html?token={$token}";

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;'><div style='max-width:600px;margin:20px auto;background:#fff;'><div style='background:linear-gradient(135deg,#007bff,#0056b3);color:white;padding:40px 20px;text-align:center;'><h1>🔑 Password Reset</h1></div><div style='padding:30px;'><p>Reset password for: <strong>{$email}</strong></p><div style='text-align:center;'><a href='{$resetLink}' style='display:inline-block;background:#007bff;color:white;padding:15px 40px;text-decoration:none;border-radius:5px;'>Reset Password</a></div><div style='background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;'><strong>⚠️ Link expires in 1 hour</strong></div></div></div></body></html>";
    }

    public function sendLowStockAlert(array $items, string $recipientEmail): bool
    {
        if (!$this->enabled || empty($items)) return false;

        $itemsList = '';
        foreach ($items as $item) {
            $shortage = ($item['reorder_level'] ?? 0) - ($item['quantity'] ?? 0);
            $itemsList .= "<tr><td style='padding:10px;border-bottom:1px solid #ddd;'>{$item['name']}</td><td style='padding:10px;border-bottom:1px solid #ddd;color:#dc3545;'><strong>{$item['quantity']}</strong></td><td style='padding:10px;border-bottom:1px solid #ddd;'>{$item['reorder_level']}</td><td style='padding:10px;border-bottom:1px solid #ddd;color:#dc3545;'>{$shortage}</td></tr>";
        }

        $subject = "⚠️ Low Stock Alert - " . count($items) . " Items";
        $body = "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;'><div style='max-width:600px;margin:0 auto;'><div style='background:linear-gradient(135deg,#dc3545,#c82333);color:white;padding:30px;text-align:center;'><h1>⚠️ Low Stock Alert</h1></div><div style='padding:30px;background:#fff;'><p><strong>" . count($items) . " items</strong> below reorder level:</p><table style='width:100%;border-collapse:collapse;margin:20px 0;'><thead><tr style='background:#f8f9fa;'><th style='padding:12px;text-align:left;'>Item</th><th style='padding:12px;text-align:left;'>Stock</th><th style='padding:12px;text-align:left;'>Reorder</th><th style='padding:12px;text-align:left;'>Shortage</th></tr></thead><tbody>{$itemsList}</tbody></table></div></div></body></html>";

        return $this->sendEmail($recipientEmail, $subject, $body, 'low_stock');
    }

    // ========================================================================
    // CORE EMAIL SENDING
    // ========================================================================

    private function sendEmail(string $to, string $subject, string $body, string $type = 'general', ?int $referenceId = null): bool
    {
        if (!$this->enabled) return false;
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        // Try SMTP first
        if (!empty($this->smtpConfig['smtp_host']) && !empty($this->smtpConfig['smtp_username'])) {
            $sent = $this->sendViaSMTP($to, $subject, $body);
            if ($sent) {
                $this->logEmail($to, $subject, $type, 'sent');
                return true;
            }
        }

        // Fallback to PHP mail()
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->smtpConfig['from_name'] . ' <' . $this->smtpConfig['from_email'] . '>',
            ];

            $sent = mail($to, $subject, $body, implode("\r\n", $headers));
            $this->logEmail($to, $subject, $type, $sent ? 'sent' : 'failed');
            return $sent;
        } catch (\Exception $e) {
            $this->logEmail($to, $subject, $type, 'failed', $e->getMessage());
            return false;
        }
    }

    private function sendViaSMTP(string $to, string $subject, string $body): bool
    {
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return false;
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtpConfig['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpConfig['smtp_username'];
            $mail->Password = $this->smtpConfig['smtp_password'];

            $encryption = strtolower($this->smtpConfig['smtp_encryption'] ?? 'tls');
            $mail->SMTPSecure = ($encryption === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($this->smtpConfig['smtp_port'] ?? 587);

            // ✅ FIX: Environment-based debug
            $mail->SMTPDebug = ($_ENV['APP_ENV'] ?? 'production') === 'production' ? 0 : 2;

            if ($mail->SMTPDebug > 0) {
                $mail->Debugoutput = function ($str) {
                    error_log("SMTP: $str");
                };
            }

            $mail->setFrom($this->smtpConfig['from_email'], $this->smtpConfig['from_name']);
            $mail->addAddress($to);

            if (!empty($this->smtpConfig['reply_to'])) {
                $mail->addReplyTo($this->smtpConfig['reply_to']);
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (Exception $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }

    private function logEmail(string $recipient, string $subject, string $type, string $status, ?string $error = null): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient, subject, status, sent_at, error_message)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([$recipient, $subject, $status, $error]);
        } catch (\Exception $e) {
            error_log("Email log failed: " . $e->getMessage());
        }
    }
}
