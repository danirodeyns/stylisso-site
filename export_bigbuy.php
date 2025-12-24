<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bigbuy.php';
require __DIR__ . '/mailing.php';
require __DIR__ . '/db_connect.php';
$conn->set_charset("utf8mb4");

$api = new BigBuyAPI();

// ============================================================
//Helpers
// ============================================================
function logMessage($msg) {
    file_put_contents(
        "/home/stylisso/logs/bigbuy_export.log",
        date("Y-m-d H:i:s") . " - $msg\n",
        FILE_APPEND
    );
}

// ============================================================
// 1) Pending orders ophalen
// ============================================================
$orders = [];

$stmt = $conn->prepare("
    SELECT 
        o.id AS order_id, o.user_id, o.taal,
        u.name, u.email, u.telephone, u.company_name, u.vat_number,
        a.street, a.house_number, a.postal_code, a.city, a.country
    FROM orders o
    JOIN users u ON u.id = o.user_id
    JOIN addresses a ON a.user_id = u.id AND a.type = 'shipping'
    WHERE o.status = 'pending'
    ORDER BY o.created_at ASC
");
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $orders[$row['order_id']] = $row;
}

if (empty($orders)) {
    logMessage("Geen pending orders");
    exit;
}

// ============================================================
// 2) Order items ophalen (nog aanpassen enkel als order items niet voucher)
// ============================================================
$orderItems = [];

$stmt = $conn->prepare("
    SELECT order_id, product_id, quantity, type, maat 
    FROM order_items 
    WHERE order_id IN (" . implode(',', array_keys($orders)) . ")
");
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $orderItems[$row['order_id']][] = $row;
}

// ============================================================
// 3) Eénmalig taxonomies ophalen
// ============================================================
$taxonomies        = json_decode($api->getTaxonomies()['response'], true) ?: [];
$firstLevelTax     = json_decode($api->getTaxonomiesFirstLevel()['response'], true) ?: [];
$firstLevelIds     = array_column($firstLevelTax, 'id');

// ============================================================
// 4) Orders verwerken
// ============================================================
foreach ($orders as $orderId => $order) {

    logMessage("▶ START order {$orderId}");

    if (!isset($orderItems[$orderId]) || empty($orderItems[$orderId])) {

        logMessage("⚠ Order {$orderId} heeft geen items → status naar paid");

        $stmtUpdate = $conn->prepare("
            UPDATE orders 
            SET status = 'paid'
            WHERE id = ?
        ");
        $stmtUpdate->bind_param("i", $orderId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        continue;
    }

    // ============================================
    // Check: zijn er product-items?
    // ============================================
    $hasProductItems = false;

    foreach ($orderItems[$orderId] as $item) {
        if ($item['type'] === 'product') {
            $hasProductItems = true;
            break;
        }
    }

    if (!$hasProductItems) {

        logMessage("⚠ Order {$orderId} bevat geen product-items (alleen vouchers) → status naar paid");

        $stmtUpdate = $conn->prepare("
            UPDATE orders 
            SET status = 'paid'
            WHERE id = ?
        ");
        $stmtUpdate->bind_param("i", $orderId);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        continue;
    }

    $bigBuyProducts = [];
    $outOfStock     = false;

    foreach ($orderItems[$orderId] as $item) {

        if ($item['type'] !== 'product') {
            logMessage("ℹ Item {$item['product_id']} overgeslagen (type={$item['type']})");
            continue;
        }

        $productId = (int)$item['product_id'];
        $qty       = (int)$item['quantity'];

        // Product ophalen
        $productResp = json_decode($api->getProduct($productId)['response'], true);
        sleep(5);
        $product     = $productResp[0] ?? $productResp;

        $sku  = $product['sku'] ?? null;
        $maat = $item['maat'] ?? null;
        logMessage("🧩 Product {$productId} – maat uit DB: " . ($maat ?? 'NULL'));

        if (!$sku) {
            logMessage("⛔ Geen SKU voor product {$productId}");
            $outOfStock = true;
            continue;
        }

        // Product info
        $infoResp = json_decode(
            $api->getProductInformationBySku($sku, 'nl')['response'],
            true
        );
        sleep(5);
        $info = $infoResp[0] ?? $infoResp;


        // STOCKCHECK
        $finalSku = null;
        $hasStock = false;

        if ($maat === null || $maat === '') {

            // --- Simple product
            $stockResp = json_decode(
                $api->getStockByProduct($productId)['response'],
                true
            );
            sleep(5);

            foreach ($stockResp['stocks'] ?? [] as $s) {
                if ((int)$s['quantity'] >= $qty) {
                    $hasStock = true;
                    $finalSku = $sku;
                    break;
                }
            }

        } else {

            // --- Variant product
            $taxonomyId = $product['taxonomy'] ?? null;
            if (!$taxonomyId) continue;

            // Parent taxonomy zoeken
            $parent = $taxonomyId;
            while (!in_array($parent, $firstLevelIds, true)) {
                foreach ($taxonomies as $t) {
                    if ($t['id'] == $parent) {
                        $parent = $t['parentTaxonomy'];
                        break;
                    }
                }
            }

            $variations = json_decode(
                $api->getProductsVariations($parent)['response'],
                true
            ) ?: [];

            $varAttr = json_decode(
                $api->getVariationsAttributes($parent)['response'],
                true
            ) ?: [];

            $attrResp = json_decode(
                $api->getAttributes('en', $parent)['response'],
                true
            ) ?: [];

            sleep(5);

            $attrMap = [];
            foreach ($attrResp as $a) {
                $attrMap[$a['id']] = $a['name'];
            }

            foreach ($variations as $v) {

                if ($v['product'] != $productId) continue;

                $variantId = $v['id'];
                $variantSku = $v['sku'];

                // attributes matchen
                $attrs = [];
                foreach ($varAttr as $va) {
                    if ($va['id'] == $v['variation']) {
                        foreach ($va['attributes'] as $a) {
                            $attrs[] = $attrMap[$a['id']] ?? null;
                        }
                    }
                }

                if (!in_array($maat, $attrs)) continue;

                // stock check variant
                $stockVar = json_decode(
                    $api->getProductVariationStock($variantId)['response'],
                    true
                );
                sleep(5);

                foreach ($stockVar[0]['stocks'] ?? [] as $s) {
                    if ((int)$s['quantity'] >= $qty) {
                        $hasStock = true;
                        $finalSku = $variantSku;
                        break 2;
                    }
                }
            }
        }

        if (!$hasStock || !$finalSku) {
            logMessage("❌ Geen stock voor product {$productId}");
            $outOfStock = true;
            sendBigBuyDebugMail('BigBuy fout bij order '. $orderId, '❌ Geen stock voor product ' . $productId);
            continue;
        }

        $bigBuyProducts[] = [
            'reference' => $finalSku,
            'quantity'  => $qty
        ];
    }

    if ($outOfStock || empty($bigBuyProducts)) {
        logMessage("⛔ Order {$orderId} NIET verzonden (stockprobleem)");
        continue;
    }

    // ============================================================
    // Order opbouwen
    // ============================================================
    $bigBuyOrder = [
        'order' => [
            'internalReference' => (string)$orderId,
            'language' => substr($order['taal'], 3),
            'paymentMethod' => 'paypal',
            'shippingAddress' => [
                'firstName' => strtok($order['name'], ' '),
                'lastName'  => trim(substr($order['name'], strlen(strtok($order['name'], ' ')))),
                'email'     => $order['email'],
                'phone'     => $order['telephone'],
                'companyName' => $order['company_name'],
                'vatNumber' => $order['vat_number'],
                'country'   => strtoupper($order['country']),
                'postcode'  => str_replace(' ', '', $order['postal_code']),
                'town'      => $order['city'],
                'address'   => trim($order['street'].' '.$order['house_number'])
            ],
            'products' => $bigBuyProducts
        ]
    ];

    logMessage("✔ Order {$orderId} klaar voor verzending");
    logMessage(json_encode($bigBuyOrder, JSON_PRETTY_PRINT));

    // ============================================================
    // 4) Stuur order naar BigBuy
    // ============================================================
    try {
        $response = $api->createOrder($bigBuyOrder);
        $responseData = json_decode($response['response'], true);
        logMessage("Antwoord BigBuy: " . json_encode($responseData, JSON_PRETTY_PRINT));
        sleep(1);
    } catch (Exception $e) {
        logMessage("Fout bij verzenden naar BigBuy: " . $e->getMessage());
        sendBigBuyDebugMail('BigBuy fout bij order ' . $orderId, $e->getMessage());
        continue;
    }

    // ============================================================
    // 5) Order verwerken in db
    // ============================================================
    if (!empty($responseData['orders'])) {
        $bbOrderId = $responseData['orders'][0]['id'] ?? null;

        if ($bbOrderId) {
            // Update lokale DB met BigBuy reference en status op paid
            $stmtUpdate = $conn->prepare("
                UPDATE orders
                SET status = 'paid',
                    bigbuy_reference = ?
                WHERE id = ?
            ");
            $stmtUpdate->bind_param("si", $bbOrderId, $orderId);
            if ($stmtUpdate->execute()) {
                logMessage("Order $orderId bijgewerkt met BigBuy reference: $bbOrderId en status: paid");
            } else {
                logMessage("Fout bij update order $orderId: " . $stmtUpdate->error);
            }
            $stmtUpdate->close();
        }
    }
}

echo "Export afgerond.\n";
?>