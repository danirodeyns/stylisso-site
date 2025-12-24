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

    // Order information ophalen
    public function getOrderInformationForMultishipping(array $orderData)
    {
        return $this->request("order/check/multishipping.json", "POST", $orderData);
    }

    // --- Alle beschikbare carriers ophalen
    public function getShippingCarriers()
    {
        return $this->request("shipping/carriers.json");
    }

    // --- Laagste verzendkosten per land en producten
    public function getLowestShippingCost(array $data)
    {
        return $this->request("shipping/lowest-shipping-cost-by-country.json", 'POST', $data);
    }

    // --- Laagste verzendkosten per land
    public function getLowestShippingCostByCountry(string $countryIsoCode ='BE')
    {
        return $this->request("shipping/lowest-shipping-costs-by-country/{$countryIsoCode}.json");
    }

    // --- Verzendkosten berekenen voor een specifieke bestelling
    public function getShippingOrderCost(array $data)
    {
        return $this->request("shipping/orders.json", 'POST', $data);
    }

    public function getProductVariations($productId)
    {
        return $this->request("catalog/productvariations/{$productId}.json");
    }

    public function getAttributesAllLanguages($productId)
    {
        return $this->request("catalog/attributealllanguages/{$productId}.json");
    }

    // --- Variation → Attribute mapping
    public function getVariationsAttributes(int $parentTaxonomy = null)
    {
        $endpoint = "catalog/variations.json";

        if ($parentTaxonomy !== null) {
            $endpoint .= "?parentTaxonomy=" . intval($parentTaxonomy);
        }

        return $this->request($endpoint);
    }

    // --- Attribute details
    public function getAttributes(string $lang = "en", int $parentTaxonomy = null)
    {
        $endpoint = "catalog/attributes.json?isoCode={$lang}";

        if ($parentTaxonomy !== null) {
            $endpoint .= "&parentTaxonomy=" . intval($parentTaxonomy);
        }

        return $this->request($endpoint);
    }

    // --- Attribute groups (Size, Color, Capacity...)
    public function getAttributeGroups(string $lang = "en", int $parentTaxonomy = null)
    {
        $endpoint = "catalog/attributesgroups.json?isoCode={$lang}";

        if ($parentTaxonomy !== null) {
            $endpoint .= "&parentTaxonomy=" . intval($parentTaxonomy);
        }

        return $this->request($endpoint);
    }

    // --- Complete products + variations catalog
    public function getProductsVariations(int $parentTaxonomy = null)
    {
        $endpoint = "catalog/productsvariations.json";

        if ($parentTaxonomy !== null) {
            $endpoint .= "?parentTaxonomy=" . intval($parentTaxonomy);
        }

        return $this->request($endpoint);
    }

    public function getProductsByItemId(int $itemId)
    {
        return $this->request("catalog/products.json?itemId={$itemId}");
    }

    public function getProductVariationStock(int $itemId)
    {
        return $this->request("catalog/productvariationsstockbyhandlingdays/{$itemId}.json");
    }

    public function getTaxonomies()
    {
        return $this->request("catalog/taxonomies.json");
    }

    public function getTaxonomiesFirstLevel()
    {
        return $this->request("catalog/taxonomies.json?firstLevel");
    }

    public function getProductVariants(
        int $productId,
        string $lang = 'en',
        array $taxonomiesResp = [],
        array $firstLevelIds = []
    ): array {
        // 1. Product info ophalen
        sleep(5);
        $productResp = $this->getProduct($productId);
        $productInfo = json_decode($productResp['response'] ?? '', true);
        if (!$productInfo || !is_array($productInfo)) return [];

        $productTaxonomyId = $productInfo['taxonomy'] ?? $productInfo['category'] ?? null;
        if (!$productTaxonomyId) return [];

        // --- Gebruik reeds opgehaalde taxonomies ---
        if (empty($taxonomiesResp) || empty($firstLevelIds)) {
            // fallback: indien geen taxonomies doorgegeven, alsnog ophalen
            $taxonomiesResp = json_decode($this->getTaxonomies()['response'] ?? '', true) ?: [];
            $firstLevelResp = json_decode($this->getTaxonomiesFirstLevel()['response'] ?? '', true) ?: [];
            $firstLevelIds  = array_column($firstLevelResp, 'id');
        }

        // 2. Vind eerste-level parentTaxonomy
        $parentTaxonomy = $productTaxonomyId;
        $maxLoops = 10; $i = 0;
        while (!in_array($parentTaxonomy, $firstLevelIds, true) && $i < $maxLoops) {
            $found = false;
            foreach ($taxonomiesResp as $tax) {
                if ($tax['id'] == $parentTaxonomy) {
                    $parentTaxonomy = $tax['parentTaxonomy'] ?? $tax['id'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $parentTaxonomy = $productTaxonomyId;
                break;
            }
            $i++;
        }

        // 3. Ophalen van variaties & attributen
        $variationsResp = json_decode($this->getProductsVariations($parentTaxonomy)['response'] ?? '', true) ?: [];
        $varAttrResp    = json_decode($this->getVariationsAttributes($parentTaxonomy)['response'] ?? '', true) ?: [];
        $attrResp       = json_decode($this->getAttributes($lang, $parentTaxonomy)['response'] ?? '', true) ?: [];

        // 4. Attributes map (alleen groep 162)
        $attrMap = [];
        foreach ($attrResp as $a) {
            if (($a['attributeGroup'] ?? null) == 162) {
                $attrMap[$a['id']] = $a;
            }
        }

        // 5. Variaties mappen naar attributen
        $variants = [];
        foreach ($variationsResp as $v) {
            if (($v['product'] ?? null) != $productId) {
                sleep(5);
                continue;
            }
            $varId = $v['id'] ?? null;
            if (!$varId) {
                sleep(5);
                continue;
            }

            $variants[$v['sku'] ?? 'unknown'] = [];

            foreach ($varAttrResp as $va) {
                if (($va['id'] ?? null) != $varId) {
                    sleep(5);
                    continue;
                }
                foreach ($va['attributes'] as $a) {
                    $attrId = $a['id'] ?? null;
                    if ($attrId && isset($attrMap[$attrId])) {
                        $variants[$v['sku']][$attrMap[$attrId]['attributeGroup']] = $attrMap[$attrId]['name'] ?? 'Unknown';
                    }
                }
            }

            // Fallback als er geen attribute 162 is
            if (empty($variants[$v['sku']])) {
                $variants[$v['sku']][null] = $v['sku'];
            }
        }

        return $variants;
    }
}
?>