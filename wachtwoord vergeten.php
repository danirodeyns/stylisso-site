<?php
session_start();

include 'db_connect.php';
include 'translations.php';
include 'mailing.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => t('invalid_email')]);
        exit;
    }

    // Controleer of e-mailadres bestaat
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['error' => t('email_not_found')]);
        exit;
    }

    // Genereer reset token en verloopdatum
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Sla token op in database
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    $stmt->bind_param("ssi", $token, $expires, $user['id']);
    $stmt->execute();
    $stmt->close();

    // Maak resetlink
    $resetLink = "https://stylisso.be/reset_password.html?token=" . $token;

    // --- Verstuur mail via mailing.php ---
    $postData = http_build_query([
        'task' => 'password_reset_link',  // task in mailing.php voor wachtwoord reset
        'email' => $email,
        'reset_link' => $resetLink,
        'lang' => 'be-nl' // optie om taal uit user tabel of voorkeur te halen
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData
        ]
    ]);

    // Mail triggeren
    sendPasswordResetLinkMail($email, $resetLink);

    echo json_encode(['success' => t('password_reset_sent')]);
    exit;
}
?>