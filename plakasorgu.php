<?php
/**
 * Plaka Sorgulama API - Dosyadan Okuma (SQLite'siz)
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// SQL dosyasını oku
$sql_file = __DIR__ . '/plakasrg.sql';

if (!file_exists($sql_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı dosyası bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// SQL dosyasını oku
$sql_content = file_get_contents($sql_file);

// Tüm INSERT sorgularını bul
preg_match_all("/INSERT INTO `75k_plaka` VALUES \((.+?)\);/", $sql_content, $matches);

$plakalar = [];
foreach ($matches[1] as $values) {
    $parcalar = explode(',', $values);
    if (count($parcalar) >= 5) {
        $plakalar[] = [
            'id' => trim($parcalar[0]),
            'plaka' => trim($parcalar[1], " '"),
            'isim' => trim($parcalar[2], " '"),
            'tarih' => trim($parcalar[3], " '"),
            'gsm' => trim($parcalar[4], " '")
        ];
    }
}

// Plaka parametresi
$plaka = null;

if (isset($_GET['plaka'])) {
    $plaka = strtoupper(trim($_GET['plaka']));
} elseif (isset($_POST['plaka'])) {
    $plaka = strtoupper(trim($_POST['plaka']));
} else {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['plaka'])) {
        $plaka = strtoupper(trim($input['plaka']));
    }
}

if (!$plaka) {
    echo json_encode([
        'success' => false,
        'error' => 'Plaka parametresi gerekli',
        'toplam_kayit' => count($plakalar),
        'kullanım' => '/plakasorgu.php?plaka=34KG4978',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Plakayı ara
$bulunan = null;
foreach ($plakalar as $kayit) {
    if ($kayit['plaka'] === $plaka) {
        $bulunan = $kayit;
        break;
    }
}

if ($bulunan) {
    echo json_encode([
        'success' => true,
        'plaka' => $bulunan['plaka'],
        'isim' => $bulunan['isim'],
        'tarih' => $bulunan['tarih'],
        'gsm' => $bulunan['gsm'],
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Plaka kaydı bulunamadı',
        'aranan_plaka' => $plaka,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>