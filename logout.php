<?php
session_start();
include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate();

// 1. Check of cookie bestaat
if (isset($_COOKIE['user_login'])) {
    $cookie = json_decode($_COOKIE['user_login'], true);

    // Beveiligde check
    if (isset($cookie['id']) && isset($cookie['token'])) {
        $user_id = $cookie['id'];
        $token   = $cookie['token'];

        // Token verwijderen voor dit apparaat
        $stmt = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token = ?");
        if ($stmt) {
            $stmt->bind_param("is", $user_id, $token);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Cookie verwijderen
    setcookie('user_login', '', time() - 3600, '/', '', false, true);
}

// 4. Sessie vernietigen
session_unset();
session_destroy();

// Response naar frontend
header('Content-Type: application/json');
echo json_encode(['success' => true]);
exit;
?>