<?php
// ==============================================================================
// LA LIGA - KURULUM VE GÜNCEL KADRO MOTORU
// ==============================================================================
include '../db.php';
set_time_limit(0); 
error_reporting(0);

// TABLOLARI KUR
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT DEFAULT 75, savunma INT DEFAULT 75, butce BIGINT DEFAULT 100000000, lig VARCHAR(50) DEFAULT 'La Liga',
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal',
        stadyum_seviye INT DEFAULT 1, altyapi_seviye INT DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS es_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT DEFAULT 75, yas INT DEFAULT 24, fiyat BIGINT DEFAULT 10000000, lig VARCHAR(50) DEFAULT 'La Liga',
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50'
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL, son_basin_haftasi INT DEFAULT 0 )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM es_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO es_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
    
} catch(Exception $e) {}

$hedef_takimlar = [
    ['id' => 418,   'name' => 'Real Madrid',        'tm_id' => '418'],
    ['id' => 131,   'name' => 'FC Barcelona',        'tm_id' => '131'],
    ['id' => 13,    'name' => 'Atlético Madrid',     'tm_id' => '13'],
    ['id' => 621,   'name' => 'Athletic Bilbao',     'tm_id' => '621'],
    ['id' => 681,   'name' => 'Real Sociedad',       'tm_id' => '681'],
    ['id' => 1050,  'name' => 'Villarreal CF',       'tm_id' => '1050'],
    ['id' => 150,   'name' => 'Real Betis',          'tm_id' => '150'],
    ['id' => 940,   'name' => 'Celta Vigo',          'tm_id' => '940'],
    ['id' => 12321, 'name' => 'Girona FC',           'tm_id' => '12321'],
    ['id' => 1049,  'name' => 'Valencia CF',         'tm_id' => '1049'],
    ['id' => 368,   'name' => 'Sevilla FC',          'tm_id' => '368'],
    ['id' => 714,   'name' => 'Espanyol Barcelona',  'tm_id' => '714'],
    ['id' => 331,   'name' => 'CA Osasuna',          'tm_id' => '331'],
    ['id' => 367,   'name' => 'Rayo Vallecano',      'tm_id' => '367'],
    ['id' => 3368,  'name' => 'Levante',             'tm_id' => '3368'],
    ['id' => 3709,  'name' => 'Getafe CF',           'tm_id' => '3709'],
    ['id' => 1531,  'name' => 'Elche CF',            'tm_id' => '1531'],
    ['id' => 237,   'name' => 'RCD Mallorca',        'tm_id' => '237'],
    ['id' => 1108,  'name' => 'Deportivo Alavés',    'tm_id' => '1108'],
    ['id' => 2497,  'name' => 'Real Oviedo',         'tm_id' => '2497'],
];

// --- BAŞLATMA ---
if(isset($_GET['basla'])) {
    $pdo->exec("TRUNCATE TABLE es_takimlar");
    $pdo->exec("TRUNCATE TABLE es_oyuncular");
    $pdo->exec("TRUNCATE TABLE es_maclar");
    $pdo->exec("UPDATE es_ayar SET hafta = 1, kullanici_takim_id = NULL");

    foreach($hedef_takimlar as $t) {
        $logo_url = "https://tmssl.akamaized.net/images/wappen/head/" . $t['tm_id'] . ".png";
        $stmt = $pdo->prepare("INSERT INTO es_takimlar (id, takim_adi, logo, lig) VALUES (?, ?, ?, 'La Liga')");
        $stmt->execute([$t['id'], $t['name'], $logo_url]);
    }
    
    header("Location: ll_kurulum.php?idx=0");
    exit;
}

// --- İŞLEM EKRANI ---
if(isset($_GET['idx'])) {
    $idx = (int)$_GET['idx'];
    
    echo "<body style='background:#150003; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>";

    if ($idx >= count($hedef_takimlar)) {
        echo "<h1 style='color:#f5c518;'><i class='fa-solid fa-check-circle'></i> LA LIGA KADROLARI HAZIR!</h1>";
        echo "<p>Tüm La Liga takımlarının kadroları çekildi ve En İyi 11'ler dizildi.</p>";
        echo "<a href='la_liga.php' style='display:inline-block; padding:15px 30px; background:#f5c518; color:#1a0008; font-weight:bold; border-radius:8px; text-decoration:none;'>La Liga'ya Git</a>";
        exit;
    }

    $takim = $hedef_takimlar[$idx];
    $takim_adi = $takim['name'];
    $takim_id = $takim['id'];
    $tm_id = $takim['tm_id'];

    echo "<h2 style='color:#c8102e;'>⚙️ AKTİF KADROLAR ÇEKİLİYOR...</h2>";
    echo "<div style='background:#2a0008; padding:20px; border-radius:10px; display:inline-block; margin-top:20px; text-align:left; border:1px solid rgba(245,197,24,0.3); min-width: 400px;'>";
    echo "<h3 style='margin-top:0; border-bottom: 1px solid #333; padding-bottom: 10px;'><img src='https://tmssl.akamaized.net/images/wappen/head/{$tm_id}.png' style='width:30px; vertical-align:middle; margin-right:10px;'> <span style='color:#f5c518;'>$takim_adi</span></h3>";

    $pdo->exec("DELETE FROM es_oyuncular WHERE takim_id = $takim_id");

    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$tm_id}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $html = curl_exec($ch);
    curl_close($ch);

    $oyuncu_sayisi = 0;

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//table[@class='items']//tbody/tr");
        
        $stmt_oyuncu = $pdo->prepare("INSERT INTO es_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, form) VALUES (?, ?, ?, ?, ?, ?, 'La Liga', ?)");
        
        foreach($rows as $row) {
            $nameNode = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
            $posNode = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
            $valNode = $xpath->query(".//td[@class='rechts hauptlink']", $row)->item(0);
            
            if ($nameNode && $posNode) {
                $isim = trim($nameNode->nodeValue);
                $mevki_metin = mb_strtolower(trim($posNode->nodeValue), 'UTF-8');
                
                $mevki = 'OS'; 
                if(strpos($mevki_metin, 'kaleci') !== false) $mevki = 'K';
                elseif(strpos($mevki_metin, 'bek') !== false || strpos($mevki_metin, 'stoper') !== false || strpos($mevki_metin, 'defans') !== false) $mevki = 'D';
                elseif(strpos($mevki_metin, 'santrafor') !== false || strpos($mevki_metin, 'forvet') !== false || strpos($mevki_metin, 'kanat') !== false) $mevki = 'F';

                $deger = 5000000;
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

                $ovr = 65;
                if($deger >= 100000000) $ovr = rand(90, 92);
                elseif($deger >= 60000000) $ovr = rand(87, 90);
                elseif($deger >= 40000000) $ovr = rand(84, 86);
                elseif($deger >= 25000000) $ovr = rand(81, 83);
                elseif($deger >= 15000000) $ovr = rand(77, 80);
                elseif($deger >= 8000000) $ovr = rand(74, 76);
                elseif($deger >= 3000000) $ovr = rand(70, 73);
                elseif($deger >= 1000000) $ovr = rand(67, 69);
                else $ovr = rand(62, 66);

                // MEGA YILDIZLARI KORUMA
                $isim_kucuk = mb_strtolower($isim, 'UTF-8');
                if(strpos($isim_kucuk, 'vinicius') !== false) $ovr = 92;
                if(strpos($isim_kucuk, 'bellingham') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'yamal') !== false) $ovr = 87;
                if(strpos($isim_kucuk, 'pedri') !== false) $ovr = 87;
                if(strpos($isim_kucuk, 'griezmann') !== false) $ovr = 88;
                if(strpos($isim_kucuk, 'oblak') !== false) $ovr = 88;
                if(strpos($isim_kucuk, 'lewandowski') !== false) $ovr = 89;
                if(strpos($isim_kucuk, 'courtois') !== false) $ovr = 90;

                $yas = rand(18, 34);
                $form = rand(5, 9);
                $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $ovr, $yas, $deger, $form]);
                $oyuncu_sayisi++;
            }
        }
        
        echo "<div style='color:#f59e0b; margin-bottom:10px;'><i class='fa-solid fa-users'></i> <strong>$oyuncu_sayisi</strong> oyuncu çekildi!</div>";

        $mevkiler_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($mevkiler_limit as $mvk => $limit) {
            $en_iyiler = $pdo->query("SELECT id FROM es_oyuncular WHERE takim_id = $takim_id AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $limit")->fetchAll();
            foreach($en_iyiler as $iyi) {
                $pdo->exec("UPDATE es_oyuncular SET ilk_11 = 1 WHERE id = " . $iyi['id']);
            }
        }
        
        $pdo->exec("UPDATE es_oyuncular SET yedek = 1 WHERE takim_id = $takim_id AND ilk_11 = 0 ORDER BY ovr DESC LIMIT 12");
        
        $ort = $pdo->query("SELECT AVG(ovr) FROM es_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
        $guc = round($ort ?: 78);
        $pdo->exec("UPDATE es_takimlar SET hucum = $guc, savunma = $guc WHERE id = $takim_id");

        echo "<div style='color:#22c55e;'><i class='fa-solid fa-chess-board'></i> İlk 11 dizildi. (OVR: $guc)</div>";
    } else {
        echo "<div style='color:#f59e0b;'>⚠️ Siteye bağlanılamadı, bu takım atlandı.</div>";
    }

    echo "</div>";

    $sonraki_idx = $idx + 1;
    $yuzde = round(($sonraki_idx / 20) * 100);
    echo "<meta http-equiv='refresh' content='2;url=?idx=$sonraki_idx'>";
    echo "<p style='margin-top:20px; color:#f59e0b;'>2 Saniye içinde sıradaki takıma geçiliyor... Tamamlanan: %$yuzde</p>";
    
    echo "</body>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>La Liga - Güncel Kadro Motoru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #150003; color: #fff; font-family: 'Segoe UI', sans-serif; }
        .hero-box { background: linear-gradient(135deg, #c8102e, #f5c518); padding: 50px; text-align: center; border-radius: 15px; margin-top: 100px; box-shadow: 0 10px 40px rgba(200,16,46,0.3);}
    </style>
</head>
<body>

<div class="container">
    <div class="hero-box">
        <div style="font-size: 4rem; margin-bottom: 15px;">🇪🇸</div>
        <h1 class="fw-bold text-white text-uppercase" style="letter-spacing: 2px;">LA LIGA GÜNCEL KADRO PAKETİ</h1>
        <p class="fs-5 text-light opacity-75">Tüm eski oyuncular kalıcı olarak silinecek, 20 İspanyol kulübünün <strong>tam ve en güncel aktif kadroları</strong> çekilip En İyi 11'leri otomatik dizilecektir.</p>
        
        <a href="?basla=1" class="btn btn-dark btn-lg fw-bold px-5 py-3 mt-4 rounded-pill shadow-lg" onclick="return confirm('DİKKAT: Mevcut tüm La Liga oyuncu verileri silinecek! Devam etmek istiyor musunuz?')">
            <i class="fa-solid fa-power-off"></i> LA LIGA KADRO MOTORUNU BAŞLAT
        </a>
        
        <div class="mt-3">
            <a href="la_liga.php" class="btn btn-outline-light btn-sm">Kurulum yapmadan La Liga'ya Git</a>
        </div>
    </div>
</div>

</body>
</html>
