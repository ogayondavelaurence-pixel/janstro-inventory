<?php

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Models\Supplier;
use PDO;
use PDOException;

class SupplierRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* Get all suppliers */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM suppliers 
                ORDER BY supplier_name
            ");

            $suppliers = [];
            while ($row = $stmt->fetch()) {
                $suppliers[] = new Supplier($row);
            }

            return $suppliers;
        } catch (PDOException $e) {
            error_log("SupplierRepository::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /* Find supplier by ID */
    public function findById(int $supplierId): ?Supplier
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM suppliers 
                WHERE supplier_id = ?
            ");
            $stmt->execute([$supplierId]);

            $result = $stmt->fetch();
            return $result ? new Supplier($result) : null;
        } catch (PDOException $e) {
            error_log("SupplierRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    /* Create supplier */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO suppliers (supplier_name, contact_info, address, email)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['supplier_name'],
                $data['contact_info'] ?? null,
                $data['address'] ?? null,
                $data['email'] ?? null
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("SupplierRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /* Update supplier */
    public function update(int $supplierId, array $data): bool
    {
        try {
            $fields = [];
            $values = [];

            $allowedFields = ['supplier_name', 'contact_info', 'address', 'email'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return false;
            }

            $values[] = $supplierId;

            $sql = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE supplier_id = ?";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute($values);
        } catch (PDOException $e) {
            error_log("SupplierRepository::update Error: " . $e->getMessage());
            return false;
        }
    }

    /* Delete supplier */
    public function delete(int $supplierId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
            return $stmt->execute([$supplierId]);
        } catch (PDOException $e) {
            error_log("SupplierRepository::delete Error: " . $e->getMessage());
            return false;
        }
    }
}
