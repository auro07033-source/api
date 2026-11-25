<?php
// claila.php - Claila AI sohbet proxy (PHP)
// Kullanım:
//   /claila.php?mesaj=Merhaba
//   /claila.php?mesaj=Devam&session=1795587006
//   /claila.php?mesaj=Merhaba&stream=1   (SSE canlı akış)
// Gereksinim: PHP 7.4+, curl

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$mesaj   = $_POST['mesaj']   ?? $_GET['mesaj']   ?? null;
$session = $_POST['session'] ?? $_GET['session'] ?? null;
$stream  = isset($_GET['stream']) && $_GET['stream'] === '1';

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

$session = $session ?: (string)time();

$API = "https://app.claila.com/api/v2/aichat31";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
    "X-CSRF-Token: 055b231c06218424884b26fde740d9335fa42adcaeed753174c7e1b36b59abe8",
    "X-Requested-With: XMLHttpRequest",
    "Origin: https://app.claila.com",
    "Referer: https://app.claila.com/chat?lang=tr&autostart=1&prompt=" . urlencode(mb_substr($mesaj, 0, 30)),
];

$COOKIES = [
    "dmcfkjn3cdc"       => "e106p3a7ol7ebmboosmjm8pq57",
    "claila_fcm_first"  => "1795586962",
    "_ct_uid"           => "cd4d18846e76cac2c4d3bb51e559428e",
    "_fl_sid"           => "6677e0c654a297f026c31613dbe8a6d5",
    "fs_ux"             => "edd78d80-2d1b-454f-9cad-b28d2be07ff0",
    "lang"              => "tr",
    "claila_lens_ok"    => "0",
    "claila_anon"       => "ff40a39c8dc83486b71e5446fd65e5bf8558002f37234d2c2e9bfedc003f0b23",
    "theme"             => "dark",
];
$cookieStr = [];
foreach ($COOKIES as $k => $v) $cookieStr[] = "$k=$v";
$cookieStr = implode("; ", $cookieStr);

$POST = http_build_query([
    "model"         => "claila",
    "response_mode" => "auto",
    "message"       => $mesaj,
    "sessionId"     => $session,
    "chat_mode"     => "chat",
    "websearch"     => "false",
    "tmp_enabled"   => "0",
]);

// ---- STREAM ----
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    $buffer = "";

    $ch = curl_init($API);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $POST,
        CURLOPT_HTTPHEADER     => array_merge($HEADERS, ["Cookie: $cookieStr"]),
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
    ]);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer) {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = rtrim($line, "\r");

            if (strpos($line, "data: ") === 0) {
                $gövde = trim(substr($line, 6));
                if ($gövde === '' || $gövde === '[DONE]') continue;
                $j = json_decode($gövde, true);
                if (is_array($j)) {
                    $parca = '';
                    foreach (["response", "message", "content", "text", "delta"] as $a) {
                        if (isset($j[$a]) && is_string($j[$a]) && $j[$a] !== '') { $parca = $j[$a]; break; }
                    }
                    if ($parca !== '') {
                        echo "event: message\n";
                        echo "data: " . json_encode(["content" => $parca], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                    }
                } else {
                    echo "event: message\n";
                    echo "data: " . json_encode(["content" => $gövde], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }
            } else if ($line !== '') {
                echo "event: message\n";
                echo "data: " . json_encode(["content" => $line], JSON_UNESCAPED_UNICODE) . "\n\n";
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
        "session"     => $session,
        "error"       => $err ?: null,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

// ---- NORMAL ----
header('Content-Type: application/json; charset=utf-8');

$ch = curl_init($API);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $POST,
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

if ($body === false || $code !== 200) {
    echo json_encode([
        "success"     => false,
        "message"     => "claila yanıt vermedi",
        "http"        => $code,
        "error"       => $err,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// JSON mu düz metin/SSE mi?
$j = json_decode($body, true);

if (is_array($j)) {
    // JSON yanıt
    $cevap = "";
    foreach (["response", "message", "content", "text", "answer", "output"] as $a) {
        if (isset($j[$a]) && is_string($j[$a]) && $j[$a] !== '') { $cevap = $j[$a]; break; }
        if (isset($j[$a]) && is_array($j[$a])) {
            $iç = $j[$a]['content'] ?? $j[$a]['text'] ?? null;
            if (is_string($iç) && $iç !== '') { $cevap = $iç; break; }
        }
    }
    if ($cevap === "" && isset($j['data']) && is_array($j['data'])) {
        foreach (["response", "message", "content", "text"] as $a) {
            if (isset($j['data'][$a]) && is_string($j['data'][$a])) { $cevap = $j['data'][$a]; break; }
        }
    }
    $yeniSession = (string)($j['sessionId'] ?? $j['session_id'] ?? $session);

    echo json_encode([
        "success"     => true,
        "gelistirici" => "@ittizar",
        "session"     => $yeniSession,
        "cevap"       => trim($cevap),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// JSON değil: SSE veya düz metin olarak parse
$parcalar = [];
$yeniSession = $session;
foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
    $line = trim($line);
    if ($line === '') continue;

    if (strpos($line, "data: ") === 0) {
        $gövde = trim(substr($line, 6));
        if ($gövde === '' || $gövde === '[DONE]') continue;
        $jj = json_decode($gövde, true);
        if (is_array($jj)) {
            $yeniSession = (string)($jj['sessionId'] ?? $jj['session_id'] ?? $yeniSession);
            foreach (["response", "message", "content", "text", "delta"] as $a) {
                if (isset($jj[$a]) && is_string($jj[$a]) && $jj[$a] !== '') {
                    $parcalar[] = $jj[$a]; break;
                }
            }
        } else {
            $parcalar[] = $gövde;
        }
    } else {
        $parcalar[] = $line;
    }
}

echo json_encode([
    "success"     => true,
    "gelistirici" => "@ittizar",
    "session"     => $yeniSession,
    "cevap"       => trim(implode("", $parcalar)),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);