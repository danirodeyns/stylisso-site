<?php
session_start();
require 'db_connect.php'; // PDO connectie

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Haal gegevens op en filter
    $amount = floatval($_POST['amount']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($amount < 5 || $amount > 500) {
        die("Ongeldig bedrag. Kies een bedrag tussen €5 en €500.");
    }

    if (!$email) {
        die("Ongeldig e-mailadres.");
    }

    // Genereer unieke boncode (8 karakters)
    $code = strtoupper(bin2hex(random_bytes(4)));

    // Optioneel: vervaldatum instellen (bijv. 1 jaar geldig)
    $expires_at = date('Y-m-d', strtotime('+1 year'));

    // Voeg toe aan vouchers tabel
    $stmt = $pdo->prepare("INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (:code, :value, :remaining_value, :expires_at)");
    $stmt->execute([
        ':code' => $code,
        ':value' => $amount,
        ':remaining_value' => $amount,
        ':expires_at' => $expires_at
    ]);

    // Mail de boncode naar de gebruiker
    $subject = "Jouw Stylisso cadeaubon";
    $message = "Bedankt voor je aankoop!\n\nJe cadeauboncode is: $code\nWaarde: €$amount\n\nGeldig tot: $expires_at";
    $headers = "From: no-reply@stylisso.com";

    if (mail($email, $subject, $message, $headers)) {
        echo "Cadeaubon aangemaakt en verzonden naar $email!";
    } else {
        echo "Cadeaubon aangemaakt, maar er is een probleem met het verzenden van de e-mail.";
    }
}
?>