<?php
require __DIR__ . '/bigbuy.php';
require __DIR__ . '/db_connect.php';
require __DIR__ . '/mailing.php';
require __DIR__ . '/translations.php';

$api = new BigBuyAPI();

// --- Haal orders die nog niet 'received' of 'cancelled' zijn en een BigBuy reference hebben ---
$sql = "SELECT id, user_id, status, taal, bigbuy_reference FROM orders 
        WHERE status NOT IN ('received', 'cancelled') 
            AND bigbuy_reference IS NOT NULL";
$result = $conn->query($sql);

if ($result === false) {
    die("Fout bij query: " . $conn->error);
}

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo "Aantal orders om te updaten: " . count($orders) . "\n";

$statusMapping = [
    "Pendiente de pago" => "pending",
    "En proceso" => "paid",
    "Expedido" => "shipped",
    "Cancelado" => "cancelled",
    "Entregado" => "received",
];

foreach ($orders as $order) {
    $orderId = $order['id'];
    $bigbuyRef = $order['bigbuy_reference'];
    $localStatus = $order['status'];

    echo "Bezig met order ID $orderId (BigBuy reference: $bigbuyRef)\n";

    try {
        $bbOrderResponse = $api->getOrderInformation((int)$bigbuyRef);
        $bbOrder = json_decode($bbOrderResponse['response'], true);
        sleep(1);

        if (!is_array($bbOrder) || empty($bbOrder['status'])) {
            echo "Geen status ontvangen voor order $orderId, overslaan.\n";
            continue;
        }

        $bbStatusRaw = $bbOrder['status'];
        $bbStatus = $statusMapping[$bbStatusRaw] ?? null;

        if (!$bbStatus) {
            echo "Onbekende BigBuy status '$bbStatusRaw', overslaan.\n";
            continue;
        }

        echo "BigBuy status: $bbStatus, Lokale status: $localStatus\n";

        // --- Voeg dit toe ---
        $ignoreStatuses = ['pending', 'paid'];
        if (in_array($bbStatus, $ignoreStatuses)) {
            echo "BigBuy status is '$bbStatus', update overslaan.\n";
            continue; // overslaan, geen update
        }

        if ($bbStatus !== $localStatus) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $bbStatus, $orderId);
            if ($stmt->execute()) {
                echo "Order $orderId status bijgewerkt naar $bbStatus\n";

                // Mail bij shipped
                if ($bbStatus === 'shipped') {
                    $userResult = $conn->query("SELECT email, name FROM users WHERE id = " . intval($order['user_id']));
                    if ($userResult && $userRow = $userResult->fetch_assoc()) {
                        $email = $userRow['email'];
                        $name  = $userRow['name'] ?? '';
                        $lang  = $userRow['taal'] ?? 'be-nl';
                        sendShippingConfirmation($email, $name, $orderId, $lang);
                    }
                }
            } else {
                echo "Fout bij update order $orderId: " . $stmt->error . "\n";
            }
        } else {
            echo "Status is al up-to-date.\n";
        }

    } catch (Exception $e) {
        echo "Fout bij ophalen order $orderId: " . $e->getMessage() . "\n";
    }
}

$conn->close();
echo "Alle orders verwerkt.\n";
?>