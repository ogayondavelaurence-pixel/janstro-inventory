<?php

/**
 * ============================================================================
 * JANSTRO IMS - PRODUCTION ROUTER v8.4 (DELETION EMAIL FIX)
 * ============================================================================
 * CRITICAL FIX: Privacy routes now use ProfileController (emails work!)
 * ============================================================================
 */

require_once __DIR__ . '/../autoload.php';

// ============================================================================
// ENVIRONMENT & ERROR REPORTING
// ============================================================================
$appEnv = $_ENV['APP_ENV'] ?? 'development';
$isProduction = ($appEnv === 'production');

// ✅ CRITICAL: Start output buffering FIRST
ob_start();

if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// ============================================================================
// HTTPS ENFORCEMENT (PRODUCTION)
// ============================================================================
if ($isProduction) {
    \Janstro\InventorySystem\Utils\Security::enforceHTTPS();
}

// ============================================================================
// SESSION CONFIGURATION (SECURE)
// ============================================================================
$sessionLifetime = (int)($_ENV['SESSION_LIFETIME'] ?? 86400);
ini_set('session.gc_maxlifetime', (string)$sessionLifetime);
ini_set('session.cookie_lifetime', (string)$sessionLifetime);
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isProduction ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($isProduction) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// ============================================================================
// CORS CONFIGURATION
// ============================================================================
$allowedOrigins = [
    'http://localhost:8080',
    'http://localhost',
    'http://127.0.0.1:8080',
];

if (!empty($_ENV['FRONTEND_URL'])) {
    $allowedOrigins[] = $_ENV['FRONTEND_URL'];
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} elseif (!$isProduction) {
    if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header('Access-Control-Allow-Origin: null');
    }
} else {
    header('Access-Control-Allow-Origin: null');
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

// ============================================================================
// START SESSION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// PARSE REQUEST PATH
// ============================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isPdfDownload = (
    strpos($requestUri, '/pdf') !== false ||
    strpos($requestUri, '/download') !== false
);

if ($isPdfDownload) {
    // Kill all buffers for file downloads
    while (ob_get_level()) {
        ob_end_clean();
    }
    error_log("🔧 Output buffering disabled for: {$requestUri}");
}

// Parse request AFTER buffer handling
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = dirname($scriptName);
$path = str_replace($scriptDir, '', $requestUri);
$path = parse_url($path, PHP_URL_PATH);
$path = (string)$path;
$path = trim($path, '/');
$path = preg_replace('#/+#', '/', $path);
$path = preg_replace('#^index\.php/?#', '', $path);

if (strpos($path, 'api/') === 0) {
    $path = substr($path, 4);
}

$segments = $path !== '' ? explode('/', $path) : [];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ============================================================================
// CHECK AUTH/LOGIN BEFORE SECURITY MIDDLEWARE
// ============================================================================
$resource = $segments[0] ?? '';
$action = $segments[1] ?? '';
$sub = $segments[2] ?? '';
$isAuthLogin = ($resource === 'auth' && $action === 'login' && $method === 'POST');

error_log("🔍 Router v8.4 [{$appEnv}]: {$method} /{$path}");

// ============================================================================
// APPLY SECURITY MIDDLEWARE
// ============================================================================
\Janstro\InventorySystem\Middleware\SecurityMiddleware::protect(
    skipValidation: $isAuthLogin
);

// ============================================================================
// CONTROLLER IMPORTS
// ============================================================================
use Janstro\InventorySystem\Controllers\AuthController;
use Janstro\InventorySystem\Controllers\InventoryController;
use Janstro\InventorySystem\Controllers\OrderController;
use Janstro\InventorySystem\Controllers\UserController;
use Janstro\InventorySystem\Controllers\SupplierController;
use Janstro\InventorySystem\Controllers\ReportController;
use Janstro\InventorySystem\Controllers\ProfileController;
use Janstro\InventorySystem\Utils\Response;
use Janstro\InventorySystem\Services\CompleteInventoryService;
use Janstro\InventorySystem\Middleware\AuthMiddleware;

try {
    // ========================================================================
    // HEALTH CHECK (PUBLIC)
    // ========================================================================
    if (empty($path) || $path === 'health') {
        Response::success([
            'status' => 'running',
            'version' => '8.4.0',
            'environment' => $appEnv,
            'timestamp' => date('Y-m-d H:i:s'),
            'secure' => $isProduction
        ], 'API healthy');
        exit;
    }

    /* ================================================================
       AUTH ROUTES - PUBLIC
    ================================================================ */
    if ($resource === 'auth') {
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

        if ($method === 'POST' && $action === 'refresh') {
            $auth->refreshToken();
            exit;
        }

        if ($method === 'GET' && $action === 'verify') {
            $auth->verifyToken();
            exit;
        }

        Response::notFound('Auth endpoint not found');
        exit;
    }

    /* ================================================================
       PASSWORD RESET ROUTES (PUBLIC)
    ================================================================ */
    if ($resource === 'password-reset') {
        $db = \Janstro\InventorySystem\Config\Database::connect();

        // POST /password-reset/request - Request password reset
        if ($method === 'POST' && $action === 'request') {
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::badRequest('Valid email address required');
                exit;
            }

            try {
                $stmt = $db->prepare("SELECT user_id, username, name FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $stmt = $db->prepare("
                        UPDATE users 
                        SET recovery_token = ?, recovery_token_expires = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$token, $expires, $user['user_id']]);

                    // Send reset email
                    $emailService = new \Janstro\InventorySystem\Services\EmailService();
                    $emailService->sendPasswordReset($email, $token);

                    error_log("✅ Password reset email sent to: {$email}");
                }

                // Always return success (don't reveal if email exists)
                Response::success(null, 'If email exists, reset instructions have been sent');
            } catch (\Exception $e) {
                error_log("❌ Password reset request error: " . $e->getMessage());
                Response::serverError('Failed to process request');
            }
            exit;
        }

        // POST /password-reset/validate - Validate reset token
        if ($method === 'POST' && $action === 'validate') {
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? null;

            if (!$token) {
                Response::badRequest('Token required');
                exit;
            }

            try {
                $stmt = $db->prepare("
                    SELECT user_id, username, recovery_token_expires
                    FROM users 
                    WHERE recovery_token = ?
                ");
                $stmt->execute([$token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    Response::unauthorized('Invalid or expired token');
                    exit;
                }

                if (strtotime($user['recovery_token_expires']) < time()) {
                    Response::unauthorized('Token expired');
                    exit;
                }

                Response::success([
                    'valid' => true,
                    'username' => $user['username']
                ], 'Token valid');
            } catch (\Exception $e) {
                error_log("❌ Token validation error: " . $e->getMessage());
                Response::serverError('Validation failed');
            }
            exit;
        }

        // POST /password-reset/reset - Complete password reset
        if ($method === 'POST' && $action === 'reset') {
            $data = json_decode(file_get_contents('php://input'), true);
            $token = $data['token'] ?? null;
            $newPassword = $data['new_password'] ?? null;

            if (!$token || !$newPassword) {
                Response::badRequest('Token and new password required');
                exit;
            }

            if (strlen($newPassword) < 8) {
                Response::badRequest('Password must be at least 8 characters');
                exit;
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare("
                    SELECT user_id, recovery_token_expires
                    FROM users 
                    WHERE recovery_token = ?
                ");
                $stmt->execute([$token]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || strtotime($user['recovery_token_expires']) < time()) {
                    $db->rollBack();
                    Response::unauthorized('Invalid or expired token');
                    exit;
                }

                // Update password
                $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $stmt = $db->prepare("
                    UPDATE users 
                    SET password_hash = ?,
                        recovery_token = NULL,
                        recovery_token_expires = NULL,
                        last_password_change = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$passwordHash, $user['user_id']]);

                // Log audit
                $stmt = $db->prepare("
                    INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                    VALUES (?, 'Password reset via email', 'security', 'password_reset', ?)
                ");
                $stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

                $db->commit();

                error_log("✅ Password reset completed for user: {$user['user_id']}");
                Response::success(null, 'Password reset successful. Please login with your new password.');
            } catch (\Exception $e) {
                $db->rollBack();
                error_log("❌ Password reset error: " . $e->getMessage());
                Response::serverError('Failed to reset password');
            }
            exit;
        }

        Response::notFound('Password reset endpoint not found');
        exit;
    }

    /* ================================================================
   EMAIL SETTINGS ROUTES (SUPERADMIN ONLY) - REFACTORED v1.0
================================================================ */
    if ($resource === 'email-settings') {
        $ctrl = new \Janstro\InventorySystem\Controllers\EmailController();

        // GET /email-settings - Get configuration
        if ($method === 'GET' && $action === '') {
            $ctrl->getSettings();
            exit;
        }

        // POST /email-settings/save - Save configuration
        if ($method === 'POST' && $action === 'save') {
            $ctrl->saveSettings();
            exit;
        }

        // POST /email-settings/test - Send test email
        if ($method === 'POST' && $action === 'test') {
            $ctrl->testEmail();
            exit;
        }

        Response::notFound('Email settings endpoint not found');
        exit;
    }

    /* ================================================================
   EMAIL OPERATIONS (SUPERADMIN ONLY)
================================================================ */
    if ($resource === 'email') {
        $ctrl = new \Janstro\InventorySystem\Controllers\EmailController();

        // POST /email/low-stock-alerts
        if ($method === 'POST' && $action === 'low-stock-alerts') {
            $ctrl->sendLowStockAlerts();
            exit;
        }

        // GET /email/logs
        if ($method === 'GET' && $action === 'logs') {
            $ctrl->getEmailLogs();
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

    // ========================================================================
    // INVENTORY ROUTES
    // ========================================================================
    if ($resource === 'inventory') {
        $invCtrl = new InventoryController();

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET') {
            try {
                if ($action === '') {
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
                Response::serverError('Failed to retrieve inventory');
                exit;
            }
        }

        if ($method === 'POST' && $action === '') {
            $invCtrl->create();
            exit;
        }

        if ($method === 'PUT' && is_numeric($action)) {
            $invCtrl->update((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action)) {
            $invCtrl->delete((int)$action);
            exit;
        }

        Response::notFound('Inventory endpoint not found');
        exit;
    }

    // ========================================================================
    // CATEGORIES ROUTE
    // ========================================================================
    if ($resource === 'categories') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $stmt = $db->query("
                SELECT category_id, name, description, created_at 
                FROM categories 
                ORDER BY name
            ");

            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            Response::success($categories, 'Categories retrieved');
            exit;
        }

        Response::notFound('Category endpoint not found');
        exit;
    }

    /* ================================================================
       CUSTOMERS ROUTES - COMPLETE CRUD
    ================================================================ */
    if ($resource === 'customers') {
        $ctrl = new \Janstro\InventorySystem\Controllers\CustomerController();

        if ($method === 'GET' && $action === '') {
            AuthMiddleware::authenticate();
            $ctrl->getAll();
            exit;
        }

        if ($method === 'GET' && is_numeric($action)) {
            AuthMiddleware::authenticate();
            $ctrl->getById((int)$action);
            exit;
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

        Response::notFound('Customer endpoint not found');
        exit;
    }

    /* ================================================================
       TRANSACTIONS ROUTE
    ================================================================ */
    if ($resource === 'transactions') {
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            Response::unauthorized('Authentication required');
            exit;
        }

        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

            $stmt = $db->prepare("
                SELECT * FROM v_stock_movements
                LIMIT ?
            ");

            $stmt->execute([$limit]);
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success($transactions, 'Transactions retrieved');
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

        if (!($method === 'GET' && is_numeric($action) && $sub === 'pdf')) {
            $user = AuthMiddleware::authenticate();
        }

        if ($method === 'GET' && is_numeric($action) && $sub === 'pdf') {
            $order->downloadPDF((int)$action);
            exit;
        }

        if ($method === 'POST' && is_numeric($action) && $sub === 'receive') {
            $user = AuthMiddleware::authenticate();
            if (!$user) exit;

            $data = json_decode(file_get_contents('php://input'), true);
            $data['user_id'] = $user->user_id;

            try {
                $result = $svc->receiveGoods((int)$action, $data);
                Response::success($result, 'Goods received successfully');
            } catch (Exception $e) {
                error_log("Receive goods error: " . $e->getMessage());
                Response::serverError('Failed to receive goods: ' . $e->getMessage());
            }
            exit;
        }

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

        if ($method === 'PUT' && is_numeric($action)) {
            $order->update((int)$action);
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
       SUPPLIERS ROUTES
    ================================================================ */
    if ($resource === 'suppliers') {
        $ctrl = new SupplierController();

        if ($method === 'GET' && $action === '') {
            AuthMiddleware::authenticate();
            $ctrl->getAll();
            exit;
        }

        if ($method === 'GET' && is_numeric($action)) {
            AuthMiddleware::authenticate();
            $ctrl->getById((int)$action);
            exit;
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

        Response::notFound('Supplier endpoint not found');
        exit;
    }

    /* ================================================================
   STOCK REQUIREMENTS ROUTES - ENHANCED v2.0
================================================================ */
    if ($resource === 'stock-requirements') {
        $ctrl = new \Janstro\InventorySystem\Controllers\StockRequirementsController();

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        // GET /stock-requirements
        if ($method === 'GET' && $action === '') {
            $ctrl->getAll();
            exit;
        }

        // GET /stock-requirements/summary
        if ($method === 'GET' && $action === 'summary') {
            $ctrl->getSummary();
            exit;
        }

        // GET /stock-requirements/:id
        if ($method === 'GET' && is_numeric($action)) {
            $ctrl->getById((int)$action);
            exit;
        }

        // POST /stock-requirements/calculate/:sales_order_id
        if ($method === 'POST' && $action === 'calculate' && is_numeric($sub)) {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->recalculate((int)$sub);
            exit;
        }

        // ✅ NEW: POST /stock-requirements/batch-generate-pr
        if ($method === 'POST' && $action === 'batch-generate-pr') {
            AuthMiddleware::authenticate(); // Any authenticated user
            $ctrl->batchGeneratePR();
            exit;
        }

        // POST /stock-requirements/:id/generate-pr
        if ($method === 'POST' && is_numeric($action) && $sub === 'generate-pr') {
            $ctrl->generatePR((int)$action);
            exit;
        }

        Response::notFound('Stock requirements endpoint not found');
        exit;
    }

    /* ================================================================
        PURCHASE REQUISITIONS - COMPLETE ROUTES
    ================================================================ */
    if ($resource === 'purchase-requisitions') {
        $ctrl = new \Janstro\InventorySystem\Controllers\PurchaseRequisitionController();

        // GET /purchase-requisitions
        if ($method === 'GET' && $action === '') {
            AuthMiddleware::authenticate();
            $ctrl->getAll();
            exit;
        }

        // POST /purchase-requisitions/create-from-shortage
        if ($method === 'POST' && $action === 'create-from-shortage') {
            AuthMiddleware::authenticate();
            $ctrl->createFromShortage();
            exit;
        }

        // POST /purchase-requisitions/{id}/approve (ADMIN)
        if ($method === 'POST' && is_numeric($action) && $sub === 'approve') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->approve((int)$action);
            exit;
        }

        // POST /purchase-requisitions/{id}/reject (ADMIN)
        if ($method === 'POST' && is_numeric($action) && $sub === 'reject') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->reject((int)$action);
            exit;
        }

        // POST /purchase-requisitions/{id}/convert-to-po (ADMIN)
        if ($method === 'POST' && is_numeric($action) && $sub === 'convert-to-po') {
            AuthMiddleware::requireRole(['admin', 'superadmin']);
            $ctrl->convertToPO((int)$action);
            exit;
        }

        Response::notFound('PR endpoint not found');
        exit;
    }

    /* ================================================================
   INVOICES v3.0 (CONTROLLER-BASED)
================================================================ */
    if ($resource === 'invoices') {
        $ctrl = new \Janstro\InventorySystem\Controllers\InvoiceController();

        // GET /invoices
        if ($method === 'GET' && $action === '') {
            $ctrl->getAll();
            exit;
        }

        // GET /invoices/:id
        if ($method === 'GET' && is_numeric($action) && $sub === '') {
            $ctrl->getById((int)$action);
            exit;
        }

        // POST /invoices/generate
        if ($method === 'POST' && $action === 'generate') {
            $ctrl->generate();
            exit;
        }

        // POST /invoices/:id/payment
        if ($method === 'POST' && is_numeric($action) && $sub === 'payment') {
            $ctrl->recordPayment((int)$action);
            exit;
        }

        // POST /invoices/:id/email
        if ($method === 'POST' && is_numeric($action) && $sub === 'email') {
            $ctrl->sendEmail((int)$action);
            exit;
        }

        // GET /invoices/:id/pdf
        if ($method === 'GET' && is_numeric($action) && $sub === 'pdf') {
            $ctrl->downloadPDF((int)$action);
            exit;
        }

        // GET /invoices/stats
        if ($method === 'GET' && $action === 'stats') {
            $ctrl->getStats();
            exit;
        }

        Response::notFound('Invoice endpoint not found');
        exit;
    }

    /* ================================================================
   BILL OF MATERIALS (BOM) v3.0 - REFACTORED
================================================================ */
    if ($resource === 'bom') {
        $ctrl = new \Janstro\InventorySystem\Controllers\BomController();
        AuthMiddleware::authenticate();

        // GET /bom/families
        if ($method === 'GET' && $action === 'families') {
            $ctrl->getFamilies();
            exit;
        }

        // GET /bom/templates
        if ($method === 'GET' && $action === 'templates') {
            $ctrl->getTemplates();
            exit;
        }

        // POST /bom/templates
        if ($method === 'POST' && $action === 'templates') {
            $ctrl->createTemplate();
            exit;
        }

        // POST /bom/templates/:id/apply
        if ($method === 'POST' && is_numeric($action) && $sub === 'apply') {
            $ctrl->applyTemplate((int)$action);
            exit;
        }

        // GET /bom/:id/versions
        if ($method === 'GET' && is_numeric($action) && $sub === 'versions') {
            $ctrl->getVersions((int)$action);
            exit;
        }

        // GET /bom/:id/explosion
        if ($method === 'GET' && is_numeric($action) && $sub === 'explosion') {
            $ctrl->getExplosion((int)$action);
            exit;
        }

        // GET /bom
        if ($method === 'GET' && $action === '') {
            $ctrl->getAll();
            exit;
        }

        // POST /bom
        if ($method === 'POST' && $action === '') {
            $ctrl->create();
            exit;
        }

        // DELETE /bom/:id
        if ($method === 'DELETE' && is_numeric($action)) {
            $ctrl->delete((int)$action);
            exit;
        }

        Response::notFound('BOM endpoint not found');
        exit;
    }

    /* ================================================================
       USERS ROUTES
    ================================================================ */
    if ($resource === 'users') {
        $ctrl = new UserController();

        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET' && $action === 'current') {
            $ctrl->getCurrentUser();
            exit;
        }

        if ($method === 'POST' && is_numeric($action) && $sub === 'profile-picture') {
            $ctrl->uploadProfilePicture((int)$action);
            exit;
        }

        if ($method === 'DELETE' && is_numeric($action) && $sub === 'profile-picture') {
            $ctrl->removeProfilePicture((int)$action);
            exit;
        }

        if ($method === 'GET' && $action === '') {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->getAll();
            exit;
        }

        if ($method === 'GET' && is_numeric($action)) {
            if ($user->user_id != (int)$action && $user->role !== 'superadmin') {
                Response::forbidden('Cannot view other users');
                exit;
            }
            $ctrl->getById((int)$action);
            exit;
        }

        if ($method === 'POST' && $action === '') {
            AuthMiddleware::requireRole(['superadmin']);
            $ctrl->create();
            exit;
        }

        if ($method === 'PUT' && is_numeric($action)) {
            if ($user->user_id != (int)$action && $user->role !== 'superadmin') {
                Response::forbidden('Cannot update other users');
                exit;
            }
            $ctrl->update((int)$action);
            exit;
        }

        if ($method === 'POST' && is_numeric($action) && $sub === 'change-password') {
            if ($user->user_id != (int)$action) {
                Response::forbidden('Cannot change other users password');
                exit;
            }
            $ctrl->changePassword((int)$action);
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
       PROFILE ROUTES - ✅ NOW USES PROFILECONTROLLER (EMAILS WORK!)
    ================================================================ */
    if ($resource === 'profile') {
        $ctrl = new ProfileController();
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET' && $action === '') {
            $ctrl->getProfile();
            exit;
        }

        if ($method === 'PUT' && $action === 'update') {
            $ctrl->updateProfile();
            exit;
        }

        if ($method === 'POST' && $action === 'change-password') {
            $ctrl->changePassword();
            exit;
        }

        Response::notFound('Profile endpoint not found');
        exit;
    }


    /* ================================================================
       PRIVACY & ACCOUNT MANAGEMENT - ✅ FIXED v8.5
    ================================================================ */
    if ($resource === 'privacy') {
        $ctrl = new ProfileController();
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        // ✅ NEW: Check if user has pending deletion request
        if ($method === 'GET' && $action === 'deletion-status') {
            try {
                $db = \Janstro\InventorySystem\Config\Database::connect();

                $stmt = $db->prepare("
                    SELECT request_id, status, requested_at
                    FROM user_deletion_requests 
                    WHERE user_id = ? AND status = 'pending'
                    LIMIT 1
                ");
                $stmt->execute([$user->user_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                Response::success([
                    'has_pending' => $request !== false,
                    'request' => $request
                ], 'Deletion status retrieved');
            } catch (\Exception $e) {
                error_log("❌ Deletion status error: " . $e->getMessage());
                Response::serverError('Failed to check deletion status');
            }
            exit;
        }

        // POST /privacy/request-deletion
        if ($method === 'POST' && $action === 'request-deletion') {
            $ctrl->requestDeletion();
            exit;
        }

        // GET /privacy/sessions
        if ($method === 'GET' && $action === 'sessions') {
            $ctrl->getSessions();
            exit;
        }

        // DELETE /privacy/sessions/:id
        if ($method === 'DELETE' && $action === 'revoke-session' && is_numeric($sub)) {
            $ctrl->revokeSession((int)$sub);
            exit;
        }

        // POST /privacy/logout-all
        if ($method === 'POST' && $action === 'logout-all') {
            $ctrl->revokeAllSessions();
            exit;
        }

        // GET /privacy/export-data
        if ($method === 'GET' && $action === 'export-data') {
            $ctrl->exportData();
            exit;
        }

        Response::notFound('Privacy endpoint not found');
        exit;
    }
    /* ================================================================
       ADMIN DELETION REQUESTS - FIXED v8.7
    ================================================================ */
    if ($resource === 'admin' && $action === 'deletion-requests') {
        $user = AuthMiddleware::requireRole(['superadmin']);
        if (!$user) exit;

        $db = \Janstro\InventorySystem\Config\Database::connect();

        // GET /admin/deletion-requests - Get all deletion requests
        if ($method === 'GET' && $sub === '') {
            try {
                error_log("========================================");
                error_log("📥 FETCHING DELETION REQUESTS");
                error_log("========================================");

                $stmt = $db->query("
                    SELECT 
                        r.request_id,
                        r.user_id,
                        r.reason,
                        r.requested_at,
                        r.status,
                        u.username,
                        u.name,
                        u.email,
                        u.contact_no,
                        role.role_name
                    FROM user_deletion_requests r
                    LEFT JOIN users u ON r.user_id = u.user_id
                    LEFT JOIN roles role ON u.role_id = role.role_id
                    ORDER BY 
                        CASE r.status 
                            WHEN 'pending' THEN 1 
                            WHEN 'approved' THEN 2 
                            ELSE 3 
                        END,
                        r.requested_at DESC
                ");

                $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("✅ Found " . count($requests) . " deletion requests");
                error_log("========================================");

                Response::success($requests, 'Deletion requests retrieved');
            } catch (Exception $e) {
                error_log("========================================");
                error_log("❌ DELETION REQUESTS FETCH ERROR");
                error_log($e->getMessage());
                error_log("========================================");
                Response::serverError('Failed to retrieve deletion requests');
            }
            exit;
        }

        // ✅ FIX: Correct route parsing for approve/reject
        // POST /admin/deletion-requests/:id/approve
        // POST /admin/deletion-requests/:id/reject
        if ($method === 'POST' && is_numeric($sub)) {
            $requestId = (int)$sub;
            $actionType = $segments[3] ?? '';

            error_log("========================================");
            error_log("🔍 DELETION REQUEST ACTION");
            error_log("Request ID: {$requestId}");
            error_log("Action Type: {$actionType}");
            error_log("========================================");

            // POST /admin/deletion-requests/:id/approve
            if ($actionType === 'approve') {
                try {
                    error_log("✅ APPROVING DELETION REQUEST #{$requestId}");

                    $db->beginTransaction();

                    // Get request details
                    $stmt = $db->prepare("
            SELECT r.user_id, r.status, u.username, u.email, u.name, role.role_name
            FROM user_deletion_requests r
            LEFT JOIN users u ON r.user_id = u.user_id
            LEFT JOIN roles role ON u.role_id = role.role_id
            WHERE r.request_id = ?
        ");
                    $stmt->execute([$requestId]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$request) {
                        $db->rollBack();
                        Response::notFound('Request not found');
                        exit;
                    }

                    if ($request['status'] !== 'pending') {
                        $db->rollBack();
                        Response::badRequest('Request already processed');
                        exit;
                    }

                    $userId = $request['user_id'];
                    error_log("🗑️ Deleting user: {$request['username']} (ID: {$userId})");

                    // ✅ CRITICAL FIX: Proper FK constraint handling

                    // Step 1: Anonymize audit_logs (set user_id to NULL)
                    $db->prepare("UPDATE audit_logs SET user_id = NULL WHERE user_id = ?")->execute([$userId]);

                    // Step 2: Handle transactions (nullify user_id)
                    $db->prepare("UPDATE transactions SET user_id = NULL WHERE user_id = ?")->execute([$userId]);

                    // Step 3: Handle purchase_orders - set to NULL instead of 0
                    $db->prepare("UPDATE purchase_orders SET created_by = NULL WHERE created_by = ?")->execute([$userId]);

                    // Step 4: Handle sales_orders - set to NULL
                    $db->prepare("UPDATE sales_orders SET created_by = NULL WHERE created_by = ?")->execute([$userId]);
                    $db->prepare("UPDATE sales_orders SET completed_by = NULL WHERE completed_by = ?")->execute([$userId]);

                    // Step 5: Handle invoices - set to NULL
                    $db->prepare("UPDATE invoices SET generated_by = NULL WHERE generated_by = ?")->execute([$userId]);

                    // Step 6: Handle purchase_requisitions - set to NULL
                    $db->prepare("UPDATE purchase_requisitions SET requested_by = NULL WHERE requested_by = ?")->execute([$userId]);
                    $db->prepare("UPDATE purchase_requisitions SET approved_by = NULL WHERE approved_by = ?")->execute([$userId]);

                    // Step 7: Delete user sessions
                    $db->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$userId]);

                    // Step 8: DELETE ALL deletion requests for this user (including current)
                    $db->prepare("DELETE FROM user_deletion_requests WHERE user_id = ?")->execute([$userId]);

                    // Step 9: NOW safe to delete user
                    $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$userId]);

                    if ($stmt->rowCount() === 0) {
                        $db->rollBack();
                        Response::serverError('User deletion failed');
                        exit;
                    }

                    // Step 10: Audit log (create AFTER user deletion)
                    $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'admin', 'deletion_approved', ?)
        ");
                    $stmt->execute([
                        $user->user_id,
                        "Approved deletion of user: {$request['username']} (ID: {$userId})",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    $db->commit();
                    error_log("✅ Deletion approved successfully");

                    Response::success(null, 'Account deleted successfully');
                } catch (Exception $e) {
                    $db->rollBack();
                    error_log("❌ APPROVE DELETION ERROR: " . $e->getMessage());
                    Response::serverError('Failed to approve deletion: ' . $e->getMessage());
                }
                exit;
            }

            if ($actionType === 'reject') {
                try {
                    error_log("❌ REJECTING DELETION REQUEST #{$requestId}");

                    $stmt = $db->prepare("
                        UPDATE user_deletion_requests 
                        SET status = 'rejected' 
                        WHERE request_id = ? AND status = 'pending'
                    ");

                    if (!$stmt->execute([$requestId]) || $stmt->rowCount() === 0) {
                        error_log("❌ Request not found or already processed");
                        Response::badRequest('Request not found or already processed');
                        exit;
                    }

                    // Audit log
                    $stmt = $db->prepare("
                        INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                        VALUES (?, ?, 'admin', 'deletion_rejected', ?)
                    ");
                    $stmt->execute([
                        $user->user_id,
                        "Rejected deletion request #{$requestId}",
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    error_log("✅ Deletion rejected successfully");
                    error_log("========================================");

                    Response::success(null, 'Deletion request rejected');
                } catch (Exception $e) {
                    error_log("========================================");
                    error_log("❌ REJECT DELETION ERROR");
                    error_log($e->getMessage());
                    error_log("========================================");
                    Response::serverError('Failed to reject deletion');
                }
                exit;
            }
        }

        Response::notFound('Admin deletion endpoint not found');
        exit;
    }

    /* ================================================================
     AUDIT LOGS ROUTE
   ================================================================ */
    if ($resource === 'audit-logs') {
        AuthMiddleware::requireRole(['superadmin']);
        $db = \Janstro\InventorySystem\Config\Database::connect();

        if ($method === 'GET') {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;

            $where = [];
            $params = [];

            if (!empty($_GET['module'])) {
                $where[] = "a.module = ?";
                $params[] = $_GET['module'];
            }

            if (!empty($_GET['action_type'])) {
                $where[] = "a.action_type = ?";
                $params[] = $_GET['action_type'];
            }

            if (!empty($_GET['user_search'])) {
                $where[] = "(u.username LIKE ? OR u.name LIKE ?)";
                $search = '%' . $_GET['user_search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }

            $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id {$whereSQL}");
                $stmt->execute($params);
                $total = (int)$stmt->fetchColumn();

                $sql = "
                SELECT a.*, u.username, u.name as user_name
                FROM audit_logs a
                LEFT JOIN users u ON a.user_id = u.user_id
                {$whereSQL}
                ORDER BY a.created_at DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

                $stmt = $db->prepare($sql);
                $stmt->execute($params);

                Response::success([
                    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => (int)ceil($total / $perPage)
                    ]
                ], 'Audit logs retrieved');
            } catch (\PDOException $e) {
                error_log("Audit logs error: " . $e->getMessage());
                Response::serverError('Failed to retrieve audit logs');
            }
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

        if ($method === 'GET') {
            if ($action === 'dashboard') {
                $ctrl->getDashboardStats();
                exit;
            }
            if ($action === 'inventory-summary') {
                $ctrl->getInventorySummary();
                exit;
            }
            if ($action === 'transactions') {
                $ctrl->getTransactionHistory();
                exit;
            }
        }

        Response::notFound('Report endpoint not found');
        exit;
    }

    /* ================================================================
       NOTIFICATIONS API
    ================================================================ */
    if ($resource === 'notifications') {
        $user = AuthMiddleware::authenticate();
        if (!$user) exit;

        if ($method === 'GET' && $action === '') {
            $notifService = new \Janstro\InventorySystem\Services\NotificationService();
            $notifications = $notifService->getUnreadNotifications($user->user_id);
            Response::success([
                'notifications' => $notifications,
                'unread_count' => count($notifications)
            ], 'Notifications retrieved');
            exit;
        }

        Response::notFound('Notification endpoint not found');
        exit;
    }

    /* ================================================================
         ANALYTICS ROUTES
    ================================================================ */
    if ($resource === 'analytics') {
        $ctrl = new \Janstro\InventorySystem\Controllers\AnalyticsController();

        if ($method === 'GET' && $action === 'dashboard') {
            $ctrl->getDashboard();
            exit;
        }

        if ($method === 'GET' && $action === 'inventory') {
            $ctrl->getInventoryAnalysis();
            exit;
        }

        if ($method === 'GET' && $action === 'suppliers') {
            $ctrl->getSupplierPerformance();
            exit;
        }

        if ($method === 'GET' && $action === 'sales-forecast') {
            $ctrl->getSalesForecast();
            exit;
        }

        if ($method === 'GET' && $action === 'abc-analysis') {
            $ctrl->getABCAnalysis();
            exit;
        }

        if ($method === 'GET' && $action === 'stock-velocity') {
            $ctrl->getStockVelocity();
            exit;
        }

        if ($method === 'GET' && $action === 'pr-turnaround') {
            $ctrl->getPRTurnaround();
            exit;
        }

        Response::notFound('Analytics endpoint not found');
        exit;
    }

    // No route matched
    error_log("⚠️ No matching route for: /{$path}");
    Response::notFound("API endpoint not found: /{$path}");
    exit;
} catch (\Exception $e) {
    error_log("🚨 FATAL API ERROR: " . $e->getMessage());
    Response::serverError($isProduction ? 'Internal server error' : $e->getMessage());
    exit;
}
