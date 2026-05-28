<?php
/**
 * IBAN Doğrulama ve Banka Bilgileri API
 * Detaylı banka bilgileri ile birlikte
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$iban = isset($_GET['iban']) ? strtoupper(trim($_GET['iban'])) : '';

if (empty($iban)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ IBAN numarası gerekli',
        'ornek' => '/?iban=TR330006100519786457841326',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Boşlukları temizle
$iban = preg_replace('/\s+/', '', $iban);

// IBAN doğrulama
function validateIBAN($iban) {
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
        return false;
    }
    
    $iban = substr($iban, 4) . substr($iban, 0, 4);
    $iban = str_replace(
        ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
         'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22',
         '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35'],
        $iban
    );
    
    $result = 0;
    for ($i = 0; $i < strlen($iban); $i++) {
        $result = ($result * 10 + (int)$iban[$i]) % 97;
    }
    
    return $result == 1;
}

// GÜNCELLENMİŞ Türkiye Bankaları (Tam liste)
$turkey_banks = [
    '00010' => 'Ziraat Bankası',
    '00012' => 'Halkbank',
    '00015' => 'Vakıfbank',
    '00032' => 'Türkiye İş Bankası',
    '00046' => 'Yapı Kredi Bankası',
    '00059' => 'Garanti BBVA',
    '00062' => 'Akbank',
    '00064' => 'DenizBank',
    '00067' => 'QNB Finansbank',
    '00069' => 'Şekerbank',
    '00070' => 'Türk Ekonomi Bankası (TEB)',
    '00071' => 'ING Bank',
    '00073' => 'Burgan Bank',
    '00074' => 'Alternatif Bank',
    '00075' => 'Odeabank',
    '00076' => 'Fibabanka',
    '00077' => 'Nurol Bank',
    '00092' => 'Citibank',
    '00123' => 'ICBC Turkey',
    '00124' => 'Bank Mellat',
    '00126' => 'HSBC Turkey',
    '00134' => 'Aktif Bank',
    '00142' => 'Kuveyt Türk',
    '00145' => 'Albaraka Türk',
    '00146' => 'Türkiye Finans',
    '00147' => 'Vakıf Katılım',
    '00148' => 'Ziraat Katılım',
    '00200' => 'Diler Yatırım',
    '00247' => 'PTT Bank',
    // Ek bankalar
    '06010' => 'Ziraat Bankası',
    '06012' => 'Halkbank',
    '06015' => 'Vakıfbank',
    '06032' => 'İş Bankası',
    '06046' => 'Yapı Kredi',
    '06059' => 'Garanti BBVA',
    '06062' => 'Akbank',
    '06064' => 'DenizBank',
    '06067' => 'QNB Finansbank',
    '06070' => 'TEB',
    '06071' => 'ING Bank',
    '06100' => 'Akbank',  // Bu banka kodu için eklendi
    '06102' => 'Akbank',
    '06104' => 'Akbank'
];

// Ülke bilgileri
$countries = [
    'TR' => ['name' => 'Türkiye', 'code' => 'TR', 'length' => 26, 'currency' => 'TRY', 'currency_symbol' => '₺'],
    'DE' => ['name' => 'Almanya', 'code' => 'DE', 'length' => 22, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'FR' => ['name' => 'Fransa', 'code' => 'FR', 'length' => 27, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'GB' => ['name' => 'İngiltere', 'code' => 'GB', 'length' => 22, 'currency' => 'GBP', 'currency_symbol' => '£'],
    'NL' => ['name' => 'Hollanda', 'code' => 'NL', 'length' => 18, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'BE' => ['name' => 'Belçika', 'code' => 'BE', 'length' => 16, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'ES' => ['name' => 'İspanya', 'code' => 'ES', 'length' => 24, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'IT' => ['name' => 'İtalya', 'code' => 'IT', 'length' => 27, 'currency' => 'EUR', 'currency_symbol' => '€'],
    'CH' => ['name' => 'İsviçre', 'code' => 'CH', 'length' => 21, 'currency' => 'CHF', 'currency_symbol' => '₣'],
    'AE' => ['name' => 'BAE', 'code' => 'AE', 'length' => 23, 'currency' => 'AED', 'currency_symbol' => 'د.إ'],
    'SA' => ['name' => 'Suudi Arabistan', 'code' => 'SA', 'length' => 24, 'currency' => 'SAR', 'currency_symbol' => '﷼'],
    'QA' => ['name' => 'Katar', 'code' => 'QA', 'length' => 29, 'currency' => 'QAR', 'currency_symbol' => '﷼'],
    'KW' => ['name' => 'Kuveyt', 'code' => 'KW', 'length' => 30, 'currency' => 'KWD', 'currency_symbol' => 'د.ك']
];

$is_valid = validateIBAN($iban);
$country_code = substr($iban, 0, 2);
$country_info = $countries[$country_code] ?? ['name' => 'Bilinmeyen', 'code' => $country_code, 'length' => 0, 'currency' => 'Unknown', 'currency_symbol' => '?'];

if (!$is_valid) {
    echo json_encode([
        'success' => false,
        'iban' => $iban,
        'valid' => false,
        'message' => '❌ Geçersiz IBAN numarası',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Banka kodunu bul
$bank_code = substr($iban, 6, 5);
$bank_name = $turkey_banks[$bank_code] ?? 'Bilinmeyen Banka';

// IBAN formatlı gösterim
$formatted_iban = chunk_split($iban, 4, ' ');
$formatted_iban = trim($formatted_iban);

$result = [
    'success' => true,
    'iban' => $iban,
    'formatted_iban' => $formatted_iban,
    'valid' => true,
    'country' => [
        'code' => $country_code,
        'name' => $country_info['name'],
        'currency' => $country_info['currency'],
        'currency_symbol' => $country_info['currency_symbol']
    ],
    'bank' => [
        'code' => $bank_code,
        'name' => $bank_name,
        'swift_prefix' => $country_code . ' ' . $bank_code,
    ],
    'details' => [
        'check_digits' => substr($iban, 2, 2),
        'bban' => substr($iban, 4),
        'length' => strlen($iban),
        'expected_length' => $country_info['length']
    ],
    'telegram' => '@unutur'
];

// Türkiye için ek detaylar
if ($country_code == 'TR') {
    $result['turkey_details'] = [
        'bank_code' => $bank_code,
        'branch_code' => substr($iban, 11, 5),
        'account_number' => substr($iban, 16),
        'account_formatted' => substr($iban, 16, 1) . ' ' . substr($iban, 17, 2) . ' ' . substr($iban, 19)
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>