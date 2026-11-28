<?php
// hava_api.php - Web/domain üzerinde çalışan hava durumu API'si
// Kurulum: dosyayı sunucuya at, https://domain.com/hava_api.php?sehir=istanbul
// Çıktı: JSON. Kaydetme yok. Sadece canlı yanıt.
// Geliştirici: @ittizar

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$HEADERS = ["User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36"];

$ILLER = [
    "adana"=>[37.00,35.32],"adiyaman"=>[37.76,38.28],"afyonkarahisar"=>[38.76,30.54],
    "agri"=>[39.72,43.05],"amasya"=>[40.65,35.83],"ankara"=>[39.93,32.86],
    "antalya"=>[36.90,30.70],"artvin"=>[41.18,41.82],"aydin"=>[37.85,27.84],
    "balikesir"=>[39.65,27.89],"bilecik"=>[40.15,29.98],"bingol"=>[38.88,40.50],
    "bitlis"=>[38.40,42.11],"bolu"=>[40.74,31.61],"burdur"=>[37.72,30.29],
    "bursa"=>[40.19,29.06],"canakkale"=>[40.15,26.41],"cankiri"=>[40.60,33.62],
    "corum"=>[40.55,34.95],"denizli"=>[37.78,29.09],"diyarbakir"=>[37.91,40.24],
    "edirne"=>[41.68,26.56],"elazig"=>[38.68,39.22],"erzincan"=>[39.75,39.49],
    "erzurum"=>[39.90,41.27],"eskisehir"=>[39.78,30.52],"gaziantep"=>[37.07,37.38],
    "giresun"=>[40.91,38.39],"gumushane"=>[40.46,39.48],"hakkari"=>[37.58,43.74],
    "hatay"=>[36.20,36.16],"isparta"=>[37.76,30.55],"mersin"=>[36.80,34.63],
    "istanbul"=>[41.01,28.98],"izmir"=>[38.42,27.14],"kars"=>[40.60,43.10],
    "kastamonu"=>[41.38,33.78],"kayseri"=>[38.73,35.49],"kirklareli"=>[41.73,27.22],
    "kirsehir"=>[39.15,34.16],"kocaeli"=>[40.85,29.88],"konya"=>[37.87,32.48],
    "kutahya"=>[39.42,29.98],"malatya"=>[38.35,38.31],"manisa"=>[38.62,27.43],
    "kahramanmaras"=>[37.58,36.94],"mardin"=>[37.31,40.74],"mugla"=>[37.22,28.36],
    "mus"=>[38.73,41.49],"nevsehir"=>[38.62,34.71],"nigde"=>[37.97,34.68],
    "ordu"=>[40.98,37.88],"rize"=>[41.02,40.52],"sakarya"=>[40.78,30.40],
    "samsun"=>[41.29,36.33],"siirt"=>[37.93,41.94],"sinop"=>[42.03,35.15],
    "sivas"=>[39.75,37.02],"tekirdag"=>[40.98,27.51],"tokat"=>[40.31,36.55],
    "trabzon"=>[41.00,39.72],"tunceli"=>[39.11,39.55],"sanliurfa"=>[37.17,38.79],
    "usak"=>[38.68,29.41],"van"=>[38.49,43.38],"yozgat"=>[39.82,34.81],
    "zonguldak"=>[41.46,31.79],"aksaray"=>[38.37,34.03],"bayburt"=>[40.26,40.22],
    "karaman"=>[37.18,33.22],"kirikkale"=>[39.85,33.52],"batman"=>[37.88,41.13],
    "sirnak"=>[37.52,42.46],"bartin"=>[41.64,32.34],"ardahan"=>[41.11,42.70],
    "igdir"=>[39.92,44.04],"yalova"=>[40.65,29.28],"karabuk"=>[41.20,32.63],
    "kilis"=>[36.72,37.12],"osmaniye"=>[37.07,36.25],"duzce"=>[40.84,31.16]
];

function http_get($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $res];
}

function normalize_tr($s) {
    $s = mb_strtolower(trim($s), "UTF-8");
    return strtr($s, ["ç"=>"c","ğ"=>"g","ı"=>"i","ö"=>"o","ş"=>"s","ü"=>"u","â"=>"a","î"=>"i","û"=>"u"]);
}

function koordinat_bul($sehir) {
    global $ILLER, $HEADERS;
    $s = normalize_tr($sehir);
    if (isset($ILLER[$s])) return $ILLER[$s];
    list($code, $body) = http_get("https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($sehir) . "&count=1&language=tr&countryCode=TR", $HEADERS);
    $d = json_decode($body, true);
    if (!empty($d["results"])) return [$d["results"][0]["latitude"], $d["results"][0]["longitude"]];
    return null;
}

function wttr_cek($sehir) {
    global $HEADERS;
    list($code, $body) = http_get("https://wttr.in/" . urlencode($sehir) . "?format=j1&lang=tr", $HEADERS);
    if ($code !== 200) return null;
    $d = json_decode($body, true);
    if (!$d || !isset($d["current_condition"][0])) return null;
    $c = $d["current_condition"][0];
    return [
        "sicaklik"   => $c["temp_C"] . "°C",
        "hissedilen" => $c["FeelsLikeC"] . "°C",
        "durum"      => isset($c["lang_tr"][0]["value"]) ? $c["lang_tr"][0]["value"] : $c["weatherDesc"][0]["value"],
        "nem"        => $c["humidity"] . "%",
        "ruzgar"     => $c["windspeedKmph"] . " km/h",
        "basinc"     => $c["pressure"] . " hPa",
        "gorus"      => $c["visibility"] . " km",
        "uvindex"    => $c["uvIndex"]
    ];
}

function openmeteo_cek($lat, $lon) {
    global $HEADERS;
    $url = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&current_weather=true&daily=temperature_2m_max,temperature_2m_min,precipitation_sum&timezone=Europe/Istanbul";
    list($code, $body) = http_get($url, $HEADERS);
    if ($code !== 200) return null;
    $d = json_decode($body, true);
    if (!$d || !isset($d["current_weather"])) return null;
    $cw = $d["current_weather"]; $dl = $d["daily"] ?? [];
    return [
        "sicaklik"  => $cw["temperature"] . "°C",
        "ruzgar"    => $cw["windspeed"] . " km/h",
        "yon"       => $cw["winddirection"] . "°",
        "bugun_max" => ($dl["temperature_2m_max"][0] ?? "?") . "°C",
        "bugun_min" => ($dl["temperature_2m_min"][0] ?? "?") . "°C",
        "yagis_mm"  => ($dl["precipitation_sum"][0] ?? "?") . " mm"
    ];
}

function gunes_cek($lat, $lon) {
    global $HEADERS;
    list($code, $body) = http_get("https://api.sunrise-sunset.org/json?lat=$lat&lng=$lon&formatted=0", $HEADERS);
    if ($code !== 200) return null;
    $d = json_decode($body, true)["results"] ?? null;
    if (!$d) return null;
    $fmt = function($iso) {
        $dt = new DateTime($iso);
        $dt->setTimezone(new DateTimeZone("Europe/Istanbul"));
        return $dt->format("H:i");
    };
    return ["gun_dogumu"=>$fmt($d["sunrise"]), "gun_batimi"=>$fmt($d["sunset"]), "gun_uzunlugu"=>$d["day_length"]];
}

// ==== İSTEK İŞLEME ====
$sehir = $_GET["sehir"] ?? $_GET["city"] ?? $_GET["q"] ?? "istanbul";
$koord = koordinat_bul($sehir);

if (!$koord) {
    http_response_code(404);
    echo json_encode(["hata" => "şehir bulunamadı", "sehir" => $sehir], JSON_UNESCAPED_UNICODE);
    exit;
}

list($lat, $lon) = $koord;
$yanit = [
    "sehir"      => $sehir,
    "koordinat"  => ["lat" => $lat, "lon" => $lon],
    "wttr"       => wttr_cek($sehir),
    "openmeteo"  => openmeteo_cek($lat, $lon),
    "gunes"      => gunes_cek($lat, $lon),
    "gelistirici"=> "@ittizar",
    "zaman"      => date("c")
];

echo json_encode($yanit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);