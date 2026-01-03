<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

/**
 * ============================================================================
 * COMPLETE INVENTORY SERVICE - ERP TRANSACTION-BASED v7.0
 * ============================================================================
 * ENHANCEMENTS:
 * ✅ Auto-update stock_requirements after PO receipt (LINE 155-175)
 * ✅ Resolve shortage notifications
 * ✅ Multi-item SO support
 * ✅ Enhanced stock requirement recalculation
 * ============================================================================
 */
class CompleteInventoryService
{
    private PDO $db;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->notificationService = new NotificationService();
    }

    // ============================================
    // INVENTORY STATUS
    // ============================================

    public function getCurrentStock(int $itemId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(
                CASE WHEN transaction_type = 'IN' THEN quantity 
                     ELSE -quantity END
            ), 0) as current_stock
            FROM transactions
            WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);
        return (int)$stmt->fetchColumn();
    }

    public function checkStockAvailability(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_current_inventory WHERE item_id = ?
        ");
        $stmt->execute([$itemId]);
        $stock = $stmt->fetch();

        if (!$stock) {
            throw new \Exception("Item not found: ID $itemId");
        }

        return [
            'item_id' => $stock['item_id'],
            'item_name' => $stock['item_name'],
            'sku' => $stock['sku'],
            'current_stock' => (int)$stock['current_stock'],
            'unit' => $stock['unit'],
            'reorder_level' => (int)$stock['reorder_level'],
            'is_low_stock' => $stock['stock_status'] === 'low_stock',
            'available_for_order' => $stock['current_stock'] > 0,
            'unit_price' => (float)$stock['unit_price'],
            'stock_status' => $stock['stock_status']
        ];
    }

    public function getInventoryStatus(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN stock_status = 'low_stock' THEN 1 ELSE 0 END) as low_stock_items,
                SUM(CASE WHEN stock_status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock_items,
                SUM(stock_value) as total_value
            FROM v_current_inventory
        ");

        $result = $stmt->fetch();

        return [
            'total_items' => (int)$result['total_items'],
            'low_stock_items' => (int)$result['low_stock_items'],
            'out_of_stock_items' => (int)$result['out_of_stock_items'],
            'total_inventory_value' => round((float)$result['total_value'], 2),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    // ============================================
    // GOODS RECEIPT (MIGO) - ENHANCED v7.0
    // ============================================

    public function receiveGoods(int $poId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Get PO details
            $stmt = $this->db->prepare("
                SELECT po.*, i.item_name, i.unit
                FROM purchase_orders po
                JOIN items i ON po.item_id = i.item_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch();

            if (!$po) {
                throw new \Exception("Purchase Order #$poId not found");
            }

            if ($po['status'] === 'delivered') {
                throw new \Exception("PO #$poId already received");
            }

            $receivedQty = $data['received_quantity'] ?? $po['quantity'];
            $userId = $data['user_id'];
            $notes = $data['notes'] ?? "Goods received from PO #$poId (MIGO)";

            // Get current stock BEFORE
            $previousStock = $this->getCurrentStock($po['item_id']);

            // Create IN transaction (trigger auto-updates items.quantity)
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    item_id, user_id, transaction_type, quantity, 
                    reference_type, reference_number, notes,
                    previous_quantity, new_quantity, movement_date
                ) VALUES (?, ?, 'IN', ?, 'PURCHASE_ORDER', ?, ?, ?, ?, NOW())
            ");

            $newStock = $previousStock + $receivedQty;

            $stmt->execute([
                $po['item_id'],
                $userId,
                $receivedQty,
                "PO-$poId",
                $notes,
                $previousStock,
                $newStock
            ]);

            // Update PO status
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'delivered', delivered_date = NOW()
                WHERE po_id = ?
            ");
            $stmt->execute([$poId]);

            // =====================================================
            // ✅ ENHANCEMENT v7.0: UPDATE STOCK REQUIREMENTS
            // =====================================================
            $resolvedRequirements = $this->updateStockRequirementsAfterReceipt(
                $po['item_id'],
                $newStock
            );

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'inventory', 'goods_receipt', ?)
            ");
            $stmt->execute([
                $userId,
                "Goods Receipt: PO #$poId | {$po['item_name']} x $receivedQty | Stock: $previousStock → $newStock | Updated {$resolvedRequirements} requirements (MIGO)",
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);

            $this->db->commit();

            // Send notification
            $this->notificationService->notifyPODelivered($poId, [
                'item_name' => $po['item_name'],
                'quantity' => $receivedQty,
                'unit' => $po['unit'],
                'new_stock' => $newStock,
                'resolved_requirements' => $resolvedRequirements
            ]);

            return [
                'success' => true,
                'po_id' => $poId,
                'item_name' => $po['item_name'],
                'received_quantity' => $receivedQty,
                'unit' => $po['unit'],
                'previous_stock' => $previousStock,
                'new_stock_level' => $newStock,
                'resolved_requirements' => $resolvedRequirements,
                'message' => "Goods received. Material Document created (MB51). $resolvedRequirements stock requirements updated."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ============================================
    // GOODS ISSUE (VF01)
    // ============================================

    public function processSimpleInvoice(int $salesOrderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Get SO details
            $stmt = $this->db->prepare("
                SELECT 
                    so.*, soi.item_id, soi.quantity, soi.unit_price,
                    i.item_name, i.unit
                FROM sales_orders so
                JOIN sales_order_items soi ON so.sales_order_id = soi.sales_order_id
                JOIN items i ON soi.item_id = i.item_id
                WHERE so.sales_order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$salesOrderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new \Exception("Sales Order #$salesOrderId not found");
            }

            if ($order['status'] === 'completed') {
                throw new \Exception("Invoice already processed for SO #$salesOrderId");
            }

            // Check stock availability
            $currentStock = $this->getCurrentStock($order['item_id']);

            if ($currentStock < $order['quantity']) {
                throw new \Exception("Insufficient stock. Available: $currentStock, Required: {$order['quantity']}");
            }

            // Create OUT transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    item_id, user_id, transaction_type, quantity,
                    reference_type, reference_number, notes,
                    previous_quantity, new_quantity, movement_date
                ) VALUES (?, ?, 'OUT', ?, 'INVOICE', ?, ?, ?, ?, NOW())
            ");

            $newStock = $currentStock - $order['quantity'];
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($salesOrderId, 6, '0', STR_PAD_LEFT);

            $stmt->execute([
                $order['item_id'],
                $userId,
                $order['quantity'],
                $invoiceNumber,
                "Goods issued for SO #$salesOrderId (VF01)",
                $currentStock,
                $newStock
            ]);

            // Update SO status
            $stmt = $this->db->prepare("
                UPDATE sales_orders 
                SET status = 'completed', completed_by = ?, completed_date = NOW()
                WHERE sales_order_id = ?
            ");
            $stmt->execute([$userId, $salesOrderId]);

            // Mark stock requirement as fulfilled
            $stmt = $this->db->prepare("
                UPDATE stock_requirements 
                SET status = 'fulfilled'
                WHERE sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'sales', 'goods_issue', ?)
            ");
            $stmt->execute([
                $userId,
                "Goods Issue: SO #$salesOrderId | {$order['item_name']} x {$order['quantity']} | Stock: $currentStock → $newStock (VF01)",
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $order['customer_name'],
                'item_name' => $order['item_name'],
                'quantity' => $order['quantity'],
                'unit' => $order['unit'],
                'previous_stock' => $currentStock,
                'new_stock_level' => $newStock,
                'total_amount' => $order['total_amount'],
                'message' => "Invoice $invoiceNumber processed. Material Document created (MB51)."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ============================================
    // PURCHASE ORDER CREATION (ME21N)
    // ============================================

    public function createPurchaseOrder(array $data): array
    {
        $required = ['supplier_id', 'item_id', 'quantity', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT item_name, unit_price, unit FROM items WHERE item_id = ?");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new \Exception("Item not found");
            }

            $unitPrice = $data['unit_price'] ?? $item['unit_price'];
            $totalAmount = $unitPrice * $data['quantity'];

            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders 
                (supplier_id, item_id, quantity, unit_price, total_amount, status, created_by, expected_delivery_date, notes)
                VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['item_id'],
                $data['quantity'],
                $unitPrice,
                $totalAmount,
                $data['created_by'],
                $data['expected_delivery_date'] ?? date('Y-m-d', strtotime('+7 days')),
                $data['notes'] ?? null
            ]);

            $poId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'purchase', 'po_created', ?)
            ");
            $stmt->execute([
                $data['created_by'],
                "Created Purchase Order #$poId: {$item['item_name']} x {$data['quantity']} (ME21N)",
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);

            $this->db->commit();

            $this->notificationService->notifyNewOrder('purchase_order', $poId, [
                'item_name' => $item['item_name'],
                'quantity' => $data['quantity'],
                'unit' => $item['unit'],
                'total_amount' => $totalAmount
            ]);

            return [
                'success' => true,
                'po_id' => $poId,
                'status' => 'pending',
                'message' => "Purchase Order #$poId created successfully",
                'item_name' => $item['item_name'],
                'quantity' => $data['quantity'],
                'unit' => $item['unit'],
                'total_amount' => $totalAmount
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ============================================
    // SALES ORDER CREATION (VA01) - ENHANCED v7.0
    // ============================================

    public function createSimpleSalesOrder(array $data): array
    {
        $required = ['customer_id', 'item_id', 'quantity', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            // Fetch customer
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE customer_id = ?");
            $stmt->execute([$data['customer_id']]);
            $customer = $stmt->fetch();
            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Check stock
            $stockCheck = $this->checkStockAvailability($data['item_id']);
            $unitPrice = (float)$stockCheck['unit_price'];
            $totalAmount = $unitPrice * $data['quantity'];

            // Insert sales order
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_id, customer_name, contact_number, delivery_address,
                    order_date, installation_date, total_amount, status, created_by, notes,
                    customer_order_number
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', ?, ?, ?)
            ");
            $stmt->execute([
                $customer['customer_id'],
                $customer['customer_name'],
                $customer['contact_number'],
                $data['delivery_address'] ?? null,
                $data['installation_date'] ?? date('Y-m-d', strtotime('+7 days')),
                $totalAmount,
                $data['created_by'],
                $data['notes'] ?? null,
                $data['customer_order_number'] ?? null
            ]);
            $salesOrderId = (int)$this->db->lastInsertId();

            // Insert sales order items
            $stmt = $this->db->prepare("
                INSERT INTO sales_order_items (sales_order_id, item_id, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$salesOrderId, $data['item_id'], $data['quantity'], $unitPrice, $totalAmount]);

            // ✅ ENHANCEMENT v7.0: Auto-generate stock requirements
            $currentStock = $this->getCurrentStock($data['item_id']);

            $stmt = $this->db->prepare("
                INSERT INTO stock_requirements (
                    sales_order_id,
                    item_id,
                    required_quantity,
                    available_quantity
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $salesOrderId,
                $data['item_id'],
                $data['quantity'],
                $currentStock
            ]);

            $shortage = max(0, $data['quantity'] - $currentStock);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'sales', 'so_created', ?)
            ");
            $stmt->execute([
                $data['created_by'],
                "Created Sales Order #$salesOrderId for {$customer['customer_name']} | {$stockCheck['item_name']} x {$data['quantity']} | " .
                    ($shortage > 0 ? "⚠ Shortage: $shortage units" : "✓ Stock sufficient") . " (VA01)",
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);

            $this->db->commit();

            // Send notification
            $this->notificationService->notifyNewOrder('sales_order', $salesOrderId, [
                'customer_name' => $customer['customer_name'],
                'item_name' => $stockCheck['item_name'],
                'quantity' => $data['quantity'],
                'total_amount' => $totalAmount,
                'stock_available' => $currentStock,
                'shortage' => $shortage
            ]);

            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'customer_name' => $customer['customer_name'],
                'item_name' => $stockCheck['item_name'],
                'quantity' => $data['quantity'],
                'total_amount' => $totalAmount,
                'stock_status' => [
                    'required' => $data['quantity'],
                    'available' => $currentStock,
                    'shortage' => $shortage,
                    'can_fulfill' => $shortage <= 0
                ],
                'message' => $shortage <= 0
                    ? "Sales Order #$salesOrderId created. Stock available."
                    : "Sales Order #$salesOrderId created. ⚠ Stock shortage: $shortage units. PR generation recommended."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // =====================================================
    // ✅ v7.0: ENHANCED STOCK REQUIREMENT UPDATE LOGIC
    // =====================================================

    private function updateStockRequirementsAfterReceipt(int $itemId, int $newStock): int
    {
        try {
            // Update all pending stock requirements for this item
            $stmt = $this->db->prepare("
                UPDATE stock_requirements 
                SET available_quantity = ?,
                    updated_at = NOW()
                WHERE item_id = ? 
                AND status IN ('shortage', 'critical', 'sufficient')
            ");
            $stmt->execute([$newStock, $itemId]);

            $affectedRows = $stmt->rowCount();

            if ($affectedRows > 0) {
                // Get details of resolved shortages for notification
                $stmt = $this->db->prepare("
                    SELECT 
                        sr.requirement_id,
                        sr.sales_order_id,
                        sr.status as new_status,
                        sr.shortage_quantity,
                        so.customer_name,
                        i.item_name
                    FROM stock_requirements sr
                    JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                    JOIN items i ON sr.item_id = i.item_id
                    WHERE sr.item_id = ?
                    AND sr.available_quantity >= sr.required_quantity
                    AND so.status = 'pending'
                ");
                $stmt->execute([$itemId]);
                $resolvedRequirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Send notifications for resolved shortages
                if (!empty($resolvedRequirements)) {
                    foreach ($resolvedRequirements as $req) {
                        $this->notificationService->notifyShortageResolved(
                            $req['sales_order_id'],
                            [
                                'customer_name' => $req['customer_name'],
                                'item_name' => $req['item_name'],
                                'new_stock' => $newStock
                            ]
                        );
                    }
                }

                error_log("✅ Updated $affectedRows stock requirements after goods receipt for item #$itemId");
            }

            return $affectedRows;
        } catch (\Exception $e) {
            error_log("⚠️ Failed to update stock requirements: " . $e->getMessage());
            return 0;
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function getAllSalesOrders(): array
    {
        $stmt = $this->db->query("
            SELECT 
                so.*, u.name AS created_by_name,
                GROUP_CONCAT(CONCAT(i.item_name, ' x', soi.quantity) SEPARATOR ', ') AS items_summary
            FROM sales_orders so
            LEFT JOIN users u ON so.created_by = u.user_id
            LEFT JOIN sales_order_items soi ON so.sales_order_id = soi.sales_order_id
            LEFT JOIN items i ON soi.item_id = i.item_id
            GROUP BY so.sales_order_id
            ORDER BY so.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public function getMaterialDocuments(array $filters = []): array
    {
        $sql = "SELECT * FROM v_stock_movements WHERE 1=1";
        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND item_id = ?";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND transaction_type = ?";
            $params[] = $filters['type'];
        }

        $sql .= " ORDER BY movement_date DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
