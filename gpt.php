<?php
// gpt.php - notrack.ai sohbet proxy (otomatik oturum yenileme + çerez havuzu)
// Kullanım:
//   /gpt.php?mesaj=Merhaba
//   /gpt.php?mesaj=Devam&chat_id=abc123
//   /gpt.php?mesaj=Merhaba&stream=1
// Gereksinim: PHP 7.4+, curl

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$mesaj   = $_POST['mesaj']   ?? $_GET['mesaj']   ?? null;
$chat_id = $_POST['chat_id'] ?? $_GET['chat_id'] ?? null;
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

// Hız sınırı: aynı IP'den 3 sn'de bir
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = trim(explode(',', $ip)[0]);
$lock = sys_get_temp_dir() . "/notrack_" . md5($ip) . ".lock";
if (file_exists($lock)) {
    $fark = time() - (int)@file_get_contents($lock);
    if ($fark < 3) sleep(3 - $fark);
}
@file_put_contents($lock, time());

$API  = "https://notrack.ai/api/dispatch";
$PAGE = "https://notrack.ai/tr/chat";

$HEADERS_BASE = [
    "User-Agent: Mozilla/5.0 (Android 15; Mobile; rv:159.0) Gecko/159.0 Firefox/159.0",
    "Accept: */*",
    "Accept-Language: tr-TR",
    "Sec-Fetch-Dest: empty",
    "Sec-Fetch-Mode: cors",
    "Sec-Fetch-Site: same-origin",
];

// Çerez havuzu: tarayıcıdan aldığın değerleri buraya ekle.
// Yenisi eskisini geçersiz kılmaz, sırayla denenir; ilk çalışan kullanılır.
$COOKIE_POOL = [
    // 1. set (mevcut)
    [
        "si_usr_id" => "1795395693-v-j4QULqTYbSSCcRQR1dg7",
        "si_ses_id" => "1795395693-v-j4QULqTYbSSCcRQR1dg7",
        "uid"       => "10133feb-1a97-4359-b418-b25e25a1f30d",
    ],
    // 2. set örneği (tarayıcıdan alıp doldur)
    // [
    //     "si_usr_id" => "...",
    //     "si_ses_id" => "...",
    //     "uid"       => "...",
    // ],
];

// ---- Oturum açma: GET /chat → Set-Cookie topla ----
function oturum_al($PAGE, $HEADERS_BASE) {
    $jar = tempnam(sys_get_temp_dir(), "nt_ck_");
    $ch = curl_init($PAGE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING       => "",
        CURLOPT_HTTPHEADER     => $HEADERS_BASE,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Cookie jar dosyasından çerezleri oku
    $cerezler = [];
    if ($jar && file_exists($jar)) {
        foreach (file($jar, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $satir) {
            if ($satir[0] === '#' || substr_count($satir, "\t") < 6) continue;
            $p = explode("\t", $satir);
            if (count($p) >= 7) {
                $ad = trim($p[5]);
                $deger = trim($p[6]);
                if ($ad !== '' && $deger !== '') $cerezler[$ad] = $deger;
            }
        }
        @unlink($jar);
    }
    return [$cerezler, $code, $body];
}

// ---- POST dispatch ----
function dispatch($API, $HEADERS_BASE, $cerezler, $payload) {
    $cookieStr = [];
    foreach ($cerezler as $k => $v) $cookieStr[] = "$k=$v";
    $cookieStr = implode("; ", $cookieStr);

    $ch = curl_init($API);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => array_merge($HEADERS_BASE, [
            "Content-Type: application/json",
            "Referer: https://notrack.ai/tr/chat",
            "Origin: https://notrack.ai",
            "Cookie: $cookieStr",
        ]),
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

// ---- HTML tespiti (oturum düşmüşse HTML döner) ----
function html_mi($body) {
    if (!is_string($body)) return true;
    $b = ltrim(substr($body, 0, 30));
    return stripos($b, '<!DOCTYPE') === 0 || stripos($b, '<html') === 0 || stripos($b, '<?xml') === 0;
}

// ---- Çerez sırası: havuzdakiler + sonradan taze alınanlar ----
$cerezAdaylari = $COOKIE_POOL;

// Ekstra: bu istek için taze GET oturumu da dene
[$taze, $gcode, $gbody] = oturum_al($PAGE, $HEADERS_BASE);
if (!empty($taze)) {
    $cerezAdaylari[] = $taze;
}

$payloadBase = [
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

// ---- Deneme döngüsü: her çerez setiyle bir kez, 429'da bekle ----
$sonBody = null; $sonCode = 0; $sonErr = ""; $kullanilanCerez = null;
$maxDeneme = max(count($cerezAdaylari), 1) + 2;

for ($i = 0; $i < $maxDeneme; $i++) {
    $cerez = $cerezAdaylari[$i % max(count($cerezAdaylari), 1)] ?? null;
    if (!$cerez) {
        // Sıra bitti, tekrar taze al
        [$cerez, $_, $__] = oturum_al($PAGE, $HEADERS_BASE);
        if (empty($cerez)) break;
    }

    [$body, $code, $err] = dispatch($API, $HEADERS_BASE, $cerez, $payloadBase);
    $sonBody = $body; $sonCode = $code; $sonErr = $err;

    // HTML döndüyse bu çerez ölü, sıradakine geç
    if ($code === 200 && !html_mi($body)) {
        $kullanilanCerez = $cerez;
        break;
    }
    if ($code === 429) {
        sleep(5 * (($i % 3) + 1));
        continue;
    }
    // 401/403/419 → sıradaki çerez
    if (in_array($code, [401, 403, 419], true)) continue;
    // HTML 200 → sıradaki çerez
    if ($code === 200 && html_mi($body)) continue;
    // Diğer hatalar → kır
    break;
}

// ---- STREAM ----
if ($stream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ob_end_flush();
    @ob_implicit_flush(true);

    if ($sonBody === null || $sonCode !== 200 || html_mi($sonBody)) {
        echo "event: error\n";
        echo "data: " . json_encode([
            "success"     => false,
            "message"     => "notrack.ai oturumu açılamadı",
            "http"        => $sonCode,
            "gelistirici" => "@ittizar",
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
        exit;
    }

    // Son başarılı yanıt zaten elimizde; satır satır SSE'ye aktar
    $yeni = null;
    foreach (explode("\n", $sonBody) as $line) {
        $line = rtrim($line, "\r");
        if (strpos($line, "data: ") !== 0) continue;
        $d = json_decode(substr($line, 6), true);
        if (!is_array($d)) continue;
        if (($d['type'] ?? '') === 'chat_meta' && !$yeni) {
            $yeni = $d['chat_id'] ?? null;
            echo "event: chat_id\n";
            echo "data: " . json_encode(["chat_id" => $yeni], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        }
        if (($d['type'] ?? '') === 'message' && ($d['speaker'] ?? '') === 'C') {
            echo "event: message\n";
            echo "data: " . json_encode(["content" => $d['content'] ?? ''], JSON_UNESCAPED_UNICODE) . "\n\n";
            @flush();
        }
    }

    echo "event: done\n";
    echo "data: " . json_encode([
        "success"     => true,
        "chat_id"     => $yeni,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE) . "\n\n";
    exit;
}

// ---- NORMAL ----
header('Content-Type: application/json; charset=utf-8');

if ($sonBody === false || $sonCode !== 200 || html_mi($sonBody)) {
    echo json_encode([
        "success"     => false,
        "message"     => "notrack.ai oturumu açılamadı (HTML veya hata döndü)",
        "http"        => $sonCode,
        "error"       => $sonErr,
        "ham_bas"     => is_string($sonBody) ? substr($sonBody, 0, 300) : null,
        "gelistirici" => "@ittizar",
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$yeniChatId = null;
$icerik = "";
foreach (explode("\n", $sonBody) as $line) {
    $line = rtrim($line, "\r");
    if (strpos($line, "data: ") !== 0) continue;
    $d = json_decode(substr($line, 6), true);
    if (!is_array($d)) continue;
    if (($d['type'] ?? '') === 'chat_meta' && !$yeniChatId) {
        $yeniChatId = $d['chat_id'] ?? null;
    }
    if (($d['type'] ?? '') === 'message' && ($d['speaker'] ?? '') === 'C') {
        $icerik .= ($d['content'] ?? '');
    }
}

echo json_encode([
    "success"     => true,
    "gelistirici" => "@ittizar",
    "chat_id"     => $yeniChatId,
    "cevap"       => trim($icerik),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);