<?php
/**
 * Deftere Yazı Yazdır API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$metin = null;

if (isset($_GET['metin'])) {
    $metin = $_GET['metin'];
} elseif (isset($_POST['metin'])) {
    $metin = $_POST['metin'];
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['metin'])) {
        $metin = $input['metin'];
    }
}

if (!$metin) {
    echo json_encode([
        'success' => false,
        'error' => 'Metin parametresi gerekli',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$api_url = "http://apis.xditya.me/write?text=" . urlencode($metin);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$image_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200 && $image_data) {
    $upload_dir = __DIR__ . '/uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $filename = md5($metin . time()) . '.png';
    $filepath = $upload_dir . '/' . $filename;
    
    if (file_put_contents($filepath, $image_data)) {
        $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $image_url = $protocol . $host . '/uploads/' . $filename;
        
        echo json_encode([
            'success' => true,
            'image' => $image_url,
            'metin' => $metin,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Dosya kaydedilemedi',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Fotoğraf oluşturulamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>