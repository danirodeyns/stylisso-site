<?php
session_start();
require 'db_connect.php';
include 'csrf.php';
include 'translations.php';
include 'mailing.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_validate();

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
    $stmt = $conn->prepare("SELECT id, email, name, reset_expires FROM users WHERE reset_token = ? LIMIT 1");
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
    $email = $row["email"];
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Wachtwoord updaten
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();
    $stmt->close();

    // --- Verstuur bevestigingsmail via mailing.php ---
    if ($email) {
        $postData = http_build_query([
            'task' => 'password_reset_success',
            'email' => $email,
            'name' => $row['name'],
            'lang' => 'be-nl' // optie om taal uit user of order op te halen
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $postData
            ]
        ]);

        // Mail triggeren
        sendPasswordResetSuccessMail($email, $row['name']);
    }

    header("Location: reset_succes.html");
    exit;
}
?>