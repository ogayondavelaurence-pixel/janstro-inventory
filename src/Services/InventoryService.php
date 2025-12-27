<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\InventoryRepository;
use Janstro\InventorySystem\Config\Database;

/**
 * ============================================================================
 * INVENTORY SERVICE - OPTIMIZED v2.0
 * ============================================================================
 * ENHANCEMENTS:
 * ✅ Added SKU validation
 * ✅ Added bulk operations support
 * ✅ Enhanced error handling with specific messages
 * ✅ Added stock alert threshold checks
 * ✅ Optimized category statistics
 * ============================================================================
 */
class InventoryService
{
    private InventoryRepository $inventoryRepo;
    private \PDO $db;

    public function __construct()
    {
        $this->inventoryRepo = new InventoryRepository();
        $this->db = Database::connect();
    }

    /**
     * Get all inventory items with enhanced data
     */
    public function getAllItems(): array
    {
        $items = $this->inventoryRepo->getAll();
        return array_map(function ($item) {
            $data = $item->toArray();
            // ✅ ENHANCEMENT: Add stock status indicators
            $data['stock_status'] = $this->getStockStatus($item->quantity, $item->reorder_level);
            $data['days_until_stockout'] = $this->estimateDaysUntilStockout($item->item_id);
            return $data;
        }, $items);
    }

    /**
     * Get single item by ID with additional analytics
     */
    public function getItemById(int $itemId): ?array
    {
        $item = $this->inventoryRepo->findById($itemId);
        if (!$item) {
            return null;
        }

        $data = $item->toArray();

        // ✅ ENHANCEMENT: Add usage statistics
        $data['movement_history'] = $this->getRecentMovements($itemId, 10);
        $data['average_monthly_usage'] = $this->calculateAverageUsage($itemId);

        return $data;
    }

    /**
     * Get low stock items with urgency levels
     */
    public function getLowStockItems(): array
    {
        $items = $this->inventoryRepo->getLowStock();
        return array_map(function ($item) {
            $data = $item->toArray();
            // ✅ ENHANCEMENT: Add urgency classification
            $shortage = $item->reorder_level - $item->quantity;
            $data['urgency'] = $this->getUrgencyLevel($shortage, $item->reorder_level);
            return $data;
        }, $items);
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        return $this->inventoryRepo->getCategories();
    }

    /**
     * Get transaction history with filters
     */
    public function getTransactionHistory(int $limit = 50, ?array $filters = null): array
    {
        // ✅ ENHANCEMENT: Support for date range filtering
        if ($filters) {
            return $this->getFilteredTransactions($filters, $limit);
        }
        return $this->inventoryRepo->getTransactions($limit);
    }

    /**
     * Get inventory summary with enhanced statistics
     */
    public function getInventorySummary(): array
    {
        $allItems = $this->inventoryRepo->getAll();

        $categoryStats = [];
        $totalValue = 0;
        $lowStockCount = 0;

        foreach ($allItems as $item) {
            $categoryName = $item->category_name ?: 'Uncategorized';

            if (!isset($categoryStats[$categoryName])) {
                $categoryStats[$categoryName] = [
                    'category' => $categoryName,
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'total_value' => 0,
                    'low_stock_items' => 0 // ✅ NEW
                ];
            }

            $categoryStats[$categoryName]['total_items']++;
            $categoryStats[$categoryName]['total_quantity'] += $item->quantity;

            $itemValue = $item->getTotalValue();
            $categoryStats[$categoryName]['total_value'] += $itemValue;
            $totalValue += $itemValue;

            if ($item->isLowStock()) {
                $categoryStats[$categoryName]['low_stock_items']++;
                $lowStockCount++;
            }
        }

        return [
            'by_category' => array_values($categoryStats),
            'overall' => [
                'total_items' => count($allItems),
                'total_categories' => count($categoryStats),
                'grand_total_value' => round($totalValue, 2),
                'low_stock_count' => $lowStockCount, // ✅ NEW
                'average_item_value' => count($allItems) > 0 ? round($totalValue / count($allItems), 2) : 0 // ✅ NEW
            ]
        ];
    }

    /**
     * Get dashboard statistics with trends
     */
    public function getDashboardStats(): array
    {
        $allItems = $this->inventoryRepo->getAll();
        $lowStockItems = $this->inventoryRepo->getLowStock();

        $totalValue = 0;
        $criticalItems = 0; // ✅ NEW: Items at zero stock

        foreach ($allItems as $item) {
            $totalValue += $item->getTotalValue();
            if ($item->quantity === 0) {
                $criticalItems++;
            }
        }

        return [
            'total_items' => count($allItems),
            'low_stock_items' => count($lowStockItems),
            'critical_items' => $criticalItems, // ✅ NEW
            'total_inventory_value' => number_format($totalValue, 2),
            'categories_count' => count($this->inventoryRepo->getCategories()),
            'stock_turnover_rate' => $this->calculateStockTurnoverRate() // ✅ NEW
        ];
    }

    /**
     * Create new inventory item with validation
     */
    public function createItem(array $data): array
    {
        $this->validateItemData($data);

        // ✅ ENHANCEMENT: Check SKU uniqueness
        if (!empty($data['sku'])) {
            if ($this->skuExists($data['sku'])) {
                throw new \Exception('SKU already exists: ' . $data['sku']);
            }
        }

        $itemId = $this->inventoryRepo->create($data);

        if (!$itemId) {
            throw new \Exception('Failed to create item');
        }

        // ✅ ENHANCEMENT: Log initial stock if quantity > 0
        if (isset($data['quantity']) && $data['quantity'] > 0) {
            $this->inventoryRepo->logTransaction(
                $itemId,
                $data['created_by'] ?? 1,
                'IN',
                $data['quantity'],
                'Initial stock'
            );
        }

        return [
            'item_id' => $itemId,
            'message' => 'Item created successfully'
        ];
    }

    /**
     * Update inventory item
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // ✅ ENHANCEMENT: Check SKU uniqueness on update
        if (isset($data['sku']) && $data['sku'] !== $item->sku) {
            if ($this->skuExists($data['sku'])) {
                throw new \Exception('SKU already exists: ' . $data['sku']);
            }
        }

        // Remove item_id from update data if present
        unset($data['item_id']);

        return $this->inventoryRepo->update($itemId, $data);
    }

    /**
     * Delete inventory item
     */
    public function deleteItem(int $itemId): bool
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // ✅ ENHANCEMENT: Check if item has transactions
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM transactions WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            // Soft delete - mark as inactive instead
            return $this->inventoryRepo->update($itemId, ['status' => 'inactive']);
        }

        return $this->inventoryRepo->delete($itemId);
    }

    /**
     * Add stock to inventory with validation
     */
    public function stockIn(int $itemId, int $quantity, int $userId, ?string $notes = null): array
    {
        if ($quantity <= 0) {
            throw new \Exception('Quantity must be greater than 0');
        }

        $item = $this->inventoryRepo->findById($itemId);
        if (!$item) {
            throw new \Exception('Item not found');
        }

        // ✅ ENHANCEMENT: Use database transaction
        $this->db->beginTransaction();

        try {
            // Update stock
            if (!$this->inventoryRepo->updateStock($itemId, $quantity, 'IN')) {
                throw new \Exception('Failed to update stock');
            }

            // Log transaction
            $this->inventoryRepo->logTransaction($itemId, $userId, 'IN', $quantity, $notes);

            $this->db->commit();

            // Get updated item
            $updatedItem = $this->inventoryRepo->findById($itemId);

            return [
                'message' => 'Stock added successfully',
                'item' => $updatedItem->toArray()
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Remove stock from inventory with validation
     */
    public function stockOut(int $itemId, int $quantity, int $userId, ?string $notes = null): array
    {
        if ($quantity <= 0) {
            throw new \Exception('Quantity must be greater than 0');
        }

        $item = $this->inventoryRepo->findById($itemId);
        if (!$item) {
            throw new \Exception('Item not found');
        }

        if ($item->quantity < $quantity) {
            throw new \Exception('Insufficient stock. Available: ' . $item->quantity);
        }

        // ✅ ENHANCEMENT: Use database transaction
        $this->db->beginTransaction();

        try {
            // Update stock
            if (!$this->inventoryRepo->updateStock($itemId, $quantity, 'OUT')) {
                throw new \Exception('Failed to update stock');
            }

            // Log transaction
            $this->inventoryRepo->logTransaction($itemId, $userId, 'OUT', $quantity, $notes);

            $this->db->commit();

            // Get updated item
            $updatedItem = $this->inventoryRepo->findById($itemId);

            return [
                'message' => 'Stock removed successfully',
                'item' => $updatedItem->toArray(),
                'warning' => $updatedItem->isLowStock() ? 'Item is now below reorder level' : null // ✅ NEW
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * ✅ NEW: Bulk stock update
     */
    public function bulkStockUpdate(array $updates, int $userId): array
    {
        $this->db->beginTransaction();

        $results = [
            'success' => [],
            'failed' => []
        ];

        try {
            foreach ($updates as $update) {
                try {
                    if ($update['type'] === 'IN') {
                        $this->stockIn(
                            $update['item_id'],
                            $update['quantity'],
                            $userId,
                            $update['notes'] ?? 'Bulk update'
                        );
                    } else {
                        $this->stockOut(
                            $update['item_id'],
                            $update['quantity'],
                            $userId,
                            $update['notes'] ?? 'Bulk update'
                        );
                    }
                    $results['success'][] = $update['item_id'];
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'item_id' => $update['item_id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $results;
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Validate item data
     */
    private function validateItemData(array $data): void
    {
        if (empty($data['item_name'])) {
            throw new \Exception('Item name is required');
        }

        if (empty($data['category_id'])) {
            throw new \Exception('Category is required');
        }

        if (!isset($data['unit_price']) || $data['unit_price'] < 0) {
            throw new \Exception('Valid unit price is required');
        }

        // Validate string lengths
        if (strlen($data['item_name']) > 255) {
            throw new \Exception('Item name too long (max 255 characters)');
        }

        if (isset($data['unit']) && strlen($data['unit']) > 20) {
            throw new \Exception('Unit too long (max 20 characters)');
        }

        // ✅ NEW: Validate SKU format if provided
        if (isset($data['sku']) && !empty($data['sku'])) {
            if (!preg_match('/^[A-Z0-9\-]+$/', $data['sku'])) {
                throw new \Exception('SKU must contain only uppercase letters, numbers, and hyphens');
            }
        }
    }

    /**
     * Check if SKU exists
     */
    private function skuExists(string $sku): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM items WHERE sku = ?");
        $stmt->execute([$sku]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get stock status
     */
    private function getStockStatus(int $quantity, int $reorderLevel): string
    {
        if ($quantity === 0) {
            return 'out_of_stock';
        } elseif ($quantity <= $reorderLevel) {
            return 'low_stock';
        } elseif ($quantity <= $reorderLevel * 1.5) {
            return 'normal';
        } else {
            return 'healthy';
        }
    }

    /**
     * Estimate days until stockout
     */
    private function estimateDaysUntilStockout(int $itemId): ?int
    {
        try {
            $avgUsage = $this->calculateAverageUsage($itemId);
            $item = $this->inventoryRepo->findById($itemId);

            if ($avgUsage > 0 && $item) {
                return (int)ceil($item->quantity / $avgUsage);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
        return null;
    }

    /**
     * Calculate average monthly usage
     */
    private function calculateAverageUsage(int $itemId): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT AVG(quantity) as avg_usage
                FROM transactions
                WHERE item_id = ? 
                AND transaction_type = 'OUT'
                AND movement_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            ");
            $stmt->execute([$itemId]);
            $result = $stmt->fetch();
            return (float)($result['avg_usage'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent movements
     */
    private function getRecentMovements(int $itemId, int $limit): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT transaction_type, quantity, movement_date, notes
                FROM transactions
                WHERE item_id = ?
                ORDER BY movement_date DESC
                LIMIT ?
            ");
            $stmt->execute([$itemId, $limit]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get urgency level
     */
    private function getUrgencyLevel(int $shortage, int $reorderLevel): string
    {
        $percentShort = ($shortage / $reorderLevel) * 100;

        if ($percentShort >= 75) {
            return 'critical';
        } elseif ($percentShort >= 50) {
            return 'high';
        } elseif ($percentShort >= 25) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate stock turnover rate
     */
    private function calculateStockTurnoverRate(): float
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    SUM(quantity * unit_price) as total_value,
                    (SELECT SUM(quantity * unit_price) 
                     FROM transactions t2 
                     JOIN items i2 ON t2.item_id = i2.item_id
                     WHERE t2.transaction_type = 'OUT' 
                     AND t2.movement_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
                    ) as annual_sales
                FROM items
            ");
            $result = $stmt->fetch();

            $totalValue = (float)($result['total_value'] ?? 0);
            $annualSales = (float)($result['annual_sales'] ?? 0);

            if ($totalValue > 0) {
                return round($annualSales / $totalValue, 2);
            }
        } catch (\Exception $e) {
            // Silently fail
        }
        return 0;
    }

    /**
     * Get filtered transactions
     */
    private function getFilteredTransactions(array $filters, int $limit): array
    {
        $sql = "SELECT * FROM transactions WHERE 1=1";
        $params = [];

        if (isset($filters['date_from'])) {
            $sql .= " AND movement_date >= ?";
            $params[] = $filters['date_from'];
        }

        if (isset($filters['date_to'])) {
            $sql .= " AND movement_date <= ?";
            $params[] = $filters['date_to'];
        }

        if (isset($filters['type'])) {
            $sql .= " AND transaction_type = ?";
            $params[] = $filters['type'];
        }

        $sql .= " ORDER BY movement_date DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
