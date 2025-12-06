<?php

/**
 * ============================================================================ 
 * JANSTRO IMS - COMPLETE API ROUTER v5.0 (ALL ROUTES FIXED)
 * ============================================================================ 
 * Path: public/index.php
 * 
 * CRITICAL FIXES IN v5.0:
 * ✅ Added missing /stock-requirements route
 * ✅ Added missing /inquiries routes (GET, POST, PUT, DELETE)
 * ✅ Added missing /invoices routes (GET, POST)
 * ✅ Added missing /users routes (GET, POST, PUT, DELETE)
 * ✅ Added missing /audit-logs route
 * ✅ Fixed response structure consistency (always return arrays for lists)
 * ✅ Added proper error handling for all routes
 * ============================================================================ 
 */

require_once __DIR__ . '/../autoload.php';

// Environment & Error Reporting
$appEnv = $_ENV['APP_ENV'] ?? 'development';
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Session configuration
$sessionLifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
ini_set('session.cookie_lifetime', (string)$sessionLifetime);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0');
ini_set('session.cookie_samesite', 'Strict');

// CORS configuration
$allowedOrigins = [
    'http://localhost:8080',
    'http://localhost',
    'http://127.0.0.1:8080',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Handle OPTIONS preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = dirname($scriptName);
$path = str_replace($scriptDir, '', $requestUri);
$path = parse_url($path, PHP_URL_PATH);
$path = (string)$path;
$path = trim($path, '/');
$path = preg_replace('#/+#', '/', $path);
$path = preg_replace('#^index\.php/?#', '', $path);

$segments = $path !== '' ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("📍 Router: {$method} /{$path}");

// Controller imports
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
    // Health Check
    if (empty($path) || $path === 'health') {
        Response::success([
            'status' => 'running',
            'version' => '5.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ], 'API healthy');
        exit;
    }

    $resource = $segments[0] ?? '';

    /* ================================================================
       AUTH ROUTES - PUBLIC
    ================================================================ */
    if ($resource === 'auth') {
        $action = $segments[1] ?? '';
        $auth = new AuthController();

        if ($method === 'POST' && $action === 'login') {
            $auth->login();
            exit;
        }

        if ($method === 'POST' && $action === 'logout') {
            $auth->logout();
            exit;
        }

        if ($method === 'GET' && $action === 'me') {
            $auth->getCurrentUser();
            exit;
        }

        Response::notFound('Auth endpoint not found');
        exit;
    }

    /* ================================================================
       ITEMS ROUTE
    ================================================================ */
    if ($resource === 'items') {
        $invCtrl = new InventoryController();
        $action = $segments[1] ?? '';

        if ($method === 'GET' && $action === '') {
            AuthMiddleware::authenticate();
            $invCtrl->getAll();
            exit;
        }

        if ($method === 'GET' && is_numeric($action)) {
            AuthMiddleware::authenticate();
            $invCtrl->getById((int)$action);
            exit;
        }

        if ($method === 'POST' && $action === '') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $invCtrl->create();
            exit;
        }

        if ($method === 'PUT' && is_numeric($action)) {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $invCtrl->update((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            AuthMiddleware::requireRole(['superadmin']);
            $invCtrl->delete((int)$action);
            exit;
        }

        Response::notFound('Items endpoint not found');
        exit;
    }

    /* ================================================================
       INVENTORY ROUTES
    ================================================================ */
    if ($resource === 'inventory') {
        $invCtrl = new InventoryController();
        $invSvc = new CompleteInventoryService();
        $action = $segments[1] ?? '';

        AuthMiddleware::authenticate();

        if ($method === 'GET') {
            if ($action === '') {
                $invCtrl->getAll();
                exit;
            }

            if ($action === 'status') {
                Response::success($invSvc->getInventoryStatus(), 'Status retrieved');
                exit;
            }

            if ($action === 'low-stock') {
                $invCtrl->getLowStock();
                exit;
            }

            if (is_numeric($action)) {
                $invCtrl->getById((int)$action);
                exit;
            }
        }

        Response::notFound('Inventory endpoint not found');
        exit;
    }

    /* ================================================================
       CATEGORIES ROUTE
    ================================================================ */
    if ($resource === 'categories') {
        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';

        AuthMiddleware::authenticate();

        if ($method === 'GET' && $action === '') {
            $stmt = $db->query("SELECT * FROM categories ORDER BY name");
            Response::success($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Categories retrieved');
            exit;
        }

        if ($method === 'GET' && is_numeric($action)) {
            $stmt = $db->prepare("SELECT * FROM categories WHERE category_id = ?");
            $stmt->execute([$action]);
            $category = $stmt->fetch();

            if ($category) {
                Response::success($category, 'Category retrieved');
            } else {
                Response::notFound('Category not found');
            }
            exit;
        }

        if ($method === 'POST' && $action === '') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name'])) {
                Response::badRequest('Category name is required');
                exit;
            }

            $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$data['name'], $data['description'] ?? null]);

            Response::success(['category_id' => $db->lastInsertId()], 'Category created', 201);
            exit;
        }

        Response::notFound('Category endpoint not found');
        exit;
    }

    /* ================================================================
       CUSTOMERS ROUTE
    ================================================================ */
    if ($resource === 'customers') {
        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';

        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) exit;

        if ($method === 'GET' && $action === '') {
            $stmt = $db->query("SELECT * FROM customers ORDER BY created_at DESC");
            Response::success($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Customers retrieved');
            exit;
        }

        Response::notFound('Customer endpoint not found');
        exit;
    }

    /* ================================================================
       TRANSACTIONS ROUTE
    ================================================================ */
    if ($resource === 'transactions') {
        AuthMiddleware::authenticate();
        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $limit = (int)($_GET['limit'] ?? 200);

            $stmt = $db->prepare("
                SELECT t.*, i.item_name, u.name as user_name
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.item_id
                LEFT JOIN users u ON t.user_id = u.user_id
                ORDER BY t.movement_date DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);

            Response::success($stmt->fetchAll(\PDO::FETCH_ASSOC), 'Transactions retrieved');
            exit;
        }

        Response::notFound('Transaction endpoint not found');
        exit;
    }

    /* ================================================================
       PURCHASE ORDERS
    ================================================================ */
    if ($resource === 'purchase-orders') {
        $order = new OrderController();
        $svc = new CompleteInventoryService();
        $action = $segments[1] ?? '';
        $sub = $segments[2] ?? '';

        $user = AuthMiddleware::authenticate();

        if ($method === 'GET') {
            if ($action === '') {
                $order->getAll();
                exit;
            }
            if (is_numeric($action)) {
                $order->getById((int)$action);
                exit;
            }
        }

        if ($method === 'POST') {
            if ($action === '') {
                $input = json_decode(file_get_contents('php://input'), true);
                $input['created_by'] = $user->user_id;
                Response::success($svc->createPurchaseOrder($input), 'PO created', 201);
                exit;
            }
        }

        Response::notFound('PO endpoint not found');
        exit;
    }

    /* ================================================================
       SALES ORDERS
    ================================================================ */
    if ($resource === 'sales-orders') {
        $svc = new CompleteInventoryService();
        $action = $segments[1] ?? '';

        $user = AuthMiddleware::authenticate();

        if ($method === 'GET' && $action === '') {
            Response::success($svc->getAllSalesOrders(), 'SO retrieved');
            exit;
        }

        if ($method === 'POST' && $action === '') {
            $data = json_decode(file_get_contents('php://input'), true);
            $data['created_by'] = $user->user_id;
            Response::success($svc->createSimpleSalesOrder($data), 'SO created', 201);
            exit;
        }

        Response::notFound('SO endpoint not found');
        exit;
    }

    /* ================================================================
       SUPPLIERS (FIXED - Returns array)
    ================================================================ */
    if ($resource === 'suppliers') {
        $ctrl = new SupplierController();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            AuthMiddleware::authenticate();
            if ($action === '') {
                $ctrl->getAll();
                exit;
            }
        }

        Response::notFound('Supplier endpoint not found');
        exit;
    }

    /* ================================================================
       ✅ STOCK REQUIREMENTS (NEW ROUTE)
    ================================================================ */
    if ($resource === 'stock-requirements') {
        AuthMiddleware::authenticate();
        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $stmt = $db->query("
                SELECT 
                    sr.*,
                    so.customer_name,
                    i.item_name,
                    i.unit,
                    i.quantity as current_stock
                FROM stock_requirements sr
                LEFT JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                LEFT JOIN items i ON sr.item_id = i.item_id
                ORDER BY sr.created_at DESC
            ");

            $requirements = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            Response::success($requirements, 'Stock requirements retrieved');
            exit;
        }

        Response::notFound('Stock requirements endpoint not found');
        exit;
    }

    /* ================================================================
       ✅ INQUIRIES (NEW ROUTES)
    ================================================================ */
    if ($resource === 'inquiries') {
        $ctrl = new InquiryController();
        $action = $segments[1] ?? '';
        $sub = $segments[2] ?? '';

        if ($method === 'GET') {
            if ($action === '') {
                AuthMiddleware::authenticate();
                $ctrl->getAllInquiries();
                exit;
            }
            if (is_numeric($action)) {
                AuthMiddleware::authenticate();
                $ctrl->getInquiry((int)$action);
                exit;
            }
        }

        if ($method === 'POST') {
            if ($action === '') {
                // Public inquiry submission (no auth required)
                $ctrl->submitInquiry();
                exit;
            }
            if (is_numeric($action) && $sub === 'convert') {
                AuthMiddleware::requireRole(['admin', 'superadmin']);
                $ctrl->convertToSalesOrder((int)$action);
                exit;
            }
        }

        if ($method === 'PUT' && is_numeric($action)) {
            AuthMiddleware::authenticate();
            $ctrl->updateInquiry((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->deleteInquiry((int)$action);
            exit;
        }

        Response::notFound('Inquiry endpoint not found');
        exit;
    }

    /* ================================================================
       ✅ INVOICES (NEW ROUTES)
    ================================================================ */
    if ($resource === 'invoices') {
        AuthMiddleware::requireRole(['admin', 'superadmin']);
        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            if ($action === '') {
                $stmt = $db->query("
                    SELECT 
                        inv.*,
                        so.customer_name,
                        u.name as generated_by_name
                    FROM invoices inv
                    LEFT JOIN sales_orders so ON inv.sales_order_id = so.sales_order_id
                    LEFT JOIN users u ON inv.generated_by = u.user_id
                    ORDER BY inv.generated_at DESC
                ");

                $invoices = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                Response::success($invoices, 'Invoices retrieved');
                exit;
            }

            if (is_numeric($action)) {
                $stmt = $db->prepare("
                    SELECT 
                        inv.*,
                        so.customer_name,
                        so.total_amount,
                        u.name as generated_by_name
                    FROM invoices inv
                    LEFT JOIN sales_orders so ON inv.sales_order_id = so.sales_order_id
                    LEFT JOIN users u ON inv.generated_by = u.user_id
                    WHERE inv.invoice_id = ?
                ");
                $stmt->execute([$action]);
                $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($invoice) {
                    Response::success($invoice, 'Invoice retrieved');
                } else {
                    Response::notFound('Invoice not found');
                }
                exit;
            }
        }

        if ($method === 'POST' && $action === 'generate') {
            $data = json_decode(file_get_contents('php://input'), true);
            $salesOrderId = $data['sales_order_id'] ?? null;
            $user = AuthMiddleware::authenticate();

            if (!$salesOrderId) {
                Response::badRequest('Sales order ID required');
                exit;
            }

            $svc = new CompleteInventoryService();
            $result = $svc->processSimpleInvoice($salesOrderId, $user->user_id);

            if ($result['success']) {
                Response::success($result, 'Invoice generated', 201);
            } else {
                Response::error($result['message'] ?? 'Invoice generation failed', null, 400);
            }
            exit;
        }

        Response::notFound('Invoice endpoint not found');
        exit;
    }

    /* ================================================================
       ✅ USERS (NEW ROUTES)
    ================================================================ */
    if ($resource === 'users') {
        $ctrl = new UserController();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            if ($action === '') {
                AuthMiddleware::requireRole(['admin', 'superadmin']);
                $ctrl->getAll();
                exit;
            }
            if ($action === 'current') {
                AuthMiddleware::authenticate();
                $ctrl->getCurrentUser();
                exit;
            }
            if (is_numeric($action)) {
                AuthMiddleware::authenticate();
                $ctrl->getById((int)$action);
                exit;
            }
        }

        if ($method === 'POST' && $action === '') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->create();
            exit;
        }

        if ($method === 'PUT' && is_numeric($action)) {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->update((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->delete((int)$action);
            exit;
        }

        Response::notFound('User endpoint not found');
        exit;
    }

    /* ================================================================
       ✅ AUDIT LOGS (NEW ROUTE)
    ================================================================ */
    if ($resource === 'audit-logs') {
        AuthMiddleware::requireRole(['superadmin']);
        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $page = (int)($_GET['page'] ?? 1);
            $perPage = (int)($_GET['per_page'] ?? 50);
            $offset = ($page - 1) * $perPage;

            $filters = [];
            $params = [];

            if (!empty($_GET['module'])) {
                $filters[] = "module = ?";
                $params[] = $_GET['module'];
            }

            if (!empty($_GET['action_type'])) {
                $filters[] = "action_type = ?";
                $params[] = $_GET['action_type'];
            }

            if (!empty($_GET['user_search'])) {
                $filters[] = "u.name LIKE ?";
                $params[] = "%{$_GET['user_search']}%";
            }

            $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

            $sql = "
                SELECT 
                    a.log_id,
                    a.user_id,
                    a.action_description,
                    a.ip_address,
                    a.module,
                    a.action_type,
                    a.created_at,
                    u.username,
                    u.name as user_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                {$whereClause}
                ORDER BY a.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id {$whereClause}";
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

            Response::success([
                'logs' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ], 'Audit logs retrieved');
            exit;
        }

        Response::notFound('Audit logs endpoint not found');
        exit;
    }

    /* ================================================================
       REPORTS
    ================================================================ */
    if ($resource === 'reports') {
        AuthMiddleware::authenticate();
        $ctrl = new ReportController();
        $action = $segments[1] ?? '';

        if ($method === 'GET') {
            switch ($action) {
                case 'dashboard':
                    $ctrl->getDashboardStats();
                    exit;
                case 'inventory-summary':
                    $ctrl->getInventorySummary();
                    exit;
                case 'transactions':
                    $ctrl->getTransactionHistory();
                    exit;
            }
        }

        Response::notFound('Report endpoint not found');
        exit;
    }

    // No route matched
    error_log("⚠️ No matching route for: /{$path}");
    Response::notFound("API endpoint not found: /{$path}");
    exit;
} catch (\Exception $e) {
    error_log("🚨 FATAL API ERROR");
    error_log("❌ Message: " . $e->getMessage());
    error_log("❌ File: " . $e->getFile() . ":" . $e->getLine());
    error_log("❌ Stack trace:");
    error_log($e->getTraceAsString());

    Response::serverError($e->getMessage());
    exit;
}
