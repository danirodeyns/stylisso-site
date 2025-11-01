<?php
session_start();
include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate();
include 'mailing.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Controleer of gebruiker ingelogd is ---
    if (!isset($_SESSION['user_id'])) {
        header("Location: login_registreren.html");
        exit;
    }
    $userId = $_SESSION['user_id'];

    // --- 1️⃣ Haal e-mailadres op voor bevestigingsmail ---
    $stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $userEmail = $user['email'] ?? null;
    $userName = $user['name'] ?? 'Gebruiker';

    // --- 2️⃣ Anonimiseer gebruiker ---
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

    // --- 3️⃣ Adressen verwijderen ---
    $stmt_addr = $conn->prepare("DELETE FROM addresses WHERE user_id = ?");
    $stmt_addr->bind_param("i", $userId);
    $stmt_addr->execute();

    // --- 4️⃣ Orders/returns loskoppelen ---
    $conn->query("UPDATE orders SET user_id = NULL WHERE user_id = $userId");
    $conn->query("UPDATE returns SET user_id = NULL WHERE user_id = $userId");

    // --- 5️⃣ Wishlist en cart leegmaken ---
    $conn->query("DELETE FROM wishlist WHERE user_id = $userId");
    $conn->query("DELETE FROM cart WHERE user_id = $userId");

    // --- 6️⃣ Mail sturen via mailing.php functie ---
    if ($userEmail) {
        sendAccountDeleteMail($userEmail, $userName);
    }

    // --- 7️⃣ Session en cookies vernietigen ---
    session_unset();
    session_destroy();

    if (isset($_COOKIE['user_login'])) {
        setcookie('user_login', '', time() - 3600, '/'); // pad '/' belangrijk
    }

    // --- 8️⃣ Redirect naar homepage ---
    header("Location: index.html");
    exit;

} else {
    header("Location: index.html");
    exit;
}
?>