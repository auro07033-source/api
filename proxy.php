<?php
/**
 * Free Proxy List API - Key Gerektirmez
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$format = isset($_GET['format']) ? $_GET['format'] : 'json'; // json, text
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

if ($limit > 200) $limit = 200;

// Free proxy kaynakları (key gerektirmez)
$sources = [
    'https://api.proxyscrape.com/v2/?request=getproxies&protocol=http&timeout=10000&country=all',
    'https://raw.githubusercontent.com/TheSpeedX/PROXY-List/master/http.txt',
    'https://raw.githubusercontent.com/ShiftyTR/Proxy-List/master/http.txt',
    'https://raw.githubusercontent.com/monosans/proxy-list/main/proxies/http.txt',
    'https://www.proxy-list.download/api/v1/get?type=http',
    'https://raw.githubusercontent.com/jetkai/proxy-list/main/online-proxies/txt/proxies-http.txt'
];

$all_proxies = [];

foreach ($sources as $source) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $source);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        // Her satırdaki proxy'leri al
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            $line = trim($line);
            // IP:Port formatını kontrol et
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d{2,5}$/', $line)) {
                $all_proxies[] = $line;
            }
        }
    }
}

// Tekrarları temizle
$all_proxies = array_unique($all_proxies);
$all_proxies = array_values($all_proxies);

// Limit uygula
$proxies = array_slice($all_proxies, 0, $limit);

if ($format == 'text') {
    header('Content-Type: text/plain');
    echo implode("\n", $proxies);
    exit;
}

// JSON formatı
$response = [
    'success' => true,
    'total' => count($all_proxies),
    'count' => count($proxies),
    'limit' => $limit,
    'type' => $type,
    'proxies' => $proxies,
    'telegram' => '@unutur'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>