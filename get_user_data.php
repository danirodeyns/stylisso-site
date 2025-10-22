<?php
session_start();
header('Content-Type: application/json');

// Controleer of de gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

// Database connectie
include 'db_connect.php';
include 'translations.php';

// Maak mysqli connectie
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connectie mislukt: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset("utf8mb4");

// Haal gebruikersgegevens op zonder adres
$stmt = $conn->prepare("
    SELECT name, email, newsletter, company_name, vat_number
    FROM users
    WHERE id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Haal shipping adres op
    $stmt_shipping = $conn->prepare("
        SELECT id, street, house_number, postal_code, city, country
        FROM addresses
        WHERE user_id = ? AND type = 'shipping'
        LIMIT 1
    ");
    $stmt_shipping->bind_param("i", $_SESSION['user_id']);
    $stmt_shipping->execute();
    $shippingResult = $stmt_shipping->get_result();
    $shippingAddress = $shippingResult->fetch_assoc();
    $stmt_shipping->close();

    // Haal billing adres op
    $stmt_billing = $conn->prepare("
        SELECT id, street, house_number, postal_code, city, country
        FROM addresses
        WHERE user_id = ? AND type = 'billing'
        LIMIT 1
    ");
    $stmt_billing->bind_param("i", $_SESSION['user_id']);
    $stmt_billing->execute();
    $billingResult = $stmt_billing->get_result();
    $billingAddress = $billingResult->fetch_assoc();
    $stmt_billing->close();

    // Voeg toe aan JSON output
    $user['shipping_address'] = $shippingAddress ?: null;
    $user['billing_address']  = $billingAddress ?: null;

    echo json_encode($user);
} else {
    echo json_encode(['error' => 'Gebruiker niet gevonden']);
}

$conn->close();
?>