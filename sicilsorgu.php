<?php
/**
 * Sicil Sorgulama API - Dosyadan Direkt Okuma (JSON formatı takmıyor)
 * Telegram: @zahettim
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$json_file = __DIR__ . '/sicil_data.json';

if (!file_exists($json_file)) {
    echo '{"success":false,"error":"Dosya bulunamadı","telegram":"@zahettim"}';
    exit;
}

$content = file_get_contents($json_file);
$sicil = $_GET['sicil'] ?? null;

if (!$sicil) {
    echo '{"success":false,"error":"Sicil gerekli","kullanım":"?sicil=2925","telegram":"@zahettim"}';
    exit;
}

// Sicili ara (basit string araması)
if (strpos($content, '"AVUKAT_SICIL_NO":"' . $sicil . '"') !== false || 
    strpos($content, '"AVUKAT_SICIL_NO":' . $sicil . '') !== false) {
    
    // Kayıt bloğunu bul
    $pattern = '/\{[^{}]*"AVUKAT_SICIL_NO"[:\s]*["\']?' . $sicil . '["\']?[^{}]*\}/';
    preg_match($pattern, $content, $match);
    
    if (isset($match[0])) {
        // Alanları temizle ve JSON yap
        $raw = $match[0];
        
        // Temizlik
        $raw = preg_replace('/,\s*,/', ',', $raw);
        $raw = preg_replace('/,\s*}/', '}', $raw);
        $raw = preg_replace('/\{\s*,/', '{', $raw);
        
        // Geçerli JSON yapmaya çalış
        $data = json_decode($raw, true);
        
        if ($data) {
            // Boş olmayan alanları al
            $sonuc = ['success' => true];
            foreach ($data as $k => $v) {
                if ($v !== '' && $v !== null) {
                    $sonuc[$k] = $v;
                }
            }
            $sonuc['telegram'] = '@zahettim';
            echo json_encode($sonuc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            // Manuel çıkar
            $sonuc = ['success' => true];
            preg_match_all('/"([^"]+)"\s*:\s*"?([^",}\n]+)"?/', $raw, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $val = trim($m[2], '"');
                if ($val !== '') {
                    $sonuc[$m[1]] = is_numeric($val) ? (float)$val : $val;
                }
            }
            $sonuc['telegram'] = '@zahettim';
            echo json_encode($sonuc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo '{"success":true,"AVUKAT_SICIL_NO":"' . $sicil . '","telegram":"@zahettim"}';
    }
} else {
    echo '{"success":false,"error":"Sicil bulunamadı","aranan":"' . $sicil . '","telegram":"@zahettim"}';
}
?>