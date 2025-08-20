<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'loggedIn' => true,
        'name' => $_SESSION['user']['name'],
        'email' => $_SESSION['user']['email'],
    ]);
} else {
    echo json_encode(['loggedIn' => false]);
}
?>