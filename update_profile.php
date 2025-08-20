<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

include 'db_connect.php';
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

$name = cleanInput($_POST['name'] ?? '');
$address = cleanInput($_POST['address'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$company_name = cleanInput($_POST['company_name'] ?? '');
$vat_number = cleanInput($_POST['vat_number'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['passwordConfirm'] ?? '';
$newsletter = isset($_POST['newsletter']) ? 1 : 0;

$errors = [];

// Validaties
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

// Als er een wachtwoord is ingevoerd → check tegen huidige
if (!empty($password)) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $currentUser = $stmt->fetch();

    if ($currentUser && password_verify($password, $currentUser['password'])) {
        $errors[] = 'password_same';
    }
}

if (!empty($errors)) {
    header("Location: gegevens.html?success=0&errors=" . implode(',', $errors));
    exit;
}

// Update uitvoeren
$params = [
    ':name'         => $name,
    ':address'      => $address,
    ':email'        => $email,
    ':company_name' => $company_name,
    ':vat_number'   => $vat_number,
    ':newsletter'   => $newsletter,
    ':id'           => $_SESSION['user_id']
];

$sql = "UPDATE users 
        SET name = :name, address = :address, email = :email, company_name = :company_name, vat_number = :vat_number, newsletter = :newsletter";

if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql .= ", password = :password";
    $params[':password'] = $hashedPassword;
}

$sql .= " WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Success terugsturen
header("Location: gegevens.html?success=1");
exit;
?>