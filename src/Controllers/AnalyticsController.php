<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\AnalyticsService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * ANALYTICS CONTROLLER v1.0 - REFACTORED (Service Layer)
 * ============================================================================
 * Thin controller - delegates all analytics logic to AnalyticsService
 * Path: src/Controllers/AnalyticsController.php
 * ============================================================================
 */
class AnalyticsController
{
    private AnalyticsService $analyticsService;

    public function __construct()
    {
        $this->analyticsService = new AnalyticsService();
    }

    /**
     * GET /analytics/dashboard
     * Complete dashboard KPIs
     */
    public function getDashboard(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->getDashboardKPIs();
            Response::success($data, 'Dashboard KPIs retrieved');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getDashboard - " . $e->getMessage());
            Response::serverError('Failed to retrieve dashboard data');
        }
    }

    /**
     * GET /analytics/inventory
     * Value-based inventory turnover analysis
     */
    public function getInventoryAnalysis(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->getInventoryTurnoverAnalysis();
            Response::success($data, 'Inventory analysis retrieved');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getInventoryAnalysis - " . $e->getMessage());
            Response::serverError('Failed to analyze inventory');
        }
    }

    /**
     * GET /analytics/suppliers
     * Industry-weighted supplier performance scoring
     */
    public function getSupplierPerformance(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->getSupplierPerformanceMetrics();
            Response::success($data, 'Supplier metrics retrieved');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getSupplierPerformance - " . $e->getMessage());
            Response::serverError('Failed to retrieve supplier metrics');
        }
    }

    /**
     * GET /analytics/sales-forecast
     * Exponential smoothing with seasonality detection
     */
    public function getSalesForecast(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->generateSalesForecast();
            Response::success($data, 'Sales forecast generated');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getSalesForecast - " . $e->getMessage());
            Response::serverError('Failed to generate forecast');
        }
    }

    /**
     * GET /analytics/abc-analysis
     * ABC inventory classification (80-15-5 rule)
     */
    public function getABCAnalysis(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->performABCAnalysis();
            Response::success($data, 'ABC analysis completed');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getABCAnalysis - " . $e->getMessage());
            Response::serverError('Failed to perform ABC analysis');
        }
    }

    /**
     * GET /analytics/stock-velocity?days=30
     * Stock velocity with safety stock calculations
     */
    public function getStockVelocity(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
            $data = $this->analyticsService->analyzeStockVelocity($days);
            Response::success($data, 'Stock velocity analysis retrieved');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getStockVelocity - " . $e->getMessage());
            Response::serverError('Failed to analyze stock velocity');
        }
    }

    /**
     * GET /analytics/pr-turnaround
     * Purchase requisition turnaround time analysis
     */
    public function getPRTurnaround(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = $this->analyticsService->analyzePRTurnaround();
            Response::success($data, 'PR turnaround metrics retrieved');
        } catch (\Exception $e) {
            error_log("AnalyticsController::getPRTurnaround - " . $e->getMessage());
            Response::serverError('Failed to retrieve PR metrics');
        }
    }
}
