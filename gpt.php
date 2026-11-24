<?php
// notrack_api.php - notrack.ai sohbet API proxy (PHP)
// Kullanım:
//   GET/POST /notrack_api.php?mesaj=Merhaba
//   GET/POST /notrack_api.php?mesaj=Devam&chat_id=xxxxx
//   ?stream=1  -> SSE (text/event-stream) olarak canlı akıtır
// Gereksinim: PHP 7.4+, curl

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$mesaj   = $_POST['mesaj']   ?? $_GET['mesaj']   ?? null;
$chat_id = $_POST['chat_id'] ?? $_GET['chat_id'] ?? null;
$stream  = isset($_GET['stream']) && $_GET['stream'] === '1';

if ($mesaj === null || trim($mesaj) === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => false,
        "message" => "mesaj parametresi gerekli",
        "ornek"   => "?mesaj=Merhaba",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$URL = "https://notrack.ai/api/dispatch";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
    "Content-Type: application/json",
    "Referer: https://notrack.ai/tr/chat",
    "Origin: https://notrack.ai",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin",
];

$COOKIES = [
    "si_usr_id" => "1795395693-v-j4QULqTYbSSCcRQR1dg7",
    "si_ses_id" => "1795395693-v-j4QULqTYbSSCcRQR1dg7",
    "uid"       => "10133feb-1a97-4359-b418-b25e25a1f30d",
];

$payload = [
    "user_input"  => $mesaj,
    "mode"        => "usual",
    "model"       => "C",
    "persona"     => "normal",
    "max_turns"   => 6,
    "chat_id"     => $chat_id,
    "attachments" => [],
    "regenerate"  => false,
    "edit"        => false,
    "edit_mid"    => null,
    "via"         => "typed",
];

$cookieStr = [];
foreach ($COOKIES as $k => $v) $cookieStr[] = "$k=$v";
$cookieStr = implode("; ", $cookieStr);

// ---- İstek hazırla ----
$ch = curl_init($URL);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => array_merge($HEADERS, ["Cookie: $cookieStr"]),
    CURLOPT_RETURNTRANSFER => !$stream,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_ENCODING       => "",
]);

if ($stream) {
    // SSE olarak canlı akıt
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    $yeni_chat_id = null;
    $buffer = "";

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$yeni_chat_id) {
        $buffer .= $chunk;

        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = rtrim($line, "\r");

            if ($line === '' || strpos($line, "data: ") !== 0) continue;
            $jsonStr = substr($line, 6);
            $data = json_decode($jsonStr, true);
            if (!is_array($data)) continue;

            if (($data['type'] ?? '') === 'chat_meta' && !$yeni_chat_id) {
                $yeni_chat_id = $data['chat_id'] ?? null;
                echo "event: chat_id\n";
                echo "data: " . json_encode(["chat_id" => $yeni_chat_id], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }

            if (($data['type'] ?? '') === 'message' && ($data['speaker'] ?? '') === 'C') {
                echo "event: message\n";
                echo "data: " . json_encode([
                    "content" => $data['content'] ?? ''
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        }
        return strlen($chunk);
    });

    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    echo "event: done\n";
    echo "data: " . json_encode([
        "success"   => $err === "",
        "chat_id"   => $yeni_chat_id,
        "error"     => $err ?: null,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

// ---- Stream yok: tüm yanıtı topla ----
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json; charset=utf-8');

if ($body === false || $code !== 200) {
    echo json_encode([
        "success" => false,
        "message" => "notrack.ai yanıt vermedi",
        "http"    => $code,
        "error"   => $err,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$yeni_chat_id = null;
$icerik = "";
foreach (explode("\n", $body) as $line) {
    $line = rtrim($line, "\r");
    if (strpos($line, "data: ") !== 0) continue;
    $jsonStr = substr($line, 6);
    $data = json_decode($jsonStr, true);
    if (!is_array($data)) continue;

    if (($data['type'] ?? '') === 'chat_meta' && !$yeni_chat_id) {
        $yeni_chat_id = $data['chat_id'] ?? null;
    }
    if (($data['type'] ?? '') === 'message' && ($data['speaker'] ?? '') === 'C') {
        $icerik .= ($data['content'] ?? '');
    }
}

echo json_encode([
    "success"     => true,
    "gelistirici" => "@ittizar",
    "chat_id"     => $yeni_chat_id,
    "cevap"       => trim($icerik),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);