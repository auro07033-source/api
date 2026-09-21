<?php
// gpt.php - Chatex.ai AI proxy - Key + Rate limit + 429 retry + cache
require_once __DIR__ . '/api_guard.php';
ApiGuard::checkKey();
ApiGuard::checkRate();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ─── AYARLAR ───
define('CHATEX_CACHE_DIR', sys_get_temp_dir() . '/chatex_cache');
define('CHATEX_CACHE_TTL', 120);   // 2 dakika cache
define('CHATEX_DELAY_FILE', sys_get_temp_dir() . '/chatex_last_request.txt');
define('CHATEX_MIN_INTERVAL', 2);  // istekler arası min 2 saniye
define('CHATEX_MAX_RETRY', 3);     // 429'da max 3 deneme

class AI {
    private $base_url = "https://chat.chatex.ai";
    private $chat_id;
    private $cookie_file;

    public function __construct() {
        $this->chat_id = $this->uuid();
        $this->cookie_file = sys_get_temp_dir() . '/chatex_cookies_' . md5($this->chat_id) . '.txt';
        if (!is_dir(CHATEX_CACHE_DIR)) @mkdir(CHATEX_CACHE_DIR, 0777, true);
    }

    private function uuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    // ─── CACHE ───
    private function cacheGet($key) {
        $file = CHATEX_CACHE_DIR . '/' . md5($key) . '.json';
        if (!file_exists($file)) return null;
        if ((time() - filemtime($file)) > CHATEX_CACHE_TTL) {
            @unlink($file);
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private function cacheSet($key, $value) {
        $file = CHATEX_CACHE_DIR . '/' . md5($key) . '.json';
        @file_put_contents($file, json_encode($value), LOCK_EX);
    }

    // ─── GLOBAL GECİKME ───
    private function throttle() {
        if (!file_exists(CHATEX_DELAY_FILE)) return;
        $last = (int)file_get_contents(CHATEX_DELAY_FILE);
        $diff = time() - $last;
        if ($diff < CHATEX_MIN_INTERVAL) {
            sleep(CHATEX_MIN_INTERVAL - $diff);
        }
    }

    private function markRequest() {
        @file_put_contents(CHATEX_DELAY_FILE, time(), LOCK_EX);
    }

    // ─── TEK İSTEK ───
    private function doRequest($message) {
        $this->throttle();

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
            CURLOPT_HEADER => true,
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

        $full_response = "";
        $usage = null;
        $header_size = 0;

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$header_size) {
            $header_size += strlen($header);
            return strlen($header);
        });

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$full_response, &$usage) {
            foreach (explode("\n", $data) as $line) {
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
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $this->markRequest();

        if ($curl_errno) {
            return ["error" => "cURL #$curl_errno: $curl_error", "http_code" => 0];
        }

        if ($http_code === 429) {
            return ["error" => "Chatex rate limit (429)", "http_code" => 429];
        }

        if ($http_code !== 200) {
            $body = substr($raw, $header_size);
            return ["error" => "Chatex HTTP $http_code - " . substr($body, 0, 300), "http_code" => $http_code];
        }

        if (empty($full_response)) {
            return ["error" => "Chatex bos yanit", "http_code" => 200];
        }

        return ["response" => $full_response, "usage" => $usage];
    }

    // ─── RETRY'Lİ İSTEK ───
    public function send($message) {
        // Cache kontrol
        $cacheKey = "chatex_" . md5($message);
        $cached = $this->cacheGet($cacheKey);
        if ($cached && isset($cached['response'])) {
            $cached['cached'] = true;
            return $cached;
        }

        $lastError = null;

        for ($i = 1; $i <= CHATEX_MAX_RETRY; $i++) {
            $result = $this->doRequest($message);

            if (!isset($result['error'])) {
                // Başarılı → cache'e kaydet
                $this->cacheSet($cacheKey, $result);
                return $result;
            }

            $lastError = $result;

            // 429 ise bekle ve tekrar dene
            if (isset($result['http_code']) && $result['http_code'] === 429) {
                $wait = 3 * $i; // 3, 6, 9 saniye
                sleep($wait);
                continue;
            }

            // Diğer hatalarda direkt dön
            break;
        }

        return $lastError ?: ["error" => "Bilinmeyen hata"];
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
        "error" => "q parametresi gerekli",
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$tool = new AI();
$result = $tool->send(trim($q));

if (isset($result['error'])) {
    http_response_code(200);
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
        "cached" => $result['cached'] ?? false,
        "telegram" => "@cmrbaskani",
        "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}