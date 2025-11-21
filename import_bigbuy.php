<?php
require __DIR__ . '/db_connect.php';
require __DIR__ . '/bigbuy.php';

$api = new BigBuyAPI();

function logMessage($msg) {
    file_put_contents("/home/stylisso/logs/bigbuy_import.log", date("Y-m-d H:i:s") . " - $msg\n", FILE_APPEND);
}

// ----------------------------------------
// Functie: prijs verhogen met 10 € of 10 % en afronden op .99
// ----------------------------------------
function prijsMetAfronding(float $prijs): float {
    if ($prijs < 100) {
        $prijs += 10;
    } else {
        $prijs = $prijs * 1.10;
    }
    return ceil($prijs) - 0.01;
}

// --- Producten die je wilt importeren ---
$selectedProducts = [
    777174,
    1155141,
    1248305,
    1249339,
    1251734
];

// --- Categorie mapping ---
$productCategoryMap = [
    777174 => 1,
    1155141 => 1,
    1248305 => 1,
    1249339 => 1,
    1251734 => 1
];

// --- Subcategorie mapping ---
$productSubcategoryMap = [
    777174 => 1,
    1155141 => 1,
    1248305 => 1,
    1249339 => 1,
    1251734 => 1
];

echo "=== DEBUG MODE ACTIEF ===\n\n";

// ---------------------------------------------------------
// 0) SHIPPING FILTER (< €9)
// ---------------------------------------------------------
echo "=== START FILTER OP VERZENDKOSTEN (< 9 EUR) ===\n\n";

$filteredProducts = [];

foreach ($selectedProducts as $productId) {
    echo "🟦 Controleer product $productId...\n";

    $response = $api->getProduct($productId);
    $prod = json_decode($response['response'], true);

    // Als het een array is met één product, pak dat
    if (isset($prod[0])) {
        $prod = $prod[0];
    }

    if (empty($prod['sku'])) {
        echo "⛔ Geen SKU → overslaan\n\n";
        continue;
    }

    $sku = $prod['sku'];
    echo "➡ SKU gevonden: $sku\n";

    $payload = [
        "productCountry" => [
            "reference" => $sku,
            "countryIsoCode" => "BE"
        ]
    ];

    $shipResponse = $api->getLowestShippingCost($payload);
    $shipData = json_decode($shipResponse['response'], true);

    $shippingCost = $shipData['shippingCost'] ?? $shipData[0]['shippingCost'] ?? null;
    $carrierName  = $shipData['carrier']['name'] ?? $shipData[0]['carrier']['name'] ?? 'Onbekend';

    if ($shippingCost === null) {
        echo "⛔ Geen carrier beschikbaar voor België → overslaan\n\n";
        continue;
    }

    echo "🚚 Carrier: $carrierName | 💶 Shipping cost: €" . number_format($shippingCost,2) . "\n";

    if ($shippingCost < 9.0) {
        echo "✔ Product toegestaan → toevoegen aan importlijst\n\n";
        $filteredProducts[] = $productId;
    } else {
        echo "⛔ Product te duur (≥ €9) → NIET geïmporteerd\n\n";
    }

    sleep(5);
}

if (empty($filteredProducts)) {
    die("⛔ GEEN PRODUCTEN VOLDOEN AAN DE VERZENDVOORWAARDE (< €9)\n");
}

$selectedProducts = $filteredProducts;

echo "\n=== GEFILTERDE PRODUCTEN ===\n";
print_r($selectedProducts);
echo "\n\n";

// ---------------------------------------------------------
// VANAF HIER ALLEEN PRODUCTEN MET VERZENDKOST < €9
// ---------------------------------------------------------
$languageMap = ['nl'=>'be-nl','fr'=>'be-fr','en'=>'be-en','de'=>'be-de'];

foreach ($selectedProducts as $productId) {
    echo "\n==============================\n";
    echo "📌 PRODUCT $productId START\n";
    echo "==============================\n\n";

    try {
        // 1) GET PRODUCT
        $response = $api->getProduct($productId);
        $prod = json_decode($response['response'], true);
        if (empty($prod['id']) || empty($prod['sku'])) { continue; }
        $sku = $prod['sku'];

        // 2) PRODUCT INFO (NL)
        $infoResponse = $api->getProductInformationBySku($sku,'nl');
        $details = json_decode($infoResponse['response'], true);
        if (!empty($details[0])) $details = $details[0];

        // 3) DESCRIPTION & SPECIFICATIONS
        $rawDescription = $details['description'] ?? '';
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $rawDescription, $matches);
        $specifications = implode(';', array_map(fn($v) => trim(strip_tags($v)), $matches[1] ?? []));
        $descriptionClean = trim(strip_tags(preg_replace('/<ul.*?<\/ul>/is','',$rawDescription)));

        // 4) MATEN
        $maat = null;
        if (!empty($details['attributes'])) {
            $maten=[];
            foreach($details['attributes'] as $attr){
                if(isset($attr['name']) && in_array(strtolower($attr['name']),['size','maat'])){
                    foreach($attr['values'] as $v){
                        if(!empty($v['value'])) $maten[] = trim($v['value']);
                    }
                }
            }
            $maat = implode(';',$maten);
        }

        // 5) IMAGES
        $image=''; $localImageDir=__DIR__.'/products';
        if(!file_exists($localImageDir)) mkdir($localImageDir,0777,true);
        if(!empty($prod['images']['images']) && is_array($prod['images']['images'])){
            $imagesArr = $prod['images']['images'];
            usort($imagesArr, fn($a,$b)=>(!empty($b['isCover'])?1:0)<=>(!empty($a['isCover'])?1:0));
            $imagePaths=[]; $counter=1;
            foreach($imagesArr as $img){
                if(empty($img['url'])) continue;
                $urlPath=parse_url($img['url'],PHP_URL_PATH);
                $ext=pathinfo($urlPath,PATHINFO_EXTENSION)?:'jpg';
                $localFile="{$productId}_{$counter}.".$ext;
                $localPath=$localImageDir.'/'.$localFile;
                $content=@file_get_contents($img['url']);
                if($content!==false){file_put_contents($localPath,$content); $imagePaths[]='products/'.$localFile;$counter++;}
            }
            $image=implode(';',$imagePaths);
        }

        // 6) PRICE
        $price = prijsMetAfronding($details['wholesalePrice'] ?? $prod['wholesalePrice'] ?? 0);

        // 7) DATA VOOR DATABASE
        $dataForDb = [
            'id'=>$productId,
            'category_id'=>$productCategoryMap[$productId]??1,
            'subcategory_id'=>$productSubcategoryMap[$productId]??null,
            'name'=>$details['name']??$prod['name']??'',
            'description'=>$descriptionClean,
            'specifications'=>$specifications,
            'maat'=>$maat,
            'price'=>$price,
            'image'=>$image
        ];

        // 8) INSERT/UPDATE PRODUCTS
        $stmt=$conn->prepare("
            INSERT INTO products (id,category_id,subcategory_id,name,description,specifications,maat,price,image,created_at,active)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),1)
            ON DUPLICATE KEY UPDATE
                category_id=VALUES(category_id),
                subcategory_id=VALUES(subcategory_id),
                name=VALUES(name),
                description=VALUES(description),
                specifications=VALUES(specifications),
                maat=VALUES(maat),
                price=VALUES(price),
                image=VALUES(image),
                active=1
        ");
        $stmt->bind_param("iiissssds",$dataForDb['id'],$dataForDb['category_id'],$dataForDb['subcategory_id'],$dataForDb['name'],$dataForDb['description'],$dataForDb['specifications'],$dataForDb['maat'],$dataForDb['price'],$dataForDb['image']);
        $stmt->execute();

        // 9) VERTALINGEN
        foreach($languageMap as $bbLang=>$dbLang){
            sleep(5);
            $infoResponseLang = $api->getProductInformationBySku($sku,$bbLang);
            $detailsLang = json_decode($infoResponseLang['response'],true);
            if(!empty($detailsLang[0])) $detailsLang=$detailsLang[0];
            if(empty($detailsLang['sku'])) continue;
            $rawDescriptionLang = $detailsLang['description']??'';
            preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $rawDescriptionLang, $matchesLang);
            $specificationsLang = implode(';', array_map(fn($v) => trim(strip_tags($v)), $matchesLang[1] ?? []));
            $descriptionCleanLang = trim(strip_tags(preg_replace('/<ul.*?<\/ul>/is','',$rawDescriptionLang)));
            $nameLang=$detailsLang['name']??'';
            $stmtTrans=$conn->prepare("
                INSERT INTO product_translations (product_id,lang,name,description,specifications,maat,created_at)
                VALUES (?,?,?,?,?,?,NOW())
                ON DUPLICATE KEY UPDATE
                    name=VALUES(name),
                    description=VALUES(description),
                    specifications=VALUES(specifications),
                    maat=VALUES(maat),
                    updated_at=NOW()
            ");
            $stmtTrans->bind_param("isssss",$productId,$dbLang,$nameLang,$descriptionCleanLang,$specificationsLang,$maat);
            $stmtTrans->execute();
            echo "✔ Vertaling ($dbLang) opgeslagen!\n";
            logMessage("✔ Vertaling $dbLang voor product $productId succesvol geïmporteerd");
        }

        echo "✔ Product opgeslagen in database!\n";
        logMessage("✔ Product $productId succesvol geïmporteerd");

    } catch(Exception $e){
        echo "⛔ Fout bij product $productId: ".$e->getMessage()."\n";
        logMessage("⛔ Fout bij product $productId: ".$e->getMessage());
    }

    sleep(5);
}

// ----------------------------------------
// 10) Andere producten deactiveren
// ----------------------------------------
if (!empty($selectedProducts)) {
    // Maak placeholders voor prepared statement
    $placeholders = implode(',', array_fill(0, count($selectedProducts), '?'));
    
    // Zet types voor bind_param ('i' voor integer per ID)
    $types = str_repeat('i', count($selectedProducts));

    $stmtInactive = $conn->prepare("UPDATE products SET active = 0 WHERE id NOT IN ($placeholders)");
    
    // Bind de waarden dynamisch
    $stmtInactive->bind_param($types, ...$selectedProducts);
    
    $stmtInactive->execute();
    $stmtInactive->close();

    echo "✔ Andere producten gedeactiveerd (active=0)!\n";
    logMessage("✔ Andere producten gedeactiveerd (active=0)");
}

echo "\n=== IMPORT VOLTOOID ===\n";
?>