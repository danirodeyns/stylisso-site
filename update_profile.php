<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate();

// Mysqli connectie
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Database verbinding mislukt: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

function cleanInput($data) {
    return htmlspecialchars(trim($data));
}

// ------------------------
// Functie om land te normaliseren (accents en hoofdletters negeren)
// ------------------------
function normalizeCountry($str) {
    $str = trim($str);
    $str = mb_strtolower($str, 'UTF-8');
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = preg_replace('/[^a-z]/', '', $str);
    return $str;
}

// Toegestane landen
$allowedCountries = ['belgie', 'belgium', 'belgique'];

// ------------------------
// Basisgegevens ophalen
// ------------------------
$name = cleanInput($_POST['name'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$telephone = cleanInput($_POST['telephone'] ?? '');
$company_name = cleanInput($_POST['company_name'] ?? '');
$vat_number = cleanInput($_POST['vat_number'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['passwordConfirm'] ?? '';
$newsletter = isset($_POST['newsletter']) ? 1 : 0;

// ------------------------
// Shippingadresgegevens
// ------------------------
$street        = cleanInput($_POST['street'] ?? '');
$house_number  = cleanInput($_POST['house_number'] ?? '');
$postal_code   = cleanInput($_POST['postal_code'] ?? '');
$city          = cleanInput($_POST['city'] ?? '');
$country       = cleanInput($_POST['country'] ?? '');

// ------------------------
// Factuuradresgegevens
// ------------------------
$differentBilling     = isset($_POST['different_billing']);
$billing_street       = cleanInput($_POST['billing_street'] ?? '');
$billing_house_number = cleanInput($_POST['billing_house_number'] ?? '');
$billing_postal_code  = cleanInput($_POST['billing_postal_code'] ?? '');
$billing_city         = cleanInput($_POST['billing_city'] ?? '');
$billing_country      = cleanInput($_POST['billing_country'] ?? '');

// ------------------------
// Validaties
// ------------------------
$errors = [];

if (empty($name)) {
    $errors[] = 'name_empty';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email_invalid';
}

if ($password !== $passwordConfirm) {
    $errors[] = 'password_mismatch';
}

$shippingFilled = $street || $house_number || $postal_code || $city || $country;

if ($shippingFilled && (!$street || !$house_number || !$postal_code || !$city || !$country)) {
    $errors[] = 'address_empty';
}

// Check of e-mail al bestaat bij andere gebruiker
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $_SESSION['user_id']);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $errors[] = 'email_exists';
}
$stmt->close();

// Check of nieuw wachtwoord anders is dan huidige
if (!empty($password)) {
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($currentPasswordHash);
    $stmt->fetch();
    $stmt->close();

    if ($currentPasswordHash && password_verify($password, $currentPasswordHash)) {
        $errors[] = 'password_same';
    }
}

// ------------------------
// Land validatie
// ------------------------
// Shipping land check
$normalizedShipping = normalizeCountry($country);
if (!in_array($normalizedShipping, $allowedCountries)) {
    $errors[] = 'country_not_allowed';
}

// Billing land check (alleen als billing anders is)
if ($differentBilling) {
    $normalizedBilling = normalizeCountry($billing_country);
    if (!in_array($normalizedBilling, $allowedCountries)) {
        $errors[] = 'billing_country_not_allowed';
    }
}

// ------------------------
// Telephone normaliseren
// ------------------------
function normalizeTelephone($tel) {
    $tel = trim($tel);

    // Alles behalve cijfers en plus weghalen
    $tel = preg_replace('/[^\d\+]/', '', $tel);

    // Controleer op internationaal formaat
    if (strpos($tel, '+') === 0) {
        // al in internationaal formaat, ok
        $tel = $tel;
    } elseif (strpos($tel, '00') === 0) {
        // 00xx naar +xx
        $tel = '+' . substr($tel, 2);
    } elseif (strpos($tel, '0') === 0) {
        // lokaal nummer beginnend met 0, Belgische standaard +32
        $tel = '+32' . substr($tel, 1);
    } else {
        // onbekend formaat, fallback +32
        $tel = '+32' . $tel;
    }

    return $tel;
}

// ------------------------
// Pas normalisatie toe
// ------------------------
$telephone = normalizeTelephone($telephone);

// Optionele validatie
if (!preg_match('/^\+\d+$/', $telephone)) {
    $errors[] = 'telephone_invalid';
}

// Stop bij fouten
if (!empty($errors)) {
    header("Location: gegevens.html?success=0&errors=" . implode(',', $errors));
    exit;
}

// ------------------------
// Users tabel bijwerken
// ------------------------
$sql = "UPDATE users SET name=?, email=?, telephone=?, company_name=?, vat_number=?, newsletter=?";
$params = [$name, $email, $telephone, $company_name, $vat_number, $newsletter];

if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql .= ", password=?";
    $params[] = $hashedPassword;
}

$sql .= " WHERE id=?";
$params[] = $_SESSION['user_id'];

$stmt = $conn->prepare($sql);
$stmt->bind_param(str_repeat("s", count($params)-1) . "i", ...$params); // laatste is i voor id
$stmt->execute();
$stmt->close();

// ------------------------
// Shippingadres updaten/toevoegen/verwijderen
// ------------------------
if ($street && $house_number && $postal_code && $city && $country) {
    $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id=? AND type='shipping' LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->bind_result($shippingId);
    $stmt->fetch();
    $stmt->close();

    if ($shippingId) {
        $stmt = $conn->prepare("
            UPDATE addresses 
            SET street=?, house_number=?, postal_code=?, city=?, country=? 
            WHERE id=? AND user_id=?
        ");
        $stmt->bind_param("ssssssi", $street, $house_number, $postal_code, $city, $country, $shippingId, $_SESSION['user_id']);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO addresses (user_id, type, street, house_number, postal_code, city, country)
            VALUES (?, 'shipping', ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssss", $_SESSION['user_id'], $street, $house_number, $postal_code, $city, $country);
        $stmt->execute();
        $stmt->close();
    }
} else {
    // Shipping adres leeg → verwijderen
    $stmt = $conn->prepare("DELETE FROM addresses WHERE user_id=? AND type='shipping'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// ------------------------
// Factuuradres updaten/verwijderen
// ------------------------
if (!$differentBilling) {
    $stmt = $conn->prepare("DELETE FROM addresses WHERE user_id=? AND type='billing'");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
} else {
    if ($billing_street && $billing_house_number && $billing_postal_code && $billing_city && $billing_country) {
        $stmt = $conn->prepare("SELECT id FROM addresses WHERE user_id=? AND type='billing' LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $stmt->bind_result($billingId);
        $stmt->fetch();
        $stmt->close();

        if ($billingId) {
            $stmt = $conn->prepare("
                UPDATE addresses 
                SET street=?, house_number=?, postal_code=?, city=?, country=? 
                WHERE id=? AND user_id=?
            ");
            $stmt->bind_param("ssssssi", $billing_street, $billing_house_number, $billing_postal_code, $billing_city, $billing_country, $billingId, $_SESSION['user_id']);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                INSERT INTO addresses (user_id, type, street, house_number, postal_code, city, country)
                VALUES (?, 'billing', ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssss", $_SESSION['user_id'], $billing_street, $billing_house_number, $billing_postal_code, $billing_city, $billing_country);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();

header("Location: gegevens.html?success=1");
exit;
?>