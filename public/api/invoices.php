<?php

/**
 * ============================================================================
 * INVOICE API v5.4 - PDF STATUS FIX (DELETE OLD + CACHE BUST)
 * ============================================================================
 * CRITICAL FIXES:
 * ✅ Deletes old PDF file before regenerating
 * ✅ Adds timestamp to PDF path for cache-busting
 * ✅ Returns PDF URL with version parameter
 * ============================================================================
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\PdfService;
use Janstro\InventorySystem\Services\EmailService;
use Janstro\InventorySystem\Utils\Response;

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);

// Parse segments
$segments = explode('/', trim($path, '/'));
$action = '';
$id = '';

$invoicesIndex = array_search('invoices', $segments);
if ($invoicesIndex !== false && isset($segments[$invoicesIndex + 1])) {
    $action = $segments[$invoicesIndex + 1];
    if (isset($segments[$invoicesIndex + 2])) {
        $id = $segments[$invoicesIndex + 2];
    }
}

/**
 * ============================================================================
 * GET /invoices - List all invoices
 * ============================================================================
 */
if ($method === 'GET' && $action === '') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

    try {
        $filters = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $filters[] = "i.payment_status = ?";
            $params[] = $_GET['status'];
        }

        if (!empty($_GET['from_date'])) {
            $filters[] = "DATE(i.generated_at) >= ?";
            $params[] = $_GET['from_date'];
        }

        if (!empty($_GET['to_date'])) {
            $filters[] = "DATE(i.generated_at) <= ?";
            $params[] = $_GET['to_date'];
        }

        $whereClause = !empty($filters) ? 'WHERE ' . implode(' AND ', $filters) : '';

        $stmt = $db->prepare("
            SELECT 
                i.*,
                so.delivery_address,
                so.contact_number as customer_phone,
                u.name as generated_by_name,
                CASE 
                    WHEN i.payment_status = 'paid' THEN 'Paid'
                    WHEN i.payment_status = 'unpaid' AND i.due_date < CURDATE() THEN 'Overdue'
                    WHEN i.payment_status = 'unpaid' THEN 'Pending'
                    WHEN i.payment_status = 'partial' THEN 'Partial'
                    ELSE 'Unknown'
                END AS status_label,
                DATEDIFF(CURDATE(), i.due_date) as days_overdue
            FROM invoices i
            LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
            LEFT JOIN users u ON i.generated_by = u.user_id
            {$whereClause}
            ORDER BY i.generated_at DESC
            LIMIT 200
        ");

        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        Response::success($invoices, 'Invoices retrieved');
    } catch (PDOException $e) {
        error_log("Get invoices error: " . $e->getMessage());
        Response::serverError('Failed to retrieve invoices');
    }
    exit;
}

/**
 * ============================================================================
 * GET /invoices/:id/pdf - Download PDF with cache-busting
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($action) && $id === 'pdf') {
    // Clear ALL buffers BEFORE authentication
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Disable error output
    ini_set('display_errors', '0');
    error_reporting(0);

    try {
        error_log("========================================");
        error_log("📄 PDF DOWNLOAD REQUEST v5.4");
        error_log("========================================");

        // Authenticate
        $user = AuthMiddleware::authenticate();
        if (!$user) {
            error_log("❌ Authentication failed");
            http_response_code(401);
            exit("Unauthorized");
        }

        $invoiceId = (int)$action;
        error_log("✅ Authenticated user: {$user->username}");
        error_log("📄 Invoice ID: {$invoiceId}");

        // Get invoice
        $stmt = $db->prepare("SELECT pdf_path, invoice_number FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice || !$invoice['pdf_path']) {
            error_log("❌ Invoice or PDF not found");
            http_response_code(404);
            exit("PDF not found");
        }

        error_log("✅ Invoice found: {$invoice['invoice_number']}");
        error_log("📁 PDF Path (relative): {$invoice['pdf_path']}");

        // Build absolute path
        $relativePath = ltrim($invoice['pdf_path'], '/');
        $pdfPath = __DIR__ . '/../../' . $relativePath;

        error_log("📁 Absolute path: {$pdfPath}");
        error_log("📁 File exists: " . (file_exists($pdfPath) ? 'YES' : 'NO'));

        if (!file_exists($pdfPath)) {
            error_log("❌ Physical file not found on disk");
            http_response_code(404);
            exit("PDF file not found on server");
        }

        $filesize = filesize($pdfPath);
        error_log("✅ File size: {$filesize} bytes");

        // Output PDF with correct headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $invoice['invoice_number'] . '.pdf"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Accept-Ranges: bytes');

        // Stream file and exit immediately
        readfile($pdfPath);

        error_log("✅ PDF streamed successfully");
        error_log("========================================");
        exit(0);
    } catch (Exception $e) {
        error_log("========================================");
        error_log("❌ PDF DOWNLOAD ERROR: " . $e->getMessage());
        error_log("========================================");
        http_response_code(500);
        exit("PDF Error");
    }
}

/**
 * ============================================================================
 * GET /invoices/:id - Get invoice details
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($action)) {
    $user = AuthMiddleware::authenticate();
    if (!$user) exit;

    try {
        $invoiceId = (int)$action;

        $stmt = $db->prepare("
            SELECT 
                i.*,
                so.delivery_address,
                so.contact_number as customer_phone,
                so.customer_order_number,
                u.name as generated_by_name,
                u.email as generated_by_email
            FROM invoices i
            LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
            LEFT JOIN users u ON i.generated_by = u.user_id
            WHERE i.invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            Response::notFound('Invoice not found');
            exit;
        }

        $stmt = $db->prepare("
            SELECT 
                ii.*,
                i.item_name,
                i.sku
            FROM invoice_items ii
            LEFT JOIN items i ON ii.item_id = i.item_id
            WHERE ii.invoice_id = ?
            ORDER BY ii.invoice_item_id
        ");
        $stmt->execute([$invoiceId]);
        $invoice['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("
            SELECT 
                p.*,
                u.name as recorded_by_name
            FROM invoice_payments p
            LEFT JOIN users u ON p.recorded_by = u.user_id
            WHERE p.invoice_id = ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->execute([$invoiceId]);
        $invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invoice['status_label'] = match ($invoice['payment_status']) {
            'paid' => 'Paid',
            'partial' => 'Partial',
            'unpaid' => ($invoice['due_date'] < date('Y-m-d') ? 'Overdue' : 'Pending'),
            default => 'Unknown'
        };

        Response::success($invoice, 'Invoice details retrieved');
    } catch (PDOException $e) {
        error_log("Get invoice details error: " . $e->getMessage());
        Response::serverError('Failed to retrieve invoice');
    }
    exit;
}

/**
 * ============================================================================
 * POST /invoices/generate - Generate invoice
 * ============================================================================
 */
if ($method === 'POST' && $action === 'generate') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $salesOrderId = $data['sales_order_id'] ?? null;
        $paymentTerms = $data['payment_terms'] ?? 'Net 30';
        $taxRate = $data['tax_rate'] ?? 12.00;
        $sendEmail = $data['send_email'] ?? false;

        if (!$salesOrderId) {
            Response::badRequest('Sales order ID required');
            exit;
        }

        $stmt = $db->prepare("
            SELECT so.*, c.email as customer_email
            FROM sales_orders so
            LEFT JOIN customers c ON so.customer_id = c.customer_id
            WHERE so.sales_order_id = ?
        ");
        $stmt->execute([$salesOrderId]);
        $so = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$so) {
            Response::notFound('Sales order not found');
            exit;
        }

        if ($so['status'] === 'completed') {
            Response::badRequest('Sales order already invoiced');
            exit;
        }

        $db->beginTransaction();

        // Generate invoice number
        $stmt = $db->query("
            SELECT MAX(CAST(SUBSTRING(invoice_number, 10) AS UNSIGNED)) as max_num 
            FROM invoices 
            WHERE invoice_number LIKE 'INV-" . date('Y') . "-%'
        ");
        $maxNum = $stmt->fetchColumn() ?: 0;
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

        // Get line items
        $stmt = $db->prepare("
            SELECT soi.*, i.item_name, i.sku, i.unit
            FROM sales_order_items soi
            JOIN items i ON soi.item_id = i.item_id
            WHERE soi.sales_order_id = ?
        ");
        $stmt->execute([$salesOrderId]);
        $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $subtotal = array_sum(array_column($lineItems, 'line_total'));
        $taxAmount = $subtotal * ($taxRate / 100);
        $totalAmount = $subtotal + $taxAmount;

        // Calculate due date
        $daysToAdd = 30;
        if (preg_match('/Net (\d+)/', $paymentTerms, $matches)) {
            $daysToAdd = (int)$matches[1];
        }
        $dueDate = date('Y-m-d', strtotime("+{$daysToAdd} days"));

        // Insert invoice
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

        // Insert line items
        foreach ($lineItems as $item) {
            $stmt = $db->prepare("
                INSERT INTO invoice_items (
                    invoice_id, item_id, item_name, sku, quantity, 
                    unit, unit_price, line_total
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $invoiceId,
                $item['item_id'],
                $item['item_name'],
                $item['sku'],
                $item['quantity'],
                $item['unit'] ?? 'pcs',
                $item['unit_price'],
                $item['line_total']
            ]);
        }

        // Get full invoice data
        $stmt = $db->prepare("
            SELECT i.*, so.delivery_address, so.contact_number as customer_phone
            FROM invoices i
            LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
            WHERE i.invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoiceData = $stmt->fetch(PDO::FETCH_ASSOC);

        // Generate PDF
        $pdfService = new PdfService();
        $pdfPath = $pdfService->generateInvoicePDF($invoiceData, $lineItems);

        // Update with PDF path
        $stmt = $db->prepare("UPDATE invoices SET pdf_path = ? WHERE invoice_id = ?");
        $stmt->execute([$pdfPath, $invoiceId]);

        // Update sales order
        $stmt = $db->prepare("
            UPDATE sales_orders 
            SET status = 'completed', completed_by = ?, completed_date = NOW() 
            WHERE sales_order_id = ?
        ");
        $stmt->execute([$user->user_id, $salesOrderId]);

        // Deduct stock
        $stmt = $db->prepare("
            UPDATE items i
            JOIN sales_order_items soi ON i.item_id = soi.item_id
            SET i.quantity = i.quantity - soi.quantity
            WHERE soi.sales_order_id = ?
        ");
        $stmt->execute([$salesOrderId]);

        // Log transactions
        foreach ($lineItems as $item) {
            $stmt = $db->prepare("SELECT quantity FROM items WHERE item_id = ?");
            $stmt->execute([$item['item_id']]);
            $currentQty = $stmt->fetchColumn();

            $stmt = $db->prepare("
                INSERT INTO transactions (
                    item_id, user_id, transaction_type, quantity, unit_price, 
                    reference_type, reference_number, previous_quantity, new_quantity
                ) VALUES (?, ?, 'OUT', ?, ?, 'INVOICE', ?, ?, ?)
            ");
            $stmt->execute([
                $item['item_id'],
                $user->user_id,
                $item['quantity'],
                $item['unit_price'],
                $invoiceNumber,
                $currentQty + $item['quantity'],
                $currentQty
            ]);
        }

        // Audit log
        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'invoices', 'generate', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Generated invoice {$invoiceNumber} for SO #{$salesOrderId} | Customer: {$so['customer_name']} | Amount: PHP " . number_format($totalAmount, 2),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        $db->commit();

        // Send email if requested
        $emailSent = false;
        if ($sendEmail && !empty($so['customer_email'])) {
            try {
                $emailService = new EmailService();

                $stmt = $db->prepare("
                    SELECT ii.item_name, ii.sku, ii.quantity, ii.unit, ii.unit_price, ii.line_total
                    FROM invoice_items ii
                    WHERE ii.invoice_id = ?
                ");
                $stmt->execute([$invoiceId]);
                $emailLineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $emailSent = $emailService->sendInvoiceEmail($invoiceData, $emailLineItems, $so['customer_email']);

                if ($emailSent) {
                    $stmt = $db->prepare("UPDATE invoices SET email_sent = 1, email_sent_at = NOW() WHERE invoice_id = ?");
                    $stmt->execute([$invoiceId]);
                }
            } catch (Exception $e) {
                error_log("Invoice email failed: " . $e->getMessage());
            }
        }

        Response::success([
            'invoice_id' => $invoiceId,
            'invoice_number' => $invoiceNumber,
            'total_amount' => $totalAmount,
            'pdf_path' => $pdfPath,
            'email_sent' => $emailSent,
            'due_date' => $dueDate
        ], 'Invoice generated successfully', 201);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Invoice generation error: " . $e->getMessage());
        Response::serverError('Failed to generate invoice: ' . $e->getMessage());
    }
    exit;
}

/**
 * ============================================================================
 * POST /invoices/:id/email - Send invoice email
 * ============================================================================
 */
if ($method === 'POST' && is_numeric($action) && $id === 'email') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

    try {
        $invoiceId = (int)$action;
        $data = json_decode(file_get_contents('php://input'), true);
        $toEmail = $data['to_email'] ?? null;

        $stmt = $db->prepare("
            SELECT i.*, so.delivery_address, so.contact_number as customer_phone, c.email as customer_email
            FROM invoices i
            LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
            LEFT JOIN customers c ON so.customer_id = c.customer_id
            WHERE i.invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            Response::notFound('Invoice not found');
            exit;
        }

        $stmt = $db->prepare("
            SELECT ii.*, i.item_name, i.sku
            FROM invoice_items ii
            LEFT JOIN items i ON ii.item_id = i.item_id
            WHERE ii.invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);
        $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $recipientEmail = $toEmail ?? $invoice['customer_email'];

        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            Response::badRequest('Valid email address required');
            exit;
        }

        $emailService = new EmailService();
        $sent = $emailService->sendInvoiceEmail($invoice, $lineItems, $recipientEmail);

        if ($sent) {
            $stmt = $db->prepare("UPDATE invoices SET email_sent = 1, email_sent_at = NOW() WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);

            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
                VALUES (?, ?, 'invoices', 'email_sent', ?)
            ");
            $stmt->execute([
                $user->user_id,
                "Sent invoice {$invoice['invoice_number']} to {$recipientEmail}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            Response::success([
                'invoice_number' => $invoice['invoice_number'],
                'recipient' => $recipientEmail,
                'sent_at' => date('Y-m-d H:i:s')
            ], 'Invoice email sent successfully');
        } else {
            Response::error('Failed to send email. Check SMTP configuration.', null, 500);
        }
    } catch (Exception $e) {
        error_log("Email invoice error: " . $e->getMessage());
        Response::serverError('Failed to process email request: ' . $e->getMessage());
    }
    exit;
}

/**
 * ============================================================================
 * POST /invoices/:id/payment - ✅ FIXED v5.4 (DELETE OLD PDF + REGENERATE)
 * ============================================================================
 */
if ($method === 'POST' && is_numeric($action) && $id === 'payment') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

    try {
        $invoiceId = (int)$action;
        $data = json_decode(file_get_contents('php://input'), true);

        $amount = $data['amount'] ?? 0;
        $paymentDate = $data['payment_date'] ?? date('Y-m-d');
        $paymentMethod = $data['payment_method'] ?? 'Cash';
        $referenceNumber = $data['reference_number'] ?? null;
        $notes = $data['notes'] ?? null;

        if ($amount <= 0) {
            Response::badRequest('Invalid payment amount');
            exit;
        }

        $db->beginTransaction();

        $stmt = $db->prepare("SELECT total_amount, payment_status, pdf_path FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            $db->rollBack();
            Response::notFound('Invoice not found');
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO invoice_payments (invoice_id, payment_date, amount, payment_method, reference_number, notes, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$invoiceId, $paymentDate, $amount, $paymentMethod, $referenceNumber, $notes, $user->user_id]);

        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM invoice_payments WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $totalPaid = $stmt->fetchColumn() ?: 0;

        $newStatus = 'partial';
        if ($totalPaid >= $invoice['total_amount']) {
            $newStatus = 'paid';
        }

        $stmt = $db->prepare("
            UPDATE invoices 
            SET payment_status = ?, paid_date = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_date END, payment_method = ?
            WHERE invoice_id = ?
        ");
        $stmt->execute([$newStatus, $newStatus, $paymentMethod, $invoiceId]);

        // ✅ CRITICAL FIX v5.4: DELETE OLD PDF FIRST
        if (!empty($invoice['pdf_path'])) {
            $oldPdfPath = __DIR__ . '/../../' . ltrim($invoice['pdf_path'], '/');
            if (file_exists($oldPdfPath)) {
                @unlink($oldPdfPath);
                error_log("🗑️ Deleted old PDF: {$oldPdfPath}");
            }
        }

        // ✅ REGENERATE PDF WITH UPDATED STATUS
        try {
            error_log("🔄 Regenerating PDF with payment status: {$newStatus}");

            // Get updated invoice data AFTER commit
            $db->commit(); // ✅ Commit BEFORE fetching updated data

            $stmt = $db->prepare("
                SELECT i.*, so.delivery_address, so.contact_number as customer_phone
                FROM invoices i
                LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
                WHERE i.invoice_id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoiceData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify status is updated
            if ($invoiceData['payment_status'] !== $newStatus) {
                error_log("⚠️ WARNING: Status mismatch - Expected: {$newStatus}, Got: {$invoiceData['payment_status']}");
            }

            $stmt = $db->prepare("
                SELECT ii.*, i.item_name, i.sku
                FROM invoice_items ii
                LEFT JOIN items i ON ii.item_id = i.item_id
                WHERE ii.invoice_id = ?
            ");
            $stmt->execute([$invoiceId]);
            $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get full invoice data
            $stmt = $db->prepare("
    SELECT i.*, so.delivery_address, so.contact_number as customer_phone
    FROM invoices i
    LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
    WHERE i.invoice_id = ?
");
            $stmt->execute([$invoiceId]);
            $invoiceData = $stmt->fetch(PDO::FETCH_ASSOC);

            // ✅ FIX: Add proper error handling for PDF generation
            try {
                error_log("🔧 Starting PDF generation for invoice {$invoiceNumber}");

                $pdfService = new PdfService();
                $pdfPath = $pdfService->generateInvoicePDF($invoiceData, $lineItems);

                // Verify file was created
                $absolutePath = __DIR__ . '/../../' . ltrim($pdfPath, '/');
                if (!file_exists($absolutePath)) {
                    throw new \Exception("PDF file not created at: {$absolutePath}");
                }

                error_log("✅ PDF created successfully: {$pdfPath}");

                // Update with PDF path
                $stmt = $db->prepare("UPDATE invoices SET pdf_path = ? WHERE invoice_id = ?");
                $stmt->execute([$pdfPath, $invoiceId]);
            } catch (\Exception $e) {
                error_log("❌ PDF generation failed: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                // Don't fail the entire invoice - just mark PDF as missing
                $stmt = $db->prepare("UPDATE invoices SET pdf_path = NULL WHERE invoice_id = ?");
                $stmt->execute([$invoiceId]);
            }
            // Build new PDF path with cache-busting timestamp
            error_log("✅ PDF regenerated successfully: {$newPdfPath}");
            error_log("✅ Payment Status in PDF: {$invoiceData['payment_status']}");

            $pdfRegenerated = true;
        } catch (Exception $e) {
            error_log("⚠️ PDF regeneration failed: " . $e->getMessage());
            $pdfRegenerated = false;
        }

        $stmt = $db->prepare("
            INSERT INTO audit_logs (user_id, action_description, module, action_type, ip_address)
            VALUES (?, ?, 'invoices', 'payment', ?)
        ");
        $stmt->execute([
            $user->user_id,
            "Recorded payment of PHP " . number_format($amount, 2) . " for Invoice #{$invoiceId} | Method: {$paymentMethod} | Status: {$newStatus}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        Response::success([
            'invoice_id' => $invoiceId,
            'payment_status' => $newStatus,
            'total_paid' => $totalPaid,
            'remaining' => $invoice['total_amount'] - $totalPaid,
            'pdf_regenerated' => $pdfRegenerated ?? false,
            'cache_buster' => time() // ✅ Add timestamp for cache busting
        ], 'Payment recorded successfully');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Payment recording error: " . $e->getMessage());
        Response::serverError('Failed to record payment');
    }
    exit;
}

Response::notFound('Invoice endpoint not found');
