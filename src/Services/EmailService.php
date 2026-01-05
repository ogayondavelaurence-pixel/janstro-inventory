<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * ============================================================================
 * EMAIL SERVICE v2.0 - REFACTORED SERVICE LAYER
 * ============================================================================
 * Centralized email operations with proper configuration management
 * ============================================================================
 */
class EmailService
{
    private PDO $db;
    private array $config;
    private bool $enabled;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->loadConfiguration();
    }

    /**
     * Load email configuration from database
     */
    private function loadConfiguration(): void
    {
        try {
            $stmt = $this->db->query("SELECT * FROM email_settings LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config) {
                $this->config = $config;
                $this->enabled = (bool)$config['enabled'];
            } else {
                // Fallback to environment variables
                $this->config = [
                    'enabled' => 1,
                    'smtp_host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
                    'smtp_port' => $_ENV['MAIL_PORT'] ?? 587,
                    'smtp_encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
                    'smtp_username' => $_ENV['MAIL_USERNAME'] ?? '',
                    'smtp_password' => $_ENV['MAIL_PASSWORD'] ?? '',
                    'from_email' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@janstro.com',
                    'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'Janstro IMS',
                    'reply_to' => $_ENV['MAIL_REPLY_TO'] ?? null
                ];
                $this->enabled = true;
            }
        } catch (\Exception $e) {
            error_log("EmailService: Config load failed - " . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Send email (generic method)
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        string $type = 'general',
        ?int $referenceId = null
    ): bool {
        if (!$this->enabled) {
            error_log("EmailService: Disabled");
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("EmailService: Invalid email - {$to}");
            return false;
        }

        // Try SMTP first
        if ($this->hasSMTPConfig()) {
            $sent = $this->sendViaSMTP($to, $subject, $body);
            if ($sent) {
                $this->logEmail($to, $subject, $type, 'sent', null, $referenceId);
                return true;
            }
        }

        // Fallback to PHP mail()
        $sent = $this->sendViaPHPMail($to, $subject, $body);
        $this->logEmail($to, $subject, $type, $sent ? 'sent' : 'failed', null, $referenceId);

        return $sent;
    }

    /**
     * Send invoice email with PDF attachment
     */
    public function sendInvoice(
        array $invoice,
        array $lineItems,
        string $recipientEmail
    ): bool {
        $subject = "Invoice {$invoice['invoice_number']} from Janstro Prime Corporation";
        $body = $this->buildInvoiceTemplate($invoice, $lineItems);

        return $this->send($recipientEmail, $subject, $body, 'invoice', $invoice['invoice_id']);
    }

    /**
     * Send low stock alert
     */
    public function sendLowStockAlert(array $items, string $recipientEmail): bool
    {
        if (empty($items)) {
            return false;
        }

        $subject = "⚠️ Low Stock Alert - " . count($items) . " Items";
        $body = $this->buildLowStockTemplate($items);

        return $this->send($recipientEmail, $subject, $body, 'low_stock');
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(string $email, string $token): bool
    {
        $subject = "🔑 Password Reset Request - Janstro IMS";
        $body = $this->buildPasswordResetTemplate($email, $token);

        return $this->send($email, $subject, $body, 'password_reset');
    }

    /**
     * Send deletion request notification to admins
     */
    public function sendDeletionRequestNotification(array $userData, string $reason): bool
    {
        try {
            $admins = $this->getAdminEmails();

            if (empty($admins)) {
                return false;
            }

            $subject = "🚨 Account Deletion Request - Action Required";
            $body = $this->buildDeletionRequestTemplate($userData, $reason);

            $sentCount = 0;
            foreach ($admins as $adminEmail) {
                if ($this->send($adminEmail, $subject, $body, 'deletion_request')) {
                    $sentCount++;
                }
            }

            return $sentCount > 0;
        } catch (\Exception $e) {
            error_log("Deletion notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send purchase order notification
     */
    public function sendPONotification(array $po, array $supplier): bool
    {
        $subject = "📦 Purchase Order PO-{$po['po_id']} Created";
        $body = $this->buildPOTemplate($po, $supplier);

        // Send to admins
        $admins = $this->getAdminEmails();
        $sentCount = 0;

        foreach ($admins as $email) {
            if ($this->send($email, $subject, $body, 'purchase_order', $po['po_id'])) {
                $sentCount++;
            }
        }

        return $sentCount > 0;
    }

    /**
     * Send via SMTP (PHPMailer)
     */
    private function sendViaSMTP(string $to, string $subject, string $body): bool
    {
        try {
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return false;
            }

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];

            $encryption = strtolower($this->config['smtp_encryption'] ?? 'tls');
            $mail->SMTPSecure = ($encryption === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int)($this->config['smtp_port'] ?? 587);

            // Debug only in development
            $mail->SMTPDebug = ($_ENV['APP_ENV'] ?? 'production') === 'production' ? 0 : 2;

            if ($mail->SMTPDebug > 0) {
                $mail->Debugoutput = function ($str) {
                    error_log("SMTP: $str");
                };
            }

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to);

            if (!empty($this->config['reply_to'])) {
                $mail->addReplyTo($this->config['reply_to']);
            }

            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            return $mail->send();
        } catch (PHPMailerException $e) {
            error_log("SMTP error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send via PHP mail() fallback
     */
    private function sendViaPHPMail(string $to, string $subject, string $body): bool
    {
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>'
            ];

            return mail($to, $subject, $body, implode("\r\n", $headers));
        } catch (\Exception $e) {
            error_log("PHP mail() error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if SMTP is configured
     */
    private function hasSMTPConfig(): bool
    {
        return !empty($this->config['smtp_host'])
            && !empty($this->config['smtp_username'])
            && !empty($this->config['smtp_password']);
    }

    /**
     * Log email send attempt
     */
    private function logEmail(
        string $recipient,
        string $subject,
        string $type,
        string $status,
        ?string $error = null,
        ?int $referenceId = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient, subject, sent_at, status, error_message)
                VALUES (?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([$recipient, $subject, $status, $error]);
        } catch (\Exception $e) {
            error_log("Email log failed: " . $e->getMessage());
        }
    }

    /**
     * Get admin emails
     */
    private function getAdminEmails(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT DISTINCT u.email 
                FROM users u
                JOIN roles r ON u.role_id = r.role_id
                WHERE r.role_name IN ('admin', 'superadmin')
                AND u.status = 'active'
                AND u.email IS NOT NULL
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ========================================================================
    // EMAIL TEMPLATES
    // ========================================================================

    private function buildInvoiceTemplate(array $invoice, array $lineItems): string
    {
        $invoiceNumber = $invoice['invoice_number'];
        $customerName = $invoice['customer_name'];
        $totalAmount = number_format($invoice['total_amount'], 2);
        $dueDate = date('F j, Y', strtotime($invoice['due_date']));

        $itemsHtml = '';
        foreach ($lineItems as $item) {
            $itemTotal = number_format($item['line_total'], 2);
            $itemsHtml .= "<tr><td style='padding:10px;border-bottom:1px solid #ddd;'>{$item['item_name']}</td><td style='padding:10px;border-bottom:1px solid #ddd;text-align:right;'>{$item['quantity']}</td><td style='padding:10px;border-bottom:1px solid #ddd;text-align:right;'>₱{$itemTotal}</td></tr>";
        }

        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:#667eea;padding:20px;text-align:center;color:white;'><h1>Invoice Ready</h1></div><div style='padding:20px;'><p>Dear <strong>{$customerName}</strong>,</p><p>Your invoice is ready:</p><table style='width:100%;border-collapse:collapse;margin:20px 0;'><thead><tr style='background:#f4f4f4;'><th style='padding:10px;text-align:left;'>Item</th><th style='padding:10px;text-align:right;'>Qty</th><th style='padding:10px;text-align:right;'>Amount</th></tr></thead><tbody>{$itemsHtml}</tbody></table><div style='text-align:right;font-size:18px;font-weight:bold;padding:15px;background:#f9f9f9;'>Total: ₱{$totalAmount}</div><p><strong>Invoice:</strong> {$invoiceNumber}<br><strong>Due Date:</strong> {$dueDate}</p></div></body></html>";
    }

    private function buildLowStockTemplate(array $items): string
    {
        $rows = '';
        foreach ($items as $item) {
            $rows .= "<tr><td style='padding:8px;border-bottom:1px solid #ddd;'>{$item['name']}</td><td style='padding:8px;border-bottom:1px solid #ddd;color:#d32f2f;'><strong>{$item['quantity']}</strong></td><td style='padding:8px;border-bottom:1px solid #ddd;'>{$item['reorder_level']}</td></tr>";
        }

        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:#ff9800;padding:20px;text-align:center;color:white;'><h1>⚠️ Low Stock Alert</h1></div><div style='padding:20px;'><p><strong>" . count($items) . " items</strong> are below reorder level:</p><table style='width:100%;border-collapse:collapse;'><thead><tr style='background:#f4f4f4;'><th style='padding:10px;text-align:left;'>Item</th><th style='padding:10px;'>Stock</th><th style='padding:10px;'>Reorder</th></tr></thead><tbody>{$rows}</tbody></table></div></body></html>";
    }

    private function buildPasswordResetTemplate(string $email, string $token): string
    {
        $baseUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:8080/janstro-inventory/frontend';
        $resetLink = "{$baseUrl}/reset-password.html?token={$token}";

        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:#007bff;padding:20px;text-align:center;color:white;'><h1>🔑 Password Reset</h1></div><div style='padding:20px;text-align:center;'><p>Reset password for: <strong>{$email}</strong></p><a href='{$resetLink}' style='display:inline-block;margin:20px 0;padding:15px 40px;background:#007bff;color:white;text-decoration:none;border-radius:5px;'>Reset Password</a><p style='color:#666;font-size:14px;'>Link expires in 1 hour</p></div></body></html>";
    }

    private function buildDeletionRequestTemplate(array $user, string $reason): string
    {
        $approvalLink = "http://localhost:8080/janstro-inventory/frontend/deletion-approvals.html";

        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:#dc3545;padding:20px;text-align:center;color:white;'><h1>🚨 Account Deletion Request</h1></div><div style='padding:20px;'><p><strong>User:</strong> {$user['username']}<br><strong>Name:</strong> {$user['name']}<br><strong>Email:</strong> {$user['email']}</p><div style='background:#fff3cd;padding:15px;border-left:4px solid #ffc107;margin:20px 0;'><strong>Reason:</strong> {$reason}</div><div style='text-align:center;'><a href='{$approvalLink}' style='display:inline-block;padding:15px 30px;background:#dc3545;color:white;text-decoration:none;border-radius:5px;'>Review Request</a></div></div></body></html>";
    }

    private function buildPOTemplate(array $po, array $supplier): string
    {
        $poNumber = 'PO-' . str_pad($po['po_id'], 6, '0', STR_PAD_LEFT);

        return "<!DOCTYPE html><html><body style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'><div style='background:#42a5f5;padding:20px;text-align:center;color:white;'><h1>📦 Purchase Order</h1></div><div style='padding:20px;'><p><strong>PO Number:</strong> {$poNumber}<br><strong>Supplier:</strong> {$supplier['supplier_name']}<br><strong>Amount:</strong> ₱" . number_format($po['total_amount'], 2) . "</p></div></body></html>";
    }
}
