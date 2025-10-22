<?php
require 'vendor/autoload.php';
use Mpdf\Mpdf;

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer PDF-factuur en stuur mail met bijlage
 *
 * @param int $order_id
 * @param string $order_date
 * @param mysqli $conn
 * @param string $lang
 * @param array|null $used_voucher
 * @return string|false PDF-bestandsnaam of false bij fout
 */
function create_invoice($order_id, $conn, $lang, $used_voucher = null) {
    // --- Order + klantgegevens ophalen ---
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
    $stmt->close();

    if (!$order) return false;

    // --- Adres ---
    $addressParts = [];
    if (!empty($order['street'])) $addressParts[] = htmlspecialchars($order['street']) . ' ' . htmlspecialchars($order['house_number']);
    if (!empty($order['postal_code'])) $addressParts[] = htmlspecialchars($order['postal_code']);
    if (!empty($order['city'])) $addressParts[] = htmlspecialchars($order['city']);
    if (!empty($order['country'])) $addressParts[] = htmlspecialchars($order['country']);
    $fullAddress = implode(', ', $addressParts);

    // --- Items ophalen ---
    $stmt_items = $conn->prepare("
        SELECT oi.quantity, oi.price, oi.type, 
            COALESCE(pt.name, p.name) AS product_name,
            v.code AS voucher_code
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_translations pt ON pt.product_id = p.id AND pt.lang = ?
        LEFT JOIN vouchers v ON oi.voucher_id = v.id
        WHERE oi.order_id = ?
    ");
    $stmt_items->bind_param("si", $lang, $order_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();

    // --- HTML PDF ---
    $html = '<h1>' . t('invoice_title') . '</h1>';
    $html .= '<p><strong>' . t('order_number') . ':</strong> ' . $order['id'] . '</p>';
    $html .= '<p><strong>' . t('order_date') . ':</strong> ' . date('d-m-Y', strtotime($order['created_at'])) . '</p>';
    $html .= '<p><strong>' . t('customer') . ':</strong> ' . htmlspecialchars($order['name']) . '<br>';
    $html .= '<strong>' . t('email') . ':</strong> ' . htmlspecialchars($order['email']) . '<br>';
    if (!empty($order['company_name'])) $html .= '<strong>' . t('company') . ':</strong> ' . htmlspecialchars($order['company_name']) . '<br>';
    if (!empty($order['vat_number'])) $html .= '<strong>' . t('vat_number') . ':</strong> ' . htmlspecialchars($order['vat_number']) . '<br>';
    $html .= '<strong>' . t('address') . ':</strong> ' . $fullAddress . '</p>';

    // --- Tabel ---
    $html .= '<table width="100%" border="1" cellpadding="5" cellspacing="0">';
    $html .= '<thead><tr>
                <th>' . t('quantity') . '</th>
                <th>' . t('product') . '</th>
                <th>' . t('price_per_item') . '</th>
                <th>' . t('total') . '</th>
              </tr></thead><tbody>';

    foreach ($items as $item) {
        $productName = ($item['type'] === 'voucher')
            ? t('gift_voucher') . (!empty($item['voucher_code']) ? ' - ' . htmlspecialchars($item['voucher_code']) : '')
            : htmlspecialchars($item['product_name']);
        $qty = intval($item['quantity']);
        $price = number_format($item['price'], 2, ',', '.');
        $total = number_format($qty * $item['price'], 2, ',', '.');

        $html .= "<tr>
                    <td style='text-align:center;'>$qty</td>
                    <td>$productName</td>
                    <td style='text-align:right;'>€$price</td>
                    <td style='text-align:right;'>€$total</td>
                  </tr>";
    }
    $html .= '</tbody></table>';

    // --- Voucher ---
    if (!empty($used_voucher) && isset($used_voucher['code'], $used_voucher['amount'])) {
        $html .= '<p><strong>' . t('used_voucher') . ':</strong> ' . htmlspecialchars($used_voucher['code']) . 
                 ' - €' . number_format($used_voucher['amount'], 2, ',', '.') . '</p>';
    }

    $html .= '<p><strong>' . t('total_to_pay') . ':</strong> €' . number_format($order['total_price'], 2, ',', '.') . '</p>';
    $html .= '<p><strong>' . t('status') . ':</strong> ' . ucfirst(htmlspecialchars($order['status'])) . '</p>';

    // --- PDF genereren ---
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = date('Y-m-d', strtotime($order['created_at'])) . '-' . $order_id . '.pdf';
    $filepath = $dir . '/' . $filename;

    try {
        $mpdf = new Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
    } catch (\Mpdf\MpdfException $e) {
        error_log('PDF genereren mislukt: ' . $e->getMessage());
        return false;
    }

    // --- Mail sturen met PDF als bijlage ---
    $name = $order['name'];
    $cartItems = $items;
    $total_order = $order['total_price'];
    $siteLanguage = $lang;

    sendOrderConfirmationMail($order['email'], $name, $order_id, $cartItems, $total_order, $siteLanguage, $filepath);

    return $filename;
}
?>