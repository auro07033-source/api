<?php
/**
 * Gemini AI Telegram Bot
 * Chatex.ai chat endpoint + Gemini AI kimliği
 */

// ═══════════ AYARLAR ═══════════
define('TELEGRAM_BOT_TOKEN', '8795815010:AAEHeB6ZdqlscGNEP5LY1C1Ysw949EnTf7s');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');
define('CHATEX_BASE', 'https://chat.chatex.ai');
define('CHATEX_MODEL', 'chatex/auto');
define('BOT_NAME', 'ɢᴇᴍɪɴɪ ᴀɪ');
define('BOT_USERNAME', '@sonsuzdusuncebot');

// ═══════════ CHATEX AI SINIFI ═══════════
class ChatexTool {
    private $session;
    private $chatId;
    private $cookieFile;

    public function __construct() {
        $this->chatId = $this->uuid4();
        $this->cookieFile = sys_get_temp_dir() . '/chatex_cookies_' . md5($this->chatId) . '.txt';
    }

    private function uuid4() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function send($message) {
        $payload = [
            "id" => $this->chatId,
            "message" => [
                "role" => "user",
                "parts" => [["type" => "text", "text" => $message]],
                "id" => $this->uuid4()
            ],
            "selectedChatModel" => CHATEX_MODEL,
            "selectedVisibilityType" => "private",
            "webSearchEnabled" => false,
            "imageGenerationEnabled" => false,
            "isExistingChat" => false
        ];

        $ch = curl_init(CHATEX_BASE . '/api/chat');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: text/event-stream',
                'User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
                'Origin: https://chat.chatex.ai',
                'Referer: https://chat.chatex.ai/'
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ["error" => "cURL: $err"];
        if ($httpCode !== 200) return ["error" => "HTTP $httpCode", "raw" => substr($body, 0, 300)];

        $fullResponse = "";
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === "" || strpos($line, "data: ") !== 0) continue;
            $data = substr($line, 6);
            if ($data === "[DONE]") break;
            $event = json_decode($data, true);
            if (!is_array($event)) continue;
            if (isset($event['type']) && $event['type'] === 'text-delta') {
                $fullResponse .= $event['delta'] ?? '';
            }
        }

        return ["response" => trim($fullResponse)];
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

function sendTyping($chatId) {
    tg('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
}

function editMessage($chatId, $messageId, $text) {
    return tg('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => mb_substr($text, 0, 4000),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ]);
}

// ═══════════ MESAJ İŞLEME ═══════════
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $firstName = $message['from']['first_name'] ?? 'Kullanıcı';

    if ($text === '') return;

    // ═══ /start ═══
    if ($text === '/start') {
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 Sohbete Başla', 'callback_data' => 'start_chat']],
                [['text' => 'ℹ️ Hakkında', 'callback_data' => 'about']],
            ]
        ];
        sendMessage($chatId,
            "👋 <b>Merhaba $firstName!</b>\n\n" .
            "Ben <b>Gemini</b> — Google'ın yapay zeka asistanıyım. 🤖✨\n\n" .
            "Sana şu konularda yardımcı olabilirim:\n\n" .
            "📝 <b>Yazma</b> — makale, hikaye, e-posta\n" .
            "💻 <b>Kod</b> — Python, PHP, JavaScript, C#\n" .
            "🌍 <b>Çeviri</b> — 100+ dil\n" .
            "📚 <b>Bilgi</b> — tarih, bilim, matematik\n" .
            "💡 <b>Fikir</b> — beyin fırtınası, öneri\n" .
            "🎨 <b>Yaratıcılık</b> — şiir, şarkı sözü\n\n" .
            "<i>Hadi başlayalım! Bana bir şey yaz.</i>",
            $message['message_id'],
            $keyboard
        );
        return;
    }

    // ═══ /help ═══
    if ($text === '/help') {
        sendMessage($chatId,
            "📖 <b>Gemini Bot — Yardım</b>\n\n" .
            "<b>Komutlar:</b>\n" .
            "/start — Botu başlat\n" .
            "/help — Bu menü\n" .
            "/about — Hakkımda\n" .
            "/new — Yeni sohbet\n" .
            "/clear — Sohbeti temizle\n\n" .
            "<b>Nasıl kullanılır?</b>\n" .
            "Direkt mesaj yaz, cevap veririm.\n\n" .
            "<b>Örnek sorular:</b>\n" .
            "• \"Python'da liste nasıl ters çevrilir?\"\n" .
            "• \"İstanbul'da gezilecek yerler\"\n" .
            "• \"Bana motivasyon sözü yaz\"\n" .
            "• \"İngilizceye çevir: Merhaba dünya\"\n\n" .
            "Sorun için: @cmrbaskani",
            $message['message_id']
        );
        return;
    }

    // ═══ /about ═══
    if ($text === '/about') {
        sendMessage($chatId,
            "ℹ️ <b>Gemini Hakkında</b>\n\n" .
            "🤖 <b>Model:</b> Gemini (Google AI)\n" .
            "⚡ <b>Versiyon:</b> 1.5 Pro\n" .
            "🌐 <b>Diller:</b> 100+ dil desteği\n" .
            "🔒 <b>Gizlilik:</b> Sohbetler kaydedilmez\n" .
            "💎 <b>Ücret:</b> Tamamen ücretsiz\n\n" .
            "<b>Neler yapabilirim?</b>\n" .
            "• Doğal dil anlama\n" .
            "• Kod yazma ve düzeltme\n" .
            "• Metin özetleme\n" .
            "• Çeviri\n" .
            "• Yaratıcı yazarlık\n" .
            "• Soru-cevap\n\n" .
            "<i>Geliştirici: @cmrbaskani</i>",
            $message['message_id']
        );
        return;
    }

    // ═══ /new ═══
    if ($text === '/new') {
        sendMessage($chatId,
            "🆕 <b>Yeni sohbet başlatıldı!</b>\n\n" .
            "Şimdi bana ne sormak istersin?",
            $message['message_id']
        );
        return;
    }

    // ═══ /clear ═══
    if ($text === '/clear') {
        sendMessage($chatId, "🗑️ Sohbet temizlendi. Yeni bir konuşma başlatabilirsin.", $message['message_id']);
        return;
    }

    // ═══ NORMAL MESAJ → AI'ya sor ═══
    sendTyping($chatId);

    $ai = new ChatexTool();
    $result = $ai->send($text);

    if (isset($result['error'])) {
        sendMessage($chatId,
            "❌ <b>Hata oluştu</b>\n\n" .
            "Sebep: " . htmlspecialchars($result['error']) . "\n\n" .
            "Tekrar dene veya @cmrbaskani ile iletişime geç.",
            $message['message_id']
        );
        return;
    }

    $answer = $result['response'] ?? '';

    if ($answer === '') {
        sendMessage($chatId, "⚠️ Boş yanıt geldi. Lütfen tekrar dene.", $message['message_id']);
        return;
    }

    // Cevabı Gemini imzası ile gönder
    sendMessage($chatId, htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $message['message_id']);
}

// ═══════════ CALLBACK QUERY (buton tıklama) ═══════════
function handleCallback($callback) {
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'] ?? '';

    tg('answerCallbackQuery', ['callback_query_id' => $callback['id']]);

    if ($data === 'start_chat') {
        editMessage($chatId, $messageId,
            "💬 <b>Sohbet başlatıldı!</b>\n\n" .
            "Şimdi bana bir mesaj yaz, cevap vereyim. 🤖"
        );
    } elseif ($data === 'about') {
        editMessage($chatId, $messageId,
            "ℹ️ <b>Gemini AI Bot</b>\n\n" .
            "🤖 Model: Gemini 1.5 Pro\n" .
            "⚡ Geliştirici: @cmrbaskani\n" .
            "🌐 Dil: 100+\n" .
            "💎 Ücretsiz"
        );
    }
}

// ═══════════ WEBHOOK ═══════════
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    echo "✅ Gemini Bot aktif — " . date('Y-m-d H:i:s');
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