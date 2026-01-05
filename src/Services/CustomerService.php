<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * CUSTOMER SERVICE v1.0 - PRODUCTION READY
 * ============================================================================
 * Handles ALL customer business logic
 * Controllers should ONLY call these methods
 * ============================================================================
 */
class CustomerService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    /**
     * Get all customers with aggregated stats
     */
    public function getAllCustomers(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    c.customer_id, c.customer_name, c.contact_number, c.email,
                    c.address, c.customer_type, c.source, c.notes,
                    c.total_inquiries, c.first_inquiry_date, c.last_activity_date,
                    c.created_at, c.updated_at,
                    COUNT(DISTINCT so.sales_order_id) as total_orders,
                    COALESCE(SUM(so.total_amount), 0) as total_sales
                FROM customers c
                LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
                GROUP BY 
                    c.customer_id, c.customer_name, c.contact_number, c.email,
                    c.address, c.customer_type, c.source, c.notes,
                    c.total_inquiries, c.first_inquiry_date, c.last_activity_date,
                    c.created_at, c.updated_at
                ORDER BY c.created_at DESC
            ");

            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($c) {
                return [
                    'customer_id' => (int)$c['customer_id'],
                    'customer_name' => htmlspecialchars($c['customer_name'], ENT_QUOTES, 'UTF-8'),
                    'contact_number' => (string)($c['contact_number'] ?? ''),
                    'email' => (string)($c['email'] ?? ''),
                    'address' => htmlspecialchars($c['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'customer_type' => (string)($c['customer_type'] ?? 'individual'),
                    'source' => (string)($c['source'] ?? ''),
                    'notes' => htmlspecialchars($c['notes'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'total_orders' => (int)$c['total_orders'],
                    'total_sales' => (float)$c['total_sales'],
                    'total_inquiries' => (int)($c['total_inquiries'] ?? 0),
                    'first_inquiry_date' => $c['first_inquiry_date'],
                    'last_activity_date' => $c['last_activity_date'],
                    'created_at' => $c['created_at'],
                    'updated_at' => $c['updated_at']
                ];
            }, $customers);
        } catch (Exception $e) {
            error_log("CustomerService::getAllCustomers - " . $e->getMessage());
            throw new Exception('Failed to retrieve customers');
        }
    }

    /**
     * Get single customer by ID with recent orders
     */
    public function getCustomerById(int $customerId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*, COUNT(DISTINCT so.sales_order_id) as total_orders,
                    COALESCE(SUM(so.total_amount), 0) as total_sales
                FROM customers c
                LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
                WHERE c.customer_id = ?
                GROUP BY c.customer_id
            ");

            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                return null;
            }

            // Get recent orders
            $stmt = $this->db->prepare("
                SELECT sales_order_id, order_date, installation_date, 
                       total_amount, status
                FROM sales_orders
                WHERE customer_id = ?
                ORDER BY order_date DESC
                LIMIT 10
            ");
            $stmt->execute([$customerId]);
            $customer['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'customer_id' => (int)$customer['customer_id'],
                'customer_name' => htmlspecialchars($customer['customer_name'], ENT_QUOTES, 'UTF-8'),
                'contact_number' => (string)($customer['contact_number'] ?? ''),
                'email' => (string)($customer['email'] ?? ''),
                'address' => htmlspecialchars($customer['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                'customer_type' => (string)($customer['customer_type'] ?? 'individual'),
                'source' => (string)($customer['source'] ?? ''),
                'notes' => htmlspecialchars($customer['notes'] ?? '', ENT_QUOTES, 'UTF-8'),
                'total_orders' => (int)$customer['total_orders'],
                'total_sales' => (float)$customer['total_sales'],
                'total_inquiries' => (int)($customer['total_inquiries'] ?? 0),
                'first_inquiry_date' => $customer['first_inquiry_date'],
                'last_activity_date' => $customer['last_activity_date'],
                'recent_orders' => $customer['recent_orders'],
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at']
            ];
        } catch (Exception $e) {
            error_log("CustomerService::getCustomerById - " . $e->getMessage());
            throw new Exception('Failed to retrieve customer');
        }
    }

    // ========================================================================
    // CREATE OPERATION
    // ========================================================================

    /**
     * Create new customer with validation
     */
    public function createCustomer(array $data, int $userId): array
    {
        try {
            // Validate required fields
            $this->validateCustomerData($data, true);

            // Check duplicates
            if ($this->contactNumberExists($data['contact_number'])) {
                throw new Exception('Customer with this contact number already exists');
            }

            if (!empty($data['email']) && $this->emailExists($data['email'])) {
                throw new Exception('Customer with this email already exists');
            }

            $this->db->beginTransaction();

            // Sanitize inputs
            $customerName = htmlspecialchars(trim($data['customer_name']), ENT_QUOTES, 'UTF-8');
            $contactNumber = htmlspecialchars(trim($data['contact_number']), ENT_QUOTES, 'UTF-8');
            $email = !empty($data['email']) ? htmlspecialchars(trim($data['email']), ENT_QUOTES, 'UTF-8') : null;
            $address = htmlspecialchars(trim($data['address'] ?? ''), ENT_QUOTES, 'UTF-8');
            $customerType = $data['customer_type'] ?? 'individual';
            $source = htmlspecialchars(trim($data['source'] ?? ''), ENT_QUOTES, 'UTF-8');
            $notes = htmlspecialchars(trim($data['notes'] ?? ''), ENT_QUOTES, 'UTF-8');

            // Insert customer
            $stmt = $this->db->prepare("
                INSERT INTO customers (
                    customer_name, contact_number, email, address,
                    customer_type, source, notes, 
                    first_inquiry_date, last_activity_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $stmt->execute([
                $customerName,
                $contactNumber,
                $email,
                $address,
                $customerType,
                $source,
                $notes
            ]);

            $customerId = (int)$this->db->lastInsertId();

            // Audit log
            $this->createAuditLog(
                $userId,
                "Created customer: {$customerName}",
                'customers',
                'create'
            );

            $this->db->commit();

            return [
                'success' => true,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'message' => 'Customer created successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("CustomerService::createCustomer - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // UPDATE OPERATION
    // ========================================================================

    /**
     * Update customer (excludes current customer from duplicate check)
     */
    public function updateCustomer(int $customerId, array $data, int $userId): array
    {
        try {
            // Validate customer exists
            $existing = $this->getCustomerById($customerId);
            if (!$existing) {
                throw new Exception('Customer not found');
            }

            // Validate data
            $this->validateCustomerData($data, false);

            // Check duplicates (excluding current customer)
            if (
                isset($data['contact_number']) &&
                $this->contactNumberExists($data['contact_number'], $customerId)
            ) {
                throw new Exception('Another customer with this contact number already exists');
            }

            if (
                !empty($data['email']) &&
                $this->emailExists($data['email'], $customerId)
            ) {
                throw new Exception('Another customer with this email already exists');
            }

            $this->db->beginTransaction();

            // Build update query
            $updates = [];
            $values = [];

            $allowedFields = [
                'customer_name',
                'contact_number',
                'email',
                'address',
                'customer_type',
                'source',
                'notes'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = htmlspecialchars(trim($data[$field]), ENT_QUOTES, 'UTF-8');
                }
            }

            if (empty($updates)) {
                throw new Exception('No fields to update');
            }

            $updates[] = "last_activity_date = NOW()";
            $updates[] = "updated_at = NOW()";
            $values[] = $customerId;

            $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE customer_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            // Audit log
            $this->createAuditLog(
                $userId,
                "Updated customer ID: {$customerId} ({$existing['customer_name']})",
                'customers',
                'update'
            );

            $this->db->commit();

            return [
                'success' => true,
                'customer_id' => $customerId,
                'message' => 'Customer updated successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("CustomerService::updateCustomer - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // DELETE OPERATION
    // ========================================================================

    /**
     * Delete customer (checks for existing orders first)
     */
    public function deleteCustomer(int $customerId, int $userId): array
    {
        try {
            // Check exists
            $customer = $this->getCustomerById($customerId);
            if (!$customer) {
                throw new Exception('Customer not found');
            }

            // Check for existing sales orders
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as order_count 
                FROM sales_orders 
                WHERE customer_id = ?
            ");
            $stmt->execute([$customerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['order_count'] > 0) {
                throw new Exception('Cannot delete customer with existing sales orders. Please cancel or transfer orders first.');
            }

            $this->db->beginTransaction();

            // Delete customer
            $stmt = $this->db->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->execute([$customerId]);

            // Audit log
            $this->createAuditLog(
                $userId,
                "Deleted customer ID: {$customerId} ({$customer['customer_name']})",
                'customers',
                'delete'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Customer deleted successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("CustomerService::deleteCustomer - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // VALIDATION HELPERS
    // ========================================================================

    /**
     * Validate customer data
     */
    private function validateCustomerData(array $data, bool $isCreate): void
    {
        if ($isCreate) {
            if (empty($data['customer_name'])) {
                throw new Exception('Customer name is required');
            }

            if (empty($data['contact_number'])) {
                throw new Exception('Contact number is required');
            }
        }

        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Customer type validation
        if (isset($data['customer_type'])) {
            $validTypes = ['individual', 'commercial', 'government'];
            if (!in_array($data['customer_type'], $validTypes)) {
                throw new Exception('Invalid customer type. Must be: individual, commercial, or government');
            }
        }
    }

    /**
     * Check if contact number exists (excluding specific customer)
     */
    private function contactNumberExists(string $contactNumber, ?int $excludeCustomerId = null): bool
    {
        try {
            if ($excludeCustomerId) {
                $stmt = $this->db->prepare("
                    SELECT customer_id FROM customers 
                    WHERE contact_number = ? AND customer_id != ?
                    LIMIT 1
                ");
                $stmt->execute([$contactNumber, $excludeCustomerId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT customer_id FROM customers 
                    WHERE contact_number = ? LIMIT 1
                ");
                $stmt->execute([$contactNumber]);
            }

            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("CustomerService::contactNumberExists - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if email exists (excluding specific customer)
     */
    private function emailExists(string $email, ?int $excludeCustomerId = null): bool
    {
        try {
            if ($excludeCustomerId) {
                $stmt = $this->db->prepare("
                    SELECT customer_id FROM customers 
                    WHERE email = ? AND customer_id != ?
                    LIMIT 1
                ");
                $stmt->execute([$email, $excludeCustomerId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT customer_id FROM customers 
                    WHERE email = ? LIMIT 1
                ");
                $stmt->execute([$email]);
            }

            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("CustomerService::emailExists - " . $e->getMessage());
            return false;
        }
    }

    // ========================================================================
    // AUDIT LOGGING
    // ========================================================================

    /**
     * Create audit log entry
     */
    private function createAuditLog(
        int $userId,
        string $description,
        string $module,
        string $actionType
    ): void {
        try {
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
        } catch (Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
