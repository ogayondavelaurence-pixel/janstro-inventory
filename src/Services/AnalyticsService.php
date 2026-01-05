<?php

namespace Janstro\InventorySystem\Services;

use Janstro\InventorySystem\Config\Database;
use PDO;
use Exception;

/**
 * ============================================================================
 * ANALYTICS SERVICE v1.0 - PRODUCTION READY
 * ============================================================================
 * Complete analytics business logic with industry-standard algorithms
 * Path: src/Services/AnalyticsService.php
 * ============================================================================
 */
class AnalyticsService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ========================================================================
    // DASHBOARD KPIs - ENHANCED
    // ========================================================================

    public function getDashboardKPIs(): array
    {
        // Total Inventory Value
        $stmt = $this->db->query("
            SELECT SUM(quantity * unit_price) as total_value
            FROM items WHERE status = 'active'
        ");
        $inventoryValue = (float)($stmt->fetchColumn() ?: 0);

        // Total Items & Categories
        $stmt = $this->db->query("SELECT COUNT(*) FROM items WHERE status = 'active'");
        $totalItems = (int)$stmt->fetchColumn();

        $stmt = $this->db->query("SELECT COUNT(*) FROM categories");
        $totalCategories = (int)$stmt->fetchColumn();

        // Low Stock Items
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM items 
            WHERE quantity <= reorder_level AND status = 'active'
        ");
        $lowStockCount = (int)$stmt->fetchColumn();

        // Stock Status Distribution
        $stmt = $this->db->query("
            SELECT 
                SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN quantity > reorder_level THEN 1 ELSE 0 END) as in_stock
            FROM items WHERE status = 'active'
        ");
        $stockStatus = $stmt->fetch(PDO::FETCH_ASSOC);

        // Order Fulfillment Rate (Last 30 Days)
        $stmt = $this->db->query("
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

        // Value-based Inventory Turnover (Last 90 Days)
        $stmt = $this->db->query("
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

        // Stock Accuracy Proxy
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN quantity >= 0 THEN 1 ELSE 0 END) as valid_items
            FROM items WHERE status = 'active'
        ");
        $accuracyData = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockAccuracy = $totalItems > 0 ? round(((int)$accuracyData['valid_items'] / $totalItems) * 100, 1) : 100;

        // Purchase Orders Status
        $stmt = $this->db->query("
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
        $stmt = $this->db->query("
            SELECT 
                item_name, sku, quantity, unit_price,
                (quantity * unit_price) as total_value
            FROM items WHERE status = 'active'
            ORDER BY total_value DESC LIMIT 5
        ");
        $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Recent Transactions
        $stmt = $this->db->query("
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
        $stmt = $this->db->query("
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

        return [
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
        ];
    }

    // ========================================================================
    // INVENTORY TURNOVER ANALYSIS - VALUE-BASED
    // ========================================================================

    public function getInventoryTurnoverAnalysis(): array
    {
        $stmt = $this->db->query("
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

            // Value-based turnover
            $avgStock = ($currentStock + $totalOut) / 2;
            $cogs = $totalOut * $unitPrice;
            $avgInventoryValue = $avgStock * $unitPrice;
            $turnoverRatio = $avgInventoryValue > 0 ? round($cogs / $avgInventoryValue, 2) : 0;

            // Demand variability
            $demandMetrics = $this->calculateDemandVariability($item['item_id']);

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

        return [
            'items' => $inventoryMetrics,
            'summary' => [
                'fast_moving' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] >= 4)),
                'normal' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] >= 2 && $i['turnover_ratio'] < 4)),
                'slow_moving' => count(array_filter($inventoryMetrics, fn($i) => $i['turnover_ratio'] < 2)),
                'total_items' => count($inventoryMetrics)
            ]
        ];
    }

    // ========================================================================
    // SUPPLIER PERFORMANCE - INDUSTRY WEIGHTED
    // ========================================================================

    public function getSupplierPerformanceMetrics(): array
    {
        $stmt = $this->db->query("
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

            // Industry-standard weighted scoring
            $onTimeScore = $onTimeRate;
            $deliveryScore = max(0, 100 - ($avgDelay * 10));
            $reliabilityScore = $totalOrders > 0 ? round((($totalOrders - $cancelledOrders) / $totalOrders) * 100, 1) : 100;

            // Weighted: 40% on-time + 40% delivery speed + 20% reliability
            $performanceScore = round(
                ($onTimeScore * 0.4) + ($deliveryScore * 0.4) + ($reliabilityScore * 0.2),
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

        return [
            'suppliers' => $suppliers,
            'summary' => [
                'total_suppliers' => count($suppliers),
                'excellent_performers' => count(array_filter($suppliers, fn($s) => $s['performance_score'] >= 90)),
                'good_performers' => count(array_filter($suppliers, fn($s) => $s['performance_score'] >= 75 && $s['performance_score'] < 90)),
                'needs_improvement' => count(array_filter($suppliers, fn($s) => $s['performance_score'] < 75))
            ]
        ];
    }

    // ========================================================================
    // SALES FORECAST - EXPONENTIAL SMOOTHING + SEASONALITY
    // ========================================================================

    public function generateSalesForecast(): array
    {
        $stmt = $this->db->query("
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
            // Exponential smoothing (alpha = 0.3)
            $alpha = 0.3;
            $smoothed = [$revenues[0]];

            for ($i = 1; $i < $count; $i++) {
                $smoothed[] = ($alpha * $revenues[$i]) + ((1 - $alpha) * $smoothed[$i - 1]);
            }

            // Seasonality detection
            $seasonalFactors = $this->detectSeasonality($revenues);

            // Linear trend
            $sumX = $sumY = $sumXY = $sumX2 = 0;
            foreach ($smoothed as $x => $y) {
                $sumX += $x;
                $sumY += $y;
                $sumXY += $x * $y;
                $sumX2 += $x * $x;
            }

            $slope = ($count * $sumXY - $sumX * $sumY) / ($count * $sumX2 - $sumX * $sumX);
            $intercept = ($sumY - $slope * $sumX) / $count;

            // Standard error for confidence intervals
            $residuals = [];
            foreach ($smoothed as $x => $actualY) {
                $predictedY = $intercept + ($slope * $x);
                $residuals[] = pow($actualY - $predictedY, 2);
            }
            $standardError = sqrt(array_sum($residuals) / max(1, $count - 2));

            // Forecast next 3 months
            $forecast = [];
            for ($i = 1; $i <= 3; $i++) {
                $forecastMonth = date('Y-m', strtotime("+$i month"));
                $baseValue = $intercept + ($slope * ($count + $i - 1));

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

        // Growth rate
        $recentRevenue = $count > 0 ? (float)$revenues[$count - 1] : 0;
        $previousRevenue = $count > 1 ? (float)$revenues[$count - 2] : 0;
        $growthRate = $previousRevenue > 0 ? round((($recentRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : 0;

        return [
            'historical_data' => $historical,
            'forecast' => $forecast,
            'trends' => [
                'growth_rate_mom' => $growthRate,
                'trend_direction' => $growthRate > 0 ? 'Increasing' : ($growthRate < 0 ? 'Decreasing' : 'Stable'),
                'avg_monthly_revenue' => $count > 0 ? round(array_sum($revenues) / $count, 2) : 0,
                'volatility' => $count > 1 ? round($this->calculateStandardDeviation($revenues), 2) : 0
            ]
        ];
    }

    // ========================================================================
    // ABC ANALYSIS - INVENTORY CLASSIFICATION
    // ========================================================================

    public function performABCAnalysis(): array
    {
        $stmt = $this->db->query("
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

        return [
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
        ];
    }

    // ========================================================================
    // STOCK VELOCITY - WITH SAFETY STOCK
    // ========================================================================

    public function analyzeStockVelocity(int $days = 30): array
    {
        $stmt = $this->db->prepare("
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

            // Safety stock calculation (1.65 * std dev * sqrt(lead time))
            $demandMetrics = $this->calculateDemandVariability($item['item_id']);
            $leadTimeDays = 7;
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

        return [
            'items' => $velocityData,
            'period_days' => $days,
            'summary' => [
                'high_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'High')),
                'medium_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'Medium')),
                'low_risk_items' => count(array_filter($velocityData, fn($i) => $i['risk_level'] === 'Low'))
            ]
        ];
    }

    // ========================================================================
    // PR TURNAROUND ANALYSIS
    // ========================================================================

    public function analyzePRTurnaround(): array
    {
        $stmt = $this->db->query("
            SELECT 
                pr.pr_id, pr.pr_number, pr.item_id, pr.sales_order_id,
                pr.urgency, pr.status, pr.created_at, pr.approved_at,
                pr.converted_to_po_id, i.item_name,
                TIMESTAMPDIFF(HOUR, pr.created_at, pr.approved_at) as approval_time_hours,
                TIMESTAMPDIFF(HOUR, pr.approved_at, po.po_date) as conversion_time_hours,
                TIMESTAMPDIFF(HOUR, po.po_date, po.delivered_date) as fulfillment_time_hours,
                TIMESTAMPDIFF(HOUR, pr.created_at, po.delivered_date) as total_cycle_hours,
                po.po_date, po.delivered_date, po.status as po_status
            FROM purchase_requisitions pr
            LEFT JOIN items i ON pr.item_id = i.item_id
            LEFT JOIN purchase_orders po ON pr.converted_to_po_id = po.po_id
            WHERE pr.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY pr.created_at DESC
        ");

        $allPRs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate metrics
        $metrics = [
            'total_prs' => count($allPRs),
            'pending_prs' => 0,
            'approved_prs' => 0,
            'converted_prs' => 0,
            'fulfilled_prs' => 0,
            'rejected_prs' => 0,
            'avg_approval_time' => 0,
            'avg_conversion_time' => 0,
            'avg_fulfillment_time' => 0,
            'avg_total_cycle_time' => 0,
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

            $urgency = strtolower($pr['urgency']);
            if (isset($metrics['by_urgency'][$urgency])) {
                $metrics['by_urgency'][$urgency]['count']++;
            }

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

                if (isset($urgencyCycleTimes[$urgency])) {
                    $urgencyCycleTimes[$urgency][] = (float)$pr['total_cycle_hours'];
                }
            }
        }

        // Calculate averages
        $metrics['avg_approval_time'] = !empty($approvalTimes) ? round(array_sum($approvalTimes) / count($approvalTimes), 1) : 0;
        $metrics['avg_conversion_time'] = !empty($conversionTimes) ? round(array_sum($conversionTimes) / count($conversionTimes), 1) : 0;
        $metrics['avg_fulfillment_time'] = !empty($fulfillmentTimes) ? round(array_sum($fulfillmentTimes) / count($fulfillmentTimes), 1) : 0;
        $metrics['avg_total_cycle_time'] = !empty($totalCycleTimes) ? round(array_sum($totalCycleTimes) / count($totalCycleTimes), 1) : 0;

        foreach ($urgencyCycleTimes as $urgency => $times) {
            if (!empty($times)) {
                $metrics['by_urgency'][$urgency]['avg_cycle_time'] = round(array_sum($times) / count($times), 1);
            }
        }

        // Identify bottlenecks
        $bottlenecks = [];
        if ($metrics['avg_approval_time'] > 24) {
            $bottlenecks[] = [
                'stage' => 'approval',
                'severity' => 'high',
                'message' => "Avg approval time is {$metrics['avg_approval_time']} hours (target: < 24h)",
                'recommendation' => 'Delegate approval authority or implement auto-approval for low-value PRs'
            ];
        }

        return [
            'summary' => $metrics,
            'bottlenecks' => $bottlenecks,
            'period' => '90 days',
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    // ========================================================================
    // HELPER FUNCTIONS
    // ========================================================================

    private function calculateDemandVariability(int $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT quantity 
            FROM transactions 
            WHERE item_id = ? AND transaction_type = 'OUT'
            AND movement_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ORDER BY movement_date
        ");
        $stmt->execute([$itemId]);
        $demands = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'quantity');

        if (count($demands) < 2) {
            return ['cv' => 0, 'mad' => 0, 'std_dev' => 0, 'mean_demand' => 0];
        }

        $mean = array_sum($demands) / count($demands);
        $stdDev = $this->calculateStandardDeviation($demands);
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

    private function calculateStandardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) return 0;

        $mean = array_sum($values) / $count;
        $variance = array_sum(array_map(fn($v) => pow($v - $mean, 2), $values)) / ($count - 1);

        return sqrt($variance);
    }

    private function detectSeasonality(array $revenues): array
    {
        if (count($revenues) < 12) {
            return array_fill(0, 12, 1.0);
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
}
