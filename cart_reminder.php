<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/mailing.php';
require __DIR__ . '/translations.php';

function logMessage($msg) {
    file_put_contents("/home/stylisso/logs/cart_reminder.log", date("Y-m-d H:i:s") . " - $msg\n", FILE_APPEND);
}

// --- 1) Bepaal tijdvenster: 48 tot 72 uur geleden ---
$olderThan = (new DateTime('-48 hours'))->format('Y-m-d H:i:s'); // 48h geleden
$newerThan = (new DateTime('-72 hours'))->format('Y-m-d H:i:s'); // 72h geleden
logMessage("Tijdvenster voor reminders: $newerThan tot $olderThan");

// --- 2) Vind gebruikers met items in de cart binnen dat venster ---
$sql = "
    SELECT user_id, COUNT(*) AS item_count
    FROM cart
    WHERE created_at BETWEEN ? AND ?
    GROUP BY user_id
    HAVING item_count > 0
";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    logMessage("❌ Fout bij voorbereiden van SQL: " . $conn->error);
    die();
}
$stmt->bind_param("ss", $newerThan, $olderThan);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logMessage("⚠️ Geen gebruikers gevonden in het tijdvenster.");
}

while ($row = $result->fetch_assoc()) {
    $userId = $row['user_id'];
    $itemCount = $row['item_count'];
    logMessage("-- Gebruiker $userId heeft $itemCount item(s) in cart --");

    // --- 3) Haal user email en naam op ---
    $stmtUser = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userResult = $stmtUser->get_result();
    if (!$user = $userResult->fetch_assoc()) {
        logMessage("❌ Geen gebruiker gevonden voor ID $userId");
        continue;
    }
    $userEmail = $user['email'];
    $userName = $user['name'];

    // --- 3b) Haal taal van de laatste order op ---
    $stmtLang = $conn->prepare("
        SELECT taal 
        FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmtLang->bind_param("i", $userId);
    $stmtLang->execute();
    $langResult = $stmtLang->get_result();
    $langRow = $langResult->fetch_assoc();
    $lang = $langRow['taal'] ?? 'be-nl';

    logMessage("Gebruiker email: $userEmail | Naam: $userName | Taal: $lang");

    // --- 4) Haal de cart-items op ---
    $stmtCart = $conn->prepare("
        SELECT p.name, c.quantity, c.maat 
        FROM cart c 
        LEFT JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmtCart->bind_param("i", $userId);
    $stmtCart->execute();
    $cartResult = $stmtCart->get_result();

    // Maak een array van items
    $itemsList = [];
    while ($item = $cartResult->fetch_assoc()) {
        $itemsList[] = [
            'product_name' => $item['name'],
            'quantity' => $item['quantity'],
            'size' => $item['maat'] ?? '-'
        ];
    }

    // Controleer of er items zijn
    if (empty($itemsList)) {
        logMessage("⚠️ Geen items gevonden in de cart voor gebruiker $userId");
        continue;
    }

    logMessage("Items in cart:");
    foreach ($itemsList as $i) {
        logMessage(" - {$i['quantity']}x {$i['product_name']}" . (!empty($i['size']) ? " (Maat: {$i['size']})" : ""));
    }

    // --- 5) Verstuur de herinneringsmail ---
    $sent = sendCartReminder($userEmail, $userName, $itemsList, $lang);

    if ($sent) {
        logMessage("✔ Mail succesvol verstuurd naar $userEmail");
    } else {
        logMessage("❌ Mail NIET verstuurd naar $userEmail");
    }
}

logMessage("=== Cron voltooid ===");
?>