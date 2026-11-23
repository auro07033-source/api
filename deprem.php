<?php
// kandilli_deprem.php - Kandilli son depremler, JSON çıktı
// Kullanım:
//   /kandilli_deprem.php                 -> son 50 deprem
//   /kandilli_deprem.php?limit=20        -> son 20
//   /kandilli_deprem.php?min=3.0         -> ML/MW >= 3.0
//   /kandilli_deprem.php?limit=20&min=3  -> birleşik
// Gereksinim: PHP 7.4+, curl, mbstring

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$URL   = "http://www.koeri.boun.edu.tr/scripts/lst9.asp";
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
$min   = isset($_GET['min']) ? (float)$_GET['min'] : null;

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language: tr-TR",
    "Upgrade-Insecure-Requests: 1",
];

// --- HTTP ---
$ch = curl_init($URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_ENCODING       => "",
    CURLOPT_HTTPHEADER     => $HEADERS,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "HTTP $code",
        "error"   => $err,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Kodlama: windows-1254 -> UTF-8 ---
if (function_exists('mb_convert_encoding')) {
    $body = mb_convert_encoding($body, "UTF-8", "Windows-1254");
}

// --- <pre> içeriği ---
if (!preg_match('/<pre>(.*?)<\/pre>/is', $body, $m)) {
    echo json_encode([
        "success" => false,
        "message" => "pre bloğu bulunamadı",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}
$pre = $m[1];

// --- Yardımcı: "1.2" / "-.-" / ".-" ---
function _f($x) {
    $x = trim($x);
    if ($x === "" || $x === "-.-" || $x === ".-") return null;
    return is_numeric($x) ? (float)$x : null;
}

// --- Regex: tarih, saat, enlem, boylam, derinlik, md, ml, mw, yer + çözüm ---
// Örnek:
// 2026.11.24  02:24:18    38.309   31.953    3.6   -.-   0.6  .-   ORHANIYE-ILGIN (KONYA)   İlksel
$re = '/^'
    . '(\d{4}\.\d{2}\.\d{2})'      . '\s+'   // tarih
    . '(\d{2}:\d{2}:\d{2})'        . '\s+'   // saat
    . '(-?\d+\.\d+)'               . '\s+'   // enlem
    . '(-?\d+\.\d+)'               . '\s+'   // boylam
    . '(-?\d+\.\d+)'               . '\s+'   // derinlik
    . '(-\.-|[\d.]+)'              . '\s+'   // md
    . '(-\.-|[\d.]+)'              . '\s+'   // ml
    . '(-\.-|[\d.]+)'              . '\s+'   // mw
    . '(.+?)'                      . '\s{2,}'// yer
    . '(\S.*)?'                    . '$/u';  // çözüm

$depremler = [];
foreach (preg_split('/\r\n|\r|\n/', $pre) as $satir) {
    $satir = rtrim($satir);
    if ($satir === '' || trim($satir) === '') continue;
    if (stripos(ltrim($satir), 'Tarih') === 0) continue;
    if (preg_match('/^-{5,}/', $satir)) continue;

    if (!preg_match($re, $satir, $mm)) continue;

    $d = [
        "tarih"    => $mm[1],
        "saat"     => $mm[2],
        "enlem"    => _f($mm[3]),
        "boylam"   => _f($mm[4]),
        "derinlik" => _f($mm[5]),
        "md"       => _f($mm[6]),
        "ml"       => _f($mm[7]),
        "mw"       => _f($mm[8]),
        "yer"      => trim($mm[9]),
        "cozum"    => isset($mm[10]) && trim($mm[10]) !== '' ? trim($mm[10]) : "İlksel",
    ];

    if ($min !== null) {
        $buyuk = null;
        if ($d["ml"] !== null) $buyuk = $d["ml"];
        if ($d["mw"] !== null) {
            $buyuk = ($buyuk === null) ? $d["mw"] : max($buyuk, $d["mw"]);
        }
        if ($buyuk === null || $buyuk < $min) continue;
    }

    $depremler[] = $d;
    if (count($depremler) >= $limit) break;
}

echo json_encode([
    "success"  => true,
    "gelistirici"=> "@ittizar",
    "adet"     => count($depremler),
    "filtre"   => ["limit" => $limit, "min_mag" => $min],
    "depremler" => $depremler,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);