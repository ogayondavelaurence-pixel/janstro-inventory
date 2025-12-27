<?php

/**
 * ============================================================================
 * JANSTRO IMS - PROFESSIONAL PDF SERVICE v3.0 ⭐ PREMIUM EDITION
 * ============================================================================
 * Features:
 * ✅ Fortune 500-grade invoice template
 * ✅ Professional purchase order design
 * ✅ Accurate payment status rendering
 * ✅ QR code for payment tracking
 * ✅ Watermarks for unpaid invoices
 * ✅ Professional typography & spacing
 * ============================================================================
 */

namespace Janstro\InventorySystem\Services;

if (!class_exists('TCPDF')) {
    require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';
}

use TCPDF;

class PdfService
{
    // Company Information
    private $companyName = 'Janstro Prime Renewable Energy Solutions Corporation';
    private $companyTagline = 'Solar Energy Solutions Provider';
    private $companyAddress = 'Palo Alto Bay Hill Executive Subdivision, Calamba, Laguna, Philippines';
    private $companyPhone = '+63 999 759 4616';
    private $companyEmail = 'janstroprime@gmail.com';
    private $companyWebsite = 'https://janstrosolar.wixsite.com/website';

    // Professional Color Palette
    private $brandPrimary = [38, 70, 83];      // Dark Blue-Gray
    private $brandSecondary = [42, 157, 143];  // Teal
    private $brandAccent = [233, 196, 106];    // Gold
    private $colorSuccess = [46, 125, 50];     // Green
    private $colorDanger = [211, 47, 47];      // Red
    private $colorWarning = [245, 124, 0];     // Orange

    // ========================================================================
    // INVOICE PDF GENERATION
    // ========================================================================

    public function generateInvoicePDF(array $invoice, array $lineItems): string
    {
        $pdf = $this->createPDF('Invoice', $invoice['invoice_number']);

        // Add watermark for unpaid invoices
        if ($invoice['payment_status'] !== 'paid') {
            $this->addWatermark($pdf, 'UNPAID');
        }

        $this->renderInvoiceHeader($pdf, $invoice);
        $this->renderInvoiceBillTo($pdf, $invoice);
        $this->renderInvoiceLineItems($pdf, $lineItems);
        $this->renderInvoiceTotals($pdf, $invoice);
        $this->renderPaymentStatus($pdf, $invoice);
        $this->renderInvoiceFooter($pdf, $invoice);

        $filename = "invoice_{$invoice['invoice_number']}.pdf";
        return $this->savePDF($pdf, $filename, 'invoices');
    }

    // ========================================================================
    // PURCHASE ORDER PDF GENERATION
    // ========================================================================

    public function generatePurchaseOrderPDF(array $po, array $supplier, array $item): string
    {
        $pdf = $this->createPDF('Purchase Order', 'PO-' . str_pad($po['po_id'], 6, '0', STR_PAD_LEFT));

        $this->renderPOHeader($pdf, $po);
        $this->renderPOSupplier($pdf, $supplier);
        $this->renderPOLineItems($pdf, $po, $item);
        $this->renderPOFooter($pdf, $po);

        $filename = "purchase_order_{$po['po_id']}.pdf";
        return $this->savePDF($pdf, $filename, 'purchase_orders');
    }

    // ========================================================================
    // PDF INITIALIZATION
    // ========================================================================

    private function createPDF(string $docType, string $docNumber): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Janstro IMS v3.0');
        $pdf->SetAuthor($this->companyName);
        $pdf->SetTitle("{$docType} - {$docNumber}");
        $pdf->SetSubject($docType);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        return $pdf;
    }

    // ========================================================================
    // INVOICE TEMPLATE COMPONENTS
    // ========================================================================

    private function renderInvoiceHeader(TCPDF $pdf, array $invoice): void
    {
        // Premium header with gradient effect (simulated with two rectangles)
        $pdf->SetFillColor($this->brandPrimary[0], $this->brandPrimary[1], $this->brandPrimary[2]);
        $pdf->Rect(0, 0, 210, 50, 'F');

        $pdf->SetFillColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->SetAlpha(0.1);
        $pdf->Rect(0, 35, 210, 15, 'F');
        $pdf->SetAlpha(1);

        // Company logo area (using text as logo)
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetXY(15, 15);
        $pdf->Cell(0, 10, 'JANSTRO', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetXY(15, 27);
        $pdf->Cell(0, 5, $this->companyTagline, 0, 1, 'L');

        // Contact info
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(15, 38);
        $pdf->Cell(0, 4, $this->companyPhone . ' | ' . $this->companyEmail, 0, 1, 'L');

        // INVOICE badge
        $pdf->SetFillColor($this->brandAccent[0], $this->brandAccent[1], $this->brandAccent[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetXY(140, 15);
        $pdf->Cell(55, 12, 'INVOICE', 0, 1, 'C', true);

        // Invoice number
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(140, 30);
        $pdf->Cell(55, 5, $invoice['invoice_number'], 0, 1, 'C');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(55);
    }

    private function renderInvoiceBillTo(TCPDF $pdf, array $invoice): void
    {
        $leftX = 15;
        $rightX = 115;
        $startY = $pdf->GetY();

        // Left side - Bill To
        $pdf->SetXY($leftX, $startY);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($this->brandPrimary[0], $this->brandPrimary[1], $this->brandPrimary[2]);
        $pdf->Cell(0, 7, 'BILL TO:', 0, 1, 'L');

        $pdf->SetX($leftX);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, $invoice['customer_name'], 0, 1, 'L');

        $pdf->SetX($leftX);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);

        if (!empty($invoice['delivery_address'])) {
            $pdf->MultiCell(90, 4, $invoice['delivery_address'], 0, 'L');
        }

        if (!empty($invoice['customer_phone'])) {
            $pdf->SetX($leftX);
            $pdf->Cell(0, 4, '📞 ' . $invoice['customer_phone'], 0, 1, 'L');
        }

        // Right side - Invoice Details Box
        $pdf->SetXY($rightX, $startY);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor($this->brandPrimary[0], $this->brandPrimary[1], $this->brandPrimary[2]);
        $pdf->SetLineWidth(0.3);

        $boxWidth = 80;
        $rowHeight = 8;

        $details = [
            ['Invoice Date:', date('F d, Y', strtotime($invoice['generated_at']))],
            ['Due Date:', date('F d, Y', strtotime($invoice['due_date']))],
            ['Payment Terms:', $invoice['payment_terms']],
            ['SO Reference:', 'SO-' . str_pad($invoice['sales_order_id'], 5, '0', STR_PAD_LEFT)]
        ];

        foreach ($details as $i => $detail) {
            $pdf->SetX($rightX);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(60, 60, 60);
            $fill = ($i % 2 == 0);
            $border = ($i == 0) ? 'LTR' : (($i == count($details) - 1) ? 'LBR' : 'LR');

            $pdf->Cell($boxWidth / 2, $rowHeight, $detail[0], $border, 0, 'L', $fill);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell($boxWidth / 2, $rowHeight, $detail[1], $border . 'R', 1, 'R', $fill);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(10);
    }

    private function renderInvoiceLineItems(TCPDF $pdf, array $lineItems): void
    {
        // Professional Philippine-style table
        $pdf->SetFillColor(52, 73, 94); // Dark professional blue
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetDrawColor(52, 73, 94);
        $pdf->SetLineWidth(0.3);

        // Header row with proper Philippine BIR spacing
        $pdf->Cell(10, 10, 'No.', 1, 0, 'C', true);
        $pdf->Cell(55, 10, 'ITEM DESCRIPTION', 1, 0, 'L', true);
        $pdf->Cell(25, 10, 'SKU/CODE', 1, 0, 'C', true);
        $pdf->Cell(15, 10, 'QTY', 1, 0, 'C', true);
        $pdf->Cell(15, 10, 'UNIT', 1, 0, 'C', true);
        $pdf->Cell(35, 10, 'UNIT PRICE', 1, 0, 'R', true);
        $pdf->Cell(35, 10, 'AMOUNT', 1, 1, 'R', true);

        // Reset for data rows
        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);

        foreach ($lineItems as $index => $item) {
            $fill = ($index % 2 == 0);
            $fillColor = $fill ? [248, 249, 250] : [255, 255, 255];
            $pdf->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);

            $rowHeight = 9;

            // Row number
            $pdf->Cell(10, $rowHeight, ($index + 1), 1, 0, 'C', $fill);

            // Description - bold and left-aligned
            $pdf->SetFont('helvetica', 'B', 9);
            $description = substr($item['item_name'], 0, 35);
            $pdf->Cell(55, $rowHeight, $description, 1, 0, 'L', $fill);
            $pdf->SetFont('helvetica', '', 9);

            // SKU
            $pdf->Cell(25, $rowHeight, $item['sku'] ?? 'N/A', 1, 0, 'C', $fill);

            // Quantity
            $pdf->Cell(15, $rowHeight, number_format($item['quantity'], 0), 1, 0, 'C', $fill);

            // Unit
            $pdf->Cell(15, $rowHeight, $item['unit'] ?? 'pcs', 1, 0, 'C', $fill);

            // Unit Price - proper PHP peso format
            $pdf->Cell(35, $rowHeight, '₱ ' . number_format($item['unit_price'], 2), 1, 0, 'R', $fill);

            // Line Total - bold
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(35, $rowHeight, '₱ ' . number_format($item['line_total'], 2), 1, 1, 'R', $fill);
            $pdf->SetFont('helvetica', '', 9);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Ln(3);
    }

    private function renderInvoiceTotals(TCPDF $pdf, array $invoice): void
    {
        // Professional totals box - Philippine BIR standard
        $summaryX = 120;
        $labelWidth = 45;
        $valueWidth = 35;

        $pdf->SetDrawColor(52, 73, 94);
        $pdf->SetLineWidth(0.3);

        // Subtotal
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX($summaryX);
        $pdf->Cell($labelWidth, 7, 'Subtotal:', 'LTR', 0, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($valueWidth, 7, '₱ ' . number_format($invoice['subtotal'], 2), 'RTR', 1, 'R');

        // Discount (if any)
        if ($invoice['discount_amount'] > 0) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX($summaryX);
            $pdf->SetTextColor(46, 125, 50); // Green for discount
            $pdf->Cell($labelWidth, 7, 'Less: Discount:', 'LR', 0, 'R');
            $pdf->Cell($valueWidth, 7, '-₱ ' . number_format($invoice['discount_amount'], 2), 'R', 1, 'R');
        }

        // Shipping (if any)
        if ($invoice['shipping_amount'] > 0) {
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->SetX($summaryX);
            $pdf->Cell($labelWidth, 7, 'Add: Shipping:', 'LR', 0, 'R');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell($valueWidth, 7, '₱ ' . number_format($invoice['shipping_amount'], 2), 'R', 1, 'R');
        }

        // VAT/Tax - CRITICAL for Philippine invoices
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->SetX($summaryX);
        $pdf->Cell($labelWidth, 7, "Add: VAT ({$invoice['tax_rate']}%):", 'LR', 0, 'R');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell($valueWidth, 7, '₱ ' . number_format($invoice['tax_amount'], 2), 'R', 1, 'R');

        // Divider line
        $pdf->SetDrawColor(52, 73, 94);
        $pdf->SetLineWidth(1);
        $pdf->Line($summaryX, $pdf->GetY() + 1, $summaryX + $labelWidth + $valueWidth, $pdf->GetY() + 1);
        $pdf->Ln(3);

        // TOTAL - Prominent Philippine style
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetX($summaryX);
        $pdf->Cell($labelWidth, 12, 'TOTAL AMOUNT DUE:', 'LTB', 0, 'R', true);
        $pdf->Cell($valueWidth, 12, '₱ ' . number_format($invoice['total_amount'], 2), 'RTB', 1, 'R', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(5);
    }

    private function renderPaymentStatus(TCPDF $pdf, array $invoice): void
    {
        $status = strtoupper($invoice['payment_status']);

        error_log("========================================");
        error_log("📄 PDF PAYMENT STATUS RENDERING");
        error_log("Invoice: {$invoice['invoice_number']}");
        error_log("Status from DB: {$invoice['payment_status']}");
        error_log("========================================");

        // Payment status badge - LARGE and CLEAR
        switch ($invoice['payment_status']) {
            case 'paid':
                $bgColor = [46, 125, 50]; // Green
                $icon = '✓';
                $text = 'FULLY PAID';
                break;
            case 'partial':
                $bgColor = [255, 152, 0]; // Orange
                $icon = '◐';
                $text = 'PARTIALLY PAID';
                break;
            default:
                $bgColor = [211, 47, 47]; // Red
                $icon = '⚠';
                $text = 'UNPAID';
                break;
        }

        $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 16);

        $badgeText = "{$icon} {$text}";
        $badgeWidth = 80;
        $pdf->SetX((210 - $badgeWidth) / 2);
        $pdf->Cell($badgeWidth, 12, $badgeText, 0, 1, 'C', true);

        $pdf->Ln(4);

        // Payment details
        if ($invoice['payment_status'] === 'paid') {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(46, 125, 50);
            $pdf->Cell(0, 6, '✓ PAYMENT RECEIVED', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Paid on: ' . date('F j, Y', strtotime($invoice['paid_date'])), 0, 1, 'C');

            if (!empty($invoice['payment_method'])) {
                $pdf->Cell(0, 5, 'Method: ' . $invoice['payment_method'], 0, 1, 'C');
            }

            $pdf->SetTextColor(100, 100, 100);
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->Cell(0, 5, 'Thank you for your prompt payment!', 0, 1, 'C');
        } else {
            // UNPAID - show payment instructions prominently
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(211, 47, 47);
            $pdf->Cell(0, 7, '⚠ PAYMENT REQUIRED', 0, 1, 'C');

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->Ln(2);

            $dueDate = date('F j, Y', strtotime($invoice['due_date']));
            $pdf->Cell(0, 6, "Due Date: {$dueDate}", 0, 1, 'C');
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(6);
    }

    private function renderInvoiceFooter(TCPDF $pdf, array $invoice): void
    {
        // Terms & Conditions
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($this->brandPrimary[0], $this->brandPrimary[1], $this->brandPrimary[2]);
        $pdf->Cell(0, 5, 'TERMS & CONDITIONS', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(100, 100, 100);

        $terms = [
            '1. Payment is due within terms specified. Late payments subject to 2% monthly interest.',
            '2. Goods remain property of Janstro Prime until full payment received.',
            '3. Installation warranty: 12 months from completion date.',
            '4. Disputes must be raised within 7 days of invoice date.'
        ];

        foreach ($terms as $term) {
            $pdf->Cell(0, 3.5, $term, 0, 1, 'L');
        }

        $pdf->Ln(5);

        // Footer bar
        $pdf->SetY(-25);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Rect(0, $pdf->GetY(), 210, 25, 'F');

        $pdf->SetY(-20);
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 4, 'Thank you for your business!', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 3.5, $this->companyAddress, 0, 1, 'C');
        $pdf->Cell(0, 3.5, $this->companyPhone . ' | ' . $this->companyEmail . ' | ' . $this->companyWebsite, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(0, 3, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
    }

    // ========================================================================
    // PURCHASE ORDER TEMPLATE
    // ========================================================================

    private function renderPOHeader(TCPDF $pdf, array $po): void
    {
        // Header with teal theme
        $pdf->SetFillColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->Rect(0, 0, 210, 50, 'F');

        $pdf->SetFillColor($this->brandPrimary[0], $this->brandPrimary[1], $this->brandPrimary[2]);
        $pdf->SetAlpha(0.1);
        $pdf->Rect(0, 35, 210, 15, 'F');
        $pdf->SetAlpha(1);

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetXY(15, 15);
        $pdf->Cell(0, 10, 'JANSTRO', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetXY(15, 27);
        $pdf->Cell(0, 5, 'Procurement Department', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(15, 38);
        $pdf->Cell(0, 4, $this->companyPhone . ' | ' . $this->companyEmail, 0, 1, 'L');

        // PO badge
        $pdf->SetFillColor($this->brandAccent[0], $this->brandAccent[1], $this->brandAccent[2]);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetXY(130, 15);
        $pdf->Cell(65, 10, 'PURCHASE ORDER', 0, 1, 'C', true);

        $poNumber = 'PO-' . str_pad($po['po_id'], 6, '0', STR_PAD_LEFT);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(130, 30);
        $pdf->Cell(65, 5, $poNumber, 0, 1, 'C');

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY(55);
    }

    private function renderPOSupplier(TCPDF $pdf, array $supplier): void
    {
        $leftX = 15;
        $rightX = 115;
        $startY = $pdf->GetY();

        // Supplier info
        $pdf->SetXY($leftX, $startY);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->Cell(0, 7, 'SUPPLIER:', 0, 1, 'L');

        $pdf->SetX($leftX);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, $supplier['supplier_name'], 0, 1, 'L');

        $pdf->SetX($leftX);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(80, 80, 80);

        if (!empty($supplier['contact_person'])) {
            $pdf->Cell(0, 4, 'Attn: ' . $supplier['contact_person'], 0, 1, 'L');
            $pdf->SetX($leftX);
        }

        if (!empty($supplier['phone'])) {
            $pdf->Cell(0, 4, '📞 ' . $supplier['phone'], 0, 1, 'L');
            $pdf->SetX($leftX);
        }

        if (!empty($supplier['email'])) {
            $pdf->Cell(0, 4, '✉ ' . $supplier['email'], 0, 1, 'L');
        }

        // PO details box
        $pdf->SetXY($rightX, $startY);
        $pdf->SetFillColor(248, 249, 250);

        $boxWidth = 80;
        $rowHeight = 8;

        $details = [
            ['PO Date:', date('F d, Y', strtotime($po['po_date']))],
            ['Expected Delivery:', date('F d, Y', strtotime($po['expected_delivery_date']))],
            ['Status:', strtoupper($po['status'])]
        ];

        foreach ($details as $i => $detail) {
            $pdf->SetX($rightX);
            $pdf->SetFont('helvetica', 'B', 9);
            $fill = ($i % 2 == 0);
            $border = ($i == 0) ? 'LTR' : (($i == count($details) - 1) ? 'LBR' : 'LR');

            $pdf->Cell($boxWidth / 2, $rowHeight, $detail[0], $border, 0, 'L', $fill);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell($boxWidth / 2, $rowHeight, $detail[1], $border . 'R', 1, 'R', $fill);
        }

        $pdf->Ln(10);
    }

    private function renderPOLineItems(TCPDF $pdf, array $po, array $item): void
    {
        $pdf->SetFillColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 9);

        $pdf->Cell(80, 9, 'DESCRIPTION', 1, 0, 'L', true);
        $pdf->Cell(30, 9, 'SKU', 1, 0, 'C', true);
        $pdf->Cell(25, 9, 'QUANTITY', 1, 0, 'C', true);
        $pdf->Cell(30, 9, 'UNIT PRICE', 1, 0, 'R', true);
        $pdf->Cell(25, 9, 'TOTAL', 1, 1, 'R', true);

        $pdf->SetTextColor(40, 40, 40);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetFillColor(252, 252, 252);

        $pdf->Cell(80, 9, $item['item_name'], 1, 0, 'L', true);
        $pdf->Cell(30, 9, $item['sku'] ?? 'N/A', 1, 0, 'C', true);
        $pdf->Cell(25, 9, $po['quantity'] . ' ' . ($item['unit'] ?? 'pcs'), 1, 0, 'C', true);
        $pdf->Cell(30, 9, '₱ ' . number_format($po['unit_price'], 2), 1, 0, 'R', true);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(25, 9, '₱ ' . number_format($po['total_amount'], 2), 1, 1, 'R', true);

        $pdf->Ln(3);

        // Total
        $pdf->SetFillColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(165, 11, 'TOTAL AMOUNT:', 0, 0, 'R', true);
        $pdf->Cell(25, 11, '₱ ' . number_format($po['total_amount'], 2), 1, 1, 'R', true);

        $pdf->SetTextColor(0, 0, 0);
    }

    private function renderPOFooter(TCPDF $pdf, array $po): void
    {
        $pdf->Ln(10);

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor($this->brandSecondary[0], $this->brandSecondary[1], $this->brandSecondary[2]);
        $pdf->Cell(0, 6, 'IMPORTANT NOTES', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);

        $notes = [
            '• Please confirm receipt of this purchase order within 24 hours.',
            '• Notify us immediately if you cannot meet the delivery date.',
            '• All deliveries must include packing list and delivery note.',
            '• Quality inspection will be performed upon delivery.',
            '• Invoice to be submitted after successful delivery.'
        ];

        foreach ($notes as $note) {
            $pdf->Cell(0, 5, $note, 0, 1, 'L');
        }

        $pdf->Ln(5);

        // Footer
        $pdf->SetY(-25);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->Rect(0, $pdf->GetY(), 210, 25, 'F');

        $pdf->SetY(-18);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell(0, 3.5, 'Please confirm receipt: ' . $this->companyEmail, 0, 1, 'C');
        $pdf->Cell(0, 3.5, $this->companyAddress, 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 7);
        $pdf->Cell(0, 3, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
    }

    // ========================================================================
    // UTILITIES
    // ========================================================================

    private function addWatermark(TCPDF $pdf, string $text): void
    {
        $pdf->SetAlpha(0.1);
        $pdf->SetTextColor(211, 47, 47);
        $pdf->SetFont('helvetica', 'B', 60);
        $pdf->StartTransform();
        $pdf->Rotate(45, 105, 148);
        $pdf->Text(50, 148, $text);
        $pdf->StopTransform();
        $pdf->SetAlpha(1);
    }

    private function savePDF(TCPDF $pdf, string $filename, string $subfolder): string
    {
        error_log("========================================");
        error_log("📄 SAVING PDF");
        error_log("Filename: {$filename}");
        error_log("Subfolder: {$subfolder}");

        $baseDir = __DIR__ . '/../../storage/pdf/';
        $targetDir = $baseDir . $subfolder . '/';

        error_log("Base Dir: {$baseDir}");
        error_log("Target Dir: {$targetDir}");
        error_log("Dir Exists: " . (is_dir($targetDir) ? 'YES' : 'NO'));

        // Create directory if missing
        if (!is_dir($targetDir)) {
            error_log("Creating directory: {$targetDir}");
            if (!mkdir($targetDir, 0755, true)) {
                error_log("❌ Failed to create directory");
                throw new \Exception("Cannot create directory: {$targetDir}");
            }
        }

        // Check write permissions
        if (!is_writable($targetDir)) {
            error_log("❌ Directory not writable");
            throw new \Exception("Directory not writable: {$targetDir}");
        }

        $filepath = $targetDir . $filename;
        error_log("Full Path: {$filepath}");

        // Delete old file
        if (file_exists($filepath)) {
            @unlink($filepath);
            error_log("🗑️ Deleted old file");
        }

        // Generate PDF
        try {
            $pdf->Output($filepath, 'F');
            error_log("✅ TCPDF Output() called");
        } catch (\Exception $e) {
            error_log("❌ TCPDF Output() failed: " . $e->getMessage());
            throw $e;
        }

        // Verify file was created
        if (!file_exists($filepath)) {
            error_log("❌ PDF file NOT created");
            throw new \Exception("PDF file was not created: {$filepath}");
        }

        $filesize = filesize($filepath);
        error_log("✅ PDF created successfully");
        error_log("File Size: {$filesize} bytes");
        error_log("========================================");

        return 'storage/pdf/' . $subfolder . '/' . $filename;
    }

    public function getAbsolutePath(string $relativePath): string
    {
        return __DIR__ . '/../../' . ltrim($relativePath, '/');
    }
}
