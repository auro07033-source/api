<?php
/**
 * Telegram Bot Token Sorgulama API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : null);

if (!$token) {
    echo json_encode([
        'success' => false,
        'error' => 'Bot token gerekli',
        'kullanım' => '/telegram.php?token=1234567890:ABCdefGHIjklMNOpqrsTUVwxyz',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Token formatını kontrol et
if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
    echo json_encode([
        'success' => false,
        'error' => 'Token formatı geçersiz',
        'format' => '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Telegram API'ye istek at
$url = "https://api.telegram.org/bot{$token}/getMe";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200) {
    echo json_encode([
        'success' => false,
        'error' => "API hatası: HTTP {$httpCode}",
        'token' => substr($token, 0, 20) . '...',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data && $data['ok']) {
    $bot = $data['result'];
    echo json_encode([
        'success' => true,
        'token_valid' => true,
        'bot_id' => $bot['id'],
        'bot_username' => $bot['username'],
        'bot_name' => $bot['first_name'],
        'can_join_groups' => $bot['can_join_groups'] ?? false,
        'can_read_all_group_messages' => $bot['can_read_all_group_messages'] ?? false,
        'supports_inline_queries' => $bot['supports_inline_queries'] ?? false,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Token geçersiz',
        'token' => substr($token, 0, 20) . '...',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>