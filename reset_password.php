<?php
session_start();
require 'db_connect.php'; // maakt $conn aan (mysqli)
include 'csrf.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate(); // stopt script als token fout is

    $token = $_POST["token"] ?? "";
    $password = $_POST["password"] ?? "";
    $password_confirm = $_POST["password_confirm"] ?? "";

    if (empty($token) || empty($password) || empty($password_confirm)) {
        header("Location: reset_password.html?token=" . urlencode($token) . "&error=Alle+velden+zijn+verplicht");
        exit;
    }

    if ($password !== $password_confirm) {
        header("Location: reset_password.html?token=" . urlencode($token) . "&error=Wachtwoorden+komen+niet+overeen");
        exit;
    }

    // Token controleren bij users
    $stmt = $conn->prepare("SELECT id, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        header("Location: reset_password.html?error=Ongeldige+of+verlopen+reset-link");
        exit;
    }

    if (strtotime($row["reset_expires"]) < time()) {
        header("Location: reset_password.html?token=" . urlencode($token) . "&error=Deze+reset-link+is+verlopen");
        exit;
    }

    $userId = $row["id"];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Wachtwoord updaten
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();
    $stmt->close();

    header("Location: reset_succes.html");
    exit;
}
?>