<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\OrderService;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * ORDER CONTROLLER v3.0 (REFACTORED - SERVICE LAYER)
 * ============================================================================
 * Thin controller - delegates all logic to OrderService
 * ============================================================================
 */
class OrderController
{
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    // ========================================================================
    // PURCHASE ORDER ENDPOINTS
    // ========================================================================

    /**
     * GET /purchase-orders
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $status = $_GET['status'] ?? null;
            $orders = $this->orderService->getAllPurchaseOrders($status);
            Response::success($orders, 'Purchase orders retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve orders');
        }
    }

    /**
     * GET /purchase-orders/{id}
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $order = $this->orderService->getPurchaseOrderById($id);

            if (!$order) {
                Response::notFound('Purchase order not found');
                return;
            }

            Response::success($order, 'Purchase order retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve order');
        }
    }

    /**
     * POST /purchase-orders
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            $result = $this->orderService->createPurchaseOrder($data, $user->user_id);
            Response::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            error_log("OrderController::create - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * PUT /purchase-orders/{id}
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            // Check permission
            if (!$this->orderService->canModifyOrder($id, $user->user_id, $user->role)) {
                Response::forbidden('You cannot modify this order');
                return;
            }

            $result = $this->orderService->updatePurchaseOrder($id, $data, $user->user_id);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("OrderController::update - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /purchase-orders/{id}/approve
     */
    public function approve(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $result = $this->orderService->approvePurchaseOrder($id, $user->user_id);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("OrderController::approve - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /purchase-orders/{id}/receive
     * Goods receipt (MIGO)
     */
    public function receiveGoods(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin', 'staff']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                $data = [];
            }

            $result = $this->orderService->receiveGoods($id, $data, $user->user_id);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("OrderController::receiveGoods - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /purchase-orders/{id}/cancel
     */
    public function cancel(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $reason = $data['reason'] ?? null;

            $result = $this->orderService->cancelPurchaseOrder($id, $user->user_id, $reason);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("OrderController::cancel - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * GET /purchase-orders/{id}/pdf
     */
    public function downloadPDF(int $id): void
    {
        // Accept token from query parameter for browser downloads
        $token = $_GET['token'] ?? null;
        if ($token) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $order = $this->orderService->getPurchaseOrderById($id);

            if (!$order) {
                Response::notFound('Purchase order not found');
                return;
            }

            // Generate PDF
            $pdfService = new \Janstro\InventorySystem\Services\PdfService();

            $supplier = [
                'supplier_name' => $order['supplier_name'],
                'contact_person' => $order['contact_person'],
                'phone' => $order['phone'],
                'email' => $order['email'],
                'address' => $order['supplier_address']
            ];

            $item = [
                'item_name' => $order['item_name'],
                'sku' => $order['sku'],
                'unit' => $order['unit']
            ];

            $pdfPath = $pdfService->generatePurchaseOrderPDF($order, $supplier, $item);
            $absolutePath = $pdfService->getAbsolutePath($pdfPath);

            if (!file_exists($absolutePath)) {
                Response::notFound('PDF file not found');
                return;
            }

            $poNumber = 'PO-' . str_pad($id, 6, '0', STR_PAD_LEFT);

            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $poNumber . '.pdf"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($absolutePath);
            exit;
        } catch (\Exception $e) {
            error_log("OrderController::downloadPDF - " . $e->getMessage());
            Response::serverError('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // SALES ORDER ENDPOINTS
    // ========================================================================

    /**
     * GET /sales-orders
     */
    public function getAllSalesOrders(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $orders = $this->orderService->getAllSalesOrders();
            Response::success($orders, 'Sales orders retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getAllSalesOrders - " . $e->getMessage());
            Response::serverError('Failed to retrieve sales orders');
        }
    }

    /**
     * POST /sales-orders
     */
    public function createSalesOrder(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid request data');
                return;
            }

            $result = $this->orderService->createSalesOrder($data, $user->user_id);
            Response::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            error_log("OrderController::createSalesOrder - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /sales-orders/{id}/complete
     */
    public function completeSalesOrder(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $result = $this->orderService->completeSalesOrder($id, $user->user_id);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("OrderController::completeSalesOrder - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    // ========================================================================
    // STATISTICS
    // ========================================================================

    /**
     * GET /purchase-orders/stats
     */
    public function getPurchaseOrderStats(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stats = $this->orderService->getPurchaseOrderStats();
            Response::success($stats, 'Statistics retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getPurchaseOrderStats - " . $e->getMessage());
            Response::serverError('Failed to retrieve statistics');
        }
    }
}
