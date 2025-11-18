<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\SupplierService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

class SupplierController
{
    private SupplierService $supplierService;

    public function __construct()
    {
        $this->supplierService = new SupplierService();
    }

    /**
     * GET /api/suppliers - FIXED
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $suppliers = $this->supplierService->getAllSuppliers();
            Response::success($suppliers, 'Suppliers retrieved successfully', 200);
        } catch (\Exception $e) {
            error_log("SupplierController::getAll Error: " . $e->getMessage());
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/suppliers/{id}
     */
    public function getById(int $id): void
    {
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
     */
    public function create(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);

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
     */
    public function update(int $id): void
    {
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
     */
    public function delete(int $id): void
    {
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
}
