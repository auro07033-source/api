<?php
// smsotp.php - NumberPanel OTP API - Key + Rate limit korumalı
require_once __DIR__ . '/api_guard.php';
ApiGuard::checkKey();
ApiGuard::checkRate();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('NP_BASE', 'https://numberpanel.tech/api');
define('UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36');
define('CACHE_DIR', sys_get_temp_dir() . '/np_cache');
define('CACHE_TTL', 10);

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

function fetch_json($url, $ttl = CACHE_TTL) {
    $cf = cache_path($url);
    if (file_exists($cf) && (time() - filemtime($cf)) < $ttl) {
        return json_decode(file_get_contents($cf), true);
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
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'User-Agent: ' . UA,
            'Referer: https://numberpanel.tech/',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$body) return null;
    $json = json_decode($body, true);
    if ($json === null) return null;
    @file_put_contents($cf, $body);
    return $json;
}

function extract_otp($text) {
    if (preg_match('/(?:code|password|pin|otp|doğrulama)[^\d]{0,20}(\d{4,8})/i', $text, $m)) return $m[1];
    if (preg_match('/\b(\d{4,8})\b/', $text, $m)) return $m[1];
    if (preg_match('/\b(\d{3})[-\s](\d{3})\b/', $text, $m)) return $m[1] . $m[2];
    return null;
}

function normalize($raw) {
    $out = [];
    foreach ($raw as $row) {
        if (!is_array($row) || count($row) < 4) continue;
        $text = $row[2] ?? '';
        $out[] = [
            'service' => trim($row[0] ?? ''),
            'phone'   => trim($row[1] ?? ''),
            'message' => trim($text),
            'date'    => trim($row[3] ?? ''),
            'country' => trim($row[4] ?? ''),
            'otp'     => extract_otp($text),
        ];
    }
    return $out;
}

$action = $_GET['action'] ?? 'otp';

switch ($action) {
    case 'otp':
        $count = max(1, min(1000, (int)($_GET['count'] ?? 200)));
        $url = NP_BASE . '/otp?count=' . $count;
        $data = fetch_json($url);
        if ($data === null) err("numberpanel API yanit vermedi", 502);
        json_out(['success' => true, 'count' => count($data), 'data' => normalize($data)]);
        break;

    case 'lifetime':
        $url = NP_BASE . '/lifetime';
        $data = fetch_json($url, 30);
        if ($data === null) err("numberpanel API yanit vermedi", 502);
        json_out(['success' => true, 'count' => count($data), 'data' => normalize($data)]);
        break;

    case 'search':
        $q = trim($_GET['q'] ?? '');
        if ($q === '') err("q parametresi gerekli");
        $limit = min(200, max(1, (int)($_GET['limit'] ?? 50)));
        $data = fetch_json(NP_BASE . '/otp?count=500');
        if ($data === null) err("numberpanel API yanit vermedi", 502);
        $q_lower = mb_strtolower($q, 'UTF-8');
        $sonuc = [];
        foreach (normalize($data) as $m) {
            if (mb_stripos($m['service'], $q_lower, 0, 'UTF-8') !== false ||
                mb_stripos($m['message'], $q_lower, 0, 'UTF-8') !== false) {
                $sonuc[] = $m;
                if (count($sonuc) >= $limit) break;
            }
        }
        json_out(['success' => true, 'query' => $q, 'count' => count($sonuc), 'data' => $sonuc]);
        break;

    case 'otp_only':
        $count = max(1, min(1000, (int)($_GET['count'] ?? 100)));
        $data = fetch_json(NP_BASE . '/otp?count=' . $count);
        if ($data === null) err("numberpanel API yanit vermedi", 502);
        $sonuc = [];
        foreach (normalize($data) as $m) if ($m['otp']) $sonuc[] = $m;
        json_out(['success' => true, 'count' => count($sonuc), 'data' => $sonuc]);
        break;

    case 'services':
        $data = fetch_json(NP_BASE . '/otp?count=500');
        if ($data === null) err("numberpanel API yanit vermedi", 502);
        $sayac = [];
        foreach (normalize($data) as $m) {
            $s = $m['service'] ?: 'Bilinmeyen';
            $sayac[$s] = ($sayac[$s] ?? 0) + 1;
        }
        arsort($sayac);
        $out = [];
        foreach ($sayac as $s => $c) $out[] = ['service' => $s, 'count' => $c];
        json_out(['success' => true, 'count' => count($out), 'data' => $out]);
        break;

    case 'health':
        json_out(['success' => true, 'status' => 'ok', 'time' => date('c'), 'api' => NP_BASE]);
        break;

    default:
        err("bilinmeyen action: $action", 404);
}