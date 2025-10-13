<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php';
require_once 'csrf.php';
csrf_validate();

// Ophalen van onderwerp en bericht uit POST
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$subject || !$message) {
    echo json_encode(['error' => 'Onderwerp of bericht ontbreekt']);
    exit;
}

// Haal e-mails op van gebruikers die de nieuwsbrief ontvangen
$sql = "SELECT email FROM users WHERE newsletter = 1 AND email IS NOT NULL AND email != ''";
$result = $conn->query($sql);

$emails = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = $row['email'];
}
$conn->close();

if (empty($emails)) {
    echo json_encode(['error' => 'Geen nieuwsbriefontvangers gevonden']);
    exit;
}

// === Verstuur via mailing.php ===
$postData = [
    'task' => 'newsletter',
    'emails' => json_encode($emails),
    'subject' => $subject,
    'message' => $message,
    'csrf_token' => $_POST['csrf_token'] ?? ''
];

$context = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($postData)
    ]
]);

$response = file_get_contents('mailing.php', false, $context);

if ($response === false) {
    echo json_encode(['error' => 'Verbinding met mailing.php mislukt']);
    exit;
}

echo $response;
exit;
?>