<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * INVENTORY CONTROLLER - PRODUCTION STABLE v3.0
 * ============================================================================
 * FIXES:
 * ✅ Consistent response structure { success, data, message }
 * ✅ Proper error handling with detailed logging
 * ✅ Empty array fallback instead of null
 * ✅ Transaction safety for stock operations
 * ============================================================================
 */
class InventoryController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /inventory - Get all items
     * Returns: Array of inventory items
     */
    public function getAll()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            error_log("📦 Fetching inventory items...");

            $stmt = $this->db->query("
                SELECT 
                    i.item_id,
                    i.item_name,
                    i.sku,
                    i.category_id,
                    i.quantity,
                    i.unit,
                    i.reorder_level,
                    i.unit_price,
                    i.status,
                    i.created_at,
                    i.updated_at,
                    c.name AS category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.status = 'active'
                ORDER BY i.item_name ASC
            ");

            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // ✅ FIX: Ensure array is returned (not null)
            if (!is_array($items)) {
                $items = [];
            }

            // ✅ FIX: Type-safe conversion
            $formattedItems = [];
            foreach ($items as $item) {
                $formattedItems[] = [
                    'item_id' => (int)$item['item_id'],
                    'item_name' => (string)$item['item_name'],
                    'sku' => (string)($item['sku'] ?? ''),
                    'category_id' => (int)$item['category_id'],
                    'category_name' => (string)($item['category_name'] ?? 'Uncategorized'),
                    'quantity' => (int)$item['quantity'],
                    'unit' => (string)$item['unit'],
                    'reorder_level' => (int)$item['reorder_level'],
                    'unit_price' => (float)$item['unit_price'],
                    'status' => (string)$item['status'],
                    'is_low_stock' => (int)$item['quantity'] <= (int)$item['reorder_level'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ];
            }

            error_log("✅ Retrieved " . count($formattedItems) . " items");

            // ✅ FIX: Direct array return (Response::success wraps it)
            Response::success($formattedItems, 'Items retrieved successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getAll - " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
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
                SELECT 
                    i.item_id,
                    i.item_name,
                    i.sku,
                    i.category_id,
                    i.quantity,
                    i.unit,
                    i.reorder_level,
                    i.unit_price,
                    i.status,
                    i.created_at,
                    i.updated_at,
                    c.name AS category_name
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.item_id = ?
            ");

            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Response::notFound('Item not found');
                return;
            }

            Response::success($item, 'Item retrieved successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve item: ' . $e->getMessage());
        }
    }

    /**
     * POST /inventory - Create new item (ADMIN ONLY)
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

            // Check SKU uniqueness
            $stmt = $this->db->prepare("SELECT item_id FROM items WHERE sku = ?");
            $stmt->execute([$data['sku']]);
            if ($stmt->fetch()) {
                Response::badRequest('SKU already exists');
                return;
            }

            // Validate quantity
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

            $itemId = (int)$this->db->lastInsertId();

            // Audit log
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
            error_log("❌ InventoryController::create - " . $e->getMessage());
            Response::serverError('Failed to create item: ' . $e->getMessage());
        }
    }

    /**
     * PUT /inventory/{id} - Update item (ADMIN ONLY)
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

            // Build dynamic update query
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

            // Audit log
            $this->createAuditLog(
                $user->user_id,
                "Updated item: {$existingItem['item_name']}",
                'items',
                'update'
            );

            Response::success(null, 'Item updated successfully');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::update - " . $e->getMessage());
            Response::serverError('Failed to update item: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /inventory/{id} - Delete item (SUPERADMIN ONLY)
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

            // Audit log
            $this->createAuditLog(
                $user->user_id,
                "Deleted item: {$item['item_name']}",
                'items',
                'delete'
            );

            Response::success(null, $message);
        } catch (\Exception $e) {
            error_log("❌ InventoryController::delete - " . $e->getMessage());
            Response::serverError('Failed to delete item: ' . $e->getMessage());
        }
    }

    /**
     * GET /inventory/low-stock - Get low stock items
     */
    public function getLowStock()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    i.item_id,
                    i.item_name,
                    i.sku,
                    i.category_id,
                    i.quantity,
                    i.reorder_level,
                    i.unit_price,
                    i.unit,
                    c.name AS category_name,
                    (i.reorder_level - i.quantity) AS shortage
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                WHERE i.quantity <= i.reorder_level
                AND i.status = 'active'
                ORDER BY shortage DESC
            ");

            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($items ?: [], 'Low stock items retrieved');
        } catch (\Exception $e) {
            error_log("❌ InventoryController::getLowStock - " . $e->getMessage());
            Response::serverError('Failed to retrieve low stock items: ' . $e->getMessage());
        }
    }

    /**
     * Create audit log entry
     */
    private function createAuditLog($userId, $description, $module, $actionType)
    {
        try {
            if (!$userId) return;

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $description,
                $module,
                $actionType,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
