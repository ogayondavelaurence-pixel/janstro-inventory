<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\CustomerService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * CUSTOMER CONTROLLER v2.0 - REFACTORED (Service Layer Pattern)
 * ============================================================================
 * Controllers should ONLY orchestrate services, never touch database directly
 * ============================================================================
 */
class CustomerController
{
    private CustomerService $customerService;

    public function __construct()
    {
        $this->customerService = new CustomerService();
    }

    /**
     * GET /customers - Get all customers
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $customers = $this->customerService->getAllCustomers();
            Response::success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            error_log("CustomerController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve customers');
        }
    }

    /**
     * GET /customers/{id} - Get single customer
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $customer = $this->customerService->getCustomerById($id);

            if (!$customer) {
                Response::notFound('Customer not found');
                return;
            }

            Response::success($customer, 'Customer retrieved successfully');
        } catch (\Exception $e) {
            error_log("CustomerController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve customer');
        }
    }

    /**
     * POST /customers - Create customer
     */
    public function create(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            $result = $this->customerService->createCustomer($data, $user->user_id);
            Response::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            error_log("CustomerController::create - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }

    /**
     * PUT /customers/{id} - Update customer
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                Response::badRequest('Invalid JSON data');
                return;
            }

            $result = $this->customerService->updateCustomer($id, $data, $user->user_id);
            Response::success($result, $result['message']);
        } catch (\Exception $e) {
            error_log("CustomerController::update - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }

    /**
     * DELETE /customers/{id} - Delete customer
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $result = $this->customerService->deleteCustomer($id, $user->user_id);
            Response::success(null, $result['message']);
        } catch (\Exception $e) {
            error_log("CustomerController::delete - " . $e->getMessage());
            Response::badRequest($e->getMessage());
        }
    }
}
