<?php
session_start();

include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

header('Content-Type: application/json'); // JSON response

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => t('invalid_email')]);
        exit;
    }

    // Controleer of e-mailadres bestaat
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['error' => t('db_prepare_failed') . ": " . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['error' => t('email_not_found')]);
        exit;
    }

    // Genereer reset token en verloopdatum
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Sla token op in database
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['error' => t('db_prepare_failed') . ": " . $conn->error]);
        exit;
    }
    $stmt->bind_param("sss", $token, $expires, $email);
    $stmt->execute();

    // Maak resetlink
    $resetLink = "https://stylisso.be/reset_password.html?token=" . $token;

    // Stuur e-mail
    $to = $email;
    $subject = t('password_reset_subject');
    $message = t('password_reset_message', [
        '{resetLink}' => $resetLink,
        '{expires}' => '1 ' . t('hour')
    ]);
    $headers = "From: klantendienst@stylisso.be\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo json_encode(['success' => t('password_reset_sent')]);
    } else {
        echo json_encode(['error' => t('password_reset_failed')]);
    }
}
?>