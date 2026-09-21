<?php
// api.php - AI Chat API (GET ile model + mesaj)
// Telegram: @cmrbaskani
// Kullanım:
//   /api.php?model=yqcloud&q=merhaba
//   /api.php?model=gemini-3.5-flash&q=merhaba
//   /api.php?model=gemini-2.5-flash&q=merhaba
//   /api.php?list=1  → mevcut modelleri listeler

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ═══════════ MODELLER ═══════════
$MODELLER = [
    "yqcloud"              => ["isim" => "Yqcloud · Step",         "tip" => "yqcloud", "stream" => true],
    "gemini-3.5-flash"     => ["isim" => "Gemini 3.5 Flash",       "tip" => "gemini",  "stream" => false],
    "gemini-2.5-flash"     => ["isim" => "Gemini 2.5 Flash",       "tip" => "gemini",  "stream" => false],
    "gemini-3.5-flash-lite"=> ["isim" => "Gemini 3.5 Flash Lite",  "tip" => "gemini",  "stream" => false],
    "gemini-3.1-flash-lite"=> ["isim" => "Gemini 3.1 Flash Lite",  "tip" => "gemini",  "stream" => false],
];

// ═══════════ MODEL LİSTESİ ═══════════
if (isset($_GET['list'])) {
    $out = [];
    foreach ($MODELLER as $k => $v) {
        $out[] = ["id" => $k, "isim" => $v["isim"]];
    }
    echo json_encode([
        "success" => true,
        "modeller" => $out,
        "telegram" => "@cmrbaskani",
        "chanel"   => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ═══════════ PARAMETRELER ═══════════
$model = $_GET['model'] ?? $_POST['model'] ?? '';
$q     = $_GET['q'] ?? $_POST['q'] ?? '';

if ($model === '' || $q === '') {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "model ve q parametreleri gerekli. Örnek: ?model=yqcloud&q=merhaba",
        "modeller"=> array_keys($MODELLER),
        "telegram"=> "@cmrbaskani",
        "chanel"  => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if (!isset($MODELLER[$model])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Gecersiz model: $model",
        "modeller"=> array_keys($MODELLER),
        "telegram"=> "@cmrbaskani",
        "chanel"  => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$aktif = $MODELLER[$model];

// ═══════════ YARDIMCI ═══════════
function uuid_v4() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ═══════════ API ÇAĞRISI ═══════════
$t0 = microtime(true);
$cevap = "";
$hata  = null;

if ($aktif["tip"] === "yqcloud") {
    // Yqcloud streaming
    $chat_id = uuid_v4();
    $cookie = tempnam(sys_get_temp_dir(), 'yq_');

    $payload = json_encode([
        "model"    => "step",
        "messages" => [["role" => "user", "content" => $q]],
        "stream"   => true,
        "id"       => $chat_id,
    ]);

    $ch = curl_init("https://g4f.dev/api/yqcloud/chat");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36",
            "Accept: text/event-stream",
            "Origin: https://g4f.dev",
            "Referer: https://g4f.dev/",
        ],
    ]);

    $buffer = "";
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$cevap, &$buffer) {
        $buffer .= $data;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $pos));
            $buffer = substr($buffer, $pos + 1);
            if ($line === "" || strpos($line, "data: ") !== 0) continue;
            $json_str = substr($line, 6);
            if ($json_str === "[DONE]") continue;
            $j = json_decode($json_str, true);
            if (!$j) continue;
            if (isset($j["choices"][0]["delta"]["content"])) {
                $cevap .= $j["choices"][0]["delta"]["content"];
            } elseif (isset($j["delta"]) && is_string($j["delta"])) {
                $cevap .= $j["delta"];
            } elseif (isset($j["content"])) {
                $cevap .= $j["content"];
            }
        }
        return strlen($data);
    });

    curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    @unlink($cookie);

    if ($err) $hata = "cURL: $err";
    elseif ($http !== 200) $hata = "HTTP $http";
    elseif ($cevap === "") $hata = "Bos yanit";

} else {
    // Gemini (stream=False)
    $chat_id = uuid_v4();
    $cookie = tempnam(sys_get_temp_dir(), 'gm_');

    $payload = json_encode([
        "model"    => $model,
        "messages" => [["role" => "user", "content" => $q]],
        "stream"   => false,
        "id"       => $chat_id,
    ]);

    $ch = curl_init("https://g4f.dev/api/gemini/chat");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_COOKIEJAR      => $cookie,
        CURLOPT_COOKIEFILE     => $cookie,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36",
            "Accept: application/json",
            "Origin: https://g4f.dev",
            "Referer: https://g4f.dev/",
        ],
    ]);

    $raw = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    @unlink($cookie);

    if ($err) $hata = "cURL: $err";
    elseif ($http !== 200) $hata = "HTTP $http - " . substr($raw, 0, 200);
    else {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            if (isset($j["choices"][0]["message"]["content"]))      $cevap = $j["choices"][0]["message"]["content"];
            elseif (isset($j["response"]))                          $cevap = $j["response"];
            elseif (isset($j["content"]))                           $cevap = $j["content"];
            elseif (isset($j["message"]) && is_string($j["message"]))$cevap = $j["message"];
            elseif (isset($j["data"]) && is_string($j["data"]))     $cevap = $j["data"];
        }
        if ($cevap === "" && is_string($raw)) $cevap = $raw;
        if ($cevap === "") $hata = "Bos yanit";
    }
}

$toplam = microtime(true) - $t0;

// ═══════════ YANIT ═══════════
if ($hata !== null) {
    http_response_code(200);
    echo json_encode([
        "success" => false,
        "error"   => $hata,
        "model"   => $model,
        "isim"    => $aktif["isim"],
        "sure"    => round($toplam, 2),
        "telegram"=> "@cmrbaskani",
        "chanel"  => "https://t.me/+GgzdPJJUPns3OWJk"
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