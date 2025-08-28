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

    // Voeg voucher toe aan de cart-tabel in DB
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        die("Je moet ingelogd zijn om een cadeaubon toe te voegen.");
    }

    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, type, quantity, price) VALUES (?, NULL, 'voucher', 1, ?)");
    $stmt->bind_param("id", $user_id, $amount);
    $stmt->execute();
    $stmt->close();

    // Eventueel kan je ook tijdelijk in sessie opslaan, indien nodig
    if (!isset($_SESSION['cart_vouchers'])) {
        $_SESSION['cart_vouchers'] = [];
    }
    $_SESSION['cart_vouchers'][] = [
        'amount' => $amount,
        'email' => $email
    ];

    // Redirect terug naar winkelwagen
    header("Location: cart.html");
    exit;
}
?>