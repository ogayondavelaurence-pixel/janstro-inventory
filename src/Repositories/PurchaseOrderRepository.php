<?php

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Models\PurchaseOrder;
use PDO;
use PDOException;

class PurchaseOrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* Get all purchase orders */
    public function getAll(?string $status = null): array
    {
        try {
            $sql = "
                SELECT po.*, s.supplier_name, i.item_name, u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
            ";

            if ($status) {
                $sql .= " WHERE po.status = ?";
                $stmt = $this->db->prepare($sql . " ORDER BY po.po_date DESC");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->db->query($sql . " ORDER BY po.po_date DESC");
            }

            $orders = [];
            while ($row = $stmt->fetch()) {
                $orders[] = new PurchaseOrder($row);
            }

            return $orders;
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /* Find purchase order by ID */
    public function findById(int $poId): ?PurchaseOrder
    {
        try {
            $stmt = $this->db->prepare("
                SELECT po.*, s.supplier_name, i.item_name, u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);

            $result = $stmt->fetch();
            return $result ? new PurchaseOrder($result) : null;
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    /* Create purchase order */
    public function create(array $data): ?int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    supplier_id,
                    item_id,
                    quantity,
                    unit_price,
                    total_amount,
                    expected_delivery_date,
                    created_by,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['item_id'],
                $data['quantity'],
                $data['unit_price'],
                $data['total_amount'],
                $data['expected_delivery_date'],
                $data['created_by'],
                $data['status'] ?? 'pending'
            ]);

            return (int)$this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /* Update purchase order status */
    public function updateStatus(int $poId, string $status): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = ? 
                WHERE po_id = ?
            ");

            return $stmt->execute([$status, $poId]);
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::updateStatus Error: " . $e->getMessage());
            return false;
        }
    }

    /* Get pending orders count */
    public function getPendingCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as total 
                FROM purchase_orders 
                WHERE status = 'pending'
            ");
            $result = $stmt->fetch();
            return (int)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::getPendingCount Error: " . $e->getMessage());
            return 0;
        }
    }
}
