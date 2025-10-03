<?php
session_start();
header('Content-Type: application/json');

include 'db_connect.php'; // Voeg je database connectie toe
include 'translations.php'; // Voeg je vertalingen toe

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Haal de toegang van de gebruiker op
    $stmt = $conn->prepare("SELECT toegang FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $toegang = $user ? $user['toegang'] : null;

    echo json_encode([
        'logged_in' => true,
        'toegang' => $toegang
    ]);
} else {
    echo json_encode(['logged_in' => false, 'toegang' => null]);
}
?>