<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Utils\Response;
use PDO;

/**
 * Analytics Controller
 * Provides data exports for Power BI integration
 * Based on: Topic VIII - Business Analytics (Operations Management Course)
 */
class AnalyticsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * GET /analytics/revenue
     * Export revenue data for Power BI
     */
    public function getRevenueData(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    DATE(i.generated_at) AS date,
                    SUM(i.total_amount) AS daily_revenue,
                    SUM(i.paid_amount) AS daily_collection,
                    COUNT(DISTINCT i.invoice_id) AS invoice_count,
                    COUNT(DISTINCT i.customer_id) AS customer_count,
                    AVG(i.total_amount) AS avg_invoice_value
                FROM invoices i
                WHERE i.generated_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE(i.generated_at)
                ORDER BY date DESC
            ");

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Revenue data retrieved');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve revenue data');
        }
    }

    /**
     * GET /analytics/expenses
     * Export expenses data (Purchase Orders)
     */
    public function getExpensesData(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    DATE(po.po_date) AS date,
                    s.supplier_name,
                    SUM(poi.line_total) AS daily_expenses,
                    COUNT(DISTINCT po.po_id) AS po_count,
                    AVG(poi.line_total) AS avg_po_value
                FROM purchase_orders po
                JOIN purchase_order_items poi ON po.po_id = poi.po_id
                LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
                WHERE po.po_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE(po.po_date), s.supplier_name
                ORDER BY date DESC
            ");

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Expenses data retrieved');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve expenses data');
        }
    }

    /**
     * GET /analytics/accounts-receivable
     * Export AR data for aging analysis
     */
    public function getAccountsReceivable(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    i.invoice_id,
                    i.invoice_number,
                    c.customer_name,
                    i.invoice_date,
                    i.due_date,
                    i.total_amount,
                    i.paid_amount,
                    (i.total_amount - i.paid_amount) AS balance,
                    DATEDIFF(CURDATE(), i.due_date) AS days_overdue,
                    CASE
                        WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN 'Current'
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN '1-30 Days'
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN '31-60 Days'
                        WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN '61-90 Days'
                        ELSE '90+ Days'
                    END AS aging_bucket
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.customer_id
                WHERE i.status IN ('unpaid', 'partial')
                ORDER BY i.due_date ASC
            ");

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Accounts receivable data retrieved');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve AR data');
        }
    }

    /**
     * GET /analytics/inventory-valuation
     * Export inventory value by category
     */
    public function getInventoryValuation(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stmt = $this->db->query("
                SELECT 
                    c.name AS category,
                    COUNT(i.item_id) AS item_count,
                    SUM(i.quantity) AS total_quantity,
                    SUM(i.quantity * i.unit_price) AS total_value,
                    AVG(i.unit_price) AS avg_unit_price,
                    SUM(CASE WHEN i.quantity <= i.reorder_level THEN 1 ELSE 0 END) AS low_stock_items
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.category_id
                GROUP BY c.category_id, c.name
                ORDER BY total_value DESC
            ");

            Response::success($stmt->fetchAll(PDO::FETCH_ASSOC), 'Inventory valuation data retrieved');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve inventory valuation');
        }
    }

    /**
     * GET /analytics/kpis
     * Key Performance Indicators dashboard
     */
    public function getKPIs(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $kpis = [];

            // Revenue KPI
            $stmt = $this->db->query("
                SELECT 
                    SUM(total_amount) AS total_revenue,
                    SUM(paid_amount) AS total_collected,
                    COUNT(*) AS invoice_count
                FROM invoices
                WHERE MONTH(invoice_date) = MONTH(CURDATE())
                  AND YEAR(invoice_date) = YEAR(CURDATE())
            ");
            $kpis['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Expenses KPI
            $stmt = $this->db->query("
                SELECT SUM(poi.line_total) AS total_expenses
                FROM purchase_orders po
                JOIN purchase_order_items poi ON po.po_id = poi.po_id
                WHERE MONTH(po.po_date) = MONTH(CURDATE())
                  AND YEAR(po.po_date) = YEAR(CURDATE())
            ");
            $kpis['monthly_expenses'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Inventory Turnover
            $stmt = $this->db->query("
                SELECT 
                    SUM(quantity) AS total_stock,
                    SUM(quantity * unit_price) AS total_value
                FROM items
            ");
            $kpis['inventory_status'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Outstanding AR
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) AS unpaid_invoices,
                    SUM(total_amount - paid_amount) AS total_outstanding
                FROM invoices
                WHERE status IN ('unpaid', 'partial')
            ");
            $kpis['accounts_receivable'] = $stmt->fetch(PDO::FETCH_ASSOC);

            Response::success($kpis, 'KPIs retrieved successfully');
        } catch (\Exception $e) {
            Response::serverError('Failed to retrieve KPIs');
        }
    }

    /**
     * GET /analytics/export/csv/{type}
     * Export data as CSV for Power BI import
     */
    public function exportCSV(string $type): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = [];

            switch ($type) {
                case 'revenue':
                    $stmt = $this->db->query("SELECT * FROM invoices");
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'expenses':
                    $stmt = $this->db->query("SELECT po.*, poi.* FROM purchase_orders po JOIN purchase_order_items poi ON po.po_id = poi.po_id");
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case 'inventory':
                    $stmt = $this->db->query("SELECT * FROM items");
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                default:
                    Response::notFound('Invalid export type');
                    return;
            }

            // Generate CSV
            $csv = $this->arrayToCSV($data);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="janstro_' . $type . '_' . date('Y-m-d') . '.csv"');
            echo $csv;
            exit;
        } catch (\Exception $e) {
            Response::serverError('Failed to export data');
        }
    }

    /**
     * Helper: Convert array to CSV
     */
    private function arrayToCSV(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
