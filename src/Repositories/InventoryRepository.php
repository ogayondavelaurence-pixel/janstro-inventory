<?php

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Models\Item;
use PDO;
use PDOException;

class InventoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* Get all items */
    public function getAll(): array
    {
        try {
            // Line 24 - Add status filter
            $stmt = $this->db->query("
                SELECT i.*, c.name as category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.status = 'active'  -- ADD THIS
                ORDER BY i.item_name
            ");
            $items = [];
            while ($row = $stmt->fetch()) {
                $items[] = new Item($row);
            }

            return $items;
        } catch (PDOException $e) {
            error_log("InventoryRepository::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /* Find item by ID */
    public function findById(int $itemId): ?Item
    {
        try {
            $stmt = $this->db->prepare("
                SELECT i.*, c.name as category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.item_id = ?
            ");
            $stmt->execute([$itemId]);

            $result = $stmt->fetch();
            return $result ? new Item($result) : null;
        } catch (PDOException $e) {
            error_log("InventoryRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    /* Get low stock items */
    public function getLowStock(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT i.*, c.name as category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.quantity <= i.reorder_level
                ORDER BY i.quantity ASC
            ");

            $items = [];
            while ($row = $stmt->fetch()) {
                $items[] = new Item($row);
            }

            return $items;
        } catch (PDOException $e) {
            error_log("InventoryRepository::getLowStock Error: " . $e->getMessage());
            return [];
        }
    }

    /* Create new item */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO items (item_name, category_id, quantity, unit, reorder_level, unit_price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['item_name'],
                $data['category_id'],
                $data['quantity'] ?? 0,
                $data['unit'] ?? 'pcs',
                $data['reorder_level'] ?? 10,
                $data['unit_price']
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("InventoryRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /* Update item */
    public function update(int $itemId, array $data): bool
    {
        try {
            $fields = [];
            $values = [];

            $allowedFields = ['item_name', 'category_id', 'quantity', 'unit', 'reorder_level', 'unit_price'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $itemId;

            $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE item_id = ?";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("InventoryRepository::update Error: " . $e->getMessage());
            return false;
        }
    }

    /* Update stock quantity (with transaction) */
    public function updateStock(int $itemId, int $quantity, string $operation): bool
    {
        try {
            $this->db->beginTransaction();

            $operator = ($operation === 'IN') ? '+' : '-';

            // Update stock
            $stmt = $this->db->prepare("
                UPDATE items 
                SET quantity = quantity $operator ?
                WHERE item_id = ?
            ");

            if (!$stmt->execute([$quantity, $itemId])) {
                $this->db->rollBack();
                return false;
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryRepository::updateStock Error: " . $e->getMessage());
            return false;
        }
    }

    /* Delete item */
    public function delete(int $itemId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM items WHERE item_id = ?");
            return $stmt->execute([$itemId]);
        } catch (PDOException $e) {
            error_log("InventoryRepository::delete Error: " . $e->getMessage());
            return false;
        }
    }

    /* Log transaction */
    public function logTransaction(int $itemId, int $userId, string $type, int $quantity, ?string $notes = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions (item_id, user_id, transaction_type, quantity, notes)
                VALUES (?, ?, ?, ?, ?)
            ");

            return $stmt->execute([$itemId, $userId, $type, $quantity, $notes]);
        } catch (PDOException $e) {
            error_log("InventoryRepository::logTransaction Error: " . $e->getMessage());
            return false;
        }
    }

    /* Get transaction history */
    public function getTransactions(int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, i.item_name, u.name as user_name
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.item_id
                LEFT JOIN users u ON t.user_id = u.user_id
                ORDER BY t.date_time DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("InventoryRepository::getTransactions Error: " . $e->getMessage());
            return [];
        }
    }

    /* Get categories */
    public function getCategories(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM categories ORDER BY name");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("InventoryRepository::getCategories Error: " . $e->getMessage());
            return [];
        }
    }
}
