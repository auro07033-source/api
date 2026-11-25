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

// --- Herhangi bir JSON'dan metin çıkar (derin tarama) ---
function json_metin_bul($j) {
    if (is_string($j)) return $j;
    if (!is_array($j)) return null;

    // Öncelik sırası: yaygın alanlar
    $oncelikli = ["response","message","content","text","answer","output","delta","completion","reply","result"];
    foreach ($oncelikli as $a) {
        if (isset($j[$a])) {
            $v = json_metin_bul($j[$a]);
            if (is_string($v) && trim($v) !== '') return $v;
        }
    }
    // OpenAI tarzı: choices[0].delta.content / choices[0].message.content
    if (isset($j['choices'][0]) && is_array($j['choices'][0])) {
        $c = $j['choices'][0];
        foreach (["delta","message"] as $k) {
            if (isset($c[$k]['content'])) {
                $v = $c[$k]['content'];
                if (is_string($v) && trim($v) !== '') return $v;
            }
        }
    }
    // Dizinin herhangi bir string elemanı
    foreach ($j as $v) {
        $r = json_metin_bul($v);
        if (is_string($r) && trim($r) !== '') return $r;
    }
    return null;
}

// ---- STREAM ----
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    $buffer = "";
    $yeniSession = $session;

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
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$yeniSession) {
        $buffer .= $chunk;
        while (($pos = strpos($buffer, "\n")) !== false) {
            $line = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + 1);
            $line = rtrim($line, "\r");

            if (strpos($line, "data:") === 0) {
                $gövde = ltrim(substr($line, 5));
                if ($gövde === '' || $gövde === '[DONE]') continue;
                $j = json_decode($gövde, true);
                if (is_array($j)) {
                    $yeniSession = (string)($j['sessionId'] ?? $j['session_id'] ?? $yeniSession);
                    $m = json_metin_bul($j);
                    if ($m !== null) {
                        echo "event: message\n";
                        echo "data: " . json_encode(["content" => $m], JSON_UNESCAPED_UNICODE) . "\n\n";
                        @flush();
                    }
                } else {
                    echo "event: message\n";
                    echo "data: " . json_encode(["content" => $gövde], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                }
            } else if ($line !== '' && strpos($line, "event:") !== 0) {
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
        "session"     => $yeniSession,
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

$hamYanit = $body;

// 1) Tümü JSON ise derin tarama
$j = json_decode($body, true);
if (is_array($j)) {
    $cevap = json_metin_bul($j) ?? "";
    $yeniSession = (string)($j['sessionId'] ?? $j['session_id'] ?? $session);

    $cikti = [
        "success"     => true,
        "gelistirici" => "@ittizar",
        "session"     => $yeniSession,
        "cevap"       => trim($cevap),
    ];
    if (trim($cevap) === '') {
        $cikti["uyari"] = "cevap boş, JSON yapısı tanınmadı";
        $cikti["ham_yanit"] = substr($hamYanit, 0, 2000);
    }
    echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 2) SSE / düz metin parse (buffer'lı)
$parcalar = [];
$yeniSession = $session;
$sseBuffer = "";

foreach (preg_split('/\r\n|\r|\n/', $body) as $line) {
    $line = rtrim($line, "\r");
    if ($line === '') continue;

    if (strpos($line, "event:") === 0) continue;

    if (strpos($line, "data:") === 0) {
        $gövde = ltrim(substr($line, 5));
        if ($gövde === '' || $gövde === '[DONE]') continue;

        // Bölünmüş JSON desteği
        $sseBuffer .= $gövde;
        $jj = json_decode($sseBuffer, true);
        if (is_array($jj)) {
            $yeniSession = (string)($jj['sessionId'] ?? $jj['session_id'] ?? $yeniSession);
            $m = json_metin_bul($jj);
            if ($m !== null) $parcalar[] = $m;
            $sseBuffer = "";
        }
        continue;
    }

    // Düz metin satırı (JSON olabilir)
    $jj = json_decode($line, true);
    if (is_array($jj)) {
        $yeniSession = (string)($jj['sessionId'] ?? $jj['session_id'] ?? $yeniSession);
        $m = json_metin_bul($jj);
        if ($m !== null) $parcalar[] = $m;
    } else {
        $parcalar[] = $line;
    }
}

// Buffer'da kalan
if ($sseBuffer !== "") {
    $jj = json_decode($sseBuffer, true);
    if (is_array($jj)) {
        $m = json_metin_bul($jj);
        if ($m !== null) $parcalar[] = $m;
    }
}

$cevap = trim(implode("", $parcalar));

$cikti = [
    "success"     => true,
    "gelistirici" => "@ittizar",
    "session"     => $yeniSession,
    "cevap"       => $cevap,
];

if ($cevap === '') {
    $cikti["uyari"] = "cevap boş, ham_yanit içine bak";
    $cikti["ham_yanit"] = substr($hamYanit, 0, 2000);
}

echo json_encode($cikti, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);