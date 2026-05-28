<?php
/**
 * YouTube Kanal Bilgisi API
 * Kanal adına göre istatistikleri getirir
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$channel = isset($_GET['channel']) ? trim($_GET['channel']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : 'username'; // username, id, url

if (empty($channel)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Kanal adı veya ID gerekli',
        'ornek' => '/?channel=MrBeast',
        'ornek_id' => '/?channel=UCX6OQ3DkcsbYNE6H8uQQuVA&type=id',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'http_code' => $http_code];
}

// YouTube API (No API Key - Web scraping alternatifi)
if ($type == 'id') {
    $channel_id = $channel;
} else {
    // Önce kanal ID'yi bul
    $search_url = "https://www.youtube.com/@" . urlencode($channel);
    $result = sendRequest($search_url);
    
    if ($result['http_code'] == 404) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Kanal bulunamadı',
            'channel' => $channel,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Kanal ID'yi çek
    preg_match('/"channelId":"([^"]+)"/', $result['response'], $match);
    if (empty($match)) {
        preg_match('/"externalChannelId":"([^"]+)"/', $result['response'], $match);
    }
    if (empty($match)) {
        preg_match('/"browseId":"([^"]+)"/', $result['response'], $match);
    }
    
    if (empty($match)) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Kanal ID alınamadı',
            'channel' => $channel,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $channel_id = $match[1];
}

// Kanal sayfasını al
$channel_url = "https://www.youtube.com/channel/" . $channel_id;
$result = sendRequest($channel_url);

if ($result['http_code'] != 200) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Kanal bilgisi alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$html = $result['response'];

// Verileri çek
$output = [
    'success' => true,
    'channel_id' => $channel_id,
    'telegram' => '@unutur'
];

// Kanal adı
if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $match)) {
    $output['title'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

// Kanal açıklaması
if (preg_match('/<meta name="description" content="([^"]+)"/', $html, $match)) {
    $output['description'] = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

// Abone sayısı
if (preg_match('/"subscriberCountText":\{"simpleText":"([^"]+)"/', $html, $match)) {
    $output['subscribers'] = $match[1];
} elseif (preg_match('/"subscriberCountText":\{"runs":\[\{"text":"([^"]+)"\}\],?/', $html, $match)) {
    $output['subscribers'] = $match[1];
}

// Video sayısı
if (preg_match('/"videoCountText":\{"simpleText":"([^"]+)"/', $html, $match)) {
    $output['videos'] = $match[1];
}

// Görüntülenme sayısı
if (preg_match('/"viewCountText":\{"simpleText":"([^"]+)"/', $html, $match)) {
    $output['views'] = $match[1];
}

// Kanal fotoğrafı
if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $match)) {
    $output['avatar'] = $match[1];
}

// Doğrulanmış mı?
if (strpos($html, '"badges":[{"metadataBadgeRenderer":{"style":"BADGE_STYLE_TYPE_VERIFIED"') !== false) {
    $output['verified'] = true;
} else {
    $output['verified'] = false;
}

// Kanal oluşturulma tarihi
if (preg_match('/"joinedDateText":\{"simpleText":"([^"]+)"/', $html, $match)) {
    $output['joined_date'] = $match[1];
}

// Ülke
if (preg_match('/"country":"([^"]+)"/', $html, $match)) {
    $output['country'] = $match[1];
}

// Kanal linki
$output['channel_url'] = "https://www.youtube.com/channel/" . $channel_id;

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>