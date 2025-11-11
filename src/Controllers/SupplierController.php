<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\SupplierService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Supplier Controller
 * Handles supplier management API endpoints
 * ISO/IEC 25010: Functional Suitability, Usability
 */
class SupplierController
{
    private SupplierService $supplierService;

    public function __construct()
    {
        $this->supplierService = new SupplierService();
    }

    /**
     * GET /api/suppliers
     * Get all suppliers
     */
    public function getAll(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $suppliers = $this->supplierService->getAllSuppliers();
            Response::success($suppliers, 'Suppliers retrieved successfully', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/suppliers/{id}
     * Get single supplier
     */
    public function getById(int $id): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $supplier = $this->supplierService->getSupplierById($id);

            if ($supplier) {
                Response::success($supplier, 'Supplier found', 200);
            } else {
                Response::notFound('Supplier not found');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/suppliers
     * Create new supplier
     */
    public function create(): void
    {
        // Require admin role
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            if (empty($input['supplier_name'])) {
                Response::error('Supplier name is required', null, 400);
                return;
            }

            $result = $this->supplierService->createSupplier($input);

            Response::success($result, 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * PUT /api/suppliers/{id}
     * Update supplier
     */
    public function update(int $id): void
    {
        // Require admin role
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $success = $this->supplierService->updateSupplier($id, $input);

            if ($success) {
                Response::success(null, 'Supplier updated successfully', 200);
            } else {
                Response::error('Failed to update supplier', null, 400);
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * DELETE /api/suppliers/{id}
     * Delete supplier
     */
    public function delete(int $id): void
    {
        // Require superadmin role
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $success = $this->supplierService->deleteSupplier($id);

            if ($success) {
                Response::success(null, 'Supplier deleted successfully', 200);
            } else {
                Response::error('Failed to delete supplier', null, 400);
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/suppliers/{id}/orders
     * Get supplier's purchase orders
     */
    public function getOrders(int $id): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $orders = $this->supplierService->getSupplierOrders($id);
            Response::success($orders, 'Supplier orders retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }
}
