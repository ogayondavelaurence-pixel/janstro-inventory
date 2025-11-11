<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;
use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Config\Database;

/**
 * Complete Inventory Service - FIXED VERSION
 * IMMUTABLE TRANSACTION MODEL - No Direct Stock Editing
 * 
 * @version 2.1.0
 * @date 2025-11-11
 * 
 * FIXES:
 * - Updated sales order column names to match database schema
 * - Added order_date column
 * - Fixed sales_order_items foreign key reference
 * - Improved error handling and validation
 */
class CompleteInventoryService
{
    private InventoryRepository $inventoryRepo;
    private PurchaseOrderRepository $poRepo;
    private UserRepository $userRepo;
    private \PDO $db;

    public function __construct()
    {
        $this->inventoryRepo = new InventoryRepository();
        $this->poRepo = new PurchaseOrderRepository();
        $this->userRepo = new UserRepository();
        $this->db = Database::connect();
    }

    /**
     * SAP: MMBE - Check Stock Availability
     * READ-ONLY: View current stock levels
     */
    public function checkStockAvailability(int $itemId): array
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception("Item not found: ID $itemId");
        }

        return [
            'item_id' => $item->item_id,
            'item_name' => $item->item_name,
            'current_stock' => $item->quantity,
            'unit' => $item->unit,
            'reorder_level' => $item->reorder_level,
            'is_low_stock' => $item->isLowStock(),
            'available_for_order' => $item->quantity > 0,
            'unit_price' => $item->unit_price
        ];
    }

    /**
     * Get Full Inventory Status Report
     * Dashboard overview
     */
    public function getInventoryStatus(): array
    {
        $allItems = $this->inventoryRepo->getAll();
        $lowStockItems = $this->inventoryRepo->getLowStock();

        $totalValue = 0;
        foreach ($allItems as $item) {
            $totalValue += $item->getTotalValue();
        }

        return [
            'total_items' => count($allItems),
            'low_stock_items' => count($lowStockItems),
            'total_inventory_value' => round($totalValue, 2),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * SAP: MD04 - Check Stock Requirements
     * Determine if replenishment needed
     */
    public function checkStockRequirements(int $itemId): array
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception("Item not found: ID $itemId");
        }

        // Check pending purchase orders
        $stmt = $this->db->prepare("
            SELECT SUM(quantity) as pending_qty
            FROM purchase_orders
            WHERE item_id = ? AND status IN ('pending', 'approved')
        ");
        $stmt->execute([$itemId]);
        $result = $stmt->fetch();
        $pendingQty = (int)($result['pending_qty'] ?? 0);

        // Calculate projected stock
        $projectedStock = $item->quantity + $pendingQty;
        $needsReplenishment = $projectedStock <= $item->reorder_level;

        return [
            'item_id' => $item->item_id,
            'item_name' => $item->item_name,
            'current_stock' => $item->quantity,
            'pending_orders' => $pendingQty,
            'projected_stock' => $projectedStock,
            'reorder_level' => $item->reorder_level,
            'needs_replenishment' => $needsReplenishment,
            'suggested_order_quantity' => $needsReplenishment ?
                max($item->reorder_level * 2 - $projectedStock, 0) : 0
        ];
    }

    /**
     * SAP: ME21N - Create Purchase Order
     * STOCK IN STEP 1: Initiate procurement
     */
    public function createPurchaseOrder(array $data): array
    {
        // Validate required fields
        $required = ['supplier_id', 'item_id', 'quantity', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        // Validate item exists
        $item = $this->inventoryRepo->findById($data['item_id']);
        if (!$item) {
            throw new \Exception("Item not found: ID {$data['item_id']}");
        }

        // Calculate total amount
        $totalAmount = $item->unit_price * $data['quantity'];

        try {
            $this->db->beginTransaction();

            // Create PO
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders 
                (supplier_id, item_id, quantity, total_amount, status, created_by, po_date)
                VALUES (?, ?, ?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $data['supplier_id'],
                $data['item_id'],
                $data['quantity'],
                $totalAmount,
                $data['created_by']
            ]);

            $poId = (int)$this->db->lastInsertId();

            // Log audit
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created Purchase Order #$poId: {$item->item_name} x {$data['quantity']}"
            );

            $this->db->commit();

            return [
                'po_id' => $poId,
                'status' => 'pending',
                'message' => "Purchase Order #$poId created successfully",
                'item_name' => $item->item_name,
                'quantity' => $data['quantity'],
                'total_amount' => $totalAmount
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SAP: MIGO - Goods Receipt (Stock In)
     * STOCK IN STEP 2: Receive delivery and increase stock
     * THIS IS THE ONLY WAY TO INCREASE STOCK!
     */
    public function receiveGoods(int $poId, array $data): array
    {
        // Get PO details
        $po = $this->poRepo->findById($poId);
        if (!$po) {
            throw new \Exception("Purchase Order #$poId not found");
        }

        if ($po->status === 'delivered') {
            throw new \Exception("Purchase Order #$poId already received");
        }

        if ($po->status === 'cancelled') {
            throw new \Exception("Purchase Order #$poId is cancelled");
        }

        $receivedQty = $data['received_quantity'] ?? $po->quantity;
        $userId = $data['user_id'];
        $notes = $data['notes'] ?? "Goods received from supplier";

        try {
            $this->db->beginTransaction();

            // Update PO status
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'delivered', 
                    delivered_date = NOW()
                WHERE po_id = ?
            ");
            $stmt->execute([$poId]);

            // INCREASE STOCK - IMMUTABLE TRANSACTION
            $stmt = $this->db->prepare("
                UPDATE items 
                SET quantity = quantity + ? 
                WHERE item_id = ?
            ");
            $stmt->execute([$receivedQty, $po->item_id]);

            // Log transaction (STOCK IN)
            $stmt = $this->db->prepare("
                INSERT INTO transactions 
                (item_id, user_id, transaction_type, quantity, notes, date_time)
                VALUES (?, ?, 'IN', ?, ?, NOW())
            ");
            $stmt->execute([
                $po->item_id,
                $userId,
                $receivedQty,
                "$notes | PO #$poId"
            ]);

            // Audit log
            $this->userRepo->logAudit(
                $userId,
                "Received goods: PO #$poId | {$po->item_name} x $receivedQty | Stock IN"
            );

            $this->db->commit();

            // Get updated stock
            $item = $this->inventoryRepo->findById($po->item_id);

            return [
                'success' => true,
                'po_id' => $poId,
                'item_name' => $po->item_name,
                'received_quantity' => $receivedQty,
                'new_stock_level' => $item->quantity,
                'message' => "Goods received successfully. Stock updated."
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * SAP: MB51 - Material Document List
     * READ-ONLY: View all stock movements (audit trail)
     */
    public function getMaterialDocuments(array $filters = []): array
    {
        $sql = "
            SELECT 
                t.transaction_id,
                t.transaction_type,
                t.quantity,
                t.notes,
                t.date_time,
                i.item_name,
                i.unit,
                u.name as user_name
            FROM transactions t
            JOIN items i ON t.item_id = i.item_id
            JOIN users u ON t.user_id = u.user_id
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
            $sql .= " AND DATE(t.date_time) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(t.date_time) <= ?";
            $params[] = $filters['date_to'];
        }

        $sql .= " ORDER BY t.date_time DESC LIMIT 100";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ============================================
    // SALES ORDER METHODS - FIXED VERSION
    // ============================================

    /**
     * Get all sales orders
     * 
     * @return array List of all sales orders with details
     */
    public function getAllSalesOrders(): array
    {
        $query = "
            SELECT 
                so.sales_order_id,
                so.customer_name,
                so.contact_number,
                so.delivery_address,
                so.order_date,
                so.installation_date,
                so.total_amount,
                so.status,
                so.created_at,
                so.notes,
                u.name AS created_by_name,
                -- Get first item details for simple display
                (SELECT i.item_name 
                 FROM sales_order_items soi 
                 JOIN items i ON soi.item_id = i.item_id 
                 WHERE soi.sales_order_id = so.sales_order_id 
                 LIMIT 1) AS item_name,
                (SELECT soi.quantity 
                 FROM sales_order_items soi 
                 WHERE soi.sales_order_id = so.sales_order_id 
                 LIMIT 1) AS quantity,
                (SELECT i.unit 
                 FROM sales_order_items soi 
                 JOIN items i ON soi.item_id = i.item_id 
                 WHERE soi.sales_order_id = so.sales_order_id 
                 LIMIT 1) AS unit
            FROM sales_orders so
            LEFT JOIN users u ON so.created_by = u.user_id
            ORDER BY so.created_at DESC
        ";

        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create simple sales order (single item) - FIXED VERSION
     * 
     * @param array $data Order data with correct column names
     * @return array Created order details
     * @throws \Exception If validation fails or insufficient stock
     */
    public function createSimpleSalesOrder(array $data): array
    {
        // ============================================
        // STEP 1: VALIDATE REQUIRED FIELDS
        // ============================================
        $requiredFields = ['customer_name', 'item_id', 'quantity', 'created_by'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            // ============================================
            // STEP 2: GET ITEM DETAILS & VALIDATE STOCK
            // ============================================
            $stmt = $this->db->prepare("
                SELECT item_id, item_name, unit_price, unit, quantity AS current_stock
                FROM items 
                WHERE item_id = :item_id
            ");
            $stmt->execute(['item_id' => $data['item_id']]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                throw new \Exception('Item not found');
            }

            // Check stock availability
            $requestedQty = (int)$data['quantity'];
            if ($item['current_stock'] < $requestedQty) {
                throw new \Exception(
                    "Insufficient stock. Available: {$item['current_stock']}, Requested: $requestedQty"
                );
            }

            // ============================================
            // STEP 3: CALCULATE AMOUNTS
            // ============================================
            $unitPrice = (float)$item['unit_price'];
            $totalAmount = $unitPrice * $requestedQty;

            // Default installation date to 7 days from now
            $installationDate = !empty($data['installation_date'])
                ? $data['installation_date']
                : date('Y-m-d', strtotime('+7 days'));

            // ============================================
            // STEP 4: CREATE SALES ORDER - FIXED COLUMNS
            // ============================================
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_name,
                    contact_number,
                    delivery_address,
                    order_date,
                    installation_date,
                    total_amount,
                    status,
                    created_by,
                    notes
                ) VALUES (
                    :customer_name,
                    :contact_number,
                    :delivery_address,
                    :order_date,
                    :installation_date,
                    :total_amount,
                    'pending',
                    :created_by,
                    :notes
                )
            ");

            $stmt->execute([
                'customer_name' => $data['customer_name'],
                'contact_number' => $data['contact_number'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'order_date' => date('Y-m-d H:i:s'),
                'installation_date' => $installationDate,
                'total_amount' => $totalAmount,
                'created_by' => $data['created_by'],
                'notes' => $data['notes'] ?? null
            ]);

            $salesOrderId = (int)$this->db->lastInsertId();

            // ============================================
            // STEP 5: CREATE SALES ORDER ITEM - FIXED FK
            // ============================================
            $stmt = $this->db->prepare("
                INSERT INTO sales_order_items (
                    sales_order_id,
                    item_id,
                    quantity,
                    unit_price,
                    line_total
                ) VALUES (
                    :sales_order_id,
                    :item_id,
                    :quantity,
                    :unit_price,
                    :line_total
                )
            ");

            $stmt->execute([
                'sales_order_id' => $salesOrderId,
                'item_id' => $data['item_id'],
                'quantity' => $requestedQty,
                'unit_price' => $unitPrice,
                'line_total' => $totalAmount
            ]);

            // ============================================
            // STEP 6: LOG AUDIT TRAIL
            // ============================================
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created Sales Order #{$salesOrderId} for {$data['customer_name']} | {$item['item_name']} x {$requestedQty}"
            );

            $this->db->commit();

            // ============================================
            // STEP 7: RETURN SUCCESS RESPONSE
            // ============================================
            return [
                'success' => true,
                'sales_order_id' => $salesOrderId,
                'customer_name' => $data['customer_name'],
                'item_name' => $item['item_name'],
                'quantity' => $requestedQty,
                'unit' => $item['unit'],
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'message' => "Sales Order #{$salesOrderId} created successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Process simple invoice (Stock OUT)
     * This decreases stock and marks order as completed
     */
    public function processSimpleInvoice(int $salesOrderId, int $userId): array
    {
        $db = Database::connect();

        try {
            $db->beginTransaction();

            // Get sales order details
            $stmt = $db->prepare("
            SELECT 
                so.order_id AS sales_order_id,
                so.customer_name,
                so.total_amount,
                so.status,
                soi.item_id,
                soi.quantity,
                soi.unit_price,
                i.item_name,
                i.unit,
                i.quantity AS current_stock
            FROM sales_orders so
            JOIN sales_order_items soi ON so.order_id = soi.order_id
            JOIN items i ON soi.item_id = i.item_id
            WHERE so.order_id = :order_id
        ");
            $stmt->execute(['order_id' => $salesOrderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                throw new \Exception('Sales order not found');
            }

            if ($order['status'] === 'completed') {
                throw new \Exception('Invoice already processed');
            }

            // Check stock availability
            if ($order['current_stock'] < $order['quantity']) {
                throw new \Exception('Insufficient stock to process this order');
            }

            // Decrease stock (Stock OUT)
            $stmt = $db->prepare("
            UPDATE items 
            SET quantity = quantity - :quantity 
            WHERE item_id = :item_id
        ");
            $stmt->execute([
                'quantity' => $order['quantity'],
                'item_id' => $order['item_id']
            ]);

            // Create transaction record (Material Document - MB51)
            $stmt = $db->prepare("
            INSERT INTO transactions (
                item_id,
                user_id,
                transaction_type,
                quantity,
                notes
            ) VALUES (
                :item_id,
                :user_id,
                'OUT',
                :quantity,
                :notes
            )
        ");

            $stmt->execute([
                'item_id' => $order['item_id'],
                'user_id' => $userId,
                'quantity' => $order['quantity'],
                'notes' => "Sales Order #$salesOrderId - Invoice processed for {$order['customer_name']}"
            ]);

            // Update sales order status
            $stmt = $db->prepare("
            UPDATE sales_orders 
            SET status = 'completed',
                completed_by = :user_id,
                completed_date = NOW()
            WHERE order_id = :order_id
        ");
            $stmt->execute([
                'user_id' => $userId,
                'order_id' => $salesOrderId
            ]);

            $db->commit();

            // Get new stock level
            $newStock = $order['current_stock'] - $order['quantity'];

            return [
                'sales_order_id' => $salesOrderId,
                'customer_name' => $order['customer_name'],
                'item_name' => $order['item_name'],
                'quantity' => $order['quantity'],
                'unit' => $order['unit'],
                'previous_stock' => $order['current_stock'],
                'new_stock_level' => $newStock,
                'total_amount' => $order['total_amount'],
                'status' => 'completed',
                'material_document_created' => true
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
