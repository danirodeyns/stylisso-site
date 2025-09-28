<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

include 'db_connect.php';
include 'translations.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Database verbinding mislukt: " . $e->getMessage());
}

function cleanInput($data) {
    return htmlspecialchars(trim($data));
}

// ------------------------
// Functie om land te normaliseren (accents en hoofdletters negeren)
// ------------------------
function normalizeCountry($str) {
    $str = trim($str);
    $str = mb_strtolower($str, 'UTF-8');                  // kleine letters
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);      // accenten verwijderen
    $str = preg_replace('/[^a-z]/', '', $str);           // niet-letters verwijderen
    return $str;
}

// Toegestane landen
$allowedCountries = ['belgie', 'belgium', 'belgique'];

// ------------------------
// Basisgegevens ophalen
// ------------------------
$name = cleanInput($_POST['name'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
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

// Check of e-mail al bestaat bij andere gebruiker
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
$stmt->execute([':email' => $email, ':id' => $_SESSION['user_id']]);
if ($stmt->fetch()) {
    $errors[] = 'email_exists';
}

// Check of nieuw wachtwoord anders is dan huidige
if (!empty($password)) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if ($currentUser && password_verify($password, $currentUser['password'])) {
        $errors[] = 'password_same';
    }
}

// ------------------------
// Land validatie server-side
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

// Stop bij fouten
if (!empty($errors)) {
    header("Location: gegevens.html?success=0&errors=" . implode(',', $errors));
    exit;
}

// ------------------------
// Users tabel bijwerken
// ------------------------
$params = [
    ':name'         => $name,
    ':email'        => $email,
    ':company_name' => $company_name,
    ':vat_number'   => $vat_number,
    ':newsletter'   => $newsletter,
    ':id'           => $_SESSION['user_id']
];

$sql = "UPDATE users 
        SET name = :name, email = :email, company_name = :company_name, vat_number = :vat_number, newsletter = :newsletter";

if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql .= ", password = :password";
    $params[':password'] = $hashedPassword;
}

$sql .= " WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// ------------------------
// Shippingadres updaten/toevoegen
// ------------------------
if ($street && $house_number && $postal_code && $city && $country) {
    $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = :user_id AND type = 'shipping' LIMIT 1");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $shippingAddress = $stmt->fetch();

    if ($shippingAddress) {
        $stmt = $pdo->prepare("
            UPDATE addresses 
            SET street = :street, house_number = :house_number, postal_code = :postal_code, city = :city, country = :country
            WHERE id = :address_id AND user_id = :user_id
        ");
        $stmt->execute([
            ':street'       => $street,
            ':house_number' => $house_number,
            ':postal_code'  => $postal_code,
            ':city'         => $city,
            ':country'      => $country,
            ':address_id'   => $shippingAddress['id'],
            ':user_id'      => $_SESSION['user_id']
        ]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO addresses (user_id, type, street, house_number, postal_code, city, country)
            VALUES (:user_id, 'shipping', :street, :house_number, :postal_code, :city, :country)
        ");
        $stmt->execute([
            ':user_id'      => $_SESSION['user_id'],
            ':street'       => $street,
            ':house_number' => $house_number,
            ':postal_code'  => $postal_code,
            ':city'         => $city,
            ':country'      => $country
        ]);
    }
}

// ------------------------
// Factuuradres updaten/verwijderen
// ------------------------
if (!$differentBilling) {
    // Verwijder eventueel bestaand billingadres
    $stmt = $pdo->prepare("DELETE FROM addresses WHERE user_id = :user_id AND type = 'billing'");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
} else {
    if ($billing_street && $billing_house_number && $billing_postal_code && $billing_city && $billing_country) {
        $stmt = $pdo->prepare("SELECT id FROM addresses WHERE user_id = :user_id AND type = 'billing' LIMIT 1");
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $billingAddress = $stmt->fetch();

        if ($billingAddress) {
            $stmt = $pdo->prepare("
                UPDATE addresses 
                SET street = :street, house_number = :house_number, postal_code = :postal_code, city = :city, country = :country
                WHERE id = :address_id AND user_id = :user_id
            ");
            $stmt->execute([
                ':street'       => $billing_street,
                ':house_number' => $billing_house_number,
                ':postal_code'  => $billing_postal_code,
                ':city'         => $billing_city,
                ':country'      => $billing_country,
                ':address_id'   => $billingAddress['id'],
                ':user_id'      => $_SESSION['user_id']
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO addresses (user_id, type, street, house_number, postal_code, city, country)
                VALUES (:user_id, 'billing', :street, :house_number, :postal_code, :city, :country)
            ");
            $stmt->execute([
                ':user_id'          => $_SESSION['user_id'],
                ':street'           => $billing_street,
                ':house_number'     => $billing_house_number,
                ':postal_code'      => $billing_postal_code,
                ':city'             => $billing_city,
                ':country'          => $billing_country
            ]);
        }
    }
}

header("Location: gegevens.html?success=1");
exit;
?>