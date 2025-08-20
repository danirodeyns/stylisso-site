<?php
session_start();
include 'db_connect.php'; // Zorg dat je DB-connectie hier hebt

header('Content-Type: application/json');

$response = ['loggedIn' => false];

if (isset($_SESSION['user_id'])) {
    // Gebruiker is ingelogd via sessie
    $response['loggedIn'] = true;
    $response['userName'] = $_SESSION['user_name'];
} elseif (isset($_COOKIE['user_login'])) {
    // Controleer de remember cookie
    $cookie = json_decode($_COOKIE['user_login'], true);

    if (isset($cookie['id'], $cookie['token'])) {
        $stmt = $conn->prepare("SELECT name, remember_token FROM users WHERE id = ?");
        $stmt->bind_param("i", $cookie['id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Vergelijk token uit cookie met token in DB
            if (hash_equals($user['remember_token'], $cookie['token'])) {
                // Token klopt, log gebruiker in via sessie
                $_SESSION['user_id'] = $cookie['id'];
                $_SESSION['user_name'] = $user['name'];
                $response['loggedIn'] = true;
                $response['userName'] = $user['name'];
            }
        }
    }
}

echo json_encode($response);
?>