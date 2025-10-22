<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
include 'mailing.php';
require 'vendor/autoload.php';
use Mpdf\Mpdf;

// --- Taal ophalen (fallback: be-nl) ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer een interne voucherfactuur (PDF) en stuur deze via mailing.php
 *
 * @param string $voucher_code
 * @param float  $amount
 * @param string $create_date
 * @param string $reason
 * @param string $lang
 * @return string|false PDF-bestandsnaam of false bij fout
 */
function create_own_voucher_invoice($voucher_code, $amount, $create_date, $reason = 'Interne voucheruitgifte', $lang = 'be-nl') {

    $formattedAmount = number_format((float)$amount, 2, ',', '.');
    $date = date('Y-m-d', strtotime($create_date));

    // --- HTML voor PDF ---
    $html = "
    <h1>Interne Voucherfactuur</h1>
    <p><strong>Datum:</strong> {$date}</p>
    <p><strong>Reden:</strong> " . htmlspecialchars($reason) . "</p>
    <p><strong>Voucher code:</strong> " . htmlspecialchars($voucher_code) . "</p>

    <table width='100%' border='1' cellpadding='6' cellspacing='0' style='border-collapse: collapse; margin-top: 10px;'>
        <thead style='background-color: #f2f2f2;'>
            <tr>
                <th style='text-align:left;'>Omschrijving</th>
                <th style='text-align:right;'>Bedrag (€)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Uitgifte van interne waardebon ({$voucher_code})</td>
                <td style='text-align:right;'>{$formattedAmount}</td>
            </tr>
        </tbody>
    </table>

    <p><strong>Totaalwaarde voucher:</strong> €{$formattedAmount}</p>";

    // --- Map aanmaken indien nodig ---
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $date . "-" . preg_replace('/[^A-Za-z0-9]/', '', $voucher_code) . ".pdf";
    $filepath = $dir . '/' . $filename;

    // --- PDF genereren ---
    try {
        $mpdf = new Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
    } catch (\Mpdf\MpdfException $e) {
        error_log('PDF genereren mislukt: ' . $e->getMessage());
        return false;
    }

    // --- Mail sturen via mailing.php functie ---
    if (file_exists($filepath)) {
        sendOwnVoucherInvoiceMail($filename, $lang);
    }

    return $filename;
}

// --- Directe aanroep via GET ---
if (isset($_GET['voucher_code'], $_GET['amount'], $_GET['create_date'])) {
    $voucher_code = $_GET['voucher_code'];
    $amount = floatval($_GET['amount']);
    $create_date = $_GET['create_date'];
    $reason = $_GET['reason'] ?? 'Interne voucheruitgifte';

    $result = create_own_voucher_invoice($voucher_code, $amount, $create_date, $reason, $lang);
    if ($result) {
        echo json_encode([
            'success'  => true,
            'message'  => 'Voucherfactuur aangemaakt en verzonden via mailing.php',
            'file'     => $result
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success'  => false,
            'message'  => 'PDF genereren of mailing mislukt'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
?>