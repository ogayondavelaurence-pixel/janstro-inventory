<?php

/**
 * Janstro IMS - FIXED API Router v4.1
 * Date: 2025-11-22
 */

require_once __DIR__ . '/../autoload.php';

if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = dirname($_SERVER['SCRIPT_NAME']);
$path = str_replace($scriptName, '', $requestUri);
$path = parse_url($path, PHP_URL_PATH);
$path = trim($path, '/');
$path = preg_replace('#/+#', '/', $path);
$path = preg_replace('#^index\.php/?#', '', $path);

$segments = $path ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'];

error_log("📍 Path: $path | Method: $method | Segments: " . json_encode($segments));

use Janstro\InventorySystem\Controllers\AuthController;
use Janstro\InventorySystem\Controllers\InventoryController;
use Janstro\InventorySystem\Controllers\OrderController;
use Janstro\InventorySystem\Controllers\UserController;
use Janstro\InventorySystem\Controllers\SupplierController;
use Janstro\InventorySystem\Controllers\ReportController;
use Janstro\InventorySystem\Controllers\InquiryController;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Services\CompleteInventoryService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;

try {
    // Health check
    if (empty($path) || $path === 'health') {
        Response::success(['status' => 'running', 'version' => '4.1.0'], 'API healthy');
        exit;
    }

    $resource = $segments[0] ?? '';

    // ===============================================
    // AUTH ROUTES
    // ===============================================
    if ($resource === 'auth') {
        $authController = new AuthController();
        $action = $segments[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $authController->login();
            exit;
        }
        if ($method === 'POST' && $action === 'logout') {
            $authController->logout();
            exit;
        }
        if ($method === 'GET' && $action === 'me') {
            $authController->getCurrentUser();
            exit;
        }
        Response::notFound('Auth endpoint not found');
        exit;
    }

    // ===============================================
    // USERS ROUTES (CRITICAL FIX)
    // ===============================================
    if ($resource === 'users') {
        $userController = new UserController();
        $action = $segments[1] ?? '';

        // GET /users/current - MUST be first
        if ($method === 'GET' && $action === 'current') {
            $userController->getCurrentUser();
            exit;
        }

        // GET /users/roles
        if ($method === 'GET' && $action === 'roles') {
            $user = AuthMiddleware::requireRole(['superadmin', 'admin']);
            if (!$user) exit;
            $svc = new \Janstro\InventorySystem\Services\UserService();
            Response::success($svc->getRoles(), 'Roles retrieved');
            exit;
        }

        // GET /users
        if ($method === 'GET' && $action === '') {
            $userController->getAll();
            exit;
        }

        // GET /users/:id
        if ($method === 'GET' && is_numeric($action)) {
            $userController->getById((int)$action);
            exit;
        }

        // POST /users
        if ($method === 'POST' && $action === '') {
            $userController->create();
            exit;
        }

        // PUT /users/:id
        if ($method === 'PUT' && is_numeric($action)) {
            $userController->update((int)$action);
            exit;
        }

        // DELETE /users/:id
        if ($method === 'DELETE' && is_numeric($action)) {
            $userController->delete((int)$action);
            exit;
        }

        Response::notFound('User endpoint not found');
        exit;
    }

    // ===============================================
    // INVENTORY ROUTES
    // ===============================================
    if ($resource === 'inventory') {
        $inventoryController = new InventoryController();
        $inventoryService = new CompleteInventoryService();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            if ($action === '') {
                $inventoryController->getAll();
                exit;
            }
            if ($action === 'status') {
                $user = AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->getInventoryStatus(), 'Status retrieved');
                exit;
            }
            if ($action === 'check-stock' && isset($_GET['item_id'])) {
                $user = AuthMiddleware::authenticate();
                if (!$user) exit;
                Response::success($inventoryService->checkStockAvailability((int)$_GET['item_id']), 'Stock checked');
                exit;
            }
            if ($action === 'movements') {
                $user = AuthMiddleware::authenticate();
                if (!$user) exit;
                $filters = ['item_id' => $_GET['item_id'] ?? null, 'type' => $_GET['type'] ?? null];
                Response::success($inventoryService->getMaterialDocuments($filters), 'Movements retrieved');
                exit;
            }
            if ($action === 'low-stock') {
                $inventoryController->getLowStock();
                exit;
            }
            if ($action === 'categories') {
                $user = AuthMiddleware::authenticate();
                if (!$user) exit;
                $svc = new \Janstro\InventorySystem\Services\InventoryService();
                Response::success($svc->getCategories(), 'Categories retrieved');
                exit;
            }
            if (is_numeric($action)) {
                $inventoryController->getById((int)$action);
                exit;
            }
        }

        if ($method === 'POST' && $action === '') {
            $inventoryController->create();
            exit;
        }

        if ($method === 'PUT' && is_numeric($action)) {
            $inventoryController->update((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            $inventoryController->delete((int)$action);
            exit;
        }

        Response::notFound('Inventory endpoint not found');
        exit;
    }

    // ===============================================
    // PURCHASE ORDERS
    // ===============================================
    if ($resource === 'purchase-orders') {
        $orderController = new OrderController();
        $inventoryService = new CompleteInventoryService();
        $action = $segments[1] ?? '';
        $subAction = $segments[2] ?? '';

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET') {
            if ($action === '') {
                $orderController->getAll();
                exit;
            }
            if (is_numeric($action)) {
                $orderController->getById((int)$action);
                exit;
            }
        }

        if ($method === 'POST') {
            if ($action === '') {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createPurchaseOrder($input), 'PO created', 201);
                exit;
            }
            if ($action === 'receive' && is_numeric($subAction)) {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['user_id'] = $user->user_id;
                Response::success($inventoryService->receiveGoods((int)$subAction, $input), 'Goods received');
                exit;
            }
        }

        Response::notFound('PO endpoint not found');
        exit;
    }

    // ===============================================
    // SALES ORDERS
    // ===============================================
    if ($resource === 'sales-orders') {
        $inventoryService = new CompleteInventoryService();
        $action = $segments[1] ?? '';
        $subAction = $segments[2] ?? '';

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET' && $action === '') {
            Response::success($inventoryService->getAllSalesOrders(), 'Sales orders retrieved');
            exit;
        }

        if ($method === 'POST') {
            if ($action === '') {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($inventoryService->createSimpleSalesOrder($input), 'SO created', 201);
                exit;
            }
            if ($action === 'invoice' && is_numeric($subAction)) {
                Response::success($inventoryService->processSimpleInvoice((int)$subAction, $user->user_id), 'Invoice processed');
                exit;
            }
        }

        Response::notFound('SO endpoint not found');
        exit;
    }

    // ===============================================
    // INVOICES
    // ===============================================
    if ($resource === 'invoices') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        // ONLY FIX APPLIED
        $db = \Janstro\InventorySystem\Config\Database::connect();

        $action = $segments[1] ?? '';

        // GET /invoices
        if ($method === 'GET' && $action === '') {
            $stmt = $db->query("SELECT * FROM invoices ORDER BY generated_at DESC");
            Response::success($stmt->fetchAll(), 'Invoices retrieved');
            exit;
        }

        // PUT /invoices/:id
        if ($method === 'PUT' && is_numeric($action)) {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $db->prepare("
            UPDATE invoices 
            SET paid_status = ?, paid_date = NOW() 
            WHERE invoice_id = ?
        ");
            $stmt->execute([$data['paid_status'], (int)$action]);
            Response::success(null, 'Invoice updated');
            exit;
        }

        Response::notFound('Invoice endpoint not found');
        exit;
    }

    // ===============================================
    // SUPPLIERS
    // ===============================================
    if ($resource === 'suppliers') {
        $supplierController = new SupplierController();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            if ($action === '') {
                $supplierController->getAll();
                exit;
            }
            if (is_numeric($action)) {
                $supplierController->getById((int)$action);
                exit;
            }
        }
        if ($method === 'POST') {
            $supplierController->create();
            exit;
        }
        if ($method === 'PUT' && is_numeric($action)) {
            $supplierController->update((int)$action);
            exit;
        }
        if ($method === 'DELETE' && is_numeric($action)) {
            $supplierController->delete((int)$action);
            exit;
        }

        Response::notFound('Supplier endpoint not found');
        exit;
    }

    // ===============================================
    // CUSTOMERS
    // ===============================================
    if ($resource === 'customers') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $action = $segments[1] ?? '';
        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET' && $action === '') {
            $stmt = $db->query("SELECT * FROM customers ORDER BY customer_name");
            Response::success($stmt->fetchAll(), 'Customers retrieved');
            exit;
        }
        if ($method === 'GET' && is_numeric($action)) {
            $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = ?");
            $stmt->execute([(int)$action]);
            $customer = $stmt->fetch();
            $customer ? Response::success($customer, 'Customer found') : Response::notFound('Customer not found');
            exit;
        }
        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $db->prepare("INSERT INTO customers (customer_name, contact_number, email, address, customer_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['customer_name'], $data['contact_number'] ?? null, $data['email'] ?? null, $data['address'] ?? null, $data['customer_type'] ?? 'individual']);
            Response::success(['customer_id' => (int)$db->lastInsertId()], 'Customer created', 201);
            exit;
        }

        Response::notFound('Customer endpoint not found');
        exit;
    }

    // ===============================================
    // REPORTS
    // ===============================================
    if ($resource === 'reports') {
        $reportController = new ReportController();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            switch ($action) {
                case 'dashboard':
                    $reportController->getDashboardStats();
                    exit;
                case 'inventory-summary':
                    $reportController->getInventorySummary();
                    exit;
                case 'transactions':
                    $reportController->getTransactionHistory();
                    exit;
                case 'low-stock':
                    $reportController->getLowStockReport();
                    exit;
            }
        }
        Response::notFound('Report endpoint not found');
        exit;
    }

    // ===============================================
    // INQUIRIES
    // ===============================================
    if ($resource === 'inquiries') {
        $inquiryController = new InquiryController();
        $action = $segments[1] ?? '';
        $subAction = $segments[2] ?? '';

        if ($method === 'POST' && $action === '') {
            $inquiryController->submitInquiry();  // ✅ This is correct (no auth)
            exit;
        }
        if ($method === 'GET' && $action === '') {
            $inquiryController->getAllInquiries();
            exit;
        }
        if ($method === 'GET' && is_numeric($action)) {
            $inquiryController->getInquiry((int)$action);
            exit;
        }
        if ($method === 'PUT' && is_numeric($action)) {
            $inquiryController->updateInquiry((int)$action);
            exit;
        }
        if ($method === 'POST' && is_numeric($action) && $subAction === 'convert') {
            $inquiryController->convertToSalesOrder((int)$action);
            exit;
        }
        if ($method === 'DELETE' && is_numeric($action)) {
            $inquiryController->deleteInquiry((int)$action);
            exit;
        }

        Response::notFound('Inquiry endpoint not found');
        exit;
    }

    // Not found
    Response::notFound('API endpoint not found: /' . $path);
    exit;
} catch (\Exception $e) {
    error_log("🚨 FATAL: " . $e->getMessage());
    Response::serverError($e->getMessage());
    exit;
}
