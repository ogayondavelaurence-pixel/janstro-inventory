<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use PDOException;

/**
 * Complete Inventory Service - FULLY FIXED VERSION
 * Implements immutable transaction model aligned with all flowcharts
 * 
 * @version 3.0.0 - Complete Fix
 * @date 2025-11-18
 */
class CompleteInventoryService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ============================================
    // CUSTOMER MASTER DATA (NEW)
    // ============================================

    /**
     * Create or find customer master record
     */
    public function createOrFindCustomer(array $data): int
    {
        // Check if customer exists
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

        // Create new customer
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

        // Check BOM components if this is a BOM item
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

    /**
     * Check BOM component availability
     */
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
        // Total items
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE status = 'active'");
        $totalItems = (int)$stmt->fetch()['total'];

        // Low stock items
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM items WHERE quantity <= reorder_level");
        $lowStockItems = (int)$stmt->fetch()['total'];

        // Total inventory value
        $stmt = $this->db->query("SELECT SUM(quantity * unit_price) as total FROM items");
        $totalValue = (float)($stmt->fetch()['total'] ?? 0);

        // Items by category
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

            // Get item details
            $stmt = $this->db->prepare("SELECT item_name, unit_price, unit FROM items WHERE item_id = ?");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch();

            if (!$item) {
                throw new \Exception("Item not found");
            }

            $unitPrice = $data['unit_price'] ?? $item['unit_price'];
            $totalAmount = $unitPrice * $data['quantity'];

            // Create PO
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

            // Audit log
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
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * GOODS RECEIPT (SAP: MIGO) - Stock IN
     */
    public function receiveGoods(int $poId, array $data): array
    {
        try {
            $this->db->beginTransaction();

            // Get PO details
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

            // Call stored procedure for stock movement
            $stmt = $this->db->prepare("
                CALL sp_process_stock_movement(?, ?, 'IN', ?, 'Purchase Order', ?, ?, @success, @message, @new_stock)
            ");

            $stmt->execute([
                $po['item_id'],
                $receivedQty,
                $userId,
                "PO-$poId",
                $notes
            ]);

            // Get procedure output
            $result = $this->db->query("SELECT @success AS success, @message AS message, @new_stock AS new_stock")->fetch();

            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            // Update PO status
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'delivered', delivered_date = NOW()
                WHERE po_id = ?
            ");
            $stmt->execute([$poId]);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $userId,
                "Goods Receipt: PO #$poId | {$po['item_name']} x $receivedQty | Stock IN (MIGO)"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'po_id' => $poId,
                'item_name' => $po['item_name'],
                'received_quantity' => $receivedQty,
                'unit' => $po['unit'],
                'previous_stock' => (int)$po['current_stock'],
                'new_stock_level' => (int)$result['new_stock'],
                'message' => "Goods received successfully. Material Document created (MB51)."
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================
    // SALES ORDER FLOW (VA01 → VF01)
    // ============================================

    /**
     * Create Sales Order (SAP: VA01)
     */
    public function createSimpleSalesOrder(array $data): array
    {
        $required = ['customer_name', 'item_id', 'quantity', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            // Create or find customer
            $customerId = $this->createOrFindCustomer([
                'customer_name' => $data['customer_name'],
                'contact_number' => $data['contact_number'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['delivery_address'] ?? null
            ]);

            // Check stock availability
            $stockCheck = $this->checkStockAvailability($data['item_id']);

            if ($stockCheck['current_stock'] < $data['quantity']) {
                throw new \Exception(
                    "Insufficient stock. Available: {$stockCheck['current_stock']}, Requested: {$data['quantity']}"
                );
            }

            // If BOM item, check components
            if ($stockCheck['is_bom_item']) {
                $bomCheck = $this->checkBOMAvailability($data['item_id']);
                if (!$bomCheck['all_components_available']) {
                    $missing = implode(', ', array_column($bomCheck['missing_components'], 'component_name'));
                    throw new \Exception("Missing BOM components: $missing");
                }
            }

            // Calculate amounts
            $unitPrice = (float)$stockCheck['unit_price'];
            $totalAmount = $unitPrice * $data['quantity'];
            $installationDate = $data['installation_date'] ?? date('Y-m-d', strtotime('+7 days'));

            // Create sales order
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_id, customer_name, contact_number, delivery_address,
                    order_date, installation_date, total_amount, status, created_by, notes
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', ?, ?)
            ");

            $stmt->execute([
                $customerId,
                $data['customer_name'],
                $data['contact_number'] ?? null,
                $data['delivery_address'] ?? null,
                $installationDate,
                $totalAmount,
                $data['created_by'],
                $data['notes'] ?? null
            ]);

            $salesOrderId = (int)$this->db->lastInsertId();

            // Create order items
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

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $data['created_by'],
                "Created Sales Order #$salesOrderId for {$data['customer_name']} | {$stockCheck['item_name']} x {$data['quantity']} (VA01)"
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'customer_name' => $data['customer_name'],
                'item_name' => $stockCheck['item_name'],
                'quantity' => $data['quantity'],
                'unit' => $stockCheck['unit'],
                'total_amount' => $totalAmount,
                'installation_date' => $installationDate,
                'status' => 'pending',
                'message' => "Sales Order #$salesOrderId created successfully (SAP: VA01)"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process Invoice (SAP: VF01) - Stock OUT
     */
    public function processSimpleInvoice(int $salesOrderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Get sales order details
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
            if ($order['current_stock'] < $order['quantity']) {
                throw new \Exception("Insufficient stock to process this order");
            }

            // If BOM item, process component deduction
            if ($order['is_bom_item']) {
                $this->processBOMDeduction($order['item_id'], $order['quantity'], $userId, $salesOrderId);
            } else {
                // Regular item - process stock out
                $stmt = $this->db->prepare("
                    CALL sp_process_stock_movement(?, ?, 'OUT', ?, 'Sales Order', ?, ?, @success, @message, @new_stock)
                ");

                $stmt->execute([
                    $order['item_id'],
                    $order['quantity'],
                    $userId,
                    "SO-$salesOrderId",
                    "Invoice processed for {$order['customer_name']} (VF01)"
                ]);

                $result = $this->db->query("SELECT @success AS success, @message AS message, @new_stock AS new_stock")->fetch();

                if (!$result['success']) {
                    throw new \Exception($result['message']);
                }
            }

            // Update sales order status
            $stmt = $this->db->prepare("
                UPDATE sales_orders 
                SET status = 'completed', completed_by = ?, completed_date = NOW()
                WHERE sales_order_id = ?
            ");
            $stmt->execute([$userId, $salesOrderId]);

            // Generate invoice
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($salesOrderId, 6, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("
                INSERT INTO invoices (invoice_number, sales_order_id, customer_name, total_amount, generated_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceNumber,
                $salesOrderId,
                $order['customer_name'],
                $order['total_amount'],
                $userId
            ]);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description)
                VALUES (?, ?)
            ");
            $stmt->execute([
                $userId,
                "Invoice $invoiceNumber processed: SO #$salesOrderId | {$order['item_name']} x {$order['quantity']} | Stock OUT (VF01)"
            ]);

            $this->db->commit();

            // Get new stock
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
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process BOM component deduction
     */
    private function processBOMDeduction(int $parentItemId, int $quantity, int $userId, int $salesOrderId): void
    {
        // Get BOM components
        $stmt = $this->db->prepare("
            SELECT component_item_id, quantity_required
            FROM bill_of_materials
            WHERE parent_item_id = ?
        ");
        $stmt->execute([$parentItemId]);
        $components = $stmt->fetchAll();

        foreach ($components as $comp) {
            $compQty = $comp['quantity_required'] * $quantity;

            $stmt = $this->db->prepare("
                CALL sp_process_stock_movement(?, ?, 'OUT', ?, 'Sales Order BOM', ?, ?, @success, @message, @new_stock)
            ");

            $stmt->execute([
                $comp['component_item_id'],
                $compQty,
                $userId,
                "SO-$salesOrderId-BOM",
                "BOM component for SO #$salesOrderId"
            ]);

            $result = $this->db->query("SELECT @success AS success, @message AS message")->fetch();

            if (!$result['success']) {
                throw new \Exception("BOM component error: " . $result['message']);
            }
        }
    }

    /**
     * Get all sales orders
     */
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
     */
    public function getMaterialDocuments(array $filters = []): array
    {
        $sql = "
            SELECT * FROM v_stock_movements
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['item_id'])) {
            $sql .= " AND item_id = ?";
            $params[] = $filters['item_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND transaction_type = ?";
            $params[] = $filters['type'];
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(transaction_date) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(transaction_date) <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY transaction_date DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
