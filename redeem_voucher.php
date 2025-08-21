<?php
session_start();
require 'db_connect.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

// Controleer of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    die("Je moet ingelogd zijn om een bon in te wisselen.");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Controleer of voucher_code aanwezig is
    if (!isset($_POST['voucher_code']) || empty(trim($_POST['voucher_code']))) {
        die("Voer een geldige boncode in.");
    }

    $code = strtoupper(trim($_POST['voucher_code']));

    // Controleer of bon bestaat, nog waarde heeft en niet verlopen is
    $stmt = $conn->prepare("
        SELECT * FROM vouchers 
        WHERE code = ? 
          AND remaining_value > 0
          AND (expires_at IS NULL OR expires_at > NOW())
        LIMIT 1
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $voucher = $result->fetch_assoc();
    $stmt->close();

    if (!$voucher) {
        die("Deze boncode bestaat niet, is volledig gebruikt of is verlopen.");
    }

    // Controleer of bon al gekoppeld is aan deze gebruiker
    $stmt = $conn->prepare("SELECT id FROM user_vouchers WHERE user_id = ? AND voucher_id = ?");
    $stmt->bind_param("ii", $user_id, $voucher['id']);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        die("Deze cadeaubon is al aan jouw account gekoppeld.");
    }

    // Koppel bon aan gebruiker
    $stmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $voucher['id']);
    $stmt->execute();
    $stmt->close();

    echo "Cadeaubon succesvol gekoppeld aan je account! Waarde: €" . number_format($voucher['remaining_value'], 2, ',', '.');
}
?>