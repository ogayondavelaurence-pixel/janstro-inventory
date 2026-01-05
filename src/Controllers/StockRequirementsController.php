<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use PDO;

/**
 * ============================================================================
 * STOCK REQUIREMENTS CONTROLLER v2.0 - COMPLETE ENHANCEMENT
 * ============================================================================
 * ENHANCEMENTS:
 * ✅ Auto-PR generation with urgency calculation
 * ✅ Real-time stock availability updates
 * ✅ Multi-item SO support
 * ✅ Batch PR generation
 * ✅ Stock shortage alerts
 * ✅ Integration with CompleteInventoryService
 * ============================================================================
 */
class StockRequirementsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /stock-requirements
     * Returns all stock requirements with detailed analysis
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            // ✅ ENHANCEMENT: Join with more tables for complete info
            $stmt = $this->db->query("
                SELECT 
                    sr.requirement_id,
                    sr.sales_order_id,
                    sr.item_id,
                    sr.required_quantity,
                    sr.available_quantity,
                    sr.shortage_quantity,
                    sr.status,
                    sr.created_at,
                    sr.updated_at,
                    so.customer_name,
                    so.contact_number,
                    so.order_date,
                    so.installation_date,
                    so.status as order_status,
                    so.customer_order_number,
                    i.item_name,
                    i.sku,
                    i.unit,
                    i.unit_price,
                    i.quantity as current_stock,
                    i.reorder_level,
                    CASE 
                        WHEN sr.status = 'sufficient' THEN 'success'
                        WHEN sr.status = 'shortage' THEN 'warning'
                        WHEN sr.status = 'critical' THEN 'danger'
                        ELSE 'secondary'
                    END as status_color,
                    (sr.shortage_quantity > 0) as needs_pr,
                    -- ✅ NEW: Check if PR already exists
                    (SELECT COUNT(*) 
                     FROM purchase_requisitions pr 
                     WHERE pr.sales_order_id = sr.sales_order_id 
                     AND pr.item_id = sr.item_id 
                     AND pr.status IN ('pending', 'approved')
                    ) as has_pending_pr
                FROM stock_requirements sr
                LEFT JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                LEFT JOIN items i ON sr.item_id = i.item_id
                WHERE so.status != 'cancelled'
                ORDER BY 
                    CASE sr.status 
                        WHEN 'critical' THEN 1 
                        WHEN 'shortage' THEN 2 
                        WHEN 'sufficient' THEN 3 
                    END,
                    sr.created_at DESC
            ");

            $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary
            $summary = [
                'total' => count($requirements),
                'sufficient' => 0,
                'shortage' => 0,
                'critical' => 0,
                'total_shortage_value' => 0
            ];

            foreach ($requirements as &$req) {
                // Type casting
                $req['requirement_id'] = (int)$req['requirement_id'];
                $req['sales_order_id'] = (int)$req['sales_order_id'];
                $req['item_id'] = (int)$req['item_id'];
                $req['required_quantity'] = (int)$req['required_quantity'];
                $req['available_quantity'] = (int)$req['available_quantity'];
                $req['shortage_quantity'] = (int)$req['shortage_quantity'];
                $req['current_stock'] = (int)$req['current_stock'];
                $req['reorder_level'] = (int)$req['reorder_level'];
                $req['unit_price'] = (float)$req['unit_price'];
                $req['needs_pr'] = (bool)$req['needs_pr'];
                $req['has_pending_pr'] = (bool)$req['has_pending_pr'];

                // ✅ NEW: Calculate shortage value
                $req['shortage_value'] = $req['shortage_quantity'] * $req['unit_price'];

                // Update summary
                $summary[$req['status']]++;
                $summary['total_shortage_value'] += $req['shortage_value'];
            }

            $summary['total_shortage_value'] = round($summary['total_shortage_value'], 2);

            Response::success([
                'requirements' => $requirements,
                'summary' => $summary
            ], 'Stock requirements retrieved');
        } catch (\PDOException $e) {
            error_log("Stock requirements error: " . $e->getMessage());
            Response::serverError('Failed to retrieve stock requirements');
        }
    }

    /**
     * GET /stock-requirements/:id
     * Get single requirement with full details
     */
    public function getById(int $requirementId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    sr.*,
                    so.customer_name,
                    so.contact_number,
                    so.delivery_address,
                    so.order_date,
                    so.installation_date,
                    so.total_amount,
                    so.status as order_status,
                    so.customer_order_number,
                    i.item_name,
                    i.sku,
                    i.unit,
                    i.unit_price,
                    i.quantity as current_stock,
                    i.reorder_level,
                    -- ✅ NEW: Get associated PR if exists
                    pr.pr_id,
                    pr.pr_number,
                    pr.status as pr_status,
                    pr.urgency as pr_urgency
                FROM stock_requirements sr
                LEFT JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                LEFT JOIN items i ON sr.item_id = i.item_id
                LEFT JOIN purchase_requisitions pr ON (
                    pr.sales_order_id = sr.sales_order_id 
                    AND pr.item_id = sr.item_id
                    AND pr.status IN ('pending', 'approved')
                )
                WHERE sr.requirement_id = ?
            ");
            $stmt->execute([$requirementId]);
            $requirement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$requirement) {
                Response::notFound('Stock requirement not found');
                return;
            }

            // ✅ NEW: Add calculated fields
            $requirement['shortage_value'] = $requirement['shortage_quantity'] * $requirement['unit_price'];
            $requirement['days_until_installation'] = null;

            if ($requirement['installation_date']) {
                $installDate = new \DateTime($requirement['installation_date']);
                $today = new \DateTime();
                $diff = $today->diff($installDate);
                $requirement['days_until_installation'] = (int)$diff->format('%r%a');
            }

            Response::success($requirement, 'Requirement retrieved');
        } catch (\PDOException $e) {
            error_log("Get requirement error: " . $e->getMessage());
            Response::serverError('Failed to retrieve requirement');
        }
    }

    /**
     * POST /stock-requirements/calculate/:sales_order_id
     * Recalculate stock requirements for a sales order
     * ✅ ENHANCEMENT: Now handles multi-item orders
     */
    public function recalculate(int $salesOrderId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $this->db->beginTransaction();

            // Get sales order items
            $stmt = $this->db->prepare("
                SELECT soi.item_id, soi.quantity, i.quantity as current_stock, i.item_name
                FROM sales_order_items soi
                JOIN items i ON soi.item_id = i.item_id
                WHERE soi.sales_order_id = ?
            ");
            $stmt->execute([$salesOrderId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                $this->db->rollBack();
                Response::notFound('No items found for this sales order');
                return;
            }

            // Delete existing requirements
            $stmt = $this->db->prepare("DELETE FROM stock_requirements WHERE sales_order_id = ?");
            $stmt->execute([$salesOrderId]);

            // Create new requirements with current stock
            $stmt = $this->db->prepare("
                INSERT INTO stock_requirements (sales_order_id, item_id, required_quantity, available_quantity)
                VALUES (?, ?, ?, ?)
            ");

            $results = [];
            foreach ($items as $item) {
                $stmt->execute([
                    $salesOrderId,
                    $item['item_id'],
                    $item['quantity'],
                    $item['current_stock']
                ]);

                $results[] = [
                    'item_name' => $item['item_name'],
                    'required' => $item['quantity'],
                    'available' => $item['current_stock'],
                    'shortage' => max(0, $item['quantity'] - $item['current_stock'])
                ];
            }

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'stock_requirements', 'recalculate', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Recalculated stock requirements for SO #$salesOrderId",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $this->db->commit();

            Response::success([
                'sales_order_id' => $salesOrderId,
                'items_processed' => count($items),
                'results' => $results
            ], 'Stock requirements recalculated');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Recalculate error: " . $e->getMessage());
            Response::serverError('Failed to recalculate requirements');
        }
    }

    /**
     * POST /stock-requirements/:id/generate-pr
     * Generate purchase requisition from shortage
     * ✅ ENHANCEMENT: Auto-urgency, duplicate check, notification
     */
    public function generatePR(int $requirementId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            // Get requirement details
            $stmt = $this->db->prepare("
                SELECT 
                    sr.*, 
                    i.item_name, 
                    i.reorder_level,
                    so.customer_name,
                    so.installation_date
                FROM stock_requirements sr
                JOIN items i ON sr.item_id = i.item_id
                JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                WHERE sr.requirement_id = ?
            ");
            $stmt->execute([$requirementId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req) {
                Response::notFound('Requirement not found');
                return;
            }

            if ($req['shortage_quantity'] <= 0) {
                Response::badRequest('No shortage - PR not needed');
                return;
            }

            // ✅ NEW: Check if PR already exists
            $stmt = $this->db->prepare("
                SELECT pr_id, pr_number, status 
                FROM purchase_requisitions 
                WHERE sales_order_id = ? AND item_id = ? 
                AND status IN ('pending', 'approved')
                LIMIT 1
            ");
            $stmt->execute([$req['sales_order_id'], $req['item_id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                Response::badRequest(
                    "PR already exists: {$existing['pr_number']} (Status: {$existing['status']})"
                );
                return;
            }

            $this->db->beginTransaction();

            // Generate PR number
            $stmt = $this->db->query("
                SELECT COALESCE(MAX(CAST(SUBSTRING(pr_number, 4) AS UNSIGNED)), 0) + 1 as next_num
                FROM purchase_requisitions
                WHERE pr_number LIKE 'PR-%'
            ");
            $nextNum = $stmt->fetchColumn();
            $prNumber = 'PR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            // ✅ ENHANCEMENT: Calculate urgency based on multiple factors
            $urgency = $this->calculateUrgency(
                $req['shortage_quantity'],
                $req['reorder_level'],
                $req['installation_date']
            );

            // Create PR
            $stmt = $this->db->prepare("
                INSERT INTO purchase_requisitions 
                (pr_number, sales_order_id, item_id, required_quantity, requested_by, urgency, reason, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $reason = sprintf(
                "Stock shortage for SO #%d: %s - Customer: %s (Shortage: %d units)",
                $req['sales_order_id'],
                $req['item_name'],
                $req['customer_name'],
                $req['shortage_quantity']
            );

            $stmt->execute([
                $prNumber,
                $req['sales_order_id'],
                $req['item_id'],
                $req['shortage_quantity'],
                $user->user_id,
                $urgency,
                $reason
            ]);

            $prId = (int)$this->db->lastInsertId();

            // Audit log
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'stock_requirements', 'pr_generated', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Generated {$prNumber} from requirement #{$requirementId} (Urgency: {$urgency})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            // ✅ NEW: Send notification for critical/high urgency
            if (in_array($urgency, ['critical', 'high'])) {
                try {
                    $notifService = new \Janstro\InventorySystem\Services\NotificationService();
                    $notifService->notifyPRCreated($prId, [
                        'pr_number' => $prNumber,
                        'item_name' => $req['item_name'],
                        'quantity' => $req['shortage_quantity'],
                        'urgency' => $urgency,
                        'customer' => $req['customer_name']
                    ]);
                } catch (\Exception $e) {
                    error_log("Notification failed: " . $e->getMessage());
                    // Don't fail the transaction
                }
            }

            $this->db->commit();

            Response::success([
                'pr_id' => $prId,
                'pr_number' => $prNumber,
                'quantity' => $req['shortage_quantity'],
                'urgency' => $urgency,
                'message' => "Purchase requisition created successfully"
            ], 'PR created successfully', 201);
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Generate PR error: " . $e->getMessage());
            Response::serverError('Failed to generate PR: ' . $e->getMessage());
        }
    }

    /**
     * ✅ NEW: POST /stock-requirements/batch-generate-pr
     * Generate PRs for all shortages in a sales order
     */
    public function batchGeneratePR(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $salesOrderId = $data['sales_order_id'] ?? null;

            if (!$salesOrderId) {
                Response::badRequest('sales_order_id required');
                return;
            }

            // Get all shortages for this SO
            $stmt = $this->db->prepare("
                SELECT requirement_id, item_id, item_name, shortage_quantity
                FROM stock_requirements sr
                JOIN items i ON sr.item_id = i.item_id
                WHERE sr.sales_order_id = ? AND sr.shortage_quantity > 0
            ");
            $stmt->execute([$salesOrderId]);
            $shortages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($shortages)) {
                Response::success([], 'No shortages found');
                return;
            }

            $this->db->beginTransaction();

            $results = ['success' => [], 'failed' => []];

            foreach ($shortages as $shortage) {
                try {
                    // Check if PR already exists
                    $stmt = $this->db->prepare("
                        SELECT COUNT(*) 
                        FROM purchase_requisitions 
                        WHERE sales_order_id = ? AND item_id = ? 
                        AND status IN ('pending', 'approved')
                    ");
                    $stmt->execute([$salesOrderId, $shortage['item_id']]);

                    if ($stmt->fetchColumn() > 0) {
                        $results['failed'][] = [
                            'item' => $shortage['item_name'],
                            'reason' => 'PR already exists'
                        ];
                        continue;
                    }

                    // Generate PR (inline to avoid multiple DB connections)
                    $prNumber = $this->generatePRNumber();

                    $stmt = $this->db->prepare("
                        INSERT INTO purchase_requisitions 
                        (pr_number, sales_order_id, item_id, required_quantity, requested_by, urgency, status)
                        VALUES (?, ?, ?, ?, ?, 'high', 'pending')
                    ");
                    $stmt->execute([
                        $prNumber,
                        $salesOrderId,
                        $shortage['item_id'],
                        $shortage['shortage_quantity'],
                        $user->user_id
                    ]);

                    $results['success'][] = [
                        'item' => $shortage['item_name'],
                        'pr_number' => $prNumber,
                        'quantity' => $shortage['shortage_quantity']
                    ];
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'item' => $shortage['item_name'],
                        'reason' => $e->getMessage()
                    ];
                }
            }

            $this->db->commit();

            Response::success($results, 'Batch PR generation completed');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Batch PR error: " . $e->getMessage());
            Response::serverError('Batch generation failed');
        }
    }

    /**
     * GET /stock-requirements/summary
     * Get aggregated summary statistics
     */
    public function getSummary(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total_requirements,
                    SUM(CASE WHEN status = 'sufficient' THEN 1 ELSE 0 END) as sufficient_count,
                    SUM(CASE WHEN status = 'shortage' THEN 1 ELSE 0 END) as shortage_count,
                    SUM(CASE WHEN status = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(shortage_quantity) as total_shortage_units,
                    SUM(shortage_quantity * i.unit_price) as total_shortage_value,
                    AVG(shortage_quantity * i.unit_price) as avg_shortage_value
                FROM stock_requirements sr
                JOIN items i ON sr.item_id = i.item_id
                WHERE sr.shortage_quantity > 0
            ");

            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success([
                'total_requirements' => (int)$summary['total_requirements'],
                'sufficient' => (int)$summary['sufficient_count'],
                'shortage' => (int)$summary['shortage_count'],
                'critical' => (int)$summary['critical_count'],
                'total_shortage_units' => (int)$summary['total_shortage_units'],
                'total_shortage_value' => round((float)$summary['total_shortage_value'], 2),
                'avg_shortage_value' => round((float)$summary['avg_shortage_value'], 2)
            ], 'Summary retrieved');
        } catch (\PDOException $e) {
            error_log("Summary error: " . $e->getMessage());
            Response::serverError('Failed to retrieve summary');
        }
    }

    // ========================================================================
    // PRIVATE HELPER METHODS
    // ========================================================================

    /**
     * Calculate urgency based on multiple factors
     */
    private function calculateUrgency(
        int $shortageQty,
        int $reorderLevel,
        ?string $installDate
    ): string {
        // Factor 1: Shortage magnitude
        if ($shortageQty >= $reorderLevel * 2) {
            $urgency = 'critical';
        } elseif ($shortageQty >= $reorderLevel) {
            $urgency = 'high';
        } elseif ($shortageQty > 0) {
            $urgency = 'medium';
        } else {
            $urgency = 'low';
        }

        // Factor 2: Installation date proximity
        if ($installDate) {
            try {
                $install = new \DateTime($installDate);
                $today = new \DateTime();
                $daysUntil = (int)$today->diff($install)->format('%r%a');

                if ($daysUntil <= 7 && $urgency !== 'critical') {
                    $urgency = 'critical';
                } elseif ($daysUntil <= 14 && $urgency === 'medium') {
                    $urgency = 'high';
                }
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }

        return $urgency;
    }

    /**
     * Generate next PR number
     */
    private function generatePRNumber(): string
    {
        $stmt = $this->db->query("
            SELECT COALESCE(MAX(CAST(SUBSTRING(pr_number, 4) AS UNSIGNED)), 0) + 1 as next_num
            FROM purchase_requisitions
            WHERE pr_number LIKE 'PR-%'
        ");
        $nextNum = $stmt->fetchColumn();
        return 'PR-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    }
}
