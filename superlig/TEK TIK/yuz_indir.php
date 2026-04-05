<?php
include 'db.php';
set_time_limit(0); 
error_reporting(0); 

// 1. Resimleri kaydedeceğimiz klasörü oluştur
$klasor = 'uploads/yuzler';
if (!file_exists($klasor)) {
    mkdir($klasor, 0777, true);
}

echo "<body style='background:#121418; color:#fff; font-family:sans-serif; padding:30px; text-align:center;'>";
echo "<h2 style='color:#4facfe;'>🚀 Gerçek Yüzler API'den İndiriliyor... Lütfen Bekleyin</h2>";
echo "<p>Bu işlem oyuncu yüzlerini doğrudan '{$klasor}' klasörüne fiziksel olarak indirecektir.</p>";

$oyuncular = $pdo->query("SELECT id, isim FROM oyuncular")->fetchAll();
$guncellenen = 0;
$yedek_kullanilan = 0;

foreach($oyuncular as $o) {
    // İsmi temizle
    $temiz_isim = str_replace(
        ['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'], 
        ['c','g','i','o','s','u','C','G','I','O','S','U'], 
        $o['isim']
    );
    $arama_isim = urlencode($temiz_isim);
    
    // TheSportsDB Açık API'si (Bot engeli yoktur, doğrudan JSON verir)
    $api_url = "https://www.thesportsdb.com/api/v1/json/3/searchplayers.php?p=" . $arama_isim;
    
    $json_veri = @file_get_contents($api_url);
    $data = json_decode($json_veri, true);
    
    $resim_indirildi = false;

    // Eğer oyuncu veritabanında bulunduysa
    if($data && isset($data['player']) && $data['player'] !== null) {
        $oyuncu_veri = $data['player'][0]; // İlk eşleşen oyuncuyu al
        
        // Şeffaf yüz (Cutout) veya Normal Profil Fotoğrafı (Thumb)
        $resim_url = $oyuncu_veri['strCutout'];
        if(empty($resim_url)) $resim_url = $oyuncu_veri['strThumb'];
        
        if(!empty($resim_url)) {
            $hedef_dosya = $klasor . "/oyuncu_" . $o['id'] . ".png";
            $resim_icerik = @file_get_contents($resim_url);
            
            if($resim_icerik) {
                file_put_contents($hedef_dosya, $resim_icerik); // Resmi klasöre KAYDET
                $pdo->prepare("UPDATE oyuncular SET yuz_url = ? WHERE id = ?")->execute([$hedef_dosya, $o['id']]);
                $guncellenen++;
                $resim_indirildi = true;
            }
        }
    }
    
    // YEDEK SİSTEM: API'de yüz yoksa, oyuncunun adının baş harflerinden şık bir portre İNDİR!
    if(!$resim_indirildi) {
        $yedek_url = "https://ui-avatars.com/api/?name=" . urlencode($o['isim']) . "&background=random&color=fff&size=240&bold=true&rounded=true";
        $hedef_dosya = $klasor . "/oyuncu_" . $o['id'] . "_yedek.png";
        $resim_icerik = @file_get_contents($yedek_url);
        
        if($resim_icerik) {
            file_put_contents($hedef_dosya, $resim_icerik); // Yedek resmi de klasöre KAYDET
            $pdo->prepare("UPDATE oyuncular SET yuz_url = ? WHERE id = ?")->execute([$hedef_dosya, $o['id']]);
            $yedek_kullanilan++;
        }
    }
}

echo "<div style='margin-top:30px; padding:20px; background:#1e2229; border-radius:10px; display:inline-block;'>";
echo "<h3 style='color:#27ae60;'>✅ İndirme İşlemi Tamamen Bitti!</h3>";
echo "<p><strong>$guncellenen</strong> gerçek oyuncu yüzü indirildi.</p>";
echo "<p><strong>$yedek_kullanilan</strong> oyuncu için HD baş harf portresi oluşturuldu.</p>";
echo "<p>Tüm resimler bilgisayarındaki <code>$klasor</code> konumuna kalıcı olarak kaydedildi.</p>";
echo "<a href='puan.php' style='display:inline-block; margin-top:15px; padding:10px 25px; background:#00f2fe; color:#000; text-decoration:none; border-radius:5px; font-weight:bold;'>Hadi Yüzlere Bakalım!</a>";
echo "</div>";
echo "</body>";
?>