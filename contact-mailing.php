<?php
include 'csrf.php';
include 'translations.php';
include 'mailing.php';

csrf_validate();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verkrijg de formuliervelden
    $name    = htmlspecialchars($_POST['name']);
    $email   = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    // Verstuur de e-mail via de functie sendContactEmail
    if (sendContactEmail($name, $email, $subject, $message)) {
        // Succesbericht
        $response = [
            'success' => true,
            'message' => t('contact_form_success')  // Succesbericht
        ];
    } else {
        // Foutbericht
        $response = [
            'success' => false,
            'error' => t('contact_form_error')  // Foutbericht
        ];
    }

    // Verstuur JSON-antwoord naar de client
    echo json_encode($response);
}
?>