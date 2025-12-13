<?php

/**
 * ============================================================================
 * JANSTRO IMS - INVOICE API v2.0 (PRODUCTION-READY)
 * ============================================================================
 * File: public/api/invoices-v2.php
 * Description: Complete invoice management with PDF generation and email
 * 
 * ADD TO public/index.php ROUTER:
 * 
 * if ($resource === 'invoices') {
 *     require_once __DIR__ . '/api/invoices-v2.php';
 *     exit;
 * }
 * ============================================================================
 */

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Utils\Response;

$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$segments = explode('/', trim($path, '/'));
$action = $segments[1] ?? '';
$id = $segments[2] ?? '';

/**
 * ============================================================================
 * GET /invoices - List all invoices
 * ============================================================================
 */
if ($method === 'GET' && $action === '') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

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
    exit;
}

/**
 * ============================================================================
 * GET /invoices/:id - Get invoice details with line items
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($action)) {
    $user = AuthMiddleware::authenticate();
    if (!$user) exit;

    $invoiceId = (int)$action;

    // Get invoice header
    $stmt = $db->prepare("SELECT * FROM v_invoice_details WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        Response::notFound('Invoice not found');
        exit;
    }

    // Get line items
    $stmt = $db->prepare("
        SELECT 
            ii.*,
            i.quantity as current_stock
        FROM invoice_items ii
        LEFT JOIN items i ON ii.item_id = i.item_id
        WHERE ii.invoice_id = ?
        ORDER BY ii.invoice_item_id
    ");
    $stmt->execute([$invoiceId]);
    $invoice['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get payment history
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

    // Get history
    $stmt = $db->prepare("
        SELECT 
            h.*,
            u.name as user_name
        FROM invoice_history h
        LEFT JOIN users u ON h.user_id = u.user_id
        WHERE h.invoice_id = ?
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([$invoiceId]);
    $invoice['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    Response::success($invoice, 'Invoice details retrieved');
    exit;
}

/**
 * ============================================================================
 * POST /invoices/generate - Generate invoice from sales order
 * ============================================================================
 */
if ($method === 'POST' && $action === 'generate') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

    $data = json_decode(file_get_contents('php://input'), true);
    $salesOrderId = $data['sales_order_id'] ?? null;
    $paymentTerms = $data['payment_terms'] ?? 'Net 30';
    $taxRate = $data['tax_rate'] ?? 12.00;

    if (!$salesOrderId) {
        Response::badRequest('Sales order ID required');
        exit;
    }

    try {
        // Call stored procedure
        $stmt = $db->prepare("
            CALL sp_generate_invoice(?, ?, ?, ?, @invoice_id, @invoice_number, @success, @message)
        ");
        $stmt->execute([$salesOrderId, $user->user_id, $paymentTerms, $taxRate]);

        // Get output parameters
        $result = $db->query("SELECT @invoice_id as invoice_id, @invoice_number as invoice_number, 
                                     @success as success, @message as message")->fetch(PDO::FETCH_ASSOC);

        if ($result['success']) {
            // Generate PDF
            $pdfPath = generateInvoicePDF((int)$result['invoice_id']);

            // Update invoice with PDF path
            $stmt = $db->prepare("UPDATE invoices SET pdf_path = ? WHERE invoice_id = ?");
            $stmt->execute([$pdfPath, $result['invoice_id']]);

            // Send email if enabled
            if ($data['send_email'] ?? false) {
                sendInvoiceEmail((int)$result['invoice_id']);
            }

            Response::success([
                'invoice_id' => (int)$result['invoice_id'],
                'invoice_number' => $result['invoice_number'],
                'pdf_path' => $pdfPath,
                'message' => $result['message']
            ], 'Invoice generated successfully', 201);
        } else {
            Response::error($result['message'], null, 400);
        }
    } catch (PDOException $e) {
        error_log("Invoice generation error: " . $e->getMessage());
        Response::serverError('Failed to generate invoice');
    }
    exit;
}

/**
 * ============================================================================
 * POST /invoices/:id/payment - Record payment
 * ============================================================================
 */
if ($method === 'POST' && is_numeric($action) && $id === 'payment') {
    $user = AuthMiddleware::requireRole(['admin', 'superadmin']);
    if (!$user) exit;

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

    try {
        $db->beginTransaction();

        // Get invoice details
        $stmt = $db->prepare("SELECT total_amount, payment_status FROM invoices WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            Response::notFound('Invoice not found');
            $db->rollBack();
            exit;
        }

        // Record payment
        $stmt = $db->prepare("
            INSERT INTO invoice_payments (
                invoice_id, payment_date, amount, payment_method, 
                reference_number, notes, recorded_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $invoiceId,
            $paymentDate,
            $amount,
            $paymentMethod,
            $referenceNumber,
            $notes,
            $user->user_id
        ]);

        // Calculate total paid
        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM invoice_payments WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);
        $totalPaid = $stmt->fetchColumn() ?: 0;

        // Update invoice status
        $newStatus = 'partial';
        if ($totalPaid >= $invoice['total_amount']) {
            $newStatus = 'paid';
        }

        $stmt = $db->prepare("
            UPDATE invoices 
            SET payment_status = ?,
                paid_date = CASE WHEN ? = 'paid' THEN NOW() ELSE paid_date END,
                payment_method = ?
            WHERE invoice_id = ?
        ");
        $stmt->execute([$newStatus, $newStatus, $paymentMethod, $invoiceId]);

        $db->commit();

        Response::success([
            'invoice_id' => $invoiceId,
            'payment_status' => $newStatus,
            'total_paid' => $totalPaid,
            'remaining' => $invoice['total_amount'] - $totalPaid
        ], 'Payment recorded successfully');
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Payment recording error: " . $e->getMessage());
        Response::serverError('Failed to record payment');
    }
    exit;
}

/**
 * ============================================================================
 * GET /invoices/:id/pdf - Download PDF
 * ============================================================================
 */
if ($method === 'GET' && is_numeric($action) && $id === 'pdf') {
    $user = AuthMiddleware::authenticate();
    if (!$user) exit;

    $invoiceId = (int)$action;

    $stmt = $db->prepare("SELECT pdf_path, invoice_number FROM invoices WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice || !$invoice['pdf_path']) {
        Response::notFound('PDF not found');
        exit;
    }

    $pdfPath = __DIR__ . '/../../' . $invoice['pdf_path'];

    if (!file_exists($pdfPath)) {
        Response::notFound('PDF file not found');
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $invoice['invoice_number'] . '.pdf"');
    header('Content-Length: ' . filesize($pdfPath));
    readfile($pdfPath);
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

    $invoiceId = (int)$action;
    $data = json_decode(file_get_contents('php://input'), true);
    $toEmail = $data['to_email'] ?? null;

    $result = sendInvoiceEmail($invoiceId, $toEmail);

    if ($result['success']) {
        Response::success($result, 'Invoice email sent');
    } else {
        Response::error($result['message'], null, 400);
    }
    exit;
}

/**
 * ============================================================================
 * HELPER FUNCTION: Generate Invoice PDF
 * ============================================================================
 */
function generateInvoicePDF($invoiceId)
{
    global $db;

    // Get invoice data
    $stmt = $db->prepare("
        SELECT 
            i.*,
            so.delivery_address,
            so.contact_number as customer_phone
        FROM invoices i
        LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get line items
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY invoice_item_id");
    $stmt->execute([$invoiceId]);
    $lineItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate HTML invoice
    $html = renderInvoiceHTML($invoice, $lineItems);

    // Save HTML to file (for debugging and as backup)
    $uploadDir = __DIR__ . '/../../storage/invoices/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = $invoice['invoice_number'] . '.html';
    $filepath = $uploadDir . $filename;
    file_put_contents($filepath, $html);

    // Return path relative to project root
    return 'storage/invoices/' . $filename;
}

/**
 * ============================================================================
 * HELPER FUNCTION: Render Invoice HTML
 * ============================================================================
 */
function renderInvoiceHTML($invoice, $lineItems)
{
    $companyName = $_ENV['COMPANY_NAME'] ?? 'Janstro Prime Corporation';
    $companyAddress = $_ENV['COMPANY_ADDRESS'] ?? 'Majayjay, Calabarzon, Philippines';
    $companyPhone = $_ENV['COMPANY_PHONE'] ?? '+63-999 759 4616';
    $companyEmail = $_ENV['COMPANY_EMAIL'] ?? 'janstroprime@gmail.com';

    ob_start();
?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($invoice['invoice_number']) ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Arial', sans-serif;
                font-size: 12px;
                color: #333;
                padding: 20px;
            }

            .invoice-container {
                max-width: 800px;
                margin: 0 auto;
                border: 1px solid #ddd;
                padding: 30px;
            }

            .header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 30px;
                border-bottom: 3px solid #667eea;
                padding-bottom: 20px;
            }

            .company-info {
                flex: 1;
            }

            .company-name {
                font-size: 24px;
                font-weight: bold;
                color: #667eea;
                margin-bottom: 10px;
            }

            .invoice-title {
                text-align: right;
            }

            .invoice-number {
                font-size: 28px;
                font-weight: bold;
                color: #333;
            }

            .invoice-date {
                color: #666;
                margin-top: 5px;
            }

            .details-section {
                display: flex;
                justify-content: space-between;
                margin: 30px 0;
            }

            .bill-to,
            .invoice-details {
                flex: 1;
            }

            .section-title {
                font-size: 14px;
                font-weight: bold;
                color: #667eea;
                margin-bottom: 10px;
                text-transform: uppercase;
            }

            .info-line {
                margin: 5px 0;
            }

            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: 30px 0;
            }

            .items-table th {
                background: #667eea;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: 600;
            }

            .items-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
            }

            .items-table tr:last-child td {
                border-bottom: none;
            }

            .text-right {
                text-align: right;
            }

            .text-center {
                text-align: center;
            }

            .summary {
                margin-top: 30px;
                float: right;
                width: 300px;
            }

            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }

            .summary-row.total {
                font-size: 18px;
                font-weight: bold;
                background: #f5f5f5;
                padding: 12px;
                margin-top: 10px;
                border: 2px solid #667eea;
            }

            .footer {
                clear: both;
                margin-top: 50px;
                padding-top: 20px;
                border-top: 2px solid #eee;
                text-align: center;
                color: #666;
                font-size: 11px;
            }

            .status-badge {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-weight: 600;
                text-transform: uppercase;
                font-size: 10px;
            }

            .status-unpaid {
                background: #fed7d7;
                color: #c53030;
            }

            .status-paid {
                background: #c6f6d5;
                color: #2f855a;
            }

            .status-partial {
                background: #feebc8;
                color: #c05621;
            }

            @media print {
                body {
                    padding: 0;
                }

                .invoice-container {
                    border: none;
                    max-width: 100%;
                }
            }
        </style>
    </head>

    <body>
        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <div class="company-info">
                    <div class="company-name">☀️ <?= htmlspecialchars($companyName) ?></div>
                    <div><?= htmlspecialchars($companyAddress) ?></div>
                    <div>Phone: <?= htmlspecialchars($companyPhone) ?></div>
                    <div>Email: <?= htmlspecialchars($companyEmail) ?></div>
                </div>
                <div class="invoice-title">
                    <div class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                    <div class="invoice-date">Date: <?= date('F j, Y', strtotime($invoice['generated_at'])) ?></div>
                    <div class="invoice-date">
                        <span class="status-badge status-<?= $invoice['payment_status'] ?>">
                            <?= strtoupper($invoice['payment_status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bill To & Invoice Details -->
            <div class="details-section">
                <div class="bill-to">
                    <div class="section-title">Bill To</div>
                    <div class="info-line"><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></div>
                    <?php if ($invoice['delivery_address']): ?>
                        <div class="info-line"><?= nl2br(htmlspecialchars($invoice['delivery_address'])) ?></div>
                    <?php endif; ?>
                    <?php if ($invoice['customer_phone']): ?>
                        <div class="info-line">Phone: <?= htmlspecialchars($invoice['customer_phone']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="invoice-details">
                    <div class="section-title">Invoice Details</div>
                    <div class="info-line"><strong>Sales Order:</strong> SO-<?= str_pad($invoice['sales_order_id'], 5, '0', STR_PAD_LEFT) ?></div>
                    <div class="info-line"><strong>Payment Terms:</strong> <?= htmlspecialchars($invoice['payment_terms']) ?></div>
                    <div class="info-line"><strong>Due Date:</strong> <?= date('F j, Y', strtotime($invoice['due_date'])) ?></div>
                </div>
            </div>

            <!-- Line Items -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item Description</th>
                        <th>SKU</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lineItems as $idx => $item): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                            <td><?= htmlspecialchars($item['sku'] ?? '') ?></td>
                            <td class="text-center"><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                            <td class="text-right">₱<?= number_format($item['unit_price'], 2) ?></td>
                            <td class="text-right">₱<?= number_format($item['line_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Summary -->
            <div class="summary">
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>₱<?= number_format($invoice['subtotal'], 2) ?></span>
                </div>
                <?php if ($invoice['discount_amount'] > 0): ?>
                    <div class="summary-row">
                        <span>Discount:</span>
                        <span>-₱<?= number_format($invoice['discount_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($invoice['shipping_amount'] > 0): ?>
                    <div class="summary-row">
                        <span>Shipping:</span>
                        <span>₱<?= number_format($invoice['shipping_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="summary-row">
                    <span>Tax (<?= $invoice['tax_rate'] ?>%):</span>
                    <span>₱<?= number_format($invoice['tax_amount'], 2) ?></span>
                </div>
                <div class="summary-row total">
                    <span>TOTAL:</span>
                    <span>₱<?= number_format($invoice['total_amount'], 2) ?></span>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <p><strong>Thank you for your business!</strong></p>
                <p>This is a computer-generated invoice. No signature required.</p>
                <p>For questions, contact us at <?= htmlspecialchars($companyEmail) ?> or <?= htmlspecialchars($companyPhone) ?></p>
            </div>
        </div>

        <script>
            // Auto-print functionality (optional)
            // window.onload = function() { window.print(); };
        </script>
    </body>

    </html>
<?php
    return ob_get_clean();
}

/**
 * ============================================================================
 * HELPER FUNCTION: Send Invoice Email
 * ============================================================================
 */
function sendInvoiceEmail($invoiceId, $toEmail = null)
{
    global $db;

    // Get invoice data
    $stmt = $db->prepare("
        SELECT 
            i.*,
            so.email as customer_email
        FROM invoices i
        LEFT JOIN sales_orders so ON i.sales_order_id = so.sales_order_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }

    $recipientEmail = $toEmail ?? $invoice['customer_email'];

    if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Valid email address required'];
    }

    $subject = "Invoice {$invoice['invoice_number']} from " . ($_ENV['COMPANY_NAME'] ?? 'Janstro Prime');

    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Invoice {$invoice['invoice_number']}</h2>
        <p>Dear {$invoice['customer_name']},</p>
        <p>Thank you for your business. Please find attached your invoice.</p>
        <table style='border-collapse: collapse; margin: 20px 0;'>
            <tr><td style='padding: 8px;'><strong>Invoice Number:</strong></td><td>{$invoice['invoice_number']}</td></tr>
            <tr><td style='padding: 8px;'><strong>Invoice Date:</strong></td><td>" . date('F j, Y', strtotime($invoice['generated_at'])) . "</td></tr>
            <tr><td style='padding: 8px;'><strong>Due Date:</strong></td><td>" . date('F j, Y', strtotime($invoice['due_date'])) . "</td></tr>
            <tr><td style='padding: 8px;'><strong>Amount:</strong></td><td>₱" . number_format($invoice['total_amount'], 2) . "</td></tr>
        </table>
        <p>Payment Terms: {$invoice['payment_terms']}</p>
        <p>If you have any questions, please contact us.</p>
        <p>Best regards,<br>" . ($_ENV['COMPANY_NAME'] ?? 'Janstro Prime Corporation') . "</p>
    </body>
    </html>
    ";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@janstro.com')
    ];

    // Send email
    $emailSent = mail($recipientEmail, $subject, $message, implode("\r\n", $headers));

    if ($emailSent) {
        // Update invoice
        $stmt = $db->prepare("UPDATE invoices SET email_sent = 1, email_sent_at = NOW() WHERE invoice_id = ?");
        $stmt->execute([$invoiceId]);

        return ['success' => true, 'message' => 'Invoice email sent successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}
