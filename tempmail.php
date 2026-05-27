<?php
/**
 * Basit Temp Mail API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email = isset($_GET['email']) ? $_GET['email'] : null;

// 1secmail domainleri
$domains = ['1secmail.com', '1secmail.org', '1secmail.net'];

if (!$email) {
    // Yeni email oluştur
    $random = strtolower(substr(md5(rand()), 0, 10));
    $domain = $domains[array_rand($domains)];
    $new_email = $random . '@' . $domain;
    $login = $random;
    
    echo json_encode([
        'email' => $new_email,
        'login' => $login,
        'domain' => $domain,
        'telegram' => '@unutur'
    ]);
    exit;
}

// Email formatını kontrol et
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['hata' => 'Geçersiz email', 'telegram' => '@unutur']);
    exit;
}

list($login, $domain) = explode('@', $email);

// Gelen kutusunu kontrol et
$url = "https://www.1secmail.com/api/v1/?action=getMessages&login={$login}&domain={$domain}";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$response = curl_exec($ch);
curl_close($ch);

$messages = json_decode($response, true);

if (is_array($messages) && count($messages) > 0) {
    // İlk mesajı oku
    $msg_id = $messages[0]['id'];
    $url2 = "https://www.1secmail.com/api/v1/?action=readMessage&login={$login}&domain={$domain}&id={$msg_id}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $msg_response = curl_exec($ch);
    curl_close($ch);
    
    $message = json_decode($msg_response, true);
    
    echo json_encode([
        'mesaj_var' => true,
        'gonderen' => $message['from'],
        'konu' => $message['subject'],
        'icerik' => $message['textBody'] ?? $message['body'],
        'telegram' => '@unutur'
    ]);
} else {
    echo json_encode([
        'mesaj_var' => false,
        'mesaj' => 'Henüz mesaj yok',
        'telegram' => '@unutur'
    ]);
}
?>