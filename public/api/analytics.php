<?php

/**
 * ============================================================================
 * ADVANCED ANALYTICS API - PRODUCTION v2.0
 * ============================================================================
 * FIXES APPLIED:
 * ✅ Value-based inventory turnover (COGS/Avg Inventory Value)
 * ✅ Industry-standard supplier scoring (weighted metrics)
 * ✅ Exponential smoothing for sales forecast
 * ✅ Seasonality detection (basic)
 * ✅ Safety stock calculations
 * ✅ Demand variability metrics (CV, MAD, Standard Deviation)
 * ✅ Forecast confidence intervals
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
            FROM items WHERE status = 'active'
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
            FROM items WHERE status = 'active'
        ");
        $stockStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ FIX: Accurate Order Fulfillment Rate
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM sales_orders
            WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $salesPerf = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalOrders = (int)($salesPerf['total_orders'] ?: 0);
        $completedOrders = (int)($salesPerf['completed'] ?: 0);
        $fulfillmentRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0;

        // ✅ FIX: Value-based Inventory Turnover (Last 90 Days)
        $stmt = $db->query("
            SELECT 
                SUM(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity * i.unit_price ELSE 0 END) as cogs,
                AVG(i.quantity * i.unit_price) as avg_inventory_value
            FROM transactions t
            JOIN items i ON t.item_id = i.item_id
            WHERE t.movement_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $turnoverData = $stmt->fetch(PDO::FETCH_ASSOC);
        $cogs = (float)($turnoverData['cogs'] ?: 0);
        $avgInventoryValue = (float)($turnoverData['avg_inventory_value'] ?: $inventoryValue);
        $turnoverRate = $avgInventoryValue > 0 ? round($cogs / $avgInventoryValue, 2) : 0;

        // ✅ FIX: Stock Accuracy (Physical vs System)
        // Note: Requires cycle counting data - using proxy metric
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN quantity >= 0 THEN 1 ELSE 0 END) as valid_items
            FROM items WHERE status = 'active'
        ");
        $accuracyData = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockAccuracy = $totalItems > 0 ? round(((int)$accuracyData['valid_items'] / $totalItems) * 100, 1) : 100;

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
                item_name, sku, quantity, unit_price,
                (quantity * unit_price) as total_value
            FROM items WHERE status = 'active'
            ORDER BY total_value DESC LIMIT 5
        ");
        $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Transactions
        $stmt = $db->query("
            SELECT 
                t.transaction_type, t.quantity, t.movement_date,
                i.item_name, u.name as user_name
            FROM transactions t
            LEFT JOIN items i ON t.item_id = i.item_id
            LEFT JOIN users u ON t.user_id = u.user_id
            ORDER BY t.movement_date DESC LIMIT 10
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
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'fulfillment_rate' => $fulfillmentRate,
                'total_revenue' => round((float)($salesPerf['total_revenue'] ?: 0), 2),
                'avg_order_value' => round((float)($salesPerf['avg_order_value'] ?: 0), 2)
            ],
            'metrics' => [
                'inventory_turnover' => $turnoverRate,
                'stock_accuracy' => $stockAccuracy
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
 * GET /analytics/inventory - Inventory Turnover Analysis (VALUE-BASED)
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'inventory') {
    try {
        $stmt = $db->query("
            SELECT 
                i.item_id, i.item_name, i.sku, i.quantity as current_stock,
                i.unit_price, i.reorder_level,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'OUT' THEN t.quantity ELSE 0 END), 0) as total_out,
                COALESCE(SUM(CASE WHEN t.transaction_type = 'IN' THEN t.quantity ELSE 0 END), 0) as total_in
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
            $currentStock = (int)$item['current_stock'];
            $unitPrice = (float)$item['unit_price'];

            // ✅ FIXED: Value-based turnover
            $avgStock = ($currentStock + $totalOut) / 2;
            $cogs = $totalOut * $unitPrice;
            $avgInventoryValue = $avgStock * $unitPrice;
            $turnoverRatio = $avgInventoryValue > 0 ? round($cogs / $avgInventoryValue, 2) : 0;

            // ✅ NEW: Demand Variability Metrics
            $demandMetrics = calculateDemandVariability($db, $item['item_id']);

            $inventoryMetrics[] = [
                'item_id' => (int)$item['item_id'],
                'item_name' => $item['item_name'],
                'sku' => $item['sku'],
                'current_stock' => $currentStock,
                'unit_price' => $unitPrice,
                'inventory_value' => round($currentStock * $unitPrice, 2),
                'units_sold_90d' => $totalOut,
                'units_received_90d' => (int)$item['total_in'],
                'turnover_ratio' => $turnoverRatio,
                'turnover_category' => $turnoverRatio >= 4 ? 'Fast Moving' : ($turnoverRatio >= 2 ? 'Normal' : 'Slow Moving'),
                'demand_variability' => $demandMetrics
            ];
        }

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
 * GET /analytics/suppliers - Supplier Performance (INDUSTRY WEIGHTED)
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'suppliers') {
    try {
        $stmt = $db->query("
            SELECT 
                s.supplier_id, s.supplier_name, s.contact_person, s.phone, s.email,
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
            $cancelledOrders = (int)$row['cancelled_orders'];
            $avgDelay = (float)($row['avg_delivery_delay'] ?: 0);

            // On-Time Delivery Rate
            $onTimeRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

            // ✅ FIXED: Industry-standard weighted scoring
            $onTimeScore = $onTimeRate; // 0-100
            $deliveryScore = max(0, 100 - ($avgDelay * 10)); // -10 pts per day late
            $reliabilityScore = $totalOrders > 0 ? round((($totalOrders - $cancelledOrders) / $totalOrders) * 100, 1) : 100;

            // Weighted Performance Score: 40% on-time + 40% delivery speed + 20% reliability
            $performanceScore = round(
                ($onTimeScore * 0.4) +
                    ($deliveryScore * 0.4) +
                    ($reliabilityScore * 0.2),
                1
            );

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
                'cancelled_orders' => $cancelledOrders,
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
 * GET /analytics/sales-forecast - EXPONENTIAL SMOOTHING + SEASONALITY
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'sales-forecast') {
    try {
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

        $revenues = array_column($historical, 'revenue');
        $count = count($revenues);

        if ($count >= 3) {
            // ✅ EXPONENTIAL SMOOTHING (alpha = 0.3 for stability)
            $alpha = 0.3;
            $smoothed = [$revenues[0]];

            for ($i = 1; $i < $count; $i++) {
                $smoothed[] = ($alpha * $revenues[$i]) + ((1 - $alpha) * $smoothed[$i - 1]);
            }

            // ✅ SEASONALITY DETECTION (Basic)
            $seasonalFactors = detectSeasonality($revenues);

            // Linear Trend from smoothed data
            $sumX = $sumY = $sumXY = $sumX2 = 0;
            foreach ($smoothed as $x => $y) {
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }

            $slope = ($count * $sumXY - $sumX * $sumY) / ($count * $sumX2 - $sumX * $sumX);
            $intercept = ($sumY - $slope * $sumX) / $count;

            // ✅ CONFIDENCE INTERVAL (Standard Error)
            $residuals = [];
            foreach ($smoothed as $x => $actualY) {
                $predictedY = $intercept + ($slope * $x);
                $residuals[] = pow($actualY - $predictedY, 2);
            }
            $standardError = sqrt(array_sum($residuals) / max(1, $count - 2));

            // Forecast Next 3 Months
            $forecast = [];
            for ($i = 1; $i <= 3; $i++) {
                $forecastMonth = date('Y-m', strtotime("+$i month"));
                $baseValue = $intercept + ($slope * ($count + $i - 1));

                // Apply seasonality
                $monthIndex = (int)date('n', strtotime($forecastMonth));
                $seasonalFactor = $seasonalFactors[$monthIndex - 1] ?? 1.0;
                $forecastValue = $baseValue * $seasonalFactor;

                $confidence = $standardError < 10000 ? 'High' : ($standardError < 50000 ? 'Medium' : 'Low');

                $forecast[] = [
                    'month' => $forecastMonth,
                    'forecast_revenue' => round(max(0, $forecastValue), 2),
                    'lower_bound' => round(max(0, $forecastValue - $standardError), 2),
                    'upper_bound' => round($forecastValue + $standardError, 2),
                    'confidence' => $confidence
                ];
            }
        } else {
            $forecast = [
                ['month' => date('Y-m', strtotime('+1 month')), 'forecast_revenue' => 0, 'confidence' => 'Low'],
                ['month' => date('Y-m', strtotime('+2 month')), 'forecast_revenue' => 0, 'confidence' => 'Low'],
                ['month' => date('Y-m', strtotime('+3 month')), 'forecast_revenue' => 0, 'confidence' => 'Low']
            ];
        }

        // Growth Rate
        $recentRevenue = $count > 0 ? (float)$revenues[$count - 1] : 0;
        $previousRevenue = $count > 1 ? (float)$revenues[$count - 2] : 0;
        $growthRate = $previousRevenue > 0 ? round((($recentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;

        Response::success([
            'historical_data' => $historical,
            'forecast' => $forecast,
            'trends' => [
                'growth_rate_mom' => $growthRate,
                'trend_direction' => $growthRate > 0 ? 'Increasing' : ($growthRate < 0 ? 'Decreasing' : 'Stable'),
                'avg_monthly_revenue' => $count > 0 ? round(array_sum($revenues) / $count, 2) : 0,
                'volatility' => $count > 1 ? round(calculateStandardDeviation($revenues), 2) : 0
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
        $stmt = $db->query("
            SELECT 
                i.item_id, i.item_name, i.sku, i.quantity, i.unit_price,
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
 * GET /analytics/stock-velocity - WITH SAFETY STOCK
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'stock-velocity') {
    try {
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;

        $stmt = $db->prepare("
            SELECT 
                i.item_id, i.item_name, i.sku, i.quantity as current_stock, i.reorder_level,
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

            // ✅ NEW: Safety Stock Calculation (1.65 * std dev * sqrt(lead time))
            $demandMetrics = calculateDemandVariability($db, $item['item_id']);
            $leadTimeDays = 7; // Default lead time
            $safetyStock = round(1.65 * $demandMetrics['std_dev'] * sqrt($leadTimeDays), 0);

            $velocityData[] = [
                'item_id' => (int)$item['item_id'],
                'item_name' => $item['item_name'],
                'sku' => $item['sku'],
                'current_stock' => $currentStock,
                'reorder_level' => (int)$item['reorder_level'],
                'safety_stock' => $safetyStock,
                'total_out' => $totalOut,
                'total_in' => (int)$item['total_in'],
                'transaction_count' => (int)$item['transaction_count'],
                'avg_out_qty' => round((float)($item['avg_out_qty'] ?: 0), 2),
                'velocity_score' => $velocity,
                'estimated_days_to_stockout' => $daysToStockout,
                'risk_level' => $daysToStockout < 7 ? 'High' : ($daysToStockout < 14 ? 'Medium' : 'Low'),
                'demand_variability' => $demandMetrics
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

/**
 * ============================================================================
 * HELPER FUNCTIONS
 * ============================================================================
 */

// Calculate demand variability (CV, MAD, Std Dev)
function calculateDemandVariability(PDO $db, int $itemId): array
{
    $stmt = $db->prepare("
        SELECT quantity 
        FROM transactions 
        WHERE item_id = ? AND transaction_type = 'OUT'
        AND movement_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ORDER BY movement_date
    ");
    $stmt->execute([$itemId]);
    $demands = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'quantity');

    if (count($demands) < 2) {
        return ['cv' => 0, 'mad' => 0, 'std_dev' => 0];
    }

    $mean = array_sum($demands) / count($demands);
    $stdDev = calculateStandardDeviation($demands);
    $cv = $mean > 0 ? round(($stdDev / $mean) * 100, 2) : 0;

    $deviations = array_map(fn($d) => abs($d - $mean), $demands);
    $mad = round(array_sum($deviations) / count($demands), 2);

    return [
        'cv' => $cv,
        'mad' => $mad,
        'std_dev' => round($stdDev, 2),
        'mean_demand' => round($mean, 2)
    ];
}

// Standard deviation calculation
function calculateStandardDeviation(array $values): float
{
    $count = count($values);
    if ($count < 2) return 0;

    $mean = array_sum($values) / $count;
    $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($count - 1);

    return sqrt($variance);
}

// Basic seasonality detection (monthly factors)
function detectSeasonality(array $revenues): array
{
    if (count($revenues) < 12) {
        return array_fill(0, 12, 1.0); // No seasonality
    }

    $monthlyAvg = array_sum($revenues) / count($revenues);
    $factors = [];

    for ($i = 0; $i < 12; $i++) {
        $monthValues = [];
        for ($j = $i; $j < count($revenues); $j += 12) {
            $monthValues[] = $revenues[$j];
        }

        $monthAvg = count($monthValues) > 0 ? array_sum($monthValues) / count($monthValues) : $monthlyAvg;
        $factors[] = $monthlyAvg > 0 ? round($monthAvg / $monthlyAvg, 3) : 1.0;
    }

    return $factors;
}

/**
 * ============================================================================
 * GET /analytics/pr-turnaround - Purchase Requisition Performance Metrics
 * ============================================================================
 */
if ($method === 'GET' && $endpoint === 'pr-turnaround') {
    try {
        // Get all PRs with timestamps
        $stmt = $db->query("
            SELECT 
                pr.pr_id,
                pr.pr_number,
                pr.item_id,
                pr.sales_order_id,
                pr.urgency,
                pr.status,
                pr.created_at,
                pr.approved_at,
                pr.converted_to_po_id,
                i.item_name,
                
                -- Calculate time intervals (in hours)
                TIMESTAMPDIFF(HOUR, pr.created_at, pr.approved_at) as approval_time_hours,
                
                -- Time from PR approval to PO creation
                TIMESTAMPDIFF(HOUR, pr.approved_at, po.po_date) as conversion_time_hours,
                
                -- Time from PO to delivery
                TIMESTAMPDIFF(HOUR, po.po_date, po.delivered_date) as fulfillment_time_hours,
                
                -- Total cycle time (PR created to PO delivered)
                TIMESTAMPDIFF(HOUR, pr.created_at, po.delivered_date) as total_cycle_hours,
                
                po.po_date,
                po.delivered_date,
                po.status as po_status
                
            FROM purchase_requisitions pr
            LEFT JOIN items i ON pr.item_id = i.item_id
            LEFT JOIN purchase_orders po ON pr.converted_to_po_id = po.po_id
            WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY pr.created_at DESC
        ");

        $allPRs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ====================================================================
        // CALCULATE OVERALL METRICS
        // ====================================================================
        $metrics = [
            'total_prs' => count($allPRs),
            'pending_prs' => 0,
            'approved_prs' => 0,
            'converted_prs' => 0,
            'fulfilled_prs' => 0,
            'rejected_prs' => 0,

            // Time metrics (in hours)
            'avg_approval_time' => 0,
            'avg_conversion_time' => 0,
            'avg_fulfillment_time' => 0,
            'avg_total_cycle_time' => 0,

            // By urgency
            'by_urgency' => [
                'critical' => ['count' => 0, 'avg_cycle_time' => 0],
                'high' => ['count' => 0, 'avg_cycle_time' => 0],
                'medium' => ['count' => 0, 'avg_cycle_time' => 0],
                'low' => ['count' => 0, 'avg_cycle_time' => 0]
            ]
        ];

        $approvalTimes = [];
        $conversionTimes = [];
        $fulfillmentTimes = [];
        $totalCycleTimes = [];
        $urgencyCycleTimes = ['critical' => [], 'high' => [], 'medium' => [], 'low' => []];

        foreach ($allPRs as $pr) {
            // Count by status
            switch ($pr['status']) {
                case 'pending':
                    $metrics['pending_prs']++;
                    break;
                case 'approved':
                    $metrics['approved_prs']++;
                    break;
                case 'converted':
                    $metrics['converted_prs']++;
                    break;
                case 'rejected':
                    $metrics['rejected_prs']++;
                    break;
            }

            // Track urgency
            $urgency = strtolower($pr['urgency']);
            if (isset($metrics['by_urgency'][$urgency])) {
                $metrics['by_urgency'][$urgency]['count']++;
            }

            // Collect time metrics (only for approved/converted PRs)
            if ($pr['approval_time_hours'] !== null && $pr['approval_time_hours'] > 0) {
                $approvalTimes[] = (float)$pr['approval_time_hours'];
            }

            if ($pr['conversion_time_hours'] !== null && $pr['conversion_time_hours'] > 0) {
                $conversionTimes[] = (float)$pr['conversion_time_hours'];
            }

            if ($pr['fulfillment_time_hours'] !== null && $pr['fulfillment_time_hours'] > 0) {
                $fulfillmentTimes[] = (float)$pr['fulfillment_time_hours'];
                $metrics['fulfilled_prs']++;
            }

            if ($pr['total_cycle_hours'] !== null && $pr['total_cycle_hours'] > 0) {
                $totalCycleTimes[] = (float)$pr['total_cycle_hours'];

                // Track by urgency
                if (isset($urgencyCycleTimes[$urgency])) {
                    $urgencyCycleTimes[$urgency][] = (float)$pr['total_cycle_hours'];
                }
            }
        }

        // Calculate averages
        $metrics['avg_approval_time'] = !empty($approvalTimes)
            ? round(array_sum($approvalTimes) / count($approvalTimes), 1)
            : 0;

        $metrics['avg_conversion_time'] = !empty($conversionTimes)
            ? round(array_sum($conversionTimes) / count($conversionTimes), 1)
            : 0;

        $metrics['avg_fulfillment_time'] = !empty($fulfillmentTimes)
            ? round(array_sum($fulfillmentTimes) / count($fulfillmentTimes), 1)
            : 0;

        $metrics['avg_total_cycle_time'] = !empty($totalCycleTimes)
            ? round(array_sum($totalCycleTimes) / count($totalCycleTimes), 1)
            : 0;

        // Calculate urgency averages
        foreach ($urgencyCycleTimes as $urgency => $times) {
            if (!empty($times)) {
                $metrics['by_urgency'][$urgency]['avg_cycle_time'] = round(array_sum($times) / count($times), 1);
            }
        }

        // ====================================================================
        // IDENTIFY BOTTLENECKS
        // ====================================================================
        $bottlenecks = [];

        // Bottleneck 1: Approval delays (> 24 hours)
        if ($metrics['avg_approval_time'] > 24) {
            $bottlenecks[] = [
                'stage' => 'approval',
                'severity' => 'high',
                'message' => "Avg approval time is {$metrics['avg_approval_time']} hours (target: < 24h)",
                'recommendation' => 'Delegate approval authority or implement auto-approval for low-value PRs'
            ];
        }

        // Bottleneck 2: Conversion delays (> 12 hours)
        if ($metrics['avg_conversion_time'] > 12) {
            $bottlenecks[] = [
                'stage' => 'conversion',
                'severity' => 'medium',
                'message' => "Avg conversion time is {$metrics['avg_conversion_time']} hours (target: < 12h)",
                'recommendation' => 'Create PO templates for frequently ordered items'
            ];
        }

        // Bottleneck 3: Fulfillment delays (> 168 hours = 7 days)
        if ($metrics['avg_fulfillment_time'] > 168) {
            $bottlenecks[] = [
                'stage' => 'fulfillment',
                'severity' => 'high',
                'message' => "Avg fulfillment time is " . round($metrics['avg_fulfillment_time'] / 24, 1) . " days (target: < 7 days)",
                'recommendation' => 'Review supplier performance and negotiate faster delivery terms'
            ];
        }

        // ====================================================================
        // TOP/BOTTOM PERFORMERS
        // ====================================================================

        // Filter PRs with complete cycle times
        $completedPRs = array_filter($allPRs, fn($pr) => $pr['total_cycle_hours'] !== null && $pr['total_cycle_hours'] > 0);

        // Sort by cycle time
        usort($completedPRs, fn($a, $b) => $a['total_cycle_hours'] <=> $b['total_cycle_hours']);

        $topPerformers = array_slice($completedPRs, 0, 5);
        $bottomPerformers = array_slice($completedPRs, -5);

        // ====================================================================
        // MONTHLY TREND (Last 6 months)
        // ====================================================================
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(pr.created_at, '%Y-%m') as month,
                COUNT(*) as pr_count,
                AVG(TIMESTAMPDIFF(HOUR, pr.created_at, po.delivered_date)) as avg_cycle_hours
            FROM purchase_requisitions pr
            LEFT JOIN purchase_orders po ON pr.converted_to_po_id = po.po_id
            WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND po.delivered_date IS NOT NULL
            GROUP BY month
            ORDER BY month
        ");
        $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format trend data
        $formattedTrend = array_map(function ($row) {
            return [
                'month' => $row['month'],
                'pr_count' => (int)$row['pr_count'],
                'avg_cycle_days' => round((float)$row['avg_cycle_hours'] / 24, 1)
            ];
        }, $monthlyTrend);

        // ====================================================================
        // RESPONSE
        // ====================================================================
        Response::success([
            'summary' => $metrics,
            'bottlenecks' => $bottlenecks,
            'top_performers' => array_map(function ($pr) {
                return [
                    'pr_number' => $pr['pr_number'],
                    'item_name' => $pr['item_name'],
                    'urgency' => $pr['urgency'],
                    'cycle_time_hours' => (float)$pr['total_cycle_hours'],
                    'cycle_time_days' => round((float)$pr['total_cycle_hours'] / 24, 1)
                ];
            }, $topPerformers),
            'bottom_performers' => array_map(function ($pr) {
                return [
                    'pr_number' => $pr['pr_number'],
                    'item_name' => $pr['item_name'],
                    'urgency' => $pr['urgency'],
                    'cycle_time_hours' => (float)$pr['total_cycle_hours'],
                    'cycle_time_days' => round((float)$pr['total_cycle_hours'] / 24, 1)
                ];
            }, array_reverse($bottomPerformers)),
            'monthly_trend' => $formattedTrend,
            'period' => '90 days',
            'generated_at' => date('Y-m-d H:i:s')
        ], 'PR turnaround metrics retrieved');
    } catch (PDOException $e) {
        error_log("PR turnaround metrics error: " . $e->getMessage());
        Response::serverError('Failed to retrieve PR metrics');
    }
    exit;
}

Response::notFound('Analytics endpoint not found');
