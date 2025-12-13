<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Repositories\InventoryRepository;

class InventoryService
{
    private InventoryRepository $inventoryRepo;

    public function __construct()
    {
        $this->inventoryRepo = new InventoryRepository();
    }

    /**
     * Get all inventory items
     * 
     * @return array Array of items
     */
    public function getAllItems(): array
    {
        $items = $this->inventoryRepo->getAll();
        return array_map(fn($item) => $item->toArray(), $items);
    }

    /**
     * Get single item by ID
     * 
     * @param int $itemId Item ID
     * @return array|null Item data or null
     */
    public function getItemById(int $itemId): ?array
    {
        $item = $this->inventoryRepo->findById($itemId);
        return $item ? $item->toArray() : null;
    }

    /**
     * Get low stock items
     * 
     * @return array Items below reorder level
     */
    public function getLowStockItems(): array
    {
        $items = $this->inventoryRepo->getLowStock();
        return array_map(fn($item) => $item->toArray(), $items);
    }

    /**
     * Get all categories - NEW METHOD
     * 
     * @return array Array of categories
     */
    public function getCategories(): array
    {
        return $this->inventoryRepo->getCategories();
    }

    /**
     * Get transaction history - NEW METHOD
     * 
     * @param int $limit Number of transactions to retrieve
     * @return array Transaction history
     */
    public function getTransactionHistory(int $limit = 50): array
    {
        return $this->inventoryRepo->getTransactions($limit);
    }

    /**
     * Get inventory summary - NEW METHOD
     * 
     * @return array Summary statistics
     */
    public function getInventorySummary(): array
    {
        $allItems = $this->inventoryRepo->getAll();

        $categoryStats = [];
        foreach ($allItems as $item) {
            $categoryName = $item->category_name ?: 'Uncategorized';

            if (!isset($categoryStats[$categoryName])) {
                $categoryStats[$categoryName] = [
                    'category' => $categoryName,
                    'total_items' => 0,
                    'total_quantity' => 0,
                    'total_value' => 0
                ];
            }

            $categoryStats[$categoryName]['total_items']++;
            $categoryStats[$categoryName]['total_quantity'] += $item->quantity;
            $categoryStats[$categoryName]['total_value'] += $item->getTotalValue();
        }

        return [
            'by_category' => array_values($categoryStats),
            'overall' => [
                'total_items' => count($allItems),
                'total_categories' => count($categoryStats),
                'grand_total_value' => array_sum(array_column($categoryStats, 'total_value'))
            ]
        ];
    }

    /**
     * Get dashboard statistics
     * 
     * @return array Dashboard stats
     */
    public function getDashboardStats(): array
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
            'total_inventory_value' => number_format($totalValue, 2),
            'categories_count' => count($this->inventoryRepo->getCategories())
        ];
    }

    /**
     * Create new inventory item
     * 
     * @param array $data Item data
     * @return array Created item info
     * @throws \Exception If validation fails
     */
    public function createItem(array $data): array
    {
        $this->validateItemData($data);

        $itemId = $this->inventoryRepo->create($data);

        if (!$itemId) {
            throw new \Exception('Failed to create item');
        }

        return [
            'item_id' => $itemId,
            'message' => 'Item created successfully'
        ];
    }

    /**
     * Update inventory item
     * 
     * @param int $itemId Item ID
     * @param array $data Updated data
     * @return bool Success status
     * @throws \Exception If item not found
     */
    public function updateItem(int $itemId, array $data): bool
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        // Remove item_id from update data if present
        unset($data['item_id']);

        return $this->inventoryRepo->update($itemId, $data);
    }

    /**
     * Delete inventory item
     * 
     * @param int $itemId Item ID
     * @return bool Success status
     * @throws \Exception If item not found
     */
    public function deleteItem(int $itemId): bool
    {
        $item = $this->inventoryRepo->findById($itemId);

        if (!$item) {
            throw new \Exception('Item not found');
        }

        return $this->inventoryRepo->delete($itemId);
    }

    /**
     * Add stock to inventory
     * 
     * @param int $itemId Item ID
     * @param int $quantity Quantity to add
     * @param int $userId User performing action
     * @param string|null $notes Optional notes
     * @return array Result with updated item
     * @throws \Exception If validation fails
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

        // Update stock
        if (!$this->inventoryRepo->updateStock($itemId, $quantity, 'IN')) {
            throw new \Exception('Failed to update stock');
        }

        // Log transaction
        $this->inventoryRepo->logTransaction($itemId, $userId, 'IN', $quantity, $notes);

        // Get updated item
        $updatedItem = $this->inventoryRepo->findById($itemId);

        return [
            'message' => 'Stock added successfully',
            'item' => $updatedItem->toArray()
        ];
    }

    /**
     * Remove stock from inventory
     * 
     * @param int $itemId Item ID
     * @param int $quantity Quantity to remove
     * @param int $userId User performing action
     * @param string|null $notes Optional notes
     * @return array Result with updated item
     * @throws \Exception If validation fails or insufficient stock
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

        // Update stock
        if (!$this->inventoryRepo->updateStock($itemId, $quantity, 'OUT')) {
            throw new \Exception('Failed to update stock');
        }

        // Log transaction
        $this->inventoryRepo->logTransaction($itemId, $userId, 'OUT', $quantity, $notes);

        // Get updated item
        $updatedItem = $this->inventoryRepo->findById($itemId);

        return [
            'message' => 'Stock removed successfully',
            'item' => $updatedItem->toArray()
        ];
    }

    /**
     * Validate item data
     * 
     * @param array $data Item data to validate
     * @throws \Exception If validation fails
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
    }
}
