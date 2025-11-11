<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\OrderService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Order Controller
 * Handles purchase order API endpoints
 * ISO/IEC 25010: Functional Suitability, Reliability
 */
class OrderController
{
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    /**
     * GET /api/orders
     * Get all purchase orders
     */
    public function getAll(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Get optional status filter
        $status = isset($_GET['status']) ? $_GET['status'] : null;

        $orders = $this->orderService->getAllOrders($status);

        Response::success($orders, 'Purchase orders retrieved', 200);
    }

    /**
     * GET /api/orders/{id}
     * Get single purchase order
     */
    public function getById(int $id): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        $order = $this->orderService->getOrder($id);

        if ($order) {
            Response::success($order, 'Purchase order retrieved', 200);
        } else {
            Response::notFound('Purchase order not found');
        }
    }

    /**
     * GET /api/orders/pending
     * Get pending orders
     */
    public function getPending(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        $orders = $this->orderService->getAllOrders('pending');

        Response::success([
            'count' => count($orders),
            'orders' => $orders
        ], 'Pending orders retrieved', 200);
    }

    /**
     * POST /api/orders
     * Create new purchase order
     */
    public function create(): void
    {
        // Require admin role
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Add created_by from authenticated user
        $input['created_by'] = $user->user_id;

        // Create order
        $orderId = $this->orderService->createOrder($input);

        if ($orderId) {
            Response::success([
                'po_id' => $orderId
            ], 'Purchase order created successfully', 201);
        } else {
            Response::error('Failed to create purchase order', null, 400);
        }
    }

    /**
     * PUT /api/orders/{id}/status
     * Update purchase order status
     */
    public function updateStatus(int $id): void
    {
        // Require admin role
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['status'])) {
            Response::error('Status is required', null, 400);
            return;
        }

        // Update status
        $success = $this->orderService->updateOrderStatus($id, $input['status'], $user->user_id);

        if ($success) {
            Response::success(null, 'Purchase order status updated successfully', 200);
        } else {
            Response::error('Failed to update purchase order status', null, 400);
        }
    }

    /**
     * GET /api/orders/statistics
     * Get order statistics
     */
    public function getStatistics(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        $statistics = $this->orderService->getOrderStatistics();

        Response::success($statistics, 'Order statistics retrieved', 200);
    }
}
