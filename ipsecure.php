<?php
/**
 * IP Analiz API - ISP, VPN, Proxy, Hosting Tespiti
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$ip = isset($_GET['ip']) ? trim($_GET['ip']) : '';

if (empty($ip)) {
    // Kendi IP'ini göster
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Proxy varsa gerçek IP'yi al
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
}

// IP doğrulama
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Geçersiz IP adresi',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// IP-API.com ile detaylı sorgulama (ücretsiz, key yok)
$url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,mobile,proxy,hosting,query";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200 || !$response) {
    echo json_encode([
        'success' => false,
        'error' => '❌ IP bilgisi alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data['status'] != 'success') {
    echo json_encode([
        'success' => false,
        'error' => '❌ IP bilgisi bulunamadı',
        'message' => $data['message'] ?? '',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// VPN/Proxy tespiti için ek kontrol
$is_vpn = $data['proxy'] ?? false;
$is_hosting = $data['hosting'] ?? false;

// ISP bilgisini düzenle
$isp = $data['isp'] ?? 'Bilinmiyor';
$org = $data['org'] ?? 'Bilinmiyor';

// VPN olduğunu düşündüğümüz ISP'ler
$vpn_ips = [
    'cloudflare', 'digitalocean', 'aws', 'amazon', 'google cloud', 'azure',
    'vpn', 'proxy', 'nordvpn', 'expressvpn', 'cyberghost', 'hide.me',
    'private internet access', 'surfshark', 'vyprvpn', 'ipvanish',
    'hotspot shield', 'tunnelbear', 'windscribe', 'protonvpn'
];

foreach ($vpn_ips as $vpn_keyword) {
    if (stripos($isp, $vpn_keyword) !== false || stripos($org, $vpn_keyword) !== false) {
        $is_vpn = true;
        break;
    }
}

// Sonuç
$result = [
    'success' => true,
    'ip' => $data['query'],
    'location' => [
        'country' => $data['country'],
        'country_code' => $data['countryCode'],
        'region' => $data['regionName'],
        'city' => $data['city'],
        'zip' => $data['zip'],
        'latitude' => $data['lat'],
        'longitude' => $data['lon'],
        'timezone' => $data['timezone']
    ],
    'isp' => [
        'name' => $isp,
        'organization' => $org,
        'as_number' => $data['as']
    ],
    'security' => [
        'is_proxy' => (bool)($data['proxy'] ?? false),
        'is_vpn' => $is_vpn,
        'is_hosting' => (bool)($data['hosting'] ?? false),
        'is_mobile' => (bool)($data['mobile'] ?? false)
    ],
    'map_link' => "https://www.google.com/maps?q={$data['lat']},{$data['lon']}",
    'telegram' => '@unutur'
];

// Risk durumu
if ($result['security']['is_vpn'] || $result['security']['is_proxy']) {
    $result['security']['risk'] = 'YÜKSEK - VPN/Proxy tespit edildi';
} elseif ($result['security']['is_hosting']) {
    $result['security']['risk'] = 'ORTA - Hosting/IP tespit edildi';
} else {
    $result['security']['risk'] = 'DÜŞÜK - Gerçek kullanıcı IP\'si';
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>