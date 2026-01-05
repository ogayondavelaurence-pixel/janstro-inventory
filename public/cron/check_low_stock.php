<?php

/**
 * ============================================================================
 * CRON JOB - CHECK LOW STOCK & CREATE PRs
 * ==================================================================================================================================================
 */
require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Services\LowStockAlertService;

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

$alertService = new LowStockAlertService();

echo "========================================\n";
echo "Janstro IMS - Low Stock Check\n";
echo date('Y-m-d H:i:s') . "\n";
echo "========================================\n\n";

// Check 1: Low stock items (reorder level)
echo "🔍 Checking low stock items...\n";
$lowStockResult = $alertService->checkAndAlertLowStock();

if ($lowStockResult['success']) {
    echo "✅ Low stock check complete\n";
    echo "   Items checked: {$lowStockResult['low_stock_items']}\n";
    echo "   PRs created: {$lowStockResult['prs_created']}\n\n";

    if (!empty($lowStockResult['details'])) {
        foreach ($lowStockResult['details'] as $pr) {
            echo "   - {$pr['pr_number']}: {$pr['item_name']} (Shortage: {$pr['shortage_qty']})\n";
        }
    }
} else {
    echo "❌ Low stock check failed: {$lowStockResult['error']}\n";
}

echo "\n";

// Check 2: Sales order shortages
echo "🔍 Checking sales order shortages...\n";
$shortageResult = $alertService->checkSalesOrderShortages();

if ($shortageResult['success']) {
    echo "✅ SO shortage check complete\n";
    echo "   Shortages checked: {$shortageResult['shortages_checked']}\n";
    echo "   PRs created: {$shortageResult['prs_created']}\n\n";

    if (!empty($shortageResult['details'])) {
        foreach ($shortageResult['details'] as $pr) {
            echo "   - {$pr['pr_number']}: SO #{$pr['sales_order_id']} - {$pr['item_name']}\n";
        }
    }
} else {
    echo "❌ SO shortage check failed: {$shortageResult['error']}\n";
}

echo "\n========================================\n";
echo "Check completed at " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";
