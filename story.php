<?php
/**
 * Instagram Story Görüntülenme API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$username = isset($_GET['username']) ? $_GET['username'] : (isset($_POST['username']) ? $_POST['username'] : null);

if (!$username) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcı adı gerekli',
        'kullanım' => '/story.php?username=infazsiz6',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$username = ltrim($username, '@');

// LeoFame API
$url = "https://leofame.com/free-instagram-story-views?api=1";

$cookies = [
    'token' => '00bae069a44c19e57b123978b36af6b6',
    'ci_session' => 'a0d141ca691e47c5a358a98554df38301864611c'
];

$headers = [
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Content-Type' => 'application/x-www-form-urlencoded',
    'Origin' => 'https://leofame.com',
    'Referer' => 'https://leofame.com/free-instagram-story-views'
];

$data = [
    'token' => '00bae069a44c19e57b123978b36af6b6',
    'timezone_offset' => 'Europe/Istanbul',
    'free_link' => $username
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: ' . $headers['User-Agent'],
    'Content-Type: ' . $headers['Content-Type'],
    'Origin: ' . $headers['Origin'],
    'Referer: ' . $headers['Referer']
]);
curl_setopt($ch, CURLOPT_COOKIE, 'token=' . $cookies['token'] . '; ci_session=' . $cookies['ci_session']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode([
        'success' => false,
        'error' => "API hatası: HTTP {$httpCode}",
        'username' => $username,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Yanıt analizi
if (strpos($response, 'limit on free orders') !== false) {
    echo json_encode([
        'success' => false,
        'error' => 'Günlük limit doldu. 20 saat sonra tekrar deneyin.',
        'username' => $username,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} elseif (strpos($response, 'success') !== false || strpos($response, 'order') !== false) {
    echo json_encode([
        'success' => true,
        'message' => 'İzlenme gönderimi başarılı!',
        'username' => $username,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Bilinmeyen hata',
        'response' => substr($response, 0, 200),
        'username' => $username,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>