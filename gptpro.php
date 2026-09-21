<?php
// gptpro.php - Key gerektirmeyen AI proxy
// Pollinations.ai + DuckDuckGo AI
// Telegram: @cmrbaskani
// Kullanım:
//   ?list=1
//   ?model=pollinations&q=merhaba
//   ?model=duck-gpt4o-mini&q=merhaba
//   ?model=duck-claude&q=merhaba
//   ?model=duck-llama&q=merhaba

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ═══════════ MODELLER ═══════════
$MODELLER = [
    "pollinations"       => ["isim" => "Pollinations · OpenAI",  "tip" => "pollinations", "model" => "openai"],
    "pollinations-mistral" => ["isim" => "Pollinations · Mistral", "tip" => "pollinations", "model" => "mistral"],
    "pollinations-llama" => ["isim" => "Pollinations · Llama",   "tip" => "pollinations", "model" => "llama"],
    "duck-gpt4o-mini"    => ["isim" => "DuckDuckGo · GPT-4o Mini", "tip" => "duck", "model" => "gpt-4o-mini"],
    "duck-claude"        => ["isim" => "DuckDuckGo · Claude 3 Haiku", "tip" => "duck", "model" => "claude-3-haiku-20240307"],
    "duck-llama"         => ["isim" => "DuckDuckGo · Llama 3.3 70B", "tip" => "duck", "model" => "meta-llama/Llama-3.3-70B-Instruct-Turbo"],
    "duck-mistral"       => ["isim" => "DuckDuckGo · Mistral Small", "tip" => "duck", "model" => "mistralai/Mistral-Small-24B-Instruct-2501"],
];

// ═══════════ LİSTE ═══════════
if (isset($_GET['list'])) {
    $out = [];
    foreach ($MODELLER as $k => $v) {
        $out[] = ["id" => $k, "isim" => $v["isim"]];
    }
    echo json_encode([
        "success"  => true,
        "modeller" => $out,
        "telegram" => "@cmrbaskani",
        "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════ PARAMETRELER ═══════════
$model = $_GET['model'] ?? $_POST['model'] ?? '';
$q     = $_GET['q']     ?? $_POST['q']     ?? '';

if ($model === '' || $q === '') {
    http_response_code(400);
    echo json_encode([
        "success"  => false,
        "error"    => "model ve q parametreleri gerekli",
        "modeller" => array_keys($MODELLER),
        "telegram" => "@cmrbaskani",
        "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!isset($MODELLER[$model])) {
    http_response_code(400);
    echo json_encode([
        "success"  => false,
        "error"    => "Gecersiz model: $model",
        "modeller" => array_keys($MODELLER),
        "telegram" => "@cmrbaskani",
        "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$aktif = $MODELLER[$model];

// ═══════════ YARDIMCI ═══════════
function http_get($url, $headers = [], $timeout = 90) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array_merge([
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36",
            "Accept: application/json, text/plain, */*",
        ], $headers),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ["body" => $body, "code" => $code, "error" => $err];
}

function http_post_json($url, $payload, $headers = [], $timeout = 90) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => array_merge([
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36",
            "Accept: text/event-stream, application/json",
        ], $headers),
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ["body" => $body, "code" => $code, "error" => $err];
}

// ═══════════ ÇAĞRI ═══════════
$t0 = microtime(true);
$cevap = "";
$hata  = null;

// ─── POLLINATIONS ───
if ($aktif["tip"] === "pollinations") {
    $url = "https://text.pollinations.ai/" . urlencode($q) . "?model=" . urlencode($aktif["model"]);
    $r = http_get($url);

    if ($r["error"]) {
        $hata = "cURL: " . $r["error"];
    } elseif ($r["code"] !== 200) {
        $hata = "HTTP " . $r["code"] . " - " . substr($r["body"], 0, 200);
    } else {
        $cevap = trim($r["body"]);
        // Pollinations bazen JSON döner
        $j = json_decode($cevap, true);
        if (is_array($j)) {
            if (isset($j["choices"][0]["message"]["content"]))
                $cevap = $j["choices"][0]["message"]["content"];
            elseif (isset($j["response"]))
                $cevap = $j["response"];
            elseif (isset($j["content"]))
                $cevap = $j["content"];
            elseif (isset($j["text"]))
                $cevap = $j["text"];
        }
        if ($cevap === "") $hata = "Bos yanit";
    }
}

// ─── DUCKDUCKGO AI ───
elseif ($aktif["tip"] === "duck") {
    // 1) status al (x-vqd-4 token için)
    $status = http_get("https://duckduckgo.com/duckchat/v1/status", ["x-vqd-accept: 1"]);
    $vqd = "";
    if (preg_match('/x-vqd-4:\s*([^\r\n]+)/i', $status["body"] ?? "", $m)) {
        $vqd = trim($m[1]);
    }
    // Header'dan al (curl header function olmadan)
    if ($vqd === "") {
        // body içinde ara
        if (preg_match('/"x-vqd-4"\s*:\s*"([^"]+)"/', $status["body"] ?? "", $m)) {
            $vqd = $m[1];
        }
    }

    if ($vqd === "") {
        $hata = "DuckDuckGo vqd token alinamadi";
    } else {
        $payload = [
            "model"    => $aktif["model"],
            "messages" => [["role" => "user", "content" => $q]],
        ];
        $r = http_post_json("https://duckduckgo.com/duckchat/v1/chat", $payload, [
            "x-vqd-4: " . $vqd,
            "Referer: https://duckduckgo.com/",
            "Origin: https://duckduckgo.com",
        ]);

        if ($r["error"]) {
            $hata = "cURL: " . $r["error"];
        } elseif ($r["code"] !== 200) {
            $hata = "HTTP " . $r["code"] . " - " . substr($r["body"], 0, 200);
        } else {
            // SSE formatında gelir
            $body = $r["body"];
            foreach (explode("\n", $body) as $line) {
                $line = trim($line);
                if ($line === "" || strpos($line, "data: ") !== 0) continue;
                $js = substr($line, 6);
                if ($js === "[DONE]") continue;
                $j = json_decode($js, true);
                if (!$j) continue;
                if (isset($j["message"])) $cevap .= $j["message"];
            }
            if ($cevap === "") $hata = "Bos yanit";
        }
    }
}

$toplam = microtime(true) - $t0;

// ═══════════ YANIT ═══════════
if ($hata !== null) {
    http_response_code(200);
    echo json_encode([
        "success"  => false,
        "error"    => $hata,
        "model"    => $model,
        "isim"     => $aktif["isim"],
        "sure"     => round($toplam, 2),
        "telegram" => "@cmrbaskani",
        "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

echo json_encode([
    "success"  => true,
    "model"    => $model,
    "isim"     => $aktif["isim"],
    "q"        => $q,
    "response" => $cevap,
    "sure"     => round($toplam, 2),
    "karakter" => mb_strlen($cevap),
    "telegram" => "@cmrbaskani",
    "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);