<?php
require 'config.php';
header('Content-Type: application/json');
session_start();

// --- Vul POST aan vanuit DB als het niet beschikbaar is ---
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT u.name, u.email, u.telephone, u.company_name, u.vat_number,
            s.street AS shipping_street, s.house_number AS shipping_house_number, 
            s.postal_code AS shipping_postal_code, s.city AS shipping_city, s.country AS shipping_country,
            b.street AS billing_street, b.house_number AS billing_house_number,
            b.postal_code AS billing_postal_code, b.city AS billing_city, b.country AS billing_country
        FROM users u
        LEFT JOIN addresses s ON u.id = s.user_id AND s.type = 'shipping'
        LEFT JOIN addresses b ON u.id = b.user_id AND b.type = 'billing'
        WHERE u.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Vul POST automatisch aan als leeg
    $_POST['name']         = $_POST['name'] ?? $userData['name'];
    $_POST['email']        = $_POST['email'] ?? $userData['email'];
    $_POST['telephone']    = $_POST['telephone'] ?? $userData['telephone'];
    $_POST['address']      = $_POST['address'] ?? $userData['shipping_street'];
    $_POST['house_number'] = $_POST['house_number'] ?? $userData['shipping_house_number'];
    $_POST['city']         = $_POST['city'] ?? $userData['shipping_city'];
    $_POST['postal']       = $_POST['postal'] ?? $userData['shipping_postal_code'];
    $_POST['country']      = $_POST['country'] ?? $userData['shipping_country'] ?? 'België';
}

function normalizePhoneForPaypal($tel) {
    $tel = trim($tel);
    $tel = preg_replace('/[^\d]/', '', $tel); // alleen cijfers behouden

    // Als nummer in internationaal formaat begint met 32 (België), verwijder 32
    if (strpos($tel, '32') === 0) {
        $tel = substr($tel, 2);
    } elseif (strpos($tel, '0') === 0) {
        // nummer begint met 0, laat zo (Belgisch lokaal nummer)
        $tel = $tel;
    }
    // anders: nummer wordt gebruikt zoals het is
    return $tel;
}

// --- Hulpfunctie: cURL request naar PayPal ---
function paypalRequest($method, $endpoint, $data = null, $accessToken = null) {
    $url = "https://api-m.sandbox.paypal.com" . $endpoint; // LIVE: api-m.paypal.com
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        $accessToken ? "Authorization: Bearer $accessToken" : ""
    ]);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }
    curl_close($ch);
    return json_decode($response, true);
}

// --- Token ophalen ---
function getAccessToken() {
    $ch = curl_init("https://api-m.sandbox.paypal.com/v1/oauth2/token"); // LIVE: api-m.paypal.com
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return ['error' => curl_error($ch)];
    }
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

$action = $_POST['action'] ?? null;
$cartTotal = $_POST['total'] ?? 0;

// Extra klantgegevens (optioneel, komen van je checkoutformulier via JS)
$firstName   = $_POST['first_name'] ?? '';
$lastName    = $_POST['last_name'] ?? '';
$email       = $_POST['email'] ?? '';
$phone       = $_POST['telephone'] ?? '';
$phonePaypal = normalizePhoneForPaypal($phone);
$address     = $_POST['address'] ?? '';
$city        = $_POST['city'] ?? '';
$postal      = $_POST['postal'] ?? '';
$country     = $_POST['country'] ?? 'BE'; // standaard België

if (!$action) {
    echo json_encode(['error' => 'Geen actie opgegeven']);
    exit;
}

$accessToken = getAccessToken();
if (!$accessToken) {
    echo json_encode(['error' => 'Kon geen access token ophalen']);
    exit;
}

switch($action) {

    // --- ORDER MAKEN ---
    case 'create':
        $orderData = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "amount" => [
                    "currency_code" => "EUR",
                    "value" => number_format((float)$cartTotal, 2, '.', '')
                ],
                "shipping" => [
                    "name" => [
                        "full_name" => trim("$firstName $lastName")
                    ],
                    "address" => [
                        "address_line_1" => $address,
                        "admin_area_2" => $city,
                        "postal_code" => $postal,
                        "country_code" => strtoupper($country)
                    ]
                ]
            ]],
            "payer" => [
                "name" => [
                    "given_name" => $firstName,
                    "surname" => $lastName
                ],
                "email_address" => $email,
                "phone" => [
                    "phone_type" => "MOBILE",
                    "phone_number" => [
                        "national_number" => $phonePaypal
                    ]
                ],
                "address" => [
                    "address_line_1" => $address,
                    "admin_area_2" => $city,
                    "postal_code" => $postal,
                    "country_code" => strtoupper($country)
                ]
            ],
            "application_context" => [
                "shipping_preference" => "SET_PROVIDED_ADDRESS",
                "user_action" => "PAY_NOW",
                "brand_name" => "Stylisso"
            ]
        ];

        $order = paypalRequest('POST', '/v2/checkout/orders', $orderData, $accessToken);
        echo json_encode($order);
        exit;

    // --- ORDER CAPTUREN ---
    case 'capture':
        $orderID = $_POST['orderID'] ?? null;
        if (!$orderID) {
            echo json_encode(['error' => 'Geen orderID opgegeven']);
            exit;
        }

        $capture = paypalRequest('POST', "/v2/checkout/orders/$orderID/capture", null, $accessToken);
        echo json_encode($capture);
        exit;

    default:
        echo json_encode(['error' => 'Onbekende actie']);
        exit;
}
?>