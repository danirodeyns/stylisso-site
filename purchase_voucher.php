<?php
session_start();
require 'db_connect.php';
include 'csrf.php';
csrf_validate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = floatval($_POST['amount']);
    
    if ($amount < 5) {
        die("Ongeldig bedrag. Kies een bedrag van minimaal €5.");
    }

    $user_id = $_SESSION['user_id'] ?? null;

    if ($user_id) {
        // Voeg voucher toe aan DB-cart voor ingelogde gebruiker
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) VALUES (?, NULL, 'voucher', 1, ?)");
        $stmt->bind_param("id", $user_id, $amount);
        $stmt->execute();
        $stmt->close();
    } else {
        // Sla voucher tijdelijk op in sessie voor niet-ingelogde gebruiker
        if (!isset($_SESSION['cart_vouchers'])) {
            $_SESSION['cart_vouchers'] = [];
        }
        $_SESSION['cart_vouchers'][] = [
            'type' => 'voucher',
            'name' => 'Cadeaubon',
            'price' => $amount,
            'quantity' => 1
        ];
    }

    // Redirect terug naar winkelwagen
    header("Location: cart.html");
    exit;
}
?>