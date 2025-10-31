<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'mailing.php';
require 'vendor/autoload.php';
use Mpdf\Mpdf;

// --- Taal ophalen (fallback: be-nl) ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer een interne voucherfactuur (PDF) via HTML-template + Mpdf
 *
 * @param string $voucher_code
 * @param float  $amount
 * @param string $create_date
 * @param string $reason
 * @param array|null $order
 * @param string $lang
 * @return string|false PDF-bestandsnaam of false bij fout
 */
function create_own_voucher_invoice($voucher_code, $amount, $create_date, $reason = 'Interne voucheruitgifte', $order = null, $lang = 'be-nl') {

    $formattedAmount = number_format((float)$amount, 2, ',', '.');
    $factuurDatum = date('d-m-Y', strtotime($create_date));

    // --- Klantadres samenstellen ---
    $fullAddress = '';
    if (!empty($order)) {
        $parts = [];
        if (!empty($order['street'])) $parts[] = htmlspecialchars($order['street']);
        if (!empty($order['number'])) $parts[] = htmlspecialchars($order['number']);
        if (!empty($order['zip'])) $parts[] = htmlspecialchars($order['zip']);
        if (!empty($order['city'])) $parts[] = htmlspecialchars($order['city']);
        if (!empty($order['country'])) $parts[] = htmlspecialchars($order['country']);
        $fullAddress = implode(', ', $parts);
    }

    // --- Map aanmaken indien nodig ---
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = date('Y-m-d', strtotime($create_date)) . "-" . preg_replace('/[^A-Za-z0-9]/', '', $voucher_code) . ".pdf";
    $filepath = $dir . '/' . $filename;

    // --- PDF genereren ---
    try {
        $mpdf = new Mpdf(['tempDir' => __DIR__ . '/tmp']);

        // --- Footer instellen ---
    $footerText = "Conform de Belgische wetgeving gelden onze algemene verkoopsvoorwaarden zoals vermeld op";
    $mpdf->SetHTMLFooter("
        <div style='text-align:center; font-size:9px; color:#666; border-top:1px solid #ddd; padding-top:6px;'>
            <p>
                $footerText
                <a href='https://www.stylisso.be/algemene%20voorwaarden.html' style='color:#666; text-decoration:none;'>
                    www.stylisso.be/algemene voorwaarden.html
                </a>.
            </p>
        </div>
    ");

        // --- Output bufferen om PHP-template te renderen ---
        ob_start();
        include __DIR__ . '/invoices/own_voucher_invoice_template.html';
        $html = ob_get_clean();

        // --- PDF renderen ---
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

    } catch (\Mpdf\MpdfException $e) {
        error_log('PDF genereren mislukt: ' . $e->getMessage());
        return false;
    }

    // --- Mail sturen via mailing.php (optioneel) ---
    if (file_exists($filepath)) {
        if (function_exists('sendOwnVoucherInvoiceMail')) {
            sendOwnVoucherInvoiceMail($filename, $lang);
        }
    }

    return $filename;
}
?>