<?php
/**
 * YouTube Video İndirme API
 * Video indirir ve doğrudan erişim linki verir
 * telegram : @unutur
 */

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url)) {
    die(json_encode(['error' => 'URL gerekli']));
}

// Video ID al
preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches);
$video_id = $matches[1] ?? '';

if (!$video_id) {
    die(json_encode(['error' => 'Video ID alınamadı']));
}

$output_file = __DIR__ . "/{$video_id}.mp4";
$download_url = "https://api-8ne7.onrender.com/{$video_id}.mp4";

// Video zaten varsa linki döndür
if (file_exists($output_file)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'video_id' => $video_id,
        'download_url' => $download_url
    ]);
    exit;
}

// yt-dlp ile indir
exec("yt-dlp -f 'best[ext=mp4]' -o '{$output_file}' '{$url}' 2>&1", $output, $code);

if ($code === 0 && file_exists($output_file)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'video_id' => $video_id,
        'download_url' => $download_url
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'İndirme başarısız'
    ]);
}
?>