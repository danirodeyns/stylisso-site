<?php
require 'vendor/autoload.php';
use Mpdf\Mpdf;

$lang = isset($_GET['lang']) ? $_GET['lang'] : 'be-nl';

/**
 * Genereer factuur en stuur via e-mail
 */
function create_invoice($order_id, $conn, $lang = 'be-nl', $used_voucher = null, $voucher_discount = 0, $order_subtotal = 0, $used_amount = 0, $total_order = 0)
{
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

    // --- Volledig adres ---
    $addressParts = [];
    if (!empty($order['street'])) $addressParts[] = htmlspecialchars($order['street']) . ' ' . htmlspecialchars($order['house_number']);
    if (!empty($order['postal_code'])) $addressParts[] = htmlspecialchars($order['postal_code']);
    if (!empty($order['city'])) $addressParts[] = htmlspecialchars($order['city']);
    if (!empty($order['country'])) $addressParts[] = htmlspecialchars($order['country']);
    $fullAddress = implode(', ', $addressParts);

    // --- Order items ophalen ---
    $stmt_items = $conn->prepare("
        SELECT oi.quantity, oi.price, oi.type, oi.maat, 
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

    // --- Berekeningen ---
    $products_total = 0;
    foreach ($items as $item) {
        $products_total += $item['price'] * $item['quantity'];
    }

    // verzendkosten = totaal + voucher - som producten
    $shipping = $total_order + $voucher_discount - $products_total;

    $total_without_shipping = $total_order - $shipping;
    $vat_total = $total_without_shipping * 0.21;

    $order['subtotal_price'] = $order_subtotal;
    $order['VAT_price'] = $vat_total;
    $order['shipping_costs'] = $shipping;

    // --- Pad voor PDF ---
    $dir = __DIR__ . '/invoices';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = date('Y-m-d', strtotime($order['created_at'])) . '-' . $order_id . '.pdf';
    $filepath = $dir . '/' . $filename;

    // --- PDF genereren ---
    try {
        $mpdf = new Mpdf([
            'tempDir' => __DIR__ . '/tmp',
            'margin_bottom' => 25 // ruimte voor footer
        ]);

        // --- Footer op elke pagina ---
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

        // --- Output bufferen om PHP-template te renderen ---
        ob_start();
        include __DIR__ . '/invoices/invoice_template.html';
        $html = ob_get_clean();

        // --- PDF renderen ---
        $mpdf->WriteHTML($html);
        $mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);

    } catch (\Mpdf\MpdfException $e) {
        error_log('PDF genereren mislukt: ' . $e->getMessage());
        return false;
    }

    // --- Pad opslaan in database ---
    $pdf_path = '/invoices/' . $filename;
    $update = $conn->prepare("UPDATE orders SET pdf_path = ? WHERE id = ?");
    $update->bind_param("si", $pdf_path, $order_id);
    $update->execute();
    $update->close();

    // --- Mail sturen met PDF als bijlage ---
    $name = $order['name'];
    $cartItems = $items;
    $total_order = $order['total_price'];
    $siteLanguage = $lang;
    $vatNumber = $order['vat_number'] ?? null;

    sendOrderConfirmationMail(
        $order['email'],
        $name,
        $order_id,
        $cartItems,
        $total_order,
        $siteLanguage,
        $filepath,
        $vatNumber
    );

    return $filename;
}
?>