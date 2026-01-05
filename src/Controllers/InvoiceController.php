<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\InvoiceService;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * INVOICE CONTROLLER v3.0 - THIN ORCHESTRATION LAYER
 * ============================================================================
 * Delegates all business logic to InvoiceService
 * ============================================================================
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
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $filters = [
                'status' => $_GET['status'] ?? null,
                'from_date' => $_GET['from_date'] ?? null,
                'to_date' => $_GET['to_date'] ?? null
            ];

            $invoices = $this->invoiceService->getAllInvoices($filters);
            Response::success($invoices, 'Invoices retrieved');
        } catch (\Exception $e) {
            error_log("InvoiceController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve invoices');
        }
    }

    /**
     * GET /invoices/:id
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

            Response::success($invoice, 'Invoice details retrieved');
        } catch (\Exception $e) {
            error_log("InvoiceController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve invoice');
        }
    }

    /**
     * POST /invoices/generate
     */
    public function generate(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            $result = $this->invoiceService->generateInvoice($data, $user->user_id);
            Response::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            error_log("InvoiceController::generate - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /invoices/:id/payment
     */
    public function recordPayment(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            $result = $this->invoiceService->recordPayment($id, $data, $user->user_id);
            Response::success($result, 'Payment recorded successfully');
        } catch (\Exception $e) {
            error_log("InvoiceController::recordPayment - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /invoices/:id/email
     */
    public function sendEmail(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $toEmail = $data['to_email'] ?? null;

            $sent = $this->invoiceService->sendInvoiceEmail($id, $toEmail);

            if ($sent) {
                Response::success(null, 'Invoice email sent successfully');
            } else {
                Response::error('Failed to send email. Check SMTP configuration.', null, 500);
            }
        } catch (\Exception $e) {
            error_log("InvoiceController::sendEmail - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * GET /invoices/:id/pdf
     */
    public function downloadPDF(int $id): void
    {
        // Accept token from query parameter
        $token = $_GET['token'] ?? null;
        if ($token) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $pdfPath = $this->invoiceService->getPdfPath($id);

            if (!$pdfPath) {
                Response::notFound('PDF not found');
                return;
            }

            $absolutePath = __DIR__ . '/../../' . ltrim($pdfPath, '/');

            if (!file_exists($absolutePath)) {
                Response::notFound('PDF file not found on server');
                return;
            }

            $invoice = $this->invoiceService->getInvoiceById($id);
            $filename = $invoice['invoice_number'] . '.pdf';

            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($absolutePath);
            exit;
        } catch (\Exception $e) {
            error_log("InvoiceController::downloadPDF - " . $e->getMessage());
            Response::serverError('Failed to download PDF');
        }
    }

    /**
     * GET /invoices/stats
     */
    public function getStats(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stats = $this->invoiceService->getInvoiceStats();
            Response::success($stats, 'Statistics retrieved');
        } catch (\Exception $e) {
            error_log("InvoiceController::getStats - " . $e->getMessage());
            Response::serverError('Failed to retrieve statistics');
        }
    }
}
