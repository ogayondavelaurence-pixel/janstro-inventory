<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\InventoryService;
use Janstro\InventorySystem\Services\CompleteInventoryService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * FIXED Inventory Controller - Complete with all methods
 * Version: 4.0.0
 * Date: 2025-11-19
 */
class InventoryController
{
    private InventoryService $inventoryService;
    private CompleteInventoryService $completeService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
        $this->completeService = new CompleteInventoryService();
    }

    /**
     * GET /inventory
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $items = $this->inventoryService->getAllItems();
            Response::success($items, 'Items retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/{id}
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $item = $this->inventoryService->getItemById($id);

            if (!$item) {
                Response::notFound('Item not found');
                return;
            }

            Response::success($item, 'Item found');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/low-stock
     */
    public function getLowStock(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $items = $this->inventoryService->getLowStockItems();
            Response::success($items, 'Low stock items retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/movements/summary
     * Get stock movements summary statistics
     */
    public function getMovementsSummary(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $db = \Janstro\InventorySystem\Config\Database::connect();

            // Get summary stats
            $stmt = $db->query("
            SELECT 
                COUNT(*) as total_movements,
                SUM(CASE WHEN transaction_type = 'IN' THEN 1 ELSE 0 END) as total_in,
                SUM(CASE WHEN transaction_type = 'OUT' THEN 1 ELSE 0 END) as total_out,
                SUM(CASE WHEN transaction_type = 'IN' THEN quantity ELSE 0 END) as quantity_in,
                SUM(CASE WHEN transaction_type = 'OUT' THEN quantity ELSE 0 END) as quantity_out,
                DATE(MAX(transaction_date)) as last_movement_date
            FROM transactions
            WHERE DATE(transaction_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");

            $summary = $stmt->fetch();

            Response::success($summary, 'Movements summary retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
    /**
     * POST /inventory
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $this->inventoryService->createItem($data);

            Response::success($result, 'Item created successfully', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * PUT /inventory/{id}
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $success = $this->inventoryService->updateItem($id, $data);

            if ($success) {
                Response::success(null, 'Item updated successfully');
            } else {
                Response::error('Failed to update item');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * DELETE /inventory/{id}
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can delete items');
            return;
        }

        try {
            $success = $this->inventoryService->deleteItem($id);

            if ($success) {
                Response::success(null, 'Item deleted successfully');
            } else {
                Response::error('Failed to delete item');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
