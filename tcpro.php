<?php
/**
 * TCPRO TC Kimlik Sorgulama API - Direkt JSON'dan Oku
 * Telegram: @zahettim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// JSON dosyasını oku
$json_file = __DIR__ . '/tcpro.json';

if (!file_exists($json_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı dosyası bulunamadı',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$content = file_get_contents($json_file);
$data = json_decode($content, true);

if (!$data) {
    echo json_encode([
        'success' => false,
        'error' => 'JSON formatı hatalı',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// TCPRO verilerini çıkar
$tcpro_db = [];

if (isset($data['data']['tcpro']['data'])) {
    foreach ($data['data']['tcpro']['data'] as $item) {
        if (isset($item['TCPRO']) && $item['TCPRO'] !== null) {
            $tc = $item['TCPRO']['Tc'] ?? null;
            if ($tc) {
                $tcpro_db[$tc] = [
                    'ad' => $item['TCPRO']['Ad'] ?? '',
                    'soyad' => $item['TCPRO']['Soyad'] ?? '',
                    'anne_ad' => $item['TCPRO']['AnneAd'] ?? '',
                    'baba_ad' => $item['TCPRO']['BabaAd'] ?? '',
                    'dogum_tarihi' => $item['TCPRO']['DogumTarihi'] ?? '',
                    'dogum_yeri' => $item['TCPRO']['DogumYer'] ?? '',
                    'cinsiyet' => $item['TCPRO']['Cinsiyet'] ?? '',
                    'medeni' => $item['TCPRO']['Medeni'] ?? '',
                    'cilt_no' => $item['TCPRO']['CiltNumarasi'] ?? '',
                    'aile_sira' => $item['TCPRO']['AileSiraNumarasi'] ?? '',
                    'sira' => $item['TCPRO']['SiraNumarasi'] ?? '',
                    'durum' => $item['TCPRO']['OlumTarih'] ?? 'Yasiyor'
                ];
            }
        }
    }
}

$tc = $_GET['tc'] ?? null;

if (!$tc) {
    echo json_encode([
        'success' => false,
        'error' => 'TC kimlik numarası gerekli',
        'toplam_kayit' => count($tcpro_db),
        'kullanım' => '/tcpro.php?tc=11111111110',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($tcpro_db[$tc])) {
    $k = $tcpro_db[$tc];
    echo json_encode([
        'success' => true,
        'tc' => $tc,
        'ad' => $k['ad'],
        'soyad' => $k['soyad'],
        'anne_ad' => $k['anne_ad'],
        'baba_ad' => $k['baba_ad'],
        'dogum_tarihi' => $k['dogum_tarihi'],
        'dogum_yeri' => $k['dogum_yeri'],
        'cinsiyet' => $k['cinsiyet'],
        'medeni' => $k['medeni'],
        'cilt_no' => $k['cilt_no'],
        'aile_sira' => $k['aile_sira'],
        'sira' => $k['sira'],
        'durum' => $k['durum'],
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'TC kimlik numarası bulunamadı',
        'aranan_tc' => $tc,
        'toplam_kayit' => count($tcpro_db),
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>