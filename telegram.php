<?php
/**
 * Telegram Kullanıcı Sorgulama API
 * Sadece JSON sonuç döndürür - Bot token gerekmez
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanici adi gerekli',
        'ornek' => '/?username=elonmusk',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$username = ltrim($username, '@');

// Public API (token gerekmez)
$url = "https://t.me/{$username}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
$html = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 404) {
    echo json_encode([
        'success' => false,
        'username' => $username,
        'error' => 'Kullanici bulunamadi',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($http_code != 200) {
    echo json_encode([
        'success' => false,
        'username' => $username,
        'error' => 'Baglanti hatasi',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Sayfadan verileri çek
$result = [
    'success' => true,
    'username' => $username,
    'user_id' => null,
    'first_name' => null,
    'last_name' => null,
    'full_name' => null,
    'bio' => null,
    'photo' => null,
    'telegram' => '@unutur'
];

// İsim çek
if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $match)) {
    $full_name = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    $full_name = str_replace('@' . $username, '', $full_name);
    $full_name = trim($full_name);
    
    if ($full_name && $full_name != $username) {
        $result['full_name'] = $full_name;
        $name_parts = explode(' ', $full_name, 2);
        $result['first_name'] = $name_parts[0];
        $result['last_name'] = $name_parts[1] ?? null;
    }
}

// Bio çek
if (preg_match('/<meta name="description" content="([^"]+)"/', $html, $match)) {
    $bio = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
    $bio = str_replace('Join Telegram – ', '', $bio);
    $result['bio'] = $bio;
}

// Profil fotoğrafı çek
if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $match)) {
    $result['photo'] = $match[1];
}

// ID çek (mümkünse)
if (preg_match('/tgme_page_extra">([^<]+)</', $html, $match)) {
    $extra = trim($match[1]);
    if (preg_match('/(\d+)/', $extra, $id_match)) {
        $result['user_id'] = (int)$id_match[1];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>