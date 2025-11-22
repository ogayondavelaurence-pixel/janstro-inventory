<?php

/**
 * JANSTRO INVENTORY SYSTEM - Customer Inquiry Controller
 * Location: src/Controllers/InquiryController.php
 * 
 * FEATURES:
 * - Public inquiry submission (no auth required)
 * - Staff inquiry management (auth required)
 * - Auto stock availability check
 * - Convert inquiry to sales order
 * - Notes and workflow tracking
 */

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

class InquiryController
{
    private $db;
    private $conn;

    public function __construct()
    {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    /**
     * PUBLIC ENDPOINT: Submit Customer Inquiry
     * POST /inquiries
     * No authentication required
     */
    public function create()
    {
        try {
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['customer_name', 'phone', 'address', 'item_name'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    Response::error("Field '$field' is required", 400);
                    return;
                }
            }

            // Check stock availability automatically
            $stockStatus = $this->checkStockAvailability($input['item_name']);

            // Insert inquiry
            $sql = "INSERT INTO customer_inquiries 
                    (customer_name, phone, email, address, item_name, quantity, 
                     installation_date, budget_range, notes, stock_availability) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $input['customer_name'],
                $input['phone'],
                $input['email'] ?? null,
                $input['address'],
                $input['item_name'],
                $input['quantity'] ?? 1,
                $input['installation_date'] ?? null,
                $input['budget_range'] ?? null,
                $input['notes'] ?? null,
                $stockStatus
            ]);

            $inquiryId = $this->conn->lastInsertId();

            // Log workflow
            $this->logWorkflow($inquiryId, null, 'new', 0, 'Inquiry submitted by customer');

            Response::success([
                'inquiry_id' => $inquiryId,
                'status' => 'new',
                'stock_availability' => $stockStatus,
                'message' => 'Inquiry submitted successfully! Our team will contact you soon.'
            ], 201);
        } catch (\PDOException $e) {
            error_log("Inquiry Create Error: " . $e->getMessage());
            Response::error("Failed to submit inquiry: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Get All Inquiries
     * GET /inquiries
     */
    public function getAll()
    {
        try {
            // Authenticate user
            $user = AuthMiddleware::authenticate();

            // Get filters
            $status = $_GET['status'] ?? null;
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);

            // Build query
            $sql = "SELECT 
                        ci.*,
                        u.username as assigned_to_name,
                        COUNT(DISTINCT in2.note_id) as note_count
                    FROM customer_inquiries ci
                    LEFT JOIN users u ON ci.assigned_to = u.user_id
                    LEFT JOIN inquiry_notes in2 ON ci.inquiry_id = in2.inquiry_id
                    WHERE 1=1";

            $params = [];

            if ($status) {
                $sql .= " AND ci.status = ?";
                $params[] = $status;
            }

            $sql .= " GROUP BY ci.inquiry_id
                      ORDER BY 
                        CASE ci.status 
                            WHEN 'new' THEN 1
                            WHEN 'processing' THEN 2
                            WHEN 'quoted' THEN 3
                            ELSE 4
                        END,
                        ci.created_at DESC
                      LIMIT ? OFFSET ?";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $inquiries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM customer_inquiries WHERE 1=1";
            if ($status) {
                $countSql .= " AND status = ?";
                $countParams = [$status];
            } else {
                $countParams = [];
            }
            $countStmt = $this->conn->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

            Response::success([
                'inquiries' => $inquiries,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Get Inquiries Error: " . $e->getMessage());
            Response::error("Failed to fetch inquiries: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Get Inquiry by ID
     * GET /inquiries/{id}
     */
    public function getById($id)
    {
        try {
            $user = AuthMiddleware::authenticate();

            // Get inquiry details
            $sql = "SELECT 
                        ci.*,
                        u.username as assigned_to_name,
                        so.sales_order_id as sales_order_number
                    FROM customer_inquiries ci
                    LEFT JOIN users u ON ci.assigned_to = u.user_id
                    LEFT JOIN sales_orders so ON ci.converted_to_so = so.sales_order_id
                    WHERE ci.inquiry_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$inquiry) {
                Response::error("Inquiry not found", 404);
                return;
            }

            // Get notes
            $notesSql = "SELECT in2.*, u.username 
                         FROM inquiry_notes in2
                         JOIN users u ON in2.user_id = u.user_id
                         WHERE in2.inquiry_id = ?
                         ORDER BY in2.created_at DESC";
            $notesStmt = $this->conn->prepare($notesSql);
            $notesStmt->execute([$id]);
            $notes = $notesStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get workflow history
            $workflowSql = "SELECT iw.*, u.username 
                            FROM inquiry_workflow iw
                            JOIN users u ON iw.changed_by = u.user_id
                            WHERE iw.inquiry_id = ?
                            ORDER BY iw.created_at DESC";
            $workflowStmt = $this->conn->prepare($workflowSql);
            $workflowStmt->execute([$id]);
            $workflow = $workflowStmt->fetchAll(\PDO::FETCH_ASSOC);

            $inquiry['notes'] = $notes;
            $inquiry['workflow'] = $workflow;

            Response::success($inquiry);
        } catch (\Exception $e) {
            error_log("Get Inquiry Error: " . $e->getMessage());
            Response::error("Failed to fetch inquiry: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Update Inquiry Status
     * PUT /inquiries/{id}/status
     */
    public function updateStatus($id)
    {
        try {
            $user = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['status'])) {
                Response::error("Status is required", 400);
                return;
            }

            // Get current status
            $currentSql = "SELECT status FROM customer_inquiries WHERE inquiry_id = ?";
            $currentStmt = $this->conn->prepare($currentSql);
            $currentStmt->execute([$id]);
            $current = $currentStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$current) {
                Response::error("Inquiry not found", 404);
                return;
            }

            // Update status
            $sql = "UPDATE customer_inquiries 
                    SET status = ?, 
                        assigned_to = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE inquiry_id = ?";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $input['status'],
                $input['assigned_to'] ?? $user['user_id'],
                $id
            ]);

            // Log workflow
            $this->logWorkflow(
                $id,
                $current['status'],
                $input['status'],
                $user['user_id'],
                $input['notes'] ?? null
            );

            Response::success([
                'message' => 'Status updated successfully',
                'new_status' => $input['status']
            ]);
        } catch (\Exception $e) {
            error_log("Update Status Error: " . $e->getMessage());
            Response::error("Failed to update status: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Convert Inquiry to Sales Order
     * POST /inquiries/{id}/convert
     */
    public function convertToSalesOrder($id)
    {
        try {
            $user = AuthMiddleware::authenticate();

            // Check if user is admin
            if ($user['role'] !== 'admin') {
                Response::error("Only admins can convert inquiries to sales orders", 403);
                return;
            }

            // Get inquiry details
            $sql = "SELECT * FROM customer_inquiries WHERE inquiry_id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id]);
            $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$inquiry) {
                Response::error("Inquiry not found", 404);
                return;
            }

            if ($inquiry['status'] === 'converted') {
                Response::error("Inquiry already converted", 400);
                return;
            }

            // Check if item exists in inventory
            $itemSql = "SELECT item_id FROM inventory WHERE item_name LIKE ?";
            $itemStmt = $this->conn->prepare($itemSql);
            $itemStmt->execute(['%' . $inquiry['item_name'] . '%']);
            $item = $itemStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Response::error("Item not found in inventory", 404);
                return;
            }

            // Create sales order
            $soSql = "INSERT INTO sales_orders 
                      (customer_name, contact_number, delivery_address, item_id, quantity, notes, created_by, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $soStmt = $this->conn->prepare($soSql);
            $soStmt->execute([
                $inquiry['customer_name'],
                $inquiry['phone'],
                $inquiry['address'],
                $item['item_id'],
                $inquiry['quantity'],
                'Converted from Inquiry #' . $id . '. ' . ($inquiry['notes'] ?? ''),
                $user['user_id']
            ]);

            $salesOrderId = $this->conn->lastInsertId();

            // Update inquiry
            $updateSql = "UPDATE customer_inquiries 
                          SET status = 'converted', 
                              converted_to_so = ?,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE inquiry_id = ?";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([$salesOrderId, $id]);

            // Log workflow
            $this->logWorkflow(
                $id,
                $inquiry['status'],
                'converted',
                $user['user_id'],
                "Converted to Sales Order #$salesOrderId"
            );

            Response::success([
                'message' => 'Inquiry converted to sales order successfully',
                'sales_order_id' => $salesOrderId
            ]);
        } catch (\Exception $e) {
            error_log("Convert Inquiry Error: " . $e->getMessage());
            Response::error("Failed to convert inquiry: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Add Note to Inquiry
     * POST /inquiries/{id}/notes
     */
    public function addNote($id)
    {
        try {
            $user = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['note_text'])) {
                Response::error("Note text is required", 400);
                return;
            }

            $sql = "INSERT INTO inquiry_notes (inquiry_id, user_id, note_text) 
                    VALUES (?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$id, $user['user_id'], $input['note_text']]);

            Response::success([
                'message' => 'Note added successfully',
                'note_id' => $this->conn->lastInsertId()
            ], 201);
        } catch (\Exception $e) {
            error_log("Add Note Error: " . $e->getMessage());
            Response::error("Failed to add note: " . $e->getMessage(), 500);
        }
    }

    /**
     * STAFF ENDPOINT: Get Inquiry Statistics
     * GET /inquiries/stats
     */
    public function getStatistics()
    {
        try {
            $user = AuthMiddleware::authenticate();

            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
                        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
                        SUM(CASE WHEN status = 'quoted' THEN 1 ELSE 0 END) as quoted_count,
                        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted_count,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                        SUM(CASE WHEN stock_availability = 'available' THEN 1 ELSE 0 END) as stock_available,
                        SUM(CASE WHEN stock_availability = 'insufficient' THEN 1 ELSE 0 END) as stock_insufficient
                    FROM customer_inquiries";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            Response::success($stats);
        } catch (\Exception $e) {
            error_log("Get Stats Error: " . $e->getMessage());
            Response::error("Failed to fetch statistics: " . $e->getMessage(), 500);
        }
    }

    /**
     * Helper: Check Stock Availability
     */
    private function checkStockAvailability($itemName)
    {
        try {
            $sql = "SELECT quantity_on_hand, reorder_level 
                    FROM inventory 
                    WHERE item_name LIKE ? 
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['%' . $itemName . '%']);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                return 'checking'; // Item not in inventory yet
            }

            if ($item['quantity_on_hand'] > $item['reorder_level']) {
                return 'available';
            } else {
                return 'insufficient';
            }
        } catch (\Exception $e) {
            return 'checking';
        }
    }

    /**
     * Helper: Log Workflow Changes
     */
    private function logWorkflow($inquiryId, $fromStatus, $toStatus, $userId, $notes = null)
    {
        try {
            $sql = "INSERT INTO inquiry_workflow 
                    (inquiry_id, from_status, to_status, changed_by, notes) 
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$inquiryId, $fromStatus, $toStatus, $userId, $notes]);
        } catch (\Exception $e) {
            error_log("Workflow Log Error: " . $e->getMessage());
        }
    }
}
