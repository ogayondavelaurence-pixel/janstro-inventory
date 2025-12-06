<?php

namespace Janstro\InventorySystem\Services;

class NotificationService
{
    public static function sendInquiryNotification(array $inquiry): bool
    {
        $staffEmail = $_ENV['STAFF_EMAIL'] ?? 'janstroprimecorporation@gmail.com';
        $ref = 'INQ-' . str_pad($inquiry['inquiry_id'], 6, '0', STR_PAD_LEFT);

        $subject = "New Solar Inquiry - $ref - {$inquiry['customer_name']}";

        $message = self::buildEmailTemplate($inquiry, $ref);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Janstro IMS <noreply@janstro.com>',
            'Reply-To: ' . $inquiry['email'],
            'X-Mailer: PHP/' . phpversion()
        ];

        // Try sending via PHP mail() WITHOUT suppression
        $sent = mail($staffEmail, $subject, $message, implode("\r\n", $headers));
        if (!$sent) {
            error_log("Mail delivery failed for inquiry {$inquiry['inquiry_id']}");
        }

        // Log result
        self::logNotification([
            'type' => 'inquiry',
            'ref' => $ref,
            'recipient' => $staffEmail,
            'success' => $sent,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        // Fallback: Store in database for manual processing
        if (!$sent) {
            self::queueNotification($staffEmail, $subject, $message);
        }

        return $sent;
    }

    private static function buildEmailTemplate(array $inquiry, string $ref): string
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
                <div style='margin-top: 20px; text-align: center;'>
                    <a href='http://localhost:8080/janstro-inventory/frontend/inquiries.html' style='display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>View in System</a>
                </div>
            </div>
        </body>
        </html>";
    }

    private static function logNotification(array $data): void
    {
        $logFile = __DIR__ . '/../../logs/notifications.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        file_put_contents($logFile, json_encode($data) . "\n", FILE_APPEND);
    }

    private static function queueNotification(string $to, string $subject, string $body): void
    {
        try {
            $db = \Janstro\InventorySystem\Config\Database::connect();
            $stmt = $db->prepare("INSERT INTO notification_queue (recipient, subject, body, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
            $stmt->execute([$to, $subject, $body]);
        } catch (\Exception $e) {
            error_log("Queue notification failed: " . $e->getMessage());
        }
    }
}
