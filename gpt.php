<?php
// gpt.php - Chatex.ai AI proxy - Key + Rate limit korumalı
require_once __DIR__ . '/api_guard.php';
ApiGuard::checkKey();
ApiGuard::checkRate();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

class AI {
    private $base_url = "https://chat.chatex.ai";
    private $chat_id;
    private $cookie_file;

    public function __construct() {
        $this->chat_id = $this->uuid();
        $this->cookie_file = sys_get_temp_dir() . '/chatex_cookies_' . md5($this->chat_id) . '.txt';
    }

    private function uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function send($message) {
        $payload = [
            "id" => $this->chat_id,
            "message" => [
                "role" => "user",
                "parts" => [["type" => "text", "text" => $message]],
                "id" => $this->uuid()
            ],
            "selectedChatModel" => "chatex/auto",
            "selectedVisibilityType" => "private",
            "webSearchEnabled" => false,
            "imageGenerationEnabled" => false,
            "isExistingChat" => false
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->base_url . "/api/chat",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,                // ← header'ı da al
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $this->cookie_file,
            CURLOPT_COOKIEFILE => $this->cookie_file,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36",
                "Origin: https://chat.chatex.ai",
                "Referer: https://chat.chatex.ai/",
                "Accept: text/event-stream"
            ],
        ]);

        // Streaming response
        $full_response = "";
        $usage = null;
        $header_size = 0;

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$header_size) {
            $header_size += strlen($header);
            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$usage) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                if (strpos($line, "data: ") === 0) {
                    $json_str = substr($line, 6);
                    if ($json_str === "[DONE]") continue;
                    $event = json_decode($json_str, true);
                    if (!$event) continue;
                    if (isset($event['type'])) {
                        if ($event['type'] === 'text-delta' && isset($event['delta'])) {
                            $full_response .= $event['delta'];
                        } elseif ($event['type'] === 'data-usage' && isset($event['data'])) {
                            $usage = $event['data'];
                        }
                    }
                }
            }
            return strlen($data);
        });

        $raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);

        if ($curl_errno) {
            return ["error" => "cURL #$curl_errno: $curl_error"];
        }

        if ($http_code !== 200) {
            // Header + body'den hata detayı çıkart
            $body = substr($raw, $header_size);
            $detay = substr($body, 0, 500);
            return ["error" => "Chatex HTTP $http_code - " . $detay];
        }

        if (empty($full_response)) {
            return ["error" => "Chatex bos yanit dondu. Model: chatex/auto"];
        }

        return ["response" => $full_response, "usage" => $usage];
    }

    public function __destruct() {
        if (file_exists($this->cookie_file)) @unlink($this->cookie_file);
    }
}

// ═══════════════════════════════════════════
// WEB ENDPOINT
// ═══════════════════════════════════════════

$q = $_GET['q'] ?? $_POST['q'] ?? '';

if (empty(trim($q))) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error" => "q parametresi gerekli (orn: ?q=merhaba&key=YOUR_KEY)",
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$tool = new AI();
$result = $tool->send(trim($q));

if (isset($result['error'])) {
    http_response_code(200); // hata olsa da 200 dön, frontend error mesajını görsün
    echo json_encode([
        "success" => false,
        "error" => $result['error'],
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        "success" => true,
        "response" => $result['response'],
        "usage" => $result['usage'],
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}