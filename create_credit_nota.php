<?php
include 'mailing.php';
require 'vendor/autoload.php';
use Mpdf\Mpdf;

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer PDF-creditnota en stuur e-mail met bijlage
 *
 * @param int $order_id
 * @param array $approved_item_ids Array met order_item_id's die goedgekeurd zijn
 * @param mysqli $conn
 * @param string $lang
 * @return string|false Bestandsnaam bij succes, false bij fout
 */
function create_credit_nota($order_id, $approved_item_ids, $conn, $lang) {
    if (empty($approved_item_ids)) return false;

    // --- Order + klantgegevens ophalen ---
    $stmt = $conn->prepare("
        SELECT o.id, o.total_price, o.status, o.created_at, o.pdf_path,
            u.name, u.email, u.company_name, u.vat_number,
            COALESCE(b.street, s.street) AS street,
            COALESCE(b.house_number, s.house_number) AS house_number,
            COALESCE(b.postal_code, s.postal_code) AS postal_code,
            COALESCE(b.city, s.city) AS city,
            COALESCE(b.country, s.country) AS country
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN addresses s ON s.user_id = u.id AND s.type = 'shipping'
        LEFT JOIN addresses b ON b.user_id = u.id AND b.type = 'billing'
        WHERE o.id = ?
        ORDER BY b.id DESC, s.id DESC
        LIMIT 1
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) return false;

    // --- Factuurnummer uit pdf_path halen (zonder .pdf) ---
    if (!empty($order['pdf_path'])) {
        // voorbeeld: /invoices/2025-10-22-20.pdf → 2025-10-22-20
        $order['invoice_number'] = pathinfo($order['pdf_path'], PATHINFO_FILENAME);
    } else {
        $order['invoice_number'] = 'ONBEKEND';
    }

    // Factuurdatum opmaken (uit o.created_at)
    $order['invoice_date'] = date('d-m-Y', strtotime($order['created_at']));

    // --- Adres opbouwen ---
    $addressParts = [];
    if (!empty($order['street'])) $addressParts[] = htmlspecialchars($order['street']) . ' ' . htmlspecialchars($order['house_number']);
    if (!empty($order['postal_code'])) $addressParts[] = htmlspecialchars($order['postal_code']);
    if (!empty($order['city'])) $addressParts[] = htmlspecialchars($order['city']);
    if (!empty($order['country'])) $addressParts[] = htmlspecialchars($order['country']);
    $fullAddress = implode(', ', $addressParts);

    // --- Alleen goedgekeurde items ophalen ---
    $in = implode(',', array_map('intval', $approved_item_ids));
    $stmt_items = $conn->prepare("
        SELECT oi.id AS order_item_id, oi.quantity, oi.price, oi.type,
            COALESCE(pt.name, p.name) AS product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = ?
        WHERE oi.order_id = ? AND oi.id IN ($in)
    ");
    $stmt_items->bind_param("si", $lang, $order_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    if (empty($items)) return false;

    // --- Totaalbedrag berekenen ---
    $totalCredit = 0;
    foreach ($items as $item) {
        $totalCredit += $item['quantity'] * $item['price'];
    }

    // --- BTW berekenen op basis van totaal incl. BTW (21%) ---
    $subtotal = $totalCredit / 1.21;         // exclusief BTW
    $order['VAT_price'] = $totalCredit - $subtotal; // BTW-bedrag

    // --- Creditnota directory ---
    $dir = __DIR__ . '/credit_notes';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Bestandsnaam: YYYY-MM-DD-orderid-CN.pdf
    $filename = date('Y-m-d', strtotime($order['created_at'])) . '-' . $order_id . '-CN.pdf';
    $filepath = $dir . '/' . $filename;

    try {
        $mpdf = new Mpdf([
            'tempDir' => __DIR__ . '/tmp',
            'margin_bottom' => 25
        ]);

        // --- Footer (zelfde stijl als factuur) ---
        $footerText = t('invoice_template_legal_notice', $lang);
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

        // --- Template renderen ---
        ob_start();
        include __DIR__ . '/credit_notes/credit_nota_template.html';
        $html = ob_get_clean();

        // --- PDF genereren ---
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

    } catch (\Mpdf\MpdfException $e) {
        error_log('Creditnota genereren mislukt: ' . $e->getMessage());
        return false;
    }

    // --- Pad voor database ---
    $pdf_path_db = '/credit_notes/' . $filename;

    // --- PDF-pad opslaan in return_ledger voor elk goedgekeurd item ---
    $update_stmt = $conn->prepare("
        UPDATE return_ledger
        SET pdf_path = ?
        WHERE return_id IN (
            SELECT r.id
            FROM returns r
            WHERE r.order_item_id = ?
        )
    ");
    if ($update_stmt) {
        foreach ($approved_item_ids as $item_id) {
            $update_stmt->bind_param("si", $pdf_path_db, $item_id);
            $update_stmt->execute();
        }
        $update_stmt->close();
    } else {
        error_log("Kon return_ledger niet updaten: " . $conn->error);
    }

    // --- Mail sturen met PDF als bijlage ---
    sendCreditNotaMail(
        $order['email'],
        $order['name'],
        $order_id,
        $items,
        $totalCredit,
        $filepath,
        $lang
    );

    return $filename;
}
?>