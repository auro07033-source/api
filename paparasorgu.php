<?php
/**
 * Papara No Sorgulama API - JSON Dosyasından
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// JSON dosyasını oku
$json_file = __DIR__ . '/papara.json';

if (!file_exists($json_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Papara veritabanı bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$json_content = file_get_contents($json_file);
$papara_list = json_decode($json_content, true);

// Papara no parametresi
$paparano = $_GET['paparano'] ?? $_POST['paparano'] ?? null;

if (!$paparano) {
    echo json_encode([
        'success' => false,
        'error' => 'Papara no parametresi gerekli',
        'toplam_kayit' => count($papara_list),
        'kullanım' => '/paparasorgu.php?paparano=1354693996',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Ara
$bulunan = null;
foreach ($papara_list as $kayit) {
    if ((string)$kayit['paparano'] === (string)$paparano) {
        $bulunan = $kayit;
        break;
    }
}

if ($bulunan) {
    echo json_encode([
        'success' => true,
        'paparano' => $bulunan['paparano'],
        'adsoyad' => $bulunan['adsoyad'],
        'writer' => $bulunan['writer'],
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Papara no kaydı bulunamadı',
        'aranan_papara' => $paparano,
        'toplam_kayit' => count($papara_list),
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>