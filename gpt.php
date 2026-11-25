<?php
// gpt.php - notrack.ai sohbet proxy (PHP, chat_id + gecikme + 429 retry)
// Kullanım:
//   /gpt.php?mesaj=Merhaba
//   /gpt.php?mesaj=Devam&chat_id=abc123
//   /gpt.php?mesaj=Merhaba&stream=1  (SSE canlı akış)
// Gereksinim: PHP 7.4+, curl

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$mesaj   = $_POST['mesaj']   ?? $_GET['mesaj']   ?? null;
$chat_id = $_POST['chat_id'] ?? $_GET['chat_id'] ?? null;
$stream  = isset($_GET['stream']) && $_GET['stream'] === '1';

// Aynı IP'den hızlı istekleri yumuşat (dosya tabanlı, 3 sn minimum)
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = trim(explode(',', $ip)[0]);
$lockFile = sys_get_temp_dir() . "/notrack_" . md5($ip) . ".lock";

if ($mesaj === null || trim($mesaj) === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success"     => false,
        "message"     => "mesaj parametresi gerekli",
        "ornek"       => "?mesaj=Merhaba",
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$MIN_INTERVAL = 3;
if (file_exists($lockFile)) {
    $son = (int)@file_get_contents($lockFile);
    $fark = time() - $son;
    if ($fark < $MIN_INTERVAL) sleep($MIN_INTERVAL - $fark);
}
@file_put_contents($lockFile, time());

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

$cookieStr = [];
foreach ($COOKIES as $k => $v) $cookieStr[] = "$k=$v";
$cookieStr = implode("; ", $cookieStr);

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

function notrack_post($URL, $HEADERS, $cookieStr, $payload) {
    $ch = curl_init($URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array_merge($HEADERS, ["Cookie: $cookieStr"]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$body, $code, $err];
}

// ---- STREAM ----
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    $yeni_chat_id = null;
    $buffer = "";

    $ch = curl_init($URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array_merge($HEADERS, ["Cookie: $cookieStr"]),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
    ]);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$yeni_chat_id) {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = rtrim($line, "\r");
            if (strpos($line, "data: ") !== 0) continue;
            $data = json_decode(substr($line, 6), true);
            if (!is_array($data)) continue;

            if (($data['type'] ?? '') === 'chat_meta' && !$yeni_chat_id) {
                $yeni_chat_id = $data['chat_id'] ?? null;
                echo "event: chat_id\n";
                echo "data: " . json_encode(["chat_id" => $yeni_chat_id], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
            if (($data['type'] ?? '') === 'message' && ($data['speaker'] ?? '') === 'C') {
                echo "event: message\n";
                echo "data: " . json_encode(["content" => $data['content'] ?? ''], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        }
        return strlen($chunk);
    });
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    echo "event: done\n";
    echo "data: " . json_encode([
        "success"     => ($code === 200 && $err === ""),
        "http"        => $code,
        "chat_id"     => $yeni_chat_id,
        "error"       => $err ?: null,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

// ---- NORMAL ----
header('Content-Type: application/json; charset=utf-8');

$body = null; $code = 0; $err = "";
for ($i = 0; $i < 3; $i++) {
    [$body, $code, $err] = notrack_post($URL, $HEADERS, $cookieStr, $payload);
    if ($code === 200) break;
    if (in_array($code, [429, 500, 502, 503, 504], true)) {
        sleep(5 * ($i + 1));
        continue;
    }
    break;
}

if ($body === false || $code !== 200) {
    echo json_encode([
        "success"     => false,
        "message"     => $code === 429 ? "notrack.ai rate limit (429). Daha sonra dene." : "notrack.ai yanıt vermedi",
        "http"        => $code,
        "error"       => $err,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$yeni_chat_id = null;
$icerik = "";
foreach (explode("\n", $body) as $line) {
    $line = rtrim($line, "\r");
    if (strpos($line, "data: ") !== 0) continue;
    $data = json_decode(substr($line, 6), true);
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