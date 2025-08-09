<?php
// Databaseverbinding (pas aan naar jouw gegevens)
$host = "localhost";
$dbname = "stylisso_db";
$username = "root";
$password = "";
$conn = new mysqli($host, $username, $password, $dbname);

// Controleer verbinding
if ($conn->connect_error) {
    die("Databaseverbinding mislukt: " . $conn->connect_error);
}

// Formulierverwerking
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Ongeldig e-mailadres.");
    }

    // Controleer of e-mailadres bestaat
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Dit e-mailadres is niet bij ons bekend.";
        exit;
    }

    // Genereer reset token en verloopdatum
    $token = bin2hex(random_bytes(32));
    $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

    // Sla token op in database
    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
    $stmt->bind_param("sss", $token, $expires, $email);
    $stmt->execute();

    // Maak resetlink
    $resetLink = "https://jouwsite.be/reset_password.php?token=" . $token;

    // Stuur e-mail
    $to = $email;
    $subject = "Wachtwoord resetten - Stylisso";
    $message = "Hallo,\n\nKlik op onderstaande link om je wachtwoord te resetten:\n$resetLink\n\nDeze link verloopt over 1 uur.";
    $headers = "From: klantendienst@stylisso.be\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo "Er is een e-mail verstuurd met instructies om je wachtwoord te resetten.";
    } else {
        echo "Er ging iets mis bij het verzenden van de e-mail.";
    }
}
?>