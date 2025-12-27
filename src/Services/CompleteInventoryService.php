<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use PDOException;

/**
 * Complete Inventory Service - NO STORED PROCEDURES VERSION
 * 
 * All stored procedure calls replaced with direct SQL
 * 
 * @version 4.0.0 - Stored Procedure Free
 * @date 2025-12-10
 */
class CompleteInventoryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ============================================
    // CUSTOMER MASTER DATA
    // ============================================

    public function createOrFindCustomer(array $data): int
    {
        $stmt = $this->db->prepare("
            SELECT customer_id FROM customers 
            WHERE contact_number = ? OR email = ?
            LIMIT 1
        ");
        $stmt->execute([
            $data['contact_number'] ?? null,
            $data['email'] ?? null
        ]);

        $existing = $stmt->fetch();
        if ($existing) {
            return (int)$existing['customer_id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO customers (customer_name, contact_number, email, address, customer_type, notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['customer_name'],
            $data['contact_number'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['customer_type'] ?? 'individual',
            $data['notes'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    // ============================================
    // INVENTORY STATUS & CHECKING (MMBE)
    // ============================================

    public function checkStockAvailability(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.item_id,
                i.item_name,
                i.sku,
                i.quantity AS current_stock,
                i.unit,
                i.reorder_level,
                i.unit_price,
                i.is_bom_item,
                c.name AS category_name,
                CASE 
                    WHEN i.quantity = 0 THEN 'Out of Stock'
                    WHEN i.quantity <= i.reorder_level THEN 'Low Stock'
                    ELSE 'In Stock'
                END AS stock_status
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.category_id
            WHERE i.item_id = ?
        ");

        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            throw new \Exception("Item not found: ID $itemId");
        }

        $bom_status = null;
        if ($item['is_bom_item']) {
            $bom_status = $this->checkBOMAvailability($itemId);
        }

        return [
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'sku' => $item['sku'],
            'current_stock' => (int)$item['current_stock'],
            'unit' => $item['unit'],
            'reorder_level' => (int)$item['reorder_level'],
            'is_low_stock' => $item['current_stock'] <= $item['reorder_level'],
            'available_for_order' => $item['current_stock'] > 0,
            'unit_price' => (float)$item['unit_price'],
            'stock_status' => $item['stock_status'],
            'is_bom_item' => (bool)$item['is_bom_item'],
            'bom_status' => $bom_status
        ];
    }

    public function checkBOMAvailability(int $parentItemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                b.component_item_id,
                c.item_name AS component_name,
                b.quantity_required,
                c.quantity AS available_stock,
                c.unit,
                CASE 
                    WHEN c.quantity >= b.quantity_required THEN TRUE
                    ELSE FALSE
                END AS is_available
            FROM bill_of_materials b
            JOIN items c ON b.component_item_id = c.item_id
            WHERE b.parent_item_id = ?
        ");

        $stmt->execute([$parentItemId]);
        $components = $stmt->fetchAll();

        $all_available = true;
        $missing_components = [];

        foreach ($components as $comp) {
            if (!$comp['is_available']) {
                $all_available = false;
                $missing_components[] = [
                    'component_name' => $comp['component_name'],
                    'required' => $comp['quantity_required'],
                    'available' => $comp['available_stock'],
                    'shortage' => $comp['quantity_required'] - $comp['available_stock']
                ];
            }
        }

        return [
            'all_components_available' => $all_available,
            'total_components' => count($components),
            'missing_components' => $missing_components
        ];
    }

    public function getInventoryStatus(): array
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE status = 'active'");
        $totalItems = (int)$stmt->fetch()['total'];

        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE quantity <= reorder_level");
        $lowStockItems = (int)$stmt->fetch()['total'];

        $stmt = $this->db->query("SELECT SUM(quantity * unit_price) as total FROM items");
        $totalValue = (float)($stmt->fetch()['total'] ?? 0);

        $stmt = $this->db->query("
            SELECT c.name, COUNT(i.item_id) as count, SUM(i.quantity * i.unit_price) as value
            FROM categories c
            LEFT JOIN items i ON c.category_id = i.category_id
            GROUP BY c.category_id, c.name
        ");
        $categoryBreakdown = [];
        while ($row = $stmt->fetch()) {
            $categoryBreakdown[$row['name']] = [
                'count' => (int)$row['count'],
                'value' => (float)$row['value']
            ];
        }

        return [
            'total_items' => $totalItems,
            'low_stock_items' => $lowStockItems,
            'total_inventory_value' => round($totalValue, 2),
            'items_by_category' => $categoryBreakdown,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    // ============================================
    // PURCHASE ORDER FLOW (ME21N → MIGO)
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
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $data['created_by'],
                "Created Purchase Order #$poId: {$item['item_name']} x {$data['quantity']}"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'po_id' => $poId,
                'status' => 'pending',
                'message' => "Purchase Order #$poId created successfully (SAP: ME21N)",
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

    /**
     * GOODS RECEIPT (SAP: MIGO) - Stock IN
     * ✅ FIXED: Direct SQL instead of stored procedure
     */
    public function receiveGoods(int $poId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT po.*, i.item_name, i.unit, i.quantity AS current_stock
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

            if ($po['status'] === 'cancelled') {
                throw new \Exception("PO #$poId is cancelled");
            }

            $receivedQty = $data['received_quantity'] ?? $po['quantity'];
            $userId = $data['user_id'];
            $notes = $data['notes'] ?? "Goods received from supplier (MIGO)";

            // ✅ DIRECT SQL REPLACEMENT (Location #1)
            $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
            $stmt->execute([$po['item_id']]);
            $currentStock = (int)$stmt->fetchColumn();

            // Update stock (IN)
            $stmt = $this->db->prepare("UPDATE items SET quantity = quantity + ? WHERE item_id = ?");
            $stmt->execute([$receivedQty, $po['item_id']]);

            // Log transaction
            $stmt = $this->db->prepare("
                INSERT INTO transactions (
                    item_id, user_id, transaction_type, quantity, 
                    reference_number, notes, previous_quantity, new_quantity
                ) VALUES (?, ?, 'IN', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $po['item_id'],
                $userId,
                $receivedQty,
                "PO-$poId",
                $notes,
                $currentStock,                    // previous_quantity
                $currentStock + $receivedQty       // new_quantity
            ]);

            // Get new stock level
            $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ?");
            $stmt->execute([$po['item_id']]);
            $newStockLevel = (int)$stmt->fetchColumn();

            // Update PO status
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'delivered', delivered_date = NOW()
                WHERE po_id = ?
            ");
            $stmt->execute([$poId]);

            // Update stock requirements
            $stmt = $this->db->prepare("
                UPDATE stock_requirements 
                SET available_quantity = ?,
                    updated_at = NOW()
                WHERE item_id = ? 
                AND status IN ('shortage', 'critical')
            ");
            $stmt->execute([$newStockLevel, $po['item_id']]);

            $affectedRequirements = $stmt->rowCount();

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $userId,
                "Goods Receipt: PO #$poId | {$po['item_name']} x $receivedQty | Stock IN (MIGO) | Updated $affectedRequirements stock requirements"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'po_id' => $poId,
                'item_name' => $po['item_name'],
                'received_quantity' => $receivedQty,
                'unit' => $po['unit'],
                'previous_stock' => (int)$po['current_stock'],
                'new_stock_level' => $newStockLevel,
                'stock_requirements_updated' => $affectedRequirements,
                'message' => "Goods received successfully. Material Document created (MB51). $affectedRequirements pending orders now have stock."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Create a simple sales order (VA01)
     * ✅ PHASE 5: Auto-generates PR when stock shortage detected
     */
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

            // Validate customer exists
            $stmt = $this->db->prepare("
                SELECT customer_id, customer_name, contact_number, email 
                FROM customers 
                WHERE customer_id = ?
            ");
            $stmt->execute([$data['customer_id']]);
            $customer = $stmt->fetch();

            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Validate item and get stock
            $stockCheck = $this->checkStockAvailability($data['item_id']);

            $unitPrice = (float)$stockCheck['unit_price'];
            $totalAmount = $unitPrice * $data['quantity'];
            $installationDate = $data['installation_date'] ?? date('Y-m-d', strtotime('+7 days'));

            // Insert sales order
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_id,
                    customer_order_number,
                    customer_name,
                    contact_number,
                    delivery_address,
                    order_date,
                    installation_date,
                    total_amount,
                    status,
                    created_by,
                    notes
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 'pending', ?, ?)
            ");

            $stmt->execute([
                $customer['customer_id'],
                $data['customer_order_number'] ?? null,
                $customer['customer_name'],
                $customer['contact_number'] ?? $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $installationDate,
                $totalAmount,
                $data['created_by'],
                $data['notes'] ?? null
            ]);

            $salesOrderId = (int)$this->db->lastInsertId();

            // Insert sales order items
            $stmt = $this->db->prepare("
                INSERT INTO sales_order_items (sales_order_id, item_id, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $salesOrderId,
                $data['item_id'],
                $data['quantity'],
                $unitPrice,
                $totalAmount
            ]);

            // Create stock requirement
            $currentStock = (int)$stockCheck['current_stock'];
            $requiredQty = (int)$data['quantity'];

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
                $requiredQty,
                $currentStock
            ]);

            $shortage = $requiredQty - $currentStock;
            $canFulfill = $shortage <= 0;
            $prGenerated = false;
            $prNumber = null;

            // ✅ PHASE 5: Auto-generate PR if stock shortage
            if (!$canFulfill) {
                $prNumber = $this->generatePRNumber();

                // Determine urgency based on shortage severity
                $urgency = 'medium';
                if ($shortage > $currentStock * 2) {
                    $urgency = 'critical';
                } elseif ($shortage > $currentStock) {
                    $urgency = 'high';
                }

                $stmt = $this->db->prepare("
                    INSERT INTO purchase_requisitions (
                        pr_number,
                        sales_order_id,
                        item_id,
                        required_quantity,
                        requested_by,
                        status,
                        urgency,
                        reason
                    ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
                ");

                $reason = "Auto-generated PR for SO #{$salesOrderId} | Customer: {$customer['customer_name']} | Shortage: {$shortage} units";

                $stmt->execute([
                    $prNumber,
                    $salesOrderId,
                    $data['item_id'],
                    $shortage,
                    $data['created_by'],
                    $urgency,
                    $reason
                ]);

                $prGenerated = true;

                // Log PR creation
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (user_id, action_description, module, action_type)
                    VALUES (?, ?, 'purchase_requisitions', 'auto_generated')
                ");
                $stmt->execute([
                    $data['created_by'],
                    "Auto-generated PR {$prNumber} for SO #{$salesOrderId} | Item: {$stockCheck['item_name']} | Shortage: {$shortage} units"
                ]);
            }

            // Audit log for SO
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $data['created_by'],
                "Created Sales Order #$salesOrderId for {$customer['customer_name']} | {$stockCheck['item_name']} x {$data['quantity']} (VA01)"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'customer_id' => $customer['customer_id'],
                'customer_order_number' => $data['customer_order_number'] ?? null,
                'customer_name' => $customer['customer_name'],
                'item_name' => $stockCheck['item_name'],
                'quantity' => $data['quantity'],
                'unit' => $stockCheck['unit'],
                'total_amount' => $totalAmount,
                'installation_date' => $installationDate,
                'status' => 'pending',
                'stock_status' => [
                    'required' => $requiredQty,
                    'available' => $currentStock,
                    'shortage' => $shortage > 0 ? $shortage : 0,
                    'can_fulfill' => $canFulfill,
                    'needs_po' => !$canFulfill
                ],
                'pr_generated' => $prGenerated,
                'pr_number' => $prNumber,
                'message' => $canFulfill
                    ? "Sales Order #$salesOrderId created successfully (SAP: VA01). Stock available."
                    : "Sales Order #$salesOrderId created. Purchase Requisition {$prNumber} auto-generated for {$shortage} units shortage."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Generate unique PR number
     */
    private function generatePRNumber(): string
    {
        $stmt = $this->db->query("
            SELECT MAX(CAST(SUBSTRING(pr_number, 4) AS UNSIGNED)) as max_num 
            FROM purchase_requisitions
        ");
        $maxNum = $stmt->fetchColumn() ?: 0;
        return 'PR-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Process Invoice (SAP: VF01) - Stock OUT
     * ✅ FIXED: Direct SQL instead of stored procedure
     */
    public function processSimpleInvoice(int $salesOrderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT 
                    so.sales_order_id,
                    so.customer_name,
                    so.total_amount,
                    so.status,
                    soi.item_id,
                    soi.quantity,
                    soi.unit_price,
                    i.item_name,
                    i.unit,
                    i.quantity AS current_stock,
                    i.is_bom_item
                FROM sales_orders so
                JOIN sales_order_items soi ON so.sales_order_id = soi.sales_order_id
                JOIN items i ON soi.item_id = i.item_id
                WHERE so.sales_order_id = ?
                LIMIT 1
            ");

            $stmt->execute([$salesOrderId]);
            $order = $stmt->fetch();

            if (!$order) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => "Sales Order #$salesOrderId not found",
                    'error_code' => 'SO_NOT_FOUND'
                ];
            }

            if ($order['status'] === 'completed') {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => "Invoice already processed for SO #$salesOrderId",
                    'error_code' => 'ALREADY_PROCESSED'
                ];
            }

            if (!$order['is_bom_item'] && $order['current_stock'] < $order['quantity']) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => "Insufficient stock. Available: {$order['current_stock']}, Required: {$order['quantity']}",
                    'error_code' => 'INSUFFICIENT_STOCK'
                ];
            }

            if ($order['is_bom_item']) {
                $bomResult = $this->processBOMDeduction($order['item_id'], $order['quantity'], $userId, $salesOrderId);
                if (!$bomResult['success']) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    return $bomResult;
                }
            } else {
                // ✅ DIRECT SQL REPLACEMENT (Location #2)
                $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
                $stmt->execute([$order['item_id']]);
                $currentStock = (int)$stmt->fetchColumn();

                if ($currentStock < $order['quantity']) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock',
                        'error_code' => 'STOCK_CHECK_FAILED'
                    ];
                }

                // Update stock (OUT)
                $stmt = $this->db->prepare("UPDATE items SET quantity = quantity - ? WHERE item_id = ?");
                $stmt->execute([$order['quantity'], $order['item_id']]);

                // Log transaction
                $stmt = $this->db->prepare("
                    INSERT INTO transactions (item_id, user_id, transaction_type, quantity, reference_number, notes)
                    VALUES (?, ?, 'OUT', ?, ?, ?)
                ");
                $stmt->execute([
                    $order['item_id'],
                    $userId,
                    $order['quantity'],
                    "SO-$salesOrderId",
                    "Invoice processed for {$order['customer_name']} (VF01)"
                ]);

                // Get new stock level
                $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ?");
                $stmt->execute([$order['item_id']]);
                $newStockLevel = (int)$stmt->fetchColumn();

                // Update stock requirements
                $stmt = $this->db->prepare("
                    UPDATE stock_requirements 
                    SET available_quantity = ?,
                        updated_at = NOW()
                    WHERE item_id = ? 
                    AND sales_order_id != ?
                    AND status IN ('shortage', 'critical')
                ");
                $stmt->execute([$newStockLevel, $order['item_id'], $salesOrderId]);

                $affectedRequirements = $stmt->rowCount();
                error_log("✅ Updated $affectedRequirements stock requirements after invoice processing");
            }

            // Update sales order status
            $stmt = $this->db->prepare("
                UPDATE sales_orders 
                SET status = 'completed', completed_by = ?, completed_date = NOW()
                WHERE sales_order_id = ?
            ");

            if (!$stmt->execute([$userId, $salesOrderId])) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'Failed to update sales order status',
                    'error_code' => 'UPDATE_FAILED'
                ];
            }

            // Generate invoice
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($salesOrderId, 6, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("
                INSERT INTO invoices (invoice_number, sales_order_id, customer_name, total_amount, generated_by)
                VALUES (?, ?, ?, ?, ?)
            ");

            if (!$stmt->execute([
                $invoiceNumber,
                $salesOrderId,
                $order['customer_name'],
                $order['total_amount'],
                $userId
            ])) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'Failed to generate invoice',
                    'error_code' => 'INVOICE_GENERATION_FAILED'
                ];
            }

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type)
                VALUES (?, ?, 'sales_orders', 'invoice_processed')
            ");
            $stmt->execute([
                $userId,
                "Invoice $invoiceNumber processed: SO #$salesOrderId | {$order['item_name']} x {$order['quantity']} | Stock OUT (VF01)"
            ]);

            $this->db->commit();

            $this->updateStockRequirementStatus($salesOrderId, 'completed');

            $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ?");
            $stmt->execute([$order['item_id']]);
            $newStock = (int)$stmt->fetchColumn();

            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $order['customer_name'],
                'item_name' => $order['item_name'],
                'quantity' => $order['quantity'],
                'unit' => $order['unit'],
                'previous_stock' => (int)$order['current_stock'],
                'new_stock_level' => $newStock,
                'total_amount' => $order['total_amount'],
                'message' => "Invoice $invoiceNumber processed successfully. Material Document created (MB51)."
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            error_log("Invoice processing failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Invoice processing failed: ' . $e->getMessage(),
                'error_code' => 'EXCEPTION'
            ];
        }
    }

    /**
     * Update stock requirement when SO status changes
     */
    private function updateStockRequirementStatus(int $salesOrderId, string $newStatus): void
    {
        try {
            $statusMap = [
                'pending' => 'active',
                'completed' => 'fulfilled',
                'cancelled' => 'cancelled'
            ];

            $requirementStatus = $statusMap[$newStatus] ?? 'active';

            $stmt = $this->db->prepare("
                UPDATE stock_requirements 
                SET status = ?,
                    updated_at = NOW()
                WHERE sales_order_id = ?
            ");

            $stmt->execute([$requirementStatus, $salesOrderId]);

            error_log("✅ Updated stock requirement status for SO #{$salesOrderId} to: {$requirementStatus}");
        } catch (\PDOException $e) {
            error_log("⚠️ Failed to update stock requirement status: " . $e->getMessage());
        }
    }

    /**
     * Process BOM component deduction
     * ✅ FIXED: Direct SQL instead of stored procedure
     */
    private function processBOMDeduction(int $parentItemId, int $quantity, int $userId, int $salesOrderId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT component_item_id, quantity_required
                FROM bill_of_materials
                WHERE parent_item_id = ?
            ");
            $stmt->execute([$parentItemId]);
            $components = $stmt->fetchAll();

            if (empty($components)) {
                return [
                    'success' => false,
                    'message' => "No BOM components found for item #$parentItemId",
                    'error_code' => 'NO_BOM_COMPONENTS'
                ];
            }

            foreach ($components as $comp) {
                $compQty = $comp['quantity_required'] * $quantity;

                // ✅ DIRECT SQL REPLACEMENT (Location #3)
                $stmt = $this->db->prepare("SELECT quantity FROM items WHERE item_id = ? FOR UPDATE");
                $stmt->execute([$comp['component_item_id']]);
                $currentStock = (int)$stmt->fetchColumn();

                if ($currentStock < $compQty) {
                    return [
                        'success' => false,
                        'message' => "Insufficient component stock",
                        'error_code' => 'BOM_COMPONENT_FAILED',
                        'component_id' => $comp['component_item_id']
                    ];
                }

                // Update stock (OUT)
                $stmt = $this->db->prepare("UPDATE items SET quantity = quantity - ? WHERE item_id = ?");
                $stmt->execute([$compQty, $comp['component_item_id']]);

                // Log transaction
                $stmt = $this->db->prepare("
                    INSERT INTO transactions (item_id, user_id, transaction_type, quantity, reference_number, notes)
                    VALUES (?, ?, 'OUT', ?, ?, ?)
                ");
                $stmt->execute([
                    $comp['component_item_id'],
                    $userId,
                    $compQty,
                    "SO-$salesOrderId-BOM",
                    "BOM component for SO #$salesOrderId"
                ]);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            error_log("BOM deduction failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'BOM deduction failed: ' . $e->getMessage(),
                'error_code' => 'BOM_EXCEPTION'
            ];
        }
    }

    public function getAllSalesOrders(): array
    {
        $stmt = $this->db->query("
            SELECT 
                so.sales_order_id,
                so.customer_name,
                so.contact_number,
                so.delivery_address,
                so.order_date,
                so.installation_date,
                so.total_amount,
                so.status,
                so.notes,
                u.name AS created_by_name,
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

    /**
     * Get Material Documents (SAP: MB51)
     * ✅ FIXED: Direct SQL query instead of view
     */
    public function getMaterialDocuments(array $filters = []): array
    {
        $sql = "
            SELECT 
                t.transaction_id,
                t.item_id,
                i.item_name,
                i.sku,
                t.transaction_type,
                t.quantity,
                t.movement_date AS transaction_date,
                t.reference_number,
                t.notes,
                u.name AS user_name,
                u.username
            FROM transactions t
            LEFT JOIN items i ON t.item_id = i.item_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND t.item_id = ?";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND t.transaction_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(t.movement_date) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(t.movement_date) <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY t.movement_date DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
