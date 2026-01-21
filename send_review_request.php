<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/mailing.php';
require_once __DIR__ . '/translations.php';

echo "=== REVIEW MAIL CRON START ===\n";

// -------------------------------------------------
// Selecteer bestellingen van 7 tot 8 dagen oud
// -------------------------------------------------
$sql = "
    SELECT 
        o.id AS order_id,
        o.taal,
        u.email,
        u.name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE 
        u.newsletter = 1
        AND o.status <> 'cancelled'
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 8 DAY)
        AND o.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
";

$result = $conn->query($sql);

if (!$result) {
    die("DB ERROR: " . $conn->error);
}

if ($result->num_rows === 0) {
    echo "Geen review-mails te verzenden.\n";
    exit;
}

while ($row = $result->fetch_assoc()) {

    $orderId = (int)$row['order_id'];
    $email   = $row['email'];
    $name    = $row['name'];
    $lang    = $row['taal'] ?? 'be-nl';

    // -------------------------------------------------
    // Mail verzenden via bestaande mailing functie
    // -------------------------------------------------
    $sent = sendReviewMail($email, $name, $orderId, $lang);

    if ($sent) {
        echo "Mail verzonden naar $email\n";
    } else {
        echo "Mail niet verzonden naar $email\n";
    }
}

echo "=== REVIEW MAIL CRON EINDE ===\n";
?>