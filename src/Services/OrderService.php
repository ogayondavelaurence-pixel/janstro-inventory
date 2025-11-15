<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;
use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Repositories\SupplierRepository;
use Janstro\InventorySystem\Repositories\UserRepository;

/**
 * Order Service - FIXED v2.1
 * Now supports multi-item purchase orders
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
     */
    public function getAllOrders(?string $status = null): array
    {
        return $this->orderRepo->getAll($status);
    }

    /**
     * Get single purchase order by ID
     */
    public function getOrder(int $poId): ?array
    {
        return $this->orderRepo->findById($poId);
    }

    /**
     * Create new purchase order (FIXED - Multi-item support)
     * 
     * @param array $data Order data with 'items' array
     * @return int|null New order ID or null on failure
     */
    public function createOrder(array $data): ?int
    {
        // Validate required fields
        $required = ['supplier_id', 'items', 'created_by'];
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

        // Validate user exists
        $user = $this->userRepo->findById($data['created_by']);
        if (!$user) {
            error_log("OrderService::createOrder - User {$data['created_by']} not found");
            return null;
        }

        // Validate and prepare items
        $preparedItems = [];
        foreach ($data['items'] as $item) {
            // Validate item exists
            $itemData = $this->inventoryRepo->findById($item['item_id']);
            if (!$itemData) {
                error_log("OrderService::createOrder - Item {$item['item_id']} not found");
                return null;
            }

            // Validate quantity
            if (!is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                error_log("OrderService::createOrder - Invalid quantity: {$item['quantity']}");
                return null;
            }

            // Use provided price or default to item's unit price
            $unitPrice = isset($item['unit_price']) && $item['unit_price'] > 0
                ? $item['unit_price']
                : $itemData->unit_price;

            $preparedItems[] = [
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice
            ];
        }

        // Update data with prepared items
        $data['items'] = $preparedItems;
        $data['status'] = $data['status'] ?? 'pending';

        // Create order
        $orderId = $this->orderRepo->create($data);

        if ($orderId) {
            // Calculate total for audit log
            $totalAmount = 0;
            foreach ($preparedItems as $item) {
                $totalAmount += $item['quantity'] * $item['unit_price'];
            }

            // Log audit
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created purchase order #$orderId with " . count($preparedItems) . " items (Total: ₱" . number_format($totalAmount, 2) . ")"
            );
        }

        return $orderId;
    }

    /**
     * Update purchase order status
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
            // If status is 'delivered', inventory is handled by stored procedure
            // (sp_receive_purchase_order should be called separately via API)

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
     */
    public function getPendingCount(): int
    {
        return $this->orderRepo->getPendingCount();
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(): array
    {
        $allOrders = $this->orderRepo->getAll();
        $pendingOrders = $this->orderRepo->getAll('pending');
        $deliveredOrders = $this->orderRepo->getAll('delivered');

        $totalAmount = 0;
        foreach ($deliveredOrders as $order) {
            $totalAmount += $order['total_amount'] ?? 0;
        }

        return [
            'total_orders' => count($allOrders),
            'pending_orders' => count($pendingOrders),
            'delivered_orders' => count($deliveredOrders),
            'total_procurement_value' => round($totalAmount, 2)
        ];
    }
}
