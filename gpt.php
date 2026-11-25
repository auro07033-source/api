<?php
// gptfree.php - gptfree.com agent_stream proxy (PHP)
// Kullanım:
//   /gptfree.php?mesaj=Merhaba
//   /gptfree.php?mesaj=Devam&history=[{"role":"user","content":"..."},{"role":"assistant","content":"..."}]
//   /gptfree.php?mesaj=Merhaba&stream=1
// Gereksinim: PHP 7.4+, curl
// Not: Bearer token ~1 saat geçerli. 401 alırsan gptfree.com'dan tazele.

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$mesaj   = $_POST['mesaj']   ?? $_GET['mesaj']   ?? null;
$history = $_POST['history'] ?? $_GET['history'] ?? "[]";
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

// history: JSON string veya dizi
if (is_string($history)) {
    $historyArr = json_decode($history, true);
    if (!is_array($historyArr)) $historyArr = [];
} else {
    $historyArr = $history;
}

$API = "https://us-central1-gptfree-2.cloudfunctions.net/agent_stream";

// Bearer token ~1 saat geçerli; 401'de gptfree.com'dan yenile
$TOKEN = "eyJhbGciOiJSUzI1NiIsImtpZCI6IjgxODA1ZTc2ZmQ1ZmEyZmQ5MWMyYmIzOWU0YjdlZjA1NDY5YmRkY2UiLCJ0eXAiOiJKV1QifQ.eyJwcm92aWRlcl9pZCI6ImFub255bW91cyIsImlzcyI6Imh0dHBzOi8vc2VjdXJldG9rZW4uZ29vZ2xlLmNvbS9ncHRmcmVlLTIiLCJhdWQiOiJncHRmcmVlLTIiLCJhdXRoX3RpbWUiOjE3OTU1ODk0MjksInVzZXJfaWQiOiJKbXY4U0p6ZjlWWklUUGpVMzJyY3Q1a2hhV28yIiwic3ViIjoiSm12OFNKemY5VlpJVFBqVTMycmN0NWtoYVdvMiIsImlhdCI6MTc5NTU4OTQyOSwiZXhwIjoxNzk1NTkzMDI5LCJmaXJlYmFzZSI6eyJpZGVudGl0aWVzIjp7fSwic2lnbl9pbl9wcm92aWRlciI6ImFub255bW91cyJ9fQ.0Bc29MeHS-cm8LGi0YZhquFisO1635yEllD2bhJ8i5P9lf0XWMxrviyHQL8Uga9CRWqUwJ09hrx9iBgzn1n2PD1pqqBMwlq16irNkCx3lGbqGrMP65I2NlLmBcmSEBaX31r3wBnQIrS41ENZOHvYL7zajMsxIABtj7EoGQndVbxJrfBCUmROOVngJe7JYCKkkgTsV3X6r8PwracHeFZHZvn_Qqtk8RRrucD5Rwokl5t1djKedRYYcvUleP97y5-HyTge7KfoWi40ShyQ2XqdYglMCxqEZTN5dVKIHlcsKuxl1JN26swWkHnP_tQfoK5Vqgl--mQ6Jh4WfJVmAxzkjA";

$HEADERS = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
    "Content-Type: application/json",
    "Authorization: Bearer $TOKEN",
    "Origin: https://gptfree.com",
    "Referer: https://gptfree.com/",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: cross-site",
];

$payload = [
    "message" => $mesaj,
    "images"  => [],
    "history" => $historyArr,
];

// ---- İstek fonksiyonu (429/5xx için 3 kez yeniden dener) ----
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

// ---- SSE parse ----
// Cloud Function yanıtı: data: {"text":"..."}, data: "[DONE]" veya
// {"token":"..."} / {"chunk":"..."} tarzı olabilir. Hepsi denenir.
function sse_parcala($body) {
    $cevap = "";
    $parcalar = [];
    $son = null;

    foreach (explode("\n", $body) as $line) {
        $line = rtrim($line, "\r");
        if ($line === '') continue;

        if (strpos($line, "data: ") === 0) {
            $gövde = trim(substr($line, 6));
            if ($gövde === '' || $gövde === '[DONE]') continue;
            $j = json_decode($gövde, true);
            if (is_array($j)) {
                foreach (["text", "token", "chunk", "delta", "content", "response", "message"] as $k) {
                    if (isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '') {
                        $parcalar[] = $j[$k]; break;
                    }
                }
                if (isset($j['done']) && $j['done']) $son = $j;
                if (isset($j['finish_reason'])) $son = $j;
            } else {
                $parcalar[] = $gövde;
            }
        } else if (strpos($line, "event:") === 0) {
            continue;
        } else {
            // JSON olmayan düz metin
            $j = json_decode($line, true);
            if (is_array($j)) {
                foreach (["text", "token", "chunk", "delta", "content"] as $k) {
                    if (isset($j[$k]) && is_string($j[$k])) { $parcalar[] = $j[$k]; break; }
                }
            } else {
                $parcalar[] = $line;
            }
        }
    }

    $cevap = implode("", $parcalar);
    return [$cevap, $son, $parcalar];
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
            "message"     => "gptfree yanıt vermedi",
            "http"        => $code,
            "error"       => $err,
            "gelistirici" => "@ittizar",
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
        exit;
    }

    foreach (explode("\n", $body) as $line) {
        $line = rtrim($line, "\r");
        if (strpos($line, "data: ") !== 0) continue;
        $gövde = trim(substr($line, 6));
        if ($gövde === '' || $gövde === '[DONE]') {
            if ($gövde === '[DONE]') {
                echo "event: done\n";
                echo "data: " . json_encode(["success" => true, "gelistirici" => "@ittizar"], JSON_UNESCAPED_UNICODE) . "\n\n";
                @flush();
            }
            continue;
        }
        $j = json_decode($gövde, true);
        if (is_array($j)) {
            $parca = '';
            foreach (["text", "token", "chunk", "delta", "content", "response", "message"] as $k) {
                if (isset($j[$k]) && is_string($j[$k]) && $j[$k] !== '') { $parca = $j[$k]; break; }
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
    }

    echo "event: done\n";
    echo "data: " . json_encode([
        "success"     => true,
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
    if ($code === 401) {
        echo json_encode([
            "success"     => false,
            "message"     => "Bearer token süresi dolmuş. gptfree.com'dan tazele.",
            "http"        => 401,
            "gelistirici" => "@ittizar",
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    break;
}

if ($body === false || $code !== 200) {
    echo json_encode([
        "success"     => false,
        "message"     => "gptfree yanıt vermedi",
        "http"        => $code,
        "error"       => $err,
        "ham_bas"     => is_string($body) ? substr($body, 0, 300) : null,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

[$cevap, $son, $parcalar] = sse_parcala($body);

$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "cevap"       => trim($cevap),
];
if ($son) $cikti["son"] = $son;
if (empty($cikti["cevap"])) {
    $cikti["uyari"] = "cevap boş, ham_yanit içine bak";
    $cikti["ham_yanit"] = substr($body, 0, 2000);
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);