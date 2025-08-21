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

        // Voeg order details toe
        $stmt_detail = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($checkout['cart_items'] as $item) {
            $stmt_detail->bind_param("iiid", $order_id, $item['product_id'], $item['quantity'], $item['price']);
            $stmt_detail->execute();
        }

        // Leeg winkelwagen
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