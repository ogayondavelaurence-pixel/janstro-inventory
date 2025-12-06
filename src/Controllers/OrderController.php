<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

class OrderController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Get all purchase orders (ALL ROLES)
     */
    public function getAll()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    po.po_id,
                    po.supplier_id,
                    s.supplier_name,
                    po.item_id,
                    i.item_name,
                    po.quantity,
                    po.unit_price,
                    po.total_amount,
                    po.expected_delivery_date,
                    po.status,
                    po.notes,
                    po.po_date,
                    po.created_by,
                    u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
                ORDER BY po.po_date DESC
            ");

            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($orders, 'Purchase orders retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve orders: ' . $e->getMessage());
        }
    }

    /**
     * Get purchase order by ID (ALL ROLES)
     */
    public function getById($id)
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    po.po_id,
                    po.supplier_id,
                    s.supplier_name,
                    s.contact_person,
                    s.phone,
                    s.email,
                    po.item_id,
                    i.item_name,
                    i.sku,
                    po.quantity,
                    po.unit_price,
                    po.total_amount,
                    po.expected_delivery_date,
                    po.status,
                    po.notes,
                    po.po_date,
                    po.created_by,
                    u.name as created_by_name,
                    po.delivered_date
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
                WHERE po.po_id = ?
            ");

            $stmt->execute([$id]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$order) {
                Response::notFound('Purchase order not found');
                return;
            }

            Response::success($order, 'Purchase order retrieved');
        } catch (\Exception $e) {
            error_log("OrderController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve order: ' . $e->getMessage());
        }
    }

    /**
     * Create purchase order (STAFF/ADMIN/SUPERADMIN)
     */
    public function create()
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['supplier_id', 'item_id', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Validate supplier exists
            $stmt = $this->db->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$data['supplier_id']]);
            if (!$stmt->fetch()) {
                Response::badRequest('Invalid supplier ID');
                return;
            }

            // Validate item exists
            $stmt = $this->db->prepare("SELECT item_id, item_name, unit_price FROM items WHERE item_id = ?");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$item) {
                Response::badRequest('Invalid item ID');
                return;
            }

            // Calculate total
            $unitPrice = $data['unit_price'] ?? $item['unit_price'];
            $totalAmount = $data['quantity'] * $unitPrice;

            // Insert PO
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    supplier_id,
                    item_id,
                    quantity,
                    unit_price,
                    total_amount,
                    expected_delivery_date,
                    status,
                    notes,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $data['item_id'],
                $data['quantity'],
                $unitPrice,
                $totalAmount,
                $data['expected_delivery_date'] ?? null,
                $data['notes'] ?? null,
                $user->user_id
            ]);

            $poId = $this->db->lastInsertId();

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Created Purchase Order #$poId: {$item['item_name']} x {$data['quantity']}",
                'purchase_orders',
                'create'
            );

            Response::success(
                ['po_id' => $poId],
                'Purchase order created successfully',
                201
            );
        } catch (\Exception $e) {
            error_log("OrderController::create - " . $e->getMessage());
            Response::serverError('Failed to create order: ' . $e->getMessage());
        }
    }

    /**
     * Approve purchase order (ADMIN ONLY)
     */
    public function approve($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            // Check if PO exists and is pending
            $stmt = $this->db->prepare("
                SELECT po_id, status 
                FROM purchase_orders 
                WHERE po_id = ?
            ");
            $stmt->execute([$id]);
            $po = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$po) {
                Response::notFound('Purchase order not found');
                return;
            }

            if ($po['status'] !== 'pending') {
                Response::badRequest('Purchase order is not pending');
                return;
            }

            // Update status
            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'approved' 
                WHERE po_id = ?
            ");
            $stmt->execute([$id]);

            // Create audit log
            $this->createAuditLog(
                $user->user_id,
                "Approved Purchase Order #$id",
                'purchase_orders',
                'approve'
            );

            Response::success(null, 'Purchase order approved');
        } catch (\Exception $e) {
            error_log("OrderController::approve - " . $e->getMessage());
            Response::serverError('Failed to approve order: ' . $e->getMessage());
        }
    }

    /**
     * Cancel purchase order (ADMIN ONLY)
     */
    public function cancel($id)
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT po_id, status 
                FROM purchase_orders 
                WHERE po_id = ?
            ");
            $stmt->execute([$id]);
            $po = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$po) {
                Response::notFound('Purchase order not found');
                return;
            }

            if ($po['status'] === 'delivered') {
                Response::badRequest('Cannot cancel delivered order');
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'cancelled' 
                WHERE po_id = ?
            ");
            $stmt->execute([$id]);

            $this->createAuditLog(
                $user->user_id,
                "Cancelled Purchase Order #$id",
                'purchase_orders',
                'cancel'
            );

            Response::success(null, 'Purchase order cancelled');
        } catch (\Exception $e) {
            error_log("OrderController::cancel - " . $e->getMessage());
            Response::serverError('Failed to cancel order: ' . $e->getMessage());
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
