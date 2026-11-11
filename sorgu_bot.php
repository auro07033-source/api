<?php
// sorgu_bot.php
// Buton bazli sorgu botu. PHP + cURL ile. long polling.
// Calistirma: php sorgu_bot.php

$TOKEN = "8950660109:AAFB8lKqUYFuGJMIlNOQkjVMqoJe1_GEXm4";
$CHANNEL = "@kehanetsorgu";
$CHANNEL_LINK = "https://t.me/kehanetsorgu";

$SRC1 = getenv("SRC1") ?: "https://api.ajaxsystems.fun";
$SRC2 = getenv("SRC2") ?: "https://mayte.pro/api/query";

$SMS_BASE = "https://ucretsizservicetr.onrender.com/smsotp.php";
$BINANCE_BASE = "https://ucretsizservicetr.onrender.com/binancesrg.php";
$GPT_BASE = "https://ucretsizservicetr.onrender.com/gptpro.php";
$API_KEY = "cmrbaskani_2026_secret_key_xyz";
$DEVICE_ID = "dev_j6dwyzi2j3s_1790075244015";

$HEADERS_GET = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
    "Accept: application/json, text/javascript, */*; q=0.01",
];


// ─────────────────────────────
// Yardımcı fonksiyonlar
// ─────────────────────────────

function api($token, $method, $params = []) {
    $url = "https://api.telegram.org/bot{$token}/{$method}";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_TIMEOUT => 60,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function http_get($url, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $res) {
        return json_decode($res, true) ?: null;
    }
    return null;
}

function http_post_json($url, $body, $headers = []) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => array_merge($headers, ["Content-Type: application/json"]),
        CURLOPT_TIMEOUT => 25,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && $res) {
        return json_decode($res, true) ?: null;
    }
    return null;
}

function dosya_gonder($token, $chat_id, $sonuc, $isim) {
    if (!$sonuc) {
        api($token, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "Baktım ama bir şey bulamadım 🤷‍♂️\nBilgileri kontrol edip tekrar dener misin?",
        ]);
        return;
    }

    $yol = sys_get_temp_dir() . "/{$isim}_" . time() . ".json";
    file_put_contents($yol, json_encode($sonuc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendDocument");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            "chat_id" => $chat_id,
            "caption" => "İşte bulduklarım 📄",
            "document" => new CURLFile($yol, "application/json", basename($yol)),
        ],
        CURLOPT_TIMEOUT => 60,
    ]);
    curl_exec($ch);
    curl_close($ch);
    @unlink($yol);
}

function ana_menu() {
    return [
        "inline_keyboard" => [
            [
                ["text" => "👤 Kişi Sorgulama", "callback_data" => "menu_kisi"],
                ["text" => "🔍 Kişi Detay Sorgulama", "callback_data" => "menu_kisidetay"],
            ],
            [
                ["text" => "👨‍👩‍👧 Aile Sorgulama", "callback_data" => "menu_aile"],
                ["text" => "🌳 Sülale Sorgulama", "callback_data" => "menu_sulale"],
            ],
            [
                ["text" => "📞 TC'den GSM Bulma", "callback_data" => "menu_tcgsm"],
                ["text" => "📱 GSM'den TC Bulma", "callback_data" => "menu_gsmtc"],
            ],
            [
                ["text" => "🏠 Adres Sorgulama", "callback_data" => "menu_adres"],
                ["text" => "📜 Tapu Sorgulama", "callback_data" => "menu_tapu"],
            ],
            [
                ["text" => "🗺️ Ada Parsel Sorgulama", "callback_data" => "menu_adaparsel"],
                ["text" => "🎒 Okul Bilgisi Sorgulama", "callback_data" => "menu_okul"],
            ],
            [
                ["text" => "📇 Ad Soyad ile Arama", "callback_data" => "menu_adsoyad"],
                ["text" => "🧩 Toplu Sorgulama", "callback_data" => "menu_toplu"],
            ],
            [
                ["text" => "💼 İşyeri Sorgulama", "callback_data" => "menu_isyeri"],
                ["text" => "🏡 Kişi Tapu Sorgulama", "callback_data" => "menu_tapukisi"],
            ],
            [
                ["text" => "💬 SMS OTP Sorgulama", "callback_data" => "menu_smsotp"],
                ["text" => "🪙 Binance Sorgulama", "callback_data" => "menu_binance"],
            ],
            [
                ["text" => "🤖 GPT'ye Sor", "callback_data" => "menu_gpt"],
            ],
        ],
    ];
}

function uye_mi($token, $channel, $uid) {
    $r = api($token, "getChatMember", ["chat_id" => $channel, "user_id" => $uid]);
    if (!($r["ok"] ?? false)) return false;
    $st = $r["result"]["status"] ?? "";
    return in_array($st, ["member", "administrator", "creator"]);
}


// ─────────────────────────────
// Sorgu istekleri
// ─────────────────────────────

function tc_sorgu($tip, $tc, $SRC1, $SRC2, $H) {
    switch ($tip) {
        case "kisi":      return http_get("{$SRC1}/tc.php?tc={$tc}", $H);
        case "kisidetay": return http_get("{$SRC1}/tcpro.php?tc={$tc}", $H);
        case "aile":      return http_get("{$SRC1}/aile.php?tc={$tc}", $H);
        case "sulale":    return http_get("{$SRC1}/sulale.php?tc={$tc}", $H);
        case "adres":     return http_get("{$SRC1}/adres.php?tc={$tc}", $H);
        case "tapu":      return http_get("{$SRC1}/tapu.php?tc={$tc}", $H);
        case "okul":      return http_get("{$SRC1}/eokul.php?tc={$tc}", $H);
        case "toplu":     return http_post_json("{$SRC2}/toplu-sorgu?page=1", ["mod" => "yakin", "tc" => $tc], $H);
        case "isyeri":    return http_post_json("{$SRC2}/isyeri-sorgu?page=1", ["tc" => $tc], $H);
        case "tapukisi":  return http_post_json("{$SRC2}/tapu-kisi?page=1", ["tc" => $tc], $H);
    }
    return null;
}


// ─────────────────────────────
// Callback işleyici
// ─────────────────────────────

function callback_isle($c, $TOKEN, $CHANNEL, $SRC1, $SRC2, $H, $MENU) {
    $data = $c["data"];
    $chat_id = $c["message"]["chat"]["id"];
    $msg_id = $c["message"]["message_id"];
    $uid = $c["from"]["id"];

    if (!uye_mi($TOKEN, $CHANNEL, $uid)) {
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "Önce kanala katılman gerek dostum 🙂",
        ]);
        return;
    }

    if ($data === "ana_menu") {
        $isim = $c["from"]["first_name"] ?? "dostum";
        api($TOKEN, "editMessageText", [
            "chat_id" => $chat_id,
            "message_id" => $msg_id,
            "text" => "Geri döndün {$isim} 🙂 Ne arayacağını seç.",
            "reply_markup" => json_encode($MENU),
        ]);
        return;
    }

    $tc_map = [
        "menu_kisi"      => ["kisi", "Kişi sorgulaması için TC kimlik numarasını yaz (11 hane):", "TC"],
        "menu_kisidetay" => ["kisidetay", "Detaylı kişi sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_aile"      => ["aile", "Aile sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_sulale"    => ["sulale", "Sülale sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_adres"     => ["adres", "Adres sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_tapu"      => ["tapu", "Tapu sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_okul"      => ["okul", "Okul bilgisi için TC kimlik numarasını yaz:", "TC"],
        "menu_toplu"     => ["toplu", "Toplu sorgu için TC kimlik numarasını yaz:", "TC"],
        "menu_isyeri"    => ["isyeri", "İşyeri sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_tapukisi"  => ["tapukisi", "Kişi tapu sorgusu için TC kimlik numarasını yaz:", "TC"],
        "menu_tcgsm"     => ["tcgsm", "TC'den GSM bulmak için kimlik numarasını yaz:", "TC"],
        "menu_gsmtc"     => ["gsmtc", "GSM'den TC bulmak için telefon numarasını yaz (5xx xxx xx xx):", "GSM"],
        "menu_adsoyad"   => ["adsoyad", "Ad soyad ile arama yapacağız. Önce adı yaz:", "AD"],
        "menu_adaparsel" => ["adaparsel", "Ada parsel sorgusu için ili yaz:", "IL"],
        "menu_smsotp"    => ["smsotp", "Kaç adet SMS OTP kaydı görmek istersin? (örnek: 50):", "SAYI"],
        "menu_binance"   => ["binance", "Binance sorgusu için ID (userId) yaz:", "SAYI"],
        "menu_gpt"       => ["gpt", "GPT'ye ne sormak istiyorsun? Sorunu yaz:", "METIN"],
    ];

    if (isset($tc_map[$data])) {
        [$tip, $metin, $beklenen] = $tc_map[$data];
        api($TOKEN, "editMessageText", [
            "chat_id" => $chat_id,
            "message_id" => $msg_id,
            "text" => $metin,
        ]);

        // Cevabı bekle
        $state = sys_get_temp_dir() . "/state_{$uid}.json";
        file_put_contents($state, json_encode([
            "step" => $tip,
            "expect" => $beklenen,
            "chat_id" => $chat_id,
            "ts" => time(),
        ]));
        return;
    }
}


// ─────────────────────────────
// Adım işleyici (state okuma)
// ─────────────────────────────

function adim_isle($m, $TOKEN, $SRC1, $SRC2, $H, $SMS_BASE, $BINANCE_BASE, $GPT_BASE, $API_KEY, $DEVICE_ID, $MENU) {
    $uid = $m["from"]["id"];
    $chat_id = $m["chat"]["id"];
    $text = trim($m["text"] ?? "");
    $state_file = sys_get_temp_dir() . "/state_{$uid}.json";

    if (!file_exists($state_file)) return false;

    $state = json_decode(file_get_contents($state_file), true);
    if (!$state || (time() - ($state["ts"] ?? 0)) > 300) {
        @unlink($state_file);
        return false;
    }

    $step = $state["step"];
    $expect = $state["expect"];
    $answer = $text;

    // Adım adım ek sorular
    if ($step === "adsoyad" && $expect === "AD") {
        file_put_contents($state_file, json_encode([
            "step" => "adsoyad_soyad",
            "expect" => "SOYAD",
            "ad" => strtoupper($answer),
            "chat_id" => $chat_id,
            "ts" => time(),
        ]));
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "Şimdi de soyadı yazar mısın?",
        ]);
        return true;
    }

    if ($step === "adsoyad_soyad") {
        @unlink($state_file);
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "Bakıyorum... ⏳",
        ]);
        $ad = $state["ad"] ?? "";
        $soyad = strtoupper($answer);
        $sonuc = http_get("{$SRC1}/adsoyad.php?ad={$ad}&soyad={$soyad}", $H);
        dosya_gonder($TOKEN, $chat_id, $sonuc, "adsoyad");
        return true;
    }

    if ($step === "adaparsel" && $expect === "IL") {
        file_put_contents($state_file, json_encode([
            "step" => "adaparsel_ilce",
            "expect" => "ILCE",
            "il" => strtoupper($answer),
            "chat_id" => $chat_id,
            "ts" => time(),
        ]));
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "İlçeyi de yaz:",
        ]);
        return true;
    }

    if ($step === "adaparsel_ilce") {
        @unlink($state_file);
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => "Ada parsel kayıtlarına bakıyorum... ⏳",
        ]);
        $il = $state["il"] ?? "";
        $ilce = strtoupper($answer);
        $sonuc = http_get("{$SRC1}/adaparsel.php?il={$il}&ilce={$ilce}", $H);
        dosya_gonder($TOKEN, $chat_id, $sonuc, "adaparsel");
        return true;
    }

    if ($step === "tcgsm") {
        @unlink($state_file);
        if (!ctype_digit($answer) || strlen($answer) !== 11) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "TC 11 haneli olmalı, tekrar dener misin?"]);
            return true;
        }
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Telefon numarasını arıyorum... ⏳"]);
        $sonuc = http_get("{$SRC1}/tcgsm.php?tc={$answer}", $H);
        dosya_gonder($TOKEN, $chat_id, $sonuc, "tcgsm");
        return true;
    }

    if ($step === "gsmtc") {
        @unlink($state_file);
        $gsm = str_replace(" ", "", $answer);
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Bu numaranın sahibine bakıyorum... ⏳"]);
        $sonuc = http_get("{$SRC1}/gsmtc.php?gsm={$gsm}&auth=fire", $H);
        dosya_gonder($TOKEN, $chat_id, $sonuc, "gsmtc");
        return true;
    }

    if ($step === "smsotp") {
        @unlink($state_file);
        if (!ctype_digit($answer)) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Sayı girmelisin, tekrar dener misin?"]);
            return true;
        }
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "SMS kayıtlarına bakıyorum... ⏳"]);
        $url = "{$SMS_BASE}?key={$API_KEY}&device_id={$DEVICE_ID}&action=otp&count={$answer}";
        $sonuc = http_get($url, $H);
        if (!$sonuc || !($sonuc["success"] ?? false)) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Servis şu an cevap vermiyor, birazdan tekrar dene."]);
            return true;
        }
        $kayitlar = $sonuc["data"] ?? [];
        if (!$kayitlar) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Yeni SMS kaydı yok gibi görünüyor."]);
            return true;
        }
        $i = 1;
        foreach (array_slice($kayitlar, 0, 10) as $k) {
            $metin = "📩 **{$i}. SMS**\n"
                . "Servis: " . ($k["service"] ?? "-") . "\n"
                . "Ülke: " . ($k["country"] ?? "-") . "\n"
                . "Telefon: `" . ($k["phone"] ?? "-") . "`\n"
                . "Kod: `" . ($k["otp"] ?? "-") . "`\n"
                . "Tarih: " . ($k["date"] ?? "-") . "\n"
                . "Mesaj: " . mb_substr($k["message"] ?? "", 0, 200);
            api($TOKEN, "sendMessage", [
                "chat_id" => $chat_id,
                "text" => $metin,
                "parse_mode" => "Markdown",
            ]);
            $i++;
        }
        if (count($kayitlar) > 10) {
            api($TOKEN, "sendMessage", [
                "chat_id" => $chat_id,
                "text" => "Toplam " . count($kayitlar) . " kayıt var, ilk 10'unu gösterdim.",
            ]);
        }
        return true;
    }

    if ($step === "binance") {
        @unlink($state_file);
        if (!ctype_digit($answer)) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Binance ID sayı olmalı, tekrar dener misin?"]);
            return true;
        }
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Binance kaydına bakıyorum... ⏳"]);
        $url = "{$BINANCE_BASE}?key={$API_KEY}&device_id={$DEVICE_ID}&id={$answer}";
        $sonuc = http_get($url, $H);
        if (!$sonuc) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Servisten cevap gelmedi, birazdan tekrar dene."]);
            return true;
        }
        if (!($sonuc["success"] ?? false) && !($sonuc["ok"] ?? false)) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Sonuç yok: " . ($sonuc["message"] ?? "Bilinmeyen hata")]);
            return true;
        }
        $metin = "🪙 **Binance Sorgu Sonucu**\n\n";
        $data = $sonuc["data"] ?? $sonuc["rows"] ?? $sonuc;
        if (is_array($data) && isset($data[0])) $data = $data[0];
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                $metin .= "• **{$k}**: {$v}\n";
            }
        } else {
            $metin .= (string)$data;
        }
        api($TOKEN, "sendMessage", [
            "chat_id" => $chat_id,
            "text" => mb_substr($metin, 0, 4000),
            "parse_mode" => "Markdown",
        ]);
        return true;
    }

    if ($step === "gpt") {
        @unlink($state_file);
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Düşünüyorum... 🤔"]);
        $url = "{$GPT_BASE}?key={$API_KEY}&device_id={$DEVICE_ID}&model=pollinations&q=" . urlencode($answer);
        $sonuc = http_get($url, $H);
        if (!$sonuc) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Şu an cevap veremiyorum, birazdan tekrar dene."]);
            return true;
        }
        $cevap = $sonuc["response"] ?? $sonuc["answer"] ?? $sonuc["message"] ?? null;
        if (is_array($sonuc["data"] ?? null)) $cevap = json_encode($sonuc["data"], JSON_UNESCAPED_UNICODE);
        if (!$cevap) $cevap = json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (mb_strlen($cevap) > 4000) $cevap = mb_substr($cevap, 0, 4000) . "\n\n... (devamı kesildi)";
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => $cevap]);
        return true;
    }

    // TC isteyenler
    if (in_array($step, ["kisi", "kisidetay", "aile", "sulale", "adres", "tapu", "okul", "toplu", "isyeri", "tapukisi"])) {
        @unlink($state_file);
        if (!ctype_digit($answer) || strlen($answer) !== 11) {
            api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "TC 11 haneli olmalı, tekrar dener misin?"]);
            return true;
        }
        api($TOKEN, "sendMessage", ["chat_id" => $chat_id, "text" => "Bir saniye, bakıyorum... ⏳"]);
        $sonuc = tc_sorgu($step, $answer, $SRC1, $SRC2, $H);
        dosya_gonder($TOKEN, $chat_id, $sonuc, $step);
        return true;
    }

    @unlink($state_file);
    return false;
}


// ─────────────────────────────
// Ana döngü
// ─────────────────────────────

$offset = 0;
echo "Bot ayakta 📚\n";

while (true) {
    $res = api($TOKEN, "getUpdates", ["offset" => $offset, "timeout" => 30]);
    if (!($res["ok"] ?? false)) {
        sleep(3);
        continue;
    }

    foreach ($res["result"] as $u) {
        $offset = $u["update_id"] + 1;

        // Callback
        if (isset($u["callback_query"])) {
            callback_isle($u["callback_query"], $TOKEN, $CHANNEL, $SRC1, $SRC2, $HEADERS_GET, ana_menu());
            continue;
        }

        // Mesaj
        if (isset($u["message"])) {
            $m = $u["message"];
            $text = $m["text"] ?? "";
            $uid = $m["from"]["id"] ?? 0;
            $chat_id = $m["chat"]["id"] ?? 0;

            if ($text === "/start" || $text === "/help") {
                if (!uye_mi($TOKEN, $CHANNEL, $uid)) {
                    api($TOKEN, "sendMessage", [
                        "chat_id" => $chat_id,
                        "text" => "Kapıyı çalmadan giremezsin dostum 🙂\n\n"
                            . "Önce kanala bir uğra, sonra buraya dön:\n"
                            . "👉 {$CHANNEL_LINK}\n\n"
                            . "Geldikten sonra tekrar /start yaz, gerisi kolay.",
                    ]);
                    continue;
                }
                $isim = $m["from"]["first_name"] ?? "dostum";
                api($TOKEN, "sendMessage", [
                    "chat_id" => $chat_id,
                    "text" => "Selam {$isim} 👋\n\n"
                        . "Sorgu botuna hoş geldin. Aşağıdaki butonlardan ne arayacağını seç, "
                        . "gerisini bana bırak. Sonuçları dosya olarak önüne bırakıyorum.\n\n"
                        . "Takıldığın yer olursa @kehanetsorgu'ya yaz.",
                    "reply_markup" => json_encode(ana_menu()),
                ]);
                continue;
            }

            // Adım işle
            adim_isle($m, $TOKEN, $SRC1, $SRC2, $HEADERS_GET, $SMS_BASE, $BINANCE_BASE, $GPT_BASE, $API_KEY, $DEVICE_ID, ana_menu());
        }
    }
}