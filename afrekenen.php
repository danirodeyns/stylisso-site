<?php
session_start();
include 'db_connect.php';
include 'csrf.php';
include 'translations.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['checkout'])) {
    echo "Geen gebruiker of checkout sessie";
    exit;
}

$user_id = $_SESSION['user_id'];
$checkout = $_SESSION['checkout'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    // --- Adresvelden ophalen ---
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

    // --- Functie om land te normaliseren (accents, hoofdletters, spaties) ---
    function normalizeCountry($str) {
        $str = trim($str);
        $str = mb_strtolower($str, 'UTF-8');                  // kleine letters
        $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);      // accenten verwijderen
        $str = preg_replace('/[^a-z]/', '', $str);           // niet-letters verwijderen
        return $str;
    }

    $allowedCountries = ['belgie', 'belgium', 'belgique'];

    // --- Valideer landen ---
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

    // --- Shipping adres opslaan ---
    $stmt_addr = $conn->prepare("
        INSERT INTO addresses (user_id, street, house_number, postal_code, city, country, type)
        VALUES (?, ?, ?, ?, ?, ?, 'shipping')
    ");
    $stmt_addr->bind_param("isssss", $user_id, $street, $house_number, $postal_code, $city, $country);
    if (!$stmt_addr->execute()) {
        echo "Fout bij opslaan shipping adres: ".$stmt_addr->error;
        exit;
    }
    $shipping_address_id = $conn->insert_id;

    // --- Billing adres opslaan (indien anders) ---
    if ($differentBilling && $billing_street && $billing_house_number && $billing_postal_code && $billing_city && $billing_country) {
        $stmt_bill = $conn->prepare("
            INSERT INTO addresses (user_id, street, house_number, postal_code, city, country, type)
            VALUES (?, ?, ?, ?, ?, ?, 'billing')
        ");
        $stmt_bill->bind_param("isssss", $user_id, $billing_street, $billing_house_number, $billing_postal_code, $billing_city, $billing_country);
        if (!$stmt_bill->execute()) {
            echo "Fout bij opslaan billing adres: ".$stmt_bill->error;
            exit;
        }
        $billing_address_id = $conn->insert_id;
    } else {
        $billing_address_id = null;
    }

    // --- Gebruikte voucher ---
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

    // --- Order toevoegen ---
    $stmt_order = $conn->prepare("
        INSERT INTO orders (user_id, total_price, payment_method, address_id, company_name, vat_number)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt_order) { echo "Prepare order fout: ".$conn->error; exit; }
    $stmt_order->bind_param("idisss", $user_id, $total_order, $payment_method, $shipping_address_id, $company_name, $vat_number);
    if (!$stmt_order->execute()) { echo "Execute order fout: ".$stmt_order->error; exit; }
    $order_id = $conn->insert_id;

    // --- PDF factuur genereren ---
    $order_date = date('Y-m-d');
    include 'create_invoice.php';
    create_invoice($order_id, $order_date, $conn);

    // --- Order items toevoegen ---
    foreach ($checkout['cart_items'] as $item) {
        $type = $item['type'];
        $price = floatval($item['price']);
        $qty = intval($item['quantity']);
        $prod_id = $type === 'product' ? intval($item['product_id']) : null;
        $voucher_id = null;

        // Voucher genereren
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

            $stmt_v = $conn->prepare(
                "INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)"
            );
            $stmt_v->bind_param("sdds", $code, $price, $price, $expires_at);
            $stmt_v->execute();
            $voucher_id = $conn->insert_id;

            // Mailen
            if ($email) {
                $subject = t('email_subject');
                $messageTemplate = t('email_message');
                $message = str_replace(
                    ['{code}', '{price}', '{expires_at}'],
                    [$code, number_format($price,2), $expires_at],
                    $messageTemplate
                );
                mail($email, $subject, $message, "From: no-reply@stylisso.be");
            }
        }

        // Order item invoegen
        $stmt_item = $conn->prepare(
            "INSERT INTO order_items (order_id, product_id, voucher_id, quantity, price, type) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt_item->bind_param("iiiids", $order_id, $prod_id, $voucher_id, $qty, $price, $type);
        $stmt_item->execute();
    }

    // --- Gebruikte voucher bijwerken ---
    if ($used_voucher) {
        $stmt_uv = $conn->prepare(
            "UPDATE vouchers SET remaining_value = GREATEST(remaining_value - ?, 0) WHERE code = ?"
        );
        $stmt_uv->bind_param("ds", $used_amount, $used_voucher['code']);
        $stmt_uv->execute();
    }

    // --- Winkelwagen leegmaken ---
    $stmt_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();

    unset($_SESSION['checkout']);
    unset($_SESSION['used_voucher']);

    echo "success";
    exit;
}
?>