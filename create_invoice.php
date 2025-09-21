<?php
include 'translations.php'; // Zorg dat deze als eerste staat
require 'vendor/autoload.php';
use Mpdf\Mpdf;

/**
 * Genereer een PDF-factuur voor een bestelling
 *
 * @param int $order_id ID van de order
 * @param string $order_date Datum van de order
 * @param mysqli $conn Databaseverbinding
 * @return string|false Bestandsnaam bij succes, false bij fout
 */
function create_invoice($order_id, $order_date, $conn) {
    // Ophalen van orderinformatie
    $stmt = $conn->prepare("
        SELECT o.id, o.total_price, o.status, o.created_at, u.name, u.email, u.address
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) return false;

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
    $html = '<h1>' . t('invoice_title') . '</h1>';
    $html .= '<p><strong>' . t('order_number') . ':</strong> ' . $order['id'] . '</p>';
    $html .= '<p><strong>' . t('order_date') . ':</strong> ' . date('d-m-Y', strtotime($order['created_at'])) . '</p>';
    $html .= '<p><strong>' . t('customer') . ':</strong> ' . htmlspecialchars($order['name']) . '<br>';
    $html .= '<strong>' . t('email') . ':</strong> ' . htmlspecialchars($order['email']) . '<br>';
    $html .= '<strong>' . t('address') . ':</strong> ' . nl2br(htmlspecialchars($order['address'])) . '</p>';

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
                    <td style='text-align:right;'>€$price</td>
                    <td style='text-align:right;'>€$total</td>
                  </tr>";
    }

    $html .= '</tbody></table>';
    $html .= '<p><strong>' . t('total_to_pay') . ':</strong> €' . number_format($order['total_price'], 2) . '</p>';
    $html .= '<p><strong>' . t('status') . ':</strong> ' . ucfirst($order['status']) . '</p>';

    // Map voor facturen
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Bestandsnaam
    $filename = $order_date . '-' . $order_id . '.pdf';
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