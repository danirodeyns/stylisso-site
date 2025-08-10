<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'loggedIn' => true,
        'name' => $_SESSION['user']['name'],  // of welke key je ook gebruikt voor naam
        'email' => $_SESSION['user']['email'],
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>