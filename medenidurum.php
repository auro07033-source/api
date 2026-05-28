<?php
/**
 * Telefon Numarası Sorgulama API - Medeni Hal
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// JSON dosyasını oku
$json_file = __DIR__ . '/medenihal.json';

if (!file_exists($json_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı dosyası bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$json_content = file_get_contents($json_file);
$veriler = json_decode($json_content, true);

// Telefon numarası parametresi
$telefon = $_GET['telefon'] ?? $_POST['telefon'] ?? null;

if (!$telefon) {
    echo json_encode([
        'success' => false,
        'error' => 'Telefon numarası parametresi gerekli',
        'toplam_kayit' => count($veriler),
        'kullanım' => '/telefon.php?telefon=05300131665',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Telefon numarasını düzenle (05... formatına çevir)
$telefon = preg_replace('/[^0-9]/', '', $telefon);
if (strlen($telefon) == 12 && substr($telefon, 0, 2) == '90') {
    $telefon = '0' . substr($telefon, 2);
}
if (strlen($telefon) == 11 && substr($telefon, 0, 1) == '5') {
    $telefon = '0' . $telefon;
}

// Ara
$bulunan = null;
foreach ($veriler as $kayit) {
    if ($kayit['telefon'] === $telefon) {
        $bulunan = $kayit;
        break;
    }
}

if ($bulunan) {
    echo json_encode([
        'success' => true,
        'telefon' => $bulunan['telefon'],
        'adsoyad' => $bulunan['adsoyad'],
        'cinsiyet' => $bulunan['cinsiyet'],
        'dogum_tarihi' => $bulunan['dogum_tarihi'],
        'sehir' => $bulunan['sehir'],
        'durum' => $bulunan['durum'],
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Telefon numarası kaydı bulunamadı',
        'aranan_telefon' => $telefon,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>