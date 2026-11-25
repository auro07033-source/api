<?php
// ff_info.php - Free Fire oyuncu bilgi sorgulama (PHP, JSON çıktı)
// Kullanım:
//   /ff_info.php?region=SG&uid=403676323
//   /ff_info.php?region=IND&uid=2129250963&raw=1   -> ham veri de gelir
// Gereksinim: PHP 7.4+, curl
// Not: cf_clearance çerezi süreli; 403 alırsan developers.freefirecommunity.com'dan tazele.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$API = "https://developers.freefirecommunity.com/api/public/info";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
    "Referer: https://developers.freefirecommunity.com/en/id-checker",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin",
];

$COOKIES = [
    "NEXT_LOCALE"  => "en",
    "cf_clearance" => "Clp0JN7dllk2bxso7OUFszuX9lnK3m9Y4bt3FWPTl8cCtyqkfatHJ2i45WUNv9BSVzQVoKqQh5IdDQ-Rp6CaZo3mpQcITg9oYt48E5ctX_q_OR-DW-2CWzZQiT7gbdmar3ddcrqHTQkny3NGRHB5rU6OD0G94DrGYxJ9duoWMUO0g08qo8CKBZoq-jjD_fHFl67R41KpgLe4Ewwbx0Txs55Wb0YvT_hPeZ_TxD7Y6htPuvpVzAoAX2pNtX_ML1ko-Jepb2pSvxBDzpRwPMwLujF0PyGZ6ZsuRb3PBSYzvHfaHSyy3wbHtuV-tZFR5AkDJkPFUMHK",
    "__eoi"        => "ID=c1bb448e2561375e:T=1795546653:RT=1795546653:S=AA-Afja3gGNCfsu5MReXhNNh6hD1",
];
$cookieStr = [];
foreach ($COOKIES as $k => $v) $cookieStr[] = "$k=$v";
$cookieStr = implode("; ", $cookieStr);

// --- Parametre ---
$region = $_GET['region'] ?? $_POST['region'] ?? null;
$uid    = $_GET['uid']    ?? $_POST['uid']    ?? null;
$raw    = isset($_GET['raw']) && $_GET['raw'] === '1';

// Bölge kodları
$BOLGELER = [
    "IND" => "Hindistan", "SG" => "Singapur", "BR" => "Brezilya", "US" => "ABD",
    "ID"  => "Endonezya", "TH" => "Tayland", "VN" => "Vietnam", "PK" => "Pakistan",
    "EG"  => "Mısır", "RU" => "Rusya", "TR" => "Türkiye", "ME" => "Orta Doğu",
];

if (!$region || !$uid) {
    echo json_encode([
        "success"     => false,
        "message"     => "region ve uid parametreleri gerekli",
        "ornek"       => "?region=SG&uid=403676323",
        "bolgeler"    => array_keys($BOLGELER),
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$region = strtoupper(trim($region));
$uid    = trim($uid);

if (!isset($BOLGELER[$region])) {
    echo json_encode([
        "success"     => false,
        "message"     => "geçersiz bölge kodu",
        "girilen"     => $region,
        "gecerli"     => array_keys($BOLGELER),
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!ctype_digit($uid)) {
    echo json_encode([
        "success"     => false,
        "message"     => "uid sayısal olmalı",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- İstek ---
$url = $API . "?" . http_build_query(["region" => $region, "uid" => $uid]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => "",
    CURLOPT_HTTPHEADER     => array_merge($HEADERS, ["Cookie: $cookieStr"]),
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($body === false || $code !== 200) {
    echo json_encode([
        "success"     => false,
        "message"     => "freefirecommunity yanıt vermedi",
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
        "ham_bas"     => substr($body, 0, 300),
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (empty($j['success'])) {
    echo json_encode([
        "success"     => false,
        "message"     => $j['notice'] ?? $j['message'] ?? "API başarısız",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// --- Parse ---
$d  = $j['data'] ?? [];
$b  = $d['basicInfo'] ?? [];
$c  = $d['clanBasicInfo'] ?? [];
$p  = $d['petInfo'] ?? [];
$cr = $d['creditScoreInfo'] ?? [];
$pr = $d['profileInfo'] ?? [];
$so = $d['socialInfo'] ?? [];

$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "sorgu" => [
        "region"      => $region,
        "region_adi"  => $BOLGELER[$region],
        "uid"         => $uid,
        "demo"        => !empty($j['demo']),
    ],
    "oyuncu" => [
        "takma_ad"   => $b['nickname'] ?? null,
        "hesap_id"   => $b['accountId'] ?? null,
        "bolge"      => $b['region'] ?? null,
        "seviye"     => $b['level'] ?? null,
        "exp"        => $b['exp'] ?? null,
        "begeni"     => $b['liked'] ?? null,
        "rutbe"      => $b['rank'] ?? null,
        "max_rutbe"  => $b['maxRank'] ?? null,
        "rank_puan"  => $b['rankingPoints'] ?? null,
        "cs_rutbe"   => $b['csRank'] ?? null,
        "cs_max"     => $b['csMaxRank'] ?? null,
        "cs_puan"    => $b['csRankingPoints'] ?? null,
        "sezon"      => $b['seasonId'] ?? null,
        "surum"      => $b['releaseVersion'] ?? null,
        "baslik"     => $b['title'] ?? null,
        "banner"     => $b['bannerId'] ?? null,
        "avatar"     => $b['headPic'] ?? null,
        "imza"       => $so['signature'] ?? null,
        "olusturma"  => $b['createAt'] ?? null,
        "son_giris"  => $b['lastLoginAt'] ?? null,
        "prime"      => $b['primePrivilegeDetail']['primeLevel'] ?? null,
    ],
    "klan" => $c ? [
        "ad"          => $c['clanName'] ?? null,
        "id"          => $c['clanId'] ?? null,
        "seviye"      => $c['clanLevel'] ?? null,
        "uye"         => $c['memberNum'] ?? null,
        "kapasite"    => $c['capacity'] ?? null,
        "kaptan_id"   => $c['captainId'] ?? null,
    ] : null,
    "pet" => $p ? [
        "id"       => $p['id'] ?? null,
        "seviye"   => $p['level'] ?? null,
        "exp"      => $p['exp'] ?? null,
        "skin"     => $p['skinId'] ?? null,
        "yetenek"  => $p['selectedSkillId'] ?? null,
        "secili"   => $p['isSelected'] ?? null,
    ] : null,
    "kredi" => $cr ? [
        "puan" => $cr['creditScore'] ?? null,
    ] : null,
    "gorunum" => $pr ? [
        "avatar"    => $pr['avatarId'] ?? null,
        "kiyafet"   => $pr['clothes'] ?? [],
        "yetenekler"=> array_map(fn($x) => $x['skillId'] ?? null, $pr['equipedSkills'] ?? []),
    ] : null,
    "elmas_maliyeti" => $d['diamondCostRes']['diamondCost'] ?? null,
];

if ($raw) {
    $cikti["ham_veri"] = $j;
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);