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
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$userEmail = $user['email'] ?? null;

// 2️⃣ Anonimiseer gebruiker (i.p.v. verwijderen)
$stmt = $conn->prepare("
    UPDATE users 
    SET name = 'Verwijderd', 
        email = CONCAT('deleted_', id, '@example.com'),
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

// 3️⃣ Adressen verwijderen uit `addresses` tabel
$stmt_addr = $conn->prepare("DELETE FROM addresses WHERE user_id = ?");
$stmt_addr->bind_param("i", $userId);
$stmt_addr->execute();

// 4️⃣ Orders/returns → user_id loskoppelen
$conn->query("UPDATE orders SET user_id = NULL WHERE user_id = $userId");
$conn->query("UPDATE returns SET user_id = NULL WHERE user_id = $userId");

// 5️⃣ Wishlist en cart leegmaken
$conn->query("DELETE FROM wishlist WHERE user_id = $userId");
$conn->query("DELETE FROM cart WHERE user_id = $userId");

// 6️⃣ Mail sturen via mailing.php
if ($userEmail) {
    $postData = http_build_query([
        'task' => 'account_delete',
        'email' => $userEmail
    ]);

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $postData
        ]
    ]);

    // Verstuur de mail, errors worden genegeerd zoals @mail eerder
    @file_get_contents('mailing.php', false, $context);
}

// 7️⃣ Session en cookies vernietigen
session_unset();
session_destroy();

// Cookie verwijderen (zelfde naam en pad als bij login.php)
if (isset($_COOKIE['user_login'])) {
    setcookie('user_login', '', time() - 3600, '/'); // pad '/' belangrijk
}

header("Location: index.html");
exit;
?>