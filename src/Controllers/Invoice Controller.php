<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\InvoiceService;
use Janstro\InventorySystem\Utils\Response;

/**
 * Invoice Controller
 * Handles invoice generation, payment tracking, and export
 */
class InvoiceController
{
    private InvoiceService $invoiceService;

    public function __construct()
    {
        $this->invoiceService = new InvoiceService();
    }

    /**
     * Get all invoices
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'customer_id' => $_GET['customer_id'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null
            ];

            $invoices = $this->invoiceService->getAllInvoices($filters);
            Response::success($invoices, 'Invoices retrieved successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve invoices: ' . $e->getMessage());
        }
    }

    /**
     * Get invoice by ID
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoice = $this->invoiceService->getInvoiceById($id);

            if (!$invoice) {
                Response::notFound('Invoice not found');
                return;
            }

            Response::success($invoice, 'Invoice retrieved successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve invoice: ' . $e->getMessage());
        }
    }

    /**
     * Get outstanding invoices
     */
    public function getOutstanding(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoices = $this->invoiceService->getOutstandingInvoices();
            Response::success($invoices, 'Outstanding invoices retrieved successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve outstanding invoices: ' . $e->getMessage());
        }
    }

    /**
     * Get invoice statistics
     */
    public function getStatistics(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $statistics = $this->invoiceService->getInvoiceStatistics();
            Response::success($statistics, 'Invoice statistics retrieved successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve statistics: ' . $e->getMessage());
        }
    }

    /**
     * Generate invoice from sales order
     */
    public function generate(int $salesOrderId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoice = $this->invoiceService->generateInvoice($salesOrderId, $user->user_id);
            Response::success($invoice, 'Invoice generated successfully', 201);
        } catch (\Exception $e) {
            Response::serverError('Failed to generate invoice: ' . $e->getMessage());
        }
    }

    /**
     * Apply payment to invoice
     */
    public function applyPayment(int $invoiceId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['amount']) || $input['amount'] <= 0) {
                Response::error('Valid payment amount is required', null, 400);
                return;
            }

            $payment = $this->invoiceService->applyPayment(
                $invoiceId,
                $input['amount'],
                $input['payment_method'] ?? 'cash',
                $input['reference_number'] ?? null,
                $user->user_id
            );

            Response::success($payment, 'Payment applied successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to apply payment: ' . $e->getMessage());
        }
    }

    /**
     * Export invoice to PDF
     */
    public function exportPDF(int $invoiceId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoice = $this->invoiceService->getInvoiceById($invoiceId);

            if (!$invoice) {
                Response::notFound('Invoice not found');
                return;
            }

            // Generate PDF content (simple HTML for now)
            $html = $this->generateInvoiceHTML($invoice);

            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="invoice_' . $invoice['invoice_number'] . '.pdf"');

            // For now, return HTML (you can integrate a PDF library like TCPDF or DomPDF later)
            echo $html;
        } catch (\Exception $e) {
            Response::serverError('Failed to export invoice: ' . $e->getMessage());
        }
    }

    /**
     * Generate invoice HTML for PDF export
     */
    private function generateInvoiceHTML(array $invoice): string
    {
        $html = "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Invoice {$invoice['invoice_number']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .header { text-align: center; margin-bottom: 30px; }
                .invoice-details { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f2f2f2; }
                .total { font-weight: bold; font-size: 18px; margin-top: 20px; text-align: right; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>INVOICE</h1>
                <p>Janstro Inventory System</p>
            </div>
            
            <div class='invoice-details'>
                <p><strong>Invoice Number:</strong> {$invoice['invoice_number']}</p>
                <p><strong>Date:</strong> {$invoice['invoice_date']}</p>
                <p><strong>Customer:</strong> {$invoice['customer_name']}</p>
                <p><strong>Status:</strong> " . strtoupper($invoice['status']) . "</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($invoice['items'] as $item) {
            $html .= "<tr>
                <td>{$item['item_name']}</td>
                <td>{$item['quantity']}</td>
                <td>₱" . number_format($item['unit_price'], 2) . "</td>
                <td>₱" . number_format($item['total_price'], 2) . "</td>
            </tr>";
        }

        $html .= "</tbody>
            </table>
            
            <div class='total'>
                <p>TOTAL AMOUNT: ₱" . number_format($invoice['total_amount'], 2) . "</p>
                <p>PAID: ₱" . number_format($invoice['paid_amount'], 2) . "</p>
                <p>BALANCE: ₱" . number_format($invoice['balance'], 2) . "</p>
            </div>
        </body>
        </html>";

        return $html;
    }
}
