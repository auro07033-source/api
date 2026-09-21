<?php
// api_guard.php - API Rate Limit + Key Koruması
class ApiGuard {
    private static $API_KEY = "cmrbaskani_2026_secret_key_xyz";
    private static $RATE_DIR = null;

    // ─── RATE LİMİTLERİ ───
    private static $RATE_LIMIT = 10;        // dakikada max istek (IP)
    private static $RATE_WINDOW = 60;

    private static $DEVICE_LIMIT = 30;      // dakikada max istek (cihaz)
    private static $DEVICE_WINDOW = 60;

    private static $IP_DAILY_LIMIT = 2000;  // günlük max
    private static $IP_BLOCK_TIME = 60;     // 1 dakika ban

    public static function init() {
        self::$RATE_DIR = sys_get_temp_dir() . '/api_guard';
        if (!is_dir(self::$RATE_DIR)) @mkdir(self::$RATE_DIR, 0777, true);
    }

    public static function checkKey() {
        $key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key !== self::$API_KEY) {
            self::deny("Gecersiz API key", 401);
        }
    }

    public static function checkRate() {
        $ip = self::getIP();
        $device = self::getDevice();

        if (self::isBanned($ip)) {
            self::deny("Rate limit. Lutfen bekleyin.", 429);
        }

        $ipCount = self::getCount("ip_" . md5($ip), self::$RATE_WINDOW);
        if ($ipCount >= self::$RATE_LIMIT) {
            self::banIP($ip);
            self::deny("IP rate limit: " . self::$RATE_LIMIT . "/dk. Lutfen bekleyin.", 429);
        }
        self::increment("ip_" . md5($ip));

        $devCount = self::getCount("dev_" . md5($device), self::$DEVICE_WINDOW);
        if ($devCount >= self::$DEVICE_LIMIT) {
            self::deny("Cihaz rate limit: " . self::$DEVICE_LIMIT . "/dk. Lutfen bekleyin.", 429);
        }
        self::increment("dev_" . md5($device));

        $dailyKey = "daily_" . md5($ip) . "_" . date('Y-m-d');
        if (self::getCount($dailyKey, 86400) >= self::$IP_DAILY_LIMIT) {
            self::deny("Gunluk limit asildi.", 429);
        }
        self::increment($dailyKey);
    }

    public static function getRemaining() {
        $ip = self::getIP();
        $count = self::getCount("ip_" . md5($ip), self::$RATE_WINDOW);
        return max(0, self::$RATE_LIMIT - $count);
    }

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

    private static function getDevice() {
        $parts = [];
        $parts[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $parts[] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        $parts[] = $_SERVER['HTTP_SEC_CH_UA'] ?? '';
        $parts[] = $_GET['device_id'] ?? $_POST['device_id'] ?? '';
        return implode('|', $parts);
    }

    private static function getCount($key, $window) {
        $file = self::$RATE_DIR . '/' . md5($key) . '.json';
        if (!file_exists($file)) return 0;
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) return 0;
        $now = time();
        return count(array_filter($data, fn($t) => ($now - $t) < $window));
    }

    private static function increment($key) {
        $file = self::$RATE_DIR . '/' . md5($key) . '.json';
        $data = [];
        if (file_exists($file)) $data = json_decode(file_get_contents($file), true) ?: [];
        $now = time();
        $data = array_filter($data, fn($t) => ($now - $t) < 3600);
        $data[] = $now;
        @file_put_contents($file, json_encode(array_values($data)), LOCK_EX);
    }

    private static function banIP($ip) {
        $file = self::$RATE_DIR . '/ban_' . md5($ip) . '.json';
        @file_put_contents($file, json_encode([
            'ip' => $ip,
            'until' => time() + self::$IP_BLOCK_TIME
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