<?php
session_start();
include 'db_connect.php';
include 'csrf.php';
include 'translations.php';

// ================================
// 0. Sessie check
// ================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['checkout'])) {
    echo "Geen gebruiker of checkout sessie";
    exit;
}

$user_id = $_SESSION['user_id'];
$checkout = $_SESSION['checkout'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    // ================================
    // 1. Adres- en bedrijfsgegevens ophalen
    // ================================
    $street        = trim($_POST['street'] ?? '');
    $house_number  = trim($_POST['house_number'] ?? '');
    $postal_code   = trim($_POST['postal_code'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $country       = trim($_POST['country'] ?? '');
    $payment_method = $_POST['payment_method'] ?? '';
    $email         = $_POST['email'] ?? '';
    $company_name  = trim($_POST['company_name'] ?? '');
    $vat_number    = trim($_POST['vat_number'] ?? '');
    $differentBilling     = isset($_POST['different_billing']);
    $billing_street       = trim($_POST['billing_street'] ?? '');
    $billing_house_number = trim($_POST['billing_house_number'] ?? '');
    $billing_postal_code  = trim($_POST['billing_postal_code'] ?? '');
    $billing_city         = trim($_POST['billing_city'] ?? '');
    $billing_country      = trim($_POST['billing_country'] ?? '');

    // ================================
    // 1B. Validatie van betaalmethode
    // ================================
    $allowed_methods = ['credit card', 'bancontact', 'paypal', 'apple pay', 'google pay'];
    if (!in_array($payment_method, $allowed_methods, true) && $payment_method !== '') {
        echo "Ongeldige betaalmethode.";
        exit;
    }

    // ================================
    // 2. Functie om land te normaliseren
    // ================================
    function normalizeCountry($str) {
        $str = trim($str);
        $str = mb_strtolower($str, 'UTF-8');
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
        $str = preg_replace('/[^a-z]/', '', $str);
        return $str;
    }

    $allowedCountries = ['belgie', 'belgium', 'belgique'];

    // ================================
    // 3. Land validatie
    // ================================
    $normalizedShipping = normalizeCountry($country);
    if (!in_array($normalizedShipping, $allowedCountries)) {
        echo "Verzending naar {$country} is nog niet mogelijk.";
        exit;
    }

    if ($differentBilling) {
        $normalizedBilling = normalizeCountry($billing_country);
        if (!in_array($normalizedBilling, $allowedCountries)) {
            echo "Facturatie naar {$billing_country} is nog niet mogelijk.";
            exit;
        }
    }

    // ================================
    // 4. User updaten met company + VAT
    // ================================
    $stmt_user = $conn->prepare("UPDATE users SET company_name = ?, vat_number = ? WHERE id = ?");
    $stmt_user->bind_param("ssi", $company_name, $vat_number, $user_id);
    $stmt_user->execute();

    // ================================
    // 5. Shipping adres opslaan of updaten
    // ================================
    $stmt_check_ship = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? AND type = 'shipping'");
    $stmt_check_ship->bind_param("i", $user_id);
    $stmt_check_ship->execute();
    $res_ship = $stmt_check_ship->get_result();

    if ($res_ship->num_rows > 0) {
        $row = $res_ship->fetch_assoc();
        $shipping_address_id = $row['id'];
        $stmt_update_ship = $conn->prepare("
            UPDATE addresses 
            SET street=?, house_number=?, postal_code=?, city=?, country=? 
            WHERE id=? AND user_id=? AND type='shipping'
        ");
        $stmt_update_ship->bind_param("ssssssi", $street, $house_number, $postal_code, $city, $country, $shipping_address_id, $user_id);
        $stmt_update_ship->execute();
    } else {
        $stmt_insert_ship = $conn->prepare("
            INSERT INTO addresses (user_id, street, house_number, postal_code, city, country, type)
            VALUES (?, ?, ?, ?, ?, ?, 'shipping')
        ");
        $stmt_insert_ship->bind_param("isssss", $user_id, $street, $house_number, $postal_code, $city, $country);
        $stmt_insert_ship->execute();
        $shipping_address_id = $conn->insert_id;
    }

    // ================================
    // 6. Billing adres opslaan of updaten
    // ================================
    if ($differentBilling && $billing_street && $billing_house_number && $billing_postal_code && $billing_city && $billing_country) {
        $stmt_check_bill = $conn->prepare("SELECT id FROM addresses WHERE user_id = ? AND type = 'billing'");
        $stmt_check_bill->bind_param("i", $user_id);
        $stmt_check_bill->execute();
        $res_bill = $stmt_check_bill->get_result();

        if ($res_bill->num_rows > 0) {
            $row = $res_bill->fetch_assoc();
            $billing_address_id = $row['id'];
            $stmt_update_bill = $conn->prepare("
                UPDATE addresses 
                SET street=?, house_number=?, postal_code=?, city=?, country=? 
                WHERE id=? AND user_id=? AND type='billing'
            ");
            $stmt_update_bill->bind_param("ssssssi", $billing_street, $billing_house_number, $billing_postal_code, $billing_city, $billing_country, $billing_address_id, $user_id);
            $stmt_update_bill->execute();
        } else {
            $stmt_insert_bill = $conn->prepare("
                INSERT INTO addresses (user_id, street, house_number, postal_code, city, country, type)
                VALUES (?, ?, ?, ?, ?, ?, 'billing')
            ");
            $stmt_insert_bill->bind_param("isssss", $user_id, $billing_street, $billing_house_number, $billing_postal_code, $billing_city, $billing_country);
            $stmt_insert_bill->execute();
            $billing_address_id = $conn->insert_id;
        }
    } else {
        $billing_address_id = null;
    }

    // ================================
    // 7. Voucher check & korting berekenen
    // ================================
    $used_voucher = null;
    if (!empty($_POST['used_voucher'])) {
        $decoded = json_decode($_POST['used_voucher'], true);
        if (is_array($decoded) && !empty($decoded['code']) && isset($decoded['amount'])) {
            $used_voucher = $decoded;
        }
    }

    $voucher_discount = isset($used_voucher['amount']) ? floatval($used_voucher['amount']) : 0;
    $order_subtotal = floatval($checkout['subtotal'] ?? 0);
    $used_amount = min($voucher_discount, $order_subtotal);
    $total_order = max(0, floatval($checkout['total']) - $used_amount);

    // ================================
    // 8. Order toevoegen (met taal)
    // ================================
    $allowed_langs = ['be-nl','be-fr','be-en','be-de'];
    $siteLanguage = $_COOKIE['siteLanguage'] ?? 'be-nl';
    if (!in_array($siteLanguage, $allowed_langs, true)) {
        $siteLanguage = 'be-nl';
    }

    $stmt_order = $conn->prepare("
        INSERT INTO orders (user_id, total_price, payment_method, taal)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_order->bind_param("idss", $user_id, $total_order, $payment_method, $siteLanguage);
    $stmt_order->execute();
    $order_id = $conn->insert_id;

    // ================================
    // 9. Order items + vouchers
    // ================================
    foreach ($checkout['cart_items'] as $item) {
        $type = $item['type'] ?? 'product';
        $price = floatval($item['price']);
        $qty = intval($item['quantity']);
        $prod_id = $type === 'product' ? intval($item['product_id']) : null;
        $voucher_id = null;
        $maat = $item['maat'] ?? null;

        if ($type === 'voucher') {
            do {
                $code = strtoupper(bin2hex(random_bytes(6)));
                $stmt_check = $conn->prepare("SELECT id FROM vouchers WHERE code = ?");
                $stmt_check->bind_param("s", $code);
                $stmt_check->execute();
                $stmt_check->store_result();
                $exists = $stmt_check->num_rows > 0;
                $stmt_check->close();
            } while ($exists);

            $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

            $stmt_v = $conn->prepare("INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)");
            $stmt_v->bind_param("sdds", $code, $price, $price, $expires_at);
            $stmt_v->execute();
            $voucher_id = $conn->insert_id;

            // --- Mail naar mailing.php voor de cadeaubon
            $postData = http_build_query([
                'task' => 'voucher',
                'email' => $email,
                'code' => $code,
                'price' => number_format($price, 2),
                'expires_at' => $expires_at
            ]);
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData
                ]
            ]);
            file_get_contents('mailing.php', false, $context);
        }

        // Item toevoegen
        $stmt_item = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, voucher_id, type, quantity, price, maat)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_item->bind_param("iiisids", $order_id, $prod_id, $voucher_id, $type, $qty, $price, $maat);
        $stmt_item->execute();
    }

    // ================================
    // 10. Factuur aanmaken via externe POST naar create_invoice.php
    // ================================
    $postInvoice = http_build_query([
        'order_id' => $order_id,
        'lang' => $siteLanguage,
        'email' => $email,
        'used_voucher' => json_encode($used_voucher)
    ]);

    $contextInvoice = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postInvoice
        ]
    ]);
    file_get_contents('create_invoice.php', false, $contextInvoice);

    // ================================
    // 11. Winkelwagen leegmaken & sessie reset
    // ================================
    $stmt_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();

    unset($_SESSION['checkout'], $_SESSION['used_voucher']);

    echo "success";
    exit;
}
?>