<?php
/**
 * IBAN Doğrulama ve Banka Bilgileri API
 * IBAN numarasını doğrular ve banka bilgilerini getirir
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$iban = isset($_GET['iban']) ? strtoupper(trim($_GET['iban'])) : '';
$country = isset($_GET['country']) ? strtoupper(trim($_GET['country'])) : 'TR';

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

// IBAN format kontrolü
function validateIBAN($iban) {
    // IBAN regex (basit kontrol)
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
        return false;
    }
    
    // IBAN doğrulama algoritması (MOD 97)
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

// Türkiye Bankaları (BIN/Yetki kodları)
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
    '00070' => 'TEB',
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
    '00218' => 'MNG Kargo',
    '00247' => 'PTT Bank'
];

// Ülke bilgileri
$countries = [
    'TR' => ['name' => 'Türkiye', 'code' => 'TR', 'length' => 26],
    'DE' => ['name' => 'Almanya', 'code' => 'DE', 'length' => 22],
    'FR' => ['name' => 'Fransa', 'code' => 'FR', 'length' => 27],
    'GB' => ['name' => 'İngiltere', 'code' => 'GB', 'length' => 22],
    'US' => ['name' => 'Amerika', 'code' => 'US', 'length' => 0],
    'NL' => ['name' => 'Hollanda', 'code' => 'NL', 'length' => 18],
    'BE' => ['name' => 'Belçika', 'code' => 'BE', 'length' => 16],
    'ES' => ['name' => 'İspanya', 'code' => 'ES', 'length' => 24],
    'IT' => ['name' => 'İtalya', 'code' => 'IT', 'length' => 27],
    'CH' => ['name' => 'İsviçre', 'code' => 'CH', 'length' => 21],
    'AE' => ['name' => 'Birleşik Arap Emirlikleri', 'code' => 'AE', 'length' => 23],
    'SA' => ['name' => 'Suudi Arabistan', 'code' => 'SA', 'length' => 24],
    'QA' => ['name' => 'Katar', 'code' => 'QA', 'length' => 29],
    'KW' => ['name' => 'Kuveyt', 'code' => 'KW', 'length' => 30],
];

// Banka kodunu çek
function getBankFromIBAN($iban) {
    if (substr($iban, 0, 2) == 'TR') {
        $bank_code = substr($iban, 6, 5);
        global $turkey_banks;
        return $turkey_banks[$bank_code] ?? 'Bilinmeyen Banka';
    }
    return 'Banka bilgisi yok';
}

// Ülke bilgisi
$country_code = substr($iban, 0, 2);
$country_info = $countries[$country_code] ?? ['name' => 'Bilinmeyen', 'code' => $country_code, 'length' => 0];

// Doğrulama
$is_valid = validateIBAN($iban);

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

// Banka bilgisi
$bank_name = getBankFromIBAN($iban);

// Sonuç
$result = [
    'success' => true,
    'iban' => $iban,
    'valid' => true,
    'country_code' => $country_code,
    'country' => $country_info['name'],
    'bank' => $bank_name,
    'check_digits' => substr($iban, 2, 2),
    'bban' => substr($iban, 4),
    'length' => strlen($iban),
    'expected_length' => $country_info['length'],
    'telegram' => '@unutur'
];

// Ek IBAN detayları (Türkiye için)
if ($country_code == 'TR') {
    $result['bank_code'] = substr($iban, 6, 5);
    $result['branch_code'] = substr($iban, 11, 5);
    $result['account_number'] = substr($iban, 16);
    $result['account_number_length'] = strlen($result['account_number']);
    
    // Hesap numarası formatı
    if (strlen($result['account_number']) == 10) {
        $result['account_formatted'] = substr($result['account_number'], 0, 1) . ' ' . 
                                       substr($result['account_number'], 1, 2) . ' ' . 
                                       substr($result['account_number'], 3);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>