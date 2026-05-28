<?php
/**
 * Ziraat Bankası Kart Sorgulama API - TXT Dosyasından
 * Telegram: @zahettim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// TXT dosyasını oku
$txt_file = __DIR__ . '/ccdata.txt';

if (!file_exists($txt_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı dosyası bulunamadı',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$content = file_get_contents($txt_file);
$lines = explode("\n", $content);

$kartlar = [];
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    // Python dict formatını JSON'a çevir
    $line = str_replace("'", '"', $line);
    $line = preg_replace('/(\w+):/', '"$1":', $line);
    
    $kart = json_decode($line, true);
    if ($kart && isset($kart['card_number'])) {
        $kartlar[] = $kart;
    }
}

// Parametre
$kart_no = $_GET['kart_no'] ?? null;

if (!$kart_no) {
    echo json_encode([
        'success' => false,
        'error' => 'Kart numarası gerekli',
        'toplam_kayit' => count($kartlar),
        'kullanım' => '/ziraat.php?kart_no=4818081161475565',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Kart ara
$bulunan = null;
foreach ($kartlar as $kart) {
    if ($kart['card_number'] == $kart_no) {
        $bulunan = $kart;
        break;
    }
}

if ($bulunan) {
    echo json_encode([
        'success' => true,
        'kart_no' => $bulunan['card_number'],
        'son_kullanma' => $bulunan['expiration_date'],
        'cvv' => $bulunan['cvv'],
        'kart_sahibi' => $bulunan['card_holder'],
        'ulke' => $bulunan['country_name'],
        'durum' => $bulunan['card_status'],
        'bakiye' => $bulunan['money'],
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Kart numarası bulunamadı',
        'aranan_kart' => $kart_no,
        'toplam_kayit' => count($kartlar),
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>