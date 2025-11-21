<?php
class BigBuyAPI
{
    private string $apiBase;
    private string $apiKey;

    public function __construct()
    {
        $config = require __DIR__ . '/config.php';
        $this->apiBase = rtrim($config['bigbuy']['api_base'], '/');
        $this->apiKey  = $config['bigbuy']['api_key'];
    }

    private function request(string $endpoint, string $method = 'GET', array $body = null)
    {
        $url = $this->apiBase . '/' . ltrim($endpoint, '/');

        // Headers eerst volledig opbouwen
        $headers = [
            "Accept: application/json",
            "Authorization: Bearer {$this->apiKey}"
        ];

        if ($body !== null) {
            $headers[] = "Content-Type: application/json";
        }

        $curl = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
        }

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $code     = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (curl_errno($curl)) {
            throw new Exception("BigBuy cURL error: " . curl_error($curl));
        }

        curl_close($curl);

        return [
            "status"   => $code,
            "response" => $response
        ];
    }

    // Product ophalen
    public function getProduct(int $productId)
    {
        return $this->request("catalog/product/{$productId}.json");
    }

    // Productlijst (correcte pagination)
    public function getProducts(int $page = 1, int $limit = 100)
    {
        return $this->request("catalog/products.json?page={$page}&limit={$limit}");
    }

    // Product informatie ophalen
    public function getProductInformation(int $productId)
    {
        return $this->request("catalog/productinformation/{$productId}.json");
    }

    // Product informatie ophalen via SKU
    public function getProductInformationBySku(string $sku, string $lang)
    {
        return $this->request("catalog/productinformationbysku/{$sku}.json?isoCode=$lang");
    }

    // Alle stock
    public function getStock()
    {
        return $this->request("catalog/productsstockbyhandlingdays.json");
    }

    // Stock per product
    public function getStockByProduct(int $productId)
    {
        return $this->request("catalog/productstockbyhandlingdays/{$productId}.json");
    }

    // Order maken
    public function createOrder(array $orderData)
    {
        return $this->request("order/create/multishipping.json", "POST", $orderData);
    }

    // Order information ophalen
    public function getOrderInformation(int $bigBuyOrderId)
    {
        return $this->request("order/$bigBuyOrderId.json");
    }

    // --- Alle beschikbare carriers ophalen
    public function getShippingCarriers(array $params = [])
    {
        $query = http_build_query($params);
        return $this->request("shipping/carriers.json?$query", 'GET', null);
    }

    // --- Laagste verzendkosten per land en producten
    public function getLowestShippingCost(array $data)
    {
        return $this->request("shipping/lowest-shipping-cost-by-country.json", 'POST', $data,);
    }

    // --- Verzendkosten berekenen voor een specifieke bestelling
    public function getShippingOrderCost(array $data)
    {
        return $this->request("shipping/orders.json", 'POST', $data);
    }
}
?>