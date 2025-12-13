<?php

/* Path: public/index.php*/
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

// Strip /api/ prefix if present
if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

$segments = $path !== '' ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

error_log("📍 Router v7.0: {$method} /{$path}");

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
            'version' => '7.0.0',
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
       EMAIL SETTINGS ROUTES (NEW IN v7.0)
    ================================================================ */
    if ($resource === 'email-settings') {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) exit;

        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';

        // GET /email-settings - Get current settings
        if ($method === 'GET' && $action === '') {
            $stmt = $db->query("SELECT * FROM email_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings) {
                Response::success([
                    'enabled' => 1,
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_encryption' => 'tls',
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'from_email' => 'noreply@janstro.com',
                    'from_name' => 'Janstro Inventory System',
                    'reply_to' => '',
                    'notify_low_stock' => 1,
                    'notify_new_order' => 1,
                    'notify_po_delivered' => 1,
                    'notify_installation_complete' => 1,
                    'admin_emails' => '',
                    'email_footer' => ''
                ], 'Default settings loaded');
            } else {
                Response::success($settings, 'Settings retrieved');
            }
            exit;
        }

        // POST /email-settings/save - Save settings
        if ($method === 'POST' && $action === 'save') {
            $data = json_decode(file_get_contents('php://input'), true);

            // Check if settings exist
            $stmt = $db->query("SELECT setting_id FROM email_settings LIMIT 1");
            $existing = $stmt->fetch();

            if ($existing) {
                // UPDATE
                $stmt = $db->prepare("
                    UPDATE email_settings SET
                        enabled = ?,
                        smtp_host = ?,
                        smtp_port = ?,
                        smtp_encryption = ?,
                        smtp_username = ?,
                        smtp_password = ?,
                        from_email = ?,
                        from_name = ?,
                        reply_to = ?,
                        notify_low_stock = ?,
                        notify_new_order = ?,
                        notify_po_delivered = ?,
                        notify_installation_complete = ?,
                        admin_emails = ?,
                        email_footer = ?,
                        updated_at = NOW()
                    WHERE setting_id = ?
                ");

                $stmt->execute([
                    $data['enabled'] ?? 1,
                    $data['smtp_host'] ?? '',
                    $data['smtp_port'] ?? 587,
                    $data['smtp_encryption'] ?? 'tls',
                    $data['smtp_username'] ?? '',
                    $data['smtp_password'] ?? '',
                    $data['from_email'] ?? '',
                    $data['from_name'] ?? 'Janstro Inventory System',
                    $data['reply_to'] ?? '',
                    $data['notify_low_stock'] ?? 0,
                    $data['notify_new_order'] ?? 0,
                    $data['notify_po_delivered'] ?? 0,
                    $data['notify_installation_complete'] ?? 0,
                    $data['admin_emails'] ?? '',
                    $data['email_footer'] ?? '',
                    $existing['setting_id']
                ]);
            } else {
                // INSERT
                $stmt = $db->prepare("
                    INSERT INTO email_settings (
                        enabled, smtp_host, smtp_port, smtp_encryption,
                        smtp_username, smtp_password, from_email, from_name,
                        reply_to, notify_low_stock, notify_new_order,
                        notify_po_delivered, notify_installation_complete,
                        admin_emails, email_footer
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $data['enabled'] ?? 1,
                    $data['smtp_host'] ?? '',
                    $data['smtp_port'] ?? 587,
                    $data['smtp_encryption'] ?? 'tls',
                    $data['smtp_username'] ?? '',
                    $data['smtp_password'] ?? '',
                    $data['from_email'] ?? '',
                    $data['from_name'] ?? 'Janstro Inventory System',
                    $data['reply_to'] ?? '',
                    $data['notify_low_stock'] ?? 0,
                    $data['notify_new_order'] ?? 0,
                    $data['notify_po_delivered'] ?? 0,
                    $data['notify_installation_complete'] ?? 0,
                    $data['admin_emails'] ?? '',
                    $data['email_footer'] ?? ''
                ]);
            }

            Response::success(null, 'Email settings saved successfully');
            exit;
        }

        // POST /email-settings/test - Send test email
        if ($method === 'POST' && $action === 'test') {
            $data = json_decode(file_get_contents('php://input'), true);
            $testEmail = $data['test_email'] ?? null;

            if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Valid test email address required');
                exit;
            }

            $subject = '✅ Janstro IMS - Email Test Successful';
            $message = "Test email sent successfully at " . date('F j, Y g:i A');
            $headers = [
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . ($data['from_email'] ?? 'noreply@janstro.com')
            ];

            if (mail($testEmail, $subject, $message, implode("\r\n", $headers))) {
                Response::success(null, 'Test email sent successfully');
            } else {
                Response::error('Email test failed', null, 500);
            }
            exit;
        }

        Response::notFound('Email settings endpoint not found');
        exit;
    }

    /* ================================================================
   EMAIL ROUTES (SUPERADMIN ONLY) - ADD THIS BLOCK
================================================================ */
    if ($resource === 'email') {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) exit;

        $action = $segments[1] ?? '';
        $ctrl = new \Janstro\InventorySystem\Controllers\EmailController();

        if ($method === 'POST' && $action === 'test') {
            $ctrl->testEmail();
            exit;
        }

        if ($method === 'POST' && $action === 'low-stock-alerts') {
            $ctrl->sendLowStockAlerts();
            exit;
        }

        if ($method === 'POST' && $action === 'daily-summary') {
            $ctrl->sendDailySummary();
            exit;
        }

        if ($method === 'GET' && $action === 'logs') {
            $ctrl->getEmailLogs();
            exit;
        }

        if ($method === 'POST' && $action === 'process-queue') {
            $ctrl->processQueue();
            exit;
        }

        Response::notFound('Email endpoint not found');
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

    // ============================================================================
    // INVENTORY ROUTES (FIXED - v3.0)
    // ============================================================================
    if ($resource === 'inventory') {
        $invCtrl = new InventoryController();
        $action = $segments[1] ?? '';

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET') {
            try {
                if ($action === '') {
                    error_log("📦 Fetching inventory items...");
                    $invCtrl->getAll();
                    exit;
                }

                if ($action === 'status') {
                    $invSvc = new CompleteInventoryService();
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
            } catch (\Exception $e) {
                error_log("❌ Inventory error: " . $e->getMessage());
                Response::serverError('Failed to retrieve inventory: ' . $e->getMessage());
                exit;
            }
        }

        if ($method === 'POST' && $action === '') {
            try {
                $invCtrl->create();
                exit;
            } catch (\Exception $e) {
                Response::badRequest($e->getMessage());
                exit;
            }
        }

        if ($method === 'PUT' && is_numeric($action)) {
            try {
                $invCtrl->update((int)$action);
                exit;
            } catch (\Exception $e) {
                Response::badRequest($e->getMessage());
                exit;
            }
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            try {
                $invCtrl->delete((int)$action);
                exit;
            } catch (\Exception $e) {
                Response::badRequest($e->getMessage());
                exit;
            }
        }

        Response::notFound('Inventory endpoint not found');
        exit;
    }

    // ============================================================================
    // CATEGORIES ROUTE (FIXED - v3.0)
    // ============================================================================
    if ($resource === 'categories') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';

        if ($method === 'GET' && $action === '') {
            try {
                error_log("📂 Fetching categories...");

                $stmt = $db->query("
                SELECT category_id, name, description, created_at 
                FROM categories 
                ORDER BY name
            ");

                $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!is_array($categories)) {
                    $categories = [];
                }

                $formattedCategories = [];
                foreach ($categories as $cat) {
                    $formattedCategories[] = [
                        'category_id' => (int)$cat['category_id'],
                        'name' => (string)$cat['name'],
                        'description' => (string)($cat['description'] ?? ''),
                        'created_at' => $cat['created_at']
                    ];
                }

                error_log("✅ Retrieved " . count($formattedCategories) . " categories");

                Response::success($formattedCategories, 'Categories retrieved');
                exit;
            } catch (\PDOException $e) {
                error_log("❌ Categories error: " . $e->getMessage());
                Response::serverError('Failed to retrieve categories');
                exit;
            }
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
   TRANSACTIONS ROUTE (FIXED - v7.1)
================================================================ */
    if ($resource === 'transactions') {
        // ✅ FIX: Authenticate but don't exit if fails (return error properly)
        $user = null;
        try {
            $user = AuthMiddleware::authenticate();
        } catch (\Exception $e) {
            error_log("❌ Auth failed for transactions: " . $e->getMessage());
            Response::unauthorized('Authentication required');
            exit;
        }

        if (!$user) {
            Response::unauthorized('Please login to view transactions');
            exit;
        }

        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            try {
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

                error_log("📊 Fetching transactions (limit: {$limit})");

                $stmt = $db->prepare("
                SELECT 
                    t.transaction_id,
                    t.item_id,
                    t.transaction_type,
                    t.quantity,
                    t.unit_price,
                    t.reference_type,
                    t.reference_number,
                    t.notes,
                    t.previous_quantity,
                    t.new_quantity,
                    t.movement_date,
                    t.transaction_date,
                    i.item_name,
                    i.sku,
                    i.unit,
                    c.name as category_name,
                    u.name as user_name
                FROM transactions t
                LEFT JOIN items i ON t.item_id = i.item_id
                LEFT JOIN categories c ON i.category_id = c.category_id
                LEFT JOIN users u ON t.user_id = u.user_id
                ORDER BY t.movement_date DESC
                LIMIT ?
            ");

                $stmt->execute([$limit]);
                $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // ✅ FIX: Type-safe data formatting
                $formatted = [];
                foreach ($transactions as $t) {
                    $formatted[] = [
                        'transaction_id' => (int)$t['transaction_id'],
                        'item_id' => (int)$t['item_id'],
                        'item_name' => (string)$t['item_name'],
                        'sku' => (string)($t['sku'] ?? 'N/A'),
                        'unit' => (string)$t['unit'],
                        'category_name' => (string)($t['category_name'] ?? 'Uncategorized'),
                        'transaction_type' => (string)$t['transaction_type'],
                        'quantity' => (int)$t['quantity'],
                        'unit_price' => round((float)($t['unit_price'] ?? 0), 2),
                        'total' => round((float)($t['quantity'] * ($t['unit_price'] ?? 0)), 2),
                        'reference_type' => (string)($t['reference_type'] ?? ''),
                        'reference_number' => (string)($t['reference_number'] ?? '-'),
                        'notes' => (string)($t['notes'] ?? ''),
                        'previous_quantity' => (int)($t['previous_quantity'] ?? 0),
                        'new_quantity' => (int)($t['new_quantity'] ?? 0),
                        'movement_date' => (string)$t['movement_date'],
                        'transaction_date' => (string)($t['transaction_date'] ?? $t['movement_date']),
                        'user_name' => (string)($t['user_name'] ?? 'System')
                    ];
                }

                error_log("✅ Retrieved " . count($formatted) . " transactions");

                Response::success($formatted, 'Transactions retrieved');
                exit;
            } catch (\PDOException $e) {
                error_log("❌ Transaction query error: " . $e->getMessage());
                Response::serverError('Failed to retrieve transactions: ' . $e->getMessage());
                exit;
            }
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

        if ($method === 'POST' && $action === '') {
            $input = json_decode(file_get_contents('php://input'), true);
            $input['created_by'] = $user->user_id;
            Response::success($svc->createPurchaseOrder($input), 'PO created', 201);
            exit;
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
       SUPPLIERS
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

    // ============================================================================
    // STOCK REQUIREMENTS (FIXED - v3.0)
    // ============================================================================
    if ($resource === 'stock-requirements') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            try {
                $stmt = $db->query("
                SELECT 
                    sr.requirement_id,
                    sr.sales_order_id,
                    sr.item_id,
                    sr.required_quantity,
                    sr.available_quantity,
                    sr.shortage_quantity,
                    sr.status,
                    sr.created_at,
                    sr.updated_at,
                    so.customer_name,
                    so.order_date,
                    so.installation_date,
                    so.status AS order_status,
                    i.item_name,
                    i.sku,
                    i.unit,
                    i.quantity AS current_stock,
                    i.reorder_level,
                    CASE 
                        WHEN sr.status = 'sufficient' THEN 'success'
                        WHEN sr.status = 'shortage' THEN 'warning'
                        WHEN sr.status = 'critical' THEN 'danger'
                    END AS status_color
                FROM stock_requirements sr
                LEFT JOIN sales_orders so ON sr.sales_order_id = so.sales_order_id
                LEFT JOIN items i ON sr.item_id = i.item_id
                ORDER BY 
                    FIELD(sr.status, 'critical', 'shortage', 'sufficient'),
                    sr.created_at DESC
            ");

                $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $formatted = [];
                foreach ($requirements as $req) {
                    $formatted[] = [
                        'requirement_id' => (int)$req['requirement_id'],
                        'sales_order_id' => (int)$req['sales_order_id'],
                        'item_id' => (int)$req['item_id'],
                        'item_name' => $req['item_name'],
                        'sku' => $req['sku'],
                        'unit' => $req['unit'],
                        'customer_name' => $req['customer_name'],
                        'order_date' => $req['order_date'],
                        'installation_date' => $req['installation_date'],
                        'required_quantity' => (int)$req['required_quantity'],
                        'available_quantity' => (int)$req['available_quantity'],
                        'shortage_quantity' => (int)$req['shortage_quantity'],
                        'current_stock' => (int)$req['current_stock'],
                        'reorder_level' => (int)$req['reorder_level'],
                        'status' => $req['status'],
                        'status_color' => $req['status_color'],
                        'order_status' => $req['order_status'],
                        'needs_po' => $req['shortage_quantity'] > 0,
                        'created_at' => $req['created_at'],
                        'updated_at' => $req['updated_at']
                    ];
                }

                Response::success([
                    'requirements' => $formatted,
                    'summary' => [
                        'total' => count($formatted),
                        'sufficient' => count(array_filter($formatted, fn($r) => $r['status'] === 'sufficient')),
                        'shortage' => count(array_filter($formatted, fn($r) => $r['status'] === 'shortage')),
                        'critical' => count(array_filter($formatted, fn($r) => $r['status'] === 'critical'))
                    ]
                ], 'Stock requirements retrieved');
                exit;
            } catch (PDOException $e) {
                error_log("Stock requirements error: " . $e->getMessage());
                Response::serverError('Failed to retrieve stock requirements');
                exit;
            }
        }

        Response::notFound('Stock requirements endpoint not found');
        exit;
    }

    /* ================================================================
       INQUIRIES
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
       INVOICES
    ================================================================ */
    if ($resource === 'invoices') {
        $db = \Janstro\InventorySystem\Config\Database::connect();
        $action = $segments[1] ?? '';
        $id = $segments[2] ?? '';

        $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
        if (!$user) exit;

        // GET /invoices - List all
        if ($method === 'GET' && $action === '') {
            try {
                $filters = [];
                $params = [];

                if (!empty($_GET['status'])) {
                    $filters[] = "i.payment_status = ?";
                    $params[] = $_GET['status'];
                }

                if (!empty($_GET['from_date'])) {
                    $filters[] = "i.generated_at >= ?";
                    $params[] = $_GET['from_date'] . ' 00:00:00';
                }

                if (!empty($_GET['to_date'])) {
                    $filters[] = "i.generated_at <= ?";
                    $params[] = $_GET['to_date'] . ' 23:59:59';
                }

                $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

                $stmt = $db->prepare("
                SELECT * FROM v_invoice_details
                {$whereClause}
                ORDER BY generated_at DESC
                LIMIT 100
            ");

                $stmt->execute($params);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

                Response::success($invoices, 'Invoices retrieved');
            } catch (PDOException $e) {
                error_log("Invoice list error: " . $e->getMessage());
                Response::serverError('Failed to retrieve invoices');
            }
            exit;
        }

        // GET /invoices/:id - Get details
        if ($method === 'GET' && is_numeric($action)) {
            try {
                $invoiceId = (int)$action;

                $stmt = $db->prepare("SELECT * FROM v_invoice_details WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$invoice) {
                    Response::notFound('Invoice not found');
                    exit;
                }

                $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
                $invoice['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $db->prepare("
                SELECT p.*, u.name as recorded_by_name
                FROM invoice_payments p
                LEFT JOIN users u ON p.recorded_by = u.user_id
                WHERE p.invoice_id = ?
                ORDER BY p.payment_date DESC
            ");
                $stmt->execute([$invoiceId]);
                $invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                Response::success($invoice, 'Invoice retrieved');
            } catch (PDOException $e) {
                error_log("Invoice details error: " . $e->getMessage());
                Response::serverError('Failed to retrieve invoice');
            }
            exit;
        }

        // POST /invoices/generate
        if ($method === 'POST' && $action === 'generate') {
            try {
                $data = json_decode(file_get_contents('php://input'), true);
                $salesOrderId = $data['sales_order_id'] ?? null;

                if (!$salesOrderId) {
                    Response::badRequest('Sales order ID required');
                    exit;
                }

                $stmt = $db->prepare("SELECT * FROM sales_orders WHERE sales_order_id = ?");
                $stmt->execute([$salesOrderId]);
                $so = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$so) {
                    Response::notFound('Sales order not found');
                    exit;
                }

                $db->beginTransaction();

                $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) as max_num 
                               FROM invoices WHERE invoice_number LIKE 'INV-" . date('Y') . "-%'");
                $maxNum = $stmt->fetchColumn() ?: 0;
                $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

                $stmt = $db->prepare("SELECT SUM(line_total) as subtotal FROM sales_order_items WHERE sales_order_id = ?");
                $stmt->execute([$salesOrderId]);
                $subtotal = $stmt->fetchColumn() ?: 0;

                $taxRate = $data['tax_rate'] ?? 12.00;
                $taxAmount = $subtotal * ($taxRate / 100);
                $totalAmount = $subtotal + $taxAmount;

                $paymentTerms = $data['payment_terms'] ?? 'Net 30';
                $daysToAdd = 30;
                if (preg_match('/Net (\d+)/', $paymentTerms, $matches)) {
                    $daysToAdd = (int)$matches[1];
                }
                $dueDate = date('Y-m-d', strtotime("+{$daysToAdd} days"));

                $stmt = $db->prepare("
                INSERT INTO invoices (
                    invoice_number, sales_order_id, customer_name, 
                    subtotal, tax_rate, tax_amount, total_amount,
                    payment_terms, due_date, payment_status, generated_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)
            ");
                $stmt->execute([
                    $invoiceNumber,
                    $salesOrderId,
                    $so['customer_name'],
                    $subtotal,
                    $taxRate,
                    $taxAmount,
                    $totalAmount,
                    $paymentTerms,
                    $dueDate,
                    $user->user_id
                ]);

                $invoiceId = $db->lastInsertId();

                $stmt = $db->prepare("
                INSERT INTO invoice_items (invoice_id, item_id, item_name, sku, quantity, unit, unit_price, line_total)
                SELECT ?, soi.item_id, i.item_name, i.sku, soi.quantity, i.unit, soi.unit_price, soi.line_total
                FROM sales_order_items soi
                JOIN items i ON soi.item_id = i.item_id
                WHERE soi.sales_order_id = ?
            ");
                $stmt->execute([$invoiceId, $salesOrderId]);

                $stmt = $db->prepare("UPDATE sales_orders SET status = 'completed' WHERE sales_order_id = ?");
                $stmt->execute([$salesOrderId]);

                $stmt = $db->prepare("
                UPDATE items i
                JOIN sales_order_items soi ON i.item_id = soi.item_id
                SET i.quantity = i.quantity - soi.quantity
                WHERE soi.sales_order_id = ?
            ");
                $stmt->execute([$salesOrderId]);

                $stmt = $db->prepare("
                INSERT INTO transactions (item_id, user_id, transaction_type, quantity, unit_price, 
                                        reference_type, reference_number, previous_quantity, new_quantity)
                SELECT soi.item_id, ?, 'OUT', soi.quantity, soi.unit_price, 'INVOICE', ?,
                       i.quantity + soi.quantity, i.quantity
                FROM sales_order_items soi
                JOIN items i ON soi.item_id = i.item_id
                WHERE soi.sales_order_id = ?
            ");
                $stmt->execute([$user->user_id, $invoiceNumber, $salesOrderId]);

                $db->commit();

                Response::success([
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'total_amount' => $totalAmount
                ], 'Invoice generated', 201);
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Invoice generation error: " . $e->getMessage());
                Response::serverError('Failed to generate invoice');
            }
            exit;
        }

        // POST /invoices/:id/payment
        if ($method === 'POST' && is_numeric($action) && $id === 'payment') {
            try {
                $invoiceId = (int)$action;
                $data = json_decode(file_get_contents('php://input'), true);

                $amount = $data['amount'] ?? 0;
                if ($amount <= 0) {
                    Response::badRequest('Invalid amount');
                    exit;
                }

                $db->beginTransaction();

                $stmt = $db->prepare("SELECT total_amount FROM invoices WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$invoice) {
                    $db->rollBack();
                    Response::notFound('Invoice not found');
                    exit;
                }

                $stmt = $db->prepare("
                INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, 
                                             reference_number, notes, recorded_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
                $stmt->execute([
                    $invoiceId,
                    $data['payment_date'] ?? date('Y-m-d'),
                    $amount,
                    $data['payment_method'] ?? 'Cash',
                    $data['reference_number'] ?? null,
                    $data['notes'] ?? null,
                    $user->user_id
                ]);

                $stmt = $db->prepare("SELECT SUM(amount) FROM invoice_payments WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
                $totalPaid = $stmt->fetchColumn() ?: 0;

                $newStatus = ($totalPaid >= $invoice['total_amount']) ? 'paid' : 'partial';

                $stmt = $db->prepare("
                UPDATE invoices 
                SET payment_status = ?, paid_date = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_date END
                WHERE invoice_id = ?
            ");
                $stmt->execute([$newStatus, $newStatus, $invoiceId]);

                $db->commit();

                Response::success([
                    'payment_status' => $newStatus,
                    'total_paid' => $totalPaid,
                    'remaining' => $invoice['total_amount'] - $totalPaid
                ], 'Payment recorded');
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Payment error: " . $e->getMessage());
                Response::serverError('Failed to record payment');
            }
            exit;
        }

        Response::notFound('Invoice endpoint not found');
        exit;
    }

    /* ================================================================
   USERS ROUTES - MERGED ENHANCED + SUPERADMIN
================================================================ */
    if ($resource === 'users') {
        $ctrl = new UserController();
        $action = $segments[1] ?? '';
        $sub = $segments[2] ?? '';

        // Authenticate user for routes that require login
        $user = null;
        $authRequiredMethods = ['GET', 'POST', 'PUT', 'DELETE'];
        if (in_array($method, $authRequiredMethods)) {
            $user = AuthMiddleware::authenticate();
            if (!$user) exit;
        }

        // -----------------------------
        // GET /users/current - Get current logged-in user
        // -----------------------------
        if ($method === 'GET' && $action === 'current') {
            $ctrl->getCurrentUser();
            exit;
        }

        // POST /users/:id/profile-picture - Upload picture
        if ($method === 'POST' && is_numeric($action) && $sub === 'profile-picture') {
            $ctrl->uploadProfilePicture((int)$action);
            exit;
        }

        // DELETE /users/:id/profile-picture - Remove picture
        if ($method === 'DELETE' && is_numeric($action) && $sub === 'profile-picture') {
            $ctrl->removeProfilePicture((int)$action);
            exit;
        }
        // -----------------------------
        // GET /users - Get all users (superadmin only)
        // -----------------------------
        if ($method === 'GET' && $action === '') {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->getAll();
            exit;
        }

        // -----------------------------
        // GET /users/:id - Get specific user
        // -----------------------------
        if ($method === 'GET' && is_numeric($action)) {
            if ($user->user_id != (int)$action && $user->role !== 'superadmin') {
                Response::forbidden('Cannot view other users');
                exit;
            }
            $ctrl->getById((int)$action);
            exit;
        }

        // -----------------------------
        // POST /users - Create user (superadmin only)
        // -----------------------------
        if ($method === 'POST' && $action === '') {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->create();
            exit;
        }

        // -----------------------------
        // PUT /users/:id/profile - Update profile (name, email, contact)
        // -----------------------------
        if ($method === 'PUT' && is_numeric($action) && $sub === 'profile') {
            if ($user->user_id != (int)$action) {
                Response::forbidden('Cannot update other users profile');
                exit;
            }
            $ctrl->updateProfile((int)$action);
            exit;
        }

        // -----------------------------
        // PUT /users/:id - Update user (self or superadmin)
        // -----------------------------
        if ($method === 'PUT' && is_numeric($action) && $sub === '') {
            if ($user->user_id != (int)$action && $user->role !== 'superadmin') {
                Response::forbidden('Cannot update other users');
                exit;
            }
            $ctrl->update((int)$action);
            exit;
        }

        // -----------------------------
        // POST /users/:id/change-password - Change password
        // -----------------------------
        if ($method === 'POST' && is_numeric($action) && $sub === 'change-password') {
            if ($user->user_id != (int)$action) {
                Response::forbidden('Cannot change other users password');
                exit;
            }
            $ctrl->changePassword((int)$action);
            exit;
        }

        // -----------------------------
        // POST /users/:id/recovery-email - Set recovery email
        // -----------------------------
        if ($method === 'POST' && is_numeric($action) && $sub === 'recovery-email') {
            if ($user->user_id != (int)$action) {
                Response::forbidden();
                exit;
            }
            $ctrl->setRecoveryEmail((int)$action);
            exit;
        }

        // -----------------------------
        // GET /users/:id/export - Export user data (self or superadmin)
        // -----------------------------
        if ($method === 'GET' && is_numeric($action) && $sub === 'export') {
            if ($user->user_id != (int)$action && $user->role !== 'superadmin') {
                Response::forbidden();
                exit;
            }
            $ctrl->exportUserData((int)$action);
            exit;
        }

        // -----------------------------
        // GET /users/:id/sessions - Get active sessions (self only)
        // -----------------------------
        if ($method === 'GET' && is_numeric($action) && $sub === 'sessions') {
            if ($user->user_id != (int)$action) {
                Response::forbidden();
                exit;
            }
            $ctrl->getActiveSessions((int)$action);
            exit;
        }

        // -----------------------------
        // POST /users/:id/deletion-request - Request account deletion (self only)
        // -----------------------------
        if ($method === 'POST' && is_numeric($action) && $sub === 'deletion-request') {
            if ($user->user_id != (int)$action) {
                Response::forbidden();
                exit;
            }
            $ctrl->requestAccountDeletion((int)$action);
            exit;
        }

        // -----------------------------
        // DELETE /users/:id - Delete user (superadmin only)
        // -----------------------------
        if ($method === 'DELETE' && is_numeric($action)) {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->delete((int)$action);
            exit;
        }

        // -----------------------------
        // 404 fallback
        // -----------------------------
        Response::notFound('User endpoint not found');
        exit;
    }

    /* ================================================================
   PROFILE ROUTES - AUTHENTICATED USER SELF-MANAGEMENT
================================================================ */
    if ($resource === 'profile') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $action = $segments[1] ?? '';

        // GET /profile - Get current user profile
        if ($method === 'GET' && $action === '') {
            $ctrl = new \Janstro\InventorySystem\Controllers\ProfileController();
            $ctrl->getProfile();
            exit;
        }

        // PUT /profile/update - Update profile info
        if ($method === 'PUT' && $action === 'update') {
            $ctrl = new \Janstro\InventorySystem\Controllers\ProfileController();
            $ctrl->updateProfile();
            exit;
        }

        // POST /profile/change-password - Change password
        if ($method === 'POST' && $action === 'change-password') {
            $ctrl = new \Janstro\InventorySystem\Controllers\ProfileController();
            $ctrl->changePassword();
            exit;
        }

        Response::notFound('Profile endpoint not found');
        exit;
    }

    /* ================================================================
   EMAIL NOTIFICATION ROUTES (SUPERADMIN ONLY)
================================================================ */
    if ($resource === 'email') {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) exit;

        $action = $segments[1] ?? '';
        $ctrl = new \Janstro\InventorySystem\Controllers\EmailController();

        // POST /email/low-stock-alerts - Send low stock alerts
        if ($method === 'POST' && $action === 'low-stock-alerts') {
            $ctrl->sendLowStockAlerts();
            exit;
        }

        // POST /email/daily-summary - Send daily summary
        if ($method === 'POST' && $action === 'daily-summary') {
            $ctrl->sendDailySummary();
            exit;
        }

        // POST /email/test - Test email configuration
        if ($method === 'POST' && $action === 'test') {
            $ctrl->testEmail();
            exit;
        }

        // GET /email/logs - Get email logs
        if ($method === 'GET' && $action === 'logs') {
            $ctrl->getEmailLogs();
            exit;
        }

        // POST /email/process-queue - Process email queue
        if ($method === 'POST' && $action === 'process-queue') {
            $ctrl->processQueue();
            exit;
        }

        Response::notFound('Email endpoint not found');
        exit;
    }

    /* ================================================================
   PASSWORD RESET ROUTES (PUBLIC - NO AUTH)
================================================================ */
    if ($resource === 'password-reset') {
        $action = $segments[1] ?? '';
        $db = \Janstro\InventorySystem\Config\Database::connect();

        // POST /password-reset/request - Request password reset
        if ($method === 'POST' && $action === 'request') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Valid email address required');
                exit;
            }

            $stmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $db->prepare("
                UPDATE users 
                SET recovery_token = ?, recovery_token_expires = ?
                WHERE user_id = ?
            ");
                $stmt->execute([$token, $expires, $user['user_id']]);

                // TODO: Send email with reset link
                // EmailService::sendPasswordResetEmail($email, $token);

                Response::success(['token' => $token], 'Password reset email sent');
            } else {
                // Security: Don't reveal if email exists
                Response::success(null, 'If email exists, reset instructions sent');
            }
            exit;
        }

        // POST /password-reset/verify - Verify reset token
        if ($method === 'POST' && $action === 'verify') {
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? null;

            if (!$token) {
                Response::badRequest('Token required');
                exit;
            }

            $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE recovery_token = ? 
            AND recovery_token_expires > NOW()
        ");
            $stmt->execute([$token]);

            if ($stmt->fetch()) {
                Response::success(['valid' => true], 'Token is valid');
            } else {
                Response::badRequest('Invalid or expired token');
            }
            exit;
        }

        // POST /password-reset/confirm - Confirm password reset
        if ($method === 'POST' && $action === 'confirm') {
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? null;
            $password = $data['password'] ?? null;

            if (!$token || !$password) {
                Response::badRequest('Token and password required');
                exit;
            }

            if (strlen($password) < 8) {
                Response::badRequest('Password must be at least 8 characters');
                exit;
            }

            $stmt = $db->prepare("
            SELECT user_id FROM users 
            WHERE recovery_token = ? 
            AND recovery_token_expires > NOW()
        ");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                Response::badRequest('Invalid or expired token');
                exit;
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("
            UPDATE users 
            SET password_hash = ?,
                recovery_token = NULL,
                recovery_token_expires = NULL,
                last_password_change = NOW()
            WHERE user_id = ?
        ");
            $stmt->execute([$passwordHash, $user['user_id']]);

            Response::success(null, 'Password reset successfully');
            exit;
        }

        Response::notFound('Password reset endpoint not found');
        exit;
    }

    /* ================================================================
       AUDIT LOGS
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

            Response::success($logs, 'Audit logs retrieved');
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

    /* ================================================================
       NOTIFICATIONS API (NEW)
    ================================================================ */
    if ($resource === 'notifications') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $action = $segments[1] ?? '';

        // GET /notifications - Get user's notifications
        if ($method === 'GET' && $action === '') {
            $notifService = new \Janstro\InventorySystem\Services\NotificationService();
            $notifications = $notifService->getUnreadNotifications($user->user_id);
            Response::success([
                'notifications' => $notifications,
                'unread_count' => count($notifications)
            ], 'Notifications retrieved');
            exit;
        }

        // POST /notifications/:id/read - Mark as read
        if ($method === 'POST' && is_numeric($action) && ($segments[2] ?? '') === 'read') {
            $notifService = new \Janstro\InventorySystem\Services\NotificationService();
            $success = $notifService->markAsRead((int)$action, $user->user_id);

            if ($success) {
                Response::success(null, 'Notification marked as read');
            } else {
                Response::badRequest('Failed to update notification');
            }
            exit;
        }

        // POST /notifications/preferences - Update user preferences
        if ($method === 'POST' && $action === 'preferences') {
            $data = json_decode(file_get_contents('php://input'), true);
            $notifService = new \Janstro\InventorySystem\Services\NotificationService();
            $success = $notifService->updateUserPreferences($user->user_id, $data);

            if ($success) {
                Response::success(null, 'Preferences updated');
            } else {
                Response::serverError('Failed to update preferences');
            }
            exit;
        }

        Response::notFound('Notification endpoint not found');
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

    Response::serverError($e->getMessage());
    exit;
}
