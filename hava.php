<?php
/**
 * Hava Durumu API - Temizlenmiş (Şehir adı düzeltmeli)
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sehir = isset($_GET['sehir']) ? trim($_GET['sehir']) : 'istanbul';

if (empty($sehir)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Şehir adı gerekli',
        'telegram' => '@unutur'
    ]);
    exit;
}

$sehir = strtolower(trim($sehir));
$sehir = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $sehir);

// Türkiye şehirleri için sabit isimler
$turkey_cities = [
    'adana' => 'Adana', 'adıyaman' => 'Adıyaman', 'afyon' => 'Afyon', 'aksaray' => 'Aksaray',
    'amasya' => 'Amasya', 'ankara' => 'Ankara', 'antalya' => 'Antalya', 'ardahan' => 'Ardahan',
    'artvin' => 'Artvin', 'aydın' => 'Aydın', 'balıkesir' => 'Balıkesir', 'bartın' => 'Bartın',
    'batman' => 'Batman', 'bayburt' => 'Bayburt', 'bilecik' => 'Bilecik', 'bingöl' => 'Bingöl',
    'bitlis' => 'Bitlis', 'bolu' => 'Bolu', 'burdur' => 'Burdur', 'bursa' => 'Bursa',
    'çanakkale' => 'Çanakkale', 'çankırı' => 'Çankırı', 'çorum' => 'Çorum', 'denizli' => 'Denizli',
    'diyarbakır' => 'Diyarbakır', 'düzce' => 'Düzce', 'edirne' => 'Edirne', 'elazığ' => 'Elazığ',
    'erzincan' => 'Erzincan', 'erzurum' => 'Erzurum', 'eskişehir' => 'Eskişehir', 'gaziantep' => 'Gaziantep',
    'giresun' => 'Giresun', 'gümüşhane' => 'Gümüşhane', 'hakkari' => 'Hakkari', 'hatay' => 'Hatay',
    'ığdır' => 'Iğdır', 'ısparta' => 'Isparta', 'istanbul' => 'İstanbul', 'izmir' => 'İzmir',
    'kahramanmaraş' => 'Kahramanmaraş', 'karabük' => 'Karabük', 'karaman' => 'Karaman', 'kars' => 'Kars',
    'kastamonu' => 'Kastamonu', 'kayseri' => 'Kayseri', 'kırıkkale' => 'Kırıkkale', 'kırklareli' => 'Kırklareli',
    'kırşehir' => 'Kırşehir', 'kilis' => 'Kilis', 'kocaeli' => 'Kocaeli', 'konya' => 'Konya',
    'kütahya' => 'Kütahya', 'malatya' => 'Malatya', 'manisa' => 'Manisa', 'mardin' => 'Mardin',
    'mersin' => 'Mersin', 'muğla' => 'Muğla', 'muş' => 'Muş', 'nevşehir' => 'Nevşehir',
    'niğde' => 'Niğde', 'ordu' => 'Ordu', 'osmaniye' => 'Osmaniye', 'rize' => 'Rize',
    'sakarya' => 'Sakarya', 'samsun' => 'Samsun', 'siirt' => 'Siirt', 'sinop' => 'Sinop',
    'sivas' => 'Sivas', 'şanlıurfa' => 'Şanlıurfa', 'şırnak' => 'Şırnak', 'tekirdağ' => 'Tekirdağ',
    'tokat' => 'Tokat', 'trabzon' => 'Trabzon', 'tunceli' => 'Tunceli', 'uşak' => 'Uşak',
    'van' => 'Van', 'yalova' => 'Yalova', 'yozgat' => 'Yozgat', 'zonguldak' => 'Zonguldak'
];

$url = "https://wttr.in/{$sehir}?format=j1";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200 || !$response) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Hava durumu alınamadı',
        'telegram' => '@unutur'
    ]);
    exit;
}

$data = json_decode($response, true);
$current = $data['current_condition'][0] ?? [];
$today = $data['weather'][0] ?? [];

// Gösterilecek şehir adı (API'den değil, bizim listemizden)
$sehir_adi = $turkey_cities[$sehir] ?? ucfirst($sehir);

// Hava durumu emojileri
$weather_icons = [
    'Sunny' => '☀️', 'Clear' => '☀️', 'Partly cloudy' => '⛅', 'Cloudy' => '☁️',
    'Overcast' => '☁️', 'Mist' => '🌫️', 'Fog' => '🌫️', 'Light rain' => '🌦️',
    'Moderate rain' => '🌧️', 'Heavy rain' => '🌧️', 'Light rain with thunderstorm' => '⛈️',
    'Thunderstorm' => '⛈️', 'Light snow' => '❄️', 'Moderate snow' => '❄️', 'Heavy snow' => '❄️'
];

$durum = $current['weatherDesc'][0]['value'] ?? 'Bilinmiyor';
$icon = $weather_icons[$durum] ?? '🌡️';

$result = [
    'success' => true,
    'sehir' => $sehir_adi,
    'durum' => $icon . ' ' . $durum,
    'sicaklik' => $current['temp_C'] . '°C',
    'hissedilen' => $current['FeelsLikeC'] . '°C',
    'max_sicaklik' => $today['maxtempC'] ?? $current['temp_C'] . '°C',
    'min_sicaklik' => $today['mintempC'] ?? $current['temp_C'] . '°C',
    'ruzgar' => $current['windspeedKmph'] . ' km/s',
    'nem' => $current['humidity'] . '%',
    'telegram' => '@unutur'
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>