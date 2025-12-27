<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * ORDER CONTROLLER v2.0 (ENHANCED)
 * ============================================================================
 * Manages Purchase Orders with full CRUD and state management
 * ============================================================================
 */
class OrderController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /api/purchase-orders
     * Get all purchase orders
     */
    public function getAll(): void
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
                    i.sku,
                    po.quantity,
                    po.unit_price,
                    po.total_amount,
                    po.expected_delivery_date,
                    po.status,
                    po.notes,
                    po.po_date,
                    po.delivered_date,
                    po.created_by,
                    u.name as created_by_name
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                LEFT JOIN items i ON po.item_id = i.item_id
                LEFT JOIN users u ON po.created_by = u.user_id
                ORDER BY po.po_date DESC
            ");

            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($orders ?: [], 'Purchase orders retrieved successfully');
        } catch (\Exception $e) {
            error_log("OrderController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve orders');
        }
    }

    /**
     * GET /api/purchase-orders/{id}
     * Get purchase order by ID
     */
    public function getById(int $id): void
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
                    s.address as supplier_address,
                    po.item_id,
                    i.item_name,
                    i.sku,
                    i.unit,
                    po.quantity,
                    po.unit_price,
                    po.total_amount,
                    po.expected_delivery_date,
                    po.status,
                    po.notes,
                    po.po_date,
                    po.delivered_date,
                    po.created_by,
                    u.name as created_by_name,
                    u.email as created_by_email
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

            Response::success($order, 'Purchase order retrieved successfully');
        } catch (\Exception $e) {
            error_log("OrderController::getById - " . $e->getMessage());
            Response::serverError('Failed to retrieve order');
        }
    }

    /**
     * POST /api/purchase-orders
     * Create new purchase order
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['supplier_id', 'item_id', 'quantity'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            // Validate quantity
            if ($data['quantity'] <= 0) {
                Response::badRequest('Quantity must be greater than 0');
                return;
            }

            // Start transaction
            $this->db->beginTransaction();

            try {
                // Validate supplier exists
                $stmt = $this->db->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_id = ? AND status = 'active'");
                $stmt->execute([$data['supplier_id']]);
                $supplier = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$supplier) {
                    throw new \Exception('Invalid or inactive supplier');
                }

                // Validate item exists
                $stmt = $this->db->prepare("SELECT item_id, item_name, unit_price FROM items WHERE item_id = ? AND status = 'active'");
                $stmt->execute([$data['item_id']]);
                $item = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new \Exception('Invalid or inactive item');
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

                $poId = (int)$this->db->lastInsertId();

                // Create audit log
                $this->createAuditLog(
                    $user->user_id,
                    "Created Purchase Order #{$poId}: {$item['item_name']} x {$data['quantity']} from {$supplier['supplier_name']}",
                    'purchase_orders',
                    'create'
                );

                $this->db->commit();

                Response::success(
                    ['po_id' => $poId],
                    'Purchase order created successfully',
                    201
                );
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("OrderController::create - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/purchase-orders/{id}
     * Update purchase order (only if pending)
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if PO exists and is pending
            $stmt = $this->db->prepare("
                SELECT po_id, status, item_id
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
                Response::badRequest('Can only update pending orders');
                return;
            }

            // Build update query
            $updates = [];
            $values = [];

            if (isset($data['supplier_id'])) {
                $updates[] = "supplier_id = ?";
                $values[] = $data['supplier_id'];
            }

            if (isset($data['item_id'])) {
                $updates[] = "item_id = ?";
                $values[] = $data['item_id'];
            }

            if (isset($data['quantity']) && $data['quantity'] > 0) {
                $updates[] = "quantity = ?";
                $values[] = $data['quantity'];
            }

            if (isset($data['unit_price'])) {
                $updates[] = "unit_price = ?";
                $values[] = $data['unit_price'];
            }

            if (isset($data['expected_delivery_date'])) {
                $updates[] = "expected_delivery_date = ?";
                $values[] = $data['expected_delivery_date'];
            }

            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $values[] = $data['notes'];
            }

            // Recalculate total if quantity or price changed
            if (isset($data['quantity']) || isset($data['unit_price'])) {
                $stmt = $this->db->prepare("SELECT quantity, unit_price FROM purchase_orders WHERE po_id = ?");
                $stmt->execute([$id]);
                $current = $stmt->fetch(\PDO::FETCH_ASSOC);

                $newQuantity = $data['quantity'] ?? $current['quantity'];
                $newPrice = $data['unit_price'] ?? $current['unit_price'];
                $newTotal = $newQuantity * $newPrice;

                $updates[] = "total_amount = ?";
                $values[] = $newTotal;
            }

            if (empty($updates)) {
                Response::badRequest('No fields to update');
                return;
            }

            $values[] = $id;
            $sql = "UPDATE purchase_orders SET " . implode(', ', $updates) . " WHERE po_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->createAuditLog(
                $user->user_id,
                "Updated Purchase Order #{$id}",
                'purchase_orders',
                'update'
            );

            Response::success(null, 'Purchase order updated successfully');
        } catch (\Exception $e) {
            error_log("OrderController::update - " . $e->getMessage());
            Response::serverError('Failed to update order');
        }
    }

    /**
     * POST /api/purchase-orders/{id}/approve
     * Approve purchase order (ADMIN only)
     */
    public function approve(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT po_id, status, item_id, quantity
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

            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = 'approved' 
                WHERE po_id = ?
            ");
            $stmt->execute([$id]);

            $this->createAuditLog(
                $user->user_id,
                "Approved Purchase Order #{$id}",
                'purchase_orders',
                'approve'
            );

            Response::success(null, 'Purchase order approved successfully');
        } catch (\Exception $e) {
            error_log("OrderController::approve - " . $e->getMessage());
            Response::serverError('Failed to approve order');
        }
    }

    /**
     * POST /api/purchase-orders/{id}/deliver
     * Mark purchase order as delivered and update stock
     */
    public function markDelivered(int $id): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin', 'staff']);
        if (!$user) return;

        try {
            // Start transaction
            $this->db->beginTransaction();

            try {
                // Get PO details
                $stmt = $this->db->prepare("
                    SELECT po_id, status, item_id, quantity, unit_price
                    FROM purchase_orders 
                    WHERE po_id = ?
                ");
                $stmt->execute([$id]);
                $po = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$po) {
                    throw new \Exception('Purchase order not found');
                }

                if ($po['status'] === 'delivered') {
                    throw new \Exception('Purchase order already delivered');
                }

                if ($po['status'] === 'cancelled') {
                    throw new \Exception('Cannot deliver cancelled order');
                }

                // Update PO status
                $stmt = $this->db->prepare("
                    UPDATE purchase_orders 
                    SET status = 'delivered', delivered_date = NOW()
                    WHERE po_id = ?
                ");
                $stmt->execute([$id]);

                // Get current stock
                $stmt = $this->db->prepare("SELECT quantity, item_name FROM items WHERE item_id = ?");
                $stmt->execute([$po['item_id']]);
                $item = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$item) {
                    throw new \Exception('Item not found');
                }

                $previousQuantity = $item['quantity'];
                $newQuantity = $previousQuantity + $po['quantity'];

                // Update stock
                $stmt = $this->db->prepare("
                    UPDATE items 
                    SET quantity = ?, updated_at = NOW()
                    WHERE item_id = ?
                ");
                $stmt->execute([$newQuantity, $po['item_id']]);

                // Record transaction
                $stmt = $this->db->prepare("
                    INSERT INTO transactions (
                        item_id, 
                        user_id, 
                        transaction_type, 
                        quantity, 
                        unit_price, 
                        reference_type, 
                        reference_number,
                        notes,
                        previous_quantity,
                        new_quantity
                    ) VALUES (?, ?, 'IN', ?, ?, 'PURCHASE_ORDER', ?, 'Goods received from PO', ?, ?)
                ");
                $stmt->execute([
                    $po['item_id'],
                    $user->user_id,
                    $po['quantity'],
                    $po['unit_price'],
                    "PO-{$id}",
                    $previousQuantity,
                    $newQuantity
                ]);

                // Audit log
                $this->createAuditLog(
                    $user->user_id,
                    "Marked PO #{$id} as delivered - Updated stock for {$item['item_name']} ({$previousQuantity} → {$newQuantity})",
                    'purchase_orders',
                    'deliver'
                );

                $this->db->commit();

                Response::success([
                    'previous_stock' => $previousQuantity,
                    'received_quantity' => $po['quantity'],
                    'new_stock' => $newQuantity
                ], 'Purchase order marked as delivered and stock updated');
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("OrderController::markDelivered - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * POST /api/purchase-orders/{id}/cancel
     * Cancel purchase order
     */
    public function cancel(int $id): void
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

            if ($po['status'] === 'cancelled') {
                Response::badRequest('Purchase order already cancelled');
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
                "Cancelled Purchase Order #{$id}",
                'purchase_orders',
                'cancel'
            );

            Response::success(null, 'Purchase order cancelled successfully');
        } catch (\Exception $e) {
            error_log("OrderController::cancel - " . $e->getMessage());
            Response::serverError('Failed to cancel order');
        }
    }

    /**
     * GET /api/purchase-orders/{id}/pdf
     * Generate and download PO PDF
     * ✅ FIXED: Accept token from query parameter
     */
    public function downloadPDF(int $id): void
    {
        // ✅ FIX: Read token from query param FIRST
        $token = $_GET['token'] ?? null;

        if ($token) {
            // Set it in the expected header location
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        // Now authenticate (will find token in header)
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            // Get PO data with supplier and item
            $stmt = $this->db->prepare("
            SELECT po.*, 
                   s.supplier_name, s.contact_person, s.phone, s.email, s.address,
                   i.item_name, i.sku, i.unit
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            LEFT JOIN items i ON po.item_id = i.item_id
            WHERE po.po_id = ?
        ");
            $stmt->execute([$id]);
            $po = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$po) {
                Response::notFound('Purchase order not found');
                return;
            }

            // Generate PDF
            $pdfService = new \Janstro\InventorySystem\Services\PdfService();

            $supplier = [
                'supplier_name' => $po['supplier_name'],
                'contact_person' => $po['contact_person'],
                'phone' => $po['phone'],
                'email' => $po['email'],
                'address' => $po['address']
            ];

            $item = [
                'item_name' => $po['item_name'],
                'sku' => $po['sku'],
                'unit' => $po['unit']
            ];

            $pdfPath = $pdfService->generatePurchaseOrderPDF($po, $supplier, $item);
            $absolutePath = $pdfService->getAbsolutePath($pdfPath);

            if (!file_exists($absolutePath)) {
                Response::notFound('PDF file not found');
                return;
            }

            $poNumber = 'PO-' . str_pad($id, 6, '0', STR_PAD_LEFT);

            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $poNumber . '.pdf"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            readfile($absolutePath);
            exit;
        } catch (\Exception $e) {
            error_log("PO PDF generation error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Response::serverError('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Create audit log
     */
    private function createAuditLog(
        int $userId,
        string $description,
        string $module,
        string $actionType
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $description,
                $module,
                $actionType,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
