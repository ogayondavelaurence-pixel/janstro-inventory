<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * BOM SERVICE v3.0 - Business Logic Layer
 * ============================================================================
 * Handles all BOM operations: CRUD, versioning, templates, explosion trees
 * Path: src/Services/BomService.php
 * ============================================================================
 */
class BomService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ========================================================================
    // BOM CRUD OPERATIONS
    // ========================================================================

    /**
     * Get all BOMs grouped by parent item
     */
    public function getAllBOMs(?string $familyFilter = null): array
    {
        try {
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
                $sql .= " WHERE pi.product_family = ?";
            }

            $sql .= " ORDER BY pi.product_family, pi.item_name, b.bom_id";

            $stmt = $familyFilter
                ? $this->db->prepare($sql)
                : $this->db->query($sql);

            if ($familyFilter) {
                $stmt->execute([$familyFilter]);
            }

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

            return [
                'boms' => array_values($grouped),
                'total_parent_items' => count($grouped)
            ];
        } catch (Exception $e) {
            error_log("BomService::getAllBOMs - " . $e->getMessage());
            throw new Exception('Failed to retrieve BOMs');
        }
    }

    /**
     * Create BOM entry
     */
    public function createBOM(array $data, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $parentId = (int)$data['parent_item_id'];
            $componentId = (int)$data['component_item_id'];
            $quantity = (int)$data['quantity_required'];
            $versionNumber = $data['version_number'] ?? 'v1.0';

            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }

            // Check circular dependency
            if ($this->hasCircularDependency($parentId, $componentId)) {
                throw new Exception('Circular dependency detected');
            }

            // Get or create version
            $versionId = $this->getOrCreateVersion($parentId, $versionNumber, $userId);

            // Insert BOM
            $stmt = $this->db->prepare("
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

            $bomId = (int)$this->db->lastInsertId();

            // Mark parent as BOM item
            $stmt = $this->db->prepare("UPDATE items SET is_bom_item = 1 WHERE item_id = ?");
            $stmt->execute([$parentId]);

            // Audit log
            $this->createAuditLog(
                $userId,
                "Created BOM #{$bomId} (Version: {$versionNumber})",
                'bom',
                'create'
            );

            $this->db->commit();

            return [
                'bom_id' => $bomId,
                'version_id' => $versionId
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete BOM entry
     */
    public function deleteBOM(int $bomId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT b.*, pi.item_name as parent_name, ci.item_name as component_name
                FROM bill_of_materials b
                JOIN items pi ON b.parent_item_id = pi.item_id
                JOIN items ci ON b.component_item_id = ci.item_id
                WHERE b.bom_id = ?
            ");
            $stmt->execute([$bomId]);
            $bom = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$bom) {
                throw new Exception('BOM entry not found');
            }

            $parentId = $bom['parent_item_id'];

            $stmt = $this->db->prepare("DELETE FROM bill_of_materials WHERE bom_id = ?");
            $stmt->execute([$bomId]);

            // Check if parent still has components
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM bill_of_materials WHERE parent_item_id = ?");
            $stmt->execute([$parentId]);
            $remainingCount = (int)$stmt->fetchColumn();

            if ($remainingCount === 0) {
                $stmt = $this->db->prepare("UPDATE items SET is_bom_item = 0 WHERE item_id = ?");
                $stmt->execute([$parentId]);
            }

            $this->createAuditLog(
                $userId,
                "Deleted BOM #{$bomId}",
                'bom',
                'delete'
            );

            $this->db->commit();

            return ['success' => true];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    // ========================================================================
    // EXPLOSION TREE
    // ========================================================================

    /**
     * Generate BOM explosion tree (recursive)
     */
    public function generateExplosionTree(int $parentId, int $quantity = 1, int $level = 0): array
    {
        if ($level > 3) return [];

        $stmt = $this->db->prepare("
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
                $node['sub_components'] = $this->generateExplosionTree(
                    $comp['component_item_id'],
                    $totalRequired,
                    $level + 1
                );
            }

            $result[] = $node;
        }

        return $result;
    }

    // ========================================================================
    // PRODUCT FAMILIES
    // ========================================================================

    /**
     * Get family summary
     */
    public function getFamilySummary(): array
    {
        $stmt = $this->db->query("
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // TEMPLATES
    // ========================================================================

    /**
     * Get all templates
     */
    public function getAllTemplates(): array
    {
        $stmt = $this->db->query("
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create template
     */
    public function createTemplate(array $data, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO bom_templates (template_name, description, product_family, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['template_name'],
                $data['description'] ?? null,
                $data['product_family'] ?? null,
                $userId
            ]);

            $templateId = (int)$this->db->lastInsertId();

            $this->db->commit();

            return ['template_id' => $templateId];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Apply template to parent item
     */
    public function applyTemplate(int $templateId, int $parentItemId, int $userId): array
    {
        try {
            $this->db->beginTransaction();

            // Get template items
            $stmt = $this->db->prepare("
                SELECT component_item_id, quantity_required, notes
                FROM bom_template_items
                WHERE template_id = ?
            ");
            $stmt->execute([$templateId]);
            $templateItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($templateItems)) {
                throw new Exception('Template has no components');
            }

            // Create BOM entries
            $stmt = $this->db->prepare("
                INSERT INTO bill_of_materials (parent_item_id, component_item_id, quantity_required, notes)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($templateItems as $item) {
                $stmt->execute([
                    $parentItemId,
                    $item['component_item_id'],
                    $item['quantity_required'],
                    $item['notes']
                ]);
            }

            // Mark parent as BOM item
            $stmt = $this->db->prepare("UPDATE items SET is_bom_item = 1 WHERE item_id = ?");
            $stmt->execute([$parentItemId]);

            $this->db->commit();

            return ['components_created' => count($templateItems)];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    // ========================================================================
    // VERSIONS
    // ========================================================================

    /**
     * Get versions for parent item
     */
    public function getVersions(int $parentItemId): array
    {
        $stmt = $this->db->prepare("
            SELECT v.*, u.name as created_by_name,
                   COUNT(b.bom_id) as component_count
            FROM bom_versions v
            LEFT JOIN users u ON v.created_by = u.user_id
            LEFT JOIN bill_of_materials b ON v.version_id = b.version_id
            WHERE v.parent_item_id = ?
            GROUP BY v.version_id
            ORDER BY v.created_at DESC
        ");
        $stmt->execute([$parentItemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get or create version
     */
    private function getOrCreateVersion(int $parentId, string $versionNumber, int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT version_id FROM bom_versions 
            WHERE parent_item_id = ? AND version_number = ?
        ");
        $stmt->execute([$parentId, $versionNumber]);
        $version = $stmt->fetch();

        if ($version) {
            return (int)$version['version_id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO bom_versions (parent_item_id, version_number, created_by, is_active)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$parentId, $versionNumber, $userId]);

        return (int)$this->db->lastInsertId();
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Check circular dependency (recursive)
     */
    private function hasCircularDependency(int $parentId, int $componentId, int $depth = 0): bool
    {
        if ($depth > 10) return true;

        $stmt = $this->db->prepare("SELECT component_item_id FROM bill_of_materials WHERE parent_item_id = ?");
        $stmt->execute([$componentId]);
        $subComponents = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($subComponents as $subComp) {
            if ($subComp == $parentId) return true;
            if ($this->hasCircularDependency($parentId, $subComp, $depth + 1)) return true;
        }

        return false;
    }

    /**
     * Create audit log
     */
    private function createAuditLog(int $userId, string $description, string $module, string $actionType): void
    {
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
        } catch (Exception $e) {
            error_log("Failed to create audit log: " . $e->getMessage());
        }
    }
}
