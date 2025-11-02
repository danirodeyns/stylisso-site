<?php 
session_start();
include 'translations.php';
include 'csrf.php';
include 'mailing.php'; // Het bestand waar we de mailfunctie definieren

csrf_validate(); // stopt script als token fout is

header('Content-Type: application/json'); // JSON response

// Formuliervelden ophalen
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$beoordeling = isset($_POST['beoordeling']) ? trim($_POST['beoordeling']) : '';
$review = isset($_POST['review']) ? trim($_POST['review']) : '';
$subject ="Nieuwe review van $name";

// Validatie
if (empty($name) || empty($beoordeling) || empty($review)) {
    echo json_encode(["error" => "Vul alle velden in."]);
    exit;
}

// Verstuur de e-mail via de functie sendContactEmail
    if (sendReviewsEmail($name, $beoordeling, $review, $subject)) {
        // Succesbericht
        $response = [
            'success' => true,
            'message' => t('review_success')  // Succesbericht
        ];
    } else {
        // Foutbericht
        $response = [
            'success' => false,
            'error' => t('review_error')  // Foutbericht
        ];
    }

// Verzend de reactie naar de client
echo json_encode($response);
exit;
?>