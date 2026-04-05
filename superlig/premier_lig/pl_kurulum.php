<?php
include '../db.php';
set_time_limit(0); 
error_reporting(0);

// TABLOLARI KUR
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_takimlar (
        id INT PRIMARY KEY, takim_adi VARCHAR(255), ulke VARCHAR(100) DEFAULT 'İngiltere', logo VARCHAR(500), 
        hucum INT DEFAULT 75, savunma INT DEFAULT 75, butce BIGINT DEFAULT 100000000, kimya INT DEFAULT 50, 
        dizilis VARCHAR(20) DEFAULT '4-3-3', oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', pres VARCHAR(20) DEFAULT 'Orta', 
        tempo VARCHAR(20) DEFAULT 'Normal', pas_stili VARCHAR(20) DEFAULT 'Kısa Pas', defans_cizgisi VARCHAR(20) DEFAULT 'Normal', 
        hava_tercihi VARCHAR(20) DEFAULT 'Yağmurlu', zemin_tercihi VARCHAR(20) DEFAULT 'Kısa Çim', 
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(255), mevki VARCHAR(10), ovr INT DEFAULT 75, 
        guc INT DEFAULT 75, form INT DEFAULT 6, moral INT DEFAULT 80, fitness INT DEFAULT 100, ilk_11 TINYINT(1) DEFAULT 0, 
        yedek TINYINT(1) DEFAULT 0, ozel_gorev VARCHAR(50) DEFAULT '-', fiyat BIGINT DEFAULT 25000000, 
        sakatlik_durumu VARCHAR(50) DEFAULT 'Sağlam', yas INT DEFAULT 24
    )");
} catch(Exception $e) {}

$hedef_takimlar = [
    ['id' => 281, 'name' => 'Manchester City'], ['id' => 631, 'name' => 'Chelsea FC'],
    ['id' => 11, 'name' => 'Arsenal FC'], ['id' => 31, 'name' => 'Liverpool FC'],
    ['id' => 148, 'name' => 'Tottenham Hotspur'], ['id' => 985, 'name' => 'Manchester United'],
    ['id' => 762, 'name' => 'Newcastle United'], ['id' => 703, 'name' => 'Nottingham Forest'],
    ['id' => 405, 'name' => 'Aston Villa'], ['id' => 873, 'name' => 'Crystal Palace'],
    ['id' => 989, 'name' => 'AFC Bournemouth'], ['id' => 1237, 'name' => 'Brighton & Hove Albion'],
    ['id' => 1148, 'name' => 'Brentford FC'], ['id' => 29, 'name' => 'Everton FC'],
    ['id' => 931, 'name' => 'Fulham FC'], ['id' => 289, 'name' => 'Sunderland AFC'],
    ['id' => 379, 'name' => 'West Ham United'], ['id' => 399, 'name' => 'Leeds United'],
    ['id' => 543, 'name' => 'Wolverhampton Wanderers'], ['id' => 1132, 'name' => 'Burnley FC']
];

// BAŞLATMA EKRANI
if(isset($_GET['basla'])) {
    $pdo->exec("TRUNCATE TABLE pl_takimlar");
    $pdo->exec("TRUNCATE TABLE pl_oyuncular");

    foreach($hedef_takimlar as $t) {
        $logo_url = "https://tmssl.akamaized.net/images/wappen/head/" . $t['id'] . ".png";
        $pdo->prepare("INSERT INTO pl_takimlar (id, takim_adi, logo) VALUES (?, ?, ?)")->execute([$t['id'], $t['name'], $logo_url]);
    }
    header("Location: pl_kurulum.php?idx=0");
    exit;
}

// İŞLEM EKRANI
if(isset($_GET['idx'])) {
    $idx = (int)$_GET['idx'];
    
    echo "<body style='background:#0b0c10; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";

    if ($idx >= count($hedef_takimlar)) {
        echo "<h1 style='color:#00ff85;'><i class='fa-solid fa-check-circle'></i> PREMIER LEAGUE KADROLARI GÜNCELLENDİ!</h1>";
        echo "<p>Eski oyuncular silindi, tüm 20 takımın en güncel aktif kadroları çekildi ve İlk 11'ler dizildi.</p>";
        echo "<a href='premier_league.php' style='display:inline-block; padding:15px 30px; background:#00ff85; color:#000; font-weight:bold; border-radius:8px; text-decoration:none;'>Lige Git ve Fikstürü Çek</a>";
        exit;
    }

    $takim = $hedef_takimlar[$idx];
    $takim_adi = $takim['name'];
    $takim_id = $takim['id'];

    echo "<h2 style='color:#ff2882;'>⚙️ AKTİF KADROLAR ÇEKİLİYOR...</h2>";
    echo "<div style='background:#1a1c23; padding:20px; border-radius:10px; display:inline-block; margin-top:20px; text-align:left; border:1px solid #37003c; min-width: 400px;'>";
    echo "<h3 style='margin-top:0; border-bottom: 1px solid #333; padding-bottom: 10px;'><img src='https://tmssl.akamaized.net/images/wappen/head/{$takim_id}.png' style='width:30px; vertical-align:middle; margin-right:10px;'> İşlenen Takım: <span style='color:#00ff85;'>$takim_adi</span></h3>";

    // ESKİ OYUNCULARI TEMİZLE (Kalıntıları önler, gidenleri yok eder)
    $pdo->exec("DELETE FROM pl_oyuncular WHERE takim_id = $takim_id");

    // TRANSFERMARKT'TAN HTML ÇEK (saison_id KULLANMADAN DİREKT GÜNCEL KADRO)
    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$takim_id}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//table[@class='items']//tbody/tr");
        
        $stmt_oyuncu = $pdo->prepare("INSERT INTO pl_oyuncular (takim_id, isim, mevki, guc, ovr, fiyat, form) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $oyuncu_sayisi = 0;
        
        foreach($rows as $row) {
            $nameNode = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
            $posNode = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
            $valNode = $xpath->query(".//td[@class='rechts hauptlink']", $row)->item(0);
            
            if ($nameNode && $posNode) {
                $isim = trim($nameNode->nodeValue);
                $mevki_metin = mb_strtolower(trim($posNode->nodeValue), 'UTF-8');
                
                // MEVKİ DÖNÜŞÜMÜ
                $mevki = 'OS'; 
                if(strpos($mevki_metin, 'kaleci') !== false) $mevki = 'K';
                elseif(strpos($mevki_metin, 'bek') !== false || strpos($mevki_metin, 'stoper') !== false || strpos($mevki_metin, 'defans') !== false) $mevki = 'D';
                elseif(strpos($mevki_metin, 'santrafor') !== false || strpos($mevki_metin, 'forvet') !== false || strpos($mevki_metin, 'kanat') !== false) $mevki = 'F';

                // PİYASA DEĞERİ (MARKET VALUE) PARÇALAYICI
                $deger = 5000000; // Varsayılan 5M
                if ($valNode) {
                    $val_metin = mb_strtolower(trim($valNode->nodeValue), 'UTF-8');
                    $carpan = 1;
                    if(strpos($val_metin, 'mil') !== false || strpos($val_metin, 'm') !== false) $carpan = 1000000;
                    elseif(strpos($val_metin, 'bin') !== false || strpos($val_metin, 'k') !== false) $carpan = 1000;
                    
                    preg_match('/[0-9,\.]+/', $val_metin, $eslesme);
                    if(!empty($eslesme)) {
                        $sayi = (float)str_replace(',', '.', $eslesme[0]);
                        $deger = $sayi * $carpan;
                    }
                }

                // PİYASA DEĞERİNİ EA FC REYTİNGİNE (OVR) ÇEVİRME
                $ovr = 65;
                if($deger >= 90000000) $ovr = rand(89, 91);     
                elseif($deger >= 60000000) $ovr = rand(86, 88);  
                elseif($deger >= 40000000) $ovr = rand(83, 85);  
                elseif($deger >= 25000000) $ovr = rand(80, 82);  
                elseif($deger >= 15000000) $ovr = rand(77, 79);  
                elseif($deger >= 8000000) $ovr = rand(74, 76);   
                elseif($deger >= 3000000) $ovr = rand(70, 73);   
                elseif($deger >= 1000000) $ovr = rand(67, 69);   
                else $ovr = rand(62, 66);                        

                // MEGA YILDIZLARI MANUEL KORUMA
                $isim_kucuk = mb_strtolower($isim, 'UTF-8');
                if(strpos($isim_kucuk, 'haaland') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'de bruyne') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'salah') !== false) $ovr = 89;
                if(strpos($isim_kucuk, 'rodri') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'van dijk') !== false) $ovr = 89;
                if(strpos($isim_kucuk, 'saka') !== false) $ovr = 87;
                if(strpos($isim_kucuk, 'foden') !== false) $ovr = 88;
                if(strpos($isim_kucuk, 'palmer') !== false) $ovr = 86;

                $form = rand(5, 9);
                $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $ovr, $ovr, $deger, $form]);
                $oyuncu_sayisi++;
            }
        }
        
        echo "<div style='color:#a0a5b1; margin-bottom:10px;'><i class='fa-solid fa-users'></i> Güncel olan <strong>$oyuncu_sayisi</strong> oyuncu çekildi. Eski oyuncular silindi!</div>";

        // İLK 11'İ BELİRLE VE DİZİLİŞ YAP (1 Kaleci, 4 Defans, 3 Orta Saha, 3 Forvet)
        $mevkiler_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($mevkiler_limit as $mvk => $limit) {
            $en_iyiler = $pdo->query("SELECT id FROM pl_oyuncular WHERE takim_id = $takim_id AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $limit")->fetchAll();
            foreach($en_iyiler as $iyi) {
                $pdo->exec("UPDATE pl_oyuncular SET ilk_11 = 1 WHERE id = " . $iyi['id']);
            }
        }
        
        // YEDEKLERİ BELİRLE (Kalan en iyi 12 kişi)
        $pdo->exec("UPDATE pl_oyuncular SET yedek = 1 WHERE takim_id = $takim_id AND ilk_11 = 0 ORDER BY ovr DESC LIMIT 12");
        
        // TAKIMIN GENEL GÜCÜNÜ HESAPLA
        $ort = $pdo->query("SELECT AVG(ovr) FROM pl_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
        $guc = round($ort ?: 78);
        $pdo->exec("UPDATE pl_takimlar SET hucum = $guc, savunma = $guc WHERE id = $takim_id");

        echo "<div style='color:#00ff85;'><i class='fa-solid fa-chess-board'></i> Takımın En Güçlü İlk 11'i otonom olarak sahalara dizildi. (OVR: $guc)</div>";

    } else {
        echo "<div style='color:#ff2882;'>⚠️ Siteye bağlanılamadı. Bu takım geçici olarak atlanıyor.</div>";
    }

    echo "</div>";

    $sonraki_idx = $idx + 1;
    $yuzde = round(($sonraki_idx / 20) * 100);
    echo "<meta http-equiv='refresh' content='2;url=?idx=$sonraki_idx'>";
    echo "<p style='margin-top:20px; color:#a0a5b1;'>2 Saniye içinde sıradaki takıma geçiliyor... Tamamlanan: %$yuzde</p>";
    
    echo "</body>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Premier League - Güncel Kadro Motoru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0c10; color: #fff; font-family: 'Segoe UI', sans-serif; }
        .hero-box { background: linear-gradient(135deg, #37003c, #ff2882); padding: 50px; text-align: center; border-radius: 15px; margin-top: 100px; box-shadow: 0 10px 40px rgba(255,40,130,0.3);}
    </style>
</head>
<body>

<div class="container">
    <div class="hero-box">
        <i class="fa-solid fa-crown fa-4x text-white mb-3"></i>
        <h1 class="fw-bold text-white text-uppercase" style="letter-spacing: 2px;">PREMIER LEAGUE GÜNCEL KADRO PAKETİ</h1>
        <p class="fs-5 text-light opacity-75">Tüm eski oyuncular kalıcı olarak silinecek, 20 İngiliz kulübünün <strong>tam ve en güncel aktif kadroları</strong> çekilip En İyi 11'leri otomatik dizilecektir.</p>
        
        <a href="?basla=1" class="btn btn-light btn-lg fw-bold px-5 py-3 mt-4 text-dark rounded-pill shadow-lg">
            <i class="fa-solid fa-power-off"></i> GÜNCEL KADRO MOTORUNU BAŞLAT
        </a>
    </div>
</div>

</body>
</html>