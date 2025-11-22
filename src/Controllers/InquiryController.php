<?php

/**
 * JANSTRO IMS - Customer Inquiry Controller
 * Location: src/Controllers/InquiryController.php
 */

require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Utils/Response.php';

class InquiryController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * POST /inquiries - Submit public inquiry (NO AUTH REQUIRED)
     */
    public function submitInquiry()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['customer_name', 'email', 'phone', 'inquiry_type', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("Missing required field: $field", 400);
                return;
            }
        }

        // Validate email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', 400);
            return;
        }

        // Validate inquiry type
        $validTypes = ['installation', 'maintenance', 'pricing', 'technical', 'other'];
        if (!in_array($data['inquiry_type'], $validTypes)) {
            Response::error('Invalid inquiry type', 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Insert inquiry
            $stmt = $this->db->prepare("
                INSERT INTO customer_inquiries (
                    customer_name, email, phone, inquiry_type, 
                    message, status, priority, source
                ) VALUES (?, ?, ?, ?, ?, 'new', 'medium', 'website')
            ");

            $stmt->execute([
                $data['customer_name'],
                $data['email'],
                $data['phone'],
                $data['inquiry_type'],
                $data['message']
            ]);

            $inquiryId = $this->db->lastInsertId();

            // Add activity log
            $stmt = $this->db->prepare("
                INSERT INTO inquiry_activity (
                    inquiry_id, activity_type, description, created_by
                ) VALUES (?, 'created', 'Inquiry submitted via website', NULL)
            ");
            $stmt->execute([$inquiryId]);

            $this->db->commit();

            Response::success([
                'inquiry_id' => $inquiryId,
                'message' => 'Your inquiry has been submitted successfully!'
            ], 'Inquiry submitted successfully', 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Submit inquiry error: " . $e->getMessage());
            Response::error('Failed to submit inquiry', 500);
        }
    }

    /**
     * GET /inquiries - List all inquiries (STAFF ACCESS)
     */
    public function getAllInquiries()
    {
        if (!AuthMiddleware::checkAuth()) {
            Response::error('Authentication required', 401);
            return;
        }

        try {
            // Get filter parameters
            $status = $_GET['status'] ?? null;
            $priority = $_GET['priority'] ?? null;
            $type = $_GET['type'] ?? null;

            $sql = "
                SELECT 
                    i.*,
                    u.full_name as assigned_to_name,
                    (SELECT COUNT(*) FROM inquiry_activity WHERE inquiry_id = i.inquiry_id) as activity_count
                FROM customer_inquiries i
                LEFT JOIN users u ON i.assigned_to = u.user_id
                WHERE 1=1
            ";

            $params = [];

            if ($status) {
                $sql .= " AND i.status = ?";
                $params[] = $status;
            }
            if ($priority) {
                $sql .= " AND i.priority = ?";
                $params[] = $priority;
            }
            if ($type) {
                $sql .= " AND i.inquiry_type = ?";
                $params[] = $type;
            }

            $sql .= " ORDER BY i.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'inquiries' => $inquiries,
                'total' => count($inquiries)
            ], 'Inquiries retrieved successfully');
        } catch (PDOException $e) {
            error_log("Get inquiries error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiries', 500);
        }
    }

    /**
     * GET /inquiries/:id - Get inquiry details (STAFF ACCESS)
     */
    public function getInquiry($inquiryId)
    {
        if (!AuthMiddleware::checkAuth()) {
            Response::error('Authentication required', 401);
            return;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    i.*,
                    u.full_name as assigned_to_name,
                    u.email as assigned_to_email
                FROM customer_inquiries i
                LEFT JOIN users u ON i.assigned_to = u.user_id
                WHERE i.inquiry_id = ?
            ");

            $stmt->execute([$inquiryId]);
            $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inquiry) {
                Response::error('Inquiry not found', 404);
                return;
            }

            // Get activity history
            $stmt = $this->db->prepare("
                SELECT 
                    a.*,
                    u.full_name as created_by_name
                FROM inquiry_activity a
                LEFT JOIN users u ON a.created_by = u.user_id
                WHERE a.inquiry_id = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$inquiryId]);
            $inquiry['activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success($inquiry, 'Inquiry retrieved successfully');
        } catch (PDOException $e) {
            error_log("Get inquiry error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiry', 500);
        }
    }

    /**
     * PUT /inquiries/:id - Update inquiry (STAFF ACCESS)
     */
    public function updateInquiry($inquiryId)
    {
        if (!AuthMiddleware::checkAuth()) {
            Response::error('Authentication required', 401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->db->beginTransaction();

            // Build update query
            $updates = [];
            $params = [];
            $activityLog = [];

            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
                $activityLog[] = "Status changed to {$data['status']}";
            }
            if (isset($data['priority'])) {
                $updates[] = "priority = ?";
                $params[] = $data['priority'];
                $activityLog[] = "Priority changed to {$data['priority']}";
            }
            if (isset($data['assigned_to'])) {
                $updates[] = "assigned_to = ?";
                $params[] = $data['assigned_to'] ?: null;
                $activityLog[] = $data['assigned_to']
                    ? "Assigned to staff member"
                    : "Assignment removed";
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
                $activityLog[] = "Notes updated";
            }

            if (empty($updates)) {
                Response::error('No valid fields to update', 400);
                return;
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $inquiryId;

            $sql = "UPDATE customer_inquiries SET " . implode(', ', $updates) . " WHERE inquiry_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log activity
            if (!empty($activityLog)) {
                $stmt = $this->db->prepare("
                    INSERT INTO inquiry_activity (
                        inquiry_id, activity_type, description, created_by
                    ) VALUES (?, 'updated', ?, ?)
                ");
                $stmt->execute([
                    $inquiryId,
                    implode(', ', $activityLog),
                    $_SESSION['user_id']
                ]);
            }

            $this->db->commit();

            Response::success([
                'inquiry_id' => $inquiryId
            ], 'Inquiry updated successfully');
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Update inquiry error: " . $e->getMessage());
            Response::error('Failed to update inquiry', 500);
        }
    }

    /**
     * POST /inquiries/:id/convert - Convert inquiry to sales order
     */
    public function convertToSalesOrder($inquiryId)
    {
        if (!AuthMiddleware::checkAuth()) {
            Response::error('Authentication required', 401);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($data['item_id']) || empty($data['quantity'])) {
            Response::error('Item and quantity are required', 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Get inquiry details
            $stmt = $this->db->prepare("
                SELECT * FROM customer_inquiries WHERE inquiry_id = ?
            ");
            $stmt->execute([$inquiryId]);
            $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$inquiry) {
                Response::error('Inquiry not found', 404);
                return;
            }

            // Create sales order
            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_name, contact_number, delivery_address,
                    item_id, quantity, order_date, status, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, NOW(), 'pending', ?, ?)
            ");

            $stmt->execute([
                $inquiry['customer_name'],
                $inquiry['phone'],
                $data['delivery_address'] ?? 'To be confirmed',
                $data['item_id'],
                $data['quantity'],
                "Converted from inquiry #{$inquiryId}",
                $_SESSION['user_id']
            ]);

            $salesOrderId = $this->db->lastInsertId();

            // Update inquiry status
            $stmt = $this->db->prepare("
                UPDATE customer_inquiries 
                SET status = 'converted', updated_at = NOW()
                WHERE inquiry_id = ?
            ");
            $stmt->execute([$inquiryId]);

            // Log activity
            $stmt = $this->db->prepare("
                INSERT INTO inquiry_activity (
                    inquiry_id, activity_type, description, created_by
                ) VALUES (?, 'converted', ?, ?)
            ");
            $stmt->execute([
                $inquiryId,
                "Converted to sales order #{$salesOrderId}",
                $_SESSION['user_id']
            ]);

            $this->db->commit();

            Response::success([
                'inquiry_id' => $inquiryId,
                'sales_order_id' => $salesOrderId
            ], 'Inquiry converted to sales order successfully', 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Convert inquiry error: " . $e->getMessage());
            Response::error('Failed to convert inquiry', 500);
        }
    }

    /**
     * GET /inquiries/stats - Get inquiry statistics
     */
    public function getInquiryStats()
    {
        if (!AuthMiddleware::checkAuth()) {
            Response::error('Authentication required', 401);
            return;
        }

        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority
                FROM customer_inquiries
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success($stats, 'Inquiry statistics retrieved successfully');
        } catch (PDOException $e) {
            error_log("Get inquiry stats error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiry statistics', 500);
        }
    }
}
