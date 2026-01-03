<?php

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Models\Item;
use PDO;

/**
 * ============================================================================
 * INVENTORY REPOSITORY - ERP COMPLIANT v3.0
 * ============================================================================
 * FIXES:
 * ✅ Uses v_current_inventory view (calculated stock)
 * ✅ Correct column: quantity (not current_stock)
 * ✅ Read-only stock queries (transactions manage stock)
 * ============================================================================
 */
class InventoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all items from calculated view
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT 
                item_id, item_name, sku, category_id, category_name,
                unit, reorder_level, unit_price, item_status as status,
                current_quantity as quantity,
                created_at, updated_at
            FROM v_current_inventory
            ORDER BY item_name ASC
        ");

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrateItem($row);
        }

        return $items;
    }

    /**
     * Find item by ID
     */
    public function findById(int $itemId): ?Item
    {
        $stmt = $this->db->prepare("
            SELECT 
                item_id, item_name, sku, category_id, category_name,
                unit, reorder_level, unit_price, item_status as status,
                current_quantity as quantity,
                created_at, updated_at
            FROM v_current_inventory
            WHERE item_id = ?
        ");

        $stmt->execute([$itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateItem($row) : null;
    }

    /**
     * Get low stock items
     */
    public function getLowStock(): array
    {
        $stmt = $this->db->query("
            SELECT 
                item_id, item_name, sku, category_id, category_name,
                unit, reorder_level, unit_price, item_status as status,
                current_quantity as quantity,
                created_at, updated_at
            FROM v_current_inventory
            WHERE current_quantity <= reorder_level
            ORDER BY current_quantity ASC
        ");

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $this->hydrateItem($row);
        }

        return $items;
    }

    /**
     * Create new item (quantity starts at 0)
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO items (
                item_name, sku, category_id, 
                quantity, unit, reorder_level, unit_price, status
            ) VALUES (?, ?, ?, 0, ?, ?, ?, 'active')
        ");

        $stmt->execute([
            $data['item_name'],
            $data['sku'] ?? null,
            $data['category_id'],
            $data['unit'] ?? 'pcs',
            $data['reorder_level'] ?? 10,
            $data['unit_price']
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update item (excludes quantity)
     */
    public function update(int $itemId, array $data): bool
    {
        unset($data['quantity']); // Never update quantity directly

        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['item_name', 'sku', 'category_id', 'unit', 'reorder_level', 'unit_price', 'status'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $itemId;
        $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE item_id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete item
     */
    public function delete(int $itemId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM items WHERE item_id = ?");
        return $stmt->execute([$itemId]);
    }

    /**
     * DEPRECATED: Stock updates via transactions only
     */
    public function updateStock(int $itemId, int $quantity, string $type): bool
    {
        trigger_error('Direct stock updates deprecated. Use transactions.', E_USER_DEPRECATED);
        return false;
    }

    /**
     * Log transaction (stock changes)
     */
    public function logTransaction(
        int $itemId,
        int $userId,
        string $type,
        int $quantity,
        ?string $notes = null,
        ?string $reference = null
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (
                item_id, user_id, transaction_type, quantity,
                reference_number, notes, movement_date
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $itemId,
            $userId,
            $type,
            $quantity,
            $reference,
            $notes
        ]);
    }

    /**
     * Get categories
     */
    public function getCategories(): array
    {
        $stmt = $this->db->query("
            SELECT category_id, name, description, created_at
            FROM categories
            ORDER BY name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transactions
     */
    public function getTransactions(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM v_stock_movements
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hydrate Item model from array
     */
    private function hydrateItem(array $data): Item
    {
        $item = new Item();
        $item->item_id = (int)$data['item_id'];
        $item->item_name = $data['item_name'];
        $item->sku = $data['sku'] ?? '';
        $item->category_id = (int)$data['category_id'];
        $item->category_name = $data['category_name'] ?? 'Uncategorized';
        $item->quantity = (int)$data['quantity'];
        $item->unit = $data['unit'];
        $item->reorder_level = (int)$data['reorder_level'];
        $item->unit_price = (float)$data['unit_price'];
        $item->status = $data['status'] ?? 'active';
        $item->created_at = $data['created_at'] ?? null;
        $item->updated_at = $data['updated_at'] ?? null;

        return $item;
    }
}
