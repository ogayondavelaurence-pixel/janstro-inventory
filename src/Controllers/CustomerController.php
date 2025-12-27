<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\Security;
use Janstro\InventorySystem\Utils\AuditLogger;
use PDO;

/**
 * ============================================================================
 * CustomerController v2.1 - PRODUCTION READY
 * ============================================================================
 * Complete CRUD for customer management with:
 * ✅ Duplicate validation (excludes current record on update)
 * ✅ Email validation
 * ✅ XSS protection via Security::sanitizeInput()
 * ✅ Audit logging
 * ✅ Transaction safety
 * ✅ Proper error handling
 * ============================================================================
 */
class CustomerController
{
    private PDO $db;
    private bool $isDev;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * ========================================================================
     * GET /customers - Get all customers (Staff/Admin/Superadmin)
     * ✅ Fixed: Explicit column list for GROUP BY compatibility
     * ✅ Returns empty array if no customers found
     * ========================================================================
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    c.customer_id,
                    c.customer_name,
                    c.contact_number,
                    c.email,
                    c.address,
                    c.customer_type,
                    c.source,
                    c.notes,
                    c.total_inquiries,
                    c.first_inquiry_date,
                    c.last_activity_date,
                    c.created_at,
                    c.updated_at,
                    COUNT(DISTINCT so.sales_order_id) as total_orders,
                    COALESCE(SUM(so.total_amount), 0) as total_sales
                FROM customers c
                LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
                GROUP BY 
                    c.customer_id,
                    c.customer_name,
                    c.contact_number,
                    c.email,
                    c.address,
                    c.customer_type,
                    c.source,
                    c.notes,
                    c.total_inquiries,
                    c.first_inquiry_date,
                    c.last_activity_date,
                    c.created_at,
                    c.updated_at
                ORDER BY c.created_at DESC
            ");

            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ✅ FIX: Always return array (even if empty)
            if (!$customers) {
                Response::success([], 'No customers found');
                return;
            }

            // Format with type safety and XSS protection
            $formatted = [];
            foreach ($customers as $c) {
                $formatted[] = [
                    'customer_id' => (int)$c['customer_id'],
                    'customer_name' => Security::sanitizeOutput($c['customer_name']),
                    'contact_number' => (string)($c['contact_number'] ?? ''),
                    'email' => (string)($c['email'] ?? ''),
                    'address' => Security::sanitizeOutput($c['address'] ?? ''),
                    'customer_type' => (string)($c['customer_type'] ?? 'individual'),
                    'source' => (string)($c['source'] ?? ''),
                    'notes' => Security::sanitizeOutput($c['notes'] ?? ''),
                    'total_orders' => (int)$c['total_orders'],
                    'total_sales' => (float)$c['total_sales'],
                    'total_inquiries' => (int)($c['total_inquiries'] ?? 0),
                    'first_inquiry_date' => $c['first_inquiry_date'],
                    'last_activity_date' => $c['last_activity_date'],
                    'created_at' => $c['created_at'],
                    'updated_at' => $c['updated_at']
                ];
            }

            Response::success($formatted, 'Customers retrieved successfully');
        } catch (\PDOException $e) {
            error_log("❌ CustomerController::getAll - PDO Error: " . $e->getMessage());
            Response::serverError('Failed to retrieve customers: Database error');
        } catch (\Exception $e) {
            error_log("❌ CustomerController::getAll - Error: " . $e->getMessage());
            Response::serverError('Failed to retrieve customers');
        }
    }

    /**
     * ========================================================================
     * GET /customers/{id} - Get single customer with recent orders
     * ✅ Returns 404 if not found
     * ✅ Includes recent order history
     * ========================================================================
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT so.sales_order_id) as total_orders,
                    COALESCE(SUM(so.total_amount), 0) as total_sales
                FROM customers c
                LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
                WHERE c.customer_id = ?
                GROUP BY c.customer_id
            ");

            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Response::notFound('Customer not found');
                return;
            }

            // Get recent orders
            $stmt = $this->db->prepare("
                SELECT 
                    sales_order_id, 
                    order_date, 
                    installation_date, 
                    total_amount, 
                    status
                FROM sales_orders
                WHERE customer_id = ?
                ORDER BY order_date DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $customer['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'customer_id' => (int)$customer['customer_id'],
                'customer_name' => Security::sanitizeOutput($customer['customer_name']),
                'contact_number' => (string)($customer['contact_number'] ?? ''),
                'email' => (string)($customer['email'] ?? ''),
                'address' => Security::sanitizeOutput($customer['address'] ?? ''),
                'customer_type' => (string)($customer['customer_type'] ?? 'individual'),
                'source' => (string)($customer['source'] ?? ''),
                'notes' => Security::sanitizeOutput($customer['notes'] ?? ''),
                'total_orders' => (int)$customer['total_orders'],
                'total_sales' => (float)$customer['total_sales'],
                'total_inquiries' => (int)($customer['total_inquiries'] ?? 0),
                'first_inquiry_date' => $customer['first_inquiry_date'],
                'last_activity_date' => $customer['last_activity_date'],
                'recent_orders' => $customer['recent_orders'],
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at']
            ], 'Customer retrieved successfully');
        } catch (\Exception $e) {
            error_log("❌ CustomerController::getById - Error: " . $e->getMessage());
            Response::serverError('Failed to retrieve customer');
        }
    }

    /**
     * ========================================================================
     * POST /customers - Create new customer (Admin/Superadmin)
     * ✅ Validates required fields
     * ✅ Checks for duplicate contact number
     * ✅ Validates email format
     * ✅ Uses transactions for atomicity
     * ✅ Audit logging
     * ========================================================================
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

            // Validate required fields
            $required = ['customer_name', 'contact_number'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Sanitize inputs
            $customerName = Security::sanitizeInput($data['customer_name']);
            $contactNumber = Security::sanitizeInput($data['contact_number']);
            $email = Security::sanitizeInput($data['email'] ?? '');
            $address = Security::sanitizeInput($data['address'] ?? '');
            $customerType = $data['customer_type'] ?? 'individual';
            $source = Security::sanitizeInput($data['source'] ?? '');
            $notes = Security::sanitizeInput($data['notes'] ?? '');

            // Validate email format
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Invalid email format');
                return;
            }

            // Validate customer type
            $validTypes = ['individual', 'commercial', 'government'];
            if (!in_array($customerType, $validTypes)) {
                Response::badRequest('Invalid customer type. Must be: individual, commercial, or government');
                return;
            }

            // Check for duplicate contact number
            $stmt = $this->db->prepare("
                SELECT customer_id 
                FROM customers 
                WHERE contact_number = ? 
                LIMIT 1
            ");
            $stmt->execute([$contactNumber]);

            if ($stmt->fetch()) {
                Response::badRequest('Customer with this contact number already exists');
                return;
            }

            // Check for duplicate email (if provided)
            if (!empty($email)) {
                $stmt = $this->db->prepare("
                    SELECT customer_id 
                    FROM customers 
                    WHERE email = ? 
                    LIMIT 1
                ");
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    Response::badRequest('Customer with this email already exists');
                    return;
                }
            }

            // Begin transaction
            $this->db->beginTransaction();

            // Insert customer
            $stmt = $this->db->prepare("
                INSERT INTO customers (
                    customer_name, 
                    contact_number, 
                    email, 
                    address,
                    customer_type, 
                    source, 
                    notes, 
                    first_inquiry_date, 
                    last_activity_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $customerName,
                $contactNumber,
                $email ?: null,
                $address ?: null,
                $customerType,
                $source ?: null,
                $notes ?: null
            ]);

            $customerId = (int)$this->db->lastInsertId();

            // Audit log
            AuditLogger::log(
                $user->user_id,
                "Created customer: {$customerName}",
                'customers',
                'create'
            );

            $this->db->commit();

            Response::success([
                'customer_id' => $customerId,
                'customer_name' => $customerName
            ], 'Customer created successfully', 201);
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::create - PDO Error: " . $e->getMessage());
            Response::serverError('Failed to create customer: Database error');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::create - Error: " . $e->getMessage());
            Response::serverError('Failed to create customer');
        }
    }

    /**
     * ========================================================================
     * PUT /customers/{id} - Update customer (Admin/Superadmin)
     * ✅ CRITICAL FIX: Excludes current customer from duplicate check
     * ✅ Allows updating with same contact number/email
     * ✅ Still prevents true duplicates from OTHER customers
     * ========================================================================
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

            // Validate required fields
            $required = ['customer_name', 'contact_number', 'email', 'address', 'customer_type'];
            foreach ($required as $field) {
                if (empty($data[$field]) && $data[$field] !== '') {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Sanitize inputs
            $customerName = Security::sanitizeInput($data['customer_name']);
            $contactNumber = Security::sanitizeInput($data['contact_number']);
            $email = Security::sanitizeInput($data['email']);
            $address = Security::sanitizeInput($data['address']);
            $customerType = $data['customer_type'];
            $source = Security::sanitizeInput($data['source'] ?? '');
            $notes = Security::sanitizeInput($data['notes'] ?? '');

            // Validate email format
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Invalid email format');
                return;
            }

            // Validate customer type
            $validTypes = ['individual', 'commercial', 'government'];
            if (!in_array($customerType, $validTypes)) {
                Response::badRequest('Invalid customer type. Must be: individual, commercial, or government');
                return;
            }

            // ✅ FIX: Check if contact number exists for OTHER customers (not this one)
            $stmt = $this->db->prepare("
                SELECT customer_id 
                FROM customers 
                WHERE contact_number = ? 
                AND customer_id != ?
                LIMIT 1
            ");
            $stmt->execute([$contactNumber, $id]);

            if ($stmt->fetch()) {
                Response::badRequest('Another customer with this contact number already exists');
                return;
            }

            // ✅ FIX: Check if email exists for OTHER customers (not this one)
            if (!empty($email)) {
                $stmt = $this->db->prepare("
                    SELECT customer_id 
                    FROM customers 
                    WHERE email = ? 
                    AND customer_id != ?
                    LIMIT 1
                ");
                $stmt->execute([$email, $id]);

                if ($stmt->fetch()) {
                    Response::badRequest('Another customer with this email already exists');
                    return;
                }
            }

            // Check if customer exists
            $stmt = $this->db->prepare("
                SELECT customer_id, customer_name 
                FROM customers 
                WHERE customer_id = ?
            ");
            $stmt->execute([$id]);
            $existingCustomer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingCustomer) {
                Response::notFound('Customer not found');
                return;
            }

            // Begin transaction
            $this->db->beginTransaction();

            // Update customer
            $stmt = $this->db->prepare("
                UPDATE customers 
                SET 
                    customer_name = ?,
                    contact_number = ?,
                    email = ?,
                    address = ?,
                    customer_type = ?,
                    source = ?,
                    notes = ?,
                    last_activity_date = NOW(),
                    updated_at = NOW()
                WHERE customer_id = ?
            ");

            $stmt->execute([
                $customerName,
                $contactNumber,
                $email ?: null,
                $address ?: null,
                $customerType,
                $source ?: null,
                $notes ?: null,
                $id
            ]);

            // Audit log
            AuditLogger::log(
                $user->user_id,
                "Updated customer ID: {$id} ({$customerName})",
                'customers',
                'update'
            );

            $this->db->commit();

            Response::success([
                'customer_id' => $id,
                'customer_name' => $customerName
            ], 'Customer updated successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::update - PDO Error: " . $e->getMessage());
            Response::serverError('Failed to update customer: Database error');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::update - Error: " . $e->getMessage());
            Response::serverError('Failed to update customer');
        }
    }

    /**
     * ========================================================================
     * DELETE /customers/{id} - Delete customer (Superadmin only)
     * ✅ Checks for existing sales orders before deletion
     * ✅ Prevents orphaned data
     * ✅ Audit logging
     * ========================================================================
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            // Check if customer exists
            $stmt = $this->db->prepare("
                SELECT customer_id, customer_name 
                FROM customers 
                WHERE customer_id = ?
            ");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                Response::notFound('Customer not found');
                return;
            }

            // Check for existing sales orders
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as order_count 
                FROM sales_orders 
                WHERE customer_id = ?
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['order_count'] > 0) {
                Response::badRequest('Cannot delete customer with existing sales orders. Please cancel or transfer orders first.');
                return;
            }

            // Begin transaction
            $this->db->beginTransaction();

            // Delete customer
            $stmt = $this->db->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$id]);

            // Audit log
            AuditLogger::log(
                $user->user_id,
                "Deleted customer ID: {$id} ({$customer['customer_name']})",
                'customers',
                'delete'
            );

            $this->db->commit();

            Response::success(null, 'Customer deleted successfully');
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::delete - PDO Error: " . $e->getMessage());
            Response::serverError('Failed to delete customer: Database error');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("❌ CustomerController::delete - Error: " . $e->getMessage());
            Response::serverError('Failed to delete customer');
        }
    }
}
