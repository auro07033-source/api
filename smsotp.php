<?php
/**
 * 📡 SMSToMe OTP API — @cmrbaskani
 * https://ucretsizservicetr.onrender.com/smsotp.php
 *
 * Endpointler:
 *   GET ?action=countries
 *   GET ?action=numbers&country=belgium
 *   GET ?action=sms&country=belgium&phone=32468798844
 *   GET ?action=otp&country=belgium&phone=32468798844&limit=3
 *   GET ?action=latest&country=belgium&phone=32468798844
 *   GET ?action=health
 *   GET ?action=debug&country=belgium
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── AYARLAR ───
define('BASE_URL', 'https://smstome.com');
define('UA', 'Mozilla/5.0 (Android 15; Mobile; rv:155.0) Gecko/155.0 Firefox/155.0');
define('CACHE_DIR', sys_get_temp_dir() . '/smsotp_cache');
define('CACHE_TTL', 5);

$COUNTRIES = [
    'united-kingdom' => ['name' => 'Birleşik Krallık (+44)', 'prefix' => '44'],
    'netherlands'    => ['name' => 'Hollanda (+31)',         'prefix' => '31'],
    'poland'         => ['name' => 'Polonya (+48)',          'prefix' => '48'],
    'finland'        => ['name' => 'Finlandiya (+358)',      'prefix' => '358'],
    'belgium'        => ['name' => 'Belçika (+32)',          'prefix' => '32'],
    'slovenia'       => ['name' => 'Slovenya (+386)',        'prefix' => '386'],
];

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

function fetch_url($url, $bypass_cache = false) {
    $cf = cache_path($url);
    if (!$bypass_cache && file_exists($cf) && (time() - filemtime($cf)) < CACHE_TTL) {
        return file_get_contents($cf);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
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
    return trim(preg_replace('/\s+/', ' ', $s ?? ''));
}

function extract_otp($text) {
    if (preg_match('/\b(\d{4,8})\b/', $text, $m)) return $m[1];
    return null;
}

// ─── NUMARA PARSER (regex + DOM fallback, login arkasını da dener) ───
function parse_numbers($html, $country, $prefix = '') {
    if (!$html) return [];
    $nums = [];
    $seen = [];

    // 1) Ham HTML'deki tüm href'leri tara
    if (preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches)) {
        foreach ($matches[1] as $href) {
            $num = null;

            // /phone/<num>/sms/<id>
            if (preg_match('#/phone/(\+?\d{7,15})/sms/\d+#', $href, $m)) {
                $num = ltrim($m[1], '+');
            }
            // /<num>/sms/<id> veya /number/<num>
            elseif (preg_match('#/(?:sms|number)/(\+?\d{7,15})#', $href, $m)) {
                $num = ltrim($m[1], '+');
            }
            // direkt numara
            elseif (preg_match('#/(\+?\d{10,15})(?:/|$)#', $href, $m)) {
                $num = ltrim($m[1], '+');
            }

            if (!$num || strlen($num) < 8) continue;
            if ($prefix && strpos($num, $prefix) !== 0) continue;
            if (isset($seen[$num])) continue;
            $seen[$num] = true;

            $full = (strpos($href, 'http') === 0) ? $href : BASE_URL . $href;

            $nums[] = [
                "phone"   => $num,
                "display" => '+' . $num,
                "url"     => $full,
                "country" => $country,
            ];
        }
    }

    // 2) Hiç bulunamadıysa DOM dene
    if (empty($nums) && class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        foreach ($xpath->query('//a[@href]') as $a) {
            $href = $a->getAttribute('href');
            if (!preg_match('#(\+?\d{10,15})#', $href, $m)) continue;
            $num = ltrim($m[1], '+');
            if (strlen($num) < 8 || isset($seen[$num])) continue;
            if ($prefix && strpos($num, $prefix) !== 0) continue;
            $seen[$num] = true;

            $txt = clean_text($a->textContent);
            $full = (strpos($href, 'http') === 0) ? $href : BASE_URL . $href;
            $nums[] = [
                "phone"   => $num,
                "display" => ($txt && $txt[0] === '+') ? $txt : '+' . $num,
                "url"     => $full,
                "country" => $country,
            ];
        }
    }

    // 3) Hâlâ boşsa: sayfa metnindeki tüm numaraları al (prefix filtreli)
    if (empty($nums) && $prefix) {
        if (preg_match_all('#\+?' . $prefix . '\d{7,12}#', $html, $matches)) {
            foreach ($matches[0] as $raw) {
                $num = ltrim($raw, '+');
                if (strlen($num) < 8 || isset($seen[$num])) continue;
                $seen[$num] = true;
                $nums[] = [
                    "phone"   => $num,
                    "display" => '+' . $num,
                    "url"     => BASE_URL . "/$country/phone/$num/sms/0",
                    "country" => $country,
                ];
            }
        }
    }

    return $nums;
}

// ─── SMS PARSER ───
function parse_sms($html) {
    if (!$html) return [];
    $msj = [];

    if (class_exists('DOMDocument')) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Tablo satırları
        foreach ($xpath->query('//tr') as $tr) {
            $cells = $xpath->query('.//td', $tr);
            if ($cells->length < 2) continue;
            $parts = [];
            foreach ($cells as $c) $parts[] = clean_text($c->textContent);
            $from = $parts[0] ?? '';
            $text = $parts[1] ?? '';
            $date = $parts[2] ?? '';
            if (!$from || strtolower($from) === 'from' || strtolower($from) === 'sender') continue;
            if (!$text) continue;
            $msj[] = [
                "sender" => $from,
                "text"   => $text,
                "date"   => $date,
                "otp"    => extract_otp($text),
            ];
        }

        // div-based
        if (empty($msj)) {
            foreach ($xpath->query('//div[contains(@class,"message") or contains(@class,"sms") or contains(@class,"msg")]') as $d) {
                $t = clean_text($d->textContent);
                if (strlen($t) < 10) continue;
                $msj[] = [
                    "sender" => "unknown",
                    "text"   => $t,
                    "date"   => "",
                    "otp"    => extract_otp($t),
                ];
            }
        }
    }

    // regex fallback: <tr>...</tr> satırlarını yakala
    if (empty($msj)) {
        if (preg_match_all('#<tr[^>]*>(.*?)</tr>#is', $html, $rows)) {
            foreach ($rows[1] as $row) {
                if (preg_match_all('#<td[^>]*>(.*?)</td>#is', $row, $cells)) {
                    $c = array_map(function($x){ return clean_text(strip_tags($x)); }, $cells[1]);
                    if (count($c) < 2) continue;
                    if (!$c[0] || strtolower($c[0]) === 'from') continue;
                    $msj[] = [
                        "sender" => $c[0],
                        "text"   => $c[1] ?? '',
                        "date"   => $c[2] ?? '',
                        "otp"    => extract_otp($c[1] ?? ''),
                    ];
                }
            }
        }
    }

    return $msj;
}

// ─── ROUTER ───
$action = $_GET['action'] ?? 'countries';

switch ($action) {

    // 1) ÜLKELER
    case 'countries':
        $out = [];
        foreach ($GLOBALS['COUNTRIES'] as $slug => $c) {
            $out[] = [
                "slug" => $slug,
                "name" => $c['name'],
                "url"  => BASE_URL . "/country/" . $slug,
            ];
        }
        json_out(["success" => true, "count" => count($out), "data" => $out]);
        break;

    // 2) NUMARALAR
    case 'numbers':
        $country = $_GET['country'] ?? '';
        if (!isset($GLOBALS['COUNTRIES'][$country])) {
            err("geçersiz country. Geçerli: " . implode(', ', array_keys($GLOBALS['COUNTRIES'])));
        }
        $prefix = $GLOBALS['COUNTRIES'][$country]['prefix'];

        $html = fetch_url(BASE_URL . "/country/" . $country);
        $nums = $html ? parse_numbers($html, $country, $prefix) : [];

        // Boşsa ana sayfadan dene
        if (empty($nums)) {
            $home = fetch_url(BASE_URL . "/");
            if ($home) {
                $nums = parse_numbers($home, $country, $prefix);
            }
        }

        json_out([
            "success" => true,
            "country" => $country,
            "count"   => count($nums),
            "data"    => $nums,
        ]);
        break;

    // 3) TÜM SMS
    case 'sms':
        $country = $_GET['country'] ?? '';
        $phone   = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        $url  = BASE_URL . "/$country/phone/$phone/sms/0";
        $html = fetch_url($url);
        if (!$html) {
            $html = fetch_url(BASE_URL . "/$country/phone/$phone/sms/1");
        }
        if (!$html) err("numara sayfası alınamadı", 502);

        $msj = parse_sms($html);
        json_out([
            "success" => true,
            "country" => $country,
            "phone"   => $phone,
            "count"   => count($msj),
            "data"    => $msj,
        ]);
        break;

    // 4) OTP LİSTESİ
    case 'otp':
        $country = $_GET['country'] ?? '';
        $phone   = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        $limit   = min(20, max(1, (int)($_GET['limit'] ?? 3)));
        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        $url  = BASE_URL . "/$country/phone/$phone/sms/0";
        $html = fetch_url($url);
        if (!$html) $html = fetch_url(BASE_URL . "/$country/phone/$phone/sms/1");
        if (!$html) err("numara sayfası alınamadı", 502);

        $msj = parse_sms($html);
        $otpler = [];
        foreach (array_slice($msj, 0, $limit) as $m) {
            $otpler[] = [
                "sender" => $m['sender'],
                "text"   => $m['text'],
                "date"   => $m['date'],
                "otp"    => $m['otp'],
            ];
        }
        json_out([
            "success" => true,
            "country" => $country,
            "phone"   => $phone,
            "count"   => count($otpler),
            "data"    => $otpler,
        ]);
        break;

    // 5) SON OTP (tek mesaj)
    case 'latest':
        $country = $_GET['country'] ?? '';
        $phone   = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        $url  = BASE_URL . "/$country/phone/$phone/sms/0";
        $html = fetch_url($url);
        if (!$html) $html = fetch_url(BASE_URL . "/$country/phone/$phone/sms/1");
        if (!$html) err("numara sayfası alınamadı", 502);

        $msj = parse_sms($html);
        if (empty($msj)) {
            json_out([
                "success" => true,
                "country" => $country,
                "phone"   => $phone,
                "count"   => 0,
                "data"    => [],
            ]);
        }

        // En yeni mesaj genelde ilk eleman
        $son = $msj[0];
        json_out([
            "success" => true,
            "country" => $country,
            "phone"   => $phone,
            "count"   => 1,
            "data"    => [[
                "sender" => $son['sender'],
                "text"   => $son['text'],
                "date"   => $son['date'],
                "otp"    => $son['otp'],
            ]],
        ]);
        break;

    // 6) HEALTH
    case 'health':
        json_out([
            "success" => true,
            "status"  => "ok",
            "time"    => date("c"),
            "base"    => BASE_URL,
            "php"     => PHP_VERSION,
            "curl"    => function_exists('curl_init'),
            "dom"     => class_exists('DOMDocument'),
        ]);
        break;

    // 7) DEBUG — ham HTML
    case 'debug':
        header("Content-Type: text/plain; charset=utf-8");
        $country = $_GET['country'] ?? 'united-kingdom';
        $url = BASE_URL . "/country/" . $country;

        echo "URL       : $url\n";
        echo "PHP       : " . PHP_VERSION . "\n";
        echo "cURL      : " . (function_exists('curl_init') ? "OK" : "MISSING") . "\n";
        echo "DOM       : " . (class_exists('DOMDocument') ? "OK" : "MISSING") . "\n";
        echo str_repeat("-", 60) . "\n\n";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
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
        $info = curl_getinfo($ch);
        $cerr = curl_error($ch);
        curl_close($ch);

        echo "HTTP_CODE : " . $info['http_code'] . "\n";
        echo "SIZE      : " . strlen($body ?: '') . " bytes\n";
        echo "CURL_ERR  : " . ($cerr ?: "-") . "\n";
        echo "FINAL_URL : " . ($info['url'] ?? '-') . "\n\n";
        echo str_repeat("-", 60) . "\n\n";
        echo "HTML (ilk 4000):\n\n";
        echo substr($body ?: "(BOŞ)", 0, 4000);
        exit;

    default:
        err("bilinmeyen action: $action", 404);
}