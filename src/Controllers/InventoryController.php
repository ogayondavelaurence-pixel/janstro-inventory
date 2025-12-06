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
     * Get all items (ALL ROLES)
     */
    public function getAll()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    item_id,
                    item_name,
                    sku,
                    category_id,
                    quantity,
                    unit,
                    reorder_level,
                    unit_price,
                    status,
                    created_at,
                    updated_at
                FROM items
                WHERE status = 'active'
                ORDER BY item_name ASC
            ");

            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($items, 'Items retrieved successfully');
        } catch (\Exception $e) {
            error_log("InventoryController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve items: ' . $e->getMessage());
        }
    }

    /**
     * Get item by ID (ALL ROLES)
     */
    public function getById($id)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    item_id,
                    item_name,
                    sku,
                    category_id,
                    quantity,
                    unit,
                    reorder_level,
                    unit_price,
                    status,
                    created_at,
                    updated_at
                FROM items
                WHERE item_id = ?
            ");

            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Response::notFound('Item not found');
                return;
            }

            Response::success($item, 'Item retrieved successfully');
        } catch (\Exception $e) {
            error_log("InventoryController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve item: ' . $e->getMessage());
        }
    }

    /**
     * Create new item (ADMIN ONLY)
     */
    public function create()
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['item_name', 'sku', 'quantity', 'reorder_level', 'category_id', 'unit_price'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Check if SKU already exists
            $stmt = $this->db->prepare("SELECT item_id FROM items WHERE sku = ?");
            $stmt->execute([$data['sku']]);
            if ($stmt->fetch()) {
                Response::badRequest('SKU already exists');
                return;
            }

            // Validate quantity and reorder_level
            if ($data['quantity'] < 0) {
                Response::badRequest('Quantity cannot be negative');
                return;
            }

            if ($data['reorder_level'] <= 0) {
                Response::badRequest('Reorder level must be greater than 0');
                return;
            }

            // Insert item
            $stmt = $this->db->prepare("
                INSERT INTO items (
                    item_name, 
                    sku, 
                    category_id, 
                    quantity, 
                    unit, 
                    reorder_level, 
                    unit_price,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $data['item_name'],
                $data['sku'],
                $data['category_id'],
                $data['quantity'],
                $data['unit'] ?? 'pcs',
                $data['reorder_level'],
                $data['unit_price']
            ]);

            $itemId = $this->db->lastInsertId();

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Created item: {$data['item_name']} (SKU: {$data['sku']})",
                'items',
                'create'
            );

            Response::success(
                ['item_id' => $itemId],
                'Item created successfully',
                201
            );
        } catch (\Exception $e) {
            error_log("InventoryController::create - " . $e->getMessage());
            Response::serverError('Failed to create item: ' . $e->getMessage());
        }
    }

    /**
     * Update item (ADMIN ONLY)
     */
    public function update($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if item exists
            $stmt = $this->db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $existingItem = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existingItem) {
                Response::notFound('Item not found');
                return;
            }

            // Check SKU uniqueness if being updated
            if (isset($data['sku'])) {
                $stmt = $this->db->prepare("SELECT item_id FROM items WHERE sku = ? AND item_id != ?");
                $stmt->execute([$data['sku'], $id]);
                if ($stmt->fetch()) {
                    Response::badRequest('SKU already exists');
                    return;
                }
            }

            // Validate quantity if provided
            if (isset($data['quantity']) && $data['quantity'] < 0) {
                Response::badRequest('Quantity cannot be negative');
                return;
            }

            // Validate reorder_level if provided
            if (isset($data['reorder_level']) && $data['reorder_level'] <= 0) {
                Response::badRequest('Reorder level must be greater than 0');
                return;
            }

            // Build update query dynamically
            $updates = [];
            $values = [];

            $allowedFields = ['item_name', 'sku', 'category_id', 'quantity', 'unit', 'reorder_level', 'unit_price', 'status'];

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

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Updated item: {$existingItem['item_name']}",
                'items',
                'update'
            );

            Response::success(null, 'Item updated successfully');
        } catch (\Exception $e) {
            error_log("InventoryController::update - " . $e->getMessage());
            Response::serverError('Failed to update item: ' . $e->getMessage());
        }
    }

    /**
     * Delete item (SUPERADMIN ONLY)
     */
    public function delete($id)
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            // Check if item exists
            $stmt = $this->db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Response::notFound('Item not found');
                return;
            }

            // Check if item has transactions
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM transactions WHERE item_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                // Soft delete if has transactions
                $stmt = $this->db->prepare("UPDATE items SET status = 'inactive', updated_at = NOW() WHERE item_id = ?");
                $stmt->execute([$id]);

                $message = 'Item deactivated (has transaction history)';
            } else {
                // Hard delete if no transactions
                $stmt = $this->db->prepare("DELETE FROM items WHERE item_id = ?");
                $stmt->execute([$id]);

                $message = 'Item deleted permanently';
            }

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Deleted item: {$item['item_name']}",
                'items',
                'delete'
            );

            Response::success(null, $message);
        } catch (\Exception $e) {
            error_log("InventoryController::delete - " . $e->getMessage());
            Response::serverError('Failed to delete item: ' . $e->getMessage());
        }
    }

    /**
     * Get low stock items (ALL ROLES)
     */
    public function getLowStock()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    item_id,
                    item_name,
                    sku,
                    category_id,
                    quantity,
                    reorder_level,
                    unit_price,
                    (reorder_level - quantity) as shortage
                FROM items
                WHERE quantity <= reorder_level
                AND status = 'active'
                ORDER BY shortage DESC
            ");

            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($items, 'Low stock items retrieved');
        } catch (\Exception $e) {
            error_log("InventoryController::getLowStock - " . $e->getMessage());
            Response::serverError('Failed to retrieve low stock items: ' . $e->getMessage());
        }
    }

    /**
     * Create audit log
     */
    private function createAuditLog($userId, $description, $module, $actionType)
    {
        try {
            if (!$userId) return;

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
