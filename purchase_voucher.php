<?php
session_start();
require 'db_connect.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Haal gegevens op en filter
    $amount = floatval($_POST['amount']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($amount < 5) {
        die("Ongeldig bedrag. Kies een bedrag van minimaal €5.");
    }

    if (!$email) {
        die("Ongeldig e-mailadres.");
    }

    // Zet voucher in de sessie-cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][] = [
        'type' => 'voucher',
        'amount' => $amount,
        'email' => $email
    ];

    // Redirect terug naar winkelwagen
    header("Location: cart.html");
    exit;
}
?>