<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

/**
 * Invoice Service
 * Handles invoice generation, payment tracking, and financial operations
 */
class InvoiceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all invoices with filters
     */
    public function getAllInvoices(array $filters = []): array
    {
        $sql = "SELECT 
                    i.*,
                    c.name as customer_name,
                    c.email as customer_email,
                    u.name as created_by_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.customer_id
                LEFT JOIN users u ON i.created_by = u.user_id
                WHERE 1=1";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND i.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['customer_id'])) {
            $sql .= " AND i.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND i.invoice_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND i.invoice_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY i.invoice_date DESC, i.invoice_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Get invoice by ID with items
     */
    public function getInvoiceById(int $invoiceId): ?array
    {
        // Get invoice header
        $stmt = $this->db->prepare("
            SELECT 
                i.*,
                c.name as customer_name,
                c.email as customer_email,
                c.phone as customer_phone,
                c.address as customer_address,
                u.name as created_by_name
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.customer_id
            LEFT JOIN users u ON i.created_by = u.user_id
            WHERE i.invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $invoice = $stmt->fetch();

        if (!$invoice) {
            return null;
        }

        // Get invoice items
        $stmt = $this->db->prepare("
            SELECT 
                ii.*,
                inv.item_name,
                inv.sku
            FROM invoice_items ii
            LEFT JOIN inventory inv ON ii.item_id = inv.item_id
            WHERE ii.invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $invoice['items'] = $stmt->fetchAll();

        // Get payments
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                u.name as processed_by_name
            FROM payments p
            LEFT JOIN users u ON p.processed_by = u.user_id
            WHERE p.invoice_id = :invoice_id
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([':invoice_id' => $invoiceId]);
        $invoice['payments'] = $stmt->fetchAll();

        return $invoice;
    }

    /**
     * Get outstanding invoices
     */
    public function getOutstandingInvoices(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.*,
                c.name as customer_name,
                c.email as customer_email,
                (i.total_amount - i.paid_amount) as balance
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.customer_id
            WHERE i.status IN ('pending', 'partial')
            ORDER BY i.due_date ASC
        ");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get invoice statistics
     */
    public function getInvoiceStatistics(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_invoices,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) as partial_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(total_amount) as total_revenue,
                SUM(paid_amount) as total_collected,
                SUM(total_amount - paid_amount) as total_outstanding
            FROM invoices
        ");

        return $stmt->fetch();
    }

    /**
     * Generate invoice from sales order
     */
    public function generateInvoice(int $salesOrderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Get sales order details
            $stmt = $this->db->prepare("
                SELECT so.*, c.customer_id, c.name as customer_name
                FROM sales_orders so
                LEFT JOIN customers c ON so.customer_id = c.customer_id
                WHERE so.order_id = :order_id
            ");
            $stmt->execute([':order_id' => $salesOrderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new \Exception('Sales order not found');
            }

            if ($order['status'] !== 'completed') {
                throw new \Exception('Only completed orders can be invoiced');
            }

            // Check if invoice already exists
            $stmt = $this->db->prepare("
                SELECT invoice_id FROM invoices WHERE sales_order_id = :order_id
            ");
            $stmt->execute([':order_id' => $salesOrderId]);
            if ($stmt->fetch()) {
                throw new \Exception('Invoice already exists for this order');
            }

            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();

            // Create invoice
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, sales_order_id, customer_id,
                    invoice_date, due_date, total_amount,
                    paid_amount, balance, status, created_by
                ) VALUES (
                    :invoice_number, :sales_order_id, :customer_id,
                    CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :total_amount,
                    0, :total_amount, 'pending', :created_by
                )
            ");

            $stmt->execute([
                ':invoice_number' => $invoiceNumber,
                ':sales_order_id' => $salesOrderId,
                ':customer_id' => $order['customer_id'],
                ':total_amount' => $order['total_amount'],
                ':created_by' => $userId
            ]);

            $invoiceId = (int)$this->db->lastInsertId();

            // Copy order items to invoice items
            $stmt = $this->db->prepare("
                INSERT INTO invoice_items (invoice_id, item_id, quantity, unit_price, total_price)
                SELECT :invoice_id, item_id, quantity, unit_price, total_price
                FROM sales_order_items
                WHERE order_id = :order_id
            ");
            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':order_id' => $salesOrderId
            ]);

            $this->db->commit();

            return $this->getInvoiceById($invoiceId);
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apply payment to invoice
     */
    public function applyPayment(
        int $invoiceId,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        int $userId
    ): array {
        try {
            $this->db->beginTransaction();

            // Get current invoice
            $stmt = $this->db->prepare("
                SELECT * FROM invoices WHERE invoice_id = :invoice_id
            ");
            $stmt->execute([':invoice_id' => $invoiceId]);
            $invoice = $stmt->fetch();

            if (!$invoice) {
                throw new \Exception('Invoice not found');
            }

            if ($invoice['status'] === 'paid') {
                throw new \Exception('Invoice is already fully paid');
            }

            $balance = $invoice['total_amount'] - $invoice['paid_amount'];

            if ($amount > $balance) {
                throw new \Exception('Payment amount exceeds balance');
            }

            // Record payment
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    invoice_id, amount, payment_method,
                    reference_number, payment_date, processed_by
                ) VALUES (
                    :invoice_id, :amount, :payment_method,
                    :reference_number, NOW(), :processed_by
                )
            ");

            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':reference_number' => $referenceNumber,
                ':processed_by' => $userId
            ]);

            $paymentId = (int)$this->db->lastInsertId();

            // Update invoice
            $newPaidAmount = $invoice['paid_amount'] + $amount;
            $newBalance = $invoice['total_amount'] - $newPaidAmount;
            $newStatus = $newBalance == 0 ? 'paid' : 'partial';

            $stmt = $this->db->prepare("
                UPDATE invoices 
                SET paid_amount = :paid_amount,
                    balance = :balance,
                    status = :status,
                    updated_at = NOW()
                WHERE invoice_id = :invoice_id
            ");

            $stmt->execute([
                ':paid_amount' => $newPaidAmount,
                ':balance' => $newBalance,
                ':status' => $newStatus,
                ':invoice_id' => $invoiceId
            ]);

            $this->db->commit();

            // Return payment details
            $stmt = $this->db->prepare("
                SELECT p.*, u.name as processed_by_name
                FROM payments p
                LEFT JOIN users u ON p.processed_by = u.user_id
                WHERE p.payment_id = :payment_id
            ");
            $stmt->execute([':payment_id' => $paymentId]);

            return $stmt->fetch();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Generate unique invoice number
     */
    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = date('Ymd');

        $stmt = $this->db->prepare("
            SELECT invoice_number 
            FROM invoices 
            WHERE invoice_number LIKE :pattern
            ORDER BY invoice_id DESC 
            LIMIT 1
        ");
        $stmt->execute([':pattern' => "$prefix-$date-%"]);
        $lastInvoice = $stmt->fetch();

        if ($lastInvoice) {
            $lastNumber = (int)substr($lastInvoice['invoice_number'], -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf("%s-%s-%04d", $prefix, $date, $newNumber);
    }
}
