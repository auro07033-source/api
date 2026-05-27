<?php
/**
 * OTP SMS Toplama API - Düzenlenmiş
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$api_url = "https://vexorpvip-api.alwaysdata.net/api/otpapi.php?key=vexorpapi";

// Ana API'den veri çek
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || !$response) {
    echo json_encode([
        'success' => false,
        'error' => 'Bağlantı hatası',
        'telegram' => '@unutur'
    ]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['data'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Geçersiz veri',
        'telegram' => '@unutur'
    ]);
    exit;
}

// Verileri düzenle - SAHİP BİLGİLERİNİ DEĞİŞTİR
$formatted_data = [];
foreach ($data['data'] as $item) {
    // Kodu mesajdan çıkar
    $code = '';
    if (preg_match('/code is (\d+)/', $item['message'], $match)) {
        $code = $match[1];
    } elseif (preg_match('/تأكيد (\d+)/', $item['message'], $match)) {
        $code = $match[1];
    }
    
    $formatted_data[] = [
        'number' => $item['NUMBER'],
        'flag' => $item['flag'],
        'code' => $code,
        'message' => $item['message'],
        'time' => $item['time']
    ];
}

// KENDİ BİLGİLERİNLE DÖN - SAHİP SEN OL!!!
echo json_encode([
    'success' => true,
    'owner' => '@unutur',        // Değiştirildi
    'total' => count($formatted_data),
    'codes_found' => count(array_filter(array_column($formatted_data, 'code'))),
    'data' => $formatted_data,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>