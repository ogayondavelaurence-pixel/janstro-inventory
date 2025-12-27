<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Repositories\PurchaseOrderRepository;
use Janstro\InventorySystem\Repositories\SupplierRepository;
use Janstro\InventorySystem\Repositories\UserRepository;
use Janstro\InventorySystem\Config\Database;

class ReportService
{
    private InventoryRepository $inventoryRepo;
    private PurchaseOrderRepository $orderRepo;
    private SupplierRepository $supplierRepo;
    private UserRepository $userRepo;
    private \PDO $db;

    public function __construct()
    {
        $this->inventoryRepo = new InventoryRepository();
        $this->orderRepo = new PurchaseOrderRepository();
        $this->supplierRepo = new SupplierRepository();
        $this->userRepo = new UserRepository();
        $this->db = Database::connect();
    }

    /*Get dashboard statistics (SAFE VERSION) */
    public function getDashboardStats(): array
    {
        try {
            // Total items with null safety
            $stmt = $this->db->query("
                SELECT COALESCE(COUNT(*), 0) as total 
                FROM items 
                WHERE status = 'active' OR status IS NULL
            ");
            $totalItems = (int)($stmt->fetch()['total'] ?? 0);

            // Low stock items
            $stmt = $this->db->query("
                SELECT COALESCE(COUNT(*), 0) as total 
                FROM items 
                WHERE quantity <= reorder_level AND quantity >= 0
            ");
            $lowStockItems = (int)($stmt->fetch()['total'] ?? 0);

            // Pending POs
            $pendingPOs = 0;
            try {
                $stmt = $this->db->query("
                    SELECT COALESCE(COUNT(*), 0) as total 
                    FROM purchase_orders 
                    WHERE status = 'pending'
                ");
                $pendingPOs = (int)($stmt->fetch()['total'] ?? 0);
            } catch (\PDOException $e) {
                error_log("purchase_orders table error: " . $e->getMessage());
            }

            // Total inventory value
            $stmt = $this->db->query("
                SELECT COALESCE(SUM(quantity * unit_price), 0) as total 
                FROM items
            ");
            $totalValue = (float)($stmt->fetch()['total'] ?? 0);

            return [
                'total_items' => $totalItems,
                'low_stock_items' => $lowStockItems,
                'pending_pos' => $pendingPOs,
                'total_inventory_value' => round($totalValue, 2),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (\PDOException $e) {
            error_log("Dashboard stats error: " . $e->getMessage());

            return [
                'total_items' => 0,
                'low_stock_items' => 0,
                'pending_pos' => 0,
                'total_inventory_value' => 0,
                'error' => 'Database query failed',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /* Get inventory summary */
    public function getInventorySummary(): array
    {
        $items = $this->inventoryRepo->getAll();

        $summary = [];
        foreach ($items as $item) {
            $summary[] = [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'category' => $item->category_name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_value' => $item->getTotalValue(),
                'reorder_level' => $item->reorder_level,
                'is_low_stock' => $item->isLowStock()
            ];
        }

        return $summary;
    }

    /* Get transaction history (FIXED - movement_date)*/
    public function getTransactionHistory(int $limit = 50, ?string $type = null): array
    {
        $sql = "
            SELECT t.*, i.item_name, i.unit, u.name as user_name
            FROM transactions t
            LEFT JOIN items i ON t.item_id = i.item_id
            LEFT JOIN users u ON t.user_id = u.user_id
        ";

        if ($type) {
            $sql .= " WHERE t.transaction_type = :type";
        }

        // ✅ FIXED: Changed from date_time to movement_date
        $sql .= " ORDER BY t.movement_date DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        if ($type) {
            $stmt->bindValue(':type', $type, \PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /* Get low stock items */
    public function getLowStockItems(): array
    {
        $items = $this->inventoryRepo->getLowStock();
        return array_map(fn($item) => $item->toArray(), $items);
    }

    /* Get purchase orders report */
    public function getPurchaseOrdersReport(?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "
            SELECT po.*, s.supplier_name, i.item_name, i.unit, u.name as created_by_name
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            LEFT JOIN items i ON po.item_id = i.item_id
            LEFT JOIN users u ON po.created_by = u.user_id
            WHERE 1=1
        ";

        $params = [];

        if ($status) {
            $sql .= " AND po.status = :status";
            $params[':status'] = $status;
        }

        if ($dateFrom) {
            $sql .= " AND po.po_date >= :date_from";
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND po.po_date <= :date_to";
            $params[':date_to'] = $dateTo;
        }

        $sql .= " ORDER BY po.po_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /* Get inventory valuation */
    public function getInventoryValuation(): array
    {
        $items = $this->inventoryRepo->getAll();

        $totalValue = 0;
        $categoryValues = [];

        foreach ($items as $item) {
            $itemValue = $item->getTotalValue();
            $totalValue += $itemValue;

            $category = $item->category_name ?: 'Uncategorized';

            if (!isset($categoryValues[$category])) {
                $categoryValues[$category] = 0;
            }

            $categoryValues[$category] += $itemValue;
        }

        return [
            'total_value' => round($totalValue, 2),
            'total_items' => count($items),
            'category_breakdown' => $categoryValues,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /* Get category breakdown */
    public function getCategoryBreakdown(): array
    {
        $stmt = $this->db->query("
            SELECT 
                c.name as category_name,
                COUNT(i.item_id) as item_count,
                SUM(i.quantity) as total_quantity,
                SUM(i.quantity * i.unit_price) as total_value
            FROM categories c
            LEFT JOIN items i ON c.category_id = i.category_id
            GROUP BY c.category_id, c.name
            ORDER BY total_value DESC
        ");

        return $stmt->fetchAll();
    }

    /* Get supplier performance */
    public function getSupplierPerformance(): array
    {
        $stmt = $this->db->query("
            SELECT 
                s.supplier_id,
                s.supplier_name,
                COUNT(po.po_id) as total_orders,
                SUM(CASE WHEN po.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN po.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(po.total_amount) as total_amount,
                AVG(po.total_amount) as average_order_value
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
            GROUP BY s.supplier_id, s.supplier_name
            ORDER BY total_orders DESC
        ");

        return $stmt->fetchAll();
    }

    /* Get audit log */
    public function getAuditLog(int $limit = 100, ?int $userId = null): array
    {
        $sql = "
            SELECT a.*, u.username, u.name as user_name
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.user_id
        ";

        if ($userId) {
            $sql .= " WHERE a.user_id = :user_id";
        }

        $sql .= " ORDER BY a.created_at DESC LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        if ($userId) {
            $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        }

        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /* Export report to CSV */
    public function exportReport(string $reportType, string $format = 'csv'): string
    {
        switch ($reportType) {
            case 'inventory':
                $data = $this->getInventorySummary();
                break;
            case 'transactions':
                $data = $this->getTransactionHistory(1000);
                break;
            case 'purchase_orders':
                $data = $this->getPurchaseOrdersReport();
                break;
            case 'low_stock':
                $data = $this->getLowStockItems();
                break;
            default:
                throw new \Exception('Invalid report type');
        }

        if ($format === 'csv') {
            return $this->arrayToCSV($data);
        }

        throw new \Exception('Unsupported export format');
    }

    /* Convert array to CSV string */
    private function arrayToCSV(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
