<?php
// whois.php - Domain WHOIS sorgulama (bulutova.com proxy, PHP)
// Kullanım:
//   /whois.php?domain=exxen.com.tr
//   /whois.php?domain=exxen.com.tr&raw=1   -> ham whois metni de gelir
//   POST JSON: {"domain":"exxen.com.tr","raw":true}
// Gereksinim: PHP 7.4+, curl, json

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$API = "https://www.bulutova.com/domain-check.php";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: application/json",
    "Accept-Language: tr-TR",
    "Content-Type: application/json",
    "Origin: https://www.bulutova.com",
    "Referer: https://www.bulutova.com/domain/whois-sorgula",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin",
];

// Parametreler: GET veya POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $domain = $input['domain'] ?? null;
    $raw    = !empty($input['raw']);
} else {
    $domain = $_GET['domain'] ?? null;
    $raw    = isset($_GET['raw']) && $_GET['raw'] === '1';
}

// Temizle ve doğrula
$domain = $domain ? strtolower(trim($domain)) : null;
if (!$domain || !preg_match('/^([a-z0-9\-]+\.)+[a-z]{2,}$/i', $domain)) {
    echo json_encode([
        "success"     => false,
        "message"     => "geçerli domain parametresi gerekli",
        "ornek"       => "?domain=exxen.com.tr",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// İstek gövdesi
$payload = [
    "action" => "whois",
    "domain" => $domain,
    "raw"    => true,
];

$ch = curl_init($API);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => "",
    CURLOPT_HTTPHEADER     => $HEADERS,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code !== 200) {
    echo json_encode([
        "success"     => false,
        "message"     => "bulutova.com yanıt vermedi",
        "http"        => $code,
        "error"       => $err,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$j = json_decode($body, true);
if (!is_array($j)) {
    echo json_encode([
        "success"     => false,
        "message"     => "geçersiz JSON yanıtı",
        "ham_yanit"   => substr($body, 0, 300),
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Bulutova yanıtı zaten bizim örnektekiyle aynı yapıda:
// domain, kayitci, olusturma, guncelleme, bitis, dnssec, durumlar, ns, kaynak, kisiler, raw
$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "domain"      => $j['domain']      ?? $domain,
    "kayitci"     => $j['kayitci']     ?? null,
    "olusturma"   => $j['olusturma']   ?? null,
    "guncelleme"  => $j['guncelleme']  ?? null,
    "bitis"       => $j['bitis']       ?? null,
    "dnssec"      => $j['dnssec']      ?? null,
    "durumlar"    => $j['durumlar']    ?? [],
    "ns"          => $j['ns']          ?? [],
    "kaynak"      => $j['kaynak']      ?? null,
    "kisiler"     => $j['kisiler']     ?? null,
];

if ($raw) {
    $cikti["raw"] = $j['raw'] ?? null;
} else {
    // raw alanını at, çıktıyı kısalt
    unset($j['raw']);
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);