<?php
session_start();

// Stel de content type header in op JSON
header('Content-Type: application/json');

// Simpele controle of gebruiker ingelogd is via session variabele
if (isset($_SESSION['user_id'])) {
    // Als je ook een gebruikersnaam of e-mail opslaat in sessie, geef die mee
    $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';

    echo json_encode([
        'loggedIn' => true,
        'userName' => $userName
    ]);
} else {
    // Niet ingelogd
    echo json_encode([
        'loggedIn' => false
    ]);
}
?>