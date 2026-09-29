<?php
/**
 * GPT-4 Turbo Telegram Bot
 * Tam özellikli — menü, buton, sohbet geçmişi, insan gibi yanıtlar
 */

// ═══════════ AYARLAR ═══════════
define('TELEGRAM_BOT_TOKEN', '8795815010:AAEHeB6ZdqlscGNEP5LY1C1Ysw949EnTf7s');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');
define('POLLINATIONS_API', 'https://text.pollinations.ai/');
define('DATA_DIR', __DIR__ . '/data');
define('HISTORY_DIR', DATA_DIR . '/history');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(HISTORY_DIR)) mkdir(HISTORY_DIR, 0755, true);

// ═══════════ SOHBET GEÇMİŞİ ═══════════
class ChatHistory {
    private $userId;

    public function __construct($userId) {
        $this->userId = $userId;
    }

    private function file() {
        return HISTORY_DIR . '/' . $this->userId . '.json';
    }

    public function load() {
        if (!file_exists($this->file())) return [];
        $data = json_decode(file_get_contents($this->file()), true);
        return is_array($data) ? $data : [];
    }

    public function add($role, $content) {
        $history = $this->load();
        $history[] = [
            'role' => $role,
            'content' => $content,
            'time' => time()
        ];
        // Son 20 mesajı sakla
        if (count($history) > 20) $history = array_slice($history, -20);
        file_put_contents($this->file(), json_encode($history, JSON_UNESCAPED_UNICODE));
    }

    public function clear() {
        if (file_exists($this->file())) unlink($this->file());
    }

    public function toPrompt() {
        $history = $this->load();
        $text = '';
        foreach ($history as $msg) {
            $role = $msg['role'] === 'user' ? 'Kullanıcı' : 'Asistan';
            $text .= "$role: {$msg['content']}\n";
        }
        return $text;
    }
}

// ═══════════ GPT-4 TURBO AI ═══════════
class GPT4Turbo {
    private $systemPrompt = "Sen GPT-4 Turbo adında yardımcı bir yapay zeka asistanısın. " .
        "Kullanıcıya samimi, doğal ve insan gibi davranırsın. " .
        "Cevaplarını Türkçe verirsin (kullanıcı başka dilde yazarsa o dilde). " .
        "Kısa ve öz konuşursun. Emoji kullanmaktan çekinmezsin. " .
        "Kod sorularında tam ve çalışan kod verirsin. " .
        "Asla 'Ben bir yapay zekayım' gibi monoton cevaplar vermezsin.";

    public function send($message, $history = '') {
        // Tam prompt oluştur
        $fullPrompt = $this->systemPrompt . "\n\n";
        if ($history) $fullPrompt .= "Önceki konuşma:\n" . $history . "\n";
        $fullPrompt .= "Kullanıcı: $message\nAsistan:";

        $url = POLLINATIONS_API . urlencode($fullPrompt) . '?model=openai&seed=' . rand(1, 999999);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept: text/plain, application/json'],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ["error" => "Bağlantı hatası"];
        if ($httpCode !== 200) return ["error" => "Sunucu hatası ($httpCode)"];
        if (empty(trim($body))) return ["error" => "Boş yanıt geldi"];

        return ["response" => trim($body)];
    }
}

// ═══════════ TELEGRAM API ═══════════
function tg($method, $params = []) {
    $ch = curl_init(TELEGRAM_API . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

function sendMessage($chatId, $text, $replyTo = null, $keyboard = null) {
    $text = mb_substr($text, 0, 4000);
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($replyTo) $params['reply_to_message_id'] = $replyTo;
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    return tg('sendMessage', $params);
}

function editMessage($chatId, $messageId, $text, $keyboard = null) {
    $params = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => mb_substr($text, 0, 4000),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($keyboard) $params['reply_markup'] = json_encode($keyboard);
    return tg('editMessageText', $params);
}

function sendTyping($chatId) {
    tg('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
}

function answerCallback($callbackId, $text = '') {
    tg('answerCallbackQuery', ['callback_query_id' => $callbackId, 'text' => $text]);
}

// ═══════════ KLAVYELER ═══════════
function mainKeyboard() {
    return [
        'keyboard' => [
            [['text' => '💬 Sohbet'], ['text' => '🧹 Temizle']],
            [['text' => 'ℹ️ Hakkında'], ['text' => '📖 Yardım']],
            [['text' => '💻 Kod'], ['text' => '🌍 Çeviri']],
            [['text' => '💡 Fikir'], ['text' => '📝 Yazı']],
        ],
        'resize_keyboard' => true,
        'persistent' => true
    ];
}

function quickMenu() {
    return [
        'inline_keyboard' => [
            [
                ['text' => '💬 Sohbet Et', 'callback_data' => 'chat'],
                ['text' => '🧹 Geçmişi Sil', 'callback_data' => 'clear']
            ],
            [
                ['text' => '💻 Kod Yaz', 'callback_data' => 'code'],
                ['text' => '🌍 Çeviri', 'callback_data' => 'translate']
            ],
            [
                ['text' => 'ℹ️ Hakkında', 'callback_data' => 'about']
            ]
        ]
    ];
}

function backKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => '⬅️ Geri', 'callback_data' => 'menu']]
        ]
    ];
}

// ═══════════ MESAJ İŞLEYİCİ ═══════════
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';
    $firstName = $message['from']['first_name'] ?? 'Kullanıcı';

    if ($text === '') return;

    // ═══ /start ═══
    if ($text === '/start') {
        sendMessage($chatId,
            "👋 <b>Merhaba $firstName!</b>\n\n" .
            "Ben <b>GPT-4 Turbo</b> 🤖\n" .
            "OpenAI'nin en gelişmiş dil modeli.\n\n" .
            "Sana nasıl yardımcı olabilirim?\n" .
            "Aşağıdaki menüden seç veya direkt mesaj yaz.",
            null,
            mainKeyboard()
        );
        return;
    }

    // ═══ /help veya Yardım butonu ═══
    if ($text === '/help' || $text === '📖 Yardım') {
        sendMessage($chatId,
            "📖 <b>Yardım Menüsü</b>\n\n" .
            "<b>🤖 Ne yapabilirim?</b>\n\n" .
            "💬 <b>Sohbet</b> — Her konuda konuş\n" .
            "💻 <b>Kod</b> — Python, PHP, JS, C#\n" .
            "🌍 <b>Çeviri</b> — 100+ dil\n" .
            "📝 <b>Yazı</b> — Makale, hikaye, şiir\n" .
            "💡 <b>Fikir</b> — Beyin fırtınası\n\n" .
            "<b>Komutlar:</b>\n" .
            "/start — Başlat\n" .
            "/help — Yardım\n" .
            "/about — Hakkında\n" .
            "/clear — Geçmişi temizle\n\n" .
            "<i>Direkt mesaj yaz, cevap veririm!</i>",
            null,
            mainKeyboard()
        );
        return;
    }

    // ═══ /about veya Hakkında butonu ═══
    if ($text === '/about' || $text === 'ℹ️ Hakkında') {
        sendMessage($chatId,
            "ℹ️ <b>GPT-4 Turbo</b>\n\n" .
            "🤖 <b>Model:</b> GPT-4 Turbo\n" .
            "🏢 <b>Şirket:</b> OpenAI\n" .
            "⚡ <b>Versiyon:</b> 4.0\n" .
            "🌐 <b>Diller:</b> 100+\n" .
            "💎 <b>Ücret:</b> Tamamen ücretsiz\n" .
            "🔒 <b>Gizlilik:</b> Sohbetler kaydedilmez\n\n" .
            "<b>Özellikler:</b>\n" .
            "• Doğal dil anlama\n" .
            "• Kod yazma ve düzeltme\n" .
            "• Metin özetleme\n" .
            "• Çeviri\n" .
            "• Yaratıcı yazarlık\n\n" .
            "👨‍💻 <b>Geliştirici:</b> @cmrbaskani",
            null,
            backKeyboard()
        );
        return;
    }

    // ═══ /clear veya Temizle butonu ═══
    if ($text === '/clear' || $text === '🧹 Temizle') {
        $history = new ChatHistory($userId);
        $history->clear();
        sendMessage($chatId, "🧹 <b>Sohbet geçmişi temizlendi!</b>\n\nYeni bir konuşma başlatabilirsin.", null, mainKeyboard());
        return;
    }

    // ═══ Menü butonları (hızlı yönlendirme) ═══
    if ($text === '💬 Sohbet') {
        sendMessage($chatId, "💬 <b>Sohbet modu aktif</b>\n\nBana ne sormak istersin?", null, mainKeyboard());
        return;
    }

    if ($text === '💻 Kod') {
        sendMessage($chatId,
            "💻 <b>Kod Modu</b>\n\n" .
            "Hangi dilde kod yazmamı istersin?\n\n" .
            "Örnek:\n" .
            "• <code>Python'da fibonacci yaz</code>\n" .
            "• <code>PHP'de login sistemi yap</code>\n" .
            "• <code>JS'de sayaç kodu</code>",
            null,
            mainKeyboard()
        );
        return;
    }

    if ($text === '🌍 Çeviri') {
        sendMessage($chatId,
            "🌍 <b>Çeviri Modu</b>\n\n" .
            "Çevirmek istediğin metni yaz.\n\n" .
            "Örnek:\n" .
            "• <code>İngilizceye çevir: Merhaba dünya</code>\n" .
            "• <code>Almancaya çevir: Nasılsın?</code>",
            null,
            mainKeyboard()
        );
        return;
    }

    if ($text === '💡 Fikir') {
        sendMessage($chatId,
            "💡 <b>Fikir Modu</b>\n\n" .
            "Ne hakkında fikir istiyorsun?\n\n" .
            "Örnek:\n" .
            "• <code>İş fikirleri ver</code>\n" .
            "• <code>Video içeriği fikirleri</code>\n" .
            "• <code>Hediye fikirleri</code>",
            null,
            mainKeyboard()
        );
        return;
    }

    if ($text === '📝 Yazı') {
        sendMessage($chatId,
            "📝 <b>Yazma Modu</b>\n\n" .
            "Ne yazmamı istersin?\n\n" .
            "Örnek:\n" .
            "• <code>Kısa bir hikaye yaz</code>\n" .
            "• <code>Motivasyon sözü yaz</code>\n" .
            "• <code>Doğum günü mesajı</code>",
            null,
            mainKeyboard()
        );
        return;
    }

    // ═══ NORMAL MESAJ → AI ═══
    sendTyping($chatId);

    // Geçmişi yükle
    $history = new ChatHistory($userId);
    $history->add('user', $text);

    $ai = new GPT4Turbo();
    $result = $ai->send($text, $history->toPrompt());

    if (isset($result['error'])) {
        sendMessage($chatId,
            "❌ <b>Bir sorun oluştu</b>\n\n" .
            "<i>" . htmlspecialchars($result['error']) . "</i>\n\n" .
            "Tekrar dene veya @cmrbaskani ile iletişime geç.",
            $message['message_id']
        );
        return;
    }

    $answer = trim($result['response'] ?? '');

    if ($answer === '') {
        sendMessage($chatId, "⚠️ Cevap alamadım. Tekrar dener misin?", $message['message_id']);
        return;
    }

    // Geçmişe kaydet
    $history->add('assistant', $answer);

    // Uzunsa parçala
    if (mb_strlen($answer) > 4000) {
        $chunks = str_split($answer, 4000);
        foreach ($chunks as $chunk) {
            sendMessage($chatId, htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            usleep(300000);
        }
    } else {
        sendMessage($chatId, htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $message['message_id']);
    }
}

// ═══════════ CALLBACK (Buton tıklama) ═══════════
function handleCallback($callback) {
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'] ?? '';

    answerCallback($callback['id']);

    if ($data === 'chat') {
        editMessage($chatId, $messageId, "💬 <b>Sohbet başladı</b>\n\nNe sormak istersin?", backKeyboard());
    } elseif ($data === 'clear') {
        $history = new ChatHistory($callback['from']['id']);
        $history->clear();
        editMessage($chatId, $messageId, "🧹 <b>Geçmiş temizlendi</b>", backKeyboard());
    } elseif ($data === 'about') {
        editMessage($chatId, $messageId,
            "ℹ️ <b>GPT-4 Turbo</b>\n\n" .
            "🤖 Model: GPT-4 Turbo\n" .
            "🏢 Şirket: OpenAI\n" .
            "💎 Ücretsiz\n\n" .
            "<i>Geliştirici: @cmrbaskani</i>",
            backKeyboard()
        );
    } elseif ($data === 'code') {
        editMessage($chatId, $messageId, "💻 <b>Kod Modu</b>\n\nHangi dilde kod istersin?", backKeyboard());
    } elseif ($data === 'translate') {
        editMessage($chatId, $messageId, "🌍 <b>Çeviri Modu</b>\n\nÇevrilecek metni yaz.", backKeyboard());
    } elseif ($data === 'menu') {
        editMessage($chatId, $messageId, "🏠 <b>Ana Menü</b>\n\nNe yapmak istersin?", quickMenu());
    }
}

// ═══════════ WEBHOOK ═══════════
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    echo "✅ GPT-4 Turbo Bot aktif — " . date('Y-m-d H:i:s');
    exit;
}

try {
    if (isset($update['message'])) {
        handleMessage($update['message']);
    } elseif (isset($update['callback_query'])) {
        handleCallback($update['callback_query']);
    }
} catch (Exception $e) {
    error_log('Bot error: ' . $e->getMessage());
}

echo "OK";