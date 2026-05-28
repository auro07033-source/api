<?php
/**
 * IP Logger API - Gelişmiş IP Takip Sistemi
 * telegram : @unutur
 * Domain: https://api-5-34l6.onrender.com
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ==================== KONFİGÜRASYON ====================
$log_file = __DIR__ . '/iplogs.json';
$stats_file = __DIR__ . '/stats.json';
$webhook_url = isset($_GET['webhook']) ? trim($_GET['webhook']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// ==================== FONKSİYONLAR ====================

/**
 * IP adresini al (proxy vb. atlatma)
 */
function getRealIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    
    return $ip;
}

/**
 * IP detaylarını getir (ip-api.com)
 */
function getIPDetails($ip) {
    $cache_file = __DIR__ . '/cache/' . md5($ip) . '.json';
    
    // Cache kontrolü (1 saat)
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 3600) {
        return json_decode(file_get_contents($cache_file), true);
    }
    
    $url = "http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,mobile,proxy,hosting,query";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && $data['status'] == 'success') {
        // Cache dizinini oluştur
        if (!file_exists(__DIR__ . '/cache')) {
            mkdir(__DIR__ . '/cache', 0777, true);
        }
        file_put_contents($cache_file, json_encode($data));
        return $data;
    }
    
    return null;
}

/**
 * Log kaydet
 */
function saveLog($data) {
    global $log_file, $stats_file, $webhook_url;
    
    // Log dosyasını oku
    $logs = [];
    if (file_exists($log_file)) {
        $logs = json_decode(file_get_contents($log_file), true);
        if (!is_array($logs)) $logs = [];
    }
    
    // Yeni log ekle
    array_unshift($logs, $data);
    
    // Son 1000 log tut
    $logs = array_slice($logs, 0, 1000);
    
    file_put_contents($log_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // İstatistik güncelle
    $stats = [];
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true);
        if (!is_array($stats)) $stats = [];
    }
    
    $today = date('Y-m-d');
    $stats['total'] = ($stats['total'] ?? 0) + 1;
    $stats['daily'][$today] = ($stats['daily'][$today] ?? 0) + 1;
    $stats['last_ip'] = $data['ip'];
    $stats['last_time'] = $data['timestamp'];
    
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Webhook gönder
    if ($webhook_url && filter_var($webhook_url, FILTER_VALIDATE_URL)) {
        sendWebhook($webhook_url, $data);
    }
}

/**
 * Webhook ile veri gönder
 */
function sendWebhook($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * User Agent bilgilerini parse et
 */
function parseUserAgent($ua) {
    $browsers = [
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
        'Edge' => 'Edge',
        'Opera' => 'Opera',
        'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer'
    ];
    
    $browser = 'Unknown';
    foreach ($browsers as $key => $value) {
        if (strpos($ua, $key) !== false) {
            $browser = $value;
            break;
        }
    }
    
    $os = 'Unknown';
    if (strpos($ua, 'Windows') !== false) $os = 'Windows';
    elseif (strpos($ua, 'Android') !== false) $os = 'Android';
    elseif (strpos($ua, 'iPhone') !== false) $os = 'iOS';
    elseif (strpos($ua, 'Mac') !== false) $os = 'macOS';
    elseif (strpos($ua, 'Linux') !== false) $os = 'Linux';
    
    $device = 'Desktop';
    if (strpos($ua, 'Mobile') !== false) $device = 'Mobile';
    elseif (strpos($ua, 'Tablet') !== false) $device = 'Tablet';
    
    return ['browser' => $browser, 'os' => $os, 'device' => $device];
}

// ==================== CACHE DİZİNİ ====================
if (!file_exists(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0777, true);
}

// ==================== URL KISALTMA / LOGLAMA ====================
if ($action == 'create') {
    // URL kısaltma ve loglama linki oluştur
    $redirect_url = isset($_GET['url']) ? trim($_GET['url']) : '';
    $custom_slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
    
    if (empty($redirect_url)) {
        echo json_encode([
            'success' => false,
            'error' => 'URL parametresi gerekli',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $slug = $custom_slug ?: substr(md5(uniqid()), 0, 6);
    $short_url = "https://api-5-34l6.onrender.com/l/{$slug}";
    
    // Loglama linkini kaydet
    $links_file = __DIR__ . '/links.json';
    $links = [];
    if (file_exists($links_file)) {
        $links = json_decode(file_get_contents($links_file), true);
    }
    
    $links[$slug] = [
        'original_url' => $redirect_url,
        'webhook' => $webhook_url,
        'created_at' => date('Y-m-d H:i:s'),
        'clicks' => 0
    ];
    
    file_put_contents($links_file, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'short_url' => $short_url,
        'original_url' => $redirect_url,
        'slug' => $slug,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== LOGLAMA LİNKİNE GİDEN KULLANICI ====================
$path = $_SERVER['REQUEST_URI'];
if (preg_match('/\/l\/([a-zA-Z0-9]+)/', $path, $matches)) {
    $slug = $matches[1];
    $links_file = __DIR__ . '/links.json';
    
    if (file_exists($links_file)) {
        $links = json_decode(file_get_contents($links_file), true);
        
        if (isset($links[$slug])) {
            // Tıklanma sayısını arttır
            $links[$slug]['clicks']++;
            file_put_contents($links_file, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Webhook'u güncelle
            $webhook_url = $links[$slug]['webhook'];
            
            // IP bilgilerini topla
            $ip = getRealIP();
            $ip_details = getIPDetails($ip);
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $ua_info = parseUserAgent($user_agent);
            
            $log_data = [
                'id' => uniqid(),
                'ip' => $ip,
                'timestamp' => date('Y-m-d H:i:s'),
                'timestamp_unix' => time(),
                'user_agent' => $user_agent,
                'browser' => $ua_info['browser'],
                'os' => $ua_info['os'],
                'device' => $ua_info['device'],
                'referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
                'page' => $path,
                'slug' => $slug,
                'method' => $_SERVER['REQUEST_METHOD'],
                'query_string' => $_SERVER['QUERY_STRING'] ?? '',
                'ip_details' => $ip_details,
                'all_headers' => getallheaders()
            ];
            
            saveLog($log_data);
            
            // Yönlendir
            $redirect_url = $links[$slug]['original_url'];
            header("Location: $redirect_url");
            exit;
        }
    }
    
    // Geçersiz slug
    echo "<h3>Geçersiz link!</h3>";
    exit;
}

// ==================== API: LOGLARI GÖSTER ====================
if ($action == 'logs') {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    
    if (file_exists($log_file)) {
        $logs = json_decode(file_get_contents($log_file), true);
        $logs = array_slice($logs, 0, $limit);
        
        echo json_encode([
            'success' => true,
            'total' => count($logs),
            'logs' => $logs,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'logs' => [],
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==================== API: İSTATİSTİK ====================
if ($action == 'stats') {
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true);
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'stats' => ['total' => 0, 'daily' => []],
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ==================== ANA SAYFA ====================
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Logger API</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', monospace;
        }
        body {
            background: linear-gradient(135deg, #0a0e27 0%, #1a1f3a 100%);
            min-height: 100vh;
            padding: 2rem;
            color: #fff;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .card h2 {
            margin-bottom: 1rem;
            color: #00d4ff;
        }
        input, textarea {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            color: #fff;
            font-size: 1rem;
        }
        button {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        button:hover {
            transform: scale(1.02);
        }
        .result {
            background: rgba(0,0,0,0.5);
            padding: 1rem;
            border-radius: 0.75rem;
            font-family: monospace;
            font-size: 0.85rem;
            word-break: break-all;
            margin-top: 1rem;
            display: none;
        }
        .code {
            background: #0a0e27;
            padding: 1rem;
            border-radius: 0.75rem;
            font-family: monospace;
            font-size: 0.8rem;
            overflow-x: auto;
        }
        .badge {
            display: inline-block;
            background: #00d4ff;
            color: #000;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        a {
            color: #00d4ff;
            text-decoration: none;
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: rgba(255,255,255,0.5);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🎯 IP Logger API</h1>
    
    <div class="card">
        <h2>📌 1. URL Kısalt / IP Log Oluştur</h2>
        <input type="text" id="redirectUrl" placeholder="Yönlendirilecek URL (örn: https://example.com)" value="https://t.me/lorex_tools">
        <input type="text" id="webhookUrl" placeholder="Webhook URL (isteğe bağlı - IP bilgilerinin gönderileceği adres)">
        <button onclick="createShortUrl()">🔗 Kısa URL Oluştur</button>
        <div id="createResult" class="result"></div>
    </div>
    
    <div class="card">
        <h2>📊 2. IP Logları Görüntüle</h2>
        <button onclick="viewLogs()">📋 Logları Getir</button>
        <div id="logsResult" class="result"></div>
    </div>
    
    <div class="card">
        <h2>📈 3. İstatistikler</h2>
        <button onclick="viewStats()">📊 İstatistikleri Getir</button>
        <div id="statsResult" class="result"></div>
    </div>
    
    <div class="card">
        <h2>📖 API Kullanımı</h2>
        <div class="code">
            <span class="badge">GET</span> /?action=create&url=HEDEF_URL&webhook=WEBHOOK_URL<br>
            <span class="badge">GET</span> /?action=logs&limit=50<br>
            <span class="badge">GET</span> /?action=stats<br>
            <span class="badge">GET</span> /l/SLUG (ziyaretçi loglanır ve yönlendirilir)
        </div>
    </div>
    
    <div class="footer">
        telegram : @unutur | IP Logger API v1.0
    </div>
</div>

<script>
    const API_URL = 'https://api-5-34l6.onrender.com';
    
    async function createShortUrl() {
        const url = document.getElementById('redirectUrl').value;
        const webhook = document.getElementById('webhookUrl').value;
        
        if (!url) {
            alert('URL giriniz!');
            return;
        }
        
        let apiUrl = `${API_URL}?action=create&url=${encodeURIComponent(url)}`;
        if (webhook) {
            apiUrl += `&webhook=${encodeURIComponent(webhook)}`;
        }
        
        try {
            const response = await fetch(apiUrl);
            const data = await response.json();
            const resultDiv = document.getElementById('createResult');
            
            if (data.success) {
                resultDiv.innerHTML = `
                    ✅ Kısa URL oluşturuldu!<br>
                    <strong>🔗 Link:</strong> <a href="${data.short_url}" target="_blank">${data.short_url}</a><br>
                    <strong>📝 Slug:</strong> ${data.slug}<br>
                    <strong>🎯 Hedef:</strong> ${data.original_url}
                `;
            } else {
                resultDiv.innerHTML = `❌ Hata: ${data.error}`;
            }
            resultDiv.style.display = 'block';
        } catch (err) {
            alert('Hata: ' + err.message);
        }
    }
    
    async function viewLogs() {
        try {
            const response = await fetch(`${API_URL}?action=logs&limit=20`);
            const data = await response.json();
            const resultDiv = document.getElementById('logsResult');
            
            if (data.success && data.logs.length > 0) {
                let html = `<strong>📋 Son ${data.logs.length} log:</strong><br><br>`;
                for (let log of data.logs) {
                    let details = log.ip_details;
                    html += `
                        <div style="border-bottom:1px solid rgba(255,255,255,0.1); padding:0.5rem 0;">
                            🕒 ${log.timestamp}<br>
                            🌐 IP: ${log.ip}<br>
                            ${details ? `📍 ${details.city}, ${details.country}<br>` : ''}
                            💻 ${log.browser} | ${log.os} | ${log.device}<br>
                            🔗 Slug: ${log.slug}<br>
                        </div>
                    `;
                }
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '📭 Henüz hiç log yok.';
            }
            resultDiv.style.display = 'block';
        } catch (err) {
            alert('Hata: ' + err.message);
        }
    }
    
    async function viewStats() {
        try {
            const response = await fetch(`${API_URL}?action=stats`);
            const data = await response.json();
            const resultDiv = document.getElementById('statsResult');
            
            if (data.success && data.stats) {
                resultDiv.innerHTML = `
                    <strong>📊 İstatistikler:</strong><br>
                    📌 Toplam Tıklanma: ${data.stats.total || 0}<br>
                    📅 Son IP: ${data.stats.last_ip || '-'}<br>
                    🕒 Son Zaman: ${data.stats.last_time || '-'}<br>
                    📈 Günlük: ${JSON.stringify(data.stats.daily || {})}
                `;
            } else {
                resultDiv.innerHTML = '📭 İstatistik bulunamadı.';
            }
            resultDiv.style.display = 'block';
        } catch (err) {
            alert('Hata: ' + err.message);
        }
    }
</script>
</body>
</html>