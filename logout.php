<?php
session_start();
include 'db_connect.php'; // DB connectie nodig om token te verwijderen

// Verwijder remember cookie en token uit DB indien aanwezig
if (isset($_COOKIE['user_login'])) {
    $cookie = json_decode($_COOKIE['user_login'], true);
    if (isset($cookie['id'])) {
        $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->bind_param("i", $cookie['id']);
        $stmt->execute();
    }

    // Cookie verwijderen
    setcookie('user_login', '', time() - 3600, '/', '', false, true); // secure=false, httpOnly=true
}

// Sessie vernietigen
session_unset();
session_destroy();

// Response naar frontend
header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>