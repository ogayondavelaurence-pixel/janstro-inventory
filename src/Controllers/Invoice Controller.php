<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\InvoiceService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Invoice Controller
 * Handles invoice management HTTP requests
 * ISO/IEC 25010: Functional Suitability, Security, Usability
 */
class InvoiceController
{
    private InvoiceService $invoiceService;

    public function __construct()
    {
        $this->invoiceService = new InvoiceService();
    }

    /**
     * GET /invoices
     * Get all invoices (with optional status filter)
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $status = $_GET['status'] ?? null;
            $invoices = $this->invoiceService->getAllInvoices($status);
            Response::success($invoices, 'Invoices retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /invoices/{id}
     * Get invoice details with line items
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoice = $this->invoiceService->getInvoiceDetails($id);

            if (!$invoice) {
                Response::notFound('Invoice not found');
                return;
            }

            Response::success($invoice, 'Invoice details retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /invoices/outstanding
     * Get unpaid and partially paid invoices
     */
    public function getOutstanding(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $invoices = $this->invoiceService->getOutstandingInvoices();
            Response::success($invoices, 'Outstanding invoices retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /invoices/statistics
     * Get invoice statistics
     */
    public function getStatistics(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stats = $this->invoiceService->getInvoiceStatistics();
            Response::success($stats, 'Invoice statistics retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /invoices/generate/{order_id}
     * Generate invoice from sales order
     */
    public function generate(int $orderId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Only staff and above can generate invoices
        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $result = $this->invoiceService->generateInvoice($orderId, $user->user_id);
            Response::success($result, 'Invoice generated successfully', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /invoices/{id}/payment
     * Apply payment to invoice
     */
    public function applyPayment(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['amount']) || $data['amount'] <= 0) {
                Response::error('Valid payment amount is required', null, 400);
                return;
            }

            $result = $this->invoiceService->applyPayment($id, (float)$data['amount'], $user->user_id);
            Response::success($result, 'Payment applied successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /invoices/{id}/pdf
     * Export invoice as PDF (HTML for now)
     */
    public function exportPDF(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $html = $this->invoiceService->generateInvoicePDF($id);

            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
