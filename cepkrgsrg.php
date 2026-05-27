<?php
/**
 * Telefon Servis Sorgulama API - Modelden Sorgu
 * Telegram: @zahettim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// JSON dosyasını oku
$json_file = __DIR__ . '/servis_data.json';

if (!file_exists($json_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı dosyası bulunamadı',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$content = file_get_contents($json_file);
$servisler = json_decode($content, true);

if (!is_array($servisler)) {
    echo json_encode([
        'success' => false,
        'error' => 'JSON formatı hatalı',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Parametre
$model = $_GET['model'] ?? null;

if (!$model) {
    echo json_encode([
        'success' => false,
        'error' => 'Model adı gerekli',
        'kullanım' => '/cepkrgsrg.php?model=P13%20BLUE%20MAX%20LITE%202022',
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Model ara (büyük/küçük harf duyarsız)
$bulunan = null;
foreach ($servisler as $servis) {
    if (strtolower($servis['model']) == strtolower($model)) {
        $bulunan = $servis;
        break;
    }
}

if ($bulunan) {
    echo json_encode([
        'success' => true,
        'servis_id' => $bulunan['servis_id'],
        'gelen_kargo_no' => $bulunan['gelen_kargo_no'],
        'model' => $bulunan['model'],
        'durum' => $bulunan['statu'],
        'giden_kargo_no' => $bulunan['giden_kargo_no'] ?? null,
        'aciklama' => $bulunan['aciklama'] ?? null,
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Model bulunamadı',
        'aranan_model' => $model,
        'telegram' => '@zahettim'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>