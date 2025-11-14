<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Repositories\UserRepository;

/**
 * Invoice Service
 * Manages invoice generation and payment tracking
 * ISO/IEC 25010: Functional Suitability, Reliability
 */
class InvoiceService
{
    private \PDO $db;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->userRepo = new UserRepository();
    }

    /**
     * Get all invoices
     */
    public function getAllInvoices(?string $status = null): array
    {
        $sql = "
            SELECT i.*, c.customer_name, c.contact_no,
                   so.order_id, so.installation_date,
                   u.name AS generated_by_name
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            JOIN sales_orders so ON i.order_id = so.order_id
            LEFT JOIN users u ON i.generated_by = u.user_id
        ";

        if ($status) {
            $sql .= " WHERE i.paid_status = ?";
            $stmt = $this->db->prepare($sql . " ORDER BY i.generated_at DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = $this->db->query($sql . " ORDER BY i.generated_at DESC");
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get invoice by ID with line items
     */
    public function getInvoiceDetails(int $invoiceId): ?array
    {
        // Get invoice header
        $stmt = $this->db->prepare("
            SELECT i.*, c.customer_name, c.contact_no, c.address,
                   so.installation_address, so.installation_date,
                   u.name AS generated_by_name
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            JOIN sales_orders so ON i.order_id = so.order_id
            LEFT JOIN users u ON i.generated_by = u.user_id
            WHERE i.invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$invoice) {
            return null;
        }

        // Get line items
        $stmt = $this->db->prepare("
            SELECT soi.*, i.item_name, i.unit
            FROM sales_order_items soi
            JOIN items i ON soi.item_id = i.item_id
            WHERE soi.order_id = ?
        ");
        $stmt->execute([$invoice['order_id']]);
        $invoice['items'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $invoice;
    }

    /**
     * Generate invoice from sales order (calls stored procedure)
     */
    public function generateInvoice(int $orderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Check if invoice already exists
            $stmt = $this->db->prepare("SELECT invoice_id FROM invoices WHERE order_id = ?");
            $stmt->execute([$orderId]);
            if ($stmt->fetch()) {
                throw new \Exception("Invoice already exists for this order");
            }

            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_generate_invoice(?, ?)");
            $stmt->execute([$orderId, $userId]);

            // Get generated invoice
            $stmt = $this->db->prepare("
                SELECT invoice_id, invoice_number, total_amount
                FROM invoices
                WHERE order_id = ?
                ORDER BY invoice_id DESC LIMIT 1
            ");
            $stmt->execute([$orderId]);
            $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Audit log
            $this->userRepo->logAudit(
                $userId,
                "Generated Invoice: {$invoice['invoice_number']} | Order #$orderId"
            );

            $this->db->commit();

            return [
                'success' => true,
                'invoice_id' => $invoice['invoice_id'],
                'invoice_number' => $invoice['invoice_number'],
                'total_amount' => $invoice['total_amount'],
                'message' => 'Invoice generated successfully'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apply payment to invoice (calls stored procedure)
     */
    public function applyPayment(int $invoiceId, float $amount, int $userId): array
    {
        if ($amount <= 0) {
            throw new \Exception("Payment amount must be greater than zero");
        }

        try {
            $this->db->beginTransaction();

            // Get current invoice
            $stmt = $this->db->prepare("SELECT * FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new \Exception("Invoice not found");
            }

            $remainingBalance = $invoice['total_amount'] - $invoice['paid_amount'];
            if ($amount > $remainingBalance) {
                throw new \Exception("Payment exceeds remaining balance");
            }

            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_apply_payment(?, ?)");
            $stmt->execute([$invoiceId, $amount]);

            // Get updated status
            $stmt = $this->db->prepare("SELECT paid_status, paid_amount FROM invoices WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $updated = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Audit log
            $this->userRepo->logAudit($userId, "Payment applied to Invoice #{$invoice['invoice_number']}");

            $this->db->commit();

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'],
                'payment_amount' => $amount,
                'paid_amount' => $updated['paid_amount'],
                'remaining_balance' => $invoice['total_amount'] - $updated['paid_amount'],
                'paid_status' => $updated['paid_status'],
                'message' => 'Payment applied successfully'
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get unpaid/partial invoices
     */
    public function getOutstandingInvoices(): array
    {
        $stmt = $this->db->query("
            SELECT i.*, c.customer_name,
                   (i.total_amount - i.paid_amount) AS balance
            FROM invoices i
            JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.paid_status IN ('unpaid', 'partial')
            ORDER BY i.generated_at DESC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export invoice to HTML (for PDF conversion)
     */
    public function generateInvoicePDF(int $invoiceId): string
    {
        $invoice = $this->getInvoiceDetails($invoiceId);

        if (!$invoice) {
            throw new \Exception("Invoice not found");
        }

        return $this->generateInvoiceHTML($invoice);
    }

    /**
     * Generate invoice HTML template
     */
    private function generateInvoiceHTML(array $invoice): string
    {
        $companyName = 'Janstro Prime Renewable Energy Solutions Corporation';
        $itemsHTML = '';

        foreach ($invoice['items'] as $item) {
            $itemsHTML .= '<tr>';
            $itemsHTML .= '<td>' . htmlspecialchars($item['item_name']) . '</td>';
            $itemsHTML .= '<td style="text-align:center">' . $item['quantity'] . ' ' . $item['unit'] . '</td>';
            $itemsHTML .= '<td style="text-align:right">₱' . number_format($item['unit_price'], 2) . '</td>';
            $itemsHTML .= '<td style="text-align:right">₱' . number_format($item['line_total'], 2) . '</td>';
            $itemsHTML .= '</tr>';
        }

        $balance = $invoice['total_amount'] - $invoice['paid_amount'];

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .company-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .invoice-title { font-size: 20px; margin-top: 10px; }
        .section { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        th { background-color: #3498db; color: white; }
        .total-row { font-weight: bold; background-color: #ecf0f1; }
        .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #7f8c8d; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">' . htmlspecialchars($companyName) . '</div>
        <div>Bayhill Executive Subdivision, B19 L5, Calamba, Laguna</div>
        <div>Contact: (049) 123-4567 | Email: info@janstrosolar.ph</div>
        <div class="invoice-title">INVOICE</div>
    </div>

    <div class="section">
        <table>
            <tr>
                <td><strong>Invoice Number:</strong></td>
                <td>' . htmlspecialchars($invoice['invoice_number']) . '</td>
                <td><strong>Invoice Date:</strong></td>
                <td>' . htmlspecialchars($invoice['generated_at']) . '</td>
            </tr>
            <tr>
                <td><strong>Customer:</strong></td>
                <td colspan="3">' . htmlspecialchars($invoice['customer_name']) . '</td>
            </tr>
            <tr>
                <td><strong>Contact:</strong></td>
                <td>' . htmlspecialchars($invoice['contact_no']) . '</td>
                <td><strong>Installation Date:</strong></td>
                <td>' . htmlspecialchars($invoice['installation_date']) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Line Total</th>
                </tr>
            </thead>
            <tbody>
                ' . $itemsHTML . '
                <tr class="total-row">
                    <td colspan="3" style="text-align:right">TOTAL AMOUNT</td>
                    <td style="text-align:right">₱' . number_format($invoice['total_amount'], 2) . '</td>
                </tr>
                <tr>
                    <td colspan="3" style="text-align:right">Paid Amount</td>
                    <td style="text-align:right">₱' . number_format($invoice['paid_amount'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" style="text-align:right">BALANCE DUE</td>
                    <td style="text-align:right">₱' . number_format($balance, 2) . '</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <strong>Payment Status:</strong> ' . strtoupper($invoice['paid_status']) . '<br>
        <strong>Terms:</strong> Net 30 Days<br>
    </div>

    <div class="footer">
        Thank you for your business!<br>
        This is a computer-generated invoice. No signature required.
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get invoice statistics
     */
    public function getInvoiceStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) AS total_invoices,
                SUM(CASE WHEN paid_status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN paid_status = 'unpaid' THEN 1 ELSE 0 END) AS unpaid_count,
                SUM(CASE WHEN paid_status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                SUM(total_amount) AS total_billed,
                SUM(paid_amount) AS total_collected,
                SUM(total_amount - paid_amount) AS total_outstanding
            FROM invoices
        ");

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
