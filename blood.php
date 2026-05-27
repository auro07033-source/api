<?php
/**
 * Kanlı Yazı Logo API - Güncel Çalışan Versiyon
 * Telegram: @zahettim
 */

$text = isset($_GET['text']) ? $_GET['text'] : (isset($_POST['text']) ? $_POST['text'] : null);

if (!$text) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Yazı gerekli',
        'kullanım' => '/blood.php?text=Merhaba',
        'telegram' => '@zahettim'
    ]);
    exit;
}

// Alternative API - FlamingText (ücretsiz, çalışıyor)
$url = "https://flamingtext.com/net-fu/proxy_form.cgi";

$post_data = [
    'text' => $text,
    'action' => 'generate',
    'generator' => 'blood',
    'options' => 'textsize=60',
    'output' => 'png'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 && $response) {
    // Yanıtın içinden görsel URL'sini bul
    preg_match('/<img[^>]+src="([^"]+\.png)"/', $response, $img_match);
    
    if (isset($img_match[1])) {
        $image_url = 'https://flamingtext.com' . $img_match[1];
        
        // Görseli indir ve göster
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $image_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $image_data = curl_exec($ch);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($image_data) {
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . strlen($image_data));
            echo $image_data;
            exit;
        }
    }
}

// İkinci alternatif: cooltext.com
$url2 = "https://cooltext.com/Generate";
$post_data2 = [
    'Logo' => 'Bloody+Horror',
    'Text' => $text,
    'FontSize' => '60',
    'Color' => 'red'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url2);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data2));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$response2 = curl_exec($ch);
curl_close($ch);

if ($response2 && preg_match('/<img[^>]+src="([^"]+\.png)"/', $response2, $img_match2)) {
    $image_url = 'https://cooltext.com' . $img_match2[1];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $image_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $image_data = curl_exec($ch);
    curl_close($ch);
    
    if ($image_data) {
        header('Content-Type: image/png');
        echo $image_data;
        exit;
    }
}

// Hata durumu
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => 'Görsel oluşturulamadı. Servis geçici olarak kapalı olabilir.',
    'text' => $text,
    'telegram' => '@zahettim'
]);
?>