<?php
/**
 * 📡 Receive-SMS-Online.info OTP API — @cmrbaskani
 * Dosya: smsotp.php
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('BASE_URL', 'https://receive-sms-online.info');
define('UA', 'Mozilla/5.0 (Android 15; Mobile; rv:155.0) Gecko/155.0 Firefox/155.0');
define('CACHE_DIR', sys_get_temp_dir() . '/smsotp_cache');
define('CACHE_TTL', 5);

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

function fetch_url($url, $ttl = CACHE_TTL) {
    $cf = cache_path($url);
    if (file_exists($cf) && (time() - filemtime($cf)) < $ttl) {
        return file_get_contents($cf);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: ' . UA,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: ' . BASE_URL . '/',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) return null;
    @file_put_contents($cf, $body);
    return $body;
}

function clean_text($s) {
    return trim(preg_replace('/\s+/u', ' ', $s ?? ''));
}

function extract_otp($text) {
    if (preg_match('/(?:code|otp|verification|pin)[\s:]*([0-9]{4,8})/i', $text, $m)) return $m[1];
    if (preg_match('/\b([0-9]{4,8})\b/', $text, $m)) return $m[1];
    return null;
}

// ── NUMARALAR (ana sayfa) ──
function get_numbers() {
    $html = fetch_url(BASE_URL . '/', 60);
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $nums = [];
    $seen = [];

    // /<numara>-<ulke> formatındaki linkler
    foreach ($xpath->query('//a[@href]') as $a) {
        $href = $a->getAttribute('href');
        if (!preg_match('#/(\+?\d{7,15})-([A-Za-z]+)$#', $href, $m)) continue;
        $num = ltrim($m[1], '+');
        $country = $m[2];
        if (isset($seen[$num])) continue;
        $seen[$num] = true;

        $nums[] = [
            'phone'   => $num,
            'country' => $country,
            'display' => '+' . $num,
            'url'     => (strpos($href, 'http') === 0) ? $href : BASE_URL . $href,
        ];
    }

    return $nums;
}

// ── SMS ──
function get_sms($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    $html = fetch_url(BASE_URL . '/', 5);  // liste için
    if (!$html) return null;

    // Önce doğru URL'yi bul
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $page_url = null;
    foreach ($xpath->query('//a[@href]') as $a) {
        $href = $a->getAttribute('href');
        if (strpos($href, $phone) !== false && preg_match('#-\d+$#', $href) === 0) {
            $page_url = (strpos($href, 'http') === 0) ? $href : BASE_URL . $href;
            break;
        }
        if (strpos($href, $phone) !== false) {
            $page_url = (strpos($href, 'http') === 0) ? $href : BASE_URL . $href;
        }
    }

    if (!$page_url) {
        $page_url = BASE_URL . '/' . $phone . '-sms';
    }

    $sms_html = fetch_url($page_url, 5);
    if (!$sms_html) return null;

    $sdom = new DOMDocument();
    @$sdom->loadHTML('<?xml encoding="UTF-8">' . $sms_html);
    $sxpath = new DOMXPath($sdom);

    $messages = [];

    // Tablo satırları
    foreach ($sxpath->query('//tr') as $tr) {
        $cells = $sxpath->query('.//td', $tr);
        if ($cells->length < 2) continue;
        $parts = [];
        foreach ($cells as $c) $parts[] = clean_text($c->textContent);
        if (!$parts[0] || strtolower($parts[0]) === 'from') continue;
        $messages[] = [
            'sender' => $parts[0],
            'text'   => $parts[1] ?? '',
            'date'   => $parts[2] ?? '',
            'otp'    => extract_otp($parts[1] ?? ''),
        ];
    }

    // Div yapısı
    if (!$messages) {
        foreach ($sxpath->query('//div[contains(@class,"message") or contains(@class,"sms")]') as $div) {
            $txt = clean_text($div->textContent);
            if (strlen($txt) < 5) continue;
            $messages[] = [
                'sender' => '',
                'text'   => $txt,
                'date'   => '',
                'otp'    => extract_otp($txt),
            ];
        }
    }

    return $messages;
}
case 'debug':
    $html = fetch_url(BASE_URL . '/receive-free-sms', 0);
    json_out([
        'success' => true,
        'url' => BASE_URL . '/receive-free-sms',
        'length' => strlen($html ?: ''),
        'sample' => substr($html ?: '', 0, 5000),
        'links' => array_slice(
            array_map(
                fn($m) => $m[0],
                preg_match_all('#href="([^"]+)"#', $html ?: '', $matches) ? $matches : []
            ),
            0, 50
        )
    ]);
    break;
// ── ROUTE ──
$action = $_GET['action'] ?? 'numbers';

switch ($action) {
    case 'numbers':
    case 'countries':
        $list = get_numbers();
        $gruplar = [];
        foreach ($list as $n) {
            $gruplar[$n['country']][] = $n;
        }
        json_out([
            'success'   => true,
            'count'     => count($list),
            'countries' => array_keys($gruplar),
            'data'      => $list,
        ]);
        break;

    case 'sms':
        $phone = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        if (!$phone) err("phone gerekli");
        $msj = get_sms($phone);
        if ($msj === null) err("numara sayfası alınamadı", 502);
        json_out(['success' => true, 'phone' => $phone, 'count' => count($msj), 'data' => $msj]);
        break;

    case 'otp':
        $phone = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        $limit = min(20, max(1, (int)($_GET['limit'] ?? 5)));
        if (!$phone) err("phone gerekli");
        $msj = get_sms($phone);
        if ($msj === null) err("numara sayfası alınamadı", 502);
        usort($msj, fn($a, $b) => ($b['otp'] ? 1 : 0) - ($a['otp'] ? 1 : 0));
        $msj = array_slice($msj, 0, $limit);
        json_out(['success' => true, 'phone' => $phone, 'count' => count($msj), 'data' => $msj]);
        break;

    case 'health':
        json_out(['success' => true, 'status' => 'ok', 'time' => date('c'), 'base' => BASE_URL]);
        break;

    default:
        err("bilinmeyen action: $action", 404);
}