<?php
include 'db.php';

set_time_limit(0); 
error_reporting(0);

ob_implicit_flush(true);
ob_end_flush();

echo "<body style='background:#0a0b0d; color:#fff; font-family:sans-serif; padding:40px;'>";
echo "<h2 style='color:#ffd700; text-align:center;'>🏆 Şampiyonlar Ligi Transfermarkt Botu Çalışıyor...</h2>";
echo "<p style='text-align:center; color:#a0a5b1;'>Lütfen sekmeyi kapatmayın. Gerçek 36 takım (Logolar dahil) ve oyuncuları indiriliyor ⏳</p>";
echo "<div style='max-height: 400px; overflow-y: auto; background: #1e2229; padding: 15px; border-radius: 10px; border: 1px solid #2a2f38;'>";

try {
    // 1. ŞAMPİYONLAR LİGİ TABLOLARINI OLUŞTUR VE TEMİZLE
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_takimlar (id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT, savunma INT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_oyuncular (id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), guc INT, yuz_url VARCHAR(255) DEFAULT 'https://ui-avatars.com/api/?background=random&color=fff&rounded=true&bold=true')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_maclar (id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, tur VARCHAR(50) DEFAULT 'Lig Asamasi', ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL)");
    
    $pdo->exec("TRUNCATE TABLE cl_takimlar");
    $pdo->exec("TRUNCATE TABLE cl_oyuncular");
    $pdo->exec("TRUNCATE TABLE cl_maclar");

    // 2. VİKİPEDİ PUAN TABLOSUNDAKİ TAM 36 TAKIM 
    // Format: [Takım Adı, Transfermarkt ID, Hücum Gücü, Savunma Gücü]
    $devler_ligi = [
        ['Arsenal', 11, 92, 90],
        ['Bayern München', 27, 92, 88],
        ['Liverpool', 31, 91, 89],
        ['Tottenham Hotspur', 148, 88, 85],
        ['Barcelona', 131, 89, 85],
        ['Chelsea', 631, 88, 86],
        ['Sporting CP', 336, 85, 82],
        ['Manchester City', 281, 95, 92],
        ['Real Madrid', 418, 94, 90],
        ['Inter', 46, 89, 90],
        ['Paris SG', 583, 90, 86],
        ['Newcastle United', 762, 86, 85],
        ['Juventus', 506, 86, 88],
        ['Atletico Madrid', 13, 85, 89],
        ['Atalanta', 800, 86, 84],
        ['B. Leverkusen', 15, 87, 86],
        ['B. Dortmund', 16, 86, 84],
        ['Olimpiakos', 683, 80, 78],
        ['Club Brugge', 2282, 80, 79],
        ['Galatasaray', 141, 83, 81],
        ['Monaco', 162, 82, 81],
        ['Karabağ', 10600, 76, 75],
        ['Bodø/Glimt', 501, 78, 76],
        ['Benfica', 294, 83, 82],
        ['Marsilya', 244, 81, 80],
        ['Pafos FC', 41280, 74, 73],
        ['Union SG', 3008, 78, 77],
        ['PSV Eindhoven', 383, 83, 80],
        ['Athletic Bilbao', 621, 84, 82],
        ['Napoli', 6195, 85, 83],
        ['Kopenhag', 190, 79, 78],
        ['Ajax', 1413, 81, 80],
        ['E. Frankfurt', 24, 82, 80],
        ['Slavia Prag', 719, 78, 77],
        ['Villarreal', 1050, 82, 81],
        ['Kairat', 10436, 73, 72]
    ];

    $stmt_takim = $pdo->prepare("INSERT INTO cl_takimlar (takim_adi, logo, hucum, savunma) VALUES (?, ?, ?, ?)");
    $stmt_oyuncu = $pdo->prepare("INSERT INTO cl_oyuncular (takim_id, isim, mevki, guc) VALUES (?, ?, ?, ?)");

    function html_getir($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $html = curl_exec($ch);
        curl_close($ch);
        return $html;
    }

    $toplam_oyuncu = 0;

    foreach ($devler_ligi as $t) {
        $takim_ad = $t[0];
        $tm_id = $t[1];
        $hucum = $t[2];
        $savunma = $t[3];

        // TRANSFERMARKT YÜKSEK ÇÖZÜNÜRLÜKLÜ LOGO ÇEKİCİ (Wappen/Big Formatı)
        $logo = "https://tmssl.akamaized.net/images/wappen/big/{$tm_id}.png";

        $stmt_takim->execute([$takim_ad, $logo, $hucum, $savunma]);
        $takim_id = $pdo->lastInsertId();
        
        echo "<div style='margin-bottom:8px;'>🟢 <strong>$takim_ad</strong> (Logo alındı). Kadrosu çekiliyor...</div>";
        
        // Transfermarkt veritabanında güncel lig aşaması 2024 sezon ID'sine kayıtlıdır
        $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$tm_id}/saison_id/2024";
        $html = html_getir($url);
        
        $takim_oyuncu_sayisi = 0;

        if($html) {
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            
            $rows = $xpath->query("//table[@class='items']//tbody/tr");
            
            foreach($rows as $row) {
                $nameNode = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
                $posNode = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
                
                if($nameNode && $posNode) {
                    $isim = trim($nameNode->nodeValue);
                    $orijinal_mevki = trim($posNode->nodeValue);
                    
                    $mevki = 'OS'; 
                    $kucuk_mevki = mb_strtolower($orijinal_mevki, 'UTF-8');
                    if(strpos($kucuk_mevki, 'kaleci') !== false) $mevki = 'K';
                    elseif(strpos($kucuk_mevki, 'bek') !== false || strpos($kucuk_mevki, 'stoper') !== false || strpos($kucuk_mevki, 'defans') !== false) $mevki = 'D';
                    elseif(strpos($kucuk_mevki, 'santrafor') !== false || strpos($kucuk_mevki, 'forvet') !== false || strpos($kucuk_mevki, 'kanat') !== false) $mevki = 'F';
                    
                    $guc = rand($savunma - 4, $hucum + 3);
                    if($guc > 99) $guc = 99;

                    $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $guc]);
                    $takim_oyuncu_sayisi++;
                    $toplam_oyuncu++;
                }
            }
        }
        
        if($takim_oyuncu_sayisi == 0) {
            echo "<div style='color:#e74c3c; margin-left:20px;'>⚠️ $takim_ad oyuncuları çekilemedi!</div>";
        } else {
            echo "<div style='color:#4facfe; margin-left:20px;'>↳ $takim_oyuncu_sayisi gerçek oyuncu eklendi.</div>";
        }

        // Hızlı istek atıp ban yememek için yarım saniye bekletiyoruz
        usleep(500000); 
    }

    echo "</div>";
    echo "<h2 style='color:#2ecc71; text-align:center; margin-top:20px;'>✅ ŞAMPİYONLAR LİGİ KURULUMU BAŞARILI!</h2>";
    echo "<p style='text-align:center;'>Logolar Transfermarkt sunucusundan çekildi. Toplam <strong>$toplam_oyuncu</strong> oyuncu sisteme işlendi.</p>";
    echo "<div style='text-align:center;'><a href='index.php' style='display:inline-block; margin-top:10px; padding:10px 20px; background:#ffd700; color:#000; text-decoration:none; font-weight:bold; border-radius:5px;'>Ana Sayfaya Dön</a></div>";

} catch (Exception $e) {
    echo "</div><h2 style='color:#e74c3c; text-align:center;'>❌ HATA OLUŞTU: " . $e->getMessage() . "</h2>";
}
echo "</body>";
?>