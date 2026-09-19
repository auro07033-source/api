<?php
/**
 * 🎮 Free Fire Profil API — @cmrbaskani
 * Dosya: ffprofile.php
 * Sunucu: https://ucretsizservicetr.onrender.com/ffprofile.php
 *
 * Endpointler:
 *   GET ffprofile.php?action=profile&id=403676323
 *   GET ffprofile.php?action=status&id=403676323
 *   GET ffprofile.php?action=health
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── AYARLAR ───
define('FF_API_BASE', 'https://freefirejornal.com/api/freefire/profile-preview/status');
define('FF_TOKEN', 'u9yRVlR0CRXINR7k99o23Rf4WyFindDQ6SsR1pbn5ME');
define('FF_LANG', 'en');
define('UA', 'Mozilla/5.0 (Android 15; Mobile; rv:155.0) Gecko/155.0 Firefox/155.0');
define('CACHE_DIR', sys_get_temp_dir() . '/ff_cache');
define('CACHE_TTL', 30); // 30 saniye cache

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

function ff_request($player_id) {
    // Cache kontrol
    $cf = cache_path($player_id);
    if (file_exists($cf) && (time() - filemtime($cf)) < CACHE_TTL) {
        return json_decode(file_get_contents($cf), true);
    }

    $url = FF_API_BASE . '/' . urlencode($player_id)
         . '?token=' . FF_TOKEN
         . '&lang=' . FF_LANG;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_ENCODING       => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'X-FFJ-FF-Profile: preview',
            'User-Agent: ' . UA,
        ],
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) return null;

    $json = json_decode($body, true);
    if (!$json) return null;

    @file_put_contents($cf, $body);
    return $json;
}

// ─── ROUTE ───
$action = $_GET['action'] ?? 'profile';

switch ($action) {

    // GET ffprofile.php?action=profile&id=403676323
    case 'profile':
    case 'status':
        $id = preg_replace('/\D/', '', $_GET['id'] ?? '');
        if (!$id) err("id parametresi gerekli (rakamsal)");

        $data = ff_request($id);
        if ($data === null) {
            err("Free Fire API yanıt vermedi. ID doğru mu? (ör: 403676323)", 502);
        }

        // State kontrolü: "ready" değilse hata dön
        if (($data['state'] ?? '') !== 'ready') {
            json_out([
                "success" => false,
                "error"   => "Profil hazır değil (state: " . ($data['state'] ?? 'unknown') . ")",
                "raw"     => $data,
            ], 404);
        }

        json_out([
            "success"  => true,
            "data"     => [
                "player_id"    => $data['playerId']    ?? null,
                "nickname"     => $data['nickname']    ?? null,
                "region"       => $data['region']      ?? null,
                "level"        => $data['level']       ?? null,
                "likes"        => $data['likes']       ?? null,
                "rank"         => $data['rank']        ?? null,
                "rank_points"  => $data['rankPoints']  ?? null,
                "avatar"       => $data['avatarUrl']   ?? null,
                "profile_url"  => $data['profileUrl']  ?? null,
                "updated_at"   => $data['updatedAt']   ?? null,
            ],
        ]);
        break;

    case 'health':
        json_out([
            "success" => true,
            "status"  => "ok",
            "time"    => date("c"),
            "api"     => FF_API_BASE,
        ]);
        break;

    default:
        err("bilinmeyen action: $action", 404);
}