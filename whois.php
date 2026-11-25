<?php
// whois.php - .com.tr / .tr WHOIS sorgusu (Trabis resmi kaynağı, PHP)
// Kullanım:
//   /whois.php?domain=exxen.com.tr
//   /whois.php?domain=exxen.com.tr&raw=1   -> ham whois metni
//   POST JSON: {"domain":"exxen.com.tr","raw":true}
// Kaynak: https://whois.trabis.gov.tr (resmi, key gerekmez)
// Gereksinim: PHP 7.4+, curl

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$URL = "https://whois.trabis.gov.tr/whois/domain";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language: tr-TR",
    "Content-Type: application/x-www-form-urlencoded",
    "Origin: https://whois.trabis.gov.tr",
    "Referer: https://whois.trabis.gov.tr/",
];

// --- Parametre ---
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $domain = $input['domain'] ?? null;
    $raw    = !empty($input['raw']);
} else {
    $domain = $_GET['domain'] ?? null;
    $raw    = isset($_GET['raw']) && $_GET['raw'] === '1';
}

$domain = $domain ? strtolower(trim($domain)) : null;
if (!$domain || !preg_match('/^([a-z0-9\-]+\.)+[a-z]{2,}$/i', $domain)) {
    echo json_encode([
        "success" => false,
        "message" => "geçerli domain parametresi gerekli (örn: exxen.com.tr)",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Trabis yalnızca .tr uzantılı domainleri kabul eder
if (!preg_match('/\.(com|net|org|gov|edu|av|bel|k12|info|biz|web|gen|tv|name)\.tr$/i', $domain)
    && !preg_match('/\.tr$/i', $domain)) {
    echo json_encode([
        "success" => false,
        "message" => "sadece .tr uzantılı domainler desteklenir (örn: ornek.com.tr)",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- İstek ---
$payload = http_build_query([
    "domain" => $domain,
    "submit" => "Sorgula",
]);

$ch = curl_init($URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
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
        "success" => false,
        "message" => "trabis yanıt vermedi",
        "http"    => $code,
        "error"   => $err,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Ham metni çıkar ---
// Trabis, yanıtı <pre> ... </pre> içinde veya düz metin olarak verir.
$ham = "";
if (preg_match('/<pre[^>]*>(.*?)<\/pre>/is', $body, $m)) {
    $ham = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
} else {
    // <pre> yoksa, "Domain Name:" ile başlayan blok
    if (preg_match('/(\*\*\s*Domain Name:.*?)(?:<\/body>|$)/is', $body, $m2)) {
        $ham = html_entity_decode(strip_tags($m2[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } else {
        $ham = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
$ham = preg_replace("/\r\n/", "\n", $ham);
$ham = trim($ham);

if ($ham === "" || stripos($ham, "bulunamad") !== false || stripos($ham, "not found") !== false) {
    echo json_encode([
        "success" => false,
        "message" => "domain bulunamadı veya whois yanıtı boş",
        "domain"  => $domain,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Parse ---
function bul(string $pattern, string $metin, int $grup = 1) {
    if (preg_match($pattern, $metin, $m)) return trim($m[$grup]);
    return null;
}

// Kayıt bilgileri
$domainName = bul('/Domain Name:\s*([^\n\r]+)/i', $ham) ?: $domain;
$status     = bul('/Domain Status:\s*([^\n\r]+)/i', $ham);
$frozen     = bul('/Frozen Status:\s*([^\n\r]+)/i', $ham);
$transfer   = bul('/Transfer Status:\s*([^\n\r]+)/i', $ham);

// Registrant (sahip) - "** Registrant:" sonrası ilk anlamlı satır
$sahip = null;
if (preg_match('/\*\*\s*Registrant:\s*\n(.+?)\n/i', $ham, $m)) {
    $sahip = trim($m[1]);
}

// Registrar
$kayitci = null;
$kayitci_org = bul('/Organization Name\s*:\s*([^\n\r]+)/i', $ham);
$kayitci_adres = null;
if (preg_match('/Address\s*:\s*(.+?)(?:\n\s*\n|\nPhone)/is', $ham, $m)) {
    $kayitci_adres = trim(preg_replace('/\s+/', ' ', $m[1]));
}
$kayitci_tel = bul('/Phone\s*:\s*([^\n\r]+)/i', $ham);
$kayitci_fax = bul('/Fax\s*:\s*([^\n\r]+)/i', $ham);

// NS
$ns = [];
if (preg_match('/\*\*\s*Domain Servers:\s*\n(.*?)(?:\n\s*\n|\*\*)/is', $ham, $m)) {
    foreach (preg_split('/\r\n|\r|\n/', trim($m[1])) as $satir) {
        $satir = trim($satir);
        if ($satir !== '' && stripos($satir, 'ns') !== false) {
            $ns[] = $satir;
        }
    }
}

// Tarihler
$olusturma = bul('/Created on\.+\s*:\s*([^\n\r]+)/i', $ham);
$bitis     = bul('/Expires on\.+\s*:\s*([^\n\r]+)/i', $ham);
$guncelleme= bul('/Updated on\.+\s*:\s*([^\n\r]+)/i', $ham);
$sonGuncel = bul('/Last Update Time:\s*([^\n\r]+)/i', $ham);

// Durumlar
$durumlar = [];
if ($status) $durumlar[] = $status;
if ($frozen && $frozen !== '-') $durumlar[] = "Frozen: $frozen";
if ($transfer) $durumlar[] = $transfer;

// DNSSEC bilgisi (trabis raw'da genelde yok)
$dnssec = null;
if (preg_match('/DNSSEC\s*:\s*([^\n\r]+)/i', $ham, $m)) {
    $dnssec = trim($m[1]);
}

$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "domain"      => $domainName,
    "kayitci"     => $kayitci_org,
    "kayitci_adres"=> $kayitci_adres,
    "kayitci_tel" => $kayitci_tel,
    "kayitci_fax" => $kayitci_fax,
    "olusturma"   => $olusturma,
    "guncelleme"  => $guncelleme,
    "bitis"       => $bitis,
    "son_guncelleme" => $sonGuncel,
    "dnssec"      => $dnssec,
    "durumlar"    => $durumlar,
    "ns"          => $ns,
    "kaynak"      => "whois.trabis.gov.tr",
    "kisiler"     => [
        "sahip"    => $sahip ? ["ad" => $sahip] : null,
        "yonetici" => null,
        "teknik"   => null,
    ],
];

if ($raw) {
    $cikti["raw"] = $ham;
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);