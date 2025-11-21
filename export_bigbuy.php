<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bigbuy.php';

$api = new BigBuyAPI();

function logMessage($msg) {
    file_put_contents(
        "/home/stylisso/logs/bigbuy_export.log",
        date("Y-m-d H:i:s") . " - $msg\n",
        FILE_APPEND
    );
}

function debugSmall($title, $data) {
    file_put_contents(
        "/home/stylisso/logs/bigbuy_debug_min.log",
        date("Y-m-d H:i:s") . " [$title] " . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND
    );
}

// ============================================================
// Order naar BigBuy sturen in multishipping formaat.
// ============================================================
function createBigBuyOrder(array $orderData): array {
    global $api;

    $shippingCountry = strtoupper($orderData['shippingAddress']['country'] ?? 'BE');

    // ============================================================
    // 1) SKU en producten voorbereiden
    // ============================================================
    $productsFormatted = [];
    foreach ($orderData['products'] as $p) {
        $productId = intval($p['product_id'] ?? 0);
        $qty = intval($p['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) continue;

        try {
            $res = $api->getProduct($productId);
            $resData = json_decode($res['response'], true);
            $sku = $resData['sku'] ?? null;
            if (!$sku) {
                logMessage("⛔ Geen SKU voor product_id {$productId}");
                continue;
            }
            $productsFormatted[] = [
                'reference' => $sku,
                'quantity'  => $qty
            ];
        } catch (Exception $e) {
            logMessage("⛔ Fout bij ophalen product {$productId}: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    if (empty($productsFormatted)) {
        return ['success' => false, 'message' => 'No valid products'];
    }

    // ============================================================
    // 2) Laagste verzendkosten ophalen via BigBuy API
    // ============================================================
    $payload = [
        'productCountry' => [
            'reference' => $productsFormatted[0]['reference'], // neem eerste product als voorbeeld
            'countryIsoCode' => strtolower($shippingCountry)
        ]
    ];

    try {
        $lowestResp = $api->getLowestShippingCost($payload);
        $lowestData = json_decode($lowestResp['response'], true);
        debugSmall("LowestShipping", $lowestData);

        if (empty($lowestData) || !isset($lowestData['carrier']['name'])) {
            return ['success' => false, 'message' => 'No valid carrier found'];
        }

        $chosenCarrier = $lowestData['carrier']['name'];
        $shippingCost  = $lowestData['shippingCost'];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    // ============================================================
    // 3) Bouw de order voor BigBuy
    // ============================================================
    $zipCode = str_replace(' ', '', ($orderData['shippingAddress']['zipCode'] ?? ''));
    $bigBuyResponse = [
        'order' => [
            'internalReference' => (string)$orderData['reference'],
            'language' => $orderData['customer']['language'] ?? 'en',
            'paymentMethod' => 'paypal',
            'carriers' => [
                ['name' => strtolower($chosenCarrier)]
            ],
            'shippingAddress' => [
                'firstName' => $orderData['customer']['firstName'] ?? '',
                'lastName'  => $orderData['customer']['lastName'] ?? '',
                'country'   => $shippingCountry,
                'postcode'  => $zipCode,
                'town'      => $orderData['shippingAddress']['city'] ?? '',
                'address'   => trim(($orderData['shippingAddress']['street'] ?? '') . ' ' . ($orderData['shippingAddress']['houseNumber'] ?? '')),
                'phone'     => $orderData['customer']['phone'] ?? '',
                'email'     => $orderData['customer']['email'] ?? '',
                'vatNumber'   => $orderData['customer']['vatNumber'] ?? '',
                'companyName' => $orderData['customer']['companyName'] ?? '',
                'comment'  => $orderData['comment'] ?? ''
            ],
            'products' => $productsFormatted
        ]
    ];

    logMessage("DEBUG verzenden naar BigBuy: " . json_encode($bigBuyResponse, JSON_PRETTY_PRINT));

    // ============================================================
    // 4) Stuur order naar BigBuy
    // ============================================================

}
?>