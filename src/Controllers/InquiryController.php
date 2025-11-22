<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Customer Inquiry Controller
 * Handles customer inquiry submissions and staff management
 * 
 * @version 1.0.0
 * @date 2025-11-22
 */
class InquiryController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * POST /inquiries
     * Submit new customer inquiry (PUBLIC - no auth required)
     */
    public function create(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $required = ['customer_name', 'phone', 'address', 'item_name', 'quantity'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    Response::error("Missing required field: $field", null, 400);
                    return;
                }
            }

            // Sanitize inputs
            $data = [
                'customer_name' => trim($input['customer_name']),
                'phone' => trim($input['phone']),
                'email' => isset($input['email']) ? trim($input['email']) : null,
                'address' => trim($input['address']),
                'item_name' => trim($input['item_name']),
                'quantity' => (int)$input['quantity'],
                'installation_date' => $input['installation_date'] ?? null,
                'budget_range' => $input['budget_range'] ?? null,
                'notes' => $input['notes'] ?? null
            ];

            // Validate phone format (Philippine)
            if (!preg_match('/^(09|\+639)\d{9}$/', $data['phone'])) {
                Response::error('Invalid phone number format. Use 09XXXXXXXXX', null, 400);
                return;
            }

            // Validate email if provided
            if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', null, 400);
                return;
            }

            // Check if customer exists (for returning customers)
            $stmt = $this->db->prepare("
                SELECT customer_id, customer_name, total_inquiries
                FROM customers
                WHERE contact_number = ? OR email = ?
                LIMIT 1
            ");
            $stmt->execute([$data['phone'], $data['email']]);
            $existingCustomer = $stmt->fetch();

            // Insert inquiry
            $stmt = $this->db->prepare("
                INSERT INTO customer_inquiries (
                    customer_name, phone, email, address, item_name, quantity,
                    installation_date, budget_range, notes, status, stock_availability
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'checking')
            ");

            $stmt->execute([
                $data['customer_name'],
                $data['phone'],
                $data['email'],
                $data['address'],
                $data['item_name'],
                $data['quantity'],
                $data['installation_date'],
                $data['budget_range'],
                $data['notes']
            ]);

            $inquiryId = (int)$this->db->lastInsertId();

            // Check stock availability immediately
            $stockCheck = $this->checkStockAvailability($data['item_name'], $data['quantity']);

            // Update stock availability status
            $stmt = $this->db->prepare("
                UPDATE customer_inquiries 
                SET stock_availability = ? 
                WHERE inquiry_id = ?
            ");
            $stmt->execute([$stockCheck['status'], $inquiryId]);

            // Create or update customer master data
            if (!$existingCustomer) {
                // Create new customer
                $stmt = $this->db->prepare("
                    INSERT INTO customers (
                        customer_name, contact_number, email, address,
                        source, first_inquiry_date, last_activity_date, total_inquiries
                    ) VALUES (?, ?, ?, ?, 'web_inquiry', NOW(), NOW(), 1)
                ");
                $stmt->execute([
                    $data['customer_name'],
                    $data['phone'],
                    $data['email'],
                    $data['address']
                ]);
            } else {
                // Update existing customer
                $stmt = $this->db->prepare("
                    UPDATE customers 
                    SET total_inquiries = total_inquiries + 1,
                        last_activity_date = NOW()
                    WHERE contact_number = ? OR email = ?
                ");
                $stmt->execute([$data['phone'], $data['email']]);
            }

            Response::success([
                'inquiry_id' => $inquiryId,
                'reference_number' => 'INQ-' . str_pad($inquiryId, 8, '0', STR_PAD_LEFT),
                'stock_status' => $stockCheck['status'],
                'estimated_response_time' => '24 hours',
                'customer_type' => $existingCustomer ? 'returning' : 'new'
            ], 'Inquiry submitted successfully. We will contact you within 24 hours.', 201);
        } catch (\Exception $e) {
            error_log("InquiryController::create Error: " . $e->getMessage());
            Response::error('Failed to submit inquiry', null, 500);
        }
    }

    /**
     * GET /inquiries
     * Get all inquiries (STAFF/ADMIN only)
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

            $sql = "
                SELECT 
                    ci.*,
                    u.name AS assigned_staff_name,
                    DATEDIFF(CURDATE(), DATE(ci.created_at)) AS days_old
                FROM customer_inquiries ci
                LEFT JOIN users u ON ci.assigned_to = u.user_id
            ";

            if ($status) {
                $sql .= " WHERE ci.status = ?";
                $params = [$status];
            } else {
                $params = [];
            }

            $sql .= " ORDER BY 
                CASE ci.status
                    WHEN 'new' THEN 1
                    WHEN 'processing' THEN 2
                    WHEN 'quoted' THEN 3
                    WHEN 'converted' THEN 4
                    ELSE 5
                END,
                ci.created_at DESC
                LIMIT ?";

            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $inquiries = $stmt->fetchAll();

            Response::success([
                'inquiries' => $inquiries,
                'total' => count($inquiries),
                'filters' => [
                    'status' => $status,
                    'limit' => $limit
                ]
            ], 'Inquiries retrieved successfully');
        } catch (\Exception $e) {
            error_log("InquiryController::getAll Error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiries', null, 500);
        }
    }

    /**
     * GET /inquiries/{id}
     * Get single inquiry details
     */
    public function getById(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ci.*,
                    u.name AS assigned_staff_name,
                    u.email AS assigned_staff_email,
                    so.sales_order_id,
                    so.status AS sales_order_status,
                    DATEDIFF(CURDATE(), DATE(ci.created_at)) AS days_old
                FROM customer_inquiries ci
                LEFT JOIN users u ON ci.assigned_to = u.user_id
                LEFT JOIN sales_orders so ON ci.converted_to_so = so.sales_order_id
                WHERE ci.inquiry_id = ?
            ");
            $stmt->execute([$inquiryId]);

            $inquiry = $stmt->fetch();

            if (!$inquiry) {
                Response::notFound('Inquiry not found');
                return;
            }

            // Get inquiry notes
            $stmt = $this->db->prepare("
                SELECT n.*, u.name AS author_name
                FROM inquiry_notes n
                LEFT JOIN users u ON n.user_id = u.user_id
                WHERE n.inquiry_id = ?
                ORDER BY n.created_at DESC
            ");
            $stmt->execute([$inquiryId]);
            $inquiry['notes_history'] = $stmt->fetchAll();

            // Get workflow history
            $stmt = $this->db->prepare("
                SELECT w.*, u.name AS changed_by_name
                FROM inquiry_workflow w
                LEFT JOIN users u ON w.changed_by = u.user_id
                WHERE w.inquiry_id = ?
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$inquiryId]);
            $inquiry['workflow_history'] = $stmt->fetchAll();

            Response::success($inquiry, 'Inquiry details retrieved');
        } catch (\Exception $e) {
            error_log("InquiryController::getById Error: " . $e->getMessage());
            Response::error('Failed to retrieve inquiry', null, 500);
        }
    }

    /**
     * PUT /inquiries/{id}/status
     * Update inquiry status (STAFF/ADMIN only)
     */
    public function updateStatus(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $newStatus = $input['status'] ?? null;
            $notes = $input['notes'] ?? null;

            $validStatuses = ['new', 'processing', 'quoted', 'converted', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                Response::error('Invalid status value', null, 400);
                return;
            }

            // Get current status
            $stmt = $this->db->prepare("SELECT status FROM customer_inquiries WHERE inquiry_id = ?");
            $stmt->execute([$inquiryId]);
            $current = $stmt->fetch();

            if (!$current) {
                Response::notFound('Inquiry not found');
                return;
            }

            $this->db->beginTransaction();

            // Update status
            $stmt = $this->db->prepare("
                UPDATE customer_inquiries 
                SET status = ?, assigned_to = ?, updated_at = NOW()
                WHERE inquiry_id = ?
            ");
            $stmt->execute([$newStatus, $user->user_id, $inquiryId]);

            // Log workflow
            $stmt = $this->db->prepare("
                INSERT INTO inquiry_workflow (inquiry_id, from_status, to_status, changed_by, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$inquiryId, $current['status'], $newStatus, $user->user_id, $notes]);

            $this->db->commit();

            Response::success([
                'inquiry_id' => $inquiryId,
                'old_status' => $current['status'],
                'new_status' => $newStatus
            ], 'Inquiry status updated successfully');
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("InquiryController::updateStatus Error: " . $e->getMessage());
            Response::error('Failed to update inquiry status', null, 500);
        }
    }

    /**
     * POST /inquiries/{id}/convert
     * Convert inquiry to sales order (STAFF/ADMIN only)
     */
    public function convertToSalesOrder(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            // Call stored procedure
            $stmt = $this->db->prepare("
                CALL sp_convert_inquiry_to_sales_order(?, ?, @success, @message, @sales_order_id)
            ");
            $stmt->execute([$inquiryId, $user->user_id]);

            // Get output
            $result = $this->db->query("SELECT @success AS success, @message AS message, @sales_order_id AS sales_order_id")->fetch();

            if ($result['success']) {
                Response::success([
                    'inquiry_id' => $inquiryId,
                    'sales_order_id' => $result['sales_order_id'],
                    'message' => $result['message']
                ], 'Inquiry successfully converted to sales order');
            } else {
                Response::error($result['message'], null, 400);
            }
        } catch (\Exception $e) {
            error_log("InquiryController::convertToSalesOrder Error: " . $e->getMessage());
            Response::error('Failed to convert inquiry', null, 500);
        }
    }

    /**
     * POST /inquiries/{id}/notes
     * Add note to inquiry (STAFF/ADMIN only)
     */
    public function addNote(int $inquiryId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $noteText = trim($input['note_text'] ?? '');

            if (empty($noteText)) {
                Response::error('Note text is required', null, 400);
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO inquiry_notes (inquiry_id, user_id, note_text)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$inquiryId, $user->user_id, $noteText]);

            Response::success([
                'note_id' => (int)$this->db->lastInsertId(),
                'inquiry_id' => $inquiryId
            ], 'Note added successfully', 201);
        } catch (\Exception $e) {
            error_log("InquiryController::addNote Error: " . $e->getMessage());
            Response::error('Failed to add note', null, 500);
        }
    }

    /**
     * GET /inquiries/stats
     * Get inquiry statistics (STAFF/ADMIN only)
     */
    public function getStatistics(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stats = [
                'total' => 0,
                'new' => 0,
                'processing' => 0,
                'quoted' => 0,
                'converted' => 0,
                'cancelled' => 0,
                'today' => 0,
                'this_week' => 0,
                'conversion_rate' => 0
            ];

            // Total counts by status
            $stmt = $this->db->query("
                SELECT 
                    status, 
                    COUNT(*) AS count
                FROM customer_inquiries
                GROUP BY status
            ");

            while ($row = $stmt->fetch()) {
                $stats[$row['status']] = (int)$row['count'];
                $stats['total'] += (int)$row['count'];
            }

            // Today's inquiries
            $stmt = $this->db->query("
                SELECT COUNT(*) AS count
                FROM customer_inquiries
                WHERE DATE(created_at) = CURDATE()
            ");
            $stats['today'] = (int)$stmt->fetchColumn();

            // This week's inquiries
            $stmt = $this->db->query("
                SELECT COUNT(*) AS count
                FROM customer_inquiries
                WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())
            ");
            $stats['this_week'] = (int)$stmt->fetchColumn();

            // Conversion rate
            if ($stats['total'] > 0) {
                $stats['conversion_rate'] = round(($stats['converted'] / $stats['total']) * 100, 2);
            }

            Response::success($stats, 'Statistics retrieved');
        } catch (\Exception $e) {
            error_log("InquiryController::getStatistics Error: " . $e->getMessage());
            Response::error('Failed to retrieve statistics', null, 500);
        }
    }

    /**
     * Private helper: Check stock availability
     */
    private function checkStockAvailability(string $itemName, int $quantity): array
    {
        try {
            // Try to find matching item
            $stmt = $this->db->prepare("
                SELECT item_id, item_name, quantity, unit
                FROM items
                WHERE item_name LIKE ? OR item_name LIKE ?
                LIMIT 1
            ");

            // Extract first 2 words for fuzzy matching
            $searchTerm = explode(' ', $itemName, 3);
            $search1 = '%' . $searchTerm[0] . '%';
            $search2 = '%' . implode(' ', array_slice($searchTerm, 0, 2)) . '%';

            $stmt->execute([$search1, $search2]);
            $item = $stmt->fetch();

            if (!$item) {
                return ['status' => 'checking', 'message' => 'Item not found in inventory'];
            }

            if ($item['quantity'] >= $quantity) {
                return [
                    'status' => 'available',
                    'message' => "In stock: {$item['quantity']} {$item['unit']}",
                    'item_id' => $item['item_id']
                ];
            } else {
                return [
                    'status' => 'insufficient',
                    'message' => "Low stock: {$item['quantity']} {$item['unit']} available",
                    'item_id' => $item['item_id']
                ];
            }
        } catch (\Exception $e) {
            return ['status' => 'checking', 'message' => 'Unable to check stock'];
        }
    }
}
