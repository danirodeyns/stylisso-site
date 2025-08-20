<?php
session_start();
require 'db_connect.php'; // PDO connectie
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

// Controleer of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    die("Je moet ingelogd zijn om een bon in te wisselen.");
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code']));

    // Controleer of bon bestaat en nog waarde heeft
    $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE code = :code AND remaining_value > 0");
    $stmt->execute([':code' => $code]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$voucher) {
        die("Deze boncode bestaat niet, is volledig gebruikt of is verlopen.");
    }

    // Controleer of bon al gekoppeld is aan deze gebruiker
    $stmt = $pdo->prepare("SELECT * FROM user_vouchers WHERE user_id = :user_id AND voucher_id = :voucher_id");
    $stmt->execute([
        ':user_id' => $user_id,
        ':voucher_id' => $voucher['id']
    ]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        die("Deze cadeaubon is al aan jouw account gekoppeld.");
    }

    // Koppel bon aan gebruiker
    $stmt = $pdo->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (:user_id, :voucher_id)");
    $stmt->execute([
        ':user_id' => $user_id,
        ':voucher_id' => $voucher['id']
    ]);

    echo "Cadeaubon succesvol gekoppeld aan je account! Waarde: €" . number_format($voucher['remaining_value'], 2);
}
?>