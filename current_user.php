<?php
session_start();
include 'db_connect.php';
include 'translations.php';

header('Content-Type: application/json');

$response = ['loggedIn' => false];

// =============================
// 1. Controle: sessie actief?
// =============================
if (isset($_SESSION['user_id'])) {
    $response['loggedIn'] = true;
    $response['userName'] = $_SESSION['user_name'];
    echo json_encode($response);
    exit;
}

// =============================
// 2. Controle: remember cookie?
// =============================
if (!isset($_COOKIE['user_login'])) {
    echo json_encode($response);
    exit;
}

// Cookie decoderen
$cookie = json_decode($_COOKIE['user_login'], true);

if (!isset($cookie['user_id']) || !isset($cookie['token'])) {
    echo json_encode($response);
    exit;
}

$user_id = $cookie['user_id'];
$token   = $cookie['token'];

// =============================
// 3. Token opzoeken in user_tokens
// =============================
$stmt = $conn->prepare("
    SELECT t.expires_at, u.name 
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

// =============================
// 4. Token verlopen?
// =============================
if ($data['expires_at'] !== null && strtotime($data['expires_at']) < time()) {

    // Token is verlopen → verwijderen
    $del = $conn->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token = ?");
    $del->bind_param("is", $user_id, $token);
    $del->execute();

    echo json_encode($response);
    exit;
}

// =============================
// 5. Token is geldig → login sessie
// =============================
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $data['name'];

$response['loggedIn'] = true;
$response['userName'] = $data['name'];

echo json_encode($response);
?>