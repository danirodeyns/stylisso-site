<?php
session_start();
require 'db_connect.php';
include 'csrf.php';
csrf_validate(); // stopt script als token fout is

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Haal gegevens op en filter
    $amount = floatval($_POST['amount']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if ($amount < 5) {
        die("Ongeldig bedrag. Kies een bedrag van minimaal €5.");
    }

    if (!$email) {
        die("Ongeldig e-mailadres.");
    }

    // Genereer unieke boncode (8 karakters)
    $code = strtoupper(bin2hex(random_bytes(4)));

    // Optioneel: vervaldatum instellen (bijv. 1 jaar geldig)
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 year'));

    // Voeg toe aan vouchers tabel
    $stmt = $conn->prepare("INSERT INTO vouchers (code, value, remaining_value, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdds", $code, $amount, $amount, $expires_at);
    $stmt->execute();

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