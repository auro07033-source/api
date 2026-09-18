<?php
/**
 * 📡 SMS24.me OTP API — @cmrbaskani
 * https://senin-domain/sms24.php
 *
 *   GET ?action=countries
 *   GET ?action=numbers&country=us
 *   GET ?action=sms&country=us&phone=1xxxxxxxxxx
 *   GET ?action=latest&country=us&phone=1xxxxxxxxxx
 *   GET ?action=debug&country=us
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('BASE_URL', 'https://sms24.me');
define('UA', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36');
define('CACHE_DIR', sys_get_temp_dir() . '/sms24_cache');
define('CACHE_TTL', 5);

// 53 ülke (slug => isim)
$COUNTRIES = [
  'ar'=>'🇦🇷 Arjantin','au'=>'🇦🇺 Avustralya','at'=>'🇦🇹 Avusturya','bd'=>'🇧🇩 Bangladeş',
  'be'=>'🇧🇪 Belçika','br'=>'🇧🇷 Brezilya','bg'=>'🇧🇬 Bulgaristan','ca'=>'🇨🇦 Kanada',
  'cl'=>'🇨🇱 Şili','cn'=>'🇨🇳 Çin','co'=>'🇨🇴 Kolombiya','hr'=>'🇭🇷 Hırvatistan',
  'cz'=>'🇨🇿 Çekya','dk'=>'🇩🇰 Danimarka','ee'=>'🇪🇪 Estonya','fi'=>'🇫🇮 Finlandiya',
  'fr'=>'🇫🇷 Fransa','ge'=>'🇬🇪 Gürcistan','de'=>'🇩🇪 Almanya','hk'=>'🇭🇰 Hong Kong',
  'in'=>'🇮🇳 Hindistan','id'=>'🇮🇩 Endonezya','il'=>'🇮🇱 İsrail','it'=>'🇮🇹 İtalya',
  'jp'=>'🇯🇵 Japonya','kz'=>'🇰🇿 Kazakistan','lv'=>'🇱🇻 Letonya','lt'=>'🇱🇹 Litvanya',
  'my'=>'🇲🇾 Malezya','mx'=>'🇲🇽 Meksika','mm'=>'🇲🇲 Myanmar','nl'=>'🇳🇱 Hollanda',
  'nz'=>'🇳🇿 Yeni Zelanda','ng'=>'🇳🇬 Nijerya','no'=>'🇳🇴 Norveç','ph'=>'🇵🇭 Filipinler',
  'pl'=>'🇵🇱 Polonya','pt'=>'🇵🇹 Portekiz','pr'=>'🇵🇷 Porto Riko','ro'=>'🇷🇴 Romanya',
  'ru'=>'🇷🇺 Rusya','rs'=>'🇷🇸 Sırbistan','za'=>'🇿🇦 Güney Afrika','kr'=>'🇰🇷 Güney Kore',
  'es'=>'🇪🇸 İspanya','se'=>'🇸🇪 İsveç','ch'=>'🇨🇭 İsviçre','th'=>'🇹🇭 Tayland',
  'ua'=>'🇺🇦 Ukrayna','gb'=>'🇬🇧 Birleşik Krallık','us'=>'🇺🇸 ABD','uz'=>'🇺🇿 Özbekistan',
  'vn'=>'🇻🇳 Vietnam',
];

function json_out($d, $c=200){ http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); exit; }
function err($m, $c=400){ json_out(["success"=>false,"error"=>$m], $c); }

function cache_path($k){ if(!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR,0777,true); return CACHE_DIR.'/'.md5($k).'.cache'; }

function fetch_url($url, $bypass=false){
    $cf = cache_path($url);
    if(!$bypass && file_exists($cf) && (time()-filemtime($cf))<CACHE_TTL) return file_get_contents($cf);

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
            'User-Agent: '.UA,
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: '.BASE_URL.'/',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($code !== 200 || !$body) return null;
    @file_put_contents($cf, $body);
    return $body;
}

function clean_t($s){ return trim(preg_replace('/\s+/',' ',$s??'')); }

function extract_otp($text){
    // 4-8 haneli, ortada rakam grubu
    if(preg_match('/\b(\d{4,8})\b/', $text, $m)) return $m[1];
    return null;
}

// ─── NUMARA PARSER ───
// sms24.me numara linkleri: /en/numbers/<num> veya /en/phone/<num> formatında
function parse_numbers($html, $country){
    if(!$html) return [];
    $nums = [];
    $seen = [];

    // Tüm href'leri yakala
    if(preg_match_all('#href=["\']([^"\']+)["\']#i', $html, $matches)){
        foreach($matches[1] as $href){
            $num = null;
            // /en/numbers/1xxxxxxxxxx
            if(preg_match('#/numbers/(\+?\d{7,15})#', $href, $m)){
                $num = ltrim($m[1],'+');
            }
            // /en/phone/1xxxxxxxxxx
            elseif(preg_match('#/phone/(\+?\d{7,15})#', $href, $m)){
                $num = ltrim($m[1],'+');
            }
            if(!$num || strlen($num)<7) continue;
            if(isset($seen[$num])) continue;
            $seen[$num] = true;

            $full = (strpos($href,'http')===0) ? $href : BASE_URL.$href;

            $nums[] = [
                "phone"   => $num,
                "display" => '+'.$num,
                "url"     => $full,
                "country" => $country,
            ];
        }
    }

    // Hiç bulunamadıysa: sayfadaki numaraları ülke prefix'i ile ara
    if(empty($nums)){
        if(preg_match_all('#\b(\d{10,15})\b#', $html, $m)){
            foreach($m[1] as $num){
                if(isset($seen[$num])) continue;
                $seen[$num] = true;
                $nums[] = [
                    "phone"   => $num,
                    "display" => '+'.$num,
                    "url"     => BASE_URL."/en/numbers/$num",
                    "country" => $country,
                ];
            }
        }
    }

    return $nums;
}

// ─── SMS PARSER ───
// sms24.me numara sayfasında SMS listesi tablo veya div kartları olarak gelir
function parse_sms($html){
    if(!$html) return [];
    $msj = [];

    if(class_exists('DOMDocument')){
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);

        // Yaygın class isimleri
        foreach($x->query('//div[contains(@class,"message") or contains(@class,"sms") or contains(@class,"msg") or contains(@class,"card")]') as $d){
            $t = clean_t($d->textContent);
            if(strlen($t)<5 || strlen($t)>2000) continue;
            // "from" veya "sender" alanını ayıkla
            $sender = '';
            if(preg_match('/(?:from|sender|gönderen)\s*:?\s*([^\n]{2,40})/i', $t, $mm)) $sender = $mm[1];
            $msj[] = [
                "sender" => $sender ?: "unknown",
                "text"   => $t,
                "date"   => "",
                "otp"    => extract_otp($t),
            ];
        }

        // Tablo yapısı
        if(empty($msj)){
            foreach($x->query('//tr') as $tr){
                $cells = $x->query('.//td', $tr);
                if($cells->length<2) continue;
                $p = [];
                foreach($cells as $c) $p[] = clean_t($c->textContent);
                if(!$p[0] || strtolower($p[0])==='from') continue;
                $msj[] = [
                    "sender" => $p[0],
                    "text"   => $p[1] ?? '',
                    "date"   => $p[2] ?? '',
                    "otp"    => extract_otp($p[1] ?? ''),
                ];
            }
        }
    }

    return $msj;
}

// ─── ROUTER ───
$action = $_GET['action'] ?? 'countries';

switch($action){

    case 'countries':
        $out = [];
        foreach($GLOBALS['COUNTRIES'] as $slug=>$name){
            $out[] = [
                "slug" => $slug,
                "name" => $name,
                "url"  => BASE_URL."/en/countries/".$slug,
            ];
        }
        json_out(["success"=>true,"count"=>count($out),"data"=>$out]);
        break;

    case 'numbers':
        $c = $_GET['country'] ?? '';
        if(!isset($GLOBALS['COUNTRIES'][$c])) err("geçersiz country");
        $html = fetch_url(BASE_URL."/en/countries/".$c);
        if(!$html) err("sayfa alınamadı", 502);
        $nums = parse_numbers($html, $c);
        json_out([
            "success"=>true,"country"=>$c,
            "count"=>count($nums),"data"=>$nums,
        ]);
        break;

    case 'sms':
        $c = $_GET['country'] ?? '';
        $p = preg_replace('/\D/','',$_GET['phone'] ?? '');
        if(!isset($GLOBALS['COUNTRIES'][$c])) err("geçersiz country");
        if(!$p) err("phone gerekli");

        // sms24.me numara sayfası
        $html = fetch_url(BASE_URL."/en/numbers/".$p);
        if(!$html) $html = fetch_url(BASE_URL."/en/phone/".$p);
        if(!$html) err("numara sayfası alınamadı", 502);

        $msj = parse_sms($html);
        json_out([
            "success"=>true,"country"=>$c,"phone"=>$p,
            "count"=>count($msj),"data"=>$msj,
        ]);
        break;

    case 'latest':
        $c = $_GET['country'] ?? '';
        $p = preg_replace('/\D/','',$_GET['phone'] ?? '');
        if(!isset($GLOBALS['COUNTRIES'][$c])) err("geçersiz country");
        if(!$p) err("phone gerekli");

        $html = fetch_url(BASE_URL."/en/numbers/".$p);
        if(!$html) $html = fetch_url(BASE_URL."/en/phone/".$p);
        if(!$html) err("numara sayfası alınamadı", 502);

        $msj = parse_sms($html);
        if(empty($msj)) json_out(["success"=>true,"phone"=>$p,"count"=>0,"data"=>[]]);

        $son = $msj[0];
        json_out([
            "success"=>true,"phone"=>$p,"count"=>1,
            "data"=>[[
                "sender" => $son['sender'],
                "text"   => $son['text'],
                "date"   => $son['date'],
                "otp"    => $son['otp'],
            ]],
        ]);
        break;

    case 'health':
        json_out([
            "success"=>true,"status"=>"ok","time"=>date("c"),
            "php"=>PHP_VERSION,
            "curl"=>function_exists('curl_init'),
            "dom"=>class_exists('DOMDocument'),
        ]);
        break;

    case 'debug':
        header("Content-Type: text/plain; charset=utf-8");
        $c = $_GET['country'] ?? 'us';
        $url = BASE_URL."/en/countries/".$c;
        echo "URL       : $url\n";
        echo "PHP       : ".PHP_VERSION."\n";
        echo "cURL      : ".(function_exists('curl_init')?"OK":"MISSING")."\n";
        echo "DOM       : ".(class_exists('DOMDocument')?"OK":"MISSING")."\n";
        echo str_repeat("-",60)."\n\n";

        $ch = curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: '.UA,
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Referer: '.BASE_URL.'/',
            ],
        ]);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $cerr = curl_error($ch);
        curl_close($ch);

        echo "HTTP_CODE : ".$info['http_code']."\n";
        echo "SIZE      : ".strlen($body?:'')." bytes\n";
        echo "CURL_ERR  : ".($cerr?:"-")."\n";
        echo "FINAL_URL : ".($info['url']??'-')."\n\n";
        echo str_repeat("-",60)."\n\n";
        echo "HTML (ilk 4000):\n\n";
        echo substr($body?:"(BOŞ)",0,4000);
        exit;

    default:
        err("bilinmeyen action: $action", 404);
}