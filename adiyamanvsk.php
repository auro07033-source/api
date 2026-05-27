<?php
/**
 * Adıyaman Belediyesi Vesika API
 * Telegram: @unutur
 */

$tc = $_GET['tc'] ?? '';

if (empty($tc) || !preg_match('/^[0-9]{11}$/', $tc)) {
    // Geçersiz veya eksik TC
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Geçerli bir TC kimlik numarası girin (11 hane)',
        'kullanim' => '/adiyamanvsk.php?tc=17176267670'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Belediyenin resim URL'si
$image_url = "https://personel.adiyaman.bel.tr/RESIMLER/PORTRE/{$tc}.jpg";

// cURL ile resmi kontrol et ve al
$ch = curl_init($image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$image_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200 && !empty($image_data)) {
    // Resim bulundu → doğrudan resim olarak göster
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($image_data));
    echo $image_data;
} else {
    // Resim yok veya hata → JSON hata mesajı dön
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Bu TC numarasına ait vesika fotoğrafı bulunamadı',
        'tc' => $tc
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}