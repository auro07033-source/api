<?php
/**
 * VirusTotal URL Checker API
 * URL güvenlik kontrolü
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ URL gerekli',
        'ornek' => '/?url=https://google.com',
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

// VirusTotal API Key (kendinle değiştir)
$api_key = '25b21ebb13c3b02ed2790c30d09d127b5a4e61b76b07026f54642bc740c77559';

// 1. URL'yi tara
$scan_url = 'https://www.virustotal.com/vtapi/v2/url/scan';
$scan_data = ['apikey' => $api_key, 'url' => $url];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $scan_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($scan_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$scan_response = curl_exec($ch);
curl_close($ch);

$scan_result = json_decode($scan_response, true);

if (!isset($scan_result['scan_id'])) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Tarama başlatılamadı',
        'detail' => $scan_result['verbose_msg'] ?? 'Bilinmeyen hata',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$scan_id = $scan_result['scan_id'];

// 2. Sonucu al (15 saniye bekle)
sleep(15);

$report_url = 'https://www.virustotal.com/vtapi/v2/url/report';
$report_data = ['apikey' => $api_key, 'resource' => $scan_id];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $report_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($report_data));
$report_response = curl_exec($ch);
curl_close($ch);

$report = json_decode($report_response, true);

if ($report['response_code'] != 1) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Rapor alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Zararlı tespit edenleri bul
$detected_scans = [];
foreach ($report['scans'] as $engine => $result) {
    if ($result['detected'] == true) {
        $detected_scans[] = $engine;
    }
}

$is_safe = ($report['positives'] == 0);

echo json_encode([
    'success' => true,
    'url' => $url,
    'safe' => $is_safe,
    'status' => $is_safe ? 'GÜVENLİ ✅' : 'TEHLİKELİ ⚠️',
    'positives' => $report['positives'],
    'total_scanners' => $report['total'],
    'scan_date' => $report['scan_date'],
    'permalink' => $report['permalink'],
    'detected_by' => $detected_scans,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>