<?php
/**
 * 📡 SMSToMe OTP API — @cmrbaskani
 * Dosya: smsotp.php
 * Sunucu: https://ucretsizservicetr.onrender.com/smsotp.php
 *
 * Endpointler:
 *   GET smsotp.php?action=countries
 *   GET smsotp.php?action=numbers&country=belgium
 *   GET smsotp.php?action=sms&country=belgium&phone=32468798844
 *   GET smsotp.php?action=latest&country=belgium&phone=32468798844&since=<timestamp>
 *   GET smsotp.php?action=otp&country=belgium&phone=32468798844
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── AYARLAR ───
define('BASE_URL', 'https://smstome.com');
define('UA', 'Mozilla/5.0 (Android 15; Mobile; rv:155.0) Gecko/155.0 Firefox/155.0');
define('CACHE_DIR', sys_get_temp_dir() . '/smsotp_cache');
define('CACHE_TTL', 5);   // 5 saniye cache (rate limit için)

$COUNTRIES = [
    'united-kingdom' => 'Birleşik Krallık (+44)',
    'netherlands'    => 'Hollanda (+31)',
    'poland'         => 'Polonya (+48)',
    'finland'        => 'Finlandiya (+358)',
    'belgium'        => 'Belçika (+32)',
    'slovenia'       => 'Slovenya (+386)',
];

// ─── YARDIMCI ───
function json_out($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function err($msg, $code = 400) {
    json_out(["success" => false, "error" => $msg], $code);
}

function cache_path($key) {
    if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0777, true);
    return CACHE_DIR . '/' . md5($key) . '.cache';
}

function fetch_url($url) {
    // Cache kontrol
    $cf = cache_path($url);
    if (file_exists($cf) && (time() - filemtime($cf)) < CACHE_TTL) {
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
    return trim(preg_replace('/\s+/', ' ', $s ?? ''));
}

function extract_otp($text) {
    // 4-8 haneli kelime sınırında rakam
    if (preg_match('/\b(\d{4,8})\b/', $text, $m)) return $m[1];
    return null;
}


// ─── PARSER ───
function parse_numbers($html, $country) {
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $nums = [];
    $seen = [];

    foreach ($xpath->query('//a[@href]') as $a) {
        $href = $a->getAttribute('href');

        // Format: /<ulke>/phone/<numara>/sms/<id>
        if (!preg_match('#/phone/(\+?\d{7,15})/sms/\d+#', $href, $m)) {
            continue;
        }
        $num = ltrim($m[1], '+');
        if (isset($seen[$num])) continue;
        $seen[$num] = true;

        $txt = clean_text($a->textContent);
        if (!$txt || $txt[0] !== '+') $txt = '+' . $num;

        // Tam URL
        $full = (strpos($href, 'http') === 0) ? $href : BASE_URL . $href;

        $nums[] = [
            "phone"    => $num,
            "display"  => $txt,
            "url"      => $full,
            "country"  => $country,
        ];
    }

    return $nums;
}

function parse_sms($html) {
    if (!$html) return [];

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);

    $msj = [];

    // Tablo satırları
    foreach ($xpath->query('//tr') as $tr) {
        $cells = $xpath->query('.//td', $tr);
        if ($cells->length < 2) continue;

        $parts = [];
        foreach ($cells as $c) {
            $parts[] = clean_text($c->textContent);
        }

        $from = $parts[0] ?? '';
        $text = $parts[1] ?? '';
        $date = $parts[2] ?? '';

        if (!$from || strtolower($from) === 'from') continue;
        if (!$text) continue;

        $msj[] = [
            "sender" => $from,
            "text"   => $text,
            "date"   => $date,
            "otp"    => extract_otp($text),
        ];
    }

    return $msj;
}


// ─── ROUTE ───
$action = $_GET['action'] ?? 'countries';

switch ($action) {

    // 1) Ülkeler
    case 'countries':
        $out = [];
        foreach ($GLOBALS['COUNTRIES'] as $slug => $name) {
            $out[] = [
                "slug" => $slug,
                "name" => $name,
                "url"  => BASE_URL . "/country/" . $slug,
            ];
        }
        json_out(["success" => true, "count" => count($out), "data" => $out]);
        break;

    // 2) Numaralar
    // GET smsotp.php?action=numbers&country=belgium
    case 'numbers':
        $country = $_GET['country'] ?? '';
        if (!isset($GLOBALS['COUNTRIES'][$country])) {
            err("geçersiz country. Geçerli: " . implode(', ', array_keys($GLOBALS['COUNTRIES'])));
        }

        $url = BASE_URL . "/country/" . $country;
        $html = fetch_url($url);
        if (!$html) err("sayfa alınamadı: $url", 502);

        $nums = parse_numbers($html, $country);
        json_out([
            "success" => true,
            "country" => $country,
            "count"   => count($nums),
            "data"    => $nums,
        ]);
        break;

    // 3) Tüm SMS'ler
    // GET smsotp.php?action=sms&country=belgium&phone=32468798844
    case 'sms':
        $country = $_GET['country'] ?? '';
        $phone   = preg_replace('/\D/', '', $_GET['phone'] ?? '');

        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        // Sayfa URL'sini bul
        $url = BASE_URL . "/$country/phone/$phone/sms/0";
        $html = fetch_url($url);

        // 404 olursa, doğrudan numara URL'sini dene
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

    // 4) Son OTP (ilk 1-3 mesaj)
    // GET smsotp.php?action=otp&country=belgium&phone=32468798844
    case 'otp':
        $country = $_GET['country'] ?? '';
        $phone   = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        $limit   = min(10, max(1, (int)($_GET['limit'] ?? 3)));

        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        $url = BASE_URL . "/$country/phone/$phone/sms/0";
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
            "phone"   => $phone,
            "count"   => count($otpler),
            "data"    => $otpler,
        ]);
        break;

    // 5) Canlı bekleme (uzun poll)
    // GET smsotp.php?action=wait&country=belgium&phone=32468798844&last_count=5&timeout=25
    case 'wait':
        $country   = $_GET['country'] ?? '';
        $phone     = preg_replace('/\D/', '', $_GET['phone'] ?? '');
        $lastCount = (int)($_GET['last_count'] ?? -1);
        $timeout   = min(60, max(5, (int)($_GET['timeout'] ?? 25)));

        if (!isset($GLOBALS['COUNTRIES'][$country])) err("geçersiz country");
        if (!$phone) err("phone gerekli");

        $url = BASE_URL . "/$country/phone/$phone/sms/0";
        $baslangic = time();
        $baslangicCount = $lastCount;
        $yeni = [];

        while ((time() - $baslangic) < $timeout) {
            $html = fetch_url($url);
            if ($html) {
                $msj = parse_sms($html);
                $suankiCount = count($msj);

                if ($baslangicCount === -1) {
                    // İlk çağrı: mevcut sayıyı dön
                    $baslangicCount = $suankiCount;
                } elseif ($suankiCount > $baslangicCount) {
                    // Yeni SMS var
                    $yeni = array_slice($msj, 0, $suankiCount - $baslangicCount);
                    break;
                }
            }
            sleep(3);
        }

        json_out([
            "success"  => true,
            "phone"    => $phone,
            "found"    => count($yeni) > 0,
            "count"    => count($yeni),
            "data"     => $yeni,
            "last_count" => $baslangicCount,
        ]);
        break;

    // 6) Health
    case 'health':
        json_out([
            "success" => true,
            "status"  => "ok",
            "time"    => date("c"),
            "base"    => BASE_URL,
        ]);
        break;

    default:
        err("bilinmeyen action: $action", 404);
}