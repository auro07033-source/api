<?php
// numlookup.php - Direkt API endpoint olarak çalışır
// Kullanım: https://ucretsizservicetr.onrender.com/numlookup?no=+905551234567
// Örnek: numlookup?no=+905551234567
// Telegram: @cmrbaskani

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// API ayarları
$API_KEY = "c3177593b1359e00d0e6c1a2d2cc6408";
$BASE_URL = "https://astha-9vd8.onrender.com/tapi-";

// Numara parametresini al (no veya mobile)
$num = '';
if (isset($_GET['no'])) {
    $num = trim($_GET['no']);
} elseif (isset($_GET['mobile'])) {
    $num = trim($_GET['mobile']);
} elseif (isset($_POST['no'])) {
    $num = trim($_POST['no']);
} elseif (isset($_POST['mobile'])) {
    $num = trim($_POST['mobile']);
}

// Boş kontrolü
if (empty($num)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No number provided. Use: ?no=+905551234567",
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Sadece rakamları al
$cleanNum = preg_replace('/[^0-9]/', '', $num);

// Minimum 10 hane kontrolü
if (strlen($cleanNum) < 10) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Invalid number (min 10 digits)",
        "number" => $cleanNum,
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// API isteği
$url = $BASE_URL . $API_KEY . "?Astha=" . urlencode($cleanNum);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (NumberOSINT/1.0)');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// cURL hatası
if ($curlError) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $curlError,
        "number" => $cleanNum,
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// API yanıtını çöz
$data = json_decode($response, true);

if ($data && isset($data['status']) && $data['status'] !== "error" && isset($data['data'])) {
    $formattedData = [
        "success" => true,
        "number" => $cleanNum,
        "results" => $data['data'],
        "JOIN" => "maxgotlost",
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ];
    http_response_code(200);
} elseif ($data && isset($data['status']) && $data['status'] === "error") {
    $formattedData = [
        "success" => false,
        "error" => isset($data['message']) ? $data['message'] : "No data found",
        "number" => $cleanNum,
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ];
    http_response_code(404);
} else {
    $formattedData = [
        "success" => false,
        "error" => "No data found",
        "number" => $cleanNum,
        "raw_response" => $response,
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ];
    http_response_code(404);
}

echo json_encode($formattedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);