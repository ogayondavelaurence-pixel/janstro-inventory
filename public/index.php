<?php

/**
 * Janstro Inventory Management System - FIXED API Router
 * Date: 2025-11-21
 * Version: 4.0.0 - Complete Authentication Fix
 */

// Load manual autoloader
require_once __DIR__ . '/../autoload.php';

// Error handling
if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ============================================
// CORS Headers (MUST come first)
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
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

$segments = $path ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'];

// Debug logging
error_log("========================================");
error_log("📍 Request URI: " . $requestUri);
error_log("📍 Parsed Path: " . $path);
error_log("📍 Method: " . $method);
error_log("📍 Segments: " . json_encode($segments));

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
            'version' => '4.0.0',
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
        error_log("✅ AUTH ROUTE DETECTED");

        $authController = new AuthController();

        if ($method === 'POST' && ($segments[1] ?? '') === 'login') {
            error_log("🔐 Processing login request");
            $authController->login();
            exit;
        }

        if ($method === 'POST' && ($segments[1] ?? '') === 'logout') {
            $authController->logout();
            exit;
        }

        if ($method === 'GET' && ($segments[1] ?? '') === 'me') {
            $authController->getCurrentUser();
            exit;
        }

        // If we reach here, endpoint not found
        error_log("❌ Auth endpoint not recognized: " . ($segments[1] ?? 'none'));
        Response::notFound('Auth endpoint not found');
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
                exit;
            }

            if ($segments[1] === 'status') {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->getInventoryStatus(), 'Status retrieved');
                exit;
            }

            if ($segments[1] === 'check-stock' && isset($_GET['item_id'])) {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->checkStockAvailability((int)$_GET['item_id']), 'Stock checked');
                exit;
            }

            if ($segments[1] === 'movements') {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                if (!$user) exit;
                $filters = [
                    'item_id' => $_GET['item_id'] ?? null,
                    'type' => $_GET['type'] ?? null,
                    'date_from' => $_GET['date_from'] ?? null,
                    'date_to' => $_GET['date_to'] ?? null
                ];
                Response::success($inventoryService->getMaterialDocuments($filters), 'Movements retrieved');
                exit;
            }

            if ($segments[1] === 'low-stock') {
                $inventoryController->getLowStock();
                exit;
            }

            if (is_numeric($segments[1])) {
                $inventoryController->getById((int)$segments[1]);
                exit;
            }
        }

        Response::notFound('Inventory endpoint not found');
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
                exit;
            }
            if (is_numeric($segments[1])) {
                $orderController->getById((int)$segments[1]);
                exit;
            }
        }

        if ($method === 'POST') {
            if (empty($segments[1])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createPurchaseOrder($input), 'PO created', 201);
                exit;
            }

            if ($segments[1] === 'receive' && isset($segments[2])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['user_id'] = $user->user_id;
                Response::success($inventoryService->receiveGoods((int)$segments[2], $input), 'Goods received');
                exit;
            }
        }

        Response::notFound('PO endpoint not found');
        exit;
    }

    // ===============================================
    // SALES ORDERS
    // ===============================================
    elseif ($segments[0] === 'sales-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET' && empty($segments[1])) {
            Response::success($inventoryService->getAllSalesOrders(), 'Sales orders retrieved');
            exit;
        }

        if ($method === 'POST') {
            if (empty($segments[1])) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createSimpleSalesOrder($input), 'SO created', 201);
                exit;
            }

            if ($segments[1] === 'invoice' && isset($segments[2])) {
                Response::success($inventoryService->processSimpleInvoice((int)$segments[2], $user->user_id), 'Invoice processed');
                exit;
            }
        }

        Response::notFound('SO endpoint not found');
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
                exit;
            }
            if (is_numeric($segments[1])) {
                $supplierController->getById((int)$segments[1]);
                exit;
            }
        }

        if ($method === 'POST') {
            $supplierController->create();
            exit;
        }

        if ($method === 'PUT' && isset($segments[1])) {
            $supplierController->update((int)$segments[1]);
            exit;
        }

        Response::notFound('Supplier endpoint not found');
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
                exit;
            }
            if ($segments[1] === 'roles') {
                $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::requireRole(['superadmin', 'admin']);
                if (!$user) exit;
                $userService = new \Janstro\InventorySystem\Services\UserService();
                Response::success($userService->getRoles(), 'Roles retrieved');
                exit;
            }
            if (is_numeric($segments[1])) {
                $userController->getById((int)$segments[1]);
                exit;
            }
        }

        if ($method === 'POST') {
            $userController->create();
            exit;
        }

        if ($method === 'PUT' && isset($segments[1])) {
            $userController->update((int)$segments[1]);
            exit;
        }

        Response::notFound('User endpoint not found');
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
                exit;
            }

            switch ($segments[1]) {
                case 'dashboard':
                    $reportController->getDashboardStats();
                    break;
                case 'inventory-summary':
                    $reportController->getInventorySummary();
                    break;
                case 'transactions':
                    $reportController->getTransactionHistory();
                    break;
                case 'low-stock':
                    $reportController->getLowStockReport();
                    break;
                default:
                    Response::notFound('Report endpoint not found');
            }
            exit;
        }

        Response::error('Method not allowed', null, 405);
        exit;
    }

    // ===============================================
    // NOT FOUND
    // ===============================================
    error_log("❌ No matching route for: " . $path);
    Response::notFound('API endpoint not found: /' . $path);
    exit;
} catch (\Exception $e) {
    error_log("🚨 FATAL ERROR: " . $e->getMessage());
    error_log($e->getTraceAsString());
    Response::serverError($e->getMessage());
    exit;
}

// ===============================================
// INQUIRY ROUTES (Add after SALES ORDERS section)
// ===============================================
elseif ($segments[0] === 'inquiries') {
    $inquiryController = new \Janstro\InventorySystem\Controllers\InquiryController();

    // PUBLIC: Create inquiry (no auth required)
    if ($method === 'POST' && empty($segments[1])) {
        $inquiryController->create();
        exit;
    }

    // STAFF/ADMIN: Get all inquiries
    if ($method === 'GET' && empty($segments[1])) {
        $inquiryController->getAll();
        exit;
    }

    // STAFF/ADMIN: Get inquiry statistics
    if ($method === 'GET' && ($segments[1] ?? '') === 'stats') {
        $inquiryController->getStatistics();
        exit;
    }

    // STAFF/ADMIN: Get single inquiry
    if ($method === 'GET' && is_numeric($segments[1] ?? '')) {
        $inquiryController->getById((int)$segments[1]);
        exit;
    }

    // STAFF/ADMIN: Update inquiry status
    if ($method === 'PUT' && is_numeric($segments[1] ?? '') && ($segments[2] ?? '') === 'status') {
        $inquiryController->updateStatus((int)$segments[1]);
        exit;
    }

    // STAFF/ADMIN: Convert to sales order
    if ($method === 'POST' && is_numeric($segments[1] ?? '') && ($segments[2] ?? '') === 'convert') {
        $inquiryController->convertToSalesOrder((int)$segments[1]);
        exit;
    }

    // STAFF/ADMIN: Add note to inquiry
    if ($method === 'POST' && is_numeric($segments[1] ?? '') && ($segments[2] ?? '') === 'notes') {
        $inquiryController->addNote((int)$segments[1]);
        exit;
    }

    Response::notFound('Inquiry endpoint not found');
    exit;
}

// ============================================
// CUSTOMER INQUIRY ROUTES (NEW)
// ============================================

// POST /inquiries - Submit inquiry (PUBLIC - no auth)
if (preg_match('#^/inquiries$#', $path) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new Controllers\InquiryController();
    $controller->create();
    exit;
}

// GET /inquiries - Get all inquiries (STAFF/ADMIN)
if (preg_match('#^/inquiries$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new Controllers\InquiryController();
    $controller->getAll();
    exit;
}

// GET /inquiries/{id} - Get inquiry details
if (preg_match('#^/inquiries/(\d+)$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new Controllers\InquiryController();
    $controller->getById((int)$matches[1]);
    exit;
}

// PUT /inquiries/{id}/status - Update inquiry status
if (preg_match('#^/inquiries/(\d+)/status$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $controller = new Controllers\InquiryController();
    $controller->updateStatus((int)$matches[1]);
    exit;
}

// POST /inquiries/{id}/convert - Convert to sales order
if (preg_match('#^/inquiries/(\d+)/convert$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new Controllers\InquiryController();
    $controller->convertToSalesOrder((int)$matches[1]);
    exit;
}

// POST /inquiries/{id}/notes - Add note
if (preg_match('#^/inquiries/(\d+)/notes$#', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new Controllers\InquiryController();
    $controller->addNote((int)$matches[1]);
    exit;
}

// GET /inquiries/stats - Get statistics
if (preg_match('#^/inquiries/stats$#', $path) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $controller = new Controllers\InquiryController();
    $controller->getStatistics();
    exit;
}
