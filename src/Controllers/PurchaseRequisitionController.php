<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use PDO;

/**
 * ============================================================================
 * PURCHASE REQUISITION CONTROLLER - MISSING FROM YOUR SYSTEM
 * ============================================================================
 */
class PurchaseRequisitionController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /purchase-requisitions
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    pr.*,
                    i.item_name, i.sku, i.unit, i.unit_price,
                    so.customer_name, so.order_date,
                    u.name as requested_by_name
                FROM purchase_requisitions pr
                LEFT JOIN items i ON pr.item_id = i.item_id
                LEFT JOIN sales_orders so ON pr.sales_order_id = so.sales_order_id
                LEFT JOIN users u ON pr.requested_by = u.user_id
                ORDER BY 
                    FIELD(pr.status, 'pending', 'approved', 'rejected', 'converted'),
                    FIELD(pr.urgency, 'critical', 'high', 'medium', 'low'),
                    pr.created_at DESC
            ");

            $prs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary
            $summary = [
                'total' => count($prs),
                'pending' => 0,
                'approved' => 0,
                'converted' => 0
            ];

            foreach ($prs as $pr) {
                if ($pr['status'] === 'pending') $summary['pending']++;
                if ($pr['status'] === 'approved') $summary['approved']++;
                if ($pr['status'] === 'converted') $summary['converted']++;
            }

            Response::success([
                'requisitions' => $prs,
                'summary' => $summary
            ], 'Purchase requisitions retrieved');
        } catch (\Exception $e) {
            error_log("PR getAll error: " . $e->getMessage());
            Response::serverError('Failed to retrieve requisitions');
        }
    }

    /**
     * POST /purchase-requisitions/create-from-shortage
     * Auto-create PR from stock shortage
     */
    public function createFromShortage(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['sales_order_id', 'item_id', 'required_quantity'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::badRequest("Missing: $field");
                    return;
                }
            }

            $this->db->beginTransaction();

            // Get current stock
            $stmt = $this->db->prepare("
                SELECT quantity, reorder_level, item_name 
                FROM items WHERE item_id = ?
            ");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                $this->db->rollBack();
                Response::notFound('Item not found');
                return;
            }

            $shortage = $data['required_quantity'] - $item['quantity'];
            $urgency = $this->calculateUrgency($shortage, $item['quantity'], $item['reorder_level']);

            // Generate PR number
            $prNumber = 'PR-' . date('Y') . '-' . str_pad($this->getNextPRNumber(), 6, '0', STR_PAD_LEFT);

            // Create PR
            $stmt = $this->db->prepare("
                INSERT INTO purchase_requisitions (
                    pr_number, sales_order_id, item_id, required_quantity,
                    requested_by, status, urgency, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");

            $stmt->execute([
                $prNumber,
                $data['sales_order_id'],
                $data['item_id'],
                $shortage,
                $user->user_id,
                $urgency,
                "Stock shortage for SO #{$data['sales_order_id']} - {$item['item_name']}"
            ]);

            $prId = (int)$this->db->lastInsertId();

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'purchase_requisitions', 'create', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Created PR #{$prNumber} for stock shortage (SO #{$data['sales_order_id']})",
                $_SERVER['REMOTE_ADDR'] ?? 'system'
            ]);

            $this->db->commit();

            Response::success([
                'pr_id' => $prId,
                'pr_number' => $prNumber,
                'urgency' => $urgency,
                'shortage_quantity' => $shortage
            ], 'Purchase requisition created', 201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("PR create error: " . $e->getMessage());
            Response::serverError('Failed to create requisition');
        }
    }

    /**
     * POST /purchase-requisitions/{id}/approve (ADMIN ONLY)
     */
    public function approve(int $prId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT pr_id, status, pr_number 
                FROM purchase_requisitions 
                WHERE pr_id = ?
            ");
            $stmt->execute([$prId]);
            $pr = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pr) {
                $this->db->rollBack();
                Response::notFound('PR not found');
                return;
            }

            if ($pr['status'] !== 'pending') {
                $this->db->rollBack();
                Response::badRequest("PR already {$pr['status']}");
                return;
            }

            // Update status
            $stmt = $this->db->prepare("
                UPDATE purchase_requisitions 
                SET status = 'approved', 
                    approved_by = ?, 
                    approved_at = NOW()
                WHERE pr_id = ?
            ");
            $stmt->execute([$user->user_id, $prId]);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'purchase_requisitions', 'approve', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Approved PR #{$pr['pr_number']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success(null, 'PR approved successfully');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("PR approve error: " . $e->getMessage());
            Response::serverError('Failed to approve PR');
        }
    }

    /**
     * POST /purchase-requisitions/{id}/reject (ADMIN ONLY)
     */
    public function reject(int $prId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['reason'])) {
                Response::badRequest('Rejection reason required');
                return;
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE purchase_requisitions 
                SET status = 'rejected',
                    rejection_reason = ?,
                    approved_by = ?
                WHERE pr_id = ? AND status = 'pending'
            ");

            if (!$stmt->execute([$data['reason'], $user->user_id, $prId]) || $stmt->rowCount() === 0) {
                $this->db->rollBack();
                Response::badRequest('PR not found or already processed');
                return;
            }

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'purchase_requisitions', 'reject', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Rejected PR #{$prId}: {$data['reason']}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success(null, 'PR rejected');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("PR reject error: " . $e->getMessage());
            Response::serverError('Failed to reject PR');
        }
    }

    /**
     * POST /purchase-requisitions/{id}/convert-to-po (ADMIN ONLY)
     */
    public function convertToPO(int $prId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['supplier_id'])) {
                Response::badRequest('Supplier required');
                return;
            }

            $this->db->beginTransaction();

            // Get PR details
            $stmt = $this->db->prepare("
                SELECT pr.*, i.unit_price, i.item_name
                FROM purchase_requisitions pr
                JOIN items i ON pr.item_id = i.item_id
                WHERE pr.pr_id = ? AND pr.status = 'approved'
            ");
            $stmt->execute([$prId]);
            $pr = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pr) {
                $this->db->rollBack();
                Response::badRequest('PR not found or not approved');
                return;
            }

            // Create PO
            $unitPrice = $data['unit_price'] ?? $pr['unit_price'];
            $totalAmount = $unitPrice * $pr['required_quantity'];

            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    supplier_id, item_id, quantity, unit_price, total_amount,
                    expected_delivery_date, status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
            ");

            $stmt->execute([
                $data['supplier_id'],
                $pr['item_id'],
                $pr['required_quantity'],
                $unitPrice,
                $totalAmount,
                $data['expected_delivery_date'] ?? date('Y-m-d', strtotime('+7 days')),
                "Generated from PR #{$pr['pr_number']}",
                $user->user_id
            ]);

            $poId = (int)$this->db->lastInsertId();

            // Mark PR as converted
            $stmt = $this->db->prepare("
                UPDATE purchase_requisitions 
                SET status = 'converted', converted_to_po_id = ?
                WHERE pr_id = ?
            ");
            $stmt->execute([$poId, $prId]);

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'purchase_requisitions', 'convert_to_po', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Converted PR #{$pr['pr_number']} to PO #{$poId}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success([
                'po_id' => $poId,
                'pr_id' => $prId
            ], 'PR converted to PO successfully', 201);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("PR convert error: " . $e->getMessage());
            Response::serverError('Failed to convert PR');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getNextPRNumber(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) as total 
            FROM purchase_requisitions 
            WHERE YEAR(created_at) = YEAR(NOW())
        ");
        return ((int)$stmt->fetchColumn()) + 1;
    }

    private function calculateUrgency(int $shortage, int $currentStock, int $reorderLevel): string
    {
        if ($currentStock === 0) return 'critical';
        if ($shortage > $reorderLevel * 2) return 'critical';
        if ($shortage > $reorderLevel) return 'high';
        if ($currentStock < $reorderLevel) return 'medium';
        return 'low';
    }
}
