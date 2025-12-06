<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;
use PDO;
use PDOException;

class InquiryController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * POST /inquiries - Submit public inquiry (NO AUTH)
     */
    public function submitInquiry(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $required = ['customer_name', 'email', 'phone', 'inquiry_type', 'message'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::error("Missing required field: $field", null, 400);
                return;
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            Response::error('Invalid email address', null, 400);
            return;
        }

        // Phone validation (Philippine format)
        $phone = trim($data['phone']);
        $cleanPhone = preg_replace('/[\s\-]/', '', $phone);

        if (!preg_match('/^(\+?639|09)\d{9}$/', $cleanPhone)) {
            Response::error(
                'Invalid Philippine phone number format. Expected: 09XXXXXXXXX or +639XXXXXXXXX',
                ['provided' => $phone, 'expected_format' => '09XXXXXXXXX or +639XXXXXXXXX'],
                400
            );
            return;
        }

        // Normalize phone
        if (strpos($cleanPhone, '+639') === 0) {
            $normalizedPhone = '0' . substr($cleanPhone, 3);
        } elseif (strpos($cleanPhone, '639') === 0) {
            $normalizedPhone = '0' . substr($cleanPhone, 2);
        } else {
            $normalizedPhone = $cleanPhone;
        }

        $validTypes = ['installation', 'maintenance', 'pricing', 'technical', 'other'];
        if (!in_array($data['inquiry_type'], $validTypes)) {
            Response::error('Invalid inquiry type', ['valid_types' => $validTypes], 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO customer_inquiries (
                    customer_name, email, phone, inquiry_type, 
                    message, status, priority, source
                ) VALUES (?, ?, ?, ?, ?, 'new', 'medium', 'website')
            ");
            $stmt->execute([
                $data['customer_name'],
                $data['email'],
                $normalizedPhone,
                $data['inquiry_type'],
                $data['message']
            ]);

            $inquiryId = (int)$this->db->lastInsertId();

            // Auto-create or update customer
            $stmt = $this->db->prepare("
                INSERT INTO customers (
                    customer_name, contact_number, email, customer_type, 
                    source, first_inquiry_date, total_inquiries
                ) VALUES (?, ?, ?, 'individual', 'web_inquiry', NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    last_activity_date = NOW(),
                    total_inquiries = total_inquiries + 1
            ");
            $stmt->execute([$data['customer_name'], $normalizedPhone, $data['email']]);

            $stmt = $this->db->prepare("
                SELECT customer_id FROM customers 
                WHERE email = ? OR contact_number = ? LIMIT 1
            ");
            $stmt->execute([$data['email'], $normalizedPhone]);
            $customer = $stmt->fetch();
            $customerId = $customer['customer_id'] ?? null;

            if (!$customerId) {
                throw new \Exception("Failed to create or find customer record");
            }

            $stmt = $this->db->prepare("
                UPDATE customer_inquiries SET customer_id = ? WHERE inquiry_id = ?
            ");
            $stmt->execute([$customerId, $inquiryId]);

            $stmt = $this->db->prepare("
                INSERT INTO inquiry_activity (inquiry_id, activity_type, description, created_by)
                VALUES (?, 'created', 'Inquiry submitted via website', NULL)
            ");
            $stmt->execute([$inquiryId]);

            $this->db->commit();

            \Janstro\InventorySystem\Services\NotificationService::sendInquiryNotification([
                'inquiry_id' => $inquiryId,
                'customer_name' => $data['customer_name'],
                'email' => $data['email'],
                'phone' => $normalizedPhone,
                'inquiry_type' => $data['inquiry_type'],
                'message' => $data['message']
            ]);

            Response::success([
                'inquiry_id' => $inquiryId,
                'message' => 'Your inquiry has been submitted successfully!',
                'reference' => 'INQ-' . str_pad($inquiryId, 6, '0', STR_PAD_LEFT)
            ], 'Inquiry submitted', 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Submit inquiry error: " . $e->getMessage());
            Response::error('Failed to submit inquiry', null, 500);
        }
    }

    /**
     * GET /inquiries - Get all inquiries (AUTHENTICATED)
     */
    public function getAllInquiries(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $status = $_GET['status'] ?? null;
            $priority = $_GET['priority'] ?? null;
            $type = $_GET['type'] ?? null;

            $sql = "SELECT i.*, u.name as assigned_to_name
                    FROM customer_inquiries i
                    LEFT JOIN users u ON i.assigned_to = u.user_id
                    WHERE 1=1";
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
            $inquiries = $stmt->fetchAll();

            Response::success(['inquiries' => $inquiries, 'total' => count($inquiries)], 'Inquiries retrieved');
        } catch (PDOException $e) {
            error_log("Get inquiries error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiries', null, 500);
        }
    }

    /**
     * GET /inquiries/:id - Get specific inquiry (AUTHENTICATED)
     */
    public function getInquiry(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT i.*, u.name as assigned_to_name
                FROM customer_inquiries i
                LEFT JOIN users u ON i.assigned_to = u.user_id
                WHERE i.inquiry_id = ?
            ");
            $stmt->execute([$inquiryId]);
            $inquiry = $stmt->fetch();

            if (!$inquiry) {
                Response::notFound('Inquiry not found');
                return;
            }

            $stmt = $this->db->prepare("
                SELECT a.*, u.name as created_by_name
                FROM inquiry_activity a
                LEFT JOIN users u ON a.created_by = u.user_id
                WHERE a.inquiry_id = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$inquiryId]);
            $inquiry['activity'] = $stmt->fetchAll();

            Response::success($inquiry, 'Inquiry retrieved');
        } catch (PDOException $e) {
            error_log("Get inquiry error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiry', null, 500);
        }
    }

    /**
     * PUT /inquiries/:id - Update inquiry (STAFF/ADMIN)
     */
    public function updateInquiry(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $this->db->beginTransaction();

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
                $activityLog[] = $data['assigned_to'] ? "Assigned to staff" : "Assignment removed";
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
                $activityLog[] = "Notes updated";
            }

            if (empty($updates)) {
                Response::error('No valid fields to update', null, 400);
                return;
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $inquiryId;

            $sql = "UPDATE customer_inquiries SET " . implode(', ', $updates) . " WHERE inquiry_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if (!empty($activityLog)) {
                $stmt = $this->db->prepare("
                    INSERT INTO inquiry_activity (inquiry_id, activity_type, description, created_by)
                    VALUES (?, 'updated', ?, ?)
                ");
                $stmt->execute([$inquiryId, implode(', ', $activityLog), $user->user_id]);
            }

            $this->db->commit();
            Response::success(['inquiry_id' => $inquiryId], 'Inquiry updated');
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Update inquiry error: " . $e->getMessage());
            Response::error('Failed to update inquiry', null, 500);
        }
    }

    /**
     * POST /inquiries/:id/convert - Convert to sales order (ADMIN ONLY)
     */
    public function convertToSalesOrder(int $inquiryId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['item_id']) || empty($data['quantity'])) {
            Response::error('Item and quantity are required', null, 400);
            return;
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM customer_inquiries WHERE inquiry_id = ?");
            $stmt->execute([$inquiryId]);
            $inquiry = $stmt->fetch();

            if (!$inquiry) {
                Response::notFound('Inquiry not found');
                return;
            }

            $stmt = $this->db->prepare("SELECT unit_price FROM items WHERE item_id = ?");
            $stmt->execute([$data['item_id']]);
            $item = $stmt->fetch();
            $totalAmount = ($item['unit_price'] ?? 0) * $data['quantity'];

            $stmt = $this->db->prepare("
                INSERT INTO sales_orders (
                    customer_name, contact_number, delivery_address,
                    total_amount, status, notes, created_by
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            $stmt->execute([
                $inquiry['customer_name'],
                $inquiry['phone'],
                $data['delivery_address'] ?? 'To be confirmed',
                $totalAmount,
                "Converted from inquiry #{$inquiryId}",
                $user->user_id
            ]);

            $salesOrderId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                INSERT INTO sales_order_items (sales_order_id, item_id, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$salesOrderId, $data['item_id'], $data['quantity'], $item['unit_price'] ?? 0, $totalAmount]);

            $stmt = $this->db->prepare("UPDATE customer_inquiries SET status = 'converted', updated_at = NOW() WHERE inquiry_id = ?");
            $stmt->execute([$inquiryId]);

            $stmt = $this->db->prepare("
                INSERT INTO inquiry_activity (inquiry_id, activity_type, description, created_by)
                VALUES (?, 'converted', ?, ?)
            ");
            $stmt->execute([$inquiryId, "Converted to SO #{$salesOrderId}", $user->user_id]);

            $this->db->commit();

            Response::success([
                'inquiry_id' => $inquiryId,
                'sales_order_id' => $salesOrderId
            ], 'Converted to sales order', 201);
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Convert inquiry error: " . $e->getMessage());
            Response::error('Failed to convert inquiry', null, 500);
        }
    }

    /**
     * DELETE /inquiries/:id - Delete inquiry (ADMIN ONLY)
     */
    public function deleteInquiry(int $inquiryId): void
    {
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("DELETE FROM customer_inquiries WHERE inquiry_id = ?");
            $stmt->execute([$inquiryId]);

            if ($stmt->rowCount() === 0) {
                Response::notFound('Inquiry not found');
                return;
            }

            Response::success(null, 'Inquiry deleted');
        } catch (PDOException $e) {
            error_log("Delete inquiry error: " . $e->getMessage());
            Response::error('Failed to delete inquiry', null, 500);
        }
    }
}
