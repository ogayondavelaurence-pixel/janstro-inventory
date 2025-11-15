<?php

namespace Janstro\InventorySystem\Repositories;

use Janstro\InventorySystem\Config\Database;
use PDO;
use PDOException;

/**
 * Purchase Order Repository - FIXED v2.1
 * Now supports multi-item purchase orders
 */
class PurchaseOrderRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all purchase orders (FIXED - Multi-item support)
     */
    public function getAll(?string $status = null): array
    {
        try {
            $sql = "
                SELECT 
                    po.po_id,
                    po.supplier_id,
                    s.supplier_name,
                    po.status,
                    po.po_date,
                    po.delivered_date,
                    po.notes,
                    po.created_by,
                    u.name as created_by_name,
                    COUNT(poi.po_item_id) AS item_count,
                    SUM(poi.line_total) AS total_amount
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN users u ON po.created_by = u.user_id
                LEFT JOIN purchase_order_items poi ON po.po_id = poi.po_id
            ";

            if ($status) {
                $sql .= " WHERE po.status = ?";
            }

            $sql .= " GROUP BY po.po_id ORDER BY po.po_date DESC";

            if ($status) {
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$status]);
            } else {
                $stmt = $this->db->query($sql);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Find purchase order by ID (FIXED)
     */
    public function findById(int $poId): ?array
    {
        try {
            // Get PO header
            $stmt = $this->db->prepare("
                SELECT 
                    po.*,
                    s.supplier_name,
                    u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN users u ON po.created_by = u.user_id
                WHERE po.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$po) {
                return null;
            }

            // Get PO items
            $stmt = $this->db->prepare("
                SELECT 
                    poi.*,
                    i.item_name,
                    i.unit
                FROM purchase_order_items poi
                LEFT JOIN items i ON poi.item_id = i.item_id
                WHERE poi.po_id = ?
            ");
            $stmt->execute([$poId]);
            $po['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate total
            $po['total_amount'] = array_sum(array_column($po['items'], 'line_total'));
            $po['item_count'] = count($po['items']);

            return $po;
        } catch (PDOException $e) {
            error_log("PurchaseOrderRepository::findById Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create purchase order (FIXED - Multi-item support)
     * Now accepts items array instead of single item
     */
    public function create(array $data): ?int
    {
        try {
            $this->db->beginTransaction();

            // Create PO header
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (supplier_id, status, created_by, notes)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['status'] ?? 'pending',
                $data['created_by'],
                $data['notes'] ?? null
            ]);

            $poId = (int)$this->db->lastInsertId();

            // Insert PO items
            if (!empty($data['items'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO purchase_order_items (po_id, item_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($data['items'] as $item) {
                    $lineTotal = $item['quantity'] * $item['unit_price'];
                    $stmt->execute([
                        $poId,
                        $item['item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $lineTotal
                    ]);
                }
            }

            $this->db->commit();
            return $poId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("PurchaseOrderRepository::create Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update purchase order status
     */
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

    /**
     * Get pending orders count
     */
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
