<?php

/**
 * ============================================================================
 * PURCHASE ORDER PDF GENERATION ENDPOINT
 * ============================================================================
 */

require_once __DIR__ . '/../../autoload.php';

use Janstro\InventorySystem\Config\Database;
use Janstro\InventorySystem\Middleware\AuthMiddleware;
use Janstro\InventorySystem\Services\PdfService;
use Janstro\InventorySystem\Utils\Response;

// Accept token from query parameter (for browser PDF downloads)
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
if ($token && strpos($token, 'Bearer ') === 0) {
    $token = substr($token, 7);
}

if ($token) {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
}

$user = AuthMiddleware::authenticate();
if (!$user) exit;

$db = Database::connect();
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// ✅ FIX: Get PO ID from URL segments
$segments = explode('/', trim(parse_url($requestUri, PHP_URL_PATH), '/'));
$poIdIndex = array_search('purchase-orders', $segments);
$poId = isset($segments[$poIdIndex + 1]) ? (int)$segments[$poIdIndex + 1] : null;

if (!$poId) {
    Response::badRequest('Purchase order ID required');
    exit;
}

/**
 * GET /purchase-orders/:id/pdf - Generate and download PO PDF
 */
if ($method === 'GET') {
    try {
        // Get PO data
        $stmt = $db->prepare("
            SELECT po.*, s.supplier_name, s.contact_person, s.phone, s.email, s.address
            FROM purchase_orders po
            LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
            WHERE po.po_id = ?
        ");
        $stmt->execute([$poId]);
        $po = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$po) {
            Response::notFound('Purchase order not found');
            exit;
        }

        // Get item data
        $stmt = $db->prepare("
            SELECT item_name, sku, unit
            FROM items
            WHERE item_id = ?
        ");
        $stmt->execute([$po['item_id']]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            Response::notFound('Item not found');
            exit;
        }

        // Generate PDF
        $pdfService = new PdfService();

        $supplier = [
            'supplier_name' => $po['supplier_name'],
            'contact_person' => $po['contact_person'],
            'phone' => $po['phone'],
            'email' => $po['email'],
            'address' => $po['address']
        ];

        $pdfPath = $pdfService->generatePurchaseOrderPDF($po, $supplier, $item);

        // Update PO with PDF path (add pdf_path column if needed)
        // $stmt = $db->prepare("UPDATE purchase_orders SET pdf_path = ? WHERE po_id = ?");
        // $stmt->execute([$pdfPath, $poId]);

        // Output PDF
        $absolutePath = $pdfService->getAbsolutePath($pdfPath);

        if (!file_exists($absolutePath)) {
            Response::notFound('PDF file not found');
            exit;
        }

        $poNumber = 'PO-' . str_pad($poId, 6, '0', STR_PAD_LEFT);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $poNumber . '.pdf"');
        header('Content-Length: ' . filesize($absolutePath));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        readfile($absolutePath);
        exit;
    } catch (Exception $e) {
        error_log("PO PDF generation error: " . $e->getMessage());
        Response::serverError('Failed to generate PDF: ' . $e->getMessage());
    }
    exit;
}

Response::notFound('Endpoint not found');
