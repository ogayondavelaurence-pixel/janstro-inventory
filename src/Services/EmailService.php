<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private $db;
    private $mailer;
    private $fromEmail;
    private $fromName;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@janstro-ims.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Janstro IMS';

        $this->initializeMailer();
    }

    /**
     * Initialize PHPMailer
     */
    private function initializeMailer()
    {
        $this->mailer = new PHPMailer(true);

        try {
            // SMTP Configuration
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $_ENV['MAIL_PORT'] ?? 587;

            // Default sender
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log("EmailService initialization failed: " . $e->getMessage());
        }
    }

    /**
     * Send low stock alert email
     */
    public function sendLowStockAlert($items, $recipientEmail)
    {
        try {
            $itemsList = '';
            foreach ($items as $item) {
                $shortage = ($item['reorder_level'] ?? 0) - ($item['quantity'] ?? 0);
                $itemsList .= "
                    <tr>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['name']}</td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd; color: #dc3545;'><strong>{$item['quantity']}</strong></td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd;'>{$item['reorder_level']}</td>
                        <td style='padding: 10px; border-bottom: 1px solid #ddd; color: #dc3545;'>{$shortage}</td>
                    </tr>
                ";
            }

            $subject = "⚠️ Low Stock Alert - " . count($items) . " Items Need Attention";
            $body = $this->getLowStockTemplate($items, $itemsList);

            return $this->sendEmail($recipientEmail, $subject, $body);
        } catch (Exception $e) {
            error_log("Low stock alert failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send purchase order confirmation
     */
    public function sendPurchaseOrderConfirmation($poData, $recipientEmail)
    {
        try {
            $subject = "✅ Purchase Order #{$poData['po_number']} Created";
            $body = $this->getPOConfirmationTemplate($poData);

            // Queue email
            $this->queueEmail($recipientEmail, $subject, $body, 'po_confirmation', $poData['po_id'] ?? null);

            return $this->sendEmail($recipientEmail, $subject, $body);
        } catch (Exception $e) {
            error_log("PO confirmation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send sales order confirmation
     */
    public function sendSalesOrderConfirmation($orderData, $recipientEmail)
    {
        try {
            $subject = "🎉 Order Confirmation #{$orderData['order_number']}";
            $body = $this->getSalesOrderTemplate($orderData);

            return $this->sendEmail($recipientEmail, $subject, $body);
        } catch (Exception $e) {
            error_log("Sales order confirmation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send approval notification
     */
    public function sendApprovalNotification($poData, $approverEmail, $requesterEmail)
    {
        try {
            // Notify approver
            $subjectApprover = "⏳ Approval Required - PO #{$poData['po_number']}";
            $bodyApprover = $this->getApprovalRequestTemplate($poData);
            $this->sendEmail($approverEmail, $subjectApprover, $bodyApprover);

            // Notify requester of submission
            $subjectRequester = "📋 PO #{$poData['po_number']} Submitted for Approval";
            $bodyRequester = $this->getApprovalSubmittedTemplate($poData);
            $this->sendEmail($requesterEmail, $subjectRequester, $bodyRequester);

            return true;
        } catch (Exception $e) {
            error_log("Approval notification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send daily summary report
     */
    public function sendDailySummary($summaryData, $recipientEmail)
    {
        try {
            $date = date('F j, Y');
            $subject = "📊 Daily Inventory Summary - {$date}";
            $body = $this->getDailySummaryTemplate($summaryData, $date);

            return $this->sendEmail($recipientEmail, $subject, $body);
        } catch (Exception $e) {
            error_log("Daily summary failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Core email sending method
     */
    private function sendEmail($to, $subject, $body, $attachments = [])
    {
        try {
            // Reset recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Set recipient
            $this->mailer->addAddress($to);

            // Set content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = strip_tags($body);

            // Add attachments if any
            foreach ($attachments as $attachment) {
                $this->mailer->addAttachment($attachment);
            }

            // Send
            $result = $this->mailer->send();

            if ($result) {
                $this->logEmailSent($to, $subject);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Email send failed to {$to}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue email for later sending
     */
    private function queueEmail($to, $subject, $body, $type, $referenceId = null)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (
                    recipient,
                    subject,
                    body,
                    email_type,
                    reference_id,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $stmt->execute([$to, $subject, $body, $type, $referenceId]);
            return true;
        } catch (\Exception $e) {
            error_log("Email queue failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log sent email
     */
    private function logEmailSent($to, $subject)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (recipient, subject, sent_at, status)
                VALUES (?, ?, NOW(), 'sent')
            ");
            $stmt->execute([$to, $subject]);
        } catch (\Exception $e) {
            error_log("Email log failed: " . $e->getMessage());
        }
    }

    /* ===================================================================
       EMAIL TEMPLATES
    =================================================================== */

    private function getLowStockTemplate($items, $itemsList)
    {
        $count = count($items);
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
                .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .btn { background: #dc3545; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Low Stock Alert</h1>
                    <p>{$count} items need immediate attention</p>
                </div>
                <div class='content'>
                    <div class='alert'>
                        <strong>Action Required:</strong> The following items have reached or fallen below their reorder levels. Please create purchase orders to replenish stock.
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Shortage</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$itemsList}
                        </tbody>
                    </table>
                    
                    <a href='http://localhost:8080/janstro-inventory/frontend/stock-overview.html' class='btn'>
                        View Inventory
                    </a>
                </div>
                <div class='footer'>
                    <p>This is an automated notification from Janstro IMS</p>
                    <p>Generated on " . date('F j, Y g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getPOConfirmationTemplate($poData)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .info-box { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #dee2e6; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Purchase Order Created</h1>
                    <p>PO #{$poData['po_number']}</p>
                </div>
                <div class='content'>
                    <h3>Order Details</h3>
                    <div class='info-box'>
                        <div class='info-row'>
                            <strong>PO Number:</strong>
                            <span>{$poData['po_number']}</span>
                        </div>
                        <div class='info-row'>
                            <strong>Supplier:</strong>
                            <span>{$poData['supplier_name']}</span>
                        </div>
                        <div class='info-row'>
                            <strong>Item:</strong>
                            <span>{$poData['item_name']}</span>
                        </div>
                        <div class='info-row'>
                            <strong>Quantity:</strong>
                            <span>{$poData['quantity']}</span>
                        </div>
                        <div class='info-row'>
                            <strong>Total Amount:</strong>
                            <span>₱" . number_format($poData['total_amount'], 2) . "</span>
                        </div>
                        <div class='info-row'>
                            <strong>Expected Delivery:</strong>
                            <span>{$poData['expected_delivery']}</span>
                        </div>
                        <div class='info-row'>
                            <strong>Status:</strong>
                            <span style='color: #ffc107;'><strong>Pending Approval</strong></span>
                        </div>
                    </div>
                    
                    <p style='margin-top: 20px;'>This purchase order has been submitted for approval. You will receive another notification once it has been reviewed.</p>
                </div>
                <div class='footer'>
                    <p>Janstro Inventory Management System</p>
                    <p>Created on " . date('F j, Y g:i A') . "</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getSalesOrderTemplate($orderData)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .thank-you { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin: 20px 0; }
                .info-box { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Order Confirmation</h1>
                    <p>Order #{$orderData['order_number']}</p>
                </div>
                <div class='content'>
                    <div class='thank-you'>
                        <strong>Thank you for your order!</strong> We've received your order and will process it shortly.
                    </div>
                    
                    <h3>Order Summary</h3>
                    <div class='info-box'>
                        <p><strong>Customer:</strong> {$orderData['customer_name']}</p>
                        <p><strong>Total Amount:</strong> ₱" . number_format($orderData['total_amount'], 2) . "</p>
                        <p><strong>Order Date:</strong> " . date('F j, Y') . "</p>
                    </div>
                    
                    <p>We'll notify you once your order is ready for delivery.</p>
                </div>
                <div class='footer'>
                    <p>Questions? Contact us at support@janstro-ims.com</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getApprovalRequestTemplate($poData)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #333; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .btn { background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
                .btn-reject { background: #dc3545; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⏳ Approval Required</h1>
                    <p>Purchase Order #{$poData['po_number']}</p>
                </div>
                <div class='content'>
                    <p>A new purchase order requires your approval:</p>
                    
                    <div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px;'>
                        <p><strong>Supplier:</strong> {$poData['supplier_name']}</p>
                        <p><strong>Item:</strong> {$poData['item_name']}</p>
                        <p><strong>Quantity:</strong> {$poData['quantity']}</p>
                        <p><strong>Amount:</strong> ₱" . number_format($poData['total_amount'], 2) . "</p>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='http://localhost:8080/janstro-inventory/frontend/purchase-orders.html' class='btn'>
                            Review & Approve
                        </a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Janstro IMS - Automated Notification</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getApprovalSubmittedTemplate($poData)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📋 PO Submitted</h1>
                    <p>#{$poData['po_number']}</p>
                </div>
                <div class='content'>
                    <p>Your purchase order has been successfully submitted for approval.</p>
                    <p>You will receive a notification once it has been reviewed by an administrator.</p>
                    
                    <div style='background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px;'>
                        <p><strong>PO Number:</strong> {$poData['po_number']}</p>
                        <p><strong>Status:</strong> Pending Approval</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>Janstro Inventory Management System</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private function getDailySummaryTemplate($summaryData, $date)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6f42c1 0%, #563d7c 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #fff; padding: 30px; border: 1px solid #ddd; }
                .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 20px 0; }
                .stat-box { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; }
                .stat-value { font-size: 32px; font-weight: bold; color: #007bff; }
                .stat-label { font-size: 14px; color: #6c757d; margin-top: 5px; }
                .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 8px 8px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>📊 Daily Summary Report</h1>
                    <p>{$date}</p>
                </div>
                <div class='content'>
                    <h3>Today's Activity</h3>
                    <div class='stat-grid'>
                        <div class='stat-box'>
                            <div class='stat-value'>{$summaryData['total_transactions']}</div>
                            <div class='stat-label'>Transactions</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-value'>{$summaryData['new_orders']}</div>
                            <div class='stat-label'>New Orders</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-value'>{$summaryData['low_stock_items']}</div>
                            <div class='stat-label'>Low Stock Items</div>
                        </div>
                        <div class='stat-box'>
                            <div class='stat-value'>₱" . number_format($summaryData['total_sales'], 2) . "</div>
                            <div class='stat-label'>Total Sales</div>
                        </div>
                    </div>
                    
                    <p style='margin-top: 30px;'>
                        <a href='http://localhost:8080/janstro-inventory/frontend/dashboard.html' 
                           style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                            View Dashboard
                        </a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Automated daily report from Janstro IMS</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
