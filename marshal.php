<?php
/**
 * Marshal Encoder/Decoder API - Mobil Dosya Yöneticili
 * Android com.google.android.documentsui ile uyumlu
 * telegram : @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// ==================== DOSYA YÜKLEME (Mobil Uyumlu) ====================
if ($action == 'upload_encode') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Dosya yüklenemedi. Lütfen bir .py dosyası seçin.',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $file = $_FILES['file'];
    $file_content = file_get_contents($file['tmp_name']);
    $filename = pathinfo($file['name'], PATHINFO_FILENAME);
    
    // Python kodunu marshal formatında encode et
    $encoded = base64_encode($file_content);
    
    // Python loader oluştur
    $loader = "# -*- coding: utf-8 -*-\n";
    $loader .= "import marshal, base64\n";
    $loader .= "exec(marshal.loads(base64.b64decode('{$encoded}')))\n";
    
    // Encoded dosyayı kaydet
    $output_file = $filename . '_enc.py';
    file_put_contents($output_file, $loader);
    
    // Dosya URL'i
    $file_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/" . urlencode($output_file);
    
    echo json_encode([
        'success' => true,
        'action' => 'encode',
        'original_file' => $file['name'],
        'original_size' => filesize($file['tmp_name']),
        'encoded_file' => $output_file,
        'encoded_size' => strlen($loader),
        'download_url' => $file_url,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== DECODE İÇİN DOSYA YÜKLE ====================
if ($action == 'upload_decode') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] != 0) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Dosya yüklenemedi. Lütfen _enc.py dosyası seçin.',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $file = $_FILES['file'];
    $file_content = file_get_contents($file['tmp_name']);
    $filename = pathinfo($file['name'], PATHINFO_FILENAME);
    
    // Dosyadan base64 encoded marshal verisini çek
    preg_match("/base64\.b64decode\('([^']+)'\)/", $file_content, $matches);
    
    if (empty($matches)) {
        echo json_encode([
            'success' => false,
            'error' => '❌ Geçerli bir marshal encoded dosya değil. Bu dosya _enc.py formatında mı?',
            'telegram' => '@unutur'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $encoded_data = $matches[1];
    $decoded = base64_decode($encoded_data);
    
    // Decoded dosyayı kaydet
    $output_file = str_replace('_enc', '_dec', $file['name']);
    file_put_contents($output_file, $decoded);
    
    // Dosya URL'i
    $file_url = (isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/" . urlencode($output_file);
    
    echo json_encode([
        'success' => true,
        'action' => 'decode',
        'original_file' => $file['name'],
        'original_size' => filesize($file['tmp_name']),
        'decoded_file' => $output_file,
        'decoded_size' => strlen($decoded),
        'download_url' => $file_url,
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== DOSYA İNDİRME ====================
if ($action == 'download' && isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $file_path = __DIR__ . '/' . $file;
    
    if (file_exists($file_path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    } else {
        echo "❌ Dosya bulunamadı!";
        exit;
    }
}

// ==================== ANA SAYFA ====================
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Marshal Encoder/Decoder - Python</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            padding: 1rem;
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            color: #fff;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .sub {
            text-align: center;
            margin-bottom: 1.5rem;
            color: rgba(255,255,255,0.6);
            font-size: 0.8rem;
        }
        .card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            padding: 1.2rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .card h2 {
            margin-bottom: 0.8rem;
            color: #00d4ff;
            font-size: 1.1rem;
        }
        .file-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: rgba(0,0,0,0.3);
            border: 1px dashed rgba(255,255,255,0.4);
            border-radius: 0.8rem;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            text-align: center;
        }
        .file-label:hover {
            background: rgba(0,0,0,0.5);
        }
        .file-label input {
            display: none;
        }
        .file-name {
            font-size: 0.8rem;
            color: #00d4ff;
            word-break: break-all;
        }
        button {
            background: linear-gradient(135deg, #00d4ff, #7c3aed);
            border: none;
            padding: 0.8rem;
            border-radius: 0.8rem;
            color: white;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        button:active {
            transform: scale(0.98);
        }
        .result {
            background: rgba(0,0,0,0.5);
            padding: 0.8rem;
            border-radius: 0.8rem;
            font-family: monospace;
            font-size: 0.7rem;
            margin-top: 1rem;
            word-break: break-all;
            display: none;
        }
        .result.success {
            border-left: 4px solid #00ff88;
        }
        .result.error {
            border-left: 4px solid #ff4444;
        }
        .download-link {
            display: inline-block;
            margin-top: 0.5rem;
            color: #00d4ff;
            text-decoration: none;
            background: rgba(0,212,255,0.2);
            padding: 0.3rem 0.6rem;
            border-radius: 0.5rem;
        }
        .footer {
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255,255,255,0.3);
            font-size: 0.7rem;
        }
        .badge {
            display: inline-block;
            background: #7c3aed;
            padding: 0.2rem 0.6rem;
            border-radius: 0.5rem;
            font-size: 0.6rem;
            margin-bottom: 0.5rem;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
            vertical-align: middle;
            margin-right: 0.5rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🐍 Marshal Tool</h1>
    <div class="sub">Python .py dosyalarını kodla/çöz</div>
    
    <div class="card">
        <div class="badge">🔐 ENCODE</div>
        <h2>Python Dosyasını Kodla</h2>
        <div class="file-label" onclick="document.getElementById('encodeFile').click()">
            📁 <span id="encodeFileName">Dosya seçin (.py)</span>
            <input type="file" id="encodeFile" accept=".py" onchange="updateFileName('encodeFile', 'encodeFileName')">
        </div>
        <button onclick="encodeFile()">🔐 Marshal ile Kodla</button>
        <div id="encodeResult" class="result"></div>
    </div>
    
    <div class="card">
        <div class="badge">🔓 DECODE</div>
        <h2>Encoded Dosyayı Çöz</h2>
        <div class="file-label" onclick="document.getElementById('decodeFile').click()">
            📁 <span id="decodeFileName">_enc.py dosyası seçin</span>
            <input type="file" id="decodeFile" accept=".py" onchange="updateFileName('decodeFile', 'decodeFileName')">
        </div>
        <button onclick="decodeFile()">🔓 Marshal Çöz</button>
        <div id="decodeResult" class="result"></div>
    </div>
    
    <div class="footer">
        telegram : @unutur | Dosya yönetici ile .py seçin
    </div>
</div>

<script>
    const API_URL = window.location.origin + window.location.pathname;
    
    function updateFileName(inputId, spanId) {
        const input = document.getElementById(inputId);
        const span = document.getElementById(spanId);
        if (input.files && input.files[0]) {
            span.innerHTML = input.files[0].name;
        } else {
            span.innerHTML = inputId === 'encodeFile' ? 'Dosya seçin (.py)' : '_enc.py dosyası seçin';
        }
    }
    
    async function encodeFile() {
        const fileInput = document.getElementById('encodeFile');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Lütfen bir .py dosyası seçin!');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        const resultDiv = document.getElementById('encodeResult');
        resultDiv.innerHTML = '<span class="loading"></span> Kodlanıyor...';
        resultDiv.style.display = 'block';
        resultDiv.className = 'result';
        
        try {
            const response = await fetch(`${API_URL}?action=upload_encode`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                resultDiv.innerHTML = `
                    ✅ <strong>Kodlama Başarılı!</strong><br>
                    📁 ${data.original_file} (${data.original_size} byte)<br>
                    📁 ${data.encoded_file} (${data.encoded_size} byte)<br>
                    🔗 <a href="${data.download_url}" class="download-link" download>📥 Encoded Dosyayı İndir</a>
                `;
                resultDiv.classList.add('success');
            } else {
                resultDiv.innerHTML = `❌ ${data.error}`;
                resultDiv.classList.add('error');
            }
        } catch (err) {
            resultDiv.innerHTML = `❌ Bağlantı hatası: ${err.message}`;
            resultDiv.classList.add('error');
        }
    }
    
    async function decodeFile() {
        const fileInput = document.getElementById('decodeFile');
        const file = fileInput.files[0];
        
        if (!file) {
            alert('Lütfen bir _enc.py dosyası seçin!');
            return;
        }
        
        const formData = new FormData();
        formData.append('file', file);
        
        const resultDiv = document.getElementById('decodeResult');
        resultDiv.innerHTML = '<span class="loading"></span> Çözümleniyor...';
        resultDiv.style.display = 'block';
        resultDiv.className = 'result';
        
        try {
            const response = await fetch(`${API_URL}?action=upload_decode`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                resultDiv.innerHTML = `
                    ✅ <strong>Çözümleme Başarılı!</strong><br>
                    📁 ${data.original_file} (${data.original_size} byte)<br>
                    📁 ${data.decoded_file} (${data.decoded_size} byte)<br>
                    🔗 <a href="${data.download_url}" class="download-link" download>📥 Decoded Dosyayı İndir</a>
                `;
                resultDiv.classList.add('success');
            } else {
                resultDiv.innerHTML = `❌ ${data.error}`;
                resultDiv.classList.add('error');
            }
        } catch (err) {
            resultDiv.innerHTML = `❌ Bağlantı hatası: ${err.message}`;
            resultDiv.classList.add('error');
        }
    }
</script>
</body>
</html>