<?php
/**
 * TinyURL Kısaltma API
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ URL gerekli',
        'ornek' => '/?url=https://example.com',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Geçersiz URL formatı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// TinyURL API
$tiny_url = 'http://tinyurl.com/api-create.php?url=' . urlencode($url);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $tiny_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $response && strpos($response, 'Error') === false) {
    echo json_encode([
        'success' => true,
        'original_url' => $url,
        'short_url' => trim($response),
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => '❌ Kısaltma başarısız',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>