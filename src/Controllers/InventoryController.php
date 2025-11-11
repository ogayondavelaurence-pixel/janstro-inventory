<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\InventoryService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Inventory Controller
 * Handles all inventory-related HTTP requests
 * ISO/IEC 25010: Functional Suitability, Usability
 */
class InventoryController
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = new InventoryService();
    }

    /**
     * GET /inventory
     * Get all inventory items
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
     * Get single inventory item
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
     * Get items with stock below reorder level
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
     * GET /inventory/categories - NEW METHOD
     * Get all item categories
     */
    public function getCategories(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $categories = $this->inventoryService->getCategories();
            Response::success($categories, 'Categories retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/transactions - NEW METHOD
     * Get transaction history
     */
    public function getTransactions(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $transactions = $this->inventoryService->getTransactionHistory($limit);
            Response::success($transactions, 'Transactions retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/summary - NEW METHOD
     * Get inventory summary statistics
     */
    public function getSummary(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $summary = $this->inventoryService->getInventorySummary();
            Response::success($summary, 'Inventory summary retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /inventory/dashboard-stats
     * Get dashboard statistics
     */
    public function getDashboardStats(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stats = $this->inventoryService->getDashboardStats();
            Response::success($stats, 'Dashboard stats retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /inventory
     * Create new inventory item
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Only staff, admin, and superadmin can create items
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
     * Update inventory item
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
     * Delete inventory item
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

    /**
     * POST /inventory/stock-in
     * Add stock to inventory
     */
    public function stockIn(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->inventoryService->stockIn(
                (int)$data['item_id'],
                (int)$data['quantity'],
                $user->user_id,
                $data['notes'] ?? null
            );

            Response::success($result, 'Stock added successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /inventory/stock-out
     * Remove stock from inventory
     */
    public function stockOut(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $result = $this->inventoryService->stockOut(
                (int)$data['item_id'],
                (int)$data['quantity'],
                $user->user_id,
                $data['notes'] ?? null
            );

            Response::success($result, 'Stock removed successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
