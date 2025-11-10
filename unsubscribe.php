<?php
include 'db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Geen e-mailadres opgegeven.']);
        exit;
    }

    // Bereid de query voor
    $stmt = $conn->prepare("UPDATE users SET newsletter = 0 WHERE email = ?");
    $stmt->bind_param("s", $email);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Uitschrijven gelukt.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Databasefout bij uitschrijven.']);
    }

    $stmt->close();
    $conn->close();
}
?>