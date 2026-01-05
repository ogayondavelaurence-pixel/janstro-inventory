<?php

/**
 * ============================================================================
 * CRON JOB - CHECK BOM COMPONENT SHORTAGES & CREATE PRs
 * ============================================================================
 * Purpose: Proactively check Bill of Materials (BOM) assemblies for component
 *          shortages and auto-generate Purchase Requisitions (PRs) before
 *          customer orders fail due to missing parts.
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Services\NotificationService;

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

$db = Database::connect();
$notificationService = new NotificationService();

echo "========================================\n";
echo "Janstro IMS - BOM Component Shortage Check\n";
echo date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

try {
    $db->beginTransaction();

    // ========================================================================
    // STEP 1: Get all BOM parent items (assemblies/finished products)
    // ========================================================================
    echo "🔍 Step 1: Scanning BOM assemblies...\n";

    $stmt = $db->query("
        SELECT DISTINCT
            i.item_id,
            i.item_name,
            i.sku,
            i.product_family,
            i.quantity as parent_stock,
            i.reorder_level as parent_reorder
        FROM items i
        WHERE i.is_bom_item = 1
        AND i.status = 'active'
        ORDER BY i.product_family, i.item_name
    ");

    $bomParents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Found " . count($bomParents) . " BOM assemblies\n\n";

    if (empty($bomParents)) {
        echo "✅ No BOM items found. Exiting.\n";
        $db->commit();
        exit(0);
    }

    // ========================================================================
    // STEP 2: Check component availability for each assembly
    // ========================================================================
    echo "🔍 Step 2: Checking component availability...\n";

    $shortages = [];
    $systemUserId = 1;

    foreach ($bomParents as $parent) {
        // Get all components for this parent
        $stmt = $db->prepare("
            SELECT 
                b.component_item_id,
                b.quantity_required,
                ci.item_name as component_name,
                ci.sku as component_sku,
                ci.quantity as available_stock,
                ci.unit,
                ci.unit_price,
                ci.reorder_level as component_reorder,
                b.version_id,
                v.version_number
            FROM bill_of_materials b
            JOIN items ci ON b.component_item_id = ci.item_id
            LEFT JOIN bom_versions v ON b.version_id = v.version_id
            WHERE b.parent_item_id = ?
            AND ci.status = 'active'
        ");
        $stmt->execute([$parent['item_id']]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($components)) {
            continue;
        }

        // Calculate how many assemblies can be built with current stock
        $maxBuildable = PHP_INT_MAX;
        $bottleneckComponents = [];

        foreach ($components as $component) {
            $required = (int)$component['quantity_required'];
            $available = (int)$component['available_stock'];

            if ($required <= 0) continue;

            $canBuild = floor($available / $required);

            if ($canBuild < $maxBuildable) {
                $maxBuildable = $canBuild;
            }

            // Check if component is below reorder level
            if ($available < $required || $available <= $component['component_reorder']) {
                $bottleneckComponents[] = $component;
            }
        }

        // ====================================================================
        // STEP 3: Detect shortages and calculate PR quantity
        // ====================================================================
        $targetBuildQuantity = max($parent['parent_reorder'], 5); // Build at least 5 units

        if ($maxBuildable < $targetBuildQuantity && !empty($bottleneckComponents)) {
            echo "   ⚠️  {$parent['item_name']}: Can only build {$maxBuildable} units (target: {$targetBuildQuantity})\n";

            foreach ($bottleneckComponents as $component) {
                $componentId = (int)$component['component_item_id'];
                $available = (int)$component['available_stock'];
                $requiredPerUnit = (int)$component['quantity_required'];
                $totalRequired = $requiredPerUnit * $targetBuildQuantity;
                $shortage = max(0, $totalRequired - $available);

                if ($shortage <= 0) continue;

                // Check if PR already exists for this component
                $stmt = $db->prepare("
                    SELECT COUNT(*) as pr_count
                    FROM purchase_requisitions
                    WHERE item_id = ?
                    AND status IN ('pending', 'approved')
                    AND reason LIKE ?
                ");
                $stmt->execute([
                    $componentId,
                    "%BOM: {$parent['item_name']}%"
                ]);
                $existingPR = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingPR['pr_count'] > 0) {
                    echo "      → {$component['component_name']}: PR already exists (skipped)\n";
                    continue;
                }

                $shortages[] = [
                    'parent_item_id' => $parent['item_id'],
                    'parent_name' => $parent['item_name'],
                    'parent_family' => $parent['product_family'],
                    'component_item_id' => $componentId,
                    'component_name' => $component['component_name'],
                    'component_sku' => $component['component_sku'],
                    'available' => $available,
                    'required_per_unit' => $requiredPerUnit,
                    'total_required' => $totalRequired,
                    'shortage_qty' => $shortage,
                    'unit' => $component['unit'],
                    'unit_price' => $component['unit_price']
                ];
            }
        }
    }

    echo "   Total component shortages detected: " . count($shortages) . "\n\n";

    // ========================================================================
    // STEP 4: Create Purchase Requisitions for shortages
    // ========================================================================
    if (empty($shortages)) {
        echo "✅ No BOM component shortages found. All assemblies can be built.\n";
        $db->commit();
        exit(0);
    }

    echo "🔨 Step 3: Creating Purchase Requisitions...\n";

    $created = [];

    foreach ($shortages as $shortage) {
        // Calculate urgency
        $urgency = 'high'; // BOM shortages are always high priority
        if ($shortage['available'] === 0) {
            $urgency = 'critical'; // Zero stock = critical
        }

        // Generate PR number
        $stmt = $db->query("
            SELECT COUNT(*) as total 
            FROM purchase_requisitions 
            WHERE YEAR(created_at) = YEAR(NOW())
        ");
        $prCount = (int)$stmt->fetchColumn();
        $prNumber = 'PR-' . date('Y') . '-' . str_pad($prCount + 1, 6, '0', STR_PAD_LEFT);

        // Create PR
        $reason = "Auto-generated: BOM component shortage for assembly '{$shortage['parent_name']}' ({$shortage['parent_family']}). " .
            "Required: {$shortage['total_required']} {$shortage['unit']}, Available: {$shortage['available']} {$shortage['unit']}, Shortage: {$shortage['shortage_qty']} {$shortage['unit']}";

        $stmt = $db->prepare("
            INSERT INTO purchase_requisitions (
                pr_number, 
                sales_order_id,
                item_id, 
                required_quantity,
                requested_by, 
                status, 
                urgency, 
                reason,
                created_at
            ) VALUES (?, NULL, ?, ?, ?, 'pending', ?, ?, NOW())
        ");

        $stmt->execute([
            $prNumber,
            $shortage['component_item_id'],
            $shortage['shortage_qty'],
            $systemUserId,
            $urgency,
            $reason
        ]);

        $prId = (int)$db->lastInsertId();

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (
                user_id, action_description, module, action_type, ip_address
            ) VALUES (?, ?, 'bom', 'auto_pr_created', 'system')
        ");
        $stmt->execute([
            $systemUserId,
            "Auto-created PR #{$prNumber} for BOM component shortage: {$shortage['component_name']} (Assembly: {$shortage['parent_name']})"
        ]);

        $created[] = [
            'pr_id' => $prId,
            'pr_number' => $prNumber,
            'component_name' => $shortage['component_name'],
            'component_sku' => $shortage['component_sku'],
            'shortage_qty' => $shortage['shortage_qty'],
            'urgency' => $urgency,
            'parent_name' => $shortage['parent_name'],
            'parent_family' => $shortage['parent_family']
        ];

        echo "   ✅ Created {$prNumber}: {$shortage['component_name']} x{$shortage['shortage_qty']} (for {$shortage['parent_name']})\n";
    }

    $db->commit();

    echo "\n========================================\n";
    echo "📊 BOM CHECK SUMMARY\n";
    echo "========================================\n";
    echo "Total BOM Assemblies: " . count($bomParents) . "\n";
    echo "Component Shortages Found: " . count($shortages) . "\n";
    echo "PRs Created: " . count($created) . "\n";
    echo "========================================\n";

    // ========================================================================
    // STEP 5: Send notifications to admins
    // ========================================================================
    if (!empty($created)) {
        echo "📧 Sending notifications...\n";

        try {
            // Format for notification
            $notificationItems = array_map(function ($pr) {
                return [
                    'name' => "{$pr['component_name']} (BOM: {$pr['parent_name']})",
                    'quantity' => 0, // Shortage
                    'reorder_level' => $pr['shortage_qty']
                ];
            }, $created);

            $notificationService->notifyLowStock($notificationItems);
            echo "   ✅ Notifications sent\n";
        } catch (Exception $e) {
            echo "   ⚠️  Notification error: " . $e->getMessage() . "\n";
        }
    }

    echo "\n✅ BOM component shortage check completed at " . date('Y-m-d H:i:s') . "\n";
    echo "========================================\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    echo "\n========================================\n";
    echo "❌ ERROR\n";
    echo "========================================\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "========================================\n";

    error_log("BOM shortage check error: " . $e->getMessage());
    exit(1);
}
