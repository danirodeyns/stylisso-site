<?php
ini_set('max_execution_time', 0);
set_time_limit(0);
ini_set('memory_limit', '1024M'); // verhoog tijdelijk
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        $msg = sprintf("[%s] SHUTDOWN: type=%s file=%s line=%s msg=%s\n",
            date('Y-m-d H:i:s'), $err['type'], $err['file'], $err['line'], $err['message']
        );
        file_put_contents('/home/stylisso/logs/bigbuy_shutdown.log', $msg, FILE_APPEND);
    } else {
        file_put_contents('/home/stylisso/logs/bigbuy_shutdown.log', "[".date('Y-m-d H:i:s')."] SHUTDOWN: no error (possible SIGKILL)\n", FILE_APPEND);
    }
});

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/home/stylisso/logs/php_error.log');
require __DIR__ . '/db_connect.php';
require __DIR__ . '/bigbuy.php';

$api = new BigBuyAPI();

function logMessage($msg) {
    file_put_contents("/home/stylisso/logs/bigbuy_import.log", date("Y-m-d H:i:s") . " - $msg\n", FILE_APPEND);
}

// ----------------------------------------
// Functie: prijs verhogen met 15 € of 30 % en afronden op .99
// ----------------------------------------
function prijsMetAfronding(float $prijs): float {
    if ($prijs < 50) {
        $prijs += 15;
    } else {
        $prijs = $prijs * 1.30;
    }
    return ceil($prijs) - 0.01;
}

// --- Producten die je wilt importeren ---
$selectedProducts = [
    1138002,
    306246,
];

// --- Categorie mapping ---
$productCategoryMap = [
    1138002 => 2,
    306246 => 1,
];

// --- Subcategorie mapping ---
$productSubcategoryMap = [
    1138002 => 20,
    306246 => 2,
];

echo "=== DEBUG MODE ACTIEF ===\n\n";

// ---------------------------------------------------------
// 0) STOCK FILTER (ACTIEF)
// ---------------------------------------------------------
echo "=== START STOCK FILTER ===\n\n";

$filteredProducts = [];

foreach ($selectedProducts as $productId) {
    echo "🟦 Controleer product $productId...\n";

    // Product ophalen
    try {
        $response = $api->getProduct($productId);
    } catch (Exception $e) {
        echo "⛔ getProduct() timeout → product overslaan\n\n";
        logMessage("⛔ getProduct timeout voor product $productId → overslaan");
        sleep(5);
        continue;
    }
    $prod = json_decode($response['response'], true);
    if (isset($prod[0])) $prod = $prod[0];

    if (empty($prod['sku'])) {
        echo "⛔ Geen SKU → overslaan\n\n";
        sleep(5);
        continue;
    }

    $sku = $prod['sku'];
    echo "➡ SKU gevonden: $sku\n";

    // Stockcheck op parent product
    try {
        $stockResp = $api->getStockByProduct((int)$productId);
    } catch (Exception $e) {
        echo "⛔ getStockByProduct() timeout → product overslaan\n\n";
        logMessage("⛔ Stock timeout voor product $productId → overslaan");
        sleep(5);
        continue;
    }
    $stockJson = json_decode($stockResp['response'], true);
    $available = 0;
    if (!empty($stockJson['stocks']) && is_array($stockJson['stocks'])) {
        foreach ($stockJson['stocks'] as $stockEntry) {
            if (!empty($stockEntry['quantity']) && $stockEntry['quantity'] > $available) {
                $available = (int)$stockEntry['quantity'];
            }
        }
    }

    if ($available <= 0) {
        echo "⛔ Product niet op voorraad → overslaan\n\n";
        sleep(5);
        continue;
    }

    echo "✔ Product op voorraad ($available units) → toevoegen\n\n";

    $filteredProducts[] = $productId;
    sleep(5);
}

if (empty($filteredProducts)) die("⛔ Geen producten beschikbaar in stock\n");

$selectedProducts = $filteredProducts;
echo "\n=== GEFILTERDE PRODUCTEN (STOCK OK) ===\n";
print_r($selectedProducts);
echo "\n\n";

// ------------------- IMPORT LOOP MET VARIANTS & STOCKCHECK -------------------
$languageMap = ['nl'=>'be-nl','fr'=>'be-fr','en'=>'be-en','de'=>'be-de'];

// Eénmalig taxonomies ophalen
$taxonomiesRespRaw = $api->getTaxonomies();
$firstLevelRespRaw = $api->getTaxonomiesFirstLevel();

$taxonomiesResp = json_decode($taxonomiesRespRaw['response'] ?? '', true) ?: [];
$firstLevelResp = json_decode($firstLevelRespRaw['response'] ?? '', true) ?: [];
$firstLevelIds  = array_column($firstLevelResp, 'id');

foreach ($selectedProducts as $productId) {
    echo "\n==============================\n";
    echo "📌 PRODUCT $productId START\n";
    echo "==============================\n\n";

    try {
        try {
            $response = $api->getProduct($productId);
        } catch (Exception $e) {
            echo "⛔ getProduct timeout → product $productId overslaan\n";
            logMessage("⛔ getProduct timeout bij product $productId → overslaan");
            sleep(5);
            continue;
        }
        $prod = json_decode($response['response'], true);
        if (isset($prod[0])) $prod = $prod[0];
        if (empty($prod['id']) || empty($prod['sku'])) {
            sleep(5);
            continue;
        }

        // Product info NL
        try {
            $infoResponse = $api->getProductInformationBySku($prod['sku'], 'nl');
        } catch (Exception $e) {
            echo "⛔ ProductInformation timeout → product $productId overslaan\n";
            logMessage("⛔ ProductInfo timeout voor $productId → overslaan");
            sleep(5);
            continue;
        }
        $details = json_decode($infoResponse['response'], true);
        if (!empty($details[0])) $details = $details[0];

        if (empty($details['id']) || empty($details['sku'])) {
            echo "⛔ Geen details beschikbaar, overslaan\n";
            sleep(5);
            continue;
        }

        // Description & specifications
        $rawDescription = $details['description'] ?? '';
        preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $rawDescription, $matches);
        $specifications = implode(';', array_map(fn($v) => trim(strip_tags($v)), $matches[1] ?? []));
        $descriptionClean = trim(strip_tags(preg_replace('/<ul.*?<\/ul>/is','',$rawDescription)));

        // ----------------- Variants ophalen -----------------
        // 1) Product info
        sleep(5);
        $productResp = $api->getProduct($productId);
        $productInfo = json_decode($productResp['response'] ?? '', true);
        if (!$productInfo || !is_array($productInfo)) {
            echo "⛔ Kan product info niet ophalen\n";
            sleep(5);
            continue;
        }
        if (isset($productInfo[0])) $productInfo = $productInfo[0];

        // 2) Taxonomy van product
        $productTaxonomyId = $productInfo['taxonomy'] ?? $productInfo['category'] ?? null;
        if (!$productTaxonomyId) die("⛔ Geen taxonomy gevonden voor product $productId\n");

        // 3) Zoek parent taxonomy
        $parentTaxonomy = $productTaxonomyId;
        $maxLoops = 20; $i=0;
        while (!in_array($parentTaxonomy, $firstLevelIds, true) && $i<$maxLoops) {
            $found = false;
            foreach ($taxonomiesResp as $t) {
                if (($t['id'] ?? null) === $parentTaxonomy) {
                    $parentTaxonomy = $t['parentTaxonomy'] ?? $t['id'];
                    $found = true;
                    break;
                }
            }
            if (!$found) break;
            $i++;
        }

        // DEBUG
        echo "✅ Parent taxonomy gevonden: $parentTaxonomy\n";

        // 4) Variations ophalen
        $variationsRespRaw = $api->getProductsVariations($parentTaxonomy);
        $variationsResp = json_decode($variationsRespRaw['response'] ?? '', true) ?: [];
        echo "Variations count: " . count($variationsResp) . "\n";

        // 5) Variation → attribute IDs
        $varAttrRespRaw = $api->getVariationsAttributes($parentTaxonomy);
        $varAttr = json_decode($varAttrRespRaw['response'] ?? '', true) ?: [];

        $varAttrMap = [];
        foreach ($varAttr as $va) {
            $id = $va['id'] ?? null;
            if ($id) $varAttrMap[$id] = $va['attributes'] ?? [];
        }

        // 6) Attributes ophalen
        $attrRespRaw = $api->getAttributes('en', $parentTaxonomy);
        $attrList = json_decode($attrRespRaw['response'] ?? '', true) ?: [];

        $attrMap = [];
        foreach ($attrList as $a) {
            if (($a['attributeGroup'] ?? null) == 162) {
                $attrMap[$a['id']] = $a['name'] ?? 'Unknown';
            }
        }

        // 7) Variants → sizes
        $variants = [];
        foreach ($variationsResp as $v) {
            $varId = $v['id'] ?? null;
            $sku = $v['sku'] ?? null;
            $vProductId = $v['product'] ?? null; // alleen van dit product
            if (!$varId || !$sku || $vProductId != $productId) continue;

            $itemId = $v['id'] ?? null;

            // 1) Stock ophalen per variant
            $variantStock = 0;

            if ($itemId) {
                try {
                    $stockResp = $api->getProductVariationStock($itemId);
                    $stockJson = json_decode($stockResp['response'], true);

                    if (!empty($stockJson[0]['stocks']) && is_array($stockJson[0]['stocks'])) {
                        foreach ($stockJson[0]['stocks'] as $entry) {
                            $qty = (int)($entry['quantity'] ?? 0);
                            if ($qty > $variantStock) {
                                $variantStock = $qty;
                            }
                        }
                    }
                } catch (Exception $e) {
                    echo "⛔ Variant stock timeout voor itemId $itemId → variant overslaan\n";
                    sleep(5);
                    continue;
                }
            }

            // 2) Variant NIET toevoegen als stock == 0
            if ($variantStock <= 0) {
                echo "⛔ Variant $sku (itemId $itemId) niet op voorraad → overslaan\n";
                sleep(5);
                continue;
            }

            echo "✔ Variant $sku (itemId $itemId) heeft stock ($variantStock)\n";

            // 3) Variant registreren
            $variants[$sku] = [];
            $attributes = $varAttrMap[$varId] ?? [];
            foreach ($attributes as $a) {
                $attrId = $a['id'] ?? null;
                if ($attrId && isset($attrMap[$attrId])) {
                    $variants[$sku][] = $attrMap[$attrId];
                }
            }
            sleep(5);
        }

        // --- 7b) Maak één string van alle attributen ---
        $allAttributes = [];
        foreach ($variants as $attrArray) {
            foreach ($attrArray as $a) {
                $allAttributes[] = $a;
            }
        }

        // Uniek maken
        $allAttributes = array_unique($allAttributes);

        // 1️⃣ Definieer bekende standaardmaten
        $sizeOrder = ['XXS','XS','S','M','L','XL','XXL','XXXL'];

        // 2️⃣ Categoriseer sizes
        $standard = [];
        $numeric  = [];
        $age      = [];
        $other    = [];

        foreach ($allAttributes as $s) {
            $trim = trim($s);
            if (in_array($trim, $sizeOrder)) {
                $standard[] = $trim;
            } elseif (preg_match('/^\d+$/', $trim)) {
                $numeric[] = (int)$trim; // nummers opslaan als int voor sortering
            } elseif (preg_match('/^(\d+)-(\d+)\s*years$/i', $trim, $matches)) {
                $age[] = $trim; // hou string, sorteren later
            } else {
                $other[] = $trim;
            }
        }

        // 3️⃣ Sorteer elke categorie
        // Standaard: volgorde van sizeOrder
        usort($standard, function($a,$b) use ($sizeOrder){ 
            return array_search($a,$sizeOrder) - array_search($b,$sizeOrder);
        });

        // Numeriek: laag naar hoog
        sort($numeric, SORT_NUMERIC);

        // Leeftijd: laagste leeftijd eerst
        usort($age, function($a,$b){
            preg_match('/^(\d+)-(\d+)/', $a, $mA);
            preg_match('/^(\d+)-(\d+)/', $b, $mB);
            return (int)$mA[1] - (int)$mB[1];
        });

        // Other: alfabetisch
        sort($other, SORT_STRING);

        // 4️⃣ Alles achter elkaar zetten
        $numeric = array_map('strval', $numeric); // ints terug naar strings
        $finalSizes = array_merge($age, $standard, $numeric, $other);
        $resultSizes = implode(';', $finalSizes);
        var_dump($resultSizes);
        if (empty($resultSizes)) {
            $resultSizes = null;
        }
        $maat = $resultSizes;

        // ----------------- Images -----------------
        $image=''; $localImageDir=__DIR__.'/products';
        if(!file_exists($localImageDir)) mkdir($localImageDir,0777,true);
        if(!empty($prod['images']['images']) && is_array($prod['images']['images'])){
            $imagesArr = $prod['images']['images'];
            usort($imagesArr, fn($a,$b)=>(!empty($b['isCover'])?1:0)<=>(!empty($a['isCover'])?1:0));
            $imagePaths=[]; $counter=1;
            foreach($imagesArr as $img){
                if(empty($img['url'])) {
                    sleep(5);
                    continue;
                }
                $urlPath=parse_url($img['url'],PHP_URL_PATH);
                $ext=pathinfo($urlPath,PATHINFO_EXTENSION)?:'jpg';
                $localFile="{$productId}_{$counter}.".$ext;
                $localPath=$localImageDir.'/'.$localFile;
                $content=@file_get_contents($img['url']);
                if($content!==false){file_put_contents($localPath,$content); $imagePaths[]='products/'.$localFile;$counter++;}
            }
            $image=implode(';',$imagePaths);
        }

        // ----------------- Price -----------------
        $price = prijsMetAfronding($details['wholesalePrice'] ?? $prod['wholesalePrice'] ?? 0);

        // ----------------- Data voor DB -----------------
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

        // ----------------- Insert/Update DB -----------------
        $stmt=$conn->prepare("
            INSERT INTO products (id,category_id,subcategory_id,name,description,specifications,maat,price,image,created_at,active)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),1)
            ON DUPLICATE KEY UPDATE
                category_id=VALUES(category_id),
                subcategory_id=VALUES(subcategory_id),
                name=VALUES(name),
                description=VALUES(description),
                specifications=VALUES(specifications),
                maat=NULLIF(VALUES(maat), ''),
                price=VALUES(price),
                image=VALUES(image),
                active=1
        ");
        $stmt->bind_param(
            "iiissssds",
            $dataForDb['id'],
            $dataForDb['category_id'],
            $dataForDb['subcategory_id'],
            $dataForDb['name'],
            $dataForDb['description'],
            $dataForDb['specifications'],
            $dataForDb['maat'],
            $dataForDb['price'],
            $dataForDb['image']
        );
        $stmt->execute();

        // ----------------- Vertalingen -----------------
        foreach($languageMap as $bbLang=>$dbLang){
            sleep(5);
            $infoResponseLang = $api->getProductInformationBySku($prod['sku'],$bbLang);
            $detailsLang = json_decode($infoResponseLang['response'],true);
            if(!empty($detailsLang[0])) $detailsLang=$detailsLang[0];
            if(empty($detailsLang['sku'])) {
                sleep(5);
                continue;
            }
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
            $maatLang = $dataForDb['maat'];
            $stmtTrans->bind_param("isssss", $productId, $dbLang, $nameLang, $descriptionCleanLang, $specificationsLang, $maatLang);
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

    unset($variationsResp, $varAttr, $varAttrMap, $attrList, $attrMap, $variants, $allAttributes);
    gc_collect_cycles();

    sleep(5);
}

// ----------------------------------------
// 3) Andere producten deactiveren
// ----------------------------------------
if (!empty($selectedProducts)) {
    $placeholders = implode(',', array_fill(0, count($selectedProducts), '?'));
    $types = str_repeat('i', count($selectedProducts));
    $stmtInactive = $conn->prepare("UPDATE products SET active = 0 WHERE id NOT IN ($placeholders)");
    $stmtInactive->bind_param($types, ...$selectedProducts);
    $stmtInactive->execute();
    $stmtInactive->close();

    echo "✔ Andere producten gedeactiveerd (active=0)!\n";
    logMessage("✔ Andere producten gedeactiveerd (active=0)");
}

echo "\n=== IMPORT VOLTOOID ===\n";
?>