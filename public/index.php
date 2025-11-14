<?php

/**
 * Janstro Inventory Management System - Complete API Router v3.0
 * FIXED: Security hardening, CSRF protection, rate limiting
 * All new endpoints integrated (customers, invoices, sales orders)
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Error handling
if ($_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize session for rate limiting
session_start();

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
use Janstro\InventorySystem\Controllers\CustomerController;
use Janstro\InventorySystem\Controllers\InvoiceController;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Utils\Security;
use Janstro\InventorySystem\Services\CompleteInventoryService;

$authController = new AuthController();
$inventoryController = new InventoryController();
$orderController = new OrderController();
$userController = new UserController();
$supplierController = new SupplierController();
$reportController = new ReportController();
$customerController = new CustomerController();
$invoiceController = new InvoiceController();

try {
    // ===============================================
    // HEALTH CHECK (PUBLIC)
    // ===============================================
    if ($path === 'health' || $path === '') {
        Response::success([
            'name' => $_ENV['APP_NAME'] ?? 'Janstro Inventory System',
            'version' => '3.0.0',
            'status' => 'running',
            'timestamp' => date('Y-m-d H:i:s')
        ], 'API is running');
        exit;
    }

    // ===============================================
    // AUTHENTICATION ROUTES
    // ===============================================
    if ($segments[0] === 'auth') {
        // Rate limiting for login attempts
        if ($segments[1] === 'login') {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if (!Security::checkRateLimit("login_$clientIp", 5, 60)) {
                Response::error('Too many login attempts. Please try again later.', null, 429);
                exit;
            }
        }

        switch ($method) {
            case 'POST':
                if ($segments[1] === 'login') {
                    $authController->login();
                } elseif ($segments[1] === 'logout') {
                    $authController->logout();
                } elseif ($segments[1] === 'change-password') {
                    $authController->changePassword();
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
        exit;
    }

    // ===============================================
    // CUSTOMER ROUTES
    // ===============================================
    if ($segments[0] === 'customers') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $customerController->getAll();
                } elseif ($segments[1] === 'search') {
                    $customerController->search();
                } elseif (is_numeric($segments[1])) {
                    if (isset($segments[2]) && $segments[2] === 'orders') {
                        $customerController->getOrders((int)$segments[1]);
                    } else {
                        $customerController->getById((int)$segments[1]);
                    }
                } else {
                    Response::notFound('Customer endpoint not found');
                }
                break;
            case 'POST':
                $customerController->create();
                break;
            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $customerController->update((int)$segments[1]);
                } else {
                    Response::notFound('Customer endpoint not found');
                }
                break;
            case 'DELETE':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $customerController->delete((int)$segments[1]);
                } else {
                    Response::notFound('Customer endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // INVOICE ROUTES
    // ===============================================
    if ($segments[0] === 'invoices') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $invoiceController->getAll();
                } elseif ($segments[1] === 'outstanding') {
                    $invoiceController->getOutstanding();
                } elseif ($segments[1] === 'statistics') {
                    $invoiceController->getStatistics();
                } elseif (is_numeric($segments[1])) {
                    if (isset($segments[2]) && $segments[2] === 'pdf') {
                        $invoiceController->exportPDF((int)$segments[1]);
                    } else {
                        $invoiceController->getById((int)$segments[1]);
                    }
                } else {
                    Response::notFound('Invoice endpoint not found');
                }
                break;
            case 'POST':
                if ($segments[1] === 'generate' && isset($segments[2])) {
                    $invoiceController->generate((int)$segments[2]);
                } elseif (is_numeric($segments[1]) && isset($segments[2]) && $segments[2] === 'payment') {
                    $invoiceController->applyPayment((int)$segments[1]);
                } else {
                    Response::notFound('Invoice endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // INVENTORY ROUTES
    // ===============================================
    if ($segments[0] === 'inventory') {
        $inventoryService = new CompleteInventoryService();

        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $inventoryController->getAll();
                } elseif ($segments[1] === 'status') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) exit;
                    $status = $inventoryService->getInventoryStatus();
                    Response::success($status, 'Inventory status retrieved');
                } elseif ($segments[1] === 'check-stock' && isset($_GET['item_id'])) {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) exit;
                    $stock = $inventoryService->checkStockAvailability((int)$_GET['item_id']);
                    Response::success($stock, 'Stock availability checked');
                } elseif ($segments[1] === 'requirements' && isset($_GET['item_id'])) {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) exit;
                    $requirements = $inventoryService->checkStockRequirements((int)$_GET['item_id']);
                    Response::success($requirements, 'Stock requirements calculated');
                } elseif ($segments[1] === 'movements') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
                    if (!$user) exit;
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
                } elseif ($segments[1] === 'categories') {
                    $inventoryController->getCategories();
                } elseif ($segments[1] === 'transactions') {
                    $inventoryController->getTransactions();
                } elseif ($segments[1] === 'summary') {
                    $inventoryController->getSummary();
                } elseif (is_numeric($segments[1])) {
                    $inventoryController->getById((int)$segments[1]);
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            case 'POST':
                if (!isset($segments[1])) {
                    $inventoryController->create();
                } elseif ($segments[1] === 'stock-in') {
                    $inventoryController->stockIn();
                } elseif ($segments[1] === 'stock-out') {
                    $inventoryController->stockOut();
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $inventoryController->update((int)$segments[1]);
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            case 'DELETE':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $inventoryController->delete((int)$segments[1]);
                } else {
                    Response::notFound('Inventory endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // PURCHASE ORDER ROUTES (TO SUPPLIERS)
    // ===============================================
    if ($segments[0] === 'purchase-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) exit;

        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $orderController->getAll();
                } elseif ($segments[1] === 'statistics') {
                    $orderController->getStatistics();
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
                    $result = $inventoryService->receiveGoods($poId, $user->user_id);
                    Response::success($result, 'Goods received successfully');
                } else {
                    Response::notFound('Purchase order endpoint not found');
                }
                break;

            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1]) && isset($segments[2]) && $segments[2] === 'status') {
                    $orderController->updateStatus((int)$segments[1]);
                } else {
                    Response::notFound('Purchase order endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // SALES ORDER ROUTES (FROM CUSTOMERS)
    // ===============================================
    if ($segments[0] === 'sales-orders') {
        $inventoryService = new CompleteInventoryService();
        $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::authenticate();
        if (!$user) exit;

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
                    $result = $inventoryService->createSalesOrder($input);
                    Response::success($result, 'Sales order created', 201);
                } elseif ($segments[1] === 'complete' && isset($segments[2])) {
                    $orderId = (int)$segments[2];
                    $result = $inventoryService->completeInstallation($orderId, $user->user_id);
                    Response::success($result, 'Installation completed successfully');
                } else {
                    Response::notFound('Sales order endpoint not found');
                }
                break;

            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // SUPPLIER ROUTES
    // ===============================================
    if ($segments[0] === 'suppliers') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $supplierController->getAll();
                } elseif (is_numeric($segments[1])) {
                    if (isset($segments[2]) && $segments[2] === 'orders') {
                        $supplierController->getOrders((int)$segments[1]);
                    } else {
                        $supplierController->getById((int)$segments[1]);
                    }
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;
            case 'POST':
                $supplierController->create();
                break;
            case 'PUT':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $supplierController->update((int)$segments[1]);
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;
            case 'DELETE':
                if (isset($segments[1]) && is_numeric($segments[1])) {
                    $supplierController->delete((int)$segments[1]);
                } else {
                    Response::notFound('Supplier endpoint not found');
                }
                break;
            default:
                Response::error('Method not allowed', null, 405);
        }
        exit;
    }

    // ===============================================
    // REPORT ROUTES
    // ===============================================
    if ($segments[0] === 'reports') {
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
        exit;
    }

    // ===============================================
    // USER ROUTES
    // ===============================================
    if ($segments[0] === 'users') {
        switch ($method) {
            case 'GET':
                if (!isset($segments[1])) {
                    $userController->getAll();
                } elseif ($segments[1] === 'roles') {
                    $user = \Janstro\InventorySystem\Middleware\AuthMiddleware::requireRole(['superadmin']);
                    if (!$user) exit;
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
                    if (isset($segments[2])) {
                        if ($segments[2] === 'deactivate') {
                            $userController->deactivate((int)$segments[1]);
                        } elseif ($segments[2] === 'activate') {
                            $userController->activate((int)$segments[1]);
                        } else {
                            Response::notFound('User endpoint not found');
                        }
                    } else {
                        $userController->update((int)$segments[1]);
                    }
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
        exit;
    }

    // ===============================================
    // 404 NOT FOUND
    // ===============================================
    Response::notFound('Endpoint not found: ' . $path);
} catch (\Exception $e) {
    error_log("API Error: " . $e->getMessage());
    Response::serverError('An unexpected error occurred');
}
