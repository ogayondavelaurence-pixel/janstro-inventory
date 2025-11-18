<?php

/**
 * Janstro Inventory Management System - API Router (FIXED)
 * NO COMPOSER REQUIRED - Uses Manual Autoloader
 */

// Load manual autoloader
require_once __DIR__ . '/../autoload.php';

// Error handling
if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request details
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/(janstro-inventory/)?public#', '', $path);
$path = preg_replace('#^/api#', '', $path);
$path = trim($path, '/');
$segments = explode('/', $path);

// Initialize controllers
use Janstro\InventorySystem\Controllers\AuthController;
use Janstro\InventorySystem\Controllers\InventoryController;
use Janstro\InventorySystem\Controllers\OrderController;
use Janstro\InventorySystem\Controllers\UserController;
use Janstro\InventorySystem\Controllers\SupplierController;
use Janstro\InventorySystem\Controllers\ReportController;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Services\CompleteInventoryService;

$authController = new AuthController();
$inventoryController = new InventoryController();
$orderController = new OrderController();
$userController = new UserController();
$supplierController = new SupplierController();
$reportController = new ReportController();

try {
    // ===============================================
    // HEALTH CHECK - Test this first!
    // ===============================================
    if ($path === '' || $path === 'health') {
        Response::success([
            'name' => $_ENV['APP_NAME'] ?? 'Janstro Inventory System',
            'version' => '2.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'development',
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
        switch ($method) {
            case 'POST':
                if ($segments[1] === 'login') {
                    $authController->login();
                } elseif ($segments[1] === 'logout') {
                    $authController->logout();
                } else {
                    Response::notFound('Auth endpoint not found');
                }
                break;
            case 'GET':
                if ($segments[1] === 'me') {
                    $authController->getCurrentUser();
                } else {
                    Response::notFound('Auth endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // INVENTORY ROUTES
    // ===============================================
    elseif ($segments[0] === 'inventory') {
        $inventoryService = new CompleteInventoryService();

        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $inventoryController->getAll();
                } elseif ($segments[1] === 'status') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) return;
                    $status = $inventoryService->getInventoryStatus();
                    Response::success($status, 'Inventory status retrieved');
                } elseif ($segments[1] === 'check-stock' && isset($_GET['item_id'])) {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) return;
                    $stock = $inventoryService->checkStockAvailability((int)$_GET['item_id']);
                    Response::success($stock, 'Stock checked');
                } elseif ($segments[1] === 'movements') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) return;
                    $filters = [
                        'item_id' => $_GET['item_id'] ?? null,
                        'type' => $_GET['type'] ?? null,
                        'date_from' => $_GET['date_from'] ?? null,
                        'date_to' => $_GET['date_to'] ?? null
                    ];
                    $movements = $inventoryService->getMaterialDocuments($filters);
                    Response::success($movements, 'Stock movements retrieved');
                } elseif ($segments[1] === 'low-stock') {
                    $inventoryController->getLowStock();
                } elseif (is_numeric($segments[1])) {
                    $inventoryController->getById((int)$segments[1]);
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            case 'POST':
                if (!isset($segments[1])) {
                    $inventoryController->create();
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // PURCHASE ORDER ROUTES
    // ===============================================
    elseif ($segments[0] === 'purchase-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) return;

        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $orderController->getAll();
                } elseif (is_numeric($segments[1])) {
                    $orderController->getById((int)$segments[1]);
                } else {
                    Response::notFound('Purchase order endpoint not found');
                }
                break;

            case 'POST':
                if (!isset($segments[1])) {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $input['created_by'] = $user->user_id;
                    $result = $inventoryService->createPurchaseOrder($input);
                    Response::success($result, 'Purchase order created', 201);
                } elseif ($segments[1] === 'receive' && isset($segments[2])) {
                    $poId = (int)$segments[2];
                    $input = json_decode(file_get_contents('php://input'), true);
                    $input['user_id'] = $user->user_id;
                    $result = $inventoryService->receiveGoods($poId, $input);
                    Response::success($result, 'Goods received successfully');
                } else {
                    Response::notFound('Purchase order endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // SALES ORDER ROUTES
    // ===============================================
    elseif ($segments[0] === 'sales-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) return;

        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $orders = $inventoryService->getAllSalesOrders();
                    Response::success($orders, 'Sales orders retrieved');
                } else {
                    Response::notFound('Sales order endpoint not found');
                }
                break;

            case 'POST':
                if (!isset($segments[1])) {
                    $input = json_decode(file_get_contents('php://input'), true);
                    $input['created_by'] = $user->user_id;
                    $result = $inventoryService->createSimpleSalesOrder($input);
                    Response::success($result, 'Sales order created', 201);
                } elseif ($segments[1] === 'invoice' && isset($segments[2])) {
                    $salesOrderId = (int)$segments[2];
                    $result = $inventoryService->processSimpleInvoice($salesOrderId, $user->user_id);
                    Response::success($result, 'Invoice processed successfully');
                } else {
                    Response::notFound('Sales order endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // SUPPLIER ROUTES - FIXED
    // ===============================================
    elseif ($segments[0] === 'suppliers') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    // GET /suppliers
                    $supplierController->getAll();
                } elseif (is_numeric($segments[1])) {
                    // GET /suppliers/{id}
                    $supplierController->getById((int)$segments[1]);
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;

            case 'POST':
                if (!isset($segments[1])) {
                    // POST /suppliers
                    $supplierController->create();
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;

            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    // PUT /suppliers/{id}
                    $supplierController->update((int)$segments[1]);
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;

            case 'DELETE':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    // DELETE /suppliers/{id}
                    $supplierController->delete((int)$segments[1]);
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // USER ROUTES
    // ===============================================
    elseif ($segments[0] === 'users') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $userController->getAll();
                } elseif ($segments[1] === 'roles') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::requireRole(['superadmin']);
                    if (!$user) return;
                    $userService = new \Janstro\InventorySystem\Services\UserService();
                    $roles = $userService->getRoles();
                    Response::success($roles, 'Roles retrieved');
                } elseif (is_numeric($segments[1])) {
                    $userController->getById((int)$segments[1]);
                } else {
                    Response::notFound('User endpoint not found');
                }
                break;

            case 'POST':
                $userController->create();
                break;

            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $userController->update((int)$segments[1]);
                } else {
                    Response::notFound('User endpoint not found');
                }
                break;

            case 'DELETE':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $userController->delete((int)$segments[1]);
                } else {
                    Response::notFound('User endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // REPORT ROUTES
    // ===============================================
    elseif ($segments[0] === 'reports') {
        switch ($method) {
            case 'GET':
                if ($segments[1] === 'dashboard') {
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
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
    }

    // ===============================================
    // ROUTE NOT FOUND
    // ===============================================
    else {
        Response::notFound('API endpoint not found');
    }
} catch (\Exception $e) {
    error_log("API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    if (($_ENV['APP_DEBUG'] ?? false)) {
        Response::serverError($e->getMessage());
    } else {
        Response::serverError('An internal error occurred');
    }
}
