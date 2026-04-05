<?php
include 'db.php';
set_time_limit(600); // İşlem uzun sürebilir
error_reporting(0);

echo "<body style='background:#121418; color:#fff; font-family:sans-serif; padding:30px;'>";
echo "<h2>Sofifa Yüz Tarama Botu Çalışıyor... Lütfen Bekleyin ⏳</h2>";

$oyuncular = $pdo->query("SELECT id, isim FROM oyuncular WHERE yuz_url = 'https://cdn.sofifa.net/player_0.svg' OR yuz_url IS NULL")->fetchAll();
$guncellenen = 0;

$options = [
    "http" => [
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
    ]
];
$context = stream_context_create($options);

foreach($oyuncular as $o) {
    // İsmi URL'ye uygun hale getir (Boşlukları + yap vb.)
    $arama_isim = urlencode(str_replace(['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'], ['c','g','i','o','s','u','C','G','I','O','S','U'], $o['isim']));
    $url = "https://sofifa.com/players?keyword=" . $arama_isim;
    
    $html = file_get_contents($url, false, $context);
    
    if($html) {
        // Sofifa'daki oyuncu resim URL'sini yakalamak için Regex (data-src veya src)
        if(preg_match('/<img[^>]+data-src="([^"]+)"[^>]*class="player-check"/', $html, $matches) || 
           preg_match('/<img[^>]+src="([^"]+)"[^>]*class="player-check"/', $html, $matches)) {
            
            $yuz_url = str_replace('120.png', '240.png', $matches[1]); // Yüksek çözünürlüklü hali
            
            $stmt = $pdo->prepare("UPDATE oyuncular SET yuz_url = ? WHERE id = ?");
            $stmt->execute([$yuz_url, $o['id']]);
            $guncellenen++;
        }
    }
    // Bot banı yememek için çeyrek saniye bekle
    usleep(250000); 
}

echo "<h3 style='color:#4facfe;'>✅ İşlem Tamam! $guncellenen oyuncunun yüzü Sofifa'dan çekildi.</h3>";
echo "<a href='puan.php' style='color:#ffd700;'>Puan Durumuna Dön</a></body>";
?>