<?php

/**
 * ============================================================================
 * ADVANCED ANALYTICS API - PHASE 9
 * ============================================================================
 * Path: public/api/analytics.php
 * 
 * Endpoints:
 * GET /analytics/dashboard       - Enhanced dashboard KPIs
 * GET /analytics/inventory        - Inventory turnover analysis
 * GET /analytics/suppliers        - Supplier performance metrics
 * GET /analytics/sales-forecast   - Sales forecasting data
 * GET /analytics/abc-analysis     - ABC inventory classification
 * GET /analytics/stock-velocity   - Stock movement velocity
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

// Parse route
preg_match('#/analytics/([a-z-]+)#', $path, $matches);
$endpoint = $matches[1] ?? '';

/**
 * ============================================================================
 * GET /analytics/dashboard - Enhanced Dashboard KPIs
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'dashboard') {
    try {
        // Total Inventory Value
        $stmt = $db->query("
            SELECT SUM(quantity * unit_price) as total_value
            FROM items
            WHERE status = 'active'
        ");
        $inventoryValue = (float)($stmt->fetchColumn() ?: 0);

        // Total Items & Categories
        $stmt = $db->query("SELECT COUNT(*) FROM items WHERE status = 'active'");
        $totalItems = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM categories");
        $totalCategories = (int)$stmt->fetchColumn();

        // Low Stock Items
        $stmt = $db->query("
            SELECT COUNT(*) FROM items 
            WHERE quantity <= reorder_level AND status = 'active'
        ");
        $lowStockCount = (int)$stmt->fetchColumn();

        // Stock Status Distribution
        $stmt = $db->query("
            SELECT 
                SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN quantity > reorder_level THEN 1 ELSE 0 END) as in_stock
            FROM items
            WHERE status = 'active'
        ");
        $stockStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sales Performance (Last 30 Days)
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM sales_orders
            WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $salesPerf = $stmt->fetch(PDO::FETCH_ASSOC);

        // Purchase Orders Status
        $stmt = $db->query("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_amount
            FROM purchase_orders
            GROUP BY status
        ");
        $poStatus = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $poStatus[$row['status']] = [
                'count' => (int)$row['count'],
                'total_amount' => (float)$row['total_amount']
            ];
        }

        // Top 5 Items by Value
        $stmt = $db->query("
            SELECT 
                item_name,
                sku,
                quantity,
                unit_price,
                (quantity * unit_price) as total_value
            FROM items
            WHERE status = 'active'
            ORDER BY total_value DESC
            LIMIT 5
        ");
        $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Transactions
        $stmt = $db->query("
            SELECT 
                t.transaction_type,
                t.quantity,
                t.movement_date,
                i.item_name,
                u.name as user_name
            FROM transactions t
            LEFT JOIN items i ON t.item_id = i.item_id
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY t.movement_date DESC
            LIMIT 10
        ");
        $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Monthly Trend (Last 6 Months)
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(order_date, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue
            FROM sales_orders
            WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month
        ");
        $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success([
            'inventory' => [
                'total_value' => round($inventoryValue, 2),
                'total_items' => $totalItems,
                'total_categories' => $totalCategories,
                'low_stock_count' => $lowStockCount,
                'low_stock_percentage' => $totalItems > 0 ? round(($lowStockCount / $totalItems) * 100, 1) : 0
            ],
            'stock_status' => [
                'out_of_stock' => (int)$stockStatus['out_of_stock'],
                'low_stock' => (int)$stockStatus['low_stock'],
                'in_stock' => (int)$stockStatus['in_stock']
            ],
            'sales_performance' => [
                'total_orders' => (int)($salesPerf['total_orders'] ?: 0),
                'total_revenue' => round((float)($salesPerf['total_revenue'] ?: 0), 2),
                'avg_order_value' => round((float)($salesPerf['avg_order_value'] ?: 0), 2)
            ],
            'purchase_orders' => $poStatus,
            'top_items' => $topItems,
            'recent_transactions' => $recentTransactions,
            'monthly_trend' => $monthlyTrend,
            'generated_at' => date('Y-m-d H:i:s')
        ], 'Dashboard KPIs retrieved');
    } catch (PDOException $e) {
        error_log("Dashboard KPIs error: " . $e->getMessage());
        Response::serverError('Failed to retrieve dashboard data');
    }
    exit;
}

/**
 * ============================================================================
 * GET /analytics/inventory - Inventory Turnover Analysis
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'inventory') {
    try {
        // Average Inventory Turnover
        $stmt = $db->query("
            SELECT 
                i.item_id,
                i.item_name,
                i.sku,
                i.quantity as current_stock,
                i.unit_price,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity ELSE 0 END), 0) as total_out,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'IN' THEN t.quantity ELSE 0 END), 0) as total_in,
                (i.quantity * i.unit_price) as inventory_value
            FROM items i
            LEFT JOIN transactions t ON i.item_id = t.item_id 
                AND t.movement_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            WHERE i.status = 'active'
            GROUP BY i.item_id
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $inventoryMetrics = [];
        foreach ($items as $item) {
            $totalOut = (int)$item['total_out'];
            $avgStock = ((int)$item['current_stock'] + $totalOut) / 2;
            $turnoverRatio = $avgStock > 0 ? round($totalOut / $avgStock, 2) : 0;

            $inventoryMetrics[] = [
                'item_id' => (int)$item['item_id'],
                'item_name' => $item['item_name'],
                'sku' => $item['sku'],
                'current_stock' => (int)$item['current_stock'],
                'unit_price' => (float)$item['unit_price'],
                'inventory_value' => (float)$item['inventory_value'],
                'units_sold_90d' => $totalOut,
                'units_received_90d' => (int)$item['total_in'],
                'turnover_ratio' => $turnoverRatio,
                'turnover_category' => $turnoverRatio >= 4 ? 'Fast Moving' : ($turnoverRatio >= 2 ? 'Normal' : 'Slow Moving')
            ];
        }

        // Sort by turnover ratio descending
        usort($inventoryMetrics, fn($a, $b) => $b['turnover_ratio'] <=> $a['turnover_ratio']);

        Response::success([
            'items' => $inventoryMetrics,
            'summary' => [
                'fast_moving' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] >= 4)),
                'normal' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] >= 2 && $i['turnover_ratio'] < 4)),
                'slow_moving' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] < 2)),
                'total_items' => count($inventoryMetrics)
            ]
        ], 'Inventory turnover analysis retrieved');
    } catch (PDOException $e) {
        error_log("Inventory analysis error: " . $e->getMessage());
        Response::serverError('Failed to analyze inventory');
    }
    exit;
}

/**
 * ============================================================================
 * GET /analytics/suppliers - Supplier Performance Metrics
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'suppliers') {
    try {
        $stmt = $db->query("
            SELECT 
                s.supplier_id,
                s.supplier_name,
                s.contact_person,
                s.phone,
                s.email,
                COUNT(po.po_id) as total_orders,
                SUM(po.total_amount) as total_value,
                AVG(po.total_amount) as avg_order_value,
                SUM(CASE WHEN po.status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN po.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN po.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                AVG(
                    CASE 
                        WHEN po.delivered_date IS NOT NULL AND po.expected_delivery_date IS NOT NULL 
                        THEN DATEDIFF(po.delivered_date, po.expected_delivery_date)
                        ELSE NULL
                    END
                ) as avg_delivery_delay,
                MAX(po.po_date) as last_order_date
            FROM suppliers s
            LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
            WHERE s.status = 'active'
            GROUP BY s.supplier_id
            ORDER BY total_value DESC
        ");

        $suppliers = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalOrders = (int)$row['total_orders'];
            $deliveredOrders = (int)$row['delivered_orders'];
            $onTimeRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;
            $avgDelay = (float)($row['avg_delivery_delay'] ?: 0);

            // Performance Score (0-100)
            $performanceScore = 100;
            if ($avgDelay > 0) $performanceScore -= min($avgDelay * 5, 30); // -5 pts per day late, max -30
            if ($onTimeRate < 100) $performanceScore -= (100 - $onTimeRate) * 0.5; // -0.5 pts per % below 100
            $performanceScore = max(0, round($performanceScore, 1));

            $suppliers[] = [
                'supplier_id' => (int)$row['supplier_id'],
                'supplier_name' => $row['supplier_name'],
                'contact_person' => $row['contact_person'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'total_orders' => $totalOrders,
                'total_value' => round((float)($row['total_value'] ?: 0), 2),
                'avg_order_value' => round((float)($row['avg_order_value'] ?: 0), 2),
                'delivered_orders' => $deliveredOrders,
                'pending_orders' => (int)$row['pending_orders'],
                'cancelled_orders' => (int)$row['cancelled_orders'],
                'on_time_delivery_rate' => $onTimeRate,
                'avg_delivery_delay_days' => round($avgDelay, 1),
                'performance_score' => $performanceScore,
                'performance_rating' => $performanceScore >= 90 ? 'Excellent' : ($performanceScore >= 75 ? 'Good' : ($performanceScore >= 60 ? 'Average' : 'Poor')),
                'last_order_date' => $row['last_order_date']
            ];
        }

        Response::success([
            'suppliers' => $suppliers,
            'summary' => [
                'total_suppliers' => count($suppliers),
                'excellent_performers' => count(array_filter($suppliers, fn($s) => $s['performance_score'] >= 90)),
                'good_performers' => count(array_filter($suppliers, fn($s) => $s['performance_score'] >= 75 && $s['performance_score'] < 90)),
                'needs_improvement' => count(array_filter($suppliers, fn($s) => $s['performance_score'] < 75))
            ]
        ], 'Supplier performance metrics retrieved');
    } catch (PDOException $e) {
        error_log("Supplier metrics error: " . $e->getMessage());
        Response::serverError('Failed to retrieve supplier metrics');
    }
    exit;
}

/**
 * ============================================================================
 * GET /analytics/sales-forecast - Sales Forecasting
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'sales-forecast') {
    try {
        // Historical sales data (last 12 months)
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(order_date, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            FROM sales_orders
            WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month
        ");
        $historical = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate trends
        $revenues = array_column($historical, 'revenue');
        $count = count($revenues);

        if ($count >= 3) {
            // Simple linear regression for next 3 months
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;

            foreach ($revenues as $x => $y) {
                $sumX += $x;
                $sumY += (float)$y;
                $sumXY += $x * (float)$y;
                $sumX2 += $x * $x;
            }

            $slope = ($count * $sumXY - $sumX * $sumY) / ($count * $sumX2 - $sumX * $sumX);
            $intercept = ($sumY - $slope * $sumX) / $count;

            // Forecast next 3 months
            $forecast = [];
            for ($i = 1; $i <= 3; $i++) {
                $forecastMonth = date('Y-m', strtotime("+$i month"));
                $forecastValue = $intercept + $slope * ($count + $i - 1);
                $forecast[] = [
                    'month' => $forecastMonth,
                    'forecast_revenue' => round(max(0, $forecastValue), 2),
                    'confidence' => 'Medium'
                ];
            }
        } else {
            $forecast = [
                ['month' => date('Y-m', strtotime('+1 month')), 'forecast_revenue' => 0, 'confidence' => 'Low'],
                ['month' => date('Y-m', strtotime('+2 month')), 'forecast_revenue' => 0, 'confidence' => 'Low'],
                ['month' => date('Y-m', strtotime('+3 month')), 'forecast_revenue' => 0, 'confidence' => 'Low']
            ];
        }

        // Growth rate calculation
        $recentRevenue = $count > 0 ? (float)$revenues[$count - 1] : 0;
        $previousRevenue = $count > 1 ? (float)$revenues[$count - 2] : 0;
        $growthRate = $previousRevenue > 0 ? round((($recentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;

        Response::success([
            'historical_data' => $historical,
            'forecast' => $forecast,
            'trends' => [
                'growth_rate_mom' => $growthRate,
                'trend_direction' => $growthRate > 0 ? 'Increasing' : ($growthRate < 0 ? 'Decreasing' : 'Stable'),
                'avg_monthly_revenue' => $count > 0 ? round(array_sum($revenues) / $count, 2) : 0
            ]
        ], 'Sales forecast generated');
    } catch (PDOException $e) {
        error_log("Sales forecast error: " . $e->getMessage());
        Response::serverError('Failed to generate forecast');
    }
    exit;
}

/**
 * ============================================================================
 * GET /analytics/abc-analysis - ABC Inventory Classification
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'abc-analysis') {
    try {
        // Get items with their value contribution
        $stmt = $db->query("
            SELECT 
                i.item_id,
                i.item_name,
                i.sku,
                i.quantity,
                i.unit_price,
                (i.quantity * i.unit_price) as inventory_value,
                c.name as category_name
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.category_id
            WHERE i.status = 'active' AND i.quantity > 0
            ORDER BY inventory_value DESC
        ");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalValue = array_sum(array_column($items, 'inventory_value'));
        $cumulativeValue = 0;

        // Classify items (A: 80%, B: 15%, C: 5%)
        $classifiedItems = [];
        foreach ($items as $item) {
            $itemValue = (float)$item['inventory_value'];
            $cumulativeValue += $itemValue;
            $cumulativePercentage = ($cumulativeValue / $totalValue) * 100;

            $classification = $cumulativePercentage <= 80 ? 'A' : ($cumulativePercentage <= 95 ? 'B' : 'C');

            $classifiedItems[] = [
                'item_id' => (int)$item['item_id'],
                'item_name' => $item['item_name'],
                'sku' => $item['sku'],
                'category_name' => $item['category_name'],
                'quantity' => (int)$item['quantity'],
                'unit_price' => (float)$item['unit_price'],
                'inventory_value' => round($itemValue, 2),
                'value_percentage' => round(($itemValue / $totalValue) * 100, 2),
                'cumulative_percentage' => round($cumulativePercentage, 2),
                'classification' => $classification
            ];
        }

        // Summary
        $classA = array_filter($classifiedItems, fn($i) => $i['classification'] === 'A');
        $classB = array_filter($classifiedItems, fn($i) => $i['classification'] === 'B');
        $classC = array_filter($classifiedItems, fn($i) => $i['classification'] === 'C');

        Response::success([
            'items' => $classifiedItems,
            'summary' => [
                'total_items' => count($classifiedItems),
                'total_value' => round($totalValue, 2),
                'class_a' => [
                    'count' => count($classA),
                    'percentage' => count($classifiedItems) > 0 ? round((count($classA) / count($classifiedItems)) * 100, 1) : 0,
                    'value' => round(array_sum(array_column($classA, 'inventory_value')), 2)
                ],
                'class_b' => [
                    'count' => count($classB),
                    'percentage' => count($classifiedItems) > 0 ? round((count($classB) / count($classifiedItems)) * 100, 1) : 0,
                    'value' => round(array_sum(array_column($classB, 'inventory_value')), 2)
                ],
                'class_c' => [
                    'count' => count($classC),
                    'percentage' => count($classifiedItems) > 0 ? round((count($classC) / count($classifiedItems)) * 100, 1) : 0,
                    'value' => round(array_sum(array_column($classC, 'inventory_value')), 2)
                ]
            ]
        ], 'ABC analysis completed');
    } catch (PDOException $e) {
        error_log("ABC analysis error: " . $e->getMessage());
        Response::serverError('Failed to perform ABC analysis');
    }
    exit;
}

/**
 * ============================================================================
 * GET /analytics/stock-velocity - Stock Movement Velocity
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'stock-velocity') {
    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

        $stmt = $db->prepare("
            SELECT 
                i.item_id,
                i.item_name,
                i.sku,
                i.quantity as current_stock,
                i.reorder_level,
                COUNT(t.transaction_id) as transaction_count,
                SUM(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity ELSE 0 END) as total_out,
                SUM(CASE WHEN t.transaction_type = 'IN' THEN t.quantity ELSE 0 END) as total_in,
                AVG(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity ELSE NULL END) as avg_out_qty
            FROM items i
            LEFT JOIN transactions t ON i.item_id = t.item_id 
                AND t.movement_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE i.status = 'active'
            GROUP BY i.item_id
            ORDER BY total_out DESC
        ");
        $stmt->execute([$days]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $velocityData = [];
        foreach ($items as $item) {
            $totalOut = (int)$item['total_out'];
            $currentStock = (int)$item['current_stock'];
            $velocity = $currentStock > 0 ? round($totalOut / $currentStock, 2) : 0;
            $daysToStockout = $totalOut > 0 ? round(($currentStock / $totalOut) * $days, 1) : 999;

            $velocityData[] = [
                'item_id' => (int)$item['item_id'],
                'item_name' => $item['item_name'],
                'sku' => $item['sku'],
                'current_stock' => $currentStock,
                'reorder_level' => (int)$item['reorder_level'],
                'total_out' => $totalOut,
                'total_in' => (int)$item['total_in'],
                'transaction_count' => (int)$item['transaction_count'],
                'avg_out_qty' => round((float)($item['avg_out_qty'] ?: 0), 2),
                'velocity_score' => $velocity,
                'estimated_days_to_stockout' => $daysToStockout,
                'risk_level' => $daysToStockout < 7 ? 'High' : ($daysToStockout < 14 ? 'Medium' : 'Low')
            ];
        }

        Response::success([
            'items' => $velocityData,
            'period_days' => $days,
            'summary' => [
                'high_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'High')),
                'medium_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'Medium')),
                'low_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'Low'))
            ]
        ], 'Stock velocity analysis retrieved');
    } catch (PDOException $e) {
        error_log("Stock velocity error: " . $e->getMessage());
        Response::serverError('Failed to analyze stock velocity');
    }
    exit;
}

Response::notFound('Analytics endpoint not found');
