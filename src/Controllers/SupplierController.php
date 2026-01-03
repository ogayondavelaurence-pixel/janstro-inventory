<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use PDO;

class SupplierController
{
    private PDO $db;
    private bool $isDev;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * GET /suppliers - Get all suppliers (Staff can VIEW for PO creation)
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        if (!in_array($user->role, ['staff', 'admin', 'superadmin'])) {
            Response::forbidden('Access denied');
            return;
        }

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

            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$suppliers) {
                $suppliers = [];
            }

            $formatted = [];
            foreach ($suppliers as $s) {
                $formatted[] = [
                    'supplier_id' => (int)$s['supplier_id'],
                    'supplier_name' => htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8'),
                    'contact_person' => htmlspecialchars($s['contact_person'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'phone' => (string)($s['phone'] ?? ''),
                    'email' => (string)($s['email'] ?? ''),
                    'address' => htmlspecialchars($s['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'payment_terms' => (string)($s['payment_terms'] ?? 'Net 30'),
                    'status' => (string)$s['status'],
                    'created_at' => $s['created_at'],
                    'updated_at' => $s['updated_at']
                ];
            }

            if ($this->isDev) {
                error_log("Suppliers fetched: " . count($formatted) . " records for role: " . $user->role);
            }

            Response::success($formatted, 'Suppliers retrieved successfully');
        } catch (\Exception $e) {
            if ($this->isDev) {
                error_log("SupplierController::getAll - " . $e->getMessage());
            }
            Response::serverError('Failed to retrieve suppliers');
        }
    }

    /**
     * GET /suppliers/{id} - Get supplier by ID
     */
    public function getById(int $id): void
    {
        $user = AuthMiddleware::authenticate();
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
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                Response::notFound('Supplier not found');
                return;
            }

            $stmt = $this->db->prepare("
                SELECT po_id, quantity, total_amount, status, po_date
                FROM purchase_orders
                WHERE supplier_id = ?
                ORDER BY po_date DESC
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $supplier['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'supplier_id' => (int)$supplier['supplier_id'],
                'supplier_name' => htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8'),
                'contact_person' => htmlspecialchars($supplier['contact_person'] ?? '', ENT_QUOTES, 'UTF-8'),
                'phone' => (string)($supplier['phone'] ?? ''),
                'email' => (string)($supplier['email'] ?? ''),
                'address' => htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                'payment_terms' => (string)($supplier['payment_terms'] ?? 'Net 30'),
                'status' => (string)$supplier['status'],
                'recent_orders' => $supplier['recent_orders'],
                'created_at' => $supplier['created_at'],
                'updated_at' => $supplier['updated_at']
            ], 'Supplier retrieved successfully');
        } catch (\Exception $e) {
            if ($this->isDev) {
                error_log("SupplierController::getById - " . $e->getMessage());
            }
            Response::serverError('Failed to retrieve supplier');
        }
    }

    /**
     * POST /suppliers - Create supplier (ADMIN ONLY)
     */
    public function create(): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['supplier_name', 'contact_person', 'phone'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            $supplierName = htmlspecialchars(trim($data['supplier_name']), ENT_QUOTES, 'UTF-8');
            $contactPerson = htmlspecialchars(trim($data['contact_person']), ENT_QUOTES, 'UTF-8');
            $phone = htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8');

            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Invalid email format');
                return;
            }

            $stmt = $this->db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ?");
            $stmt->execute([$supplierName]);
            if ($stmt->fetch()) {
                Response::badRequest('Supplier name already exists');
                return;
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO suppliers (
                    supplier_name, contact_person, phone, email, address, payment_terms, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $supplierName,
                $contactPerson,
                $phone,
                $data['email'] ?? null,
                htmlspecialchars(trim($data['address'] ?? ''), ENT_QUOTES, 'UTF-8'),
                $data['payment_terms'] ?? 'Net 30'
            ]);

            $supplierId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address, user_agent)
                VALUES (?, ?, 'suppliers', 'create', ?, ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Created supplier: {$supplierName}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success([
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName
            ], 'Supplier created successfully', 201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->isDev) {
                error_log("SupplierController::create - " . $e->getMessage());
            }
            Response::serverError('Failed to create supplier');
        }
    }

    /**
     * PUT /suppliers/{id} - Update supplier (ADMIN ONLY)
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $stmt = $this->db->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $existingSupplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingSupplier) {
                Response::notFound('Supplier not found');
                return;
            }

            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Invalid email format');
                return;
            }

            if (isset($data['supplier_name'])) {
                $newName = htmlspecialchars(trim($data['supplier_name']), ENT_QUOTES, 'UTF-8');
                if ($newName !== $existingSupplier['supplier_name']) {
                    $stmt = $this->db->prepare("
                        SELECT supplier_id FROM suppliers 
                        WHERE supplier_name = ? AND supplier_id != ?
                    ");
                    $stmt->execute([$newName, $id]);
                    if ($stmt->fetch()) {
                        Response::badRequest('Supplier name already exists');
                        return;
                    }
                }
            }

            $this->db->beginTransaction();

            $updates = [];
            $values = [];

            $allowedFields = ['supplier_name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $values[] = htmlspecialchars(trim($data[$field]), ENT_QUOTES, 'UTF-8');
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

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address, user_agent)
                VALUES (?, ?, 'suppliers', 'update', ?, ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Updated supplier: {$existingSupplier['supplier_name']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success(null, 'Supplier updated successfully');
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->isDev) {
                error_log("SupplierController::update - " . $e->getMessage());
            }
            Response::serverError('Failed to update supplier');
        }
    }

    /**
     * DELETE /suppliers/{id} - Delete supplier (SUPERADMIN ONLY)
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                Response::notFound('Supplier not found');
                return;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->beginTransaction();

            if ($result['count'] > 0) {
                $stmt = $this->db->prepare("UPDATE suppliers SET status = 'inactive', updated_at = NOW() WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $message = 'Supplier deactivated (has purchase order history)';
            } else {
                $stmt = $this->db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $message = 'Supplier deleted permanently';
            }

            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address, user_agent)
                VALUES (?, ?, 'suppliers', 'delete', ?, ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Deleted supplier: {$supplier['supplier_name']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success(null, $message);
        } catch (\Exception $e) {
            $this->db->rollBack();
            if ($this->isDev) {
                error_log("SupplierController::delete - " . $e->getMessage());
            }
            Response::serverError('Failed to delete supplier');
        }
    }

    /**
     * GET /suppliers/{id}/performance - Get supplier performance
     */
    public function getPerformance(int $id): void
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
            $performance = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success($performance, 'Supplier performance retrieved');
        } catch (\Exception $e) {
            if ($this->isDev) {
                error_log("SupplierController::getPerformance - " . $e->getMessage());
            }
            Response::serverError('Failed to retrieve performance');
        }
    }
}
