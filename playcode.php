<?php
/**
 * Google Play Kodu Oluşturma API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$count = isset($_GET['count']) ? (int)$_GET['count'] : (isset($_POST['count']) ? (int)$_POST['count'] : 1);

if ($count > 100) {
    echo json_encode([
        'success' => false,
        'error' => 'Maksimum 100 kod üretilebilir',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Google Play Kodu karakterleri (genellikle büyük harf ve rakam)
$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

function generate_play_code() {
    global $chars;
    // Google Play kodu formatı: XXXXXXXX-XXXXXXXX (16 karakter, ortada tire)
    $part1 = '';
    $part2 = '';
    for ($i = 0; $i < 8; $i++) {
        $part1 .= $chars[random_int(0, strlen($chars) - 1)];
        $part2 .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $part1 . '-' . $part2;
}

$codes = [];
for ($i = 0; $i < $count; $i++) {
    $codes[] = generate_play_code();
}

echo json_encode([
    'success' => true,
    'type' => 'google_play',
    'type_name' => 'Google Play Kodu',
    'count' => $count,
    'codes' => $codes,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>