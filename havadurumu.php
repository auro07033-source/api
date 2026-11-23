<?php
// turkiye_gov_hava.php - 81 il için 5 günlük hava tahmini
// Kullanım:
//   php turkiye_gov_hava.php           -> tüm 81 il
//   php turkiye_gov_hava.php 10        -> sadece Balıkesir
//   php turkiye_gov_hava.php 6 34 35   -> seçili iller
//   php turkiye_gov_hava.php --txt     -> sonucu hava.txt dosyasına da yazar
// Gereksinim: PHP 7.4+, curl ve dom eklentileri

$BASE = "https://www.turkiye.gov.tr";
$URL  = $BASE . "/5-gunluk-hava-tahmini";

// Tüm 81 il (plaka kodu => il adı)
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

// ---------- Yardımcılar ----------
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

function token_al($ch, string $url, array $headers): array {
    $html = istek_retry($ch, "GET", $url, $headers);
    if ($html === null) return [null, null];
    $token = $unique = null;
    if (preg_match('/data-token=["\']([^"\']+)/', $html, $m))  $token  = $m[1];
    if (preg_match('/data-unique=["\']([^"\']+)/', $html, $m)) $unique = $m[1];
    return [$token, $unique];
}

function temizle(string $s): string {
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function ozetle(string $html): string {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tablo = $xpath->query("//table[contains(@class,'resultTable')]")->item(0)
          ?: $xpath->query("//table")->item(0);
    if (!$tablo) return "[i] Tahmin tablosu bulunamadı.\n";

    $out = "";
    $basliklar = [];
    foreach ($xpath->query(".//th", $tablo) as $th) {
        $basliklar[] = temizle($th->textContent);
    }
    if ($basliklar) {
        $out .= implode(" | ", $basliklar) . "\n";
        $out .= str_repeat("-", 60) . "\n";
    }
    foreach ($xpath->query(".//tr", $tablo) as $tr) {
        $cells = $xpath->query("./td", $tr);
        if ($cells->length === 0) continue;

        $sehir = temizle($cells->item(0)->textContent);
        $satir = [$sehir];
        for ($i = 1; $i < $cells->length; $i += 2) {
            $ikon  = $xpath->query(".//img", $cells->item($i))->item(0);
            $durum = $ikon ? temizle($ikon->getAttribute("alt")) : "";
            $detay = ($i+1 < $cells->length) ? temizle($cells->item($i+1)->textContent) : "";
            $satir[] = "$durum ($detay)";
        }
        $out .= implode(" | ", $satir) . "\n";
    }
    return $out;
}

// ---------- Ana akış ----------
$txtYaz = false;
$args = isset($_GET['il']) ? [$_GET['il']] : [];

$cleanArgs = [];
foreach ($args as $a) {
    if ($a === "--txt") $txtYaz = true;
    else $cleanArgs[] = $a;
}

// Argüman yoksa tüm 81 il
if (empty($cleanArgs)) {
    $cleanArgs = array_keys($PLAKA);
    // Tuşları string yap
    $cleanArgs = array_map('strval', $cleanArgs);
}

$toplam = count($cleanArgs);
echo "[*] $toplam il sorgulanıyor\n";
echo "[*] Başlangıç: " . date("H:i:s") . "\n\n";

$cikti = "";
$baslangic = microtime(true);

foreach ($cleanArgs as $idx => $plaka) {
    $il = $PLAKA[$plaka] ?? "?";
    $satirBaslik = "=== [$plaka] $il (" . ($idx+1) . "/$toplam) ===";
    echo $satirBaslik . "\n";
    $cikti .= $satirBaslik . "\n";

    $cookiejar = tempnam(sys_get_temp_dir(), "edev_");
    $ch = yeni_curl($cookiejar);
    $basari = false;

    try {
        [$token, $unique] = token_al($ch, $URL, $HEADERS);
        if (!$token) {
            $msg = "[-] data-token alınamadı.\n";
            echo $msg; $cikti .= $msg;
        } else {
            $post = http_build_query(["il"=>$plaka,"token"=>$token,"il_btn"=>""]);
            $postHeaders = array_merge($HEADERS, [
                "Content-Type: application/x-www-form-urlencoded",
            ]);
            $html = istek_retry($ch, "POST", $URL . "?submit", $postHeaders, $post);
            if ($html !== null) {
                $ozet = ozetle($html);
                echo $ozet; $cikti .= $ozet;
                $basari = true;
            } else {
                $msg = "[-] POST isteği başarısız.\n";
                echo $msg; $cikti .= $msg;
            }
        }
    } catch (Throwable $e) {
        $msg = "[-] Hata: " . $e->getMessage() . "\n";
        echo $msg; $cikti .= $msg;
    } finally {
        curl_close($ch);
        @unlink($cookiejar);
    }

    echo "\n";
    $cikti .= "\n";

    // Siteyi yormamak için aralık (son eleman hariç)
    if ($idx < $toplam - 1) sleep(1);
}

$sure = round(microtime(true) - $baslangic, 1);
$ozet = "[*] Bitti. Süre: {$sure} sn | Tarih: " . date("Y-m-d H:i:s") . "\n";
echo $ozet;
$cikti .= $ozet;

// --txt ile dosyaya yaz
if ($txtYaz) {
    file_put_contents(__DIR__ . "/hava.txt", $cikti);
    echo "[*] Sonuç: " . __DIR__ . "/hava.txt\n";
}