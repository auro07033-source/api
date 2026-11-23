<?php
// havadurumu.php - 81 il 5 günlük hava tahmini, JSON çıktı
// Kullanım:
//   /havadurumu.php?il=34          -> tek il
//   /havadurumu.php?il=6,34,35     -> birden çok il (virgülle)
//   /havadurumu.php?il=all         -> 81 il
// Gereksinim: PHP 7.4+, curl, dom

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$BASE = "https://www.turkiye.gov.tr";
$URL  = "$BASE/5-gunluk-hava-tahmini";

$PLAKA = [
    "1"=>"Adana","2"=>"Adıyaman","3"=>"Afyonkarahisar","4"=>"Ağrı","5"=>"Amasya",
    "6"=>"Ankara","7"=>"Antalya","8"=>"Artvin","9"=>"Aydın","10"=>"Balıkesir",
    "11"=>"Bilecik","12"=>"Bingöl","13"=>"Bitlis","14"=>"Bolu","15"=>"Burdur",
    "16"=>"Bursa","17"=>"Çanakkale","18"=>"Çankırı","19"=>"Çorum","20"=>"Denizli",
    "21"=>"Diyarbakır","22"=>"Edirne","23"=>"Elazığ","24"=>"Erzincan","25"=>"Erzurum",
    "26"=>"Eskişehir","27"=>"Gaziantep","28"=>"Giresun","29"=>"Gümüşhane","30"=>"Hakkari",
    "31"=>"Hatay","32"=>"Isparta","33"=>"Mersin","34"=>"İstanbul","35"=>"İzmir",
    "36"=>"Kars","37"=>"Kastamonu","38"=>"Kayseri","39"=>"Kırklareli","40"=>"Kırşehir",
    "41"=>"Kocaeli","42"=>"Konya","43"=>"Kütahya","44"=>"Malatya","45"=>"Manisa",
    "46"=>"Kahramanmaraş","47"=>"Mardin","48"=>"Muğla","49"=>"Muş","50"=>"Nevşehir",
    "51"=>"Niğde","52"=>"Ordu","53"=>"Rize","54"=>"Sakarya","55"=>"Samsun",
    "56"=>"Siirt","57"=>"Sinop","58"=>"Sivas","59"=>"Tekirdağ","60"=>"Tokat",
    "61"=>"Trabzon","62"=>"Tunceli","63"=>"Şanlıurfa","64"=>"Uşak","65"=>"Van",
    "66"=>"Yozgat","67"=>"Zonguldak","68"=>"Aksaray","69"=>"Bayburt","70"=>"Karaman",
    "71"=>"Kırıkkale","72"=>"Batman","73"=>"Şırnak","74"=>"Bartın","75"=>"Ardahan",
    "76"=>"Iğdır","77"=>"Yalova","78"=>"Karabük","79"=>"Kilis","80"=>"Osmaniye",
    "81"=>"Düzce",
];

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language: tr-TR",
    "Referer: $URL",
    "Origin: $BASE",
    "Connection: close",
];

function yeni_curl(string $cookiejar) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
        CURLOPT_COOKIEJAR      => $cookiejar,
        CURLOPT_COOKIEFILE     => $cookiejar,
    ]);
    return $ch;
}

function istek_retry($ch, string $method, string $url, array $headers, $postData = null) {
    for ($i = 0; $i < 4; $i++) {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } else {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($body !== false && $code === 200) return $body;
        sleep(1 + $i);
    }
    return null;
}

function token_al($ch, string $url, array $headers): ?string {
    $html = istek_retry($ch, "GET", $url, $headers);
    if ($html === null) return null;
    if (preg_match('/data-token=["\']([^"\']+)/', $html, $m)) return $m[1];
    return null;
}

function temizle(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/**
 * HTML tablodan günleri ayrıştırıp dizi döndürür.
 * Çıktı: ["sehir"=>"...", "gunler"=>[ ["gun"=>"Salı","tarih"=>"24 Kasım","durum"=>"...","min"=>6,"max"=>9], ... ]]
 */
function parse_tablo(string $html): ?array {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    $tablo = $xp->query("//table[contains(@class,'resultTable')]")->item(0)
          ?: $xp->query("//table")->item(0);
    if (!$tablo) return null;

    // Başlıklar (günler)
    $gunler = [];
    foreach ($xp->query(".//thead//th", $tablo) as $th) {
        $gunler[] = temizle($th->textContent);
    }
    // İlk başlık "Şehir", kalanlar günler
    array_shift($gunler);

    // Veri satırı (tek il)
    $tr = $xp->query(".//tbody//tr", $tablo)->item(0)
       ?: $xp->query(".//tr", $tablo)->item(1);
    if (!$tr) return null;

    $cells = $xp->query("./td", $tr);
    if ($cells->length < 1) return null;

    $sehir = temizle($cells->item(0)->textContent);
    $sonuc = [];

    $g = 0;
    for ($i = 1; $i < $cells->length; $i += 2) {
        $ikon  = $xp->query(".//img", $cells->item($i))->item(0);
        $durum = $ikon ? temizle($ikon->getAttribute("alt")) : "";

        $detay = ($i+1 < $cells->length) ? temizle($cells->item($i+1)->textContent) : "";
        // Örnek: "24 Kasım 6 / 9 °C"
        $tarih = "";
        $min = $max = null;
        if (preg_match('/^(\d+\s+\S+)\s+(-?\d+)\s*\/\s*(-?\d+)/u', $detay, $m)) {
            $tarih = $m[1];
            $min   = (int)$m[2];
            $max   = (int)$m[3];
        }

        $sonuc[] = [
            "gun"   => $gunler[$g] ?? ("Gün " . ($g+1)),
            "tarih" => $tarih,
            "durum" => $durum,
            "min"   => $min,
            "max"   => $max,
            "ham"   => $detay,
        ];
        $g++;
    }

    return ["sehir" => $sehir, "gunler" => $sonuc];
}

// ---------- Parametre ----------
$ilParam = $_GET['il'] ?? null;

if ($ilParam === null || $ilParam === '') {
    echo json_encode([
        "success" => false,
        "message" => "il parametresi gerekli. Örnek: ?il=34 veya ?il=all veya ?il=6,34,35",
        "kullanilan_plakalar" => array_keys($PLAKA),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($ilParam === 'all') {
    $istekler = array_map('strval', array_keys($PLAKA));
} else {
    $istekler = array_filter(array_map('trim', explode(',', $ilParam)), fn($x) => $x !== '');
    $istekler = array_values($istekler);
}

$baslangic = microtime(true);
$sonuclar  = [];
$hatalar   = [];

foreach ($istekler as $plaka) {
    if (!isset($PLAKA[$plaka])) {
        $hatalar[] = ["plaka" => $plaka, "hata" => "Geçersiz plaka"];
        continue;
    }

    $cookiejar = tempnam(sys_get_temp_dir(), "edev_");
    $ch = yeni_curl($cookiejar);

    try {
        $token = token_al($ch, $URL, $HEADERS);
        if (!$token) {
            $hatalar[] = ["plaka" => $plaka, "hata" => "token alınamadı"];
            continue;
        }
        $post = http_build_query([
            "il"     => $plaka,
            "token"  => $token,
            "il_btn" => "",
        ]);
        $postHeaders = array_merge($HEADERS, [
            "Content-Type: application/x-www-form-urlencoded",
        ]);
        $html = istek_retry($ch, "POST", $URL . "?submit", $postHeaders, $post);
        if ($html === null) {
            $hatalar[] = ["plaka" => $plaka, "hata" => "POST başarısız"];
            continue;
        }
        $parsed = parse_tablo($html);
        if ($parsed) {
            $sonuclar[] = [
                "plaka" => (int)$plaka,
                "il"    => $PLAKA[$plaka],
            ] + $parsed;
        } else {
            $hatalar[] = ["plaka" => $plaka, "hata" => "tablo ayrıştırılamadı"];
        }
    } catch (Throwable $e) {
        $hatalar[] = ["plaka" => $plaka, "hata" => $e->getMessage()];
    } finally {
        curl_close($ch);
        @unlink($cookiejar);
    }

    // Siteyi yormamak için aralık
    if (count($istekler) > 1) sleep(1);
}

$sure = round(microtime(true) - $baslangic, 2);

echo json_encode([
    "success"      => true,
    "sorgu_sayisi" => count($istekler),
    "basarili"     => count($sonuclar),
    "hatali"       => count($hatalar),
    "sure_sn"      => $sure,
    "tarih"        => date("Y-m-d H:i:s"),
    "sonuclar"     => $sonuclar,
    "hatalar"      => $hatalar,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);