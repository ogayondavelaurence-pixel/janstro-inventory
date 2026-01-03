<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

class InventoryController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /inventory - Get all items
     */
    public function getAll()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    item_id, item_name, sku, category_id, 
                    category_name, unit, reorder_level, 
                    unit_price, status, current_quantity,
                    created_at, updated_at
                FROM v_current_inventory
                ORDER BY item_name ASC
            ");

            $items = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $quantity = (int)$row['current_quantity'];
                $unitPrice = (float)$row['unit_price'];
                $reorderLevel = (int)$row['reorder_level'];

                $items[] = [
                    'item_id' => (int)$row['item_id'],
                    'item_name' => $row['item_name'],
                    'sku' => $row['sku'] ?? '',
                    'category_id' => (int)$row['category_id'],
                    'category_name' => $row['category_name'] ?? 'Uncategorized',
                    'quantity' => $quantity,
                    'unit' => $row['unit'],
                    'reorder_level' => $reorderLevel,
                    'unit_price' => $unitPrice,
                    'stock_value' => round($quantity * $unitPrice, 2),
                    'status' => $row['status'],
                    'stock_status' => $quantity === 0 ? 'out_of_stock' : ($quantity <= $reorderLevel ? 'low_stock' : 'in_stock'),
                    'is_low_stock' => $quantity <= $reorderLevel,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }

            Response::success($items, 'Items retrieved successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve items: ' . $e->getMessage());
        }
    }

    /**
     * GET /inventory/{id} - Get single item
     */
    public function getById($id)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM v_current_inventory WHERE item_id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                Response::notFound('Item not found');
                return;
            }

            $quantity = (int)$row['current_quantity'];
            $unitPrice = (float)$row['unit_price'];

            Response::success([
                'item_id' => (int)$row['item_id'],
                'item_name' => $row['item_name'],
                'sku' => $row['sku'],
                'category_id' => (int)$row['category_id'],
                'category_name' => $row['category_name'],
                'quantity' => $quantity,
                'unit' => $row['unit'],
                'reorder_level' => (int)$row['reorder_level'],
                'unit_price' => $unitPrice,
                'stock_value' => round($quantity * $unitPrice, 2),
                'status' => $row['status']
            ], 'Item retrieved successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve item');
        }
    }

    /**
     * POST /inventory - Create new item
     */
    public function create()
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['item_name', 'sku', 'reorder_level', 'category_id', 'unit_price'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            if (isset($data['quantity'])) {
                Response::badRequest('Cannot set quantity directly. Use initial_quantity for new items.');
                return;
            }

            $stmt = $this->db->prepare("SELECT item_id FROM items WHERE sku = ?");
            $stmt->execute([$data['sku']]);
            if ($stmt->fetch()) {
                Response::badRequest('SKU already exists');
                return;
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO items (
                    item_name, sku, category_id, 
                    quantity, unit, reorder_level, unit_price, status
                ) VALUES (?, ?, ?, 0, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $data['item_name'],
                $data['sku'],
                $data['category_id'],
                $data['unit'] ?? 'pcs',
                $data['reorder_level'],
                $data['unit_price']
            ]);

            $itemId = (int)$this->db->lastInsertId();

            if (isset($data['initial_quantity']) && $data['initial_quantity'] > 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO transactions (
                        item_id, user_id, transaction_type, quantity,
                        reference_type, reference_number, notes, movement_date
                    ) VALUES (?, ?, 'IN', ?, 'SYSTEM', 'INITIAL-STOCK', 'Initial stock', NOW())
                ");
                $stmt->execute([$itemId, $user->user_id, $data['initial_quantity']]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'items', 'create', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Created item: {$data['item_name']} (SKU: {$data['sku']})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success(['item_id' => $itemId], 'Item created successfully', 201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("❌ InventoryController::create - " . $e->getMessage());
            Response::serverError('Failed to create item: ' . $e->getMessage());
        }
    }

    /**
     * PUT /inventory/{id} - Update item
     */
    public function update($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (isset($data['quantity'])) {
                Response::badRequest('Cannot update quantity directly. Use Goods Receipt (MIGO).');
                return;
            }

            $stmt = $this->db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $existingItem = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existingItem) {
                Response::notFound('Item not found');
                return;
            }

            if (isset($data['sku'])) {
                $stmt = $this->db->prepare("SELECT item_id FROM items WHERE sku = ? AND item_id != ?");
                $stmt->execute([$data['sku'], $id]);
                if ($stmt->fetch()) {
                    Response::badRequest('SKU already exists');
                    return;
                }
            }

            $updates = [];
            $values = [];
            $allowedFields = ['item_name', 'sku', 'category_id', 'unit', 'reorder_level', 'unit_price', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                Response::badRequest('No fields to update');
                return;
            }

            $updates[] = "updated_at = NOW()";
            $values[] = $id;

            $sql = "UPDATE items SET " . implode(', ', $updates) . " WHERE item_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'items', 'update', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Updated item: {$existingItem['item_name']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            Response::success(null, 'Item updated successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::update - " . $e->getMessage());
            Response::serverError('Failed to update item');
        }
    }

    /**
     * DELETE /inventory/{id}
     */
    public function delete($id)
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Response::notFound('Item not found');
                return;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM transactions WHERE item_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                $stmt = $this->db->prepare("UPDATE items SET status = 'inactive' WHERE item_id = ?");
                $stmt->execute([$id]);
                $message = 'Item deactivated (has transaction history)';
            } else {
                $stmt = $this->db->prepare("DELETE FROM items WHERE item_id = ?");
                $stmt->execute([$id]);
                $message = 'Item deleted permanently';
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'items', 'delete', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Deleted item: {$item['item_name']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            Response::success(null, $message);
        } catch (\Exception $e) {
            error_log("❌ InventoryController::delete - " . $e->getMessage());
            Response::serverError('Failed to delete item');
        }
    }

    /**
     * GET /inventory/low-stock
     */
    public function getLowStock()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT * FROM v_current_inventory
                WHERE current_quantity <= reorder_level
                ORDER BY current_quantity ASC
            ");

            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            Response::success($items, 'Low stock items retrieved');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getLowStock - " . $e->getMessage());
            Response::serverError('Failed to retrieve low stock items');
        }
    }
}
