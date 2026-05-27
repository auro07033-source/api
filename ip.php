<?php
/**
 * IP Sorgulama API - Konum ve Detaylı Bilgi
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$ip = isset($_GET['ip']) ? $_GET['ip'] : null;

// Eğer IP yoksa kendi IP'ni al
if (!$ip) {
    $ip = $_SERVER['REMOTE_ADDR'];
}

// IPv6 kontrolü
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
    echo json_encode([
        'success' => false,
        'error' => 'IPv6 desteklenmiyor, lütfen IPv4 adresi girin',
        'telegram' => '@unutur'
    ]);
    exit;
}

// IP doğrulama
if (!filter_var($ip, FILTER_VALIDATE_IP)) {
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz IP adresi',
        'telegram' => '@unutur'
    ]);
    exit;
}

// ip-api.com ile sorgula (ücretsiz, key gerekmez)
$url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query,mobile,proxy,hosting";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$response) {
    echo json_encode([
        'success' => false,
        'error' => 'IP bilgisi alınamadı',
        'telegram' => '@unutur'
    ]);
    exit;
}

$data = json_decode($response, true);

if ($data['status'] == 'fail') {
    echo json_encode([
        'success' => false,
        'error' => 'IP bulunamadı',
        'message' => $data['message'] ?? 'Bilinmeyen hata',
        'telegram' => '@unutur'
    ]);
    exit;
}

// Özel alanlar için düzenleme
$result = [
    'success' => true,
    'ip' => $data['query'],
    'country' => $data['country'],
    'country_code' => $data['countryCode'],
    'region' => $data['region'],
    'region_name' => $data['regionName'],
    'city' => $data['city'],
    'zip_code' => $data['zip'] ?? '-',
    'latitude' => $data['lat'],
    'longitude' => $data['lon'],
    'timezone' => $data['timezone'],
    'isp' => $data['isp'],
    'organization' => $data['org'] ?? '-',
    'as_number' => $data['as'] ?? '-',
    'mobile' => $data['mobile'] ? 'Evet' : 'Hayır',
    'proxy' => $data['proxy'] ? 'Evet' : 'Hayır',
    'hosting' => $data['hosting'] ? 'Evet' : 'Hayır',
    'telegram' => '@unutur'
];

// Google Maps linki ekle
$result['map_link'] = "https://www.google.com/maps?q={$data['lat']},{$data['lon']}";

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>