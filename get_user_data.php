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

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    
    // Selecteer gebruikersgegevens zonder adres
    $stmt = $pdo->prepare("
        SELECT name, email, newsletter, company_name, vat_number
        FROM users
        WHERE id = :id
    ");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        // Haal shipping adres op
        $stmt_shipping = $pdo->prepare("
            SELECT id, street, house_number, postal_code, city, country
            FROM addresses
            WHERE user_id = :id AND type = 'shipping'
            LIMIT 1
        ");
        $stmt_shipping->execute([':id' => $_SESSION['user_id']]);
        $shippingAddress = $stmt_shipping->fetch();

        // Haal billing adres op
        $stmt_billing = $pdo->prepare("
            SELECT id, street, house_number, postal_code, city, country
            FROM addresses
            WHERE user_id = :id AND type = 'billing'
            LIMIT 1
        ");
        $stmt_billing->execute([':id' => $_SESSION['user_id']]);
        $billingAddress = $stmt_billing->fetch();

        // Voeg toe aan JSON output
        $user['shipping_address'] = $shippingAddress ?: null;
        $user['billing_address']  = $billingAddress ?: null;

        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'Gebruiker niet gevonden']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>