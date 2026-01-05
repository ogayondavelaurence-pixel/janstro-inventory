<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\SupplierService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * SUPPLIER CONTROLLER v2.0 - REFACTORED
 * ============================================================================
 */
class SupplierController
{
    private SupplierService $supplierService;

    public function __construct()
    {
        $this->supplierService = new SupplierService();
    }

    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Access denied');
            return;
        }

        try {
            $suppliers = $this->supplierService->getAllSuppliers();
            Response::success($suppliers, 'Suppliers retrieved successfully');
        } catch (\Exception $e) {
            error_log("SupplierController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve suppliers');
        }
    }

    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $supplier = $this->supplierService->getSupplierById($id);

            if (!$supplier) {
                Response::notFound('Supplier not found');
                return;
            }

            Response::success($supplier, 'Supplier retrieved successfully');
        } catch (\Exception $e) {
            error_log("SupplierController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve supplier');
        }
    }

    public function create(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            $result = $this->supplierService->createSupplier($data, $user->user_id);
            Response::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            error_log("SupplierController::create - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }

    public function update(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!$data) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            $result = $this->supplierService->updateSupplier($id, $data, $user->user_id);
            Response::success(null, $result['message']);
        } catch (\Exception $e) {
            error_log("SupplierController::update - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $result = $this->supplierService->deleteSupplier($id, $user->user_id);
            Response::success(null, $result['message']);
        } catch (\Exception $e) {
            error_log("SupplierController::delete - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }
}
