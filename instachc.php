<?php
/**
 * Instagram User Checker API
 * telegram : @unutur
 */

header('Content-Type: application/json');

$user = isset($_GET['user']) ? trim($_GET['user']) : '';

if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanici adi gerekli',
        'telegram' => '@unutur'
    ]);
    exit;
}

$start = microtime(true);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.instagram.com/{$user}/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response_time = round((microtime(true) - $start) * 1000);

if ($http == 200) {
    echo json_encode([
        'success' => true,
        'username' => $user,
        'var_mi' => 'evet',
        'durum' => 'Hesab Bulundu ✅',
        'http' => $http,
        'sure_ms' => $response_time,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} 
elseif ($http == 404) {
    echo json_encode([
        'success' => true,
        'username' => $user,
        'var_mi' => 'hayir',
        'durum' => 'Hesab Bulunamadı ❌',
        'http' => $http,
        'sure_ms' => $response_time,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
else {
    echo json_encode([
        'success' => false,
        'username' => $user,
        'var_mi' => 'hata',
        'durum' => "⚠️ HATA (HTTP {$http})",
        'http' => $http,
        'sure_ms' => $response_time,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>