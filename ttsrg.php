<?php
/**
 * TikTok Kullanıcı Analiz - Sayfa Scraping
 * Telegram: @zahettim
 */

header('Content-Type: application/json');

$username = $_GET['username'] ?? 'infazsiz6';
$url = "https://www.tiktok.com/@{$username}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8'
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode([
        'success' => false,
        'error' => "Sayfaya erişilemedi (HTTP {$httpCode})",
        'username' => $username
    ]);
    exit;
}

// JSON verisini çek
if (preg_match('/<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__" type="application\/json">(.+?)<\/script>/', $html, $match)) {
    $data = json_decode($match[1], true);
    $user = $data['__DEFAULT_SCOPE__']['webapp.user-detail']['userInfo'] ?? null;
    
    if ($user) {
        $stats = $user['stats'];
        echo json_encode([
            'success' => true,
            'username' => $user['user']['uniqueId'],
            'nickname' => $user['user']['nickname'],
            'followers' => $stats['followerCount'],
            'following' => $stats['followingCount'],
            'likes' => $stats['heartCount'],
            'videos' => $stats['videoCount'],
            'verified' => $user['user']['verified'],
            'bio' => $user['user']['signature'],
            'avatar' => $user['user']['avatarThumb']
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['success' => false, 'error' => 'Kullanıcı verisi bulunamadı']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Sayfa parse edilemedi']);
}
?>