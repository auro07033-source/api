<?php
/**
 * Instagram Kullanıcı Analiz API - Web Scraping
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$username = isset($_GET['username']) ? trim($_GET['username']) : null;

if (!$username) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcı adı gerekli',
        'kullanım' => '/instasrg.php?username=unutur',
        'telegram' => '@unutur'
    ]);
    exit;
}

$username = ltrim($username, '@');

// Instagram sayfasını çek
$url = "https://www.instagram.com/{$username}/";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8',
    'Cache-Control: no-cache'
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode([
        'success' => false,
        'error' => "Sayfaya erişilemedi (HTTP {$httpCode})",
        'username' => $username,
        'telegram' => '@unutur'
    ]);
    exit;
}

// Meta tag'lerden bilgileri çek
preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $titleMatch);
preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $descMatch);
preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $imgMatch);

// JSON verisini çek (alternatif)
if (preg_match('/<script type="application\/ld\+json">(.+?)<\/script>/', $html, $jsonMatch)) {
    $data = json_decode($jsonMatch[1], true);
    if (isset($data['mainEntity']['interactionStatistic'])) {
        foreach ($data['mainEntity']['interactionStatistic'] as $stat) {
            if (strpos($stat['name'], 'Follow') !== false) {
                $followers = $stat['userInteractionCount'] ?? 0;
            }
        }
    }
}

// Manuel takipçi sayısını bul
preg_match('/"edge_followed_by":{"count":(\d+)}/', $html, $followMatch);
$followers = isset($followMatch[1]) ? $followMatch[1] : ($followers ?? 0);

// Takip edilen sayısı
preg_match('/"edge_follow":{"count":(\d+)}/', $html, $followingMatch);
$following = $followingMatch[1] ?? 0;

// Paylaşım sayısı
preg_match('/"edge_owner_to_timeline_media":{"count":(\d+)}/', $html, $postsMatch);
$posts = $postsMatch[1] ?? 0;

$full_name = isset($titleMatch[1]) ? str_replace(' (@' . $username . ')', '', $titleMatch[1]) : $username;
$bio = isset($descMatch[1]) ? $descMatch[1] : '';
$avatar = isset($imgMatch[1]) ? $imgMatch[1] : '';

if ($followers > 0) {
    echo json_encode([
        'success' => true,
        'username' => $username,
        'full_name' => $full_name,
        'followers' => (int)$followers,
        'following' => (int)$following,
        'posts' => (int)$posts,
        'bio' => $bio,
        'avatar' => $avatar,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcı bulunamadı veya gizli hesap',
        'username' => $username,
        'telegram' => '@unutur'
    ]);
}
?>