<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['checkout'])) {
    echo "Geen gebruiker of checkout sessie";
    exit;
}

$user_id = $_SESSION['user_id'];
$checkout = $_SESSION['checkout'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'] ?? '';
    $email = $_POST['email'] ?? '';

    // Profiel bijwerken
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt->bind_param("si", $address, $user_id);
    $stmt->execute();

    // Gebruikte voucher
    $used_voucher = null;
    if (!empty($_POST['used_voucher'])) {
        $decoded = json_decode($_POST['used_voucher'], true);
        if (is_array($decoded) && !empty($decoded['code']) && isset($decoded['amount'])) {
            $used_voucher = $decoded;
        }
    }

    $voucher_discount = isset($used_voucher['amount']) ? floatval($used_voucher['amount']) : 0;
    $order_subtotal = floatval($checkout['subtotal'] ?? 0); // of $checkout['total'] vóór korting
    $used_amount = min($voucher_discount, $order_subtotal); // Dit is wat je écht gebruikt
    $total_order = max(0, floatval($checkout['total']) - $used_amount);

    // Order toevoegen
    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_price, payment_method) VALUES (?, ?, ?)");
    if (!$stmt_order) { echo "Prepare order fout: ".$conn->error; exit; }
    $stmt_order->bind_param("ids", $user_id, $total_order, $payment_method);
    if (!$stmt_order->execute()) { echo "Execute order fout: ".$stmt_order->error; exit; }
    $order_id = $conn->insert_id;

    // Order items toevoegen
    $stmt_item = $conn->prepare(
        "INSERT INTO order_items (order_id, product_id, quantity, price, type) VALUES (?, ?, ?, ?, ?)"
    );

    foreach ($checkout['cart_items'] as $item) {
        $type = $item['type'];
        $price = floatval($item['price']);
        $qty = intval($item['quantity']);
        $prod_id = $item['type'] === 'product' ? intval($item['product_id']) : null;

        // Bind_param ondersteunt NULL voor i niet; gebruik s + converteer
        $stmt_item->bind_param("iiids", $order_id, $prod_id, $qty, $price, $type);
        $stmt_item->execute();

        // Nieuwe vouchers genereren bij voucher-items
        if ($type === 'voucher') {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

            $stmt_v = $conn->prepare(
                "INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)"
            );
            $stmt_v->bind_param("sdds", $code, $price, $price, $expires_at);
            $stmt_v->execute();

            // Mailen
            if ($email) {
                $subject = "Jouw Stylisso cadeaubon";
                $message = "Bedankt voor je aankoop!\n\n" .
                           "Je cadeauboncode: $code\n" .
                           "Waarde: €".number_format($price,2)."\n" .
                           "Geldig tot: $expires_at\n\n" .
                           "Veel shopplezier bij Stylisso!";
                mail($email, $subject, $message, "From: no-reply@stylisso.com");
            }
        }
    }

    // Gebruikte voucher bijwerken
    if ($used_voucher) {
        $stmt_uv = $conn->prepare(
            "UPDATE vouchers SET remaining_value = GREATEST(remaining_value - ?, 0) WHERE code = ?"
        );
        $stmt_uv->bind_param("ds", $used_amount, $used_voucher['code']);
        $stmt_uv->execute();
    }

    // Winkelwagen leegmaken
    $stmt_cart = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt_cart->bind_param("i", $user_id);
    $stmt_cart->execute();

    unset($_SESSION['checkout']);
    unset($_SESSION['used_voucher']);

    echo "success";
    exit;
}
?>