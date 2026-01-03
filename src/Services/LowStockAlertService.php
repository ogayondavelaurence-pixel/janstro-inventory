<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

/**
 * ============================================================================
 * LOW STOCK ALERT SERVICE - AUTO-GENERATES PRs v2.0
 * ============================================================================
 * FIXES:
 * ✅ Integrated with NotificationService
 * ✅ Removed redundant email methods
 * ✅ Proper notification workflow
 * ============================================================================
 */
class LowStockAlertService
{
    private PDO $db;
    private NotificationService $notificationService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->notificationService = new NotificationService();
    }

    /**
     * Check all items and create PRs for low stock
     */
    public function checkAndAlertLowStock(): array
    {
        try {
            $this->db->beginTransaction();

            // Find low stock items without pending PRs
            $stmt = $this->db->query("
                SELECT 
                    i.item_id,
                    i.item_name,
                    i.sku,
                    i.quantity as current_stock,
                    i.reorder_level,
                    i.unit,
                    i.unit_price,
                    (i.reorder_level - i.quantity) as shortage_qty
                FROM items i
                WHERE i.status = 'active'
                AND i.quantity <= i.reorder_level
                AND NOT EXISTS (
                    SELECT 1 FROM purchase_requisitions pr
                    WHERE pr.item_id = i.item_id
                    AND pr.status IN ('pending', 'approved')
                    AND pr.sales_order_id IS NULL
                )
                ORDER BY 
                    CASE 
                        WHEN i.quantity = 0 THEN 1
                        WHEN i.quantity < (i.reorder_level * 0.5) THEN 2
                        ELSE 3
                    END,
                    i.item_name
            ");

            $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $created = [];
            $systemUserId = 1; // System user

            foreach ($lowStockItems as $item) {
                $urgency = $this->calculateUrgency($item);
                $prNumber = 'PR-' . date('Y') . '-' . str_pad($this->getNextPRNumber(), 6, '0', STR_PAD_LEFT);

                // Create PR
                $stmt = $this->db->prepare("
                    INSERT INTO purchase_requisitions (
                        pr_number, 
                        sales_order_id,
                        item_id, 
                        required_quantity,
                        requested_by, 
                        status, 
                        urgency, 
                        reason,
                        created_at
                    ) VALUES (?, NULL, ?, ?, ?, 'pending', ?, ?, NOW())
                ");

                $stmt->execute([
                    $prNumber,
                    $item['item_id'],
                    $item['shortage_qty'],
                    $systemUserId,
                    $urgency,
                    "Auto-generated: Low stock alert for {$item['item_name']} (Current: {$item['current_stock']}, Reorder: {$item['reorder_level']})"
                ]);

                $prId = (int)$this->db->lastInsertId();

                $created[] = [
                    'pr_id' => $prId,
                    'pr_number' => $prNumber,
                    'item_name' => $item['item_name'],
                    'sku' => $item['sku'],
                    'current_stock' => $item['current_stock'],
                    'reorder_level' => $item['reorder_level'],
                    'shortage_qty' => $item['shortage_qty'],
                    'urgency' => $urgency,
                    'quantity' => $item['current_stock'], // For notification
                    'name' => $item['item_name'] // For notification
                ];

                // Audit log
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (
                        user_id, action_description, module, action_type, ip_address
                    ) VALUES (?, ?, 'system', 'auto_pr_created', 'system')
                ");
                $stmt->execute([
                    $systemUserId,
                    "Auto-created PR #{$prNumber} for low stock: {$item['item_name']}"
                ]);
            }

            $this->db->commit();

            // ✅ NEW: Send notifications via NotificationService
            if (!empty($created)) {
                $this->notificationService->notifyLowStock($created);
            }

            return [
                'success' => true,
                'checked_at' => date('Y-m-d H:i:s'),
                'low_stock_items' => count($lowStockItems),
                'prs_created' => count($created),
                'details' => $created
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Low stock alert error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check stock shortages from sales orders and create PRs
     */
    public function checkSalesOrderShortages(): array
    {
        try {
            $this->db->beginTransaction();

            // Find stock requirements with shortages (no PR yet)
            $stmt = $this->db->query("
                SELECT 
                    sr.requirement_id,
                    sr.sales_order_id,
                    sr.item_id,
                    sr.required_quantity,
                    sr.available_quantity,
                    sr.shortage_quantity,
                    sr.status,
                    i.item_name,
                    i.sku,
                    i.unit_price,
                    so.customer_name
                FROM stock_requirements sr
                JOIN items i ON sr.item_id = i.item_id
                JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                WHERE sr.status IN ('shortage', 'critical')
                AND so.status = 'pending'
                AND NOT EXISTS (
                    SELECT 1 FROM purchase_requisitions pr
                    WHERE pr.sales_order_id = sr.sales_order_id
                    AND pr.item_id = sr.item_id
                    AND pr.status IN ('pending', 'approved')
                )
                ORDER BY 
                    FIELD(sr.status, 'critical', 'shortage'),
                    sr.created_at
            ");

            $shortages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $created = [];
            $systemUserId = 1;

            foreach ($shortages as $shortage) {
                $urgency = ($shortage['status'] === 'critical') ? 'critical' : 'high';
                $prNumber = 'PR-' . date('Y') . '-' . str_pad($this->getNextPRNumber(), 6, '0', STR_PAD_LEFT);

                // Create PR
                $stmt = $this->db->prepare("
                    INSERT INTO purchase_requisitions (
                        pr_number, 
                        sales_order_id,
                        item_id, 
                        required_quantity,
                        requested_by, 
                        status, 
                        urgency, 
                        reason,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
                ");

                $stmt->execute([
                    $prNumber,
                    $shortage['sales_order_id'],
                    $shortage['item_id'],
                    $shortage['shortage_quantity'],
                    $systemUserId,
                    $urgency,
                    "Auto-generated: Stock shortage for SO #{$shortage['sales_order_id']} - {$shortage['customer_name']}"
                ]);

                $created[] = [
                    'pr_number' => $prNumber,
                    'sales_order_id' => $shortage['sales_order_id'],
                    'item_name' => $shortage['item_name'],
                    'shortage_qty' => $shortage['shortage_quantity']
                ];
            }

            $this->db->commit();

            // ✅ NEW: Send notifications for SO shortages
            if (!empty($created)) {
                // Format for notification (add required fields)
                $notificationItems = array_map(function ($item) {
                    return [
                        'name' => $item['item_name'],
                        'quantity' => 0, // Critical - out of stock
                        'reorder_level' => $item['shortage_qty']
                    ];
                }, $created);

                $this->notificationService->notifyLowStock($notificationItems);
            }

            return [
                'success' => true,
                'shortages_checked' => count($shortages),
                'prs_created' => count($created),
                'details' => $created
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SO shortage check error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function calculateUrgency(array $item): string
    {
        if ($item['current_stock'] === 0) return 'critical';

        $percentRemaining = ($item['current_stock'] / $item['reorder_level']) * 100;

        if ($percentRemaining < 25) return 'critical';
        if ($percentRemaining < 50) return 'high';
        if ($percentRemaining < 75) return 'medium';
        return 'low';
    }

    private function getNextPRNumber(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) as total 
            FROM purchase_requisitions 
            WHERE YEAR(created_at) = YEAR(NOW())
        ");
        return ((int)$stmt->fetchColumn()) + 1;
    }
}
