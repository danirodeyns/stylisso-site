<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['checkout'])) {
    header('Location: cart.html');
    exit;
}

$user_id = $_SESSION['user_id'];
$checkout = $_SESSION['checkout'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    // Adres en betaalmethode uit formulier
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'] ?? '';

    // Adres updaten in DB
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt->bind_param("si", $address, $user_id);
    $stmt->execute();

    // Totaalprijs
    $total = $checkout['total'];

    // Voeg order toe aan orders tabel
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, payment_method) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total, $payment_method);

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;

        // Order items toevoegen
        $stmt_detail = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, type, extra_info) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($checkout['cart_items'] as $item) {
            if ($item['type'] === 'product') {
                // Normaal product
                $type = 'product';
                $extra_info = null;
                $stmt_detail->bind_param("iiidss", $order_id, $item['product_id'], $item['quantity'], $item['price'], $type, $extra_info);
                $stmt_detail->execute();
            } elseif ($item['type'] === 'voucher') {
                // Voucher verwerken
                $type = 'voucher';
                $extra_info = $item['email'];
                $stmt_detail->bind_param("iiidss", $order_id, $null = 0, $qty = 1, $item['price'], $type, $extra_info);
                $stmt_detail->execute();

                // Voucher code genereren
                $code = strtoupper(bin2hex(random_bytes(4)));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

                // Opslaan in vouchers tabel
                $stmt_voucher = $conn->prepare("INSERT INTO vouchers (code, value, remaining_value, expires_at, email) VALUES (?, ?, ?, ?, ?)");
                $stmt_voucher->bind_param("sddss", $code, $item['price'], $item['price'], $expires_at, $item['email']);
                $stmt_voucher->execute();

                // E-mail versturen
                $subject = "Jouw Stylisso cadeaubon";
                $message = "Bedankt voor je aankoop!\n\n" .
                           "Je cadeauboncode: $code\n" .
                           "Waarde: €" . number_format($item['price'], 2) . "\n" .
                           "Geldig tot: $expires_at\n\n" .
                           "Veel shopplezier bij Stylisso!";
                $headers = "From: no-reply@stylisso.com";

                mail($item['email'], $subject, $message, $headers);
            }
        }

        // Leeg winkelwagen in DB
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Sessie checkout leegmaken
        unset($_SESSION['checkout']);

        echo "Bestelling succesvol geplaatst! Totaal: €" . number_format($total, 2);
        exit;
    } else {
        echo "Fout bij plaatsen bestelling: " . $conn->error;
        exit;
    }
}
?>