<?php
// -*- coding: utf-8 -*-
// rusdata.json -> REST API (PHP)
// Sorgu: username VEYA id ile -> TUM bilgileri doner
// Kullanim: php -S 0.0.0.0:8080 api.php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---------- AYARLAR ----------
define('DATA_FILE', __DIR__ . '/rusdata.json');
define('DEFAULT_LIMIT', 50);
define('MAX_LIMIT', 1000);
define('API_KEY', ''); // Bos = key istemez

// ---------- YARDIMCI ----------
function cevap($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function hata($msg, $code = 400) {
    cevap(["success" => false, "error" => $msg], $code);
}

// ---------- KORUMA ----------
if (API_KEY !== '') {
    $gelen = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
    if ($gelen !== API_KEY) hata("gecersiz api key", 401);
}

// ---------- DATA YUKLE ----------
function veri_yukle() {
    if (!file_exists(DATA_FILE)) hata("rusdata.json bulunamadi", 500);
    $raw = file_get_contents(DATA_FILE);
    $json = json_decode($raw, true);
    if (!is_array($json)) hata("rusdata.json gecersiz json", 500);
    return $json;
}

$kayitlar = veri_yukle();

// ---------- ROUTE ----------
$action = $_GET['action'] ?? 'query';

switch ($action) {

    // ANA SORGU: username veya id ile TUM bilgiler
    // GET api.php?action=query&username=vihrovatati
    // GET api.php?action=query&id=246445537
    case 'query':
        $id  = $_GET['id'] ?? null;
        $usr = $_GET['username'] ?? null;

        if ($id === null && $usr === null) {
            hata("username veya id parametresi gerekli");
        }

        foreach ($kayitlar as $k) {
            $eslesme = false;

            if ($id !== null && (string)($k['id'] ?? '') === (string)$id) {
                $eslesme = true;
            }
            if ($usr !== null && mb_strtolower((string)($k['username'] ?? ''), 'UTF-8') === mb_strtolower(trim($usr), 'UTF-8')) {
                $eslesme = true;
            }

            if ($eslesme) {
                // TUM bilgileri doner
                cevap([
                    "success" => true,
                    "data"    => [
                        "first_name" => $k['first_name'] ?? null,
                        "last_name"  => $k['last_name']  ?? null,
                        "username"   => $k['username']   ?? null,
                        "id"         => $k['id']         ?? null,
                        "phone"      => $k['phone']      ?? null
                    ]
                ]);
            }
        }
        hata("kayit bulunamadi", 404);
        break;

    // GET api.php?action=list&limit=50&offset=0
    case 'list':
        $limit  = isset($_GET['limit'])  ? max(1, min(MAX_LIMIT, (int)$_GET['limit'])) : DEFAULT_LIMIT;
        $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
        $dilim  = array_slice($kayitlar, $offset, $limit);

        $temiz = array_map(function($k) {
            return [
                "first_name" => $k['first_name'] ?? null,
                "last_name"  => $k['last_name']  ?? null,
                "username"   => $k['username']   ?? null,
                "id"         => $k['id']         ?? null,
                "phone"      => $k['phone']      ?? null
            ];
        }, $dilim);

        cevap([
            "success" => true,
            "total"   => count($kayitlar),
            "limit"   => $limit,
            "offset"  => $offset,
            "count"   => count($temiz),
            "data"    => array_values($temiz)
        ]);
        break;

    // GET api.php?action=random&count=1
    case 'random':
        $adet = isset($_GET['count']) ? max(1, min(MAX_LIMIT, (int)$_GET['count'])) : 1;
        $toplam = count($kayitlar);
        if ($toplam === 0) hata("veri bos", 404);

        $secili = [];
        $indeksler = (array) array_rand($kayitlar, min($adet, $toplam));
        foreach ($indeksler as $i) {
            $k = $kayitlar[$i];
            $secili[] = [
                "first_name" => $k['first_name'] ?? null,
                "last_name"  => $k['last_name']  ?? null,
                "username"   => $k['username']   ?? null,
                "id"         => $k['id']         ?? null,
                "phone"      => $k['phone']      ?? null
            ];
        }

        cevap([
            "success" => true,
            "count"   => count($secili),
            "data"    => $secili
        ]);
        break;

    // GET api.php?action=stats
    case 'stats':
        $toplam = count($kayitlar);
        $username_dolu = 0;
        foreach ($kayitlar as $k) if (!empty($k['username'])) $username_dolu++;

        cevap([
            "success"       => true,
            "total"         => $toplam,
            "with_username" => $username_dolu,
            "file"          => basename(DATA_FILE),
            "file_size"     => filesize(DATA_FILE),
            "modified"      => date("c", filemtime(DATA_FILE))
        ]);
        break;

    // GET api.php?action=health
    case 'health':
        cevap([
            "success" => true,
            "status"  => "ok",
            "time"    => date("c"),
            "total"   => count($kayitlar)
        ]);
        break;

    default:
        hata("bilinmeyen action: $action", 404);
}