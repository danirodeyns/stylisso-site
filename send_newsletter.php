<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'csrf.php';
include 'translations.php';
include 'mailing.php';

// --- CSRF-validatie ---
try {
    csrf_validate();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

// --- Ophalen van onderwerp en bericht uit POST ---
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Onderwerp of bericht ontbreekt']);
    exit;
}

// --- Haal e-mails op van gebruikers die de nieuwsbrief ontvangen ---
$sql = "SELECT id, email FROM users WHERE newsletter = 1 AND email IS NOT NULL AND email != ''";
$result = $conn->query($sql);

$emails = [];
$users = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = $row['email'];
    $users[$row['email']] = $row['id'];  // Sla de user ID op per e-mail
}

// --- Haal de taal van de laatste bestelling voor elke gebruiker ---
$userLangs = [];
foreach ($users as $email => $userId) {
    $sqlLang = "SELECT taal FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmtLang = $conn->prepare($sqlLang);
    $stmtLang->bind_param("i", $userId);
    $stmtLang->execute();
    $resultLang = $stmtLang->get_result();
    $langRow = $resultLang->fetch_assoc();

    // Als er een taal is gevonden, gebruik die; anders gebruik 'be-nl' als fallback
    $userLangs[$email] = $langRow['taal'] ?? 'be-nl';
    $stmtLang->close();
}

$conn->close();

if (empty($emails)) {
    echo json_encode(['success' => false, 'error' => 'Geen nieuwsbriefontvangers gevonden']);
    exit;
}

// --- Verstuur via mailing.php ---
$response = sendNewsletter($emails, $subject, $message, $userLangs);

// --- Controleer of het antwoord geldig is ---
if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Verbinding met mailing.php mislukt']);
    exit;
}

// --- Response verwerken ---
if (is_array($response)) {
    $sent = $response['sent'] ?? 0;
    $total = $response['total'] ?? count($emails);
    echo json_encode([
        'success' => $sent === $total,
        'sent' => $sent,
        'total' => $total,
        'languages' => $userLangs,
        'message' => $sent === $total ? 'Nieuwsbrief verzonden' : 'Niet alle e-mails konden worden verzonden'
    ]);
} else {
    echo json_encode(['success' => true, 'message' => 'Nieuwsbrief verzonden']);
}

exit;
?>