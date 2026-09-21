<?php
// numlookup.php - Key + Rate limit korumalı
require_once __DIR__ . '/api_guard.php';
ApiGuard::checkKey();
ApiGuard::checkRate();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$API_KEY = "c3177593b1359e00d0e6c1a2d2cc6408";
$BASE_URL = "https://astha-9vd8.onrender.com/tapi-";

$num = $_GET['no'] ?? $_GET['mobile'] ?? $_POST['no'] ?? $_POST['mobile'] ?? '';
$num = trim($num);

if (empty($num)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "No number provided. Use: ?no=+905551234567&key=YOUR_KEY",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk",
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$cleanNum = preg_replace('/[^0-9]/', '', $num);

if (strlen($cleanNum) < 10) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "Invalid number (min 10 digits)",
        "number" => $cleanNum,
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk",
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

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

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $curlError,
        "number" => $cleanNum,
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk",
        "telegram" => "@cmrbaskani",
        "credit" => "𝐌𝐀𝐗"
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data && isset($data['status']) && $data['status'] !== "error" && isset($data['data'])) {
    $out = ["success" => true, "number" => $cleanNum, "results" => $data['data']];
    http_response_code(200);
} elseif ($data && isset($data['status']) && $data['status'] === "error") {
    $out = ["success" => false, "error" => $data['message'] ?? "No data found", "number" => $cleanNum];
    http_response_code(404);
} else {
    $out = ["success" => false, "error" => "No data found", "number" => $cleanNum, "raw_response" => $response];
    http_response_code(404);
}

$out['chanel'] = "https://t.me/+GgzdPJJUPns3OWJk";
$out['telegram'] = "@cmrbaskani";
$out['credit'] = "𝐌𝐀𝐗";

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);