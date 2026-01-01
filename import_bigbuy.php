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

$lockFile = '/tmp/import_bigbuy.lock';

// Check of er al een lock is
if (file_exists($lockFile)) {
    $pid = (int) trim(file_get_contents($lockFile));
    if ($pid && posix_kill($pid, 0)) {
        echo "⏭ Script draait al (PID $pid), stoppen.\n";
        exit;
    } else {
        // stale lock
        unlink($lockFile);
    }
}

// Lock aanmaken
file_put_contents($lockFile, getmypid());

// ✅ Plaats hier de shutdown-cleanup
register_shutdown_function(function() use ($lockFile) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

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
    441567,
    452825,
    1132319,
    1132054,
    531156,
    344493,
    1071278,
    721878,
    1132065,
    306387,
    137931,
    1178089,
    762393,
    491878,
    305825,
    1077010,
    490718,
    1225697,
    1137530,
    1217043,
    1061307,
    311992,
    438008,
    490702,
    1045386,
    1225978,
    314084,
    564026,
    897326,
    897297,
    897286,
    1076918,
    796505,
    491884,
    786802,
    453855,
    491885,
    1077058,
    1183836,
    442781,
    490722,
    1076968,
    1076902,
    1076897,
    1077050,
    308073,
    353977,
    496216,
    1137526,
    1180738,
    441806,
    308557,
    438607,
    440549,
    440599,
    433534,
    1131343,
    1087906,
    451262,
    379926,
    649520,
    451271,
    1071091,
    893982,
    929593,
    649556,
    649560,
    929563,
    499198,
    652417,
    652409,
    1127578,
    1071290,
    1071271,
    1184477,
    897877,
    495971,
    648546,
    1131341,
    311190,
    1183832,
    442804,
    442518,
    511934,
    492549,
    1132313,
    492531,
    601149,
    1183839,
    489237,
    386536,
    649482,
    1132315,
    650835,
    564210,
    1137519,
    1071519,
    1131346,
    1181277,
    498602,
    591155,
    490717,
    932337,
    319468,
    443839,
    788666,
    1076957,
    1076974,
    451211,
    1077049,
    306320,
    785250,
    785162,
    530765,
    1127359,
    441572,
    119453,
    933778,
    652520,
    307141,
    785209,
    934975,
    934987,
    457461,
    496217,
    319470,
    437172,
    322275,
    600716,
    680233,
    567639,
    1132795,
    1184469,
    490710,
    600719,
    1217600,
    1072290,
    1072326,
    1072289,
    437168,
    490716,
    1137536,
    44182,
    308125,
    1088539,
    653626,
    788429,
    307037,
    308556,
    441846,
    1088127,
    1093589,
    1088611,
    1132814,
    1073814,
    510322,
    1073786,
    831388,
    1077015,
    831416,
    776197,
    1240562,
    1175274,
    317057,
    1132298,
    1132312,
    1132079,
    438934,
    1217601,
    502301,
    897302,
    1129005,
    451267,
    1132051,
    1132898,
    1088559,
    1087908,
    1168340,
    1131349,
    1132807,
    1132089,
    531173,
    441820,
    786569,
    651306,
    786567,
    936328,
    569302,
    817055,
    352876,
    1087777,
    451747,
    600681,
    796503,
    796507,
    1089249,
    1130413,
    1087784,
    1087782,
    648959,
    452814,
    1061555,
    1061545,
    1061534,
    437993,
    443849,
    438950,
    1130590,
    441884,
    786818,
    786854,
    440299,
    936330,
    1130599,
    1127283,
    1130544,
    1127374,
    936307,
    929693,
    784395,
    649547,
    649567,
    1190941,
    437243,
    929569,
    936353,
    1168473,
    1170354,
    1170083,
    649370,
    929645,
    351567,
    929534,
    936340,
    929690,
    929662,
    1263255,
    1263028,
    1263029,
    1263030,
    1263031,
    1263034,
    1263035,
    1263036,
    1263037,
    1263038,
    1263039,
    1263040,
    1263042,
    1263043,
    1263044,
    1263045,
    1263046,
    1263047,
    1263048,
    1263049,
    1263138,
    1263145,
    1263158,
    1263159,
    1263160,
    1263162,
    1263166,
    1263167,
    1263168,
    1263170,
    1263177,
    1263188,
    1263195,
    1263197,
    1263226,
    1263244,
    1263246,
    1263248,
    1260564,
    1260571,
    1260565,
    1260561,
    1260560,
    1260575,
    1260563,
    1260581,
    1260591,
    1260569,
    1260588,
    1260590,
    1260574,
    1260596,
    1260592,
    1260567,
    1260570,
    1260573,
    1260583,
    1260576,
    1260572,
    1260580,
    1260582,
    1260589,
    1260594,
    1260597,
    1260579,
    1260598,
    1260599,
    1260600,
    1260585,
    1260647,
    1260648,
    1261767,
    1261783,
    1261785,
    1261786,
    1262196,
    1262199,
    1262202,
    1262203,
    1262204,
    1262204,
    1262205,
    1262206,
    1262216,
    1262211,
    1262208,
    1262207,
    1262217,
    1262224,
    1262225,
    1262226,
    1262227,
    1262228,
    1262230,
    1262229,
    1262231,
    1262287,
    1262288,
    1260255,
    1260199,
    1260247,
    1260286,
    1260288,
    1260287,
    1260203,
    1260209,
    1260206,
    1260211,
    1260214,
    1260217,
    1260222,
    1260224,
    1260229,
    1260133,
    1260232,
    1260290,
    1260266,
    1260240,
    1260833,
    1260836,
    1260834,
    1260142,
    1260290,
    1260266,
    1260240,
    1260242,
    1260246,
    1260249,
    1260180,
    1260180,
    1260252,
    1260289,
    1260371,
    1260372,
    1260292,
    1260254,
    1260228,
    1260291,
    1260134,
    1260197,
    1260258,
    1260235,
    1260186,
    1260173,
    1260293,
    1260260,
    1260154,
    1260554,
    1260555,
    1260557,
    1260559,
    1260558,
    1260142,
    1259374,
    1259384,
    1259498,
    1259497,
    1259491,
    1259494,
    1259487,
    1259447,
    1259493,
    1259492,
    1259488,
    1259481,
    1259470,
    1259476,
    1259360,
    1259278,
    1259324,
    1259312,
    1259429,
    1259419,
    1259400,
    1259393,
    1259362,
    1259353,
    1259343,
    1259297,
    1259467,
    1259463,
    1259461,
    1259320,
    1259460,
    1259456,
    1259445,
    1259449,
    1259412,
    1259420,
    1259428,
    1259437,
    1259490,
    1259391,
    1259332,
    1259307,
    1259363,
    1259344,
    1259434,
    1259399,
    1259407,
    1259418,
    1259443,
    1259451,
    1259440,
    1259455,
    1259466,
    1259442,
    1259462,
    1259457,
    1259473,
    1259355,
    1259345,
    1259477,
    1259367,
    1259485,
    1259376,
    1259383,
    1259389,
    1259396,
    1259489,
    1259402,
    1259408,
    1259415,
    1259421,
    1259221,
    1259444,
    1259375,
    1259469,
    1259425,
    1260159,
    1260163,
    1260245,
    1260278,
    1260250,
    1260165,
    1260202,
    1260268,
    1260207,
    1260210,
    1260191,
    1260212,
    1260226,
    1260243,
    1260179,
    1260264,
    1260267,
    1260269,
    1260182,
    1260241,
];

// --- Categorie mapping ---
$productCategoryMap = [
    1138002 => 2,
    306246 => 1,
    441567 => 1,
    452825 => 2,
    1132319 => 2,
    1132054 => 1,
    531156 => 1,
    344493 => 1,
    1071278 => 1,
    721878 => 3,
    1132065 => 1,
    306387 => 1,
    137931 => 3,
    1178089 => 2,
    762393 => 1,
    491878 => 1,
    305825 => 1,
    1077010 => 1,
    490718 => 1,
    1225697 => 3,
    1137530 => 1,
    1217043 => 1,
    1061307 => 1,
    311992 => 1,
    438008 => 1,
    490702 => 1,
    1045386 => 1,
    1225978 => 1,
    314084 => 2,
    564026  => 1,
    897326  => 1,
    897297  => 1,
    897286  => 1,
    1076918  => 2,
    796505  => 1,
    491884  => 1,
    786802  => 1,
    453855  => 1,
    491885  => 1,
    1077058  => 1,
    1183836  => 2,
    442781  => 1,
    490722  => 2,
    1076968  => 1,
    1076902  => 1,
    1076897  => 1,
    1077050  => 1,
    308073  => 2,
    353977  => 1,
    496216  => 1,
    1137526  => 1,
    1180738  => 1,
    441806  => 2,
    308557  => 1,
    438607  => 1,
    440549  => 1,
    440599  => 1,
    433534  => 3,
    1131343  => 1,
    1087906  => 1,
    451262  => 1,
    379926  => 1,
    649520  => 1,
    451271  => 1,
    1071091  => 1,
    893982  => 1,
    929593  => 1,
    649556  => 1,
    649560  => 1,
    929563  => 1,
    499198  => 1,
    652417  => 1,
    652409  => 1,
    1127578  => 1,
    1071290  => 1,
    1071271  => 1,
    1184477  => 1,
    897877  => 1,
    495971  => 1,
    648546  => 1,
    1131341  => 2,
    311190  => 2,
    1183832  => 2,
    442804  => 2,
    442518  => 2,
    511934  => 2,
    492549  => 2,
    1132313  => 2,
    492531  => 2,
    601149  => 2,
    1183839  => 2,
    489237  => 2,
    386536  => 2,
    649482  => 1,
    1132315  => 2,
    650835  => 1,
    564210  => 2,
    1137519  => 1,
    1071519  => 2,
    1131346  => 2,
    1181277  => 2,
    498602  => 2,
    591155  => 2,
    490717  => 2,
    932337  => 2,
    319468  => 2,
    443839  => 2,
    788666  => 2,
    1076957 => 1,
    1076974 => 1,
    451211 => 2,
    1077049 => 1,
    306320 => 1,
    785250 => 1,
    785162 => 1,
    530765 => 1,
    1127359 => 2,
    441572 => 2,
    119453 => 3,
    933778 => 1,
    652520 => 1,
    307141 => 1,
    785209 => 1,
    934975 => 1,
    934987 => 2,
    457461 => 1,
    496217 => 2,
    319470 => 1,
    437172 => 2,
    322275 => 1,
    600716 => 1,
    680233 => 1,
    567639 => 1,
    1132795 => 1,
    1184469 => 1,
    490710 => 1,
    600719 => 1,
    1217600 => 1,
    1072290 => 1,
    1072326 => 1,
    1072289 => 1,
    437168 => 1,
    490716 => 1,
    1137536 => 1,
    44182 => 1,
    308125 => 1,
    1088539 => 1,
    653626 => 1,
    788429 => 1,
    307037 => 1,
    308556 => 1,
    441846 => 1,
    1088127 => 1,
    1093589 => 1,
    1088611 => 2,
    1132814 => 1,
    1073814 => 1,
    510322 => 1,
    1073786 => 1,
    831388 => 3,
    1077015 => 2,
    831416 => 3,
    776197 => 3,
    1240562 => 3,
    1175274 => 3,
    317057 => 3,
    1132298 => 2,
    1132312 => 2,
    1132079 => 1,
    438934 => 2,
    1217601 => 2,
    502301 => 2,
    897302 => 2,
    1129005 => 2,
    451267 => 1,
    1132051 => 1,
    1132898 => 2,
    1088559 => 2,
    1087908 => 1,
    1168340 => 1,
    1131349 => 1,
    1132807 => 1,
    1132089 => 1,
    531173 => 1,
    441820 => 2,
    786569 => 2,
    651306 => 2,
    786567 => 2,
    936328 => 1,
    569302 => 1,
    817055 => 1,
    352876 => 1,
    1087777 => 1,
    451747 => 1,
    600681 => 1,
    796503 => 1,
    796507 => 1,
    1089249 => 1,
    1130413 => 1,
    1087784 => 1,
    1087782 => 1,
    648959 => 1,
    452814 => 1,
    1061555 => 1,
    1061545 => 1,
    1061534 => 1,
    437993 => 1,
    443849 => 1,
    438950 => 1,
    1130590 => 1,
    441884 => 1,
    786818 => 1,
    786854 => 1,
    440299 => 1,
    936330 => 1,
    1130599 => 1,
    1127283 => 1,
    1130544 => 1,
    1127374 => 1,
    936307 => 1,
    929693 => 1,
    784395 => 1,
    649547 => 1,
    649567 => 1,
    1190941 => 1,
    437243 => 1,
    929569 => 1,
    936353 => 1,
    1168473 => 1,
    1170354 => 1,
    1170083 => 1,
    649370 => 1,
    929645 => 1,
    351567 => 1,
    929534 => 1,
    936340 => 1,
    929690 => 1,
    929662 => 1,
    1263255 => 2,
    1263028 => 1,
    1263029 => 2,
    1263030 => 2,
    1263031 => 1,
    1263034 => 1,
    1263035 => 2,
    1263036 => 2,
    1263037 => 2,
    1263038 => 2,
    1263039 => 2,
    1263040 => 2,
    1263042 => 2,
    1263043 => 1,
    1263044 => 1,
    1263045 => 1,
    1263046 => 2,
    1263047 => 1,
    1263048 => 2,
    1263049 => 1,
    1263138 => 2,
    1263145 => 1,
    1263158 => 1,
    1263159 => 1,
    1263160 => 2,
    1263162 => 1,
    1263166 => 2,
    1263167 => 2,
    1263168 => 1,
    1263170 => 2,
    1263177 => 1,
    1263188 => 1,
    1263195 => 1,
    1263197 => 1,
    1263226 => 1,
    1263244 => 1,
    1263246 => 2,
    1263248 => 2,
    1260564 => 1,
    1260571 => 2,
    1260565 => 2,
    1260561 => 1,
    1260560 => 1,
    1260575 => 1,
    1260563 => 2,
    1260581 => 1,
    1260591 => 2,
    1260569 => 1,
    1260588 => 1,
    1260590 => 1,
    1260574 => 1,
    1260596 => 1,
    1260592 => 1,
    1260567 => 1,
    1260570 => 1,
    1260573 => 1,
    1260583 => 1,
    1260576 => 1,
    1260572 => 1,
    1260580 => 1,
    1260582 => 1,
    1260589 => 1,
    1260594 => 1,
    1260597 => 1,
    1260579 => 1,
    1260598 => 1,
    1260599 => 1,
    1260600 => 1,
    1260585 => 2,
    1260647 => 3,
    1260648 => 3,
    1261767 => 1,
    1261783 => 1,
    1261785 => 1,
    1261786 => 1,
    1262196 => 1,
    1262199 => 1,
    1262202 => 1,
    1262203 => 1,
    1262204 => 1,
    1262204 => 1,
    1262205 => 2,
    1262206 => 1,
    1262216 => 2,
    1262211 => 2,
    1262208 => 1,
    1262207 => 1,
    1262217 => 2,
    1262224 => 1,
    1262225 => 1,
    1262226 => 1,
    1262227 => 1,
    1262228 => 1,
    1262230 => 1,
    1262229 => 1,
    1262231 => 1,
    1262287 => 1,
    1262288 => 1,
    1260255 => 1,
    1260199 => 1,
    1260247 => 1,
    1260286 => 1,
    1260288 => 1,
    1260287 => 1,
    1260203 => 1,
    1260209 => 1,
    1260206 => 1,
    1260211 => 1,
    1260214 => 1,
    1260217 => 1,
    1260222 => 1,
    1260224 => 1,
    1260229 => 1,
    1260133 => 1,
    1260232 => 1,
    1260290 => 1,
    1260266 => 1,
    1260240 => 1,
    1260833 => 1,
    1260836 => 2,
    1260834 => 1,
    1260142 => 1,
    1260290 => 1,
    1260266 => 1,
    1260240 => 1,
    1260242 => 1,
    1260246 => 1,
    1260249 => 1,
    1260180 => 1,
    1260180 => 1,
    1260252 => 2,
    1260289 => 1,
    1260371 => 2,
    1260372 => 2,
    1260292 => 1,
    1260254 => 1,
    1260228 => 1,
    1260291 => 1,
    1260134 => 1,
    1260197 => 1,
    1260258 => 1,
    1260235 => 1,
    1260186 => 1,
    1260173 => 1,
    1260293 => 1,
    1260260 => 1,
    1260154 => 1,
    1260554 => 1,
    1260555 => 1,
    1260557 => 1,
    1260559 => 1,
    1260558 => 1,
    1260142 => 1,
    1259374 => 2,
    1259384 => 2,
    1259498 => 2,
    1259497 => 2,
    1259491 => 2,
    1259494 => 2,
    1259487 => 2,
    1259447 => 2,
    1259493 => 2,
    1259492 => 2,
    1259488 => 2,
    1259481 => 2,
    1259470 => 2,
    1259476 => 2,
    1259360 => 2,
    1259278 => 2,
    1259324 => 2,
    1259312 => 2,
    1259429 => 2,
    1259419 => 2,
    1259400 => 2,
    1259393 => 2,
    1259362 => 2,
    1259353 => 2,
    1259343 => 2,
    1259297 => 2,
    1259467 => 2,
    1259463 => 2,
    1259461 => 2,
    1259320 => 2,
    1259460 => 2,
    1259456 => 2,
    1259445 => 2,
    1259449 => 2,
    1259412 => 2,
    1259420 => 2,
    1259428 => 2,
    1259437 => 2,
    1259490 => 2,
    1259391 => 2,
    1259332 => 2,
    1259307 => 1,
    1259363 => 2,
    1259344 => 2,
    1259434 => 2,
    1259399 => 2,
    1259407 => 2,
    1259418 => 2,
    1259443 => 2,
    1259451 => 2,
    1259440 => 2,
    1259455 => 2,
    1259466 => 2,
    1259442 => 2,
    1259462 => 2,
    1259457 => 2,
    1259473 => 2,
    1259355 => 2,
    1259345 => 2,
    1259477 => 2,
    1259367 => 2,
    1259485 => 2,
    1259376 => 2,
    1259383 => 2,
    1259389 => 2,
    1259396 => 2,
    1259489 => 2,
    1259402 => 2,
    1259408 => 2,
    1259415 => 2,
    1259421 => 2,
    1259221 => 2,
    1259444 => 2,
    1259375 => 2,
    1259469 => 2,
    1259425 => 2,
    1260159 => 2,
    1260163 => 2,
    1260245 => 1,
    1260278 => 1,
    1260250 => 2,
    1260165 => 1,
    1260202 => 1,
    1260268 => 1,
    1260207 => 1,
    1260210 => 2,
    1260191 => 1,
    1260212 => 1,
    1260226 => 1,
    1260243 => 1,
    1260179 => 1,
    1260264 => 1,
    1260267 => 2,
    1260269 => 1,
    1260182 => 1,
    1260241 => 2,
];

// --- Subcategorie mapping ---
$productSubcategoryMap = [
    1138002 => 20,
    306246 => 2,
    441567 => 1,
    452825 => 20,
    1132319 => 22,
    1132054 => 8,
    531156 => 5,
    344493 => 1,
    1071278 => 2,
    721878 => 27,
    1132065 => 8,
    306387 => 2,
    137931 => 27,
    1178089 => 23,
    762393 => 2,
    491878 => 8,
    305825 => 7,
    1077010 => 7,
    490718 => 1,
    1225697 => 27,
    1137530 => 7,
    1217043 => 7,
    1061307 => 11,
    311992 => 11,
    438008 => 2,
    490702 => 1,
    1045386 => 2,
    1225978 => 2,
    314084 => 20,
    564026 => 7,
    897326 => 7,
    897297 => 8,
    897286 => 7,
    1076918 => 21,
    796505 => 7,
    491884 => 5,
    786802 => 7,
    453855 => 5,
    491885 => 7,
    1077058 => 7,
    1183836 => 20,
    442781 => 7,
    490722 => 13,
    1076968 => 7,
    1076902 => 7,
    1076897 => 7,
    1077050 => 7,
    308073 => 13,
    353977 => 1,
    496216 => 8,
    1137526 => 7,
    1180738 => 5,
    441806 => 21,
    308557 => 2,
    438607 => 2,
    440549 => 9,
    440599 => 9,
    433534 => 27,
    1131343 => 7,
    1087906 => 8,
    451262 => 7,
    379926 => 6,
    649520 => 1,
    451271 => 5,
    1071091 => 2,
    893982 => 1,
    929593 => 1,
    649556 => 1,
    649560 => 1,
    929563 => 1,
    499198 => 1,
    652417 => 1,
    652409 => 1,
    1127578 => 2,
    1071290 => 2,
    1071271 => 2,
    1184477 => 2,
    897877 => 2,
    495971 => 5,
    648546 => 5,
    1131341 => 20,
    311190 => 20,
    1183832 => 20,
    442804 => 20,
    442518 => 20,
    511934 => 20,
    492549 => 20,
    1132313 => 20,
    492531 => 20,
    601149 => 19,
    1183839 => 20,
    489237 => 20,
    386536 => 20,
    649482 => 6,
    1132315 => 19,
    650835 => 5,
    564210 => 19,
    1137519 => 5,
    1071519 => 13,
    1131346 => 23,
    1181277 => 15,
    498602 => 13,
    591155 => 13,
    490717 => 13,
    932337 => 13,
    319468 => 13,
    443839 => 15,
    788666 => 15,
    1076957 => 7,
    1076974 => 7,
    451211 => 21,
    1077049 => 7,
    306320 => 5,
    785250 => 1,
    785162 => 1,
    530765 => 1,
    1127359 => 13,
    441572 => 13,
    119453 => 27,
    933778 => 1,
    652520 => 13,
    307141 => 1,
    785209 => 1,
    934975 => 1,
    934987 => 13,
    457461 => 7,
    496217 => 21,
    319470 => 1,
    437172 => 13,
    322275 => 1,
    600716 => 1,
    680233 => 8,
    567639 => 7,
    1132795 => 2,
    1184469 => 2,
    490710 => 1,
    600719 => 1,
    1217600 => 7,
    1072290 => 7,
    1072326 => 7,
    1072289 => 2,
    437168 => 1,
    490716 => 1,
    1137536 => 7,
    44182 => 5,
    308125 => 8,
    1088539 => 8,
    653626 => 2,
    788429 => 2,
    307037 => 2,
    308556 => 2,
    441846 => 5,
    1088127 => 7,
    1093589 => 2,
    1088611 => 15,
    1132814 => 9,
    1073814 => 9,
    510322 => 9,
    1073786 => 9,
    831388 => 27,
    1077015 => 23,
    831416 => 27,
    776197 => 27,
    1240562 => 27,
    1175274 => 27,
    317057 => 27,
    1132298 => 21,
    1132312 => 21,
    1132079 => 7,
    438934 => 15,
    1217601 => 15,
    502301 => 21,
    897302 => 22,
    1129005 => 21,
    451267 => 7,
    1132051 => 7,
    1132898 => 21,
    1088559 => 21,
    1087908 => 7,
    1168340 => 5,
    1131349 => 5,
    1132807 => 7,
    1132089 => 7,
    531173 => 7,
    441820 => 21,
    786569 => 21,
    651306 => 21,
    786567 => 21,
    936328 => 8,
    569302 => 5,
    817055 => 1,
    352876 => 7,
    1087777 => 5,
    451747 => 5,
    600681 => 5,
    796503 => 5,
    796507 => 2,
    1089249 => 5,
    1130413 => 11,
    1087784 => 5,
    1087782 => 5,
    648959 => 5,
    452814 => 5,
    1061555 => 11,
    1061545 => 11,
    1061534 => 11,
    437993 => 2,
    443849 => 2,
    438950 => 2,
    1130590 => 1,
    441884 => 1,
    786818 => 2,
    786854 => 2,
    440299 => 2,
    936330 => 1,
    1130599 => 1,
    1127283 => 1,
    1130544 => 1,
    1127374 => 1,
    936307 => 1,
    929693 => 1,
    784395 => 1,
    649547 => 1,
    649567 => 1,
    1190941 => 1,
    437243 => 1,
    929569 => 1,
    936353 => 1,
    1168473 => 1,
    1170354 => 1,
    1170083 => 1,
    649370 => 1,
    929645 => 1,
    351567 => 1,
    929534 => 1,
    936340 => 1,
    929690 => 1,
    929662 => 1,
    1263255 => 25,
    1263028 => 11,
    1263029 => 25,
    1263030 => 25,
    1263031 => 11,
    1263034 => 11,
    1263035 => 25,
    1263036 => 25,
    1263037 => 25,
    1263038 => 25,
    1263039 => 25,
    1263040 => 25,
    1263042 => 25,
    1263043 => 11,
    1263044 => 11,
    1263045 => 11,
    1263046 => 25,
    1263047 => 11,
    1263048 => 25,
    1263049 => 11,
    1263138 => 25,
    1263145 => 11,
    1263158 => 11,
    1263159 => 11,
    1263160 => 25,
    1263162 => 11,
    1263166 => 25,
    1263167 => 25,
    1263168 => 11,
    1263170 => 25,
    1263177 => 5,
    1263188 => 11,
    1263195 => 2,
    1263197 => 11,
    1263226 => 11,
    1263244 => 11,
    1263246 => 25,
    1263248 => 25,
    1260564 => 5,
    1260571 => 20,
    1260565 => 15,
    1260561 => 2,
    1260560 => 10,
    1260575 => 5,
    1260563 => 19,
    1260581 => 5,
    1260591 => 19,
    1260569 => 5,
    1260588 => 5,
    1260590 => 5,
    1260574 => 5,
    1260596 => 5,
    1260592 => 5,
    1260567 => 5,
    1260570 => 5,
    1260573 => 5,
    1260583 => 5,
    1260576 => 5,
    1260572 => 5,
    1260580 => 5,
    1260582 => 5,
    1260589 => 5,
    1260594 => 5,
    1260597 => 5,
    1260579 => 5,
    1260598 => 5,
    1260599 => 5,
    1260600 => 5,
    1260585 => 14,
    1260647 => 33,
    1260648 => 33,
    1261767 => 11,
    1261783 => 11,
    1261785 => 2,
    1261786 => 2,
    1262196 => 11,
    1262199 => 11,
    1262202 => 11,
    1262203 => 11,
    1262204 => 11,
    1262204 => 11,
    1262205 => 25,
    1262206 => 11,
    1262216 => 25,
    1262211 => 25,
    1262208 => 11,
    1262207 => 11,
    1262217 => 25,
    1262224 => 11,
    1262225 => 11,
    1262226 => 11,
    1262227 => 11,
    1262228 => 11,
    1262230 => 11,
    1262229 => 11,
    1262231 => 11,
    1262287 => 11,
    1262288 => 11,
    1260255 => 1,
    1260199 => 1,
    1260247 => 1,
    1260286 => 1,
    1260288 => 1,
    1260287 => 1,
    1260203 => 1,
    1260209 => 1,
    1260206 => 1,
    1260211 => 1,
    1260214 => 1,
    1260217 => 1,
    1260222 => 1,
    1260224 => 1,
    1260229 => 1,
    1260133 => 1,
    1260232 => 1,
    1260290 => 1,
    1260266 => 1,
    1260240 => 1,
    1260833 => 12,
    1260836 => 26,
    1260834 => 12,
    1260142 => 1,
    1260290 => 1,
    1260266 => 1,
    1260240 => 1,
    1260242 => 1,
    1260246 => 1,
    1260249 => 1,
    1260180 => 1,
    1260180 => 1,
    1260252 => 13,
    1260289 => 1,
    1260371 => 26,
    1260372 => 26,
    1260292 => 1,
    1260254 => 1,
    1260228 => 1,
    1260291 => 1,
    1260134 => 1,
    1260197 => 1,
    1260258 => 1,
    1260235 => 1,
    1260186 => 1,
    1260173 => 1,
    1260293 => 1,
    1260260 => 1,
    1260154 => 11,
    1260554 => 10,
    1260555 => 10,
    1260557 => 10,
    1260559 => 10,
    1260558 => 10,
    1260142 => 1,
    1259374 => 25,
    1259384 => 25,
    1259498 => 25,
    1259497 => 25,
    1259491 => 25,
    1259494 => 25,
    1259487 => 25,
    1259447 => 25,
    1259493 => 25,
    1259492 => 25,
    1259488 => 25,
    1259481 => 25,
    1259470 => 25,
    1259476 => 25,
    1259360 => 25,
    1259278 => 25,
    1259324 => 25,
    1259312 => 25,
    1259429 => 25,
    1259419 => 25,
    1259400 => 25,
    1259393 => 25,
    1259362 => 25,
    1259353 => 25,
    1259343 => 25,
    1259297 => 25,
    1259467 => 25,
    1259463 => 25,
    1259461 => 25,
    1259320 => 25,
    1259460 => 25,
    1259456 => 25,
    1259445 => 25,
    1259449 => 25,
    1259412 => 25,
    1259420 => 25,
    1259428 => 25,
    1259437 => 25,
    1259490 => 25,
    1259391 => 25,
    1259332 => 25,
    1259307 => 11,
    1259363 => 25,
    1259344 => 25,
    1259434 => 25,
    1259399 => 25,
    1259407 => 25,
    1259418 => 25,
    1259443 => 25,
    1259451 => 25,
    1259440 => 25,
    1259455 => 25,
    1259466 => 25,
    1259442 => 25,
    1259462 => 25,
    1259457 => 25,
    1259473 => 25,
    1259355 => 25,
    1259345 => 25,
    1259477 => 25,
    1259367 => 25,
    1259485 => 25,
    1259376 => 25,
    1259383 => 25,
    1259389 => 25,
    1259396 => 25,
    1259489 => 25,
    1259402 => 25,
    1259408 => 25,
    1259415 => 25,
    1259421 => 25,
    1259221 => 25,
    1259444 => 25,
    1259375 => 25,
    1259469 => 25,
    1259425 => 25,
    1260159 => 25,
    1260163 => 25,
    1260245 => 11,
    1260278 => 11,
    1260250 => 25,
    1260165 => 11,
    1260202 => 11,
    1260268 => 11,
    1260207 => 11,
    1260210 => 25,
    1260191 => 11,
    1260212 => 1,
    1260226 => 1,
    1260243 => 1,
    1260179 => 1,
    1260264 => 1,
    1260267 => 13,
    1260269 => 1,
    1260182 => 1,
    1260241 => 13,
];

echo "=== DEBUG MODE ACTIEF ===\n\n";

// ---------------------------------------------------------
// 0) STOCK FILTER (ACTIEF)
// ---------------------------------------------------------
echo "=== START STOCK FILTER ===\n\n";

$filteredProducts = [];
$processedProducts = [];

foreach ($selectedProducts as $productId) {
    echo "🟦 Controleer product $productId...\n";

    // --- 12u import guard ---
    $stmtCheck = $conn->prepare("
        SELECT last_import_run
        FROM products
        WHERE id = ?
        LIMIT 1
    ");
    $stmtCheck->bind_param("i", $productId);
    $stmtCheck->execute();
    $resCheck = $stmtCheck->get_result();
    $rowCheck = $resCheck->fetch_assoc();
    $stmtCheck->close();

    if ($rowCheck) {
        $lastRun = $rowCheck['last_import_run'];

        if ($lastRun !== null && strtotime($lastRun) > strtotime('-12 hours')) {
            echo "⏭ Product $productId minder dan 12u geleden geïmporteerd → behouden\n\n";
            $processedProducts[] = $productId; // 👈 essentieel
            continue;
        }
    }

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
        if (!$productTaxonomyId) {
            logMessage("⛔ Geen taxonomy gevonden voor product $productId\n");
            sleep(5);
            continue;
        }

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

        // --- 7b) Check of alle varianten zonder stock zitten
        if (!empty($variationsResp) && empty($variants)) {
            // Varianten bestaan, maar geen enkele heeft stock
            $active = 0;
            echo "⚠ Product $productId heeft varianten maar GEEN stock → active=0\n";
        } else {
            // Geen varianten, of minstens 1 variant met stock
            $active = 1;
        }

        // --- 7c) Maak één string van alle attributen ---
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
            'image'=>$image,
            'active'=>$active
        ];

        // ----------------- Insert/Update DB -----------------
        $stmt=$conn->prepare("
            INSERT INTO products (id,category_id,subcategory_id,name,description,specifications,maat,price,image,created_at,active)
            VALUES (?,?,?,?,?,?,?,?,?,NOW(),?)
            ON DUPLICATE KEY UPDATE
                category_id=VALUES(category_id),
                subcategory_id=VALUES(subcategory_id),
                name=VALUES(name),
                description=VALUES(description),
                specifications=VALUES(specifications),
                maat=NULLIF(VALUES(maat), ''),
                price=VALUES(price),
                image=VALUES(image),
                active=VALUES(active)
        ");
        $stmt->bind_param(
            "iiissssdsi",
            $dataForDb['id'],
            $dataForDb['category_id'],
            $dataForDb['subcategory_id'],
            $dataForDb['name'],
            $dataForDb['description'],
            $dataForDb['specifications'],
            $dataForDb['maat'],
            $dataForDb['price'],
            $dataForDb['image'],
            $dataForDb['active']
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

        $stmtUpdateRun = $conn->prepare("
            UPDATE products
            SET last_import_run = NOW()
            WHERE id = ?
        ");
        $stmtUpdateRun->bind_param("i", $productId);
        $processedProducts[] = $productId;
        $stmtUpdateRun->execute();
        $stmtUpdateRun->close();

    } catch(Exception $e){
        echo "⛔ Fout bij product $productId: ".$e->getMessage()."\n";
        logMessage("⛔ Fout bij product $productId: ".$e->getMessage());
    }

    unset($variationsResp, $varAttr, $varAttrMap, $attrList, $attrMap, $variants, $allAttributes);
    gc_collect_cycles();

    sleep(5);
}

// ----------------------------------------
// 3) Niet-verwerkte producten deactiveren
// ----------------------------------------
if (!empty($processedProducts)) {

    $placeholders = implode(',', array_fill(0, count($processedProducts), '?'));
    $types = str_repeat('i', count($processedProducts));

    $stmtInactive = $conn->prepare("
        UPDATE products
        SET active = 0
        WHERE id NOT IN ($placeholders)
    ");

    $stmtInactive->bind_param($types, ...$processedProducts);
    $stmtInactive->execute();
    $stmtInactive->close();

    echo "✔ Alle niet-verwerkte producten gedeactiveerd (active=0)\n";
    logMessage("✔ Niet-verwerkte producten gedeactiveerd");
}

echo "\n=== IMPORT VOLTOOID ===\n";
?>