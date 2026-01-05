<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * SUPPLIER SERVICE v2.0 - PRODUCTION READY
 * ============================================================================
 */
class SupplierService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ========================================================================
    // READ OPERATIONS
    // ========================================================================

    public function getAllSuppliers(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    s.supplier_id, s.supplier_name, s.contact_person,
                    s.phone, s.email, s.address, s.payment_terms, s.status,
                    s.created_at, s.updated_at,
                    COUNT(po.po_id) as total_orders,
                    COALESCE(SUM(CASE WHEN po.status = 'delivered' THEN po.total_amount ELSE 0 END), 0) as total_value
                FROM suppliers s
                LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
                WHERE s.status = 'active'
                GROUP BY s.supplier_id
                ORDER BY s.supplier_name ASC
            ");

            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function ($s) {
                return [
                    'supplier_id' => (int)$s['supplier_id'],
                    'supplier_name' => htmlspecialchars($s['supplier_name'], ENT_QUOTES, 'UTF-8'),
                    'contact_person' => htmlspecialchars($s['contact_person'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'phone' => (string)($s['phone'] ?? ''),
                    'email' => (string)($s['email'] ?? ''),
                    'address' => htmlspecialchars($s['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'payment_terms' => (string)($s['payment_terms'] ?? 'Net 30'),
                    'status' => (string)$s['status'],
                    'total_orders' => (int)$s['total_orders'],
                    'total_value' => (float)$s['total_value'],
                    'created_at' => $s['created_at'],
                    'updated_at' => $s['updated_at']
                ];
            }, $suppliers);
        } catch (Exception $e) {
            error_log("SupplierService::getAllSuppliers - " . $e->getMessage());
            throw new Exception('Failed to retrieve suppliers');
        }
    }

    public function getSupplierById(int $supplierId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*, 
                    COUNT(po.po_id) as total_orders,
                    COALESCE(SUM(CASE WHEN po.status = 'delivered' THEN po.total_amount ELSE 0 END), 0) as total_value
                FROM suppliers s
                LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
                WHERE s.supplier_id = ?
                GROUP BY s.supplier_id
            ");

            $stmt->execute([$supplierId]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return null;
            }

            // Get recent orders
            $stmt = $this->db->prepare("
                SELECT po_id, quantity, total_amount, status, po_date
                FROM purchase_orders
                WHERE supplier_id = ?
                ORDER BY po_date DESC
                LIMIT 10
            ");
            $stmt->execute([$supplierId]);
            $supplier['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'supplier_id' => (int)$supplier['supplier_id'],
                'supplier_name' => htmlspecialchars($supplier['supplier_name'], ENT_QUOTES, 'UTF-8'),
                'contact_person' => htmlspecialchars($supplier['contact_person'] ?? '', ENT_QUOTES, 'UTF-8'),
                'phone' => (string)($supplier['phone'] ?? ''),
                'email' => (string)($supplier['email'] ?? ''),
                'address' => htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES, 'UTF-8'),
                'payment_terms' => (string)($supplier['payment_terms'] ?? 'Net 30'),
                'status' => (string)$supplier['status'],
                'total_orders' => (int)$supplier['total_orders'],
                'total_value' => (float)$supplier['total_value'],
                'recent_orders' => $supplier['recent_orders'],
                'created_at' => $supplier['created_at'],
                'updated_at' => $supplier['updated_at']
            ];
        } catch (Exception $e) {
            error_log("SupplierService::getSupplierById - " . $e->getMessage());
            throw new Exception('Failed to retrieve supplier');
        }
    }

    // ========================================================================
    // CREATE OPERATION
    // ========================================================================

    public function createSupplier(array $data, int $userId): array
    {
        try {
            $this->validateSupplierData($data, true);

            if ($this->supplierNameExists($data['supplier_name'])) {
                throw new Exception('Supplier name already exists');
            }

            $this->db->beginTransaction();

            $supplierName = htmlspecialchars(trim($data['supplier_name']), ENT_QUOTES, 'UTF-8');
            $contactPerson = htmlspecialchars(trim($data['contact_person']), ENT_QUOTES, 'UTF-8');
            $phone = htmlspecialchars(trim($data['phone']), ENT_QUOTES, 'UTF-8');
            $email = !empty($data['email']) ? htmlspecialchars(trim($data['email']), ENT_QUOTES, 'UTF-8') : null;
            $address = htmlspecialchars(trim($data['address'] ?? ''), ENT_QUOTES, 'UTF-8');
            $paymentTerms = $data['payment_terms'] ?? 'Net 30';

            $stmt = $this->db->prepare("
                INSERT INTO suppliers (
                    supplier_name, contact_person, phone, email, address, payment_terms, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");

            $stmt->execute([
                $supplierName,
                $contactPerson,
                $phone,
                $email,
                $address,
                $paymentTerms
            ]);

            $supplierId = (int)$this->db->lastInsertId();

            $this->createAuditLog(
                $userId,
                "Created supplier: {$supplierName}",
                'suppliers',
                'create'
            );

            $this->db->commit();

            return [
                'success' => true,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplierName,
                'message' => 'Supplier created successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SupplierService::createSupplier - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // UPDATE OPERATION
    // ========================================================================

    public function updateSupplier(int $supplierId, array $data, int $userId): array
    {
        try {
            $existing = $this->getSupplierById($supplierId);
            if (!$existing) {
                throw new Exception('Supplier not found');
            }

            $this->validateSupplierData($data, false);

            if (
                isset($data['supplier_name']) &&
                $this->supplierNameExists($data['supplier_name'], $supplierId)
            ) {
                throw new Exception('Supplier name already exists');
            }

            $this->db->beginTransaction();

            $updates = [];
            $values = [];

            $allowedFields = [
                'supplier_name',
                'contact_person',
                'phone',
                'email',
                'address',
                'payment_terms',
                'status'
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

            $updates[] = "updated_at = NOW()";
            $values[] = $supplierId;

            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . " WHERE supplier_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->createAuditLog(
                $userId,
                "Updated supplier ID: {$supplierId} ({$existing['supplier_name']})",
                'suppliers',
                'update'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Supplier updated successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SupplierService::updateSupplier - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // DELETE OPERATION
    // ========================================================================

    public function deleteSupplier(int $supplierId, int $userId): array
    {
        try {
            $supplier = $this->getSupplierById($supplierId);
            if (!$supplier) {
                throw new Exception('Supplier not found');
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?
            ");
            $stmt->execute([$supplierId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->beginTransaction();

            if ($result['count'] > 0) {
                $stmt = $this->db->prepare("
                    UPDATE suppliers SET status = 'inactive', updated_at = NOW() 
                    WHERE supplier_id = ?
                ");
                $stmt->execute([$supplierId]);
                $message = 'Supplier deactivated (has purchase order history)';
            } else {
                $stmt = $this->db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$supplierId]);
                $message = 'Supplier deleted permanently';
            }

            $this->createAuditLog(
                $userId,
                "Deleted supplier ID: {$supplierId} ({$supplier['supplier_name']})",
                'suppliers',
                'delete'
            );

            $this->db->commit();

            return [
                'success' => true,
                'message' => $message
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("SupplierService::deleteSupplier - " . $e->getMessage());
            throw $e;
        }
    }

    // ========================================================================
    // VALIDATION HELPERS
    // ========================================================================

    private function validateSupplierData(array $data, bool $isCreate): void
    {
        if ($isCreate) {
            $required = ['supplier_name', 'contact_person', 'phone'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
    }

    private function supplierNameExists(string $name, ?int $excludeSupplierId = null): bool
    {
        try {
            if ($excludeSupplierId) {
                $stmt = $this->db->prepare("
                    SELECT supplier_id FROM suppliers 
                    WHERE supplier_name = ? AND supplier_id != ? LIMIT 1
                ");
                $stmt->execute([$name, $excludeSupplierId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT supplier_id FROM suppliers WHERE supplier_name = ? LIMIT 1
                ");
                $stmt->execute([$name]);
            }

            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            error_log("SupplierService::supplierNameExists - " . $e->getMessage());
            return false;
        }
    }

    private function createAuditLog(int $userId, string $description, string $module, string $actionType): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $description,
                $module,
                $actionType,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
