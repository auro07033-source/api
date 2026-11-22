<?php
// CORS ve güvenlik
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Gizli API bilgilerini al
require_once 'config.php';

// Sorgu tipini al
$type = isset($_GET['type']) ? $_GET['type'] : null;
if (!$type) {
    echo json_encode(['error' => 'Sorgu tipi belirtilmedi']);
    exit;
}

// İzin verilen tipler (güvenlik için)
$allowed = ['tc', 'tcpro', 'adsoyad', 'aile', 'sulale', 'tcgsm', 'gsmtc', 'eokul', 'adres', 'tapu', 'adaparsel'];
if (!in_array($type, $allowed)) {
    echo json_encode(['error' => 'Geçersiz sorgu tipi']);
    exit;
}

// Parametreleri al (type hariç)
$params = $_GET;
unset($params['type']);

// Hedef URL'yi oluştur
$target_url = $REAL_API_BASE . $type . '.php?';
$target_url .= http_build_query($params);

// gsmtc için ekstra auth ekle
if ($type === 'gsmtc') {
    $target_url .= '&auth=' . $REAL_API_KEY;
}

// cURL ile istek at
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'API sunucusuna ulaşılamadı']);
} else {
    http_response_code($http_code);
    echo $response;
}
?>