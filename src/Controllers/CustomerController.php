<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\CustomerService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\Security;

/**
 * Customer Controller
 * Handles customer management HTTP requests
 * ISO/IEC 25010: Functional Suitability, Security, Usability
 */
class CustomerController
{
    private CustomerService $customerService;

    public function __construct()
    {
        $this->customerService = new CustomerService();
    }

    /**
     * GET /customers
     * Get all customers
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $customers = $this->customerService->getAllCustomers();
            Response::success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id}
     * Get single customer
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

            Response::success($customer, 'Customer found');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /customers/search?q={query}
     * Search customers
     */
    public function search(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        $query = $_GET['q'] ?? '';

        if (empty($query)) {
            Response::error('Search query is required', null, 400);
            return;
        }

        try {
            $customers = $this->customerService->searchCustomers($query);
            Response::success($customers, 'Search results retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * POST /customers
     * Create new customer
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        // Only staff and above can create customers
        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Insufficient permissions');
            return;
        }

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Sanitize inputs
            $data = array_map([Security::class, 'escapeInput'], $data);

            $result = $this->customerService->createCustomer($data);

            Response::success($result, 'Customer created successfully', 201);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * PUT /customers/{id}
     * Update customer
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

            // Sanitize inputs
            $data = array_map([Security::class, 'escapeInput'], $data);

            $success = $this->customerService->updateCustomer($id, $data);

            if ($success) {
                Response::success(null, 'Customer updated successfully');
            } else {
                Response::error('Failed to update customer');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * DELETE /customers/{id}
     * Delete customer (only if no orders)
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if ($user->role !== 'superadmin') {
            Response::forbidden('Only superadmin can delete customers');
            return;
        }

        try {
            $success = $this->customerService->deleteCustomer($id);

            if ($success) {
                Response::success(null, 'Customer deleted successfully');
            } else {
                Response::error('Failed to delete customer');
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }

    /**
     * GET /customers/{id}/orders
     * Get customer's order history
     */
    public function getOrders(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $orders = $this->customerService->getCustomerOrders($id);
            Response::success($orders, 'Customer orders retrieved');
        } catch (\Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
