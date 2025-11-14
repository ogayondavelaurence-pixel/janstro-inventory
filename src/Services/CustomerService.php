<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Utils\Validator;

/**
 * Customer Service
 * Manages customer master data
 * ISO/IEC 25010: Functional Suitability, Security
 */
class CustomerService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all customers
     */
    public function getAllCustomers(): array
    {
        $stmt = $this->db->query("
            SELECT c.*,
                   COUNT(DISTINCT so.order_id) AS total_orders,
                   COALESCE(SUM(so.total_amount), 0) AS total_spent
            FROM customers c
            LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
            GROUP BY c.customer_id
            ORDER BY c.customer_name ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $customerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COUNT(DISTINCT so.order_id) AS total_orders,
                   COALESCE(SUM(so.total_amount), 0) AS total_spent
            FROM customers c
            LEFT JOIN sales_orders so ON c.customer_id = so.customer_id
            WHERE c.customer_id = ?
            GROUP BY c.customer_id
        ");
        $stmt->execute([$customerId]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Search customers by name
     */
    public function searchCustomers(string $query): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM customers
            WHERE customer_name LIKE ? OR contact_no LIKE ? OR email LIKE ?
            ORDER BY customer_name ASC
            LIMIT 50
        ");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create customer
     */
    public function createCustomer(array $data): array
    {
        // Validate
        if (empty($data['customer_name'])) {
            throw new \Exception("Customer name is required");
        }

        if (!empty($data['email']) && !Validator::email($data['email'])) {
            throw new \Exception("Invalid email format");
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO customers (customer_name, contact_no, email, address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['customer_name'],
                $data['contact_no'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null
            ]);

            $customerId = (int)$this->db->lastInsertId();

            return [
                'customer_id' => $customerId,
                'message' => 'Customer created successfully'
            ];
        } catch (\PDOException $e) {
            throw new \Exception("Failed to create customer: " . $e->getMessage());
        }
    }

    /**
     * Update customer
     */
    public function updateCustomer(int $customerId, array $data): bool
    {
        // Check exists
        if (!$this->getCustomerById($customerId)) {
            throw new \Exception("Customer not found");
        }

        if (!empty($data['email']) && !Validator::email($data['email'])) {
            throw new \Exception("Invalid email format");
        }

        try {
            $fields = [];
            $values = [];

            $allowedFields = ['customer_name', 'contact_no', 'email', 'address'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $customerId;

            $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE customer_id = ?";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);
        } catch (\PDOException $e) {
            throw new \Exception("Failed to update customer: " . $e->getMessage());
        }
    }

    /**
     * Delete customer (only if no orders)
     */
    public function deleteCustomer(int $customerId): bool
    {
        $customer = $this->getCustomerById($customerId);
        if (!$customer) {
            throw new \Exception("Customer not found");
        }

        if ($customer['total_orders'] > 0) {
            throw new \Exception("Cannot delete customer with existing orders");
        }

        try {
            $stmt = $this->db->prepare("DELETE FROM customers WHERE customer_id = ?");
            return $stmt->execute([$customerId]);
        } catch (\PDOException $e) {
            throw new \Exception("Failed to delete customer: " . $e->getMessage());
        }
    }

    /**
     * Get customer's order history
     */
    public function getCustomerOrders(int $customerId): array
    {
        $stmt = $this->db->prepare("
            SELECT so.*, u.name AS created_by_name,
                   COUNT(DISTINCT soi.order_item_id) AS item_count
            FROM sales_orders so
            LEFT JOIN users u ON so.created_by = u.user_id
            LEFT JOIN sales_order_items soi ON so.order_id = soi.order_id
            WHERE so.customer_id = ?
            GROUP BY so.order_id
            ORDER BY so.created_at DESC
        ");
        $stmt->execute([$customerId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
