<?php
/**
 * BIN Sorgulama API
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$bin = isset($_GET['bin']) ? trim($_GET['bin']) : '';

if (!$bin) {
    echo json_encode([
        'durum' => false,
        'hata' => '❌ BIN kodu gerekli (6 haneli)',
        'ornek' => '/?bin=454671',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\d{6}$/', $bin)) {
    echo json_encode([
        'durum' => false,
        'hata' => '❌ BIN kodu 6 haneli olmalı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Türkiye Banka BIN Veritabanı
$banks = [
    // İş Bankası
    '454671' => ['banka' => '🏦 İş Bankası', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '450803' => ['banka' => '🏦 İş Bankası', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '454672' => ['banka' => '🏦 İş Bankası', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    '450841' => ['banka' => '🏦 İş Bankası', 'tip' => '💳 BANKOMAT', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    
    // Garanti BBVA
    '428945' => ['banka' => '🏦 Garanti BBVA', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '428946' => ['banka' => '🏦 Garanti BBVA', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '540667' => ['banka' => '🏦 Garanti BBVA', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 MASTERCARD', 'canli' => '✅ YÜKSEK'],
    '540669' => ['banka' => '🏦 Garanti BBVA', 'tip' => '💳 BANKOMAT', 'marka' => '💳 MASTERCARD', 'canli' => '⚠️ ORTA'],
    
    // Yapı Kredi
    '450630' => ['banka' => '🏦 Yapı Kredi', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '450631' => ['banka' => '🏦 Yapı Kredi', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    
    // Ziraat Bankası
    '454655' => ['banka' => '🏦 Ziraat Bankası', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    '540111' => ['banka' => '🏦 Ziraat Bankası', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 MASTERCARD', 'canli' => '⚠️ ORTA'],
    
    // Akbank
    '428626' => ['banka' => '🏦 Akbank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    '428627' => ['banka' => '🏦 Akbank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '✅ YÜKSEK'],
    
    // DenizBank
    '428948' => ['banka' => '🏦 DenizBank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    '540036' => ['banka' => '🏦 DenizBank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 MASTERCARD', 'canli' => '⚠️ ORTA'],
    
    // QNB Finansbank
    '428946' => ['banka' => '🏦 QNB Finansbank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    
    // TEB
    '428945' => ['banka' => '🏦 TEB', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA'],
    
    // ING Bank
    '428945' => ['banka' => '🏦 ING Bank', 'tip' => '💳 KREDİ KARTI', 'marka' => '💳 VISA', 'canli' => '⚠️ ORTA']
];

if (isset($banks[$bin])) {
    $data = $banks[$bin];
    
    // Luhn ile örnek kart üret
    function ornekKart($bin) {
        $kart = $bin . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        $rakamlar = str_split($kart);
        $toplam = 0;
        $alternatif = false;
        for ($i = strlen($kart) - 1; $i >= 0; $i--) {
            $n = $rakamlar[$i];
            if ($alternatif) {
                $n *= 2;
                if ($n > 9) $n -= 9;
            }
            $toplam += $n;
            $alternatif = !$alternatif;
        }
        $kontrol = (10 - ($toplam % 10)) % 10;
        return $kart . $kontrol;
    }
    
    echo json_encode([
        'durum' => true,
        'bin' => $bin,
        'banka' => $data['banka'],
        'kart_tipi' => $data['tip'],
        'kart türü' => $data['marka'],
        'Canlı mı?' => $data['canli'],
        'kart numarası' => ornekKart($bin),
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'durum' => false,
        'bin' => $bin,
        'mesaj' => '❌ BIN kodu bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>