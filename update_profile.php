<?php
session_start();

// Controleer of gebruiker ingelogd is
if (!isset($_SESSION['user_id'])) {
    header('Location: login_registreren.html');
    exit;
}

// Database verbinding (pas aan naar jouw config)
include 'db_connect.php';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database verbinding mislukt: " . $e->getMessage());
}

// Functie om input te sanitiseren
function cleanInput($data) {
    return htmlspecialchars(trim($data));
}

// Ontvang POST-data
$username = cleanInput($_POST['username'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['passwordConfirm'] ?? '';

// Validatie simpel voorbeeld
$errors = [];

if (empty($username)) {
    $errors[] = 'Gebruikersnaam is verplicht.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Een geldig e-mailadres is verplicht.';
}
if ($password !== $passwordConfirm) {
    $errors[] = 'Wachtwoorden komen niet overeen.';
}

if (count($errors) > 0) {
    // Simpel terug met foutmeldingen, dit kan beter met session of redirect + flash messages
    foreach ($errors as $error) {
        echo "<p style='color:red;'>$error</p>";
    }
    echo "<p><a href='profielinstellingen.html'>Ga terug</a></p>";
    exit;
}

// Update statement bouwen
$params = [
    ':username' => $username,
    ':email' => $email,
    ':id' => $_SESSION['user_id']
];

$sql = "UPDATE users SET username = :username, email = :email";

if (!empty($password)) {
    // Wachtwoord hashen
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