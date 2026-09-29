<?php
/**
 * Gemini AI Telegram Bot
 * Pollinations.ai ile çalışır — API key gerekmez, rate limit yüksek
 */

// ═══════════ AYARLAR ═══════════
define('TELEGRAM_BOT_TOKEN', '8795815010:AAEHeB6ZdqlscGNEP5LY1C1Ysw949EnTf7s');
define('TELEGRAM_API', 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/');
define('POLLINATIONS_API', 'https://text.pollinations.ai/');

// ═══════════ AI SINIFI ═══════════
class GeminiAI {
    public function send($message) {
        // Pollinations.ai — API key gerektirmez
        $url = POLLINATIONS_API . urlencode($message);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/plain, application/json',
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) return ["error" => "cURL: $err"];
        if ($httpCode !== 200) return ["error" => "HTTP $httpCode"];
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

function sendMessage($chatId, $text, $replyTo = null) {
    $text = mb_substr($text, 0, 4000);
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($replyTo) $params['reply_to_message_id'] = $replyTo;
    return tg('sendMessage', $params);
}

function sendTyping($chatId) {
    tg('sendChatAction', ['chat_id' => $chatId, 'action' => 'typing']);
}

// ═══════════ MESAJ İŞLEME ═══════════
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $firstName = $message['from']['first_name'] ?? 'Kullanıcı';

    if ($text === '') return;

    // ═══ /start ═══
    if ($text === '/start') {
        sendMessage($chatId,
            "👋 <b>Hoş geldin $firstName!</b>\n\n" .
            "Ben <b>Gemini</b> — Google'ın yapay zeka asistanıyım. 🤖✨\n\n" .
            "Sana şu konularda yardımcı olabilirim:\n\n" .
            "📝 <b>Yazma</b> — makale, hikaye, e-posta\n" .
            "💻 <b>Kod</b> — Python, PHP, JavaScript\n" .
            "🌍 <b>Çeviri</b> — 100+ dil\n" .
            "📚 <b>Bilgi</b> — tarih, bilim, matematik\n" .
            "💡 <b>Fikir</b> — beyin fırtınası, öneri\n\n" .
            "<i>Hadi başlayalım! Bana bir şey yaz.</i>",
            $message['message_id']
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
            "/about — Hakkımda\n\n" .
            "<b>Nasıl kullanılır?</b>\n" .
            "Direkt mesaj yaz, cevap veririm.\n\n" .
            "<b>Örnek:</b>\n" .
            "• \"Python'da liste nasıl ters çevrilir?\"\n" .
            "• \"İstanbul'da gezilecek yerler\"\n" .
            "• \"Bana motivasyon sözü yaz\"\n\n" .
            "Sorun için: @cmrbaskani",
            $message['message_id']
        );
        return;
    }

    // ═══ /about ═══
    if ($text === '/about') {
        sendMessage($chatId,
            "ℹ️ <b>Gemini Hakkında</b>\n\n" .
            "🤖 <b>Model:</b> Gemini\n" .
            "🌐 <b>Diller:</b> 100+ dil desteği\n" .
            "💎 <b>Ücret:</b> Tamamen ücretsiz\n" .
            "⚡ <b>Geliştirici:</b> @cmrbaskani",
            $message['message_id']
        );
        return;
    }

    // ═══ NORMAL MESAJ → AI'ya sor ═══
    sendTyping($chatId);

    $ai = new GeminiAI();
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

    // Cevabı gönder
    sendMessage($chatId, htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $message['message_id']);
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
    }
} catch (Exception $e) {
    error_log('Bot error: ' . $e->getMessage());
}

echo "OK";