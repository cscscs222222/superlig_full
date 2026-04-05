<?php
include 'db.php';
set_time_limit(0); 
error_reporting(0); 

echo "<body style='background:#121418; color:#fff; font-family:sans-serif; padding:30px;'>";
echo "<h2 style='color:#4facfe;'>Futwiz (FC 25) Yüz Tarama Botu Çalışıyor... Lütfen Bekleyin ⏳</h2>";
echo "<p>Oyuncuların ilk ve soy isimleri analiz edilerek taranıyor, sayfayı kapatmayın.</p>";

$oyuncular = $pdo->query("SELECT id, isim FROM oyuncular")->fetchAll();
$guncellenen = 0;

// Gelişmiş Tarayıcı Simülasyonu (Gzip Çözücü Eklendi)
function html_getir($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // ÇOK ÖNEMLİ: Sitenin gönderdiği sıkıştırılmış şifreli veriyi (Gzip) çözer!
    curl_setopt($ch, CURLOPT_ENCODING, ""); 
    
    // Güvenlik duvarlarını aşmak için Google'dan geliyormuşuz gibi davranıyoruz
    curl_setopt($ch, CURLOPT_REFERER, "https://www.google.com/");
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

foreach($oyuncular as $o) {
    // 1. İSMİ SADELEŞTİR (Örn: "Mauro Emanuel Icardi" -> "Mauro Icardi" yapar)
    $isim_parcalari = explode(" ", $o['isim']);
    if(count($isim_parcalari) > 1) {
        $arama_metni = $isim_parcalari[0] . " " . end($isim_parcalari);
    } else {
        $arama_metni = $o['isim'];
    }

    // 2. TÜRKÇE KARAKTERLERİ İNGİLİZCEYE ÇEVİR VE BOŞLUKLARI '+' YAP
    $temiz_isim = str_replace(
        ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü', ' '], 
        ['c','g','i','o','s','u','C','G','I','O','S','U', '+'], 
        $arama_metni
    );
    
    // Futwiz FC 25 veritabanında oyuncuyu arat
    $url = "https://www.futwiz.com/en/fc25/players?search=" . $temiz_isim;
    $html = html_getir($url);
    
    if($html) {
        // Futwiz resim URL kuralı: <img src="https://cdn.futwiz.com/assets/img/fc25/faces/xxxx.png"
        if(preg_match('/<img[^>]+src="([^"]+\/faces\/[^"]+\.(png|webp|jpg))"/i', $html, $matches) || 
           preg_match('/<img[^>]+src="([^"]+cdn\.futwiz\.com[^"]+faces[^"]+)"/i', $html, $matches)) {
            
            $yuz_url = $matches[1];
            
            $stmt = $pdo->prepare("UPDATE oyuncular SET yuz_url = ? WHERE id = ?");
            $stmt->execute([$yuz_url, $o['id']]);
            $guncellenen++;
        }
    }
    
    // Ban yememek için saniyenin beşte biri kadar bekle
    usleep(200000); 
}

echo "<h3 style='color:#27ae60;'>✅ İşlem Tamamlandı! Toplam $guncellenen oyuncunun gerçek yüzü Futwiz üzerinden çekildi.</h3>";
echo "<a href='puan.php' style='display:inline-block; padding:10px 20px; background:#ffd700; color:#000; text-decoration:none; border-radius:5px; font-weight:bold;'>Puan Durumuna Dön ve İncele</a>";
echo "</body>";
?>