<?php
/**
 * Proxy Test API - Tek Tek Proxy Kontrolü
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$proxy_list = isset($_GET['proxies']) ? $_GET['proxies'] : null;
$format = isset($_GET['format']) ? $_GET['format'] : 'json';

if (!$proxy_list) {
    echo json_encode([
        'success' => false,
        'error' => 'Proxy listesi gerekli',
        'kullanım' => '/proxy_test.php?proxies=123.45.67.89:8080,98.76.54.32:3128',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$proxies = explode(',', $proxy_list);
$results = [];

foreach ($proxies as $proxy) {
    $proxy = trim($proxy);
    $start_time = microtime(true);
    
    // Proxy test et
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.ipify.org?format=json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $end_time = microtime(true);
    $time_ms = round(($end_time - $start_time) * 1000);
    
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $ip_data = json_decode($response, true);
        $results[] = [
            'proxy' => $proxy,
            'status' => '✅ Çalışıyor',
            'ip' => $ip_data['ip'] ?? '?',
            'time' => $time_ms . ' ms'
        ];
    } else {
        $results[] = [
            'proxy' => $proxy,
            'status' => '❌ Çalışmıyor',
            'error' => $error ?: "HTTP $httpCode",
            'time' => $time_ms . ' ms'
        ];
    }
}

if ($format == 'text') {
    header('Content-Type: text/plain');
    $output = "PROXY TEST SONUÇLARI\n";
    $output .= str_repeat('-', 50) . "\n";
    foreach ($results as $r) {
        $output .= $r['proxy'] . " -> " . $r['status'] . "\n";
    }
    echo $output;
    exit;
}

echo json_encode([
    'success' => true,
    'total' => count($proxies),
    'results' => $results,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>