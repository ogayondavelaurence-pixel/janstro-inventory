<?php

/**
 * src/Services/ServiceManagementService.php
 * 
 * Implements SAP Service Module (SM01) for after-sales support
 * Based on: Operations-Management-with-Analytics-Workbook Exercise 8.1
 */

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;

class ServiceManagementService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Create Service Call (SAP: IW21)
     * Based on Exercise 8.1 requirements
     */
    public function createServiceCall(array $data): array
    {
        // Validate required fields
        $required = ['customer_id', 'item_id', 'subject', 'priority', 'service_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Missing required field: $field");
            }
        }

        try {
            $this->db->beginTransaction();

            // Create service call
            $stmt = $this->db->prepare("
                INSERT INTO service_calls (
                    customer_id, item_id, serial_number, 
                    subject, description, priority, service_type,
                    status, assignee_id, open_datetime, 
                    scheduled_start, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'open', ?, NOW(), ?, ?)
            ");

            $stmt->execute([
                $data['customer_id'],
                $data['item_id'],
                $data['serial_number'] ?? null,
                $data['subject'],
                $data['description'] ?? null,
                $data['priority'], // high, medium, low
                $data['service_type'], // warranty, maintenance, repair
                $data['assignee_id'] ?? null,
                $data['scheduled_start'] ?? date('Y-m-d H:i:s', strtotime('+1 day')),
                $data['created_by']
            ]);

            $serviceCallId = (int)$this->db->lastInsertId();

            $this->db->commit();

            return [
                'success' => true,
                'service_call_id' => $serviceCallId,
                'message' => "Service call #{$serviceCallId} created successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Add Activity Log to Service Call
     */
    public function addActivity(int $serviceCallId, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO service_activities (
                    service_call_id, activity_type, description,
                    start_time, end_time, technician_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $serviceCallId,
                $data['activity_type'] ?? 'task',
                $data['description'],
                $data['start_time'] ?? date('Y-m-d H:i:s'),
                $data['end_time'] ?? null,
                $data['technician_id'],
                $data['status'] ?? 'in_progress'
            ]);

            return [
                'success' => true,
                'activity_id' => (int)$this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to add activity: " . $e->getMessage());
        }
    }

    /**
     * Add Solution to Knowledge Base
     */
    public function addSolution(int $serviceCallId, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO service_solutions (
                    service_call_id, problem_description,
                    root_cause, solution_description, 
                    parts_used, resolved_by, resolved_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $serviceCallId,
                $data['problem_description'],
                $data['root_cause'] ?? null,
                $data['solution_description'],
                $data['parts_used'] ?? null,
                $data['resolved_by']
            ]);

            // Update service call status
            $stmt = $this->db->prepare("
                UPDATE service_calls 
                SET status = 'resolved', resolved_datetime = NOW()
                WHERE service_call_id = ?
            ");
            $stmt->execute([$serviceCallId]);

            return [
                'success' => true,
                'solution_id' => (int)$this->db->lastInsertId(),
                'message' => "Solution added to knowledge base"
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to add solution: " . $e->getMessage());
        }
    }

    /**
     * Close Service Call
     */
    public function closeServiceCall(int $serviceCallId, int $userId, string $resolution): array
    {
        try {
            $this->db->beginTransaction();

            // Update status to closed
            $stmt = $this->db->prepare("
                UPDATE service_calls 
                SET status = 'closed', 
                    resolution = ?,
                    resolved_datetime = NOW(),
                    closed_by = ?
                WHERE service_call_id = ?
            ");
            $stmt->execute([$resolution, $userId, $serviceCallId]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Service call #{$serviceCallId} closed successfully"
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get All Service Calls
     */
    public function getAllServiceCalls(array $filters = []): array
    {
        $sql = "
            SELECT sc.*, 
                   c.customer_name, c.contact_no,
                   i.item_name,
                   u1.name AS assignee_name,
                   u2.name AS created_by_name
            FROM service_calls sc
            LEFT JOIN customers c ON sc.customer_id = c.customer_id
            LEFT JOIN items i ON sc.item_id = i.item_id
            LEFT JOIN users u1 ON sc.assignee_id = u1.user_id
            LEFT JOIN users u2 ON sc.created_by = u2.user_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND sc.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND sc.priority = ?";
            $params[] = $filters['priority'];
        }

        if (!empty($filters['service_type'])) {
            $sql .= " AND sc.service_type = ?";
            $params[] = $filters['service_type'];
        }

        $sql .= " ORDER BY sc.open_datetime DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get Service Call by ID with Full Details
     */
    public function getServiceCallById(int $serviceCallId): ?array
    {
        // Get service call header
        $stmt = $this->db->prepare("
            SELECT sc.*, 
                   c.customer_name, c.contact_no, c.email,
                   i.item_name, i.unit,
                   u1.name AS assignee_name,
                   u2.name AS created_by_name
            FROM service_calls sc
            LEFT JOIN customers c ON sc.customer_id = c.customer_id
            LEFT JOIN items i ON sc.item_id = i.item_id
            LEFT JOIN users u1 ON sc.assignee_id = u1.user_id
            LEFT JOIN users u2 ON sc.created_by = u2.user_id
            WHERE sc.service_call_id = ?
        ");
        $stmt->execute([$serviceCallId]);
        $serviceCall = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$serviceCall) {
            return null;
        }

        // Get activities
        $stmt = $this->db->prepare("
            SELECT sa.*, u.name AS technician_name
            FROM service_activities sa
            LEFT JOIN users u ON sa.technician_id = u.user_id
            WHERE sa.service_call_id = ?
            ORDER BY sa.start_time DESC
        ");
        $stmt->execute([$serviceCallId]);
        $serviceCall['activities'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get solutions
        $stmt = $this->db->prepare("
            SELECT ss.*, u.name AS resolved_by_name
            FROM service_solutions ss
            LEFT JOIN users u ON ss.resolved_by = u.user_id
            WHERE ss.service_call_id = ?
        ");
        $stmt->execute([$serviceCallId]);
        $serviceCall['solutions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $serviceCall;
    }
}
