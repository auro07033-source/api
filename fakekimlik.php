<?php
/**
 * Fake Identity Generator API
 * Telegram: @unutur
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ==================== VERİ LİSTELERİ ====================
$first_names = ["Ahmet", "Mehmet", "Mustafa", "Ali", "Hasan", "Hüseyin", "Ömer", "Fatma", "Ayşe", "Emine", "Hatice", "Zeynep", "Elif", "Meryem", "Berkay", "Can", "Emre", "Deniz"];
$last_names = ["Yılmaz", "Kaya", "Demir", "Çelik", "Şahin", "Yıldız", "Yıldırım", "Öztürk", "Aydın", "Doğan", "Koç", "Kurt", "Arslan", "Polat"];
$cities = ["İstanbul", "Ankara", "İzmir", "Bursa", "Adana", "Gaziantep", "Konya", "Antalya", "Kocaeli", "Mersin", "Diyarbakır", "Samsun"];
$streets = ["Atatürk Caddesi", "Cumhuriyet Mahallesi", "İstiklal Sokak", "Bağlar Sokak", "Fatih Mahallesi", "Yeşil Sokak", "Kültür Caddesi"];

// ==================== FONKSİYONLAR ====================
function generate_tc() {
    $tc = [rand(1, 9)];
    for ($i = 0; $i < 9; $i++) {
        $tc[] = rand(0, 9);
    }
    
    $sum1 = $tc[0] + $tc[2] + $tc[4] + $tc[6] + $tc[8];
    $sum2 = $tc[1] + $tc[3] + $tc[5] + $tc[7];
    $tc[] = ($sum1 * 7 - $sum2) % 10;
    
    $sum3 = array_sum(array_slice($tc, 0, 10));
    $tc[] = $sum3 % 10;
    
    return implode('', $tc);
}

function generate_cc($bin = "524347") {
    $cc = str_split($bin);
    while (count($cc) < 15) {
        $cc[] = rand(0, 9);
    }
    
    $total = 0;
    $length = count($cc);
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = $cc[$i];
        if (($length - $i) % 2 == 0) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $total += $digit;
    }
    $check_digit = (10 - ($total % 10)) % 10;
    $cc[] = $check_digit;
    
    return implode('', $cc);
}

function generate_phone() {
    $prefixes = [30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55];
    $prefix = $prefixes[array_rand($prefixes)];
    $number = rand(1000000, 9999999);
    return "05{$prefix}{$number}";
}

// ==================== ANA FONKSİYON ====================
$count = isset($_GET['adet']) ? (int)$_GET['adet'] : (isset($_POST['adet']) ? (int)$_POST['adet'] : 1);

if ($count > 100) {
    echo json_encode([
        'success' => false,
        'error' => 'Maksimum 100 kayıt üretilebilir',
        'telegram' => '@unutur'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$results = [];
for ($i = 0; $i < $count; $i++) {
    $gender = array_rand(array_flip(["Erkek", "Kadın"]));
    $name = $first_names[array_rand($first_names)] . " " . $last_names[array_rand($last_names)];
    $tc = generate_tc();
    $birth_day = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
    $birth_month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
    $birth_year = rand(1960, 2005);
    $birth_date = "{$birth_day}/{$birth_month}/{$birth_year}";
    $phone = generate_phone();
    $city = $cities[array_rand($cities)];
    $address = $streets[array_rand($streets)] . " No:" . rand(1, 200) . " " . $city;
    $mother = $first_names[array_rand($first_names)] . " " . $last_names[array_rand($last_names)];
    $father = $first_names[array_rand($first_names)] . " " . $last_names[array_rand($last_names)];
    $cc_num = generate_cc();
    $cc_formatted = substr($cc_num, 0, 4) . " " . substr($cc_num, 4, 4) . " " . substr($cc_num, 8, 4) . " " . substr($cc_num, 12, 4);
    $exp_month = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
    $exp_year = rand(2026, 2030);
    $cvv = str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
    
    $results[] = [
        'id' => $i + 1,
        'fullname' => $name,
        'gender' => $gender,
        'tc' => $tc,
        'birth_date' => $birth_date,
        'phone' => $phone,
        'address' => $address,
        'mother' => $mother,
        'father' => $father,
        'credit_card' => $cc_num,
        'credit_card_formatted' => $cc_formatted,
        'expiry' => "{$exp_month}/{$exp_year}",
        'cvv' => $cvv
    ];
}

echo json_encode([
    'success' => true,
    'count' => $count,
    'data' => $results,
    'telegram' => '@unutur'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>