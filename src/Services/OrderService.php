<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;
use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Repositories\SupplierRepository;
use Janstro\InventorySystem\Repositories\UserRepository;

/**
 * Order Service
 * Handles purchase order management business logic
 * ISO/IEC 25010: Functional Suitability, Reliability
 */
class OrderService
{
    private PurchaseOrderRepository $orderRepo;
    private InventoryRepository $inventoryRepo;
    private SupplierRepository $supplierRepo;
    private UserRepository $userRepo;

    public function __construct()
    {
        $this->orderRepo = new PurchaseOrderRepository();
        $this->inventoryRepo = new InventoryRepository();
        $this->supplierRepo = new SupplierRepository();
        $this->userRepo = new UserRepository();
    }

    /**
     * Get all purchase orders
     * 
     * @param string|null $status Filter by status (pending, delivered, cancelled)
     * @return array Array of purchase orders
     */
    public function getAllOrders(?string $status = null): array
    {
        $orders = $this->orderRepo->getAll($status);
        return array_map(fn($order) => $order->toArray(), $orders);
    }

    /**
     * Get single purchase order by ID
     * 
     * @param int $poId Purchase order ID
     * @return array|null Order data or null
     */
    public function getOrder(int $poId): ?array
    {
        $order = $this->orderRepo->findById($poId);
        return $order ? $order->toArray() : null;
    }

    /**
     * Create new purchase order
     * 
     * @param array $data Order data
     * @return int|null New order ID or null on failure
     */
    public function createOrder(array $data): ?int
    {
        // Validate required fields
        $required = ['supplier_id', 'item_id', 'quantity', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                error_log("OrderService::createOrder - Missing required field: $field");
                return null;
            }
        }

        // Validate supplier exists
        $supplier = $this->supplierRepo->findById($data['supplier_id']);
        if (!$supplier) {
            error_log("OrderService::createOrder - Supplier {$data['supplier_id']} not found");
            return null;
        }

        // Validate item exists
        $item = $this->inventoryRepo->findById($data['item_id']);
        if (!$item) {
            error_log("OrderService::createOrder - Item {$data['item_id']} not found");
            return null;
        }

        // Validate user exists
        $user = $this->userRepo->findById($data['created_by']);
        if (!$user) {
            error_log("OrderService::createOrder - User {$data['created_by']} not found");
            return null;
        }

        // Validate quantity
        if (!is_numeric($data['quantity']) || $data['quantity'] <= 0) {
            error_log("OrderService::createOrder - Invalid quantity: {$data['quantity']}");
            return null;
        }

        // Calculate total amount if not provided
        if (!isset($data['total_amount'])) {
            $data['total_amount'] = $item->unit_price * $data['quantity'];
        }

        // Set default status
        $data['status'] = $data['status'] ?? 'pending';

        // Create order
        $orderId = $this->orderRepo->create($data);

        if ($orderId) {
            // Log audit
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created purchase order #$orderId for {$item->item_name} (Qty: {$data['quantity']})"
            );
        }

        return $orderId;
    }

    /**
     * Update purchase order status
     * 
     * @param int $poId Purchase order ID
     * @param string $status New status
     * @param int $userId User performing the update
     * @return bool Success status
     */
    public function updateOrderStatus(int $poId, string $status, int $userId): bool
    {
        // Validate status
        $validStatuses = ['pending', 'approved', 'delivered', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            error_log("OrderService::updateOrderStatus - Invalid status: $status");
            return false;
        }

        // Get order
        $order = $this->orderRepo->findById($poId);
        if (!$order) {
            error_log("OrderService::updateOrderStatus - Order $poId not found");
            return false;
        }

        // Update status
        $success = $this->orderRepo->updateStatus($poId, $status);

        if ($success) {
            // If status is 'delivered', update inventory stock
            if ($status === 'delivered') {
                $this->inventoryRepo->updateStock($order->item_id, $order->quantity, 'IN');
                $this->inventoryRepo->logTransaction(
                    $order->item_id,
                    $userId,
                    'IN',
                    $order->quantity,
                    "Purchase Order #$poId delivered"
                );
            }

            // Log audit
            $this->userRepo->logAudit(
                $userId,
                "Updated PO #$poId status to: $status"
            );
        }

        return $success;
    }

    /**
     * Get pending orders count
     * 
     * @return int Number of pending orders
     */
    public function getPendingCount(): int
    {
        return $this->orderRepo->getPendingCount();
    }

    /**
     * Get order statistics
     * 
     * @return array Order statistics
     */
    public function getOrderStatistics(): array
    {
        $allOrders = $this->orderRepo->getAll();
        $pendingOrders = $this->orderRepo->getAll('pending');
        $deliveredOrders = $this->orderRepo->getAll('delivered');

        $totalAmount = 0;
        foreach ($deliveredOrders as $order) {
            $totalAmount += $order->total_amount;
        }

        return [
            'total_orders' => count($allOrders),
            'pending_orders' => count($pendingOrders),
            'delivered_orders' => count($deliveredOrders),
            'total_procurement_value' => round($totalAmount, 2)
        ];
    }
}
