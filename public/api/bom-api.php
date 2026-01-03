<?php

/**
 * ============================================================================
 * BOM API v2.0 - WITH ORGANIZATION FEATURES
 * ============================================================================
 * Features:
 * - Product family grouping
 * - Version management
 * - BOM templates
 * - Mass quantity updates
 * ============================================================================
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

$user = AuthMiddleware::authenticate();
if (!$user) exit;

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

preg_match('#/bom(?:/(\d+|templates|families))?(?:/(explosion|versions|apply))?#', $path, $matches);
$resource = $matches[1] ?? null;
$action = $matches[2] ?? null;

/**
 * ============================================================================
 * GET /bom/families - Get product families summary
 * ============================================================================
 */
if ($method === 'GET' && $resource === 'families') {
    try {
        $stmt = $db->query("
            SELECT 
                COALESCE(i.product_family, 'Uncategorized') as family_name,
                COUNT(DISTINCT b.parent_item_id) as bom_count,
                COUNT(b.bom_id) as total_components,
                SUM(b.quantity_required * ci.unit_price) as total_value
            FROM items i
            LEFT JOIN bill_of_materials b ON i.item_id = b.parent_item_id
            LEFT JOIN items ci ON b.component_item_id = ci.item_id
            WHERE i.is_bom_item = 1
            GROUP BY i.product_family
            ORDER BY bom_count DESC
        ");

        $families = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success(['families' => $families], 'Product families retrieved');
    } catch (PDOException $e) {
        error_log("Families error: " . $e->getMessage());
        Response::serverError('Failed to retrieve families');
    }
    exit;
}

/**
 * ============================================================================
 * GET /bom/templates - Get all BOM templates
 * ============================================================================
 */
if ($method === 'GET' && $resource === 'templates') {
    try {
        $stmt = $db->query("
            SELECT 
                t.*,
                u.name as created_by_name,
                COUNT(ti.template_item_id) as component_count
            FROM bom_templates t
            LEFT JOIN users u ON t.created_by = u.user_id
            LEFT JOIN bom_template_items ti ON t.template_id = ti.template_id
            WHERE t.is_active = 1
            GROUP BY t.template_id
            ORDER BY t.created_at DESC
        ");

        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success(['templates' => $templates], 'Templates retrieved');
    } catch (PDOException $e) {
        error_log("Templates error: " . $e->getMessage());
        Response::serverError('Failed to retrieve templates');
    }
    exit;
}

/**
 * ============================================================================
 * POST /bom/templates - Create BOM template
 * ============================================================================
 */
if ($method === 'POST' && $resource === 'templates') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['template_name'])) {
            Response::badRequest('Template name required');
            exit;
        }

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO bom_templates (template_name, description, product_family, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['template_name'],
            $data['description'] ?? null,
            $data['product_family'] ?? null,
            $user->user_id
        ]);

        $templateId = (int)$db->lastInsertId();

        $db->commit();

        Response::success(['template_id' => $templateId], 'Template created', 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Create template error: " . $e->getMessage());
        Response::serverError('Failed to create template');
    }
    exit;
}

/**
 * ============================================================================
 * POST /bom/templates/:id/apply - Apply template to parent item
 * ============================================================================
 */
if ($method === 'POST' && is_numeric($resource) && $action === 'apply') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $templateId = (int)$resource;

        if (empty($data['parent_item_id'])) {
            Response::badRequest('parent_item_id required');
            exit;
        }

        $db->beginTransaction();

        // Get template items
        $stmt = $db->prepare("
            SELECT component_item_id, quantity_required, notes
            FROM bom_template_items
            WHERE template_id = ?
        ");
        $stmt->execute([$templateId]);
        $templateItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($templateItems)) {
            $db->rollBack();
            Response::notFound('Template has no components');
            exit;
        }

        // Create BOM entries
        $stmt = $db->prepare("
            INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required, notes)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($templateItems as $item) {
            $stmt->execute([
                $data['parent_item_id'],
                $item['component_item_id'],
                $item['quantity_required'],
                $item['notes']
            ]);
        }

        // Mark parent as BOM item
        $stmt = $db->prepare("UPDATE items SET is_bom_item = 1 WHERE item_id = ?");
        $stmt->execute([$data['parent_item_id']]);

        $db->commit();

        Response::success(['components_created' => count($templateItems)], 'Template applied successfully');
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("Apply template error: " . $e->getMessage());
        Response::serverError('Failed to apply template');
    }
    exit;
}

/**
 * ============================================================================
 * GET /bom/:id/versions - Get BOM versions for parent item
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($resource) && $action === 'versions') {
    try {
        $parentId = (int)$resource;

        $stmt = $db->prepare("
            SELECT v.*, u.name as created_by_name,
                   COUNT(b.bom_id) as component_count
            FROM bom_versions v
            LEFT JOIN users u ON v.created_by = u.user_id
            LEFT JOIN bill_of_materials b ON v.version_id = b.version_id
            WHERE v.parent_item_id = ?
            GROUP BY v.version_id
            ORDER BY v.created_at DESC
        ");
        $stmt->execute([$parentId]);

        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        Response::success(['versions' => $versions], 'Versions retrieved');
    } catch (PDOException $e) {
        error_log("Versions error: " . $e->getMessage());
        Response::serverError('Failed to retrieve versions');
    }
    exit;
}

/**
 * ============================================================================
 * POST /bom - Create BOM (Enhanced with versioning)
 * ============================================================================
 */
if ($method === 'POST' && !$resource) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['parent_item_id']) || empty($data['component_item_id']) || empty($data['quantity_required'])) {
            Response::badRequest('parent_item_id, component_item_id, and quantity_required required');
            exit;
        }

        $parentId = (int)$data['parent_item_id'];
        $componentId = (int)$data['component_item_id'];
        $quantity = (int)$data['quantity_required'];
        $versionNumber = $data['version_number'] ?? 'v1.0';

        if ($quantity <= 0) {
            Response::badRequest('Quantity must be greater than 0');
            exit;
        }

        if ($parentId === $componentId) {
            Response::badRequest('Parent item cannot be its own component');
            exit;
        }

        // Check circular dependency
        function hasCircularDependency($db, $parentId, $componentId, $depth = 0)
        {
            if ($depth > 10) return true;

            $stmt = $db->prepare("SELECT component_item_id FROM bill_of_materials WHERE parent_item_id = ?");
            $stmt->execute([$componentId]);
            $subComponents = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($subComponents as $subComp) {
                if ($subComp == $parentId) return true;
                if (hasCircularDependency($db, $parentId, $subComp, $depth + 1)) return true;
            }
            return false;
        }

        if (hasCircularDependency($db, $parentId, $componentId)) {
            Response::badRequest('Circular dependency detected');
            exit;
        }

        $db->beginTransaction();

        // Create or get version
        $stmt = $db->prepare("
            SELECT version_id FROM bom_versions 
            WHERE parent_item_id = ? AND version_number = ?
        ");
        $stmt->execute([$parentId, $versionNumber]);
        $version = $stmt->fetch();

        if (!$version) {
            $stmt = $db->prepare("
                INSERT INTO bom_versions (parent_item_id, version_number, created_by, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$parentId, $versionNumber, $user->user_id]);
            $versionId = (int)$db->lastInsertId();
        } else {
            $versionId = (int)$version['version_id'];
        }

        // Insert BOM
        $stmt = $db->prepare("
            INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required, version_id, notes)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $parentId,
            $componentId,
            $quantity,
            $versionId,
            $data['notes'] ?? null
        ]);

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
            "Created BOM #{$bomId} (Version: {$versionNumber})",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $db->commit();

        Response::success(['bom_id' => $bomId, 'version_id' => $versionId], 'BOM created successfully', 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("BOM create error: " . $e->getMessage());
        Response::serverError('Failed to create BOM: ' . $e->getMessage());
    }
    exit;
}

/**
 * ============================================================================
 * GET /bom - List all BOMs (Enhanced with families)
 * ============================================================================
 */
if ($method === 'GET' && !$resource) {
    try {
        $familyFilter = $_GET['family'] ?? null;

        $sql = "
            SELECT 
                b.bom_id, b.parent_item_id,
                pi.item_name as parent_item_name,
                pi.sku as parent_sku,
                pi.product_family,
                pi.product_code,
                b.component_item_id,
                ci.item_name as component_name,
                ci.sku as component_sku,
                b.quantity_required,
                ci.unit,
                ci.quantity as available_stock,
                ci.unit_price as component_price,
                (b.quantity_required * ci.unit_price) as component_value,
                v.version_number,
                b.notes,
                CASE 
                    WHEN ci.quantity >= b.quantity_required THEN 'available'
                    WHEN ci.quantity > 0 THEN 'partial'
                    ELSE 'unavailable'
                END as stock_status
            FROM bill_of_materials b
            JOIN items pi ON b.parent_item_id = pi.item_id
            JOIN items ci ON b.component_item_id = ci.item_id
            LEFT JOIN bom_versions v ON b.version_id = v.version_id
        ";

        if ($familyFilter) {
            $sql .= " WHERE pi.product_family = :family";
        }

        $sql .= " ORDER BY pi.product_family, pi.item_name, b.bom_id";

        $stmt = $db->prepare($sql);
        if ($familyFilter) {
            $stmt->bindValue(':family', $familyFilter);
        }
        $stmt->execute();

        $boms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by parent
        $grouped = [];
        foreach ($boms as $bom) {
            $parentId = $bom['parent_item_id'];
            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [
                    'parent_item_id' => (int)$parentId,
                    'parent_item_name' => $bom['parent_item_name'],
                    'parent_sku' => $bom['parent_sku'],
                    'product_family' => $bom['product_family'],
                    'product_code' => $bom['product_code'],
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
                'version_number' => $bom['version_number'],
                'notes' => $bom['notes'],
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
 * GET /bom/:id/explosion - BOM explosion tree
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($resource) && $action === 'explosion') {
    try {
        function getBOMExplosion($db, $parentId, $quantity = 1, $level = 0)
        {
            if ($level > 3) return [];

            $stmt = $db->prepare("
                SELECT 
                    b.component_item_id, i.item_name, i.sku,
                    b.quantity_required, i.unit, i.quantity as available_stock,
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

                if ($comp['is_bom_item']) {
                    $node['sub_components'] = getBOMExplosion($db, $comp['component_item_id'], $totalRequired, $level + 1);
                }

                $result[] = $node;
            }

            return $result;
        }

        $explosion = getBOMExplosion($db, (int)$resource);

        Response::success([
            'parent_item_id' => (int)$resource,
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
 * DELETE /bom/:id - Delete BOM entry
 * ============================================================================
 */
if ($method === 'DELETE' && is_numeric($resource)) {
    try {
        $bomId = (int)$resource;

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

        $stmt = $db->prepare("DELETE FROM bill_of_materials WHERE bom_id = ?");
        $stmt->execute([$bomId]);

        // Check if parent still has components
        $stmt = $db->prepare("SELECT COUNT(*) FROM bill_of_materials WHERE parent_item_id = ?");
        $stmt->execute([$parentId]);
        $remainingCount = (int)$stmt->fetchColumn();

        if ($remainingCount === 0) {
            $stmt = $db->prepare("UPDATE items SET is_bom_item = 0 WHERE item_id = ?");
            $stmt->execute([$parentId]);
        }

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'bom', 'delete', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Deleted BOM #{$bomId}",
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
