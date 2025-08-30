<?php
session_start();
include 'db_connect.php';
include 'csrf.php';

// Controleer of gebruiker en checkout aanwezig zijn
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

    // Profiel updaten
    $stmt = $conn->prepare("UPDATE users SET address = ? WHERE id = ?");
    $stmt->bind_param("si", $address, $user_id);
    $stmt->execute();

    // Totaalprijs
    $total = $checkout['total'];

    // Gebruikte voucher uit hidden input
    $used_voucher = null;
    if (!empty($_POST['used_voucher'])) {
        $decoded = json_decode($_POST['used_voucher'], true);
        if (is_array($decoded) && !empty($decoded['code']) && isset($decoded['amount'])) {
            $used_voucher = $decoded;
        }
    }

    // Voeg order toe aan orders tabel
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_price, payment_method) VALUES (?, ?, ?)");
    $stmt->bind_param("ids", $user_id, $total, $payment_method);

    if ($stmt->execute()) {
        $order_id = $conn->insert_id;

        // Order items toevoegen
        $stmt_detail = $conn->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, price, type) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($checkout['cart_items'] as $item) {
            $type = $item['type'];
            $price = $item['price'];

            if ($type === 'product') {
                $prod_id = $item['product_id'];
                $qty = $item['quantity'];
                $stmt_detail->bind_param("iiids", $order_id, $prod_id, $qty, $price, $type);
                $stmt_detail->execute();
            } elseif ($type === 'voucher') {
                // Nieuwe voucher toevoegen aan order_items
                $prod_id = null; // NULL voor vouchers
                $qty = 1;
                $stmt_detail->bind_param("iiids", $order_id, $prod_id, $qty, $price, $type);
                $stmt_detail->execute();

                // Voucher code genereren
                $code = strtoupper(bin2hex(random_bytes(4)));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

                // Opslaan in vouchers tabel (zonder email)
                $stmt_voucher = $conn->prepare(
                    "INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)"
                );
                $stmt_voucher->bind_param("sdds", $code, $price, $price, $expires_at);
                $stmt_voucher->execute();

                // E-mail uit POST halen
                $email = $_POST['email'] ?? null;
                if ($email) {
                    $subject = "Jouw Stylisso cadeaubon";
                    $message = "Bedankt voor je aankoop!\n\n" .
                               "Je cadeauboncode: $code\n" .
                               "Waarde: €" . number_format($price, 2) . "\n" .
                               "Geldig tot: $expires_at\n\n" .
                               "Veel shopplezier bij Stylisso!";
                    $headers = "From: no-reply@stylisso.com";
                    mail($email, $subject, $message, $headers);
                }
            }
        }

        // Gebruikte voucher bijwerken
        if ($used_voucher) {
            $stmt_update = $conn->prepare(
                "UPDATE vouchers SET remaining_value = GREATEST(remaining_value - ?, 0) WHERE code = ?"
            );
            $amount = $used_voucher['amount'];
            $code = $used_voucher['code'];
            $stmt_update->bind_param("ds", $amount, $code);
            $stmt_update->execute();
        }

        // Winkelwagen leegmaken
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Checkout sessie leegmaken
        unset($_SESSION['checkout']);
        unset($_SESSION['used_voucher']);

        // Response naar JS
        echo "success";
        exit;
    } else {
        echo "Fout bij plaatsen bestelling: " . $conn->error;
        exit;
    }
}
?>