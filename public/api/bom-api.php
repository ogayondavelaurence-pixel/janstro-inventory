<?php

/**
 * ============================================================================
 * BILL OF MATERIALS (BOM) API ENDPOINT
 * ============================================================================
 * Path: public/api/bom.php
 * Phase 8: BOM Management System
 * 
 * Routes:
 * GET    /bom              - List all BOMs
 * GET    /bom/:id          - Get BOM for specific parent item
 * POST   /bom              - Create new BOM
 * PUT    /bom/:id          - Update BOM
 * DELETE /bom/:id          - Delete BOM entry
 * GET    /bom/:id/explosion - Get component explosion tree
 * ============================================================================
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

// Authenticate user
$user = AuthMiddleware::authenticate();
if (!$user) exit;

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Parse route segments
preg_match('#/bom(?:/(\d+))?(?:/(explosion))?#', $path, $matches);
$bomId = isset($matches[1]) ? (int)$matches[1] : null;
$action = $matches[2] ?? null;

/**
 * ============================================================================
 * GET /bom - List all BOMs
 * ============================================================================
 */
if ($method === 'GET' && !$bomId) {
    try {
        $stmt = $db->query("
            SELECT 
                b.bom_id,
                b.parent_item_id,
                pi.item_name as parent_item_name,
                pi.sku as parent_sku,
                b.component_item_id,
                ci.item_name as component_name,
                ci.sku as component_sku,
                b.quantity_required,
                ci.unit,
                ci.quantity as available_stock,
                ci.unit_price as component_price,
                (b.quantity_required * ci.unit_price) as component_value,
                CASE 
                    WHEN ci.quantity >= b.quantity_required THEN 'available'
                    WHEN ci.quantity > 0 THEN 'partial'
                    ELSE 'unavailable'
                END as stock_status
            FROM bill_of_materials b
            JOIN items pi ON b.parent_item_id = pi.item_id
            JOIN items ci ON b.component_item_id = ci.item_id
            ORDER BY pi.item_name, b.bom_id
        ");

        $boms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by parent item
        $grouped = [];
        foreach ($boms as $bom) {
            $parentId = $bom['parent_item_id'];
            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [
                    'parent_item_id' => (int)$parentId,
                    'parent_item_name' => $bom['parent_item_name'],
                    'parent_sku' => $bom['parent_sku'],
                    'total_components' => 0,
                    'components' => []
                ];
            }

            $grouped[$parentId]['components'][] = [
                'bom_id' => (int)$bom['bom_id'],
                'component_item_id' => (int)$bom['component_item_id'],
                'component_name' => $bom['component_name'],
                'component_sku' => $bom['component_sku'],
                'quantity_required' => (int)$bom['quantity_required'],
                'unit' => $bom['unit'],
                'available_stock' => (int)$bom['available_stock'],
                'component_price' => (float)$bom['component_price'],
                'component_value' => (float)$bom['component_value'],
                'stock_status' => $bom['stock_status']
            ];
            $grouped[$parentId]['total_components']++;
        }

        Response::success([
            'boms' => array_values($grouped),
            'total_parent_items' => count($grouped)
        ], 'BOMs retrieved successfully');
    } catch (PDOException $e) {
        error_log("BOM list error: " . $e->getMessage());
        Response::serverError('Failed to retrieve BOMs');
    }
    exit;
}

/**
 * ============================================================================
 * GET /bom/:id - Get BOM for specific parent item
 * ============================================================================
 */
if ($method === 'GET' && $bomId && $action !== 'explosion') {
    try {
        // Get parent item details
        $stmt = $db->prepare("
            SELECT item_id, item_name, sku, quantity, unit, is_bom_item
            FROM items
            WHERE item_id = ?
        ");
        $stmt->execute([$bomId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            Response::notFound('Parent item not found');
            exit;
        }

        // Get BOM components
        $stmt = $db->prepare("
            SELECT 
                b.bom_id,
                b.component_item_id,
                i.item_name,
                i.sku,
                b.quantity_required,
                i.unit,
                i.quantity as available_stock,
                i.unit_price,
                (b.quantity_required * i.unit_price) as total_cost
            FROM bill_of_materials b
            JOIN items i ON b.component_item_id = i.item_id
            WHERE b.parent_item_id = ?
            ORDER BY i.item_name
        ");
        $stmt->execute([$bomId]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCost = array_sum(array_column($components, 'total_cost'));

        Response::success([
            'parent' => [
                'item_id' => (int)$parent['item_id'],
                'item_name' => $parent['item_name'],
                'sku' => $parent['sku'],
                'quantity' => (int)$parent['quantity'],
                'unit' => $parent['unit'],
                'is_bom_item' => (bool)$parent['is_bom_item']
            ],
            'components' => array_map(function ($c) {
                return [
                    'bom_id' => (int)$c['bom_id'],
                    'component_item_id' => (int)$c['component_item_id'],
                    'item_name' => $c['item_name'],
                    'sku' => $c['sku'],
                    'quantity_required' => (int)$c['quantity_required'],
                    'unit' => $c['unit'],
                    'available_stock' => (int)$c['available_stock'],
                    'unit_price' => (float)$c['unit_price'],
                    'total_cost' => (float)$c['total_cost']
                ];
            }, $components),
            'total_components' => count($components),
            'total_bom_cost' => round($totalCost, 2)
        ], 'BOM retrieved successfully');
    } catch (PDOException $e) {
        error_log("BOM get error: " . $e->getMessage());
        Response::serverError('Failed to retrieve BOM');
    }
    exit;
}

/**
 * ============================================================================
 * GET /bom/:id/explosion - Get BOM explosion tree
 * ============================================================================
 */
if ($method === 'GET' && $bomId && $action === 'explosion') {
    try {
        function getBOMExplosion($db, $parentId, $quantity = 1, $level = 0)
        {
            if ($level > 3) return []; // Max 3 levels

            $stmt = $db->prepare("
                SELECT 
                    b.component_item_id,
                    i.item_name,
                    i.sku,
                    b.quantity_required,
                    i.unit,
                    i.quantity as available_stock,
                    i.is_bom_item
                FROM bill_of_materials b
                JOIN items i ON b.component_item_id = i.item_id
                WHERE b.parent_item_id = ?
            ");
            $stmt->execute([$parentId]);
            $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result = [];
            foreach ($components as $comp) {
                $totalRequired = $comp['quantity_required'] * $quantity;

                $node = [
                    'item_id' => (int)$comp['component_item_id'],
                    'item_name' => $comp['item_name'],
                    'sku' => $comp['sku'],
                    'quantity_per_unit' => (int)$comp['quantity_required'],
                    'total_required' => $totalRequired,
                    'unit' => $comp['unit'],
                    'available_stock' => (int)$comp['available_stock'],
                    'shortage' => max(0, $totalRequired - $comp['available_stock']),
                    'level' => $level
                ];

                // Recursive check for sub-components
                if ($comp['is_bom_item']) {
                    $node['sub_components'] = getBOMExplosion($db, $comp['component_item_id'], $totalRequired, $level + 1);
                }

                $result[] = $node;
            }

            return $result;
        }

        $explosion = getBOMExplosion($db, $bomId);

        Response::success([
            'parent_item_id' => $bomId,
            'explosion_tree' => $explosion
        ], 'BOM explosion generated');
    } catch (PDOException $e) {
        error_log("BOM explosion error: " . $e->getMessage());
        Response::serverError('Failed to generate BOM explosion');
    }
    exit;
}

/**
 * ============================================================================
 * POST /bom - Create new BOM
 * ============================================================================
 */
if ($method === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        // Validation
        if (empty($data['parent_item_id']) || empty($data['component_item_id']) || empty($data['quantity_required'])) {
            Response::badRequest('parent_item_id, component_item_id, and quantity_required are required');
            exit;
        }

        $parentId = (int)$data['parent_item_id'];
        $componentId = (int)$data['component_item_id'];
        $quantity = (int)$data['quantity_required'];

        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
            exit;
        }

        // Prevent self-reference
        if ($parentId === $componentId) {
            Response::badRequest('Parent item cannot be its own component');
            exit;
        }

        // Check if parent exists
        $stmt = $db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
        $stmt->execute([$parentId]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent) {
            Response::notFound('Parent item not found');
            exit;
        }

        // Check if component exists
        $stmt = $db->prepare("SELECT item_id, item_name FROM items WHERE item_id = ?");
        $stmt->execute([$componentId]);
        $component = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$component) {
            Response::notFound('Component item not found');
            exit;
        }

        // Check for circular dependency
        function hasCircularDependency($db, $parentId, $componentId, $depth = 0)
        {
            if ($depth > 10) return true; // Safety limit

            $stmt = $db->prepare("
                SELECT component_item_id 
                FROM bill_of_materials 
                WHERE parent_item_id = ?
            ");
            $stmt->execute([$componentId]);
            $subComponents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($subComponents as $subComp) {
                if ($subComp == $parentId) return true;
                if (hasCircularDependency($db, $parentId, $subComp, $depth + 1)) return true;
            }

            return false;
        }

        if (hasCircularDependency($db, $parentId, $componentId)) {
            Response::badRequest('Circular dependency detected. Component cannot reference parent item in its BOM tree.');
            exit;
        }

        // Check if BOM entry already exists
        $stmt = $db->prepare("
            SELECT bom_id FROM bill_of_materials 
            WHERE parent_item_id = ? AND component_item_id = ?
        ");
        $stmt->execute([$parentId, $componentId]);
        if ($stmt->fetch()) {
            Response::badRequest('BOM entry already exists for this parent-component pair');
            exit;
        }

        $db->beginTransaction();

        // Insert BOM
        $stmt = $db->prepare("
            INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$parentId, $componentId, $quantity]);
        $bomId = (int)$db->lastInsertId();

        // Mark parent as BOM item
        $stmt = $db->prepare("UPDATE items SET is_bom_item = 1 WHERE item_id = ?");
        $stmt->execute([$parentId]);

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'bom', 'create', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Created BOM: {$parent['item_name']} ← {$component['item_name']} x{$quantity}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $db->commit();

        Response::success([
            'bom_id' => $bomId,
            'parent_item_name' => $parent['item_name'],
            'component_name' => $component['item_name'],
            'quantity_required' => $quantity
        ], 'BOM created successfully', 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("BOM create error: " . $e->getMessage());
        Response::serverError('Failed to create BOM: ' . $e->getMessage());
    }
    exit;
}

/**
 * ============================================================================
 * PUT /bom/:id - Update BOM quantity
 * ============================================================================
 */
if ($method === 'PUT' && $bomId) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['quantity_required'])) {
            Response::badRequest('quantity_required is required');
            exit;
        }

        $quantity = (int)$data['quantity_required'];
        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
            exit;
        }

        // Check if BOM exists
        $stmt = $db->prepare("
            SELECT b.*, pi.item_name as parent_name, ci.item_name as component_name
            FROM bill_of_materials b
            JOIN items pi ON b.parent_item_id = pi.item_id
            JOIN items ci ON b.component_item_id = ci.item_id
            WHERE b.bom_id = ?
        ");
        $stmt->execute([$bomId]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bom) {
            Response::notFound('BOM entry not found');
            exit;
        }

        // Update
        $stmt = $db->prepare("
            UPDATE bill_of_materials 
            SET quantity_required = ?
            WHERE bom_id = ?
        ");
        $stmt->execute([$quantity, $bomId]);

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'bom', 'update', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Updated BOM #{$bomId}: {$bom['parent_name']} ← {$bom['component_name']} from {$bom['quantity_required']} to {$quantity}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        Response::success(null, 'BOM updated successfully');
    } catch (PDOException $e) {
        error_log("BOM update error: " . $e->getMessage());
        Response::serverError('Failed to update BOM');
    }
    exit;
}

/**
 * ============================================================================
 * DELETE /bom/:id - Delete BOM entry
 * ============================================================================
 */
if ($method === 'DELETE' && $bomId) {
    try {
        // Check if BOM exists
        $stmt = $db->prepare("
            SELECT b.*, pi.item_name as parent_name, ci.item_name as component_name
            FROM bill_of_materials b
            JOIN items pi ON b.parent_item_id = pi.item_id
            JOIN items ci ON b.component_item_id = ci.item_id
            WHERE b.bom_id = ?
        ");
        $stmt->execute([$bomId]);
        $bom = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bom) {
            Response::notFound('BOM entry not found');
            exit;
        }

        $db->beginTransaction();

        $parentId = $bom['parent_item_id'];

        // Delete BOM
        $stmt = $db->prepare("DELETE FROM bill_of_materials WHERE bom_id = ?");
        $stmt->execute([$bomId]);

        // Check if parent still has other BOM components
        $stmt = $db->prepare("SELECT COUNT(*) FROM bill_of_materials WHERE parent_item_id = ?");
        $stmt->execute([$parentId]);
        $remainingCount = (int)$stmt->fetchColumn();

        // If no more components, unmark as BOM item
        if ($remainingCount === 0) {
            $stmt = $db->prepare("UPDATE items SET is_bom_item = 0 WHERE item_id = ?");
            $stmt->execute([$parentId]);
        }

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'bom', 'delete', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Deleted BOM #{$bomId}: {$bom['parent_name']} ← {$bom['component_name']}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $db->commit();

        Response::success(null, 'BOM deleted successfully');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("BOM delete error: " . $e->getMessage());
        Response::serverError('Failed to delete BOM');
    }
    exit;
}

Response::notFound('BOM endpoint not found');
