<?php
/**
 * Hava Durumu API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sehir = $_GET['sehir'] ?? $_POST['sehir'] ?? null;

if (!$sehir) {
    echo json_encode([
        'success' => false,
        'error' => 'Şehir adı gerekli',
        'kullanım' => '/hava.php?sehir=istanbul',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Şehir koordinatları
$sehirler = [
    "adana" => ["lat" => 37.0000, "lon" => 35.3213],
    "adıyaman" => ["lat" => 37.7641, "lon" => 38.2762],
    "afyon" => ["lat" => 38.7509, "lon" => 30.5387],
    "ankara" => ["lat" => 39.9208, "lon" => 32.8541],
    "antalya" => ["lat" => 36.8969, "lon" => 30.7133],
    "aydın" => ["lat" => 37.8454, "lon" => 27.8326],
    "balıkesir" => ["lat" => 39.6484, "lon" => 27.8826],
    "bursa" => ["lat" => 40.1862, "lon" => 29.0568],
    "çanakkale" => ["lat" => 40.1346, "lon" => 26.3948],
    "denizli" => ["lat" => 37.7738, "lon" => 29.0882],
    "diyarbakır" => ["lat" => 37.9071, "lon" => 40.2348],
    "edirne" => ["lat" => 41.6772, "lon" => 26.5559],
    "elazığ" => ["lat" => 38.6752, "lon" => 39.2093],
    "erzurum" => ["lat" => 39.9039, "lon" => 41.2641],
    "eskişehir" => ["lat" => 39.7907, "lon" => 30.5778],
    "gaziantep" => ["lat" => 37.0662, "lon" => 37.3838],
    "giresun" => ["lat" => 40.9174, "lon" => 38.3920],
    "hatay" => ["lat" => 36.2048, "lon" => 36.1572],
    "ısparta" => ["lat" => 37.7678, "lon" => 30.5563],
    "istanbul" => ["lat" => 41.0082, "lon" => 28.9784],
    "izmir" => ["lat" => 38.4237, "lon" => 27.1428],
    "kahramanmaraş" => ["lat" => 37.5770, "lon" => 36.9367],
    "karabük" => ["lat" => 41.2005, "lon" => 32.6280],
    "karaman" => ["lat" => 37.1766, "lon" => 33.2176],
    "kastamonu" => ["lat" => 41.3849, "lon" => 33.7827],
    "kayseri" => ["lat" => 38.7253, "lon" => 35.4895],
    "kırıkkale" => ["lat" => 39.8447, "lon" => 33.5109],
    "kırklareli" => ["lat" => 41.7298, "lon" => 27.2298],
    "kırşehir" => ["lat" => 39.1425, "lon" => 34.1709],
    "kocaeli" => ["lat" => 40.7654, "lon" => 29.9408],
    "konya" => ["lat" => 37.8714, "lon" => 32.4846],
    "kütahya" => ["lat" => 39.4169, "lon" => 29.9842],
    "malatya" => ["lat" => 38.3528, "lon" => 38.3095],
    "manisa" => ["lat" => 38.6190, "lon" => 27.4273],
    "mardin" => ["lat" => 37.3121, "lon" => 40.7115],
    "mersin" => ["lat" => 36.8118, "lon" => 34.6218],
    "muğla" => ["lat" => 37.2143, "lon" => 28.3669],
    "nevşehir" => ["lat" => 38.6247, "lon" => 34.7154],
    "ordu" => ["lat" => 40.9872, "lon" => 37.1776],
    "osmaniye" => ["lat" => 37.0750, "lon" => 36.2585],
    "riz" => ["lat" => 41.0297, "lon" => 40.5211],
    "sakarya" => ["lat" => 40.7734, "lon" => 30.3944],
    "samsun" => ["lat" => 41.2899, "lon" => 36.3598],
    "sinop" => ["lat" => 42.0291, "lon" => 35.1430],
    "sivas" => ["lat" => 39.7521, "lon" => 37.0210],
    "tekirdağ" => ["lat" => 40.9899, "lon" => 27.5088],
    "trabzon" => ["lat" => 41.0040, "lon" => 39.7188],
    "uşak" => ["lat" => 38.6619, "lon" => 29.4069],
    "van" => ["lat" => 38.4932, "lon" => 43.3835],
    "yalova" => ["lat" => 40.6472, "lon" => 29.2777],
    "yozgat" => ["lat" => 39.8233, "lon" => 34.8230],
    "zonguldak" => ["lat" => 41.4542, "lon" => 31.7953]
];

$sehir = mb_strtolower(trim($sehir));

if (!isset($sehirler[$sehir])) {
    echo json_encode([
        'success' => false,
        'error' => 'Şehir bulunamadı',
        'aranan' => $sehir,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = $sehirler[$sehir]['lat'];
$lon = $sehirler[$sehir]['lon'];

// MSN Weather API
$url = "https://api.msn.com/weatherfalcon/weather/current";
$params = [
    "apikey" => "j5i4gDqHL6nGYwx5wi5kRhXjtf2c5qgFX9fzfk0TOo",
    "locale" => "tr-tr",
    "units" => "C",
    "latLongList" => "$lat,$lon"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_REFERER, 'https://www.msn.com/');
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode([
        'success' => false,
        'error' => 'Hava durumu alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['responses'][0]['weather'][0]['current'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Veri bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$weather = $data['responses'][0]['weather'][0]['current'];

echo json_encode([
    'success' => true,
    'sehir' => mb_strtoupper($sehir),
    'sicaklik' => $weather['temp'] . '°C',
    'hissedilen' => $weather['feels'] . '°C',
    'nem' => $weather['rh'] . '%',
    'ruzgar' => $weather['windSpd'] . ' km/s',
    'durum' => $weather['cap'],
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>