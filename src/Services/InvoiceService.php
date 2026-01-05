<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * INVOICE SERVICE v3.0 - PRODUCTION READY
 * ============================================================================
 * Full invoice lifecycle: generation, payments, status tracking, PDF
 * ============================================================================
 */
class InvoiceService
{
    private PDO $db;
    private PdfService $pdfService;
    private EmailService $emailService;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->pdfService = new PdfService();
        $this->emailService = new EmailService();
        $this->notificationService = new NotificationService();
    }

    // ========================================================================
    // INVOICE RETRIEVAL
    // ========================================================================

    public function getAllInvoices(array $filters = []): array
    {
        try {
            $sql = "
                SELECT 
                    i.*,
                    so.delivery_address,
                    so.contact_number as customer_phone,
                    u.name as generated_by_name,
                    CASE 
                        WHEN i.payment_status = 'paid' THEN 'Paid'
                        WHEN i.payment_status = 'unpaid' AND i.due_date < CURDATE() THEN 'Overdue'
                        WHEN i.payment_status = 'unpaid' THEN 'Pending'
                        WHEN i.payment_status = 'partial' THEN 'Partial'
                        ELSE 'Unknown'
                    END AS status_label,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM invoices i
                LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
                LEFT JOIN users u ON i.generated_by = u.user_id
                WHERE 1=1
            ";

            $params = [];

            if (!empty($filters['status'])) {
                $sql .= " AND i.payment_status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['from_date'])) {
                $sql .= " AND DATE(i.generated_at) >= ?";
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $sql .= " AND DATE(i.generated_at) <= ?";
                $params[] = $filters['to_date'];
            }

            $sql .= " ORDER BY i.generated_at DESC LIMIT 200";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("InvoiceService::getAllInvoices - " . $e->getMessage());
            throw new Exception('Failed to retrieve invoices');
        }
    }

    public function getInvoiceById(int $invoiceId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    i.*,
                    so.delivery_address,
                    so.contact_number as customer_phone,
                    so.customer_order_number,
                    u.name as generated_by_name,
                    u.email as generated_by_email
                FROM invoices i
                LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
                LEFT JOIN users u ON i.generated_by = u.user_id
                WHERE i.invoice_id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                return null;
            }

            // Get line items
            $stmt = $this->db->prepare("
                SELECT 
                    ii.*,
                    i.item_name,
                    i.sku
                FROM invoice_items ii
                LEFT JOIN items i ON ii.item_id = i.item_id
                WHERE ii.invoice_id = ?
                ORDER BY ii.invoice_item_id
            ");
            $stmt->execute([$invoiceId]);
            $invoice['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payments
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    u.name as recorded_by_name
                FROM invoice_payments p
                LEFT JOIN users u ON p.recorded_by = u.user_id
                WHERE p.invoice_id = ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$invoiceId]);
            $invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $invoice;
        } catch (Exception $e) {
            error_log("InvoiceService::getInvoiceById - " . $e->getMessage());
            throw new Exception('Failed to retrieve invoice');
        }
    }

    // ========================================================================
    // INVOICE GENERATION
    // ========================================================================

    public function generateInvoice(array $data, int $userId): array
    {
        $salesOrderId = $data['sales_order_id'] ?? null;

        if (!$salesOrderId) {
            throw new Exception('Sales order ID required');
        }

        try {
            $this->db->beginTransaction();

            // Get sales order
            $stmt = $this->db->prepare("
                SELECT so.*, c.email as customer_email
                FROM sales_orders so
                LEFT JOIN customers c ON so.customer_id = c.customer_id
                WHERE so.sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);
            $so = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$so) {
                throw new Exception('Sales order not found');
            }

            if ($so['status'] === 'completed') {
                throw new Exception('Sales order already invoiced');
            }

            // Generate invoice number
            $stmt = $this->db->query("
                SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) as max_num 
                FROM invoices 
                WHERE invoice_number LIKE 'INV-" . date('Y') . "-%'
            ");
            $maxNum = $stmt->fetchColumn() ?: 0;
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

            // Get line items
            $stmt = $this->db->prepare("
                SELECT soi.*, i.item_name, i.sku, i.unit
                FROM sales_order_items soi
                JOIN items i ON soi.item_id = i.item_id
                WHERE soi.sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);
            $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lineItems)) {
                throw new Exception('No line items found');
            }

            // Calculate totals
            $taxRate = $data['tax_rate'] ?? 12.00;
            $subtotal = array_sum(array_column($lineItems, 'line_total'));
            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            // Calculate due date
            $paymentTerms = $data['payment_terms'] ?? 'Net 30';
            $daysToAdd = 30;
            if (preg_match('/Net (\d+)/', $paymentTerms, $matches)) {
                $daysToAdd = (int)$matches[1];
            }
            $dueDate = date('Y-m-d', strtotime("+{$daysToAdd} days"));

            // Insert invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, sales_order_id, customer_name, 
                    subtotal, tax_rate, tax_amount, total_amount,
                    payment_terms, due_date, payment_status, generated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)
            ");
            $stmt->execute([
                $invoiceNumber,
                $salesOrderId,
                $so['customer_name'],
                $subtotal,
                $taxRate,
                $taxAmount,
                $totalAmount,
                $paymentTerms,
                $dueDate,
                $userId
            ]);

            $invoiceId = (int)$this->db->lastInsertId();

            // Insert line items
            foreach ($lineItems as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO invoice_items (
                        invoice_id, item_id, item_name, sku, quantity, 
                        unit, unit_price, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $invoiceId,
                    $item['item_id'],
                    $item['item_name'],
                    $item['sku'],
                    $item['quantity'],
                    $item['unit'] ?? 'pcs',
                    $item['unit_price'],
                    $item['line_total']
                ]);
            }

            // Get full invoice data for PDF
            $invoiceData = $this->getInvoiceById($invoiceId);

            // Generate PDF
            $pdfPath = $this->pdfService->generateInvoicePDF($invoiceData, $invoiceData['line_items']);

            // Update with PDF path
            $stmt = $this->db->prepare("UPDATE invoices SET pdf_path = ? WHERE invoice_id = ?");
            $stmt->execute([$pdfPath, $invoiceId]);

            // Update sales order
            $stmt = $this->db->prepare("
                UPDATE sales_orders 
                SET status = 'completed', completed_by = ?, completed_date = NOW() 
                WHERE sales_order_id = ?
            ");
            $stmt->execute([$userId, $salesOrderId]);

            // Deduct stock
            $stmt = $this->db->prepare("
                UPDATE items i
                JOIN sales_order_items soi ON i.item_id = soi.item_id
                SET i.quantity = i.quantity - soi.quantity
                WHERE soi.sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);

            // Log transactions
            foreach ($lineItems as $item) {
                $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ?");
                $stmt->execute([$item['item_id']]);
                $currentQty = $stmt->fetchColumn();

                $stmt = $this->db->prepare("
                    INSERT INTO transactions (
                        item_id, user_id, transaction_type, quantity, unit_price, 
                        reference_type, reference_number, previous_quantity, new_quantity
                    ) VALUES (?, ?, 'OUT', ?, ?, 'INVOICE', ?, ?, ?)
                ");
                $stmt->execute([
                    $item['item_id'],
                    $userId,
                    $item['quantity'],
                    $item['unit_price'],
                    $invoiceNumber,
                    $currentQty + $item['quantity'],
                    $currentQty
                ]);
            }

            // Audit log
            $this->createAuditLog(
                $userId,
                "Generated invoice {$invoiceNumber} for SO #{$salesOrderId} | Customer: {$so['customer_name']} | Amount: PHP " . number_format($totalAmount, 2),
                'invoices',
                'generate'
            );

            $this->db->commit();

            // Send email if requested
            $emailSent = false;
            if (!empty($data['send_email']) && !empty($so['customer_email'])) {
                try {
                    $emailSent = $this->emailService->sendInvoice($invoiceData, $invoiceData['line_items'], $so['customer_email']);

                    if ($emailSent) {
                        $stmt = $this->db->prepare("UPDATE invoices SET email_sent = 1, email_sent_at = NOW() WHERE invoice_id = ?");
                        $stmt->execute([$invoiceId]);
                    }
                } catch (Exception $e) {
                    error_log("Invoice email failed: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'pdf_path' => $pdfPath,
                'email_sent' => $emailSent,
                'due_date' => $dueDate,
                'message' => "Invoice {$invoiceNumber} generated successfully"
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("InvoiceService::generateInvoice - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // PAYMENT RECORDING
    // ========================================================================

    public function recordPayment(int $invoiceId, array $data, int $userId): array
    {
        $amount = $data['amount'] ?? 0;

        if ($amount <= 0) {
            throw new Exception('Invalid payment amount');
        }

        try {
            $this->db->beginTransaction();

            // Get invoice
            $stmt = $this->db->prepare("SELECT invoice_number, total_amount, payment_status, pdf_path FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('Invoice not found');
            }

            // Record payment
            $stmt = $this->db->prepare("
                INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceId,
                $data['payment_date'] ?? date('Y-m-d'),
                $amount,
                $data['payment_method'] ?? 'Cash',
                $data['reference_number'] ?? null,
                $data['notes'] ?? null,
                $userId
            ]);

            // Calculate new status
            $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM invoice_payments WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $totalPaid = $stmt->fetchColumn();

            $newStatus = ($totalPaid >= $invoice['total_amount']) ? 'paid' : 'partial';

            // Update invoice status
            $stmt = $this->db->prepare("
                UPDATE invoices 
                SET payment_status = ?, 
                    paid_date = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_date END, 
                    payment_method = ?
                WHERE invoice_id = ?
            ");
            $stmt->execute([$newStatus, $newStatus, $data['payment_method'] ?? 'Cash', $invoiceId]);

            $this->db->commit();

            // Regenerate PDF with updated status
            $pdfRegenerated = false;
            try {
                // Delete old PDF
                if (!empty($invoice['pdf_path'])) {
                    $oldPdfPath = __DIR__ . '/../../' . ltrim($invoice['pdf_path'], '/');
                    if (file_exists($oldPdfPath)) {
                        @unlink($oldPdfPath);
                    }
                }

                // Get fresh invoice data
                $invoiceData = $this->getInvoiceById($invoiceId);
                $newPdfPath = $this->pdfService->generateInvoicePDF($invoiceData, $invoiceData['line_items']);

                // Update PDF path
                $stmt = $this->db->prepare("UPDATE invoices SET pdf_path = ? WHERE invoice_id = ?");
                $stmt->execute([$newPdfPath, $invoiceId]);

                $pdfRegenerated = true;
            } catch (Exception $e) {
                error_log("PDF regeneration failed: " . $e->getMessage());
            }

            // Audit log
            $this->createAuditLog(
                $userId,
                "Recorded payment of PHP " . number_format($amount, 2) . " for Invoice #{$invoice['invoice_number']} | Method: {$data['payment_method']} | Status: {$newStatus}",
                'invoices',
                'payment'
            );

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'payment_status' => $newStatus,
                'total_paid' => $totalPaid,
                'remaining' => max(0, $invoice['total_amount'] - $totalPaid),
                'pdf_regenerated' => $pdfRegenerated,
                'message' => 'Payment recorded successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("InvoiceService::recordPayment - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // INVOICE ACTIONS
    // ========================================================================

    public function sendInvoiceEmail(int $invoiceId, ?string $toEmail = null): bool
    {
        try {
            $invoice = $this->getInvoiceById($invoiceId);

            if (!$invoice) {
                throw new Exception('Invoice not found');
            }

            $recipientEmail = $toEmail ?? $this->getCustomerEmail($invoice['sales_order_id']);

            if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Valid email address required');
            }

            $sent = $this->emailService->sendInvoice($invoice, $invoice['line_items'], $recipientEmail);

            if ($sent) {
                $stmt = $this->db->prepare("UPDATE invoices SET email_sent = 1, email_sent_at = NOW() WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
            }

            return $sent;
        } catch (Exception $e) {
            error_log("InvoiceService::sendInvoiceEmail - " . $e->getMessage());
            throw $e;
        }
    }

    public function getPdfPath(int $invoiceId): ?string
    {
        try {
            $stmt = $this->db->prepare("SELECT pdf_path FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            error_log("InvoiceService::getPdfPath - " . $e->getMessage());
            return null;
        }
    }

    // ========================================================================
    // STATISTICS
    // ========================================================================

    public function getInvoiceStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_invoices,
                    SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                    SUM(CASE WHEN payment_status = 'unpaid' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_count,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue
                FROM invoices
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("InvoiceService::getInvoiceStats - " . $e->getMessage());
            throw new Exception('Failed to retrieve statistics');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getCustomerEmail(int $salesOrderId): ?string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT c.email 
                FROM sales_orders so
                JOIN customers c ON so.customer_id = c.customer_id
                WHERE so.sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);
            return $stmt->fetchColumn() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function createAuditLog(int $userId, string $description, string $module, string $actionType): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $description,
                $module,
                $actionType,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
