<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * ORDER SERVICE v3.0 - PRODUCTION READY
 * ============================================================================
 * Full PO/SO lifecycle management with CompleteInventoryService integration
 * ============================================================================
 */
class OrderService
{
    private PDO $db;
    private CompleteInventoryService $inventoryService;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->inventoryService = new CompleteInventoryService();
        $this->notificationService = new NotificationService();
    }

    // ========================================================================
    // PURCHASE ORDER OPERATIONS
    // ========================================================================

    /**
     * Get all purchase orders
     */
    public function getAllPurchaseOrders(?string $status = null): array
    {
        try {
            $sql = "
                SELECT 
                    po.po_id, po.supplier_id, s.supplier_name,
                    po.item_id, i.item_name, i.sku, i.unit,
                    po.quantity, po.unit_price, po.total_amount,
                    po.expected_delivery_date, po.status, po.notes,
                    po.po_date, po.delivered_date,
                    po.created_by, u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
            ";

            if ($status) {
                $sql .= " WHERE po.status = ?";
                $stmt = $this->db->prepare($sql . " ORDER BY po.po_date DESC");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->db->query($sql . " ORDER BY po.po_date DESC");
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("OrderService::getAllPurchaseOrders - " . $e->getMessage());
            throw new Exception('Failed to retrieve purchase orders');
        }
    }

    /**
     * Get purchase order by ID
     */
    public function getPurchaseOrderById(int $poId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    po.*, s.supplier_name, s.contact_person, s.phone, 
                    s.email, s.address as supplier_address,
                    i.item_name, i.sku, i.unit,
                    u.name as created_by_name, u.email as created_by_email
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);

            $po = $stmt->fetch(PDO::FETCH_ASSOC);
            return $po ?: null;
        } catch (Exception $e) {
            error_log("OrderService::getPurchaseOrderById - " . $e->getMessage());
            throw new Exception('Failed to retrieve purchase order');
        }
    }

    /**
     * Create purchase order
     */
    public function createPurchaseOrder(array $data, int $userId): array
    {
        $required = ['supplier_id', 'item_id', 'quantity'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if ($data['quantity'] <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }

        try {
            $this->db->beginTransaction();

            // Validate supplier
            $stmt = $this->db->prepare("
                SELECT supplier_id, supplier_name, status 
                FROM suppliers 
                WHERE supplier_id = ?
            ");
            $stmt->execute([$data['supplier_id']]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                throw new Exception('Supplier not found');
            }

            if ($supplier['status'] !== 'active') {
                throw new Exception('Supplier is not active');
            }

            // Validate item
            $stmt = $this->db->prepare("
                SELECT item_id, item_name, unit_price, status 
                FROM items 
                WHERE item_id = ?
            ");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                throw new Exception('Item not found');
            }

            if ($item['status'] !== 'active') {
                throw new Exception('Item is not active');
            }

            // Calculate total
            $unitPrice = $data['unit_price'] ?? $item['unit_price'];
            $totalAmount = $data['quantity'] * $unitPrice;

            // Insert PO
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    supplier_id, item_id, quantity, unit_price, total_amount,
                    expected_delivery_date, status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['item_id'],
                $data['quantity'],
                $unitPrice,
                $totalAmount,
                $data['expected_delivery_date'] ?? date('Y-m-d', strtotime('+7 days')),
                $data['notes'] ?? null,
                $userId
            ]);

            $poId = (int)$this->db->lastInsertId();

            // Audit log
            $this->createAuditLog(
                $userId,
                "Created PO #{$poId}: {$item['item_name']} x {$data['quantity']} from {$supplier['supplier_name']}",
                'purchase_orders',
                'create'
            );

            $this->db->commit();

            // Send notification
            $this->notificationService->notifyNewOrder('purchase_order', $poId, [
                'item_name' => $item['item_name'],
                'quantity' => $data['quantity'],
                'supplier' => $supplier['supplier_name'],
                'total_amount' => $totalAmount
            ]);

            return [
                'success' => true,
                'po_id' => $poId,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'message' => "Purchase order #{$poId} created successfully"
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("OrderService::createPurchaseOrder - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update purchase order (only if pending)
     */
    public function updatePurchaseOrder(int $poId, array $data, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Check PO exists and is pending
            $stmt = $this->db->prepare("
                SELECT po_id, status, item_name
                FROM purchase_orders po
                LEFT JOIN items i ON po.item_id = i.item_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                throw new Exception('Purchase order not found');
            }

            if ($po['status'] !== 'pending') {
                throw new Exception('Can only update pending orders');
            }

            // Build update
            $updates = [];
            $values = [];
            $changes = [];

            $allowedFields = [
                'supplier_id',
                'item_id',
                'quantity',
                'unit_price',
                'expected_delivery_date',
                'notes'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = $data[$field];
                    $changes[] = $field;
                }
            }

            if (empty($updates)) {
                throw new Exception('No fields to update');
            }

            // Recalculate total if needed
            if (isset($data['quantity']) || isset($data['unit_price'])) {
                $stmt = $this->db->prepare("SELECT quantity, unit_price FROM purchase_orders WHERE po_id = ?");
                $stmt->execute([$poId]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                $newQty = $data['quantity'] ?? $current['quantity'];
                $newPrice = $data['unit_price'] ?? $current['unit_price'];
                $newTotal = $newQty * $newPrice;

                $updates[] = "total_amount = ?";
                $values[] = $newTotal;
            }

            $values[] = $poId;
            $sql = "UPDATE purchase_orders SET " . implode(', ', $updates) . " WHERE po_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            // Audit log
            $this->createAuditLog(
                $userId,
                "Updated PO #{$poId} - Fields: " . implode(', ', $changes),
                'purchase_orders',
                'update'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Purchase order updated successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("OrderService::updatePurchaseOrder - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Approve purchase order (ADMIN)
     */
    public function approvePurchaseOrder(int $poId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT po_id, status, item_name, quantity
                FROM purchase_orders po
                LEFT JOIN items i ON po.item_id = i.item_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                throw new Exception('Purchase order not found');
            }

            if ($po['status'] !== 'pending') {
                throw new Exception('Only pending orders can be approved');
            }

            // Update status
            $stmt = $this->db->prepare("UPDATE purchase_orders SET status = 'approved' WHERE po_id = ?");
            $stmt->execute([$poId]);

            // Audit log
            $this->createAuditLog(
                $userId,
                "Approved PO #{$poId} - {$po['item_name']} x {$po['quantity']}",
                'purchase_orders',
                'approve'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Purchase order #{$poId} approved"
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("OrderService::approvePurchaseOrder - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Receive goods (MIGO) - delegates to CompleteInventoryService
     */
    public function receiveGoods(int $poId, array $data, int $userId): array
    {
        try {
            $data['user_id'] = $userId;

            // Delegate to CompleteInventoryService (handles transactions, triggers, notifications)
            return $this->inventoryService->receiveGoods($poId, $data);
        } catch (Exception $e) {
            error_log("OrderService::receiveGoods - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel purchase order
     */
    public function cancelPurchaseOrder(int $poId, int $userId, ?string $reason = null): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT po_id, status, item_name
                FROM purchase_orders po
                LEFT JOIN items i ON po.item_id = i.item_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                throw new Exception('Purchase order not found');
            }

            if ($po['status'] === 'delivered') {
                throw new Exception('Cannot cancel delivered order');
            }

            if ($po['status'] === 'cancelled') {
                throw new Exception('Order already cancelled');
            }

            // Update status
            $stmt = $this->db->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE po_id = ?");
            $stmt->execute([$poId]);

            // Audit log
            $reasonText = $reason ? " - Reason: {$reason}" : '';
            $this->createAuditLog(
                $userId,
                "Cancelled PO #{$poId}{$reasonText}",
                'purchase_orders',
                'cancel'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Purchase order #{$poId} cancelled"
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("OrderService::cancelPurchaseOrder - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // SALES ORDER OPERATIONS
    // ========================================================================

    /**
     * Get all sales orders
     */
    public function getAllSalesOrders(?string $status = null): array
    {
        try {
            return $this->inventoryService->getAllSalesOrders();
        } catch (Exception $e) {
            error_log("OrderService::getAllSalesOrders - " . $e->getMessage());
            throw new Exception('Failed to retrieve sales orders');
        }
    }

    /**
     * Create sales order (delegates to CompleteInventoryService)
     */
    public function createSalesOrder(array $data, int $userId): array
    {
        try {
            $data['created_by'] = $userId;
            return $this->inventoryService->createSimpleSalesOrder($data);
        } catch (Exception $e) {
            error_log("OrderService::createSalesOrder - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete sales order (generates invoice, goods issue)
     */
    public function completeSalesOrder(int $salesOrderId, int $userId): array
    {
        try {
            return $this->inventoryService->processSimpleInvoice($salesOrderId, $userId);
        } catch (Exception $e) {
            error_log("OrderService::completeSalesOrder - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // STATISTICS & REPORTING
    // ========================================================================

    /**
     * Get purchase order statistics
     */
    public function getPurchaseOrderStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                    COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as total_procurement_value
                FROM purchase_orders
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("OrderService::getPurchaseOrderStats - " . $e->getMessage());
            throw new Exception('Failed to retrieve statistics');
        }
    }

    /**
     * Get pending purchase orders count
     */
    public function getPendingPurchaseOrdersCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) 
                FROM purchase_orders 
                WHERE status = 'pending'
            ");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("OrderService::getPendingPurchaseOrdersCount - " . $e->getMessage());
            return 0;
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    /**
     * Create audit log entry
     */
    private function createAuditLog(
        int $userId,
        string $description,
        string $module,
        string $actionType
    ): void {
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

    /**
     * Validate purchase order status transition
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $allowedTransitions = [
            'pending' => ['approved', 'cancelled'],
            'approved' => ['delivered', 'cancelled'],
            'delivered' => [],
            'cancelled' => []
        ];

        return in_array($newStatus, $allowedTransitions[$currentStatus] ?? []);
    }

    /**
     * Check if user can modify order
     */
    public function canModifyOrder(int $poId, int $userId, string $userRole): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT created_by, status 
                FROM purchase_orders 
                WHERE po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                return false;
            }

            // Superadmin can modify anything
            if ($userRole === 'superadmin') {
                return true;
            }

            // Admin can modify pending orders
            if ($userRole === 'admin' && $po['status'] === 'pending') {
                return true;
            }

            // Staff can only modify their own pending orders
            if ($userRole === 'staff' && $po['status'] === 'pending' && $po['created_by'] == $userId) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("OrderService::canModifyOrder - " . $e->getMessage());
            return false;
        }
    }
}
