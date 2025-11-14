<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;
use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Config\Database;

/**
 * Complete Inventory Service - FIXED v2.2
 * MATCHES NEW SCHEMA: customers table, order_id, multi-item support
 * 
 * @version 2.2.0
 * @date 2025-11-14
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

    // ============================================
    // SAP: MMBE - Check Stock Availability
    // ============================================
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

    // ============================================
    // Dashboard Overview
    // ============================================
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

    // ============================================
    // SAP: MD04 - Check Stock Requirements
    // ============================================
    public function checkStockRequirements(int $itemId): array
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception("Item not found: ID $itemId");
        }

        // Check pending purchase orders
        $stmt = $this->db->prepare("
            SELECT SUM(poi.quantity) AS pending_qty
            FROM purchase_orders po
            JOIN purchase_order_items poi ON po.po_id = poi.po_id
            WHERE poi.item_id = ? AND po.status IN ('pending','approved')
        ");
        $stmt->execute([$itemId]);
        $result = $stmt->fetch();
        $pendingQty = (float)($result['pending_qty'] ?? 0);

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

    // ============================================
    // SAP: ME21N - Create Purchase Order (Multi-Item)
    // ============================================
    public function createPurchaseOrder(array $data): array
    {
        // Validate
        if (empty($data['supplier_id']) || empty($data['items']) || empty($data['created_by'])) {
            throw new \Exception("Missing required fields: supplier_id, items, created_by");
        }

        try {
            $this->db->beginTransaction();

            // Create PO header
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (supplier_id, status, created_by, notes)
                VALUES (?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $data['supplier_id'],
                $data['created_by'],
                $data['notes'] ?? null
            ]);

            $poId = (int)$this->db->lastInsertId();

            // Insert PO items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                // Get item price
                $itemData = $this->inventoryRepo->findById($item['item_id']);
                if (!$itemData) {
                    throw new \Exception("Item not found: " . $item['item_id']);
                }

                $unitPrice = $item['unit_price'] ?? $itemData->unit_price;
                $lineTotal = $item['quantity'] * $unitPrice;
                $totalAmount += $lineTotal;

                $stmt = $this->db->prepare("
                    INSERT INTO purchase_order_items (po_id, item_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $poId,
                    $item['item_id'],
                    $item['quantity'],
                    $unitPrice,
                    $lineTotal
                ]);
            }

            // Log audit
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created Purchase Order #$poId with " . count($data['items']) . " items | Total: ₱" . number_format($totalAmount, 2)
            );

            $this->db->commit();

            return [
                'po_id' => $poId,
                'status' => 'pending',
                'total_items' => count($data['items']),
                'total_amount' => $totalAmount,
                'message' => "Purchase Order #$poId created successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================
    // SAP: MIGO - Receive Goods (Stock IN via Stored Procedure)
    // ============================================
    public function receiveGoods(int $poId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_receive_purchase_order(?, ?)");
            $stmt->execute([$poId, $userId]);

            // Get updated PO details
            $stmt = $this->db->prepare("
                SELECT po.po_id, s.supplier_name, COUNT(poi.po_item_id) AS item_count,
                       SUM(poi.line_total) AS total_amount
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.supplier_id
                JOIN purchase_order_items poi ON po.po_id = poi.po_id
                WHERE po.po_id = ?
                GROUP BY po.po_id
            ");
            $stmt->execute([$poId]);
            $poDetails = $stmt->fetch();

            // Audit log
            $this->userRepo->logAudit(
                $userId,
                "Received goods: PO #$poId | {$poDetails['item_count']} items | Stock IN completed"
            );

            $this->db->commit();

            return [
                'success' => true,
                'po_id' => $poId,
                'supplier' => $poDetails['supplier_name'],
                'items_received' => $poDetails['item_count'],
                'total_amount' => $poDetails['total_amount'],
                'message' => "Goods received successfully. Stock updated."
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ============================================
    // SAP: MB51 - Material Document List
    // ============================================
    public function getMaterialDocuments(array $filters = []): array
    {
        $sql = "
            SELECT t.transaction_id, t.transaction_type, t.quantity, t.notes, t.date_time,
                   i.item_name, i.unit, u.name AS user_name
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

        $sql .= " ORDER BY t.date_time DESC LIMIT 200";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ============================================
    // SALES ORDER METHODS - FIXED FOR NEW SCHEMA
    // ============================================

    /**
     * Create Multi-Item Sales Order - FIXED
     * Uses customers table + order_id column
     */
    public function createSalesOrder(array $data): array
    {
        // Validate
        $required = ['customer_id', 'items', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            // Calculate total
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $itemData = $this->inventoryRepo->findById($item['item_id']);
                if (!$itemData) {
                    throw new \Exception("Item not found: " . $item['item_id']);
                }
                $totalAmount += $item['quantity'] * $itemData->unit_price;
            }

            // Create sales order header
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_id, installation_address, installation_date,
                    total_amount, status, created_by, notes
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $data['customer_id'],
                $data['installation_address'] ?? null,
                $data['installation_date'] ?? date('Y-m-d', strtotime('+7 days')),
                $totalAmount,
                $data['created_by'],
                $data['notes'] ?? null
            ]);

            $orderId = (int)$this->db->lastInsertId();

            // Insert order items
            foreach ($data['items'] as $item) {
                $itemData = $this->inventoryRepo->findById($item['item_id']);
                $unitPrice = $itemData->unit_price;
                $lineTotal = $item['quantity'] * $unitPrice;

                $stmt = $this->db->prepare("
                    INSERT INTO sales_order_items (order_id, item_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['item_id'],
                    $item['quantity'],
                    $unitPrice,
                    $lineTotal
                ]);
            }

            // Audit log
            $this->userRepo->logAudit(
                $data['created_by'],
                "Created Sales Order #$orderId | " . count($data['items']) . " items | Total: ₱" . number_format($totalAmount, 2)
            );

            $this->db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'total_items' => count($data['items']),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'message' => "Sales Order #$orderId created successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get All Sales Orders
     */
    public function getAllSalesOrders(): array
    {
        $query = "
            SELECT so.order_id, c.customer_name, c.contact_no,
                   so.installation_address, so.installation_date,
                   so.total_amount, so.status, so.created_at,
                   u.name AS created_by_name,
                   COUNT(soi.order_item_id) AS item_count
            FROM sales_orders so
            JOIN customers c ON so.customer_id = c.customer_id
            LEFT JOIN users u ON so.created_by = u.user_id
            LEFT JOIN sales_order_items soi ON so.order_id = soi.order_id
            GROUP BY so.order_id
            ORDER BY so.created_at DESC
        ";

        $stmt = $this->db->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Complete Installation & Generate Invoice (SAP: VF01)
     */
    public function completeInstallation(int $orderId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Reserve stock (calls stored procedure)
            $stmt = $this->db->prepare("CALL sp_reserve_stock(?)");
            $stmt->execute([$orderId]);

            // Update order status
            $stmt = $this->db->prepare("
                UPDATE sales_orders
                SET status = 'completed', completed_by = ?, completed_date = CURRENT_TIMESTAMP
                WHERE order_id = ?
            ");
            $stmt->execute([$userId, $orderId]);

            // Generate invoice (stored procedure)
            $stmt = $this->db->prepare("CALL sp_generate_invoice(?, ?)");
            $stmt->execute([$orderId, $userId]);

            // Get invoice number
            $stmt = $this->db->prepare("
                SELECT invoice_number, total_amount
                FROM invoices
                WHERE order_id = ?
                ORDER BY invoice_id DESC LIMIT 1
            ");
            $stmt->execute([$orderId]);
            $invoice = $stmt->fetch();

            // Audit log
            $this->userRepo->logAudit(
                $userId,
                "Completed Sales Order #$orderId | Invoice: {$invoice['invoice_number']}"
            );

            $this->db->commit();

            return [
                'success' => true,
                'order_id' => $orderId,
                'invoice_number' => $invoice['invoice_number'],
                'total_amount' => $invoice['total_amount'],
                'message' => "Installation completed and invoice generated successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
