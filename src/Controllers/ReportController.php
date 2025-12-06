<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\ReportService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * Report Controller
 * Handles reporting and analytics API endpoints
 * ISO/IEC 25010: Functional Suitability, Usability
 */
class ReportController
{
    private ReportService $reportService;

    public function __construct()
    {
        $this->reportService = new ReportService();
    }

    /**
     * GET /api/reports/dashboard
     * Get dashboard statistics
     */
    public function getDashboardStats(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $stats = $this->reportService->getDashboardStats();
            Response::success($stats, 'Dashboard stats retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/inventory-summary
     * Get inventory summary report
     */
    public function getInventorySummary(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $summary = $this->reportService->getInventorySummary();
            Response::success($summary, 'Inventory summary retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/transactions
     * Get transaction history
     */
    public function getTransactionHistory(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $type = $_GET['type'] ?? null; // 'IN' or 'OUT'

            $transactions = $this->reportService->getTransactionHistory($limit, $type);
            Response::success($transactions, 'Transaction history retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/low-stock
     * Get low stock report
     */
    public function getLowStockReport(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $items = $this->reportService->getLowStockItems();
            Response::success($items, 'Low stock items retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/purchase-orders
     * Get purchase orders report
     */
    public function getPurchaseOrdersReport(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $status = $_GET['status'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;

            $orders = $this->reportService->getPurchaseOrdersReport($status, $dateFrom, $dateTo);
            Response::success($orders, 'Purchase orders report retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/inventory-valuation
     * Get inventory valuation report
     */
    public function getInventoryValuation(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $valuation = $this->reportService->getInventoryValuation();
            Response::success($valuation, 'Inventory valuation retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/category-breakdown
     * Get inventory breakdown by category
     */
    public function getCategoryBreakdown(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $breakdown = $this->reportService->getCategoryBreakdown();
            Response::success($breakdown, 'Category breakdown retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/supplier-performance
     * Get supplier performance report
     */
    public function getSupplierPerformance(): void
    {
        // Require admin role
        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) return;

        try {
            $performance = $this->reportService->getSupplierPerformance();
            Response::success($performance, 'Supplier performance retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/audit-log
     * Get audit log report
     */
    public function getAuditLog(): void
    {
        // Require superadmin role
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) return;

        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

            $logs = $this->reportService->getAuditLog($limit, $userId);
            Response::success($logs, 'Audit log retrieved', 200);
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/reports/export
     * Export report to CSV/PDF
     */
    public function exportReport(): void
    {
        // Authenticate user
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $reportType = $_GET['type'] ?? 'inventory';
            $format = $_GET['format'] ?? 'csv'; // csv or pdf

            $data = $this->reportService->exportReport($reportType, $format);

            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d') . '.csv"');
                echo $data;
            } else {
                Response::error('PDF export not yet implemented', null, 501);
            }
        } catch (\Exception $e) {
            Response::error($e->getMessage(), null, 500);
        }
    }
}
