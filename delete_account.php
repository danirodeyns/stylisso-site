<?php
session_start();
require_once 'db_connect.php';
include 'translations.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login_registreren.html");
    exit;
}

$userId = $_SESSION['user_id'];

// 1️⃣ Haal e-mailadres op voor bevestigingsmail
$result = $conn->prepare("SELECT email FROM users WHERE id = ?");
$result->bind_param("i", $userId);
$result->execute();
$res = $result->get_result();
$user = $res->fetch_assoc();
$userEmail = $user['email'] ?? null;

// 2️⃣ Anonimiseer user (i.p.v. verwijderen)
$stmt = $conn->prepare("
    UPDATE users 
    SET name = 'Verwijderd', 
        email = CONCAT('deleted_', id, '@example.com'),
        address = NULL,
        company_name = NULL,
        vat_number = NULL,
        newsletter = 0,
        reset_token = NULL,
        reset_expires = NULL,
        remember_token = NULL,
        remember_expires = NULL,
        terms_accepted = 0
    WHERE id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();

// 3️⃣ Orders/returns → user_id loskoppelen
$conn->query("UPDATE orders SET user_id = NULL WHERE user_id = $userId");
$conn->query("UPDATE returns SET user_id = NULL WHERE user_id = $userId");

// 4️⃣ (Optioneel) Wishlist en cart leegmaken
$conn->query("DELETE FROM wishlist WHERE user_id = $userId");
$conn->query("DELETE FROM cart WHERE user_id = $userId");

// 5️⃣ Mail sturen
if ($userEmail) {
    $subject = t('account_delete_subject');
    $message = t('account_delete_message');
    $headers = "From: no-reply@stylisso.be";

    @mail($userEmail, $subject, $message, $headers);
}

// 6️⃣ Session vernietigen en redirect
session_unset();
session_destroy();

header("Location: index.html");
exit;
?>