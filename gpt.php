<?php
/**
 * 🤖 GPT/Gemini Chat API — @cmrbaskani
 * Dosya: gpt.php
 * Sunucu: https://ucretsizservicetr.onrender.com/gpt.php
 *
 * Endpointler:
 *   GET  gpt.php?q=merhaba
 *   POST gpt.php  {"message": "merhaba"}
 *   GET  gpt.php?action=health
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── AYARLAR ───
// Kendi Gemini API anahtarını buraya koy (Google AI Studio'dan ücretsiz)
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   'gemini-2.0-flash');
define('GEMINI_URL',     'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');

// Fallback: supabase endpoint (senin verdiğin, ama sahibi başkası — kullanma)
define('USE_SUPABASE_FALLBACK', false);
define('SUPABASE_URL', 'https://qcpujeurnkbvwlvmylyx.supabase.co/functions/v1/chat');

define('CACHE_DIR', sys_get_temp_dir() . '/gpt_cache');
define('CACHE_TTL', 60);


// ─── YARDIMCI ───
function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}
function err($msg, $code = 400) { json_out(["success" => false, "error" => $msg], $code); }

function cache_path($key) {
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0777, true);
    return CACHE_DIR . '/' . md5($key) . '.cache';
}

function ask_gemini($message) {
    if (GEMINI_API_KEY === '') return null;

    $url = GEMINI_URL . '?key=' . GEMINI_API_KEY;
    $body = json_encode([
        "contents" => [
            ["role" => "user", "parts" => [["text" => $message]]]
        ],
        "generationConfig" => [
            "temperature" => 0.9,
            "maxOutputTokens" => 2048,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$resp) return null;

    $json = json_decode($resp, true);
    if (!is_array($json)) return null;

    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if ($text === null) return null;

    return [
        'text'   => $text,
        'model'  => GEMINI_MODEL,
        'raw'    => $json,
    ];
}


// ─── ROUTE ───
$action = $_GET['action'] ?? 'chat';

switch ($action) {

    // GET gpt.php?q=merhaba
    // POST gpt.php {"message":"merhaba"}
    case 'chat':
        $message = '';

        // GET parametresi
        if (!empty($_GET['q'])) {
            $message = trim($_GET['q']);
        }

        // POST body (JSON veya form)
        if ($message === '') {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $message = trim($decoded['message'] ?? $decoded['q'] ?? $decoded['prompt'] ?? '');
                } else {
                    $message = trim($raw);
                }
            }
        }
        if ($message === '' && !empty($_POST['message'])) {
            $message = trim($_POST['message']);
        }

        if ($message === '') {
            err("mesaj boş. 'q' parametresi veya POST body gerekli.");
        }

        // Cache kontrol
        $cf = cache_path('gemini_' . $message);
        if (file_exists($cf) && (time() - filemtime($cf)) < CACHE_TTL) {
            $cached = json_decode(file_get_contents($cf), true);
            if ($cached) {
                json_out([
                    'success' => true,
                    'cached'  => true,
                    'model'   => $cached['model'] ?? GEMINI_MODEL,
                    'answer'  => $cached['text'] ?? '',
                ]);
            }
        }

        // Gemini çağrısı
        $cevap = ask_gemini($message);

        if ($cevap === null) {
            err("Gemini API yanıt vermedi. GEMINI_API_KEY doğru mu?", 502);
        }

        @file_put_contents($cf, json_encode($cevap, JSON_UNESCAPED_UNICODE));

        json_out([
            'success' => true,
            'cached'  => false,
            'model'   => $cevap['model'],
            'answer'  => $cevap['text'],
        ]);
        break;

    // GET gpt.php?action=health
    case 'health':
        json_out([
            'success'    => true,
            'status'     => 'ok',
            'time'       => date('c'),
            'model'      => GEMINI_MODEL,
            'has_key'    => GEMINI_API_KEY !== '',
        ]);
        break;

    default:
        err("bilinmeyen action: $action", 404);
}