<?php
/**
 * 📡 SMS-Online.co OTP API — @cmrbaskani
 * Dosya: smsotp.php
 *
 * Endpointler:
 *   GET smsotp.php?action=countries
 *   GET smsotp.php?action=numbers&country=sweden
 *   GET smsotp.php?action=sms&phone=46769436266
 *   GET smsotp.php?action=otp&phone=46769436266&limit=5
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('BASE_URL', 'https://sms-online.co');
define('LIST_URL', BASE_URL . '/receive-free-sms');
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
    // Önce "code", "otp", "verification" yakınında rakam ara
    if (preg_match('/(?:code|otp|verification|pin)[\s:]*([0-9]{4,8})/i', $text, $m)) return $m[1];
    // Direkt 4-8 haneli rakam
    if (preg_match('/\b([0-9]{4,8})\b/', $text, $m)) return $m[1];
    return null;
}

// ── ÜLKELER ──
function get_countries() {
    $html = fetch_url(LIST_URL, 60);
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $countries = [];
    $seen = [];

    // Link formatı: /receive-free-sms/<numara> — yanındaki metinde ülke var
    foreach ($xpath->query('//a[contains(@href,"/receive-free-sms/")]') as $a) {
        $href = $a->getAttribute('href');
        if (!preg_match('#/receive-free-sms/(\d{6,15})#', $href, $m)) continue;
        $phone = $m[1];
        if (isset($seen[$phone])) continue;
        $seen[$phone] = true;

        // Ülke bilgisi: çevredeki h2/h3 veya class içinde
        $country = 'unknown';
        $parent = $a->parentNode;
        for ($i = 0; $i < 4 && $parent; $i++) {
            if ($parent->nodeType === XML_ELEMENT_NODE) {
                foreach (['h1','h2','h3','h4','.country','.country-name'] as $sel) {
                    if ($sel[0] === '.') {
                        $nodes = $xpath->query('.//*[contains(@class,"' . substr($sel,1) . '")]', $parent);
                        if ($nodes->length > 0) { $country = clean_text($nodes->item(0)->textContent); break 2; }
                    }
                }
            }
            $parent = $parent->parentNode;
        }

        // Alternatif: link metninden çıkar (ör. "+46769436266 Sweden")
        if ($country === 'unknown') {
            $txt = clean_text($a->textContent);
            if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s*$/', $txt, $cm)) {
                $country = $cm[1];
            }
        }

        $countries[] = [
            'country' => $country,
            'phone'   => $phone,
            'display' => '+' . $phone,
            'url'     => BASE_URL . '/receive-free-sms/' . $phone,
        ];
    }

    return $countries;
}

// ── SMS MESAJLARI ──
function get_sms($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    $url = BASE_URL . '/receive-free-sms/' . $phone;
    $html = fetch_url($url, 5);
    if (!$html) return null;

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $messages = [];

    // Yapı: <h3>GONDEREN</h3> <time>5 minutes ago</time> <p>mesaj</p>
    // Ya da benzer div yapısı. H3 + P arayacağız.
    $h3s = $xpath->query('//h3 | //h4');
    foreach ($h3s as $h) {
        $sender = clean_text($h->textContent);
        if (!$sender || strlen($sender) > 60) continue;

        // Sonraki kardeşlerden time ve mesajı bul
        $sibling = $h->nextSibling;
        $date = '';
        $text = '';

        $depth = 0;
        while ($sibling && $depth < 6) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $name = strtolower($sibling->nodeName);
                $c = clean_text($sibling->textContent);

                if (in_array($name, ['time', 'span', 'div']) && preg_match('/\d+\s+(minute|hour|day|year|month|second)/i', $c)) {
                    if (!$date) $date = $c;
                } elseif (in_array($name, ['p', 'div']) && strlen($c) > 8 && !$text) {
                    $text = $c;
                }
            }
            $sibling = $sibling->nextSibling;
            $depth++;
        }

        // Eğer sibling yöntemi tutmazsa h3 üst divinden p'leri çek
        if (!$text) {
            $parent = $h->parentNode;
            if ($parent) {
                $ps = $xpath->query('.//p', $parent);
                if ($ps->length > 0) {
                    $text = clean_text($ps->item(0)->textContent);
                }
                $ts = $xpath->query('.//time | .//*[contains(@class,"time")]', $parent);
                if ($ts->length > 0 && !$date) {
                    $date = clean_text($ts->item(0)->textContent);
                }
            }
        }

        if ($text && strlen($text) > 3) {
            $messages[] = [
                'sender' => $sender,
                'text'   => $text,
                'date'   => $date,
                'otp'    => extract_otp($text),
            ];
        }
    }

    return $messages;
}

// ── ROUTE ──
$action = $_GET['action'] ?? 'countries';

switch ($action) {

    case 'countries':
        $list = get_countries();
        json_out(['success' => true, 'count' => count($list), 'data' => $list]);
        break;

    case 'numbers':
        $country = strtolower($_GET['country'] ?? '');
        $list = get_countries();
        $filtered = $country
            ? array_values(array_filter($list, fn($x) => strtolower($x['country']) === $country))
            : $list;
        json_out(['success' => true, 'country' => $country ?: 'all', 'count' => count($filtered), 'data' => $filtered]);
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

        // OTP olanları öne al
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