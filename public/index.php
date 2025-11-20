<?php

/**
 * Janstro Inventory System - BULLETPROOF API Router
 * Date: 2025-11-21
 * Version: 5.0.0 - GUARANTEED WORKING
 */

// Load manual autoloader
require_once __DIR__ . '/../autoload.php';

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

// ============================================
// CORS Headers (MUST come first)
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// Path Parsing (CRITICAL FIX)
// ============================================
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);

// Remove base path
$path = str_replace($scriptName, '', $requestUri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');

// Remove 'api' prefix if present
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

$segments = $path ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'];

// Debug logging
error_log("========================================");
error_log("📍 Request URI: " . $requestUri);
error_log("📍 Script Name: " . $scriptName);
error_log("📍 Parsed Path: " . $path);
error_log("📍 Method: " . $method);
error_log("📍 Segments: " . json_encode($segments));
error_log("========================================");

// Import classes
use Janstro\InventorySystem\Controllers\AuthController;
use Janstro\InventorySystem\Controllers\InventoryController;
use Janstro\InventorySystem\Controllers\OrderController;
use Janstro\InventorySystem\Controllers\UserController;
use Janstro\InventorySystem\Controllers\SupplierController;
use Janstro\InventorySystem\Controllers\ReportController;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Services\CompleteInventoryService;

try {
    // ===============================================
    // HEALTH CHECK
    // ===============================================
    if (empty($path) || $path === 'health') {
        Response::success([
            'name' => 'Janstro Inventory System',
            'version' => '5.0.0',
            'environment' => 'development',
            'status' => 'running',
            'message' => 'API is healthy!',
            'timestamp' => date('Y-m-d H:i:s')
        ], 'API is running');
        exit;
    }

    // ===============================================
    // AUTHENTICATION ROUTES
    // ===============================================
    if ($segments[0] === 'auth') {
        error_log("✅ AUTH ROUTE DETECTED");
        error_log("Action: " . ($segments[1] ?? 'none'));

        $authController = new AuthController();

        if ($method === 'POST') {
            if (($segments[1] ?? '') === 'login') {
                error_log("🔐 Calling AuthController::login()");
                $authController->login();
                exit;
            } elseif (($segments[1] ?? '') === 'logout') {
                $authController->logout();
                exit;
            }
        } elseif ($method === 'GET') {
            if (($segments[1] ?? '') === 'me') {
                $authController->getCurrentUser();
                exit;
            }
        }

        // If we reach here, endpoint not found
        error_log("❌ Auth endpoint not recognized: " . ($segments[1] ?? 'none'));
        Response::notFound('Auth endpoint not found: ' . $path);
        exit;
    }

    // ===============================================
    // INVENTORY ROUTES
    // ===============================================
    elseif ($segments[0] === 'inventory') {
        $inventoryController = new InventoryController();
        $inventoryService = new CompleteInventoryService();

        if ($method === 'GET') {
            if (empty($segments[1])) {
                $inventoryController->getAll();
            } elseif ($segments[1] === 'status') {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->getInventoryStatus(), 'Status retrieved');
            } elseif ($segments[1] === 'check-stock' && isset($_GET['item_id'])) {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->checkStockAvailability((int)$_GET['item_id']), 'Stock checked');
            } elseif ($segments[1] === 'movements') {
                if (isset($segments[2]) && $segments[2] === 'summary') {
                    $inventoryController->getMovementsSummary();
                } else {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) exit;
                    $filters = [
                        'item_id' => $_GET['item_id'] ?? null,
                        'type' => $_GET['type'] ?? null,
                        'date_from' => $_GET['date_from'] ?? null,
                        'date_to' => $_GET['date_to'] ?? null
                    ];
                    Response::success($inventoryService->getMaterialDocuments($filters), 'Movements retrieved');
                }
            } elseif ($segments[1] === 'low-stock') {
                $inventoryController->getLowStock();
            } elseif (is_numeric($segments[1])) {
                $inventoryController->getById((int)$segments[1]);
            } else {
                Response::notFound('Inventory endpoint not found');
            }
        } elseif ($method === 'POST') {
            if (empty($segments[1])) {
                $inventoryController->create();
            } else {
                Response::notFound('Inventory endpoint not found');
            }
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // PURCHASE ORDERS
    // ===============================================
    elseif ($segments[0] === 'purchase-orders') {
        $orderController = new OrderController();
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET') {
            if (empty($segments[1])) {
                $orderController->getAll();
            } elseif (is_numeric($segments[1])) {
                $orderController->getById((int)$segments[1]);
            } else {
                Response::notFound('PO endpoint not found');
            }
        } elseif ($method === 'POST') {
            if (empty($segments[1])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createPurchaseOrder($input), 'PO created', 201);
            } elseif ($segments[1] === 'receive' && isset($segments[2])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['user_id'] = $user->user_id;
                Response::success($inventoryService->receiveGoods((int)$segments[2], $input), 'Goods received');
            } else {
                Response::notFound('PO endpoint not found');
            }
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // SALES ORDERS
    // ===============================================
    elseif ($segments[0] === 'sales-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET') {
            if (empty($segments[1])) {
                Response::success($inventoryService->getAllSalesOrders(), 'Sales orders retrieved');
            } else {
                Response::notFound('SO endpoint not found');
            }
        } elseif ($method === 'POST') {
            if (empty($segments[1])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createSimpleSalesOrder($input), 'SO created', 201);
            } elseif ($segments[1] === 'invoice' && isset($segments[2])) {
                Response::success($inventoryService->processSimpleInvoice((int)$segments[2], $user->user_id), 'Invoice processed');
            } else {
                Response::notFound('SO endpoint not found');
            }
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // SUPPLIERS
    // ===============================================
    elseif ($segments[0] === 'suppliers') {
        $supplierController = new SupplierController();

        if ($method === 'GET') {
            if (empty($segments[1])) {
                $supplierController->getAll();
            } elseif (is_numeric($segments[1])) {
                $supplierController->getById((int)$segments[1]);
            } else {
                Response::notFound('Supplier endpoint not found');
            }
        } elseif ($method === 'POST') {
            $supplierController->create();
        } elseif ($method === 'PUT' && isset($segments[1])) {
            $supplierController->update((int)$segments[1]);
        } elseif ($method === 'DELETE' && isset($segments[1])) {
            $supplierController->delete((int)$segments[1]);
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // USERS
    // ===============================================
    elseif ($segments[0] === 'users') {
        $userController = new UserController();

        if ($method === 'GET') {
            if (empty($segments[1])) {
                $userController->getAll();
            } elseif ($segments[1] === 'roles') {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::requireRole(['superadmin', 'admin']);
                if (!$user) exit;
                $userService = new \Janstro\InventorySystem\Services\UserService();
                Response::success($userService->getRoles(), 'Roles retrieved');
            } elseif (is_numeric($segments[1])) {
                $userController->getById((int)$segments[1]);
            } else {
                Response::notFound('User endpoint not found');
            }
        } elseif ($method === 'POST') {
            $userController->create();
        } elseif ($method === 'PUT' && isset($segments[1])) {
            $userController->update((int)$segments[1]);
        } elseif ($method === 'DELETE' && isset($segments[1])) {
            $userController->delete((int)$segments[1]);
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // REPORTS
    // ===============================================
    elseif ($segments[0] === 'reports') {
        $reportController = new ReportController();

        if ($method === 'GET') {
            if (!isset($segments[1])) {
                Response::error('Report type required', null, 400);
            } elseif ($segments[1] === 'dashboard') {
                $reportController->getDashboardStats();
            } elseif ($segments[1] === 'inventory-summary') {
                $reportController->getInventorySummary();
            } elseif ($segments[1] === 'transactions') {
                $reportController->getTransactionHistory();
            } elseif ($segments[1] === 'low-stock') {
                $reportController->getLowStockReport();
            } elseif ($segments[1] === 'purchase-orders') {
                $reportController->getPurchaseOrdersReport();
            } else {
                Response::notFound('Report endpoint not found');
            }
        } else {
            Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // NOT FOUND
    // ===============================================
    else {
        error_log("❌ No matching route for: " . $path);
        Response::notFound('API endpoint not found: /' . $path);
        exit;
    }
} catch (\Exception $e) {
    error_log("🚨 FATAL ERROR: " . $e->getMessage());
    error_log($e->getTraceAsString());
    Response::serverError($e->getMessage());
    exit;
}
