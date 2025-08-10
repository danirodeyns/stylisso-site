<?php
// login.php
session_start();
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['login-email'] ?? '';
    $password = $_POST['login-password'] ?? '';

    if (empty($email) || empty($password)) {
        echo "Vul zowel e-mail als wachtwoord in.";
        exit;
    }

    // Prepared statement voor veiligheid
    $stmt = $conn->prepare("SELECT id, password, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            echo "Inloggen gelukt!";
            // header("Location: dashboard.php"); // Optioneel redirecten
            exit;
        } else {
            echo "Verkeerd wachtwoord.";
            exit;
        }
    } else {
        echo "E-mail niet gevonden.";
        exit;
    }
}
?>

<form method="post">
    Email: <input type="email" name="login-email" required><br>
    Wachtwoord: <input type="password" name="login-password" required><br>
    <button type="submit">Inloggen</button>
</form>