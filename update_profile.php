<?php
session_start();

// Controleer of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

// Database verbinding
include 'db_connect.php';

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

// Functie om input te sanitiseren
function cleanInput($data) {
    return htmlspecialchars(trim($data));
}

// Ontvang POST-data
$email = cleanInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['passwordConfirm'] ?? '';

// Validatie
$errors = [];

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Een geldig e-mailadres is verplicht.';
}
if ($password !== $passwordConfirm) {
    $errors[] = 'Wachtwoorden komen niet overeen.';
}

if (count($errors) > 0) {
    foreach ($errors as $error) {
        echo "<p style='color:red;'>$error</p>";
    }
    echo "<p><a href='profielinstellingen.html'>Ga terug</a></p>";
    exit;
}

// Basis parameters
$params = [
    ':email' => $email,
    ':id'    => $_SESSION['user_id']
];

// SQL opbouwen
$sql = "UPDATE users SET email = :email";

if (!empty($password)) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $sql .= ", password = :password";
    $params[':password'] = $hashedPassword;
}

$sql .= " WHERE id = :id";

$stmt = $pdo->prepare($sql);

try {
    $stmt->execute($params);
    echo "<p style='color:green;'>Profiel succesvol bijgewerkt.</p>";
    echo "<p><a href='profielinstellingen.html'>Terug naar profielinstellingen</a></p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Fout bij bijwerken profiel: " . $e->getMessage() . "</p>";
    echo "<p><a href='profielinstellingen.html'>Ga terug</a></p>";
}
?>