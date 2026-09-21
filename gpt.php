<?php
// gpt.php - Web endpoint (Chatex.ai AI proxy)
// Telegram: @cmrbaskani
// Kullanım: https://ucretsizservicetr.onrender.com/gpt.php?q=merhaba

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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
                "parts" => [
                    ["type" => "text", "text" => $message]
                ],
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
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $this->cookie_file,
            CURLOPT_COOKIEFILE => $this->cookie_file,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "User-Agent: ai/1.0",
                "Origin: https://chat.chatex.ai",
                "Referer: https://chat.chatex.ai/",
                "Accept: text/event-stream"
            ],
        ]);

        $full_response = "";
        $usage = null;

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

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            return ["error" => "cURL: " . $curl_error];
        }
        if ($http_code !== 200) {
            return ["error" => "HTTP " . $http_code];
        }

        return [
            "response" => $full_response,
            "usage" => $usage
        ];
    }

    public function __destruct() {
        if (file_exists($this->cookie_file)) {
            @unlink($this->cookie_file);
        }
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
        "error" => "q parametresi gerekli (örn: ?q=merhaba)",
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$tool = new AI();
$result = $tool->send(trim($q));

if (isset($result['error'])) {
    http_response_code(500);
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