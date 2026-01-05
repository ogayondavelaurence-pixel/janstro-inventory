<?php

namespace Janstro\InventorySystem\Controllers;

use Janstro\InventorySystem\Services\BomService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

/**
 * ============================================================================
 * BOM CONTROLLER v3.0 - REFACTORED (Service Layer)
 * ============================================================================
 * Thin controller - delegates all BOM logic to BomService
 * Path: src/Controllers/BomController.php
 * ============================================================================
 */
class BomController
{
    private BomService $bomService;

    public function __construct()
    {
        $this->bomService = new BomService();
    }

    /**
     * GET /bom - List all BOMs grouped by parent
     */
    public function getAll(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $familyFilter = $_GET['family'] ?? null;
            $data = $this->bomService->getAllBOMs($familyFilter);
            Response::success($data, 'BOMs retrieved successfully');
        } catch (\Exception $e) {
            error_log("BomController::getAll - " . $e->getMessage());
            Response::serverError('Failed to retrieve BOMs');
        }
    }

    /**
     * GET /bom/families - Get product families summary
     */
    public function getFamilies(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $families = $this->bomService->getFamilySummary();
            Response::success(['families' => $families], 'Product families retrieved');
        } catch (\Exception $e) {
            error_log("BomController::getFamilies - " . $e->getMessage());
            Response::serverError('Failed to retrieve families');
        }
    }

    /**
     * GET /bom/templates - Get all BOM templates
     */
    public function getTemplates(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $templates = $this->bomService->getAllTemplates();
            Response::success(['templates' => $templates], 'Templates retrieved');
        } catch (\Exception $e) {
            error_log("BomController::getTemplates - " . $e->getMessage());
            Response::serverError('Failed to retrieve templates');
        }
    }

    /**
     * POST /bom/templates - Create BOM template
     */
    public function createTemplate(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['template_name'])) {
                Response::badRequest('Template name required');
                return;
            }

            $result = $this->bomService->createTemplate($data, $user->user_id);
            Response::success($result, 'Template created', 201);
        } catch (\Exception $e) {
            error_log("BomController::createTemplate - " . $e->getMessage());
            Response::serverError('Failed to create template');
        }
    }

    /**
     * POST /bom/templates/:id/apply - Apply template to parent item
     */
    public function applyTemplate(int $templateId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['parent_item_id'])) {
                Response::badRequest('parent_item_id required');
                return;
            }

            $result = $this->bomService->applyTemplate($templateId, $data['parent_item_id'], $user->user_id);
            Response::success($result, 'Template applied successfully');
        } catch (\Exception $e) {
            error_log("BomController::applyTemplate - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * GET /bom/:id/versions - Get BOM versions
     */
    public function getVersions(int $parentItemId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $versions = $this->bomService->getVersions($parentItemId);
            Response::success(['versions' => $versions], 'Versions retrieved');
        } catch (\Exception $e) {
            error_log("BomController::getVersions - " . $e->getMessage());
            Response::serverError('Failed to retrieve versions');
        }
    }

    /**
     * POST /bom - Create BOM entry
     */
    public function create(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            $required = ['parent_item_id', 'component_item_id', 'quantity_required'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::badRequest("Missing required field: {$field}");
                    return;
                }
            }

            if ((int)$data['parent_item_id'] === (int)$data['component_item_id']) {
                Response::badRequest('Parent item cannot be its own component');
                return;
            }

            $result = $this->bomService->createBOM($data, $user->user_id);
            Response::success($result, 'BOM created successfully', 201);
        } catch (\Exception $e) {
            error_log("BomController::create - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }

    /**
     * GET /bom/:id/explosion - BOM explosion tree
     */
    public function getExplosion(int $parentItemId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $tree = $this->bomService->generateExplosionTree($parentItemId);
            Response::success([
                'parent_item_id' => $parentItemId,
                'explosion_tree' => $tree
            ], 'BOM explosion generated');
        } catch (\Exception $e) {
            error_log("BomController::getExplosion - " . $e->getMessage());
            Response::serverError('Failed to generate BOM explosion');
        }
    }

    /**
     * DELETE /bom/:id - Delete BOM entry
     */
    public function delete(int $bomId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$user) return;

        try {
            $result = $this->bomService->deleteBOM($bomId, $user->user_id);
            Response::success($result, 'BOM deleted successfully');
        } catch (\Exception $e) {
            error_log("BomController::delete - " . $e->getMessage());
            Response::serverError($e->getMessage());
        }
    }
}
