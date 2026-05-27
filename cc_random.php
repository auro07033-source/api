<?php
/**
 * Random Credit Card Generator API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

/**
 * Luhn algoritması ile kart numarası doğrulama
 */
function luhn_check($card) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($card) - 1; $i >= 0; $i--) {
        $n = $card[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) $n -= 9;
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10 == 0);
}

/**
 * Geçerli kart numarası oluştur
 */
function generate_card($bin, $length = 16) {
    $card = $bin;
    while (strlen($card) < $length - 1) {
        $card .= rand(0, 9);
    }
    for ($i = 0; $i <= 9; $i++) {
        $test = $card . $i;
        if (luhn_check($test)) {
            return $test;
        }
    }
    return $card . '0';
}

/**
 * Rastgele son kullanma tarihi oluştur
 */
function generate_expiry() {
    $month = rand(1, 12);
    $year = rand(date('Y') + 1, date('Y') + 5);
    return sprintf("%02d|%d", $month, $year);
}

/**
 * Rastgele CVV oluştur
 */
function generate_cvv() {
    return rand(100, 999);
}

/**
 * Kart tipini belirle
 */
function get_card_type($bin) {
    $first = substr($bin, 0, 1);
    $first_two = substr($bin, 0, 2);
    $first_four = substr($bin, 0, 4);
    
    if ($first == '4') return 'VISA';
    if (in_array($first_two, ['51', '52', '53', '54', '55'])) return 'MasterCard';
    if (in_array($first_two, ['34', '37'])) return 'American Express';
    if ($first_four == '6011') return 'Discover';
    if (in_array($first_two, ['36', '38'])) return 'Diners Club';
    if ($first_two == '35') return 'JCB';
    return 'Unknown';
}

// Parametreler
$adet = isset($_GET['adet']) ? min((int)$_GET['adet'], 100) : 1;
$bin = isset($_GET['bin']) ? preg_replace('/[^0-9]/', '', $_GET['bin']) : null;

// BIN kodları (kart başlangıç numaraları)
$bin_list = [
    '4' => 'VISA',
    '51' => 'MasterCard',
    '52' => 'MasterCard',
    '53' => 'MasterCard',
    '54' => 'MasterCard',
    '55' => 'MasterCard',
    '34' => 'American Express',
    '37' => 'American Express',
    '6011' => 'Discover',
    '36' => 'Diners Club',
    '38' => 'Diners Club',
    '35' => 'JCB'
];

if ($bin && strlen($bin) < 16) {
    // Belirtilen BIN ile üret
    $cards = [];
    for ($i = 0; $i < $adet; $i++) {
        $card_no = generate_card($bin);
        $expiry = generate_expiry();
        $cvv = generate_cvv();
        $type = get_card_type(substr($card_no, 0, 6));
        
        $cards[] = [
            'card_number' => $card_no,
            'expiry' => $expiry,
            'cvv' => $cvv,
            'type' => $type
        ];
    }
    
    echo json_encode([
        'success' => true,
        'adet' => $adet,
        'bin' => $bin,
        'cards' => $cards,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} else {
    // Rastgele kart üret
    $cards = [];
    for ($i = 0; $i < $adet; $i++) {
        $random_bin = array_rand($bin_list);
        $card_no = generate_card($random_bin);
        $expiry = generate_expiry();
        $cvv = generate_cvv();
        $type = get_card_type(substr($card_no, 0, 6));
        
        $cards[] = [
            'card_number' => $card_no,
            'expiry' => $expiry,
            'cvv' => $cvv,
            'type' => $type
        ];
    }
    
    echo json_encode([
        'success' => true,
        'adet' => $adet,
        'cards' => $cards,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>