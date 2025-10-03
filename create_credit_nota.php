<?php
include 'translations.php'; // Zorg dat deze als eerste staat
require 'vendor/autoload.php';
use Mpdf\Mpdf;

/**
 * Genereer een PDF-creditnota voor een bestelling
 *
 * @param int $order_id ID van de order
 * @param string $order_date Datum van de creditnota
 * @param mysqli $conn Databaseverbinding
 * @return string|false Bestandsnaam bij succes, false bij fout
 */
function create_credit_nota($order_id, $order_date, $conn) {
    // Ophalen van orderinformatie + gebruikersgegevens
    $stmt = $conn->prepare("
        SELECT o.id, o.total_price, o.status, o.created_at,
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
    if (!$order) return false;

    // Opbouwen van het adres
    $addressParts = [];
    if (!empty($order['street'])) $addressParts[] = htmlspecialchars($order['street']) . ' ' . htmlspecialchars($order['house_number']);
    if (!empty($order['postal_code'])) $addressParts[] = htmlspecialchars($order['postal_code']);
    if (!empty($order['city'])) $addressParts[] = htmlspecialchars($order['city']);
    if (!empty($order['country'])) $addressParts[] = htmlspecialchars($order['country']);
    $fullAddress = implode(', ', $addressParts);

    // Ophalen van order items
    $stmt_items = $conn->prepare("
        SELECT oi.quantity, oi.price, oi.type, p.name AS product_name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    // Begin HTML voor PDF
    $html = '<h1>' . t('credit_note_title') . '</h1>';
    $html .= '<p><strong>' . t('credit_note_number') . ':</strong> CN-' . $order['id'] . '</p>';
    $html .= '<p><strong>' . t('credit_note_date') . ':</strong> ' . date('d-m-Y', strtotime($order_date)) . '</p>';
    $html .= '<p><strong>' . t('customer') . ':</strong> ' . htmlspecialchars($order['name']) . '<br>';
    $html .= '<strong>' . t('email') . ':</strong> ' . htmlspecialchars($order['email']) . '<br>';
    if (!empty($order['company_name'])) $html .= '<strong>' . t('company') . ':</strong> ' . htmlspecialchars($order['company_name']) . '<br>';
    if (!empty($order['vat_number'])) $html .= '<strong>' . t('vat_number') . ':</strong> ' . htmlspecialchars($order['vat_number']) . '<br>';
    $html .= '<strong>' . t('address') . ':</strong> ' . $fullAddress . '</p>';

    $html .= '<table width="100%" border="1" cellpadding="5" cellspacing="0">';
    $html .= '<thead><tr>
                <th>' . t('quantity') . '</th>
                <th>' . t('product') . '</th>
                <th>' . t('price_per_item') . '</th>
                <th>' . t('total') . '</th>
              </tr></thead>';
    $html .= '<tbody>';

    foreach ($items as $item) {
        $productName = $item['type'] === 'voucher' ? t('gift_voucher') : $item['product_name'];
        $qty = intval($item['quantity']);
        $price = number_format($item['price'], 2);
        $total = number_format($qty * $item['price'], 2);
        $html .= "<tr>
                    <td style='text-align:center;'>$qty</td>
                    <td>$productName</td>
                    <td style='text-align:right;'>-€$price</td>
                    <td style='text-align:right;'>-€$total</td>
                  </tr>";
    }

    $html .= '</tbody></table>';
    $html .= '<p><strong>' . t('total_to_refund') . ':</strong> -€' . number_format($order['total_price'], 2) . '</p>';

    // Map voor creditnota's
    $dir = __DIR__ . '/credit_notes';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Bestandsnaam
    $filename = 'CN-' . $order_date . '-' . $order_id . '.pdf';
    $filepath = $dir . '/' . $filename;

    try {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        return $filename;
    } catch (\Mpdf\MpdfException $e) {
        error_log('PDF genereren mislukt: ' . $e->getMessage());
        return false;
    }
}
?>