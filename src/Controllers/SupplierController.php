<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

class SupplierController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all suppliers (ADMIN ONLY)
     */
    public function getAll()
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    supplier_id,
                    supplier_name,
                    contact_person,
                    phone,
                    email,
                    address,
                    payment_terms,
                    status,
                    created_at,
                    updated_at
                FROM suppliers
                WHERE status = 'active'
                ORDER BY supplier_name ASC
            ");

            $suppliers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($suppliers, 'Suppliers retrieved successfully');
        } catch (\Exception $e) {
            error_log("SupplierController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve suppliers: ' . $e->getMessage());
        }
    }

    /**
     * Get supplier by ID (ADMIN ONLY)
     */
    public function getById($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    supplier_id,
                    supplier_name,
                    contact_person,
                    phone,
                    email,
                    address,
                    payment_terms,
                    status,
                    created_at,
                    updated_at
                FROM suppliers
                WHERE supplier_id = ?
            ");

            $stmt->execute([$id]);
            $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$supplier) {
                Response::notFound('Supplier not found');
                return;
            }

            // Get supplier's purchase orders
            $stmt = $this->db->prepare("
                SELECT 
                    po_id,
                    quantity,
                    total_amount,
                    status,
                    po_date
                FROM purchase_orders
                WHERE supplier_id = ?
                ORDER BY po_date DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $supplier['recent_orders'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($supplier, 'Supplier retrieved successfully');
        } catch (\Exception $e) {
            error_log("SupplierController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve supplier: ' . $e->getMessage());
        }
    }

    /**
     * Create new supplier (ADMIN ONLY)
     */
    public function create()
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['supplier_name', 'contact_person', 'phone'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Validate email format if provided
            if (isset($data['email']) && !empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    Response::badRequest('Invalid email format');
                    return;
                }
            }

            // Check if supplier name already exists
            $stmt = $this->db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ?");
            $stmt->execute([$data['supplier_name']]);
            if ($stmt->fetch()) {
                Response::badRequest('Supplier name already exists');
                return;
            }

            // Insert supplier
            $stmt = $this->db->prepare("
                INSERT INTO suppliers (
                    supplier_name,
                    contact_person,
                    phone,
                    email,
                    address,
                    payment_terms,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $data['supplier_name'],
                $data['contact_person'],
                $data['phone'],
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['payment_terms'] ?? 'Net 30'
            ]);

            $supplierId = $this->db->lastInsertId();

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Created supplier: {$data['supplier_name']}",
                'suppliers',
                'create'
            );

            Response::success(
                ['supplier_id' => $supplierId],
                'Supplier created successfully',
                201
            );
        } catch (\Exception $e) {
            error_log("SupplierController::create - " . $e->getMessage());
            Response::serverError('Failed to create supplier: ' . $e->getMessage());
        }
    }

    /**
     * Update supplier (ADMIN ONLY)
     */
    public function update($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if supplier exists
            $stmt = $this->db->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $existingSupplier = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existingSupplier) {
                Response::notFound('Supplier not found');
                return;
            }

            // Validate email if provided
            if (isset($data['email']) && !empty($data['email'])) {
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    Response::badRequest('Invalid email format');
                    return;
                }
            }

            // Check name uniqueness if being updated
            if (isset($data['supplier_name']) && $data['supplier_name'] !== $existingSupplier['supplier_name']) {
                $stmt = $this->db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ? AND supplier_id != ?");
                $stmt->execute([$data['supplier_name'], $id]);
                if ($stmt->fetch()) {
                    Response::badRequest('Supplier name already exists');
                    return;
                }
            }

            // Build update query dynamically
            $updates = [];
            $values = [];

            $allowedFields = ['supplier_name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                Response::badRequest('No fields to update');
                return;
            }

            $updates[] = "updated_at = NOW()";
            $values[] = $id;

            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . " WHERE supplier_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Updated supplier: {$existingSupplier['supplier_name']}",
                'suppliers',
                'update'
            );

            Response::success(null, 'Supplier updated successfully');
        } catch (\Exception $e) {
            error_log("SupplierController::update - " . $e->getMessage());
            Response::serverError('Failed to update supplier: ' . $e->getMessage());
        }
    }

    /**
     * Delete supplier (SUPERADMIN ONLY)
     */
    public function delete($id)
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            // Check if supplier exists
            $stmt = $this->db->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$supplier) {
                Response::notFound('Supplier not found');
                return;
            }

            // Check if supplier has purchase orders
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                // Soft delete if has purchase orders
                $stmt = $this->db->prepare("UPDATE suppliers SET status = 'inactive', updated_at = NOW() WHERE supplier_id = ?");
                $stmt->execute([$id]);

                $message = 'Supplier deactivated (has purchase order history)';
            } else {
                // Hard delete if no purchase orders
                $stmt = $this->db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$id]);

                $message = 'Supplier deleted permanently';
            }

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Deleted supplier: {$supplier['supplier_name']}",
                'suppliers',
                'delete'
            );

            Response::success(null, $message);
        } catch (\Exception $e) {
            error_log("SupplierController::delete - " . $e->getMessage());
            Response::serverError('Failed to delete supplier: ' . $e->getMessage());
        }
    }

    /**
     * Get supplier performance metrics (ADMIN ONLY)
     */
    public function getPerformance($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(total_amount) as total_value,
                    AVG(DATEDIFF(delivered_date, po_date)) as avg_delivery_days
                FROM purchase_orders
                WHERE supplier_id = ?
            ");
            $stmt->execute([$id]);
            $performance = $stmt->fetch(\PDO::FETCH_ASSOC);

            Response::success($performance, 'Supplier performance retrieved');
        } catch (\Exception $e) {
            error_log("SupplierController::getPerformance - " . $e->getMessage());
            Response::serverError('Failed to retrieve performance: ' . $e->getMessage());
        }
    }

    /**
     * Create audit log
     */
    private function createAuditLog($userId, $description, $module, $actionType)
    {
        try {
            if (!$userId) return;

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, ip_address)
                VALUES (?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
