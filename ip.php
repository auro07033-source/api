<?php
// ip_toplu.php - Anahtarsız IP sorgu API'leri, tek sonuç altında toplar
// Kullanım:
//   /ip_toplu.php?ip=37.154.175.159
//   /ip_toplu.php?ip=37.154.175.159&raw=1   -> sağlayıcı detayları da gelir
// Gereksinim: PHP 7.4+, curl

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$ip  = $_GET['ip'] ?? null;
$raw = isset($_GET['raw']) && $_GET['raw'] === '1';

if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode([
        "success" => false,
        "message" => "geçerli ip parametresi gerekli",
        "ornek"   => "?ip=8.8.8.8",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
];
$TIMEOUT = 15;

// ---- Tekil istek yardımcısı ----
function http_get($url, $headers, $timeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

function http_post_json($url, $headers, $payload, $timeout) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
        CURLOPT_HTTPHEADER     => array_merge($headers, ["Content-Type: application/json"]),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

// ---- Yardımcı: değer boş mu? ----
function _bos($v) {
    return $v === null || $v === "" || $v === "-" || $v === [];
}

// ---- Sağlayıcılar ----
function sag_ipapi($ip, $H, $T) {
    [$b, $c, $e] = http_get("https://ipapi.co/{$ip}/json/", $H, $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j) || !empty($j['error'])) throw new Exception($j['reason'] ?? 'ipapi hata');
    return [
        "ulke"        => $j['country_name'] ?? null,
        "ulke_kodu"   => $j['country_code'] ?? null,
        "bolge"       => $j['region'] ?? null,
        "sehir"       => $j['city'] ?? null,
        "posta"       => $j['postal'] ?? null,
        "enlem"       => $j['latitude'] ?? null,
        "boylam"      => $j['longitude'] ?? null,
        "zaman_dilimi"=> $j['timezone'] ?? null,
        "isp"         => $j['org'] ?? null,
        "org"         => $j['org'] ?? null,
        "asn"         => $j['asn'] ?? null,
    ];
}

function sag_ipwhois($ip, $H, $T) {
    [$b, $c, $e] = http_get("https://ipwho.is/{$ip}", $H, $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j) || empty($j['success'])) throw new Exception($j['message'] ?? 'ipwhois hata');
    $conn = $j['connection'] ?? [];
    return [
        "ulke"        => $j['country'] ?? null,
        "ulke_kodu"   => $j['country_code'] ?? null,
        "bolge"       => $j['region'] ?? null,
        "sehir"       => $j['city'] ?? null,
        "posta"       => $j['postal'] ?? null,
        "enlem"       => $j['latitude'] ?? null,
        "boylam"      => $j['longitude'] ?? null,
        "zaman_dilimi"=> $j['timezone']['id'] ?? null,
        "isp"         => $conn['isp'] ?? null,
        "org"         => $conn['org'] ?? null,
        "asn"         => $conn['asn'] ?? null,
    ];
}

function sag_ipinfo($ip, $H, $T) {
    [$b, $c, $e] = http_get("https://ipinfo.io/{$ip}/json", $H, $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j)) throw new Exception("json hata");
    if (!empty($j['bogon'])) throw new Exception("bogon");
    $loc = $j['loc'] ?? ",";
    [$lat, $lon] = array_pad(explode(",", $loc, 2), 2, null);
    $org = $j['org'] ?? null;
    $asn = $org ? (explode(" ", $org)[0] ?? null) : null;
    return [
        "ulke"        => null,
        "ulke_kodu"   => $j['country'] ?? null,
        "bolge"       => $j['region'] ?? null,
        "sehir"       => $j['city'] ?? null,
        "posta"       => $j['postal'] ?? null,
        "enlem"       => $lat ?: null,
        "boylam"      => $lon ?: null,
        "zaman_dilimi"=> $j['timezone'] ?? null,
        "isp"         => $org,
        "org"         => $org,
        "asn"         => $asn,
    ];
}

function sag_freeipapi($ip, $H, $T) {
    [$b, $c, $e] = http_get("https://freeipapi.com/api/json/{$ip}", $H, $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j)) throw new Exception("json hata");
    $tz = $j['timeZones'] ?? [];
    return [
        "ulke"        => $j['countryName'] ?? null,
        "ulke_kodu"   => $j['countryCode'] ?? null,
        "bolge"       => $j['regionName'] ?? null,
        "sehir"       => $j['cityName'] ?? null,
        "posta"       => $j['zipCode'] ?? null,
        "enlem"       => $j['latitude'] ?? null,
        "boylam"      => $j['longitude'] ?? null,
        "zaman_dilimi"=> $tz ? implode(",", $tz) : null,
        "isp"         => null,
        "org"         => null,
        "asn"         => null,
    ];
}

function sag_ipapi_is($ip, $H, $T) {
    $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query";
    [$b, $c, $e] = http_get($url, $H, $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j) || ($j['status'] ?? '') !== 'success') throw new Exception($j['message'] ?? 'ip-api hata');
    return [
        "ulke"        => $j['country'] ?? null,
        "ulke_kodu"   => $j['countryCode'] ?? null,
        "bolge"       => $j['regionName'] ?? null,
        "sehir"       => $j['city'] ?? null,
        "posta"       => $j['zip'] ?? null,
        "enlem"       => $j['lat'] ?? null,
        "boylam"      => $j['lon'] ?? null,
        "zaman_dilimi"=> $j['timezone'] ?? null,
        "isp"         => $j['isp'] ?? null,
        "org"         => $j['org'] ?? null,
        "asn"         => $j['as'] ?? null,
    ];
}

function sag_windscribe($ip, $H, $T) {
    $Hw = array_merge($H, [
        "authorization: Bearer anonymous",
        "Origin: https://windscribe.com",
        "Referer: https://windscribe.com/",
    ]);
    [$b, $c, $e] = http_post_json("https://api.windscribe.com/IpLookup", $Hw, ["ip" => $ip], $T);
    if ($c !== 200 || !$b) throw new Exception("HTTP $c $e");
    $j = json_decode($b, true);
    if (!is_array($j)) throw new Exception("json hata");
    $d = $j['data'] ?? $j;
    return [
        "ulke"        => $d['country_name'] ?? null,
        "ulke_kodu"   => $d['country_code'] ?? null,
        "bolge"       => $d['region'] ?? null,
        "sehir"       => $d['city'] ?? null,
        "posta"       => $d['postal_code'] ?? null,
        "enlem"       => $d['latitude'] ?? null,
        "boylam"      => $d['longitude'] ?? null,
        "zaman_dilimi"=> $d['timezone'] ?? null,
        "isp"         => $d['isp'] ?? null,
        "org"         => $d['org'] ?? null,
        "asn"         => $d['asn'] ?? null,
        "vpn"         => $d['is_vpn'] ?? null,
        "proxy"       => $d['is_proxy'] ?? null,
        "tor"         => $d['is_tor'] ?? null,
        "datacenter"  => $d['is_datacenter'] ?? null,
    ];
}

$SAGLAYICILAR = [
    "ipapi"      => 'sag_ipapi',
    "ipwhois"    => 'sag_ipwhois',
    "ipinfo"     => 'sag_ipinfo',
    "freeipapi"  => 'sag_freeipapi',
    "ip-api"     => 'sag_ipapi_is',
    "windscribe" => 'sag_windscribe',
];

// ---- Paralel çalıştırma (curl_multi) ----
$mh = curl_multi_init();
$handles = [];

// URL'ler ve hazırlık
$istekler = [];

foreach ($SAGLAYICILAR as $ad => $fn) {
    // Sadece URL bazlı olanları curl_multi ile hızlı çek; windscribe POST, ayrı ele alınır
    switch ($ad) {
        case "ipapi":
            $istekler[$ad] = "https://ipapi.co/{$ip}/json/";
            break;
        case "ipwhois":
            $istekler[$ad] = "https://ipwho.is/{$ip}";
            break;
        case "ipinfo":
            $istekler[$ad] = "https://ipinfo.io/{$ip}/json";
            break;
        case "freeipapi":
            $istekler[$ad] = "https://freeipapi.com/api/json/{$ip}";
            break;
        case "ip-api":
            $istekler[$ad] = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query";
            break;
        default:
            break; // windscribe ayrı
    }
}

foreach ($istekler as $ad => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => $TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
        CURLOPT_HTTPHEADER     => $HEADERS,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$ad] = $ch;
}

// windscribe paralel için POST
$wsHeaders = array_merge($HEADERS, [
    "authorization: Bearer anonymous",
    "Content-Type: application/json",
    "Origin: https://windscribe.com",
    "Referer: https://windscribe.com/",
]);
$chWs = curl_init("https://api.windscribe.com/IpLookup");
curl_setopt_array($chWs, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(["ip" => $ip]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => $TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => "",
    CURLOPT_HTTPHEADER     => $wsHeaders,
]);
curl_multi_add_handle($mh, $chWs);
$handles["windscribe"] = $chWs;

// Çalıştır
do {
    $status = curl_multi_exec($mh, $active);
    if ($active) curl_multi_select($mh, 1.0);
} while ($active && $status === CURLM_OK);

$ham = [];
foreach ($handles as $ad => $ch) {
    $body = curl_multi_getcontent($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
    $ham[$ad] = [$body, $code];
}
curl_multi_close($mh);

// ---- Parse ----
$sonuclar = [];
$hatalar  = [];
foreach ($SAGLAYICILAR as $ad => $fn) {
    [$body, $code] = $ham[$ad] ?? [null, 0];
    if ($code !== 200 || !$body) {
        $hatalar[$ad] = "HTTP $code";
        continue;
    }
    $j = json_decode($body, true);
    if (!is_array($j)) {
        $hatalar[$ad] = "JSON decode";
        continue;
    }
    try {
        // Aynı parse mantığını fonksiyonlarla yapmak yerine burada dallandırıyoruz
        switch ($ad) {
            case "ipapi":
                if (!empty($j['error'])) throw new Exception($j['reason'] ?? 'hata');
                $sonuclar[$ad] = [
                    "ulke" => $j['country_name'] ?? null, "ulke_kodu" => $j['country_code'] ?? null,
                    "bolge" => $j['region'] ?? null, "sehir" => $j['city'] ?? null,
                    "posta" => $j['postal'] ?? null, "enlem" => $j['latitude'] ?? null,
                    "boylam" => $j['longitude'] ?? null, "zaman_dilimi" => $j['timezone'] ?? null,
                    "isp" => $j['org'] ?? null, "org" => $j['org'] ?? null, "asn" => $j['asn'] ?? null,
                ];
                break;
            case "ipwhois":
                if (empty($j['success'])) throw new Exception($j['message'] ?? 'hata');
                $conn = $j['connection'] ?? [];
                $sonuclar[$ad] = [
                    "ulke" => $j['country'] ?? null, "ulke_kodu" => $j['country_code'] ?? null,
                    "bolge" => $j['region'] ?? null, "sehir" => $j['city'] ?? null,
                    "posta" => $j['postal'] ?? null, "enlem" => $j['latitude'] ?? null,
                    "boylam" => $j['longitude'] ?? null, "zaman_dilimi" => $j['timezone']['id'] ?? null,
                    "isp" => $conn['isp'] ?? null, "org" => $conn['org'] ?? null, "asn" => $conn['asn'] ?? null,
                ];
                break;
            case "ipinfo":
                if (!empty($j['bogon'])) throw new Exception("bogon");
                $loc = $j['loc'] ?? ",";
                [$lat, $lon] = array_pad(explode(",", $loc, 2), 2, null);
                $org = $j['org'] ?? null;
                $sonuclar[$ad] = [
                    "ulke" => null, "ulke_kodu" => $j['country'] ?? null,
                    "bolge" => $j['region'] ?? null, "sehir" => $j['city'] ?? null,
                    "posta" => $j['postal'] ?? null, "enlem" => $lat ?: null,
                    "boylam" => $lon ?: null, "zaman_dilimi" => $j['timezone'] ?? null,
                    "isp" => $org, "org" => $org,
                    "asn" => $org ? (explode(" ", $org)[0] ?? null) : null,
                ];
                break;
            case "freeipapi":
                $tz = $j['timeZones'] ?? [];
                $sonuclar[$ad] = [
                    "ulke" => $j['countryName'] ?? null, "ulke_kodu" => $j['countryCode'] ?? null,
                    "bolge" => $j['regionName'] ?? null, "sehir" => $j['cityName'] ?? null,
                    "posta" => $j['zipCode'] ?? null, "enlem" => $j['latitude'] ?? null,
                    "boylam" => $j['longitude'] ?? null, "zaman_dilimi" => $tz ? implode(",", $tz) : null,
                    "isp" => null, "org" => null, "asn" => null,
                ];
                break;
            case "ip-api":
                if (($j['status'] ?? '') !== 'success') throw new Exception($j['message'] ?? 'hata');
                $sonuclar[$ad] = [
                    "ulke" => $j['country'] ?? null, "ulke_kodu" => $j['countryCode'] ?? null,
                    "bolge" => $j['regionName'] ?? null, "sehir" => $j['city'] ?? null,
                    "posta" => $j['zip'] ?? null, "enlem" => $j['lat'] ?? null,
                    "boylam" => $j['lon'] ?? null, "zaman_dilimi" => $j['timezone'] ?? null,
                    "isp" => $j['isp'] ?? null, "org" => $j['org'] ?? null, "asn" => $j['as'] ?? null,
                ];
                break;
            case "windscribe":
                $d = $j['data'] ?? $j;
                $sonuclar[$ad] = [
                    "ulke" => $d['country_name'] ?? null, "ulke_kodu" => $d['country_code'] ?? null,
                    "bolge" => $d['region'] ?? null, "sehir" => $d['city'] ?? null,
                    "posta" => $d['postal_code'] ?? null, "enlem" => $d['latitude'] ?? null,
                    "boylam" => $d['longitude'] ?? null, "zaman_dilimi" => $d['timezone'] ?? null,
                    "isp" => $d['isp'] ?? null, "org" => $d['org'] ?? null, "asn" => $d['asn'] ?? null,
                    "vpn" => $d['is_vpn'] ?? null, "proxy" => $d['is_proxy'] ?? null,
                    "tor" => $d['is_tor'] ?? null, "datacenter" => $d['is_datacenter'] ?? null,
                ];
                break;
        }
    } catch (Throwable $t) {
        $hatalar[$ad] = $t->getMessage();
    }
}

// ---- Birleştir ----
$alanlar = ["ulke","ulke_kodu","bolge","sehir","posta","enlem","boylam","zaman_dilimi","isp","org","asn"];
$birlesik = [];
$guven = [];
foreach ($alanlar as $alan) {
    $degerler = [];
    foreach ($sonuclar as $s) {
        $v = $s[$alan] ?? null;
        if (!_bos($v)) $degerler[] = (string)$v;
    }
    if (!$degerler) {
        $birlesik[$alan] = null;
        $guven[$alan] = "0/0";
        continue;
    }
    $sayac = array_count_values($degerler);
    arsort($sayac);
    $enSik = array_key_first($sayac);
    $birlesik[$alan] = $enSik;
    $guven[$alan] = $sayac[$enSik] . "/" . count($degerler);
}

// ---- Çıktı ----
$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "ip"          => $ip,
    "birlesik"    => $birlesik,
    "guven"       => $guven,
];

if ($raw) {
    $cikti["saglayicilar"] = $sonuclar;
    $cikti["hatalar"]      = $hatalar;
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);