<?php
/**
 * sorgubot.php — Telegram webhook bot
 * Butonlu sorgu, günlük 3 hak, referansla +3 hak
 * İletişim: @cmrbaskani
 */

// ═══════════ AYARLAR ═══════════
define('BOT_TOKEN',   '8950660109:AAFB8lKqUYFuGJMIlNOQkjVMqoJe1_GEXm4');
define('CHANNEL',     '@cmrbaskan');
define('CHANNEL_URL', 'https://t.me/cmrbaskan');
define('API_BASE',    'https://apiv2.ajaxsystems.fun');
define('DATA_DIR',    __DIR__ . '/bot_data');
define('USERS_FILE',  DATA_DIR . '/users.json');
define('BOT_USERNAME','uzmansorgupanelbot'); // @ olmadan, örn: sorgubot

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);

// ═══════════ TELEGRAM API ═══════════
function tg($method, $data = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

function send($chat_id, $text, $kb = null, $parse = 'HTML') {
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => $parse,
    ];
    if ($kb) $data['reply_markup'] = json_encode($kb);
    return tg('sendMessage', $data);
}

function edit($chat_id, $msg_id, $text, $kb = null, $parse = 'HTML') {
    $data = [
        'chat_id'    => $chat_id,
        'message_id' => $msg_id,
        'text'       => $text,
        'parse_mode' => $parse,
    ];
    if ($kb) $data['reply_markup'] = json_encode($kb);
    return tg('editMessageText', $data);
}

function answer_cb($cb_id, $text = '') {
    return tg('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => $text]);
}

function send_doc($chat_id, $filename, $content) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendDocument';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'  => $chat_id,
            'document' => new CURLFile($filename),
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    file_put_contents($filename, $content);
    $r = curl_exec($ch);
    curl_close($ch);
    @unlink($filename);
    return json_decode($r, true);
}

// ═══════════ VERİ ═══════════
function load_users() {
    if (!file_exists(USERS_FILE)) return [];
    $j = json_decode(@file_get_contents(USERS_FILE), true);
    return is_array($j) ? $j : [];
}
function save_users($u) {
    file_put_contents(USERS_FILE, json_encode($u, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function get_user($uid) {
    $u = load_users();
    if (!isset($u[$uid])) {
        $u[$uid] = [
            'id'        => $uid,
            'username'  => '',
            'first_name'=> '',
            'kalan'     => 3,
            'son_gun'   => date('Y-m-d'),
            'ref_by'    => null,
            'ref_sayisi'=> 0,
            'ref_kazanc'=> 0,
            'toplam'    => 0,
            'created'   => time(),
        ];
        save_users($u);
    }
    // Günlük sıfırlama
    if ($u[$uid]['son_gun'] !== date('Y-m-d')) {
        $u[$uid]['kalan']   = 3;
        $u[$uid]['son_gun'] = date('Y-m-d');
        save_users($u);
    }
    return $u[$uid];
}

function update_user($uid, $fields) {
    $u = load_users();
    if (!isset($u[$uid])) return;
    $u[$uid] = array_merge($u[$uid], $fields);
    save_users($u);
}

function kalan_dus($uid) {
    $u = load_users();
    if (!isset($u[$uid])) return;
    $u[$uid]['kalan']  = max(0, $u[$uid]['kalan'] - 1);
    $u[$uid]['toplam'] = ($u[$uid]['toplam'] ?? 0) + 1;
    save_users($u);
}

function hak_ekle($uid, $adet) {
    $u = load_users();
    if (!isset($u[$uid])) return;
    $u[$uid]['kalan'] += $adet;
    save_users($u);
}

// ═══════════ KANAL KONTROL ═══════════
function uye_mi($uid) {
    $r = tg('getChatMember', ['chat_id' => CHANNEL, 'user_id' => $uid]);
    if (empty($r['ok'])) return false;
    return in_array($r['result']['status'] ?? '', ['member', 'administrator', 'creator']);
}

// ═══════════ MENÜLER ═══════════
function ana_menu() {
    return ['inline_keyboard' => [
        [
            ['text' => '👤 TC Sorgu',    'callback_data' => 'q|tc'],
            ['text' => '👤 TC Pro',      'callback_data' => 'q|tcpro'],
        ],
        [
            ['text' => '👤 Ad Soyad',    'callback_data' => 'q|adsoyad'],
            ['text' => '👤 Aile',        'callback_data' => 'q|aile'],
        ],
        [
            ['text' => '👤 Aile Pro',    'callback_data' => 'q|ailepro'],
            ['text' => '👤 Sülale',      'callback_data' => 'q|sulale'],
        ],
        [
            ['text' => '📱 TC → GSM',    'callback_data' => 'q|tcgsm'],
            ['text' => '📱 GSM → TC',    'callback_data' => 'q|gsmtc'],
        ],
        [
            ['text' => '🎓 E-Okul',      'callback_data' => 'q|eokul'],
        ],
        [
            ['text' => '🏠 Adres',       'callback_data' => 'q|adres'],
            ['text' => '🏠 Tapu',        'callback_data' => 'q|tapu'],
        ],
        [
            ['text' => '🏠 Ada Parsel',  'callback_data' => 'q|adaparsel'],
        ],
        [
            ['text' => '👤 Hesabım',     'callback_data' => 'hesap'],
            ['text' => '🔗 Referans',    'callback_data' => 'ref'],
        ],
    ]];
}

function iptal_menu() {
    return ['inline_keyboard' => [[
        ['text' => '❌ İptal', 'callback_data' => 'iptal'],
    ]]];
}

function evet_hayir_menu($tip) {
    return ['inline_keyboard' => [[
        ['text' => '✅ Sorgula', 'callback_data' => 'ok|' . $tip],
        ['text' => '❌ İptal',   'callback_data' => 'iptal'],
    ]]];
}

// ═══════════ SORGU TİPLERİ ═══════════
function sorgu_bilgi($tip) {
    $harita = [
        'tc'        => ['label' => 'TC Sorgu',    'alan' => 'tc',  'adim' => 'tc'],
        'tcpro'     => ['label' => 'TC Pro',      'alan' => 'tc',  'adim' => 'tc'],
        'aile'      => ['label' => 'Aile',        'alan' => 'tc',  'adim' => 'tc'],
        'ailepro'   => ['label' => 'Aile Pro',    'alan' => 'tc',  'adim' => 'tc'],
        'sulale'    => ['label' => 'Sülale',      'alan' => 'tc',  'adim' => 'tc'],
        'adres'     => ['label' => 'Adres',       'alan' => 'tc',  'adim' => 'tc'],
        'tapu'      => ['label' => 'Tapu',        'alan' => 'tc',  'adim' => 'tc'],
        'eokul'     => ['label' => 'E-Okul',      'alan' => 'tc',  'adim' => 'tc'],
        'tcgsm'     => ['label' => 'TC → GSM',    'alan' => 'tc',  'adim' => 'tc'],
        'gsmtc'     => ['label' => 'GSM → TC',    'alan' => 'gsm', 'adim' => 'gsm'],
        'adsoyad'   => ['label' => 'Ad Soyad',    'alan' => 'ad',  'adim' => 'ad'],
        'adaparsel' => ['label' => 'Ada Parsel',  'alan' => 'il',  'adim' => 'il'],
    ];
    return $harita[$tip] ?? null;
}

// ═══════════ WEBHOOK ═══════════
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) { http_response_code(200); exit; }

// ═══════════ MESAJLAR ═══════════
if (isset($update['message'])) {
    $m  = $update['message'];
    $cid = $m['chat']['id'];
    $uid = $m['from']['id'];

    // /start [ref]
    if (isset($m['text']) && preg_match('/^\/start(?:\s+(.+))?$/', $m['text'], $mt)) {
        $ref_id = $mt[1] ?? null;

        // Kayıt / güncelleme
        $u = get_user($uid);
        update_user($uid, [
            'username'   => $m['from']['username'] ?? '',
            'first_name' => $m['from']['first_name'] ?? '',
        ]);

        // Referans işleme (ilk kez)
        if ($ref_id && is_numeric($ref_id) && $ref_id != $uid && $u['ref_by'] === null) {
            update_user($uid, ['ref_by' => $ref_id]);
            hak_ekle($uid, 3);
            hak_ekle($ref_id, 3);

            $ru = load_users();
            $ref_sayisi = ($ru[$ref_id]['ref_sayisi'] ?? 0) + 1;
            $ref_kazanc = ($ru[$ref_id]['ref_kazanc'] ?? 0) + 3;
            update_user($ref_id, ['ref_sayisi' => $ref_sayisi, 'ref_kazanc' => $ref_kazanc]);

            send($ref_id, "🎉 <b>Yeni referans!</b>\n\nBir kişi senin davetinle katıldı.\n<b>+3 hak</b> kazandın.");
        }

        if (!uye_mi($uid)) {
            send($cid,
                "🚫 <b>Kanala katılman gerek</b>\n\n" .
                "📢 <a href=\"" . CHANNEL_URL . "\">Kanala Katıl</a>\n\n" .
                "Katıldıktan sonra <b>/start</b> tekrar yaz.",
                null, 'HTML'
            );
            exit;
        }

        $u   = get_user($uid);
        $ad  = $m['from']['username'] ? '@' . $m['from']['username'] : $m['from']['first_name'];

        $metin = "🍀 Merhaba <b>" . htmlspecialchars($ad) . "</b>\n\n" .
                 "📚 <b>Sorgu botuna hoş geldin.</b>\n\n" .
                 "🎁 Günlük <b>3 ücretsiz</b> sorgu hakkın var.\n" .
                 "🔗 Her referans ile <b>+3 hak</b> kazanırsın.\n\n" .
                 "👇 Aşağıdaki butonlardan sorgu tipini seç.\n\n" .
                 "<i>İletişim:</i> @cmrbaskani";

        send($cid, $metin, ana_menu(), 'HTML');
        exit;
    }

    // /ref
    if (isset($m['text']) && $m['text'] === '/ref') {
        ref_komut($cid, $uid);
        exit;
    }

    // /hesap
    if (isset($m['text']) && $m['text'] === '/hesap') {
        hesap_komut($cid, $uid);
        exit;
    }

    // Bekleyen adım (state)
    if (isset($m['text']) && $m['text'] !== '' && is_numeric($uid)) {
        $state = bekle_oku($uid);
        if ($state) {
            adim_isle($cid, $uid, $m['text'], $state);
            exit;
        }
    }
}

// ═══════════ CALLBACK ═══════════
if (isset($update['callback_query'])) {
    $c   = $update['callback_query'];
    $cid = $c['message']['chat']['id'];
    $mid = $c['message']['message_id'];
    $uid = $c['from']['id'];
    $d   = $c['data'];

    answer_cb($c['id']);

    // Kanal kontrolü
    if (!uye_mi($uid)) {
        send($cid, "🚫 Önce kanala katıl: " . CHANNEL_URL, null, 'HTML');
        exit;
    }

    // iptal
    if ($d === 'iptal') {
        bekle_sil($uid);
        edit($cid, $mid, "❌ İptal edildi.\n\n👇 Menüden tekrar seç.", ana_menu(), 'HTML');
        exit;
    }

    // hesap
    if ($d === 'hesap') {
        hesap_komut($cid, $uid, $mid);
        exit;
    }

    // referans
    if ($d === 'ref') {
        ref_komut($cid, $uid, $mid);
        exit;
    }

    // sorgu tipi seçimi
    if (strpos($d, 'q|') === 0) {
        $tip = substr($d, 2);
        $b   = sorgu_bilgi($tip);
        if (!$b) return;

        $u = get_user($uid);
        if ($u['kalan'] <= 0) {
            edit($cid, $mid,
                "⚠️ <b>Günlük hakkın doldu.</b>\n\n" .
                "🔗 Referans ile <b>+3 hak</b> kazanabilirsin.\n\n" .
                "Referans linkin için /ref yaz.",
                ana_menu(), 'HTML'
            );
            return;
        }

        // Hangi bilgi isteniyor
        $sorular = [
            'tc'  => '📝 TC gir (11 hane):',
            'gsm' => '📝 GSM gir (5xx...):',
            'ad'  => '📝 Ad gir:',
            'il'  => '📝 İl gir:',
        ];
        $alan = $b['alan'];

        bekle_yaz($uid, ['tip' => $tip, 'adim' => $b['adim']]);

        edit($cid, $mid, $sorular[$alan] . "\n\n<i>İptal için aşağıdaki butona bas.</i>", iptal_menu(), 'HTML');
        exit;
    }

    // ok| ile onaylanan sorgu
    if (strpos($d, 'ok|') === 0) {
        $tip = substr($d, 3);
        $state = bekle_oku($uid);
        if (!$state || $state['tip'] !== $tip) {
            edit($cid, $mid, "⚠️ Oturum süresi doldu, tekrar dene.", ana_menu(), 'HTML');
            return;
        }
        sorgu_calistir($cid, $uid, $mid, $state);
        exit;
    }
}

// ═══════════ STATE (dosya bazlı) ═══════════
function bekle_yaz($uid, $data) {
    $f = DATA_DIR . '/state_' . $uid . '.json';
    file_put_contents($f, json_encode($data), LOCK_EX);
}
function bekle_oku($uid) {
    $f = DATA_DIR . '/state_' . $uid . '.json';
    if (!file_exists($f)) return null;
    $j = json_decode(file_get_contents($f), true);
    return is_array($j) ? $j : null;
}
function bekle_sil($uid) {
    $f = DATA_DIR . '/state_' . $uid . '.json';
    if (file_exists($f)) @unlink($f);
}

// ═══════════ ADIM İŞLEME ═══════════
function adim_isle($cid, $uid, $text, $state) {
    $text = trim($text);
    $tip  = $state['tip'];
    $adim = $state['adim'];

    if ($adim === 'tc') {
        if (!ctype_digit($text) || strlen($text) != 11) {
            send($cid, "❌ Geçersiz TC. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['tc'] = $text;
        bekle_yaz($uid, $state);
        send($cid, "🔎 <b>" . htmlspecialchars($text) . "</b> sorgulanacak.\n\nOnaylıyor musun?", evet_hayir_menu($tip), 'HTML');
        return;
    }

    if ($adim === 'gsm') {
        $gsm = preg_replace('/\D/', '', $text);
        if (strlen($gsm) < 10) {
            send($cid, "❌ Geçersiz GSM. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['gsm'] = $gsm;
        bekle_yaz($uid, $state);
        send($cid, "🔎 <b>" . htmlspecialchars($gsm) . "</b> sorgulanacak.\n\nOnaylıyor musun?", evet_hayir_menu($tip), 'HTML');
        return;
    }

    if ($adim === 'ad') {
        if (mb_strlen($text) < 2) {
            send($cid, "❌ Geçersiz ad. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['ad'] = mb_strtoupper($text, 'UTF-8');
        bekle_yaz($uid, $state);
        send($cid, "📝 Soyad gir:", iptal_menu(), 'HTML');
        $state['adim'] = 'soyad';
        bekle_yaz($uid, $state);
        return;
    }

    if ($adim === 'soyad') {
        if (mb_strlen($text) < 2) {
            send($cid, "❌ Geçersiz soyad. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['soyad'] = mb_strtoupper($text, 'UTF-8');
        bekle_yaz($uid, $state);
        send($cid,
            "🔎 <b>" . htmlspecialchars($state['ad'] . ' ' . $state['soyad']) . "</b> sorgulanacak.\n\nOnaylıyor musun?",
            evet_hayir_menu($tip), 'HTML'
        );
        return;
    }

    if ($adim === 'il') {
        if (mb_strlen($text) < 2) {
            send($cid, "❌ Geçersiz il. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['il'] = mb_strtoupper($text, 'UTF-8');
        $state['adim'] = 'ilce';
        bekle_yaz($uid, $state);
        send($cid, "📝 İlçe gir:", iptal_menu(), 'HTML');
        return;
    }

    if ($adim === 'ilce') {
        if (mb_strlen($text) < 2) {
            send($cid, "❌ Geçersiz ilçe. Tekrar gir:", iptal_menu(), 'HTML');
            return;
        }
        $state['ilce'] = mb_strtoupper($text, 'UTF-8');
        bekle_yaz($uid, $state);
        send($cid,
            "🔎 <b>" . htmlspecialchars($state['il'] . ' / ' . $state['ilce']) . "</b> sorgulanacak.\n\nOnaylıyor musun?",
            evet_hayir_menu($tip), 'HTML'
        );
        return;
    }
}

// ═══════════ SORGU ÇALIŞTIR ═══════════
function sorgu_calistir($cid, $uid, $mid, $state) {
    $tip = $state['tip'];
    $u   = get_user($uid);
    if ($u['kalan'] <= 0) {
        edit($cid, $mid, "⚠️ Hakkın kalmadı. Referans topla: /ref", ana_menu(), 'HTML');
        return;
    }

    // URL kur
    $url = '';
    switch ($tip) {
        case 'tc':        $url = API_BASE . '/tc.php?tc='       . urlencode($state['tc']);        break;
        case 'tcpro':     $url = API_BASE . '/tcpro.php?tc='    . urlencode($state['tc']);        break;
        case 'aile':      $url = API_BASE . '/aile.php?tc='     . urlencode($state['tc']);        break;
        case 'ailepro':   $url = API_BASE . '/ailepro.php?tc='  . urlencode($state['tc']);        break;
        case 'sulale':    $url = API_BASE . '/sulale.php?tc='   . urlencode($state['tc']);        break;
        case 'adres':     $url = API_BASE . '/adres.php?tc='    . urlencode($state['tc']);        break;
        case 'tapu':      $url = API_BASE . '/tapu.php?tc='     . urlencode($state['tc']);        break;
        case 'eokul':     $url = API_BASE . '/eokul.php?tc='    . urlencode($state['tc']);        break;
        case 'tcgsm':     $url = API_BASE . '/tcgsm.php?tc='    . urlencode($state['tc']);        break;
        case 'gsmtc':     $url = API_BASE . '/gsmtc.php?gsm='   . urlencode($state['gsm']) . '&auth=fire'; break;
        case 'adsoyad':   $url = API_BASE . '/adsoyad.php?ad='  . urlencode($state['ad']) . '&soyad=' . urlencode($state['soyad']); break;
        case 'adaparsel': $url = API_BASE . '/adaparsel.php?il=' . urlencode($state['il']) . '&ilce=' . urlencode($state['ilce']); break;
    }

    edit($cid, $mid, "⏳ Sorgulanıyor...", null, 'HTML');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$body) {
        edit($cid, $mid, "❌ Sorgu başarısız oldu. Sonra tekrar dene.", ana_menu(), 'HTML');
        bekle_sil($uid);
        return;
    }

    $json = json_decode($body, true);
    if (!$json) {
        edit($cid, $mid, "❌ Geçersiz yanıt.", ana_menu(), 'HTML');
        bekle_sil($uid);
        return;
    }

    // Hak düş
    kalan_dus($uid);
    bekle_sil($uid);

    $u = get_user($uid);
    $kalan = $u['kalan'];

    // JSON dosya olarak gönder
    $filename = DATA_DIR . '/' . $tip . '_' . $uid . '_' . time() . '.json';
    file_put_contents($filename, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    // Telegram'a dosya gönder
    $ch = curl_init('https://api.telegram.org/bot' . BOT_TOKEN . '/sendDocument');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'   => $cid,
            'document'  => new CURLFile($filename),
            'caption'   => "✅ <b>Sorgu tamamlandı</b>\n\n" .
                           "📌 Tip: <b>" . htmlspecialchars($tip) . "</b>\n" .
                           "🎫 Kalan hak: <b>" . $kalan . "</b>\n\n" .
                           "📢 @cmrbaskani",
            'parse_mode'=> 'HTML',
        ],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
    @unlink($filename);

    edit($cid, $mid, "✅ Sorgu tamamlandı.\n\n🎫 Kalan hak: <b>$kalan</b>\n\n👇 Yeni sorgu için menü:", ana_menu(), 'HTML');
}

// ═══════════ KOMUTLAR ═══════════
function hesap_komut($cid, $uid, $mid = null) {
    $u = get_user($uid);
    $metin = "👤 <b>Hesabım</b>\n\n" .
             "🎫 Bugünkü kalan hak: <b>" . $u['kalan'] . "</b>\n" .
             "📊 Toplam sorgu: <b>" . ($u['toplam'] ?? 0) . "</b>\n" .
             "👥 Referans sayısı: <b>" . ($u['ref_sayisi'] ?? 0) . "</b>\n" .
             "🎁 Referanstan kazanılan: <b>" . ($u['ref_kazanc'] ?? 0) . "</b> hak\n\n" .
             "🔗 /ref ile davet linkini al.";
    if ($mid) edit($cid, $mid, $metin, ana_menu(), 'HTML');
    else       send($cid, $metin, ana_menu(), 'HTML');
}

function ref_komut($cid, $uid, $mid = null) {
    $link = 'https://t.me/' . BOT_USERNAME . '?start=' . $uid;
    $u = get_user($uid);
    $metin = "🔗 <b>Referans Sistemi</b>\n\n" .
             "Davet linkin:\n<code>" . $link . "</code>\n\n" .
             "🎁 Her katılan kişi için:\n" .
             "• Sen <b>+3 hak</b> kazanırsın\n" .
             "• O kişi <b>+3 hak</b> kazanır\n\n" .
             "👥 Şu ana kadar <b>" . ($u['ref_sayisi'] ?? 0) . "</b> kişi katıldı.\n" .
             "🎁 Toplam kazanç: <b>" . ($u['ref_kazanc'] ?? 0) . "</b> hak";
    if ($mid) edit($cid, $mid, $metin, ana_menu(), 'HTML');
    else       send($cid, $metin, ana_menu(), 'HTML');
}

// ═══════════ WEBHOOK KURULUM ═══════════
// Bir kez tarayıcıdan aç: sorgubot.php?kur=1
if (isset($_GET['kur'])) {
    $webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $r = tg('setWebhook', ['url' => $webhook_url]);
    header('Content-Type: application/json');
    echo json_encode(['webhook' => $webhook_url, 'result' => $r], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Webhook bilgisi
if (isset($_GET['bilgi'])) {
    header('Content-Type: application/json');
    echo json_encode(tg('getWebhookInfo'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

http_response_code(200);
echo 'OK';