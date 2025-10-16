<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db_connect.php';
require 'vendor/autoload.php'; // Voor Mpdf
use Mpdf\Mpdf;

// --- Taal ophalen (fallback: be-nl) ---
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer een interne factuur (PDF) voor een reeds bestaande voucher
 *
 * @param string $voucher_code De code van de voucher
 * @param float  $amount       Waarde van de voucher
 * @param string $create_date  Datum van creatie (bv. '2025-10-14')
 * @param string $reason       Reden van uitgifte (bv. "Promotie", "Influencer")
 * @param string $lang         Taalcode
 * @return array
 */
function create_own_voucher_invoice($voucher_code, $amount, $create_date, $reason = 'Interne voucheruitgifte', $lang = 'be-nl') {

    $formattedAmount = number_format((float)$amount, 2, ',', '.');
    $companyName = "Stylisso";
    $date = date('Y-m-d', strtotime($create_date));

    // HTML voor PDF
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

    <p><strong>Totaalwaarde voucher:</strong> €{$formattedAmount}</p>
    <p style='margin-top: 20px; font-size: 0.9em; color: #555;'>
        Deze factuur vertegenwoordigt een interne uitgifte van een Stylisso waardebon. 
        Dit document dient uitsluitend ter boekhoudkundige registratie en vertegenwoordigt geen btw-plichtige verkoop.
    </p>";

    // Map aanmaken indien nodig
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = $date . "-" . preg_replace('/[^A-Za-z0-9]/', '', $voucher_code) . ".pdf";
    $filepath = $dir . '/' . $filename;

    // PDF genereren
    try {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

        return [
            'success' => true,
            'message' => 'Interne voucherfactuur (PDF) aangemaakt',
            'file' => $filename
        ];
    } catch (\Mpdf\MpdfException $e) {
        return [
            'success' => false,
            'message' => 'PDF genereren mislukt: ' . $e->getMessage()
        ];
    }
}

// --- Directe aanroep via GET ---
if (isset($_GET['voucher_code'], $_GET['amount'], $_GET['create_date'])) {
    $voucher_code = $_GET['voucher_code'];
    $amount = floatval($_GET['amount']);
    $create_date = $_GET['create_date'];
    $reason = $_GET['reason'] ?? 'Interne voucheruitgifte';

    $result = create_own_voucher_invoice($voucher_code, $amount, $create_date, $reason, $lang);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}
?>