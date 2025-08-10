<?php
// register.php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Veilig ophalen van POST-waarden
    $name = isset($_POST['register-name']) ? trim($_POST['register-name']) : '';
    $email = isset($_POST['register-email']) ? trim($_POST['register-email']) : '';
    $password_raw = isset($_POST['register-password']) ? $_POST['register-password'] : '';
    $password2_raw = isset($_POST['register-password2']) ? $_POST['register-password2'] : '';
    $address = ''; // leeg bij registratie

    // Simpele validatie
    if (empty($name) || empty($email) || empty($password_raw) || empty($password2_raw)) {
        echo json_encode(['success' => false, 'error' => 'Vul alle velden in.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Ongeldig e-mailadres.']);
        exit;
    }

    if ($password_raw !== $password2_raw) {
        echo json_encode(['success' => false, 'error' => 'Wachtwoorden komen niet overeen.']);
        exit;
    }

    // Check of email al bestaat
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database fout: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Email bestaat al.']);
        exit;
    }

    // Hash het wachtwoord
    $password = password_hash($password_raw, PASSWORD_DEFAULT);

    // Insert nieuwe gebruiker
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, address) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Database fout: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssss", $name, $email, $password, $address);

    if ($stmt->execute()) {
        // Direct inloggen na registratie
        $_SESSION['user_id'] = $conn->insert_id;
        $_SESSION['user_name'] = $name;

        echo json_encode(['success' => true]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Fout bij registratie: ' . $conn->error]);
        exit;
    }
} else {
    // Geen POST request
    echo json_encode(['success' => false, 'error' => 'Ongeldige aanvraag.']);
    exit;
}
?>