<?php
// chatgpt4online.php - chatgpt4online.org (mwai) sohbet proxy (PHP)
// Kullanım:
//   /chatgpt4online.php?mesaj=Merhaba
//   /chatgpt4online.php?mesaj=Devam&chat_id=3t5591dr4z8&session=xxx&context_id=58
//   /chatgpt4online.php?mesaj=Merhaba&stream=1  (SSE)
// Gereksinim: PHP 7.4+, curl

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$mesaj      = $_POST['mesaj']      ?? $_GET['mesaj']      ?? null;
$chat_id    = $_POST['chat_id']    ?? $_GET['chat_id']    ?? null;
$session    = $_POST['session']    ?? $_GET['session']    ?? "3c006c48df0abf79d521092536147ab9";
$context_id = $_POST['context_id'] ?? $_GET['context_id'] ?? 58;
$stream     = isset($_GET['stream']) && $_GET['stream'] === '1';

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

$API = "https://chatgpt4online.org/wp-json/mwai-ui/v1/chats/submit";

// X-WP-Nonce süreli; ağ sekmesinden tazele
$NONCE = "abb8f8810f";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: text/event-stream",
    "Accept-Language: tr-TR",
    "Content-Type: application/json",
    "X-WP-Nonce: $NONCE",
    "Origin: https://chatgpt4online.org",
    "Referer: https://chatgpt4online.org/",
];

// chat_id yoksa üret (altsayı+harf karışık kısa ID)
if (!$chat_id) {
    $chat_id = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 11);
}

$payload = [
    "botId"      => "chatbot-on9iow",
    "customId"   => null,
    "session"    => $session,
    "chatId"     => $chat_id,
    "contextId"  => (int)$context_id,
    "messages"   => [
        [
            "id"        => "start-" . (int)(microtime(true) * 1000),
            "role"      => "assistant",
            "content"   => "Hi! How can I help you?",
            "who"       => "AI: ",
            "timestamp" => (int)(microtime(true) * 1000),
            "key"       => "start-" . (int)(microtime(true) * 1000),
        ],
    ],
    "newMessage" => $mesaj,
    "newFileId"  => null,
    "newFileIds" => [],
    "stream"     => true, // sunucuya hep stream=true
];

// --- İstek fonksiyonu (429/5xx için 3 kez yeniden dener) ---
function istek($API, $HEADERS, $payload) {
    $ch = curl_init($API);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $HEADERS,
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

// --- SSE parse: {"type":"live","data":"..."}, {"type":"end","data":"{json}"} ---
function sse_parcala($body) {
    $parcalar = [];
    $cevap = "";
    $son = null;
    $yeniChatId = null;

    foreach (explode("\n", $body) as $line) {
        $line = rtrim($line, "\r");
        if (strpos($line, "data: ") !== 0) continue;
        $json = substr($line, 6);
        $d = json_decode($json, true);
        if (!is_array($d)) continue;

        $tip = $d['type'] ?? '';
        if ($tip === 'live') {
            $parca = $d['data'] ?? '';
            if ($parca !== '') $parcalar[] = $parca;
        } elseif ($tip === 'end') {
            $ic = json_decode($d['data'] ?? '{}', true);
            if (is_array($ic)) {
                $son = $ic;
                $cevap = $ic['reply'] ?? '';
                $yeniChatId = $ic['chatId'] ?? ($ic['chat_id'] ?? null);
            }
        } elseif ($tip === 'error') {
            $son = $d;
        }
    }

    if ($cevap === '') $cevap = implode("", $parcalar);
    return [$cevap, $son, $yeniChatId, $parcalar];
}

// ---- STREAM ----
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    [$body, $code, $err] = istek($API, $HEADERS, $payload);

    if ($body === false || $code !== 200) {
        echo "event: error\n";
        echo "data: " . json_encode([
            "success"     => false,
            "message"     => "chatgpt4online yanıt vermedi",
            "http"        => $code,
            "error"       => $err,
            "gelistirici" => "@ittizar",
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
        exit;
    }

    // Gelen SSE'yi satır satır aktar
    $yeniChatId = $chat_id;
    foreach (explode("\n", $body) as $line) {
        $line = rtrim($line, "\r");
        if (strpos($line, "data: ") !== 0) continue;
        $d = json_decode(substr($line, 6), true);
        if (!is_array($d)) continue;

        $tip = $d['type'] ?? '';
        if ($tip === 'live') {
            echo "event: message\n";
            echo "data: " . json_encode(["content" => $d['data'] ?? ''], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        } elseif ($tip === 'end') {
            $ic = json_decode($d['data'] ?? '{}', true);
            if (is_array($ic)) {
                $yeniChatId = $ic['chatId'] ?? $yeniChatId;
                echo "event: end\n";
                echo "data: " . json_encode([
                    "reply"   => $ic['reply'] ?? '',
                    "chat_id" => $yeniChatId,
                    "usage"   => $ic['usage'] ?? null,
                ], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
        } elseif ($tip === 'error') {
            echo "event: error\n";
            echo "data: " . json_encode($d, JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        }
    }

    echo "event: done\n";
    echo "data: " . json_encode([
        "success"     => true,
        "chat_id"     => $yeniChatId,
        "session"     => $session,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

// ---- NORMAL (buffer'lı, retry'lı) ----
header('Content-Type: application/json; charset=utf-8');

$body = null; $code = 0; $err = "";
for ($i = 0; $i < 3; $i++) {
    [$body, $code, $err] = istek($API, $HEADERS, $payload);
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
        "message"     => "chatgpt4online yanıt vermedi",
        "http"        => $code,
        "error"       => $err,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

[$cevap, $son, $yeniChatId, $parcalar] = sse_parcala($body);

$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "chat_id"     => $yeniChatId ?: $chat_id,
    "session"     => $session,
    "cevap"       => trim($cevap),
];

if (!empty($son['usage'])) $cikti["usage"] = $son['usage'];
if (empty($cikti["cevap"])) {
    $cikti["uyari"] = "cevap boş, ham_yanit içine bak";
    $cikti["ham_yanit"] = substr($body, 0, 2000);
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);