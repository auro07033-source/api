<?php
// ═══════════════════════════════════════════════════════════════
// api_guard.php - API Rate Limit + Key Koruması
// Telegram: @cmrbaskani
// Kullanım: require_once 'api_guard.php'; en üste ekle
// ═══════════════════════════════════════════════════════════════

class ApiGuard {
    // ─── AYARLAR ───
    private static $API_KEY = "cmrbaskani_2026_secret_key_xyz"; // API Key (değiştir)
    private static $RATE_DIR = null;
    
    // Rate limit: kaç saniyede kaç istek
    private static $RATE_LIMIT = 5;        // max istek
    private static $RATE_WINDOW = 60;      // saniye
    
    // Cihaz (fingerprint) bazlı limit
    private static $DEVICE_LIMIT = 10;     // aynı cihazdan max istek
    private static $DEVICE_WINDOW = 60;    // saniye
    
    // IP bazlı günlük limit
    private static $IP_DAILY_LIMIT = 200;  // günlük max istek
    private static $IP_BLOCK_TIME = 300;   // 5 dakika ban (limit aşılınca)
    
    // ─── BAŞLAT ───
    public static function init() {
        self::$RATE_DIR = sys_get_temp_dir() . '/api_guard';
        if (!is_dir(self::$RATE_DIR)) @mkdir(self::$RATE_DIR, 0777, true);
    }
    
    // ─── API KEY KONTROL ───
    public static function checkKey() {
        $key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key !== self::$API_KEY) {
            self::deny("Gecersiz API key", 401);
        }
    }
    
    // ─── RATE LIMIT KONTROL ───
    public static function checkRate() {
        $ip = self::getIP();
        $device = self::getDevice();
        
        // 1. IP ban kontrolü
        if (self::isBanned($ip)) {
            self::deny("Cok fazla istek. 5 dakika bekleyin.", 429);
        }
        
        // 2. IP rate limit (dakikalık)
        $ipCount = self::getCount("ip_" . md5($ip), self::$RATE_WINDOW);
        if ($ipCount >= self::$RATE_LIMIT) {
            self::banIP($ip);
            self::deny("IP rate limit asildi. 5 dakika bekleyin.", 429);
        }
        self::increment("ip_" . md5($ip));
        
        // 3. Cihaz (fingerprint) bazlı limit
        $devCount = self::getCount("dev_" . md5($device), self::$DEVICE_WINDOW);
        if ($devCount >= self::$DEVICE_LIMIT) {
            self::deny("Cihaz rate limit asildi. 1 dakika bekleyin.", 429);
        }
        self::increment("dev_" . md5($device));
        
        // 4. IP günlük limit
        $dailyKey = "daily_" . md5($ip) . "_" . date('Y-m-d');
        $dailyCount = self::getCount($dailyKey, 86400);
        if ($dailyCount >= self::$IP_DAILY_LIMIT) {
            self::deny("Gunluk limit asildi. Yarın tekrar deneyin.", 429);
        }
        self::increment($dailyKey);
    }
    
    // ─── YARDIMCI: IP AL ───
    private static function getIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = $_SERVER[$h];
                if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // ─── YARDIMCI: CİHAZ FINGERPRINT ───
    // IP değişse bile aynı cihazı tanır
    private static function getDevice() {
        $parts = [];
        // User-Agent
        $parts[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Accept-Language
        $parts[] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        // Accept-Encoding
        $parts[] = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        // Client Hints (Chrome/Edge)
        $parts[] = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
        $parts[] = $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '';
        $parts[] = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
        // Ekran/tarayıcı bilgisi (frontend'den gönderilebilir)
        $parts[] = $_GET['fp'] ?? $_POST['fp'] ?? '';
        // Device ID (localStorage'dan)
        $parts[] = $_GET['device_id'] ?? $_POST['device_id'] ?? '';
        
        return implode('|', $parts);
    }
    
    // ─── SAYAÇ: GET ───
    private static function getCount($key, $window) {
        $file = self::$RATE_DIR . '/' . md5($key) . '.json';
        if (!file_exists($file)) return 0;
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return 0;
        
        $now = time();
        // Süresi geçen kayıtları temizle
        $valid = array_filter($data, function($t) use ($now, $window) {
            return ($now - $t) < $window;
        });
        return count($valid);
    }
    
    // ─── SAYAÇ: INCREMENT ───
    private static function increment($key) {
        $file = self::$RATE_DIR . '/' . md5($key) . '.json';
        $data = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?: [];
        }
        
        $now = time();
        // Süresi geçenleri temizle (1 saat)
        $data = array_filter($data, function($t) use ($now) {
            return ($now - $t) < 3600;
        });
        $data[] = $now;
        
        @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    }
    
    // ─── BAN: IP ───
    private static function banIP($ip) {
        $file = self::$RATE_DIR . '/ban_' . md5($ip) . '.json';
        @file_put_contents($file, json_encode([
            'ip' => $ip,
            'until' => time() + self::$IP_BLOCK_TIME,
            'reason' => 'rate_limit'
        ]), LOCK_EX);
    }
    
    private static function isBanned($ip) {
        $file = self::$RATE_DIR . '/ban_' . md5($ip) . '.json';
        if (!file_exists($file)) return false;
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || !isset($data['until'])) return false;
        if (time() >= $data['until']) {
            @unlink($file);
            return false;
        }
        return true;
    }
    
    // ─── REDDET ───
    private static function deny($msg, $code = 429) {
        http_response_code($code);
        echo json_encode([
            "success" => false,
            "error" => $msg,
            "telegram" => "@cmrbaskani",
            "chanel" => "https://t.me/+GgzdPJJUPns3OWJk"
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}

ApiGuard::init();