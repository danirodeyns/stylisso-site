<?php
session_start();
include 'db_connect.php';
include 'translations.php';

header('Content-Type: application/json');

$response = ['loggedIn' => false];

// ================================================
// 1. Als er een actieve sessie is → last_used ook updaten
// ================================================
if (isset($_SESSION['user_id']) && isset($_COOKIE['user_login'])) {

    // cookie uitlezen
    $cookie = json_decode($_COOKIE['user_login'], true);

    if (isset($cookie['id'], $cookie['token'])) {
        $user_id = $cookie['id'];
        $token   = $cookie['token'];

        // last_used bijwerken
        $update = $conn->prepare("UPDATE user_tokens SET last_used = NOW() WHERE user_id = ? AND token = ?");
        $update->bind_param("is", $user_id, $token);
        $update->execute();
    }

    // bestaande sessie resultaat teruggeven
    $response['loggedIn'] = true;
    $response['userName'] = $_SESSION['user_name'];
    echo json_encode($response);
    exit;
}

// ================================================
// 2. Controle: remember cookie?
// ================================================
if (!isset($_COOKIE['user_login'])) {
    echo json_encode($response);
    exit;
}

// Cookie decoderen
$cookie = json_decode($_COOKIE['user_login'], true);

if (!isset($cookie['id']) || !isset($cookie['token'])) {
    echo json_encode($response);
    exit;
}

$user_id = $cookie['id'];
$token   = $cookie['token'];

// ================================================
// 3. Token opzoeken
// ================================================
$stmt = $conn->prepare("
    SELECT t.expires_at, t.last_used, u.name 
    FROM user_tokens t
    JOIN users u ON u.id = t.user_id
    WHERE t.user_id = ? AND t.token = ?
");
$stmt->bind_param("is", $user_id, $token);
$stmt->execute();
$res = $stmt->get_result();

// Token niet gevonden → ongeldig
if ($res->num_rows !== 1) {
    echo json_encode($response);
    exit;
}

$data = $res->fetch_assoc();

// ================================================
// 4. Token valideren met nieuwe regels
// ================================================
$now = time();

// Timestamps berekenen
$lastUsedTs = strtotime($data['last_used']);
$expiresAtTs = strtotime($data['expires_at']);

// Regels:
// 1) last_used > 1 maand geleden  → ongeldig
// 2) expires_at ligt in het verleden → ongeldig

$oneMonthAgo = strtotime("-1 month");

$tokenInvalid = false;

// 1. Last_used check
if ($lastUsedTs < $oneMonthAgo) {
    $tokenInvalid = true;
}

// 2. Expires check
if ($expiresAtTs !== false && $expiresAtTs < $now) {
    $tokenInvalid = true;
}

if ($tokenInvalid) {
    // Token verwijderen
    $del = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token = ?");
    $del->bind_param("is", $user_id, $token);
    $del->execute();

    // Eventueel sessie verwijderen
    unset($_SESSION['user_id']);

    echo json_encode(["loggedIn" => false, "reason" => "token_expired"]);
    exit;
}

// ================================================
// 5. Token geldig → sessie aanmaken
// ================================================
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $data['name'];

// last_used bijwerken
$update = $conn->prepare("UPDATE user_tokens SET last_used = NOW() WHERE user_id = ? AND token = ?");
$update->bind_param("is", $user_id, $token);
$update->execute();

$response['loggedIn'] = true;
$response['userName'] = $data['name'];

echo json_encode($response);
?>