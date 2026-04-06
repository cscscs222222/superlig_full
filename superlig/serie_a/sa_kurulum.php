<?php
// ==============================================================================
// SERIE A - KURULUM VE GÜNCEL KADRO MOTORU
// ==============================================================================
include '../db.php';
set_time_limit(0); 
error_reporting(0);

// TABLOLARI KUR
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS it_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT DEFAULT 75, savunma INT DEFAULT 75, butce BIGINT DEFAULT 100000000, lig VARCHAR(50) DEFAULT 'Serie A',
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal',
        stadyum_seviye INT DEFAULT 1, altyapi_seviye INT DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS it_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT DEFAULT 75, yas INT DEFAULT 24, fiyat BIGINT DEFAULT 10000000, lig VARCHAR(50) DEFAULT 'Serie A',
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50'
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS it_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS it_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL, son_basin_haftasi INT DEFAULT 0 )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS it_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM it_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO it_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
    
} catch(Exception $e) {}

$hedef_takimlar = [
    ['id' => 506,   'name' => 'Juventus',    'tm_id' => '506'],
    ['id' => 46,    'name' => 'Inter Milan', 'tm_id' => '46'],
    ['id' => 5,     'name' => 'AC Milan',    'tm_id' => '5'],
    ['id' => 6195,  'name' => 'Napoli',      'tm_id' => '6195'],
    ['id' => 12,    'name' => 'Roma',        'tm_id' => '12'],
    ['id' => 398,   'name' => 'Lazio',       'tm_id' => '398'],
    ['id' => 430,   'name' => 'Fiorentina',  'tm_id' => '430'],
    ['id' => 800,   'name' => 'Atalanta',    'tm_id' => '800'],
    ['id' => 416,   'name' => 'Torino',      'tm_id' => '416'],
    ['id' => 1025,  'name' => 'Bologna',     'tm_id' => '1025'],
    ['id' => 410,   'name' => 'Udinese',     'tm_id' => '410'],
    ['id' => 6574,  'name' => 'Sassuolo',    'tm_id' => '6574'],
    ['id' => 749,   'name' => 'Empoli',      'tm_id' => '749'],
    ['id' => 276,   'name' => 'Verona',      'tm_id' => '276'],
    ['id' => 3524,  'name' => 'Monza',       'tm_id' => '3524'],
    ['id' => 1238,  'name' => 'Lecce',       'tm_id' => '1238'],
    ['id' => 252,   'name' => 'Genoa',       'tm_id' => '252'],
    ['id' => 1717,  'name' => 'Cagliari',    'tm_id' => '1717'],
    ['id' => 3629,  'name' => 'Frosinone',   'tm_id' => '3629'],
    ['id' => 2697,  'name' => 'Salernitana', 'tm_id' => '2697'],
];

// --- BAŞLATMA ---
if(isset($_GET['basla'])) {
    $pdo->exec("TRUNCATE TABLE it_takimlar");
    $pdo->exec("TRUNCATE TABLE it_oyuncular");
    $pdo->exec("TRUNCATE TABLE it_maclar");
    $pdo->exec("UPDATE it_ayar SET hafta = 1, kullanici_takim_id = NULL");

    foreach($hedef_takimlar as $t) {
        $logo_url = "https://tmssl.akamaized.net/images/wappen/head/" . $t['tm_id'] . ".png";
        $stmt = $pdo->prepare("INSERT INTO it_takimlar (id, takim_adi, logo, lig) VALUES (?, ?, ?, 'Serie A')");
        $stmt->execute([$t['id'], $t['name'], $logo_url]);
    }
    
    header("Location: sa_kurulum.php?idx=0");
    exit;
}

// --- İŞLEM EKRANI ---
if(isset($_GET['idx'])) {
    $idx = (int)$_GET['idx'];
    
    echo "<body style='background:#0d0d0d; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";
    echo "<link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>";

    if ($idx >= count($hedef_takimlar)) {
        echo "<h1 style='color:#059669;'><i class='fa-solid fa-check-circle'></i> SERIE A KADROLARI HAZIR!</h1>";
        echo "<p>Tüm Serie A takımlarının kadroları çekildi ve En İyi 11'ler dizildi.</p>";
        echo "<a href='serie_a.php' style='display:inline-block; padding:15px 30px; background:#059669; color:#0d0d0d; font-weight:bold; border-radius:8px; text-decoration:none;'>Serie A'ya Git</a>";
        exit;
    }

    $takim = $hedef_takimlar[$idx];
    $takim_adi = $takim['name'];
    $takim_id = $takim['id'];
    $tm_id = $takim['tm_id'];

    echo "<h2 style='color:#10b981;'>⚙️ AKTİF KADROLAR ÇEKİLİYOR...</h2>";
    echo "<div style='background:#1a1a1a; padding:20px; border-radius:10px; display:inline-block; margin-top:20px; text-align:left; border:1px solid rgba(5,150,105,0.3); min-width: 400px;'>";
    echo "<h3 style='margin-top:0; border-bottom: 1px solid #333; padding-bottom: 10px;'><img src='https://tmssl.akamaized.net/images/wappen/head/{$tm_id}.png' style='width:30px; vertical-align:middle; margin-right:10px;'> <span style='color:#059669;'>$takim_adi</span></h3>";

    $pdo->exec("DELETE FROM it_oyuncular WHERE takim_id = $takim_id");

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
        
        $stmt_oyuncu = $pdo->prepare("INSERT INTO it_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, form) VALUES (?, ?, ?, ?, ?, ?, 'Serie A', ?)");
        
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

                // MEGA YILDIZLARI KORUMA (SERIE A)
                $isim_kucuk = mb_strtolower($isim, 'UTF-8');
                if(strpos($isim_kucuk, 'vlahovic') !== false) $ovr = 90;
                if(strpos($isim_kucuk, 'lautaro') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'leao') !== false) $ovr = 89;
                if(strpos($isim_kucuk, 'osimhen') !== false) $ovr = 91;
                if(strpos($isim_kucuk, 'dybala') !== false) $ovr = 88;
                if(strpos($isim_kucuk, 'immobile') !== false) $ovr = 88;
                if(strpos($isim_kucuk, 'chiesa') !== false) $ovr = 87;
                if(strpos($isim_kucuk, 'donnarumma') !== false) $ovr = 90;

                $yas = rand(18, 34);
                $form = rand(5, 9);
                $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $ovr, $yas, $deger, $form]);
                $oyuncu_sayisi++;
            }
        }
        
        echo "<div style='color:#10b981; margin-bottom:10px;'><i class='fa-solid fa-users'></i> <strong>$oyuncu_sayisi</strong> oyuncu çekildi!</div>";

        $mevkiler_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($mevkiler_limit as $mvk => $limit) {
            $en_iyiler = $pdo->query("SELECT id FROM it_oyuncular WHERE takim_id = $takim_id AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $limit")->fetchAll();
            foreach($en_iyiler as $iyi) {
                $pdo->exec("UPDATE it_oyuncular SET ilk_11 = 1 WHERE id = " . $iyi['id']);
            }
        }
        
        $pdo->exec("UPDATE it_oyuncular SET yedek = 1 WHERE takim_id = $takim_id AND ilk_11 = 0 ORDER BY ovr DESC LIMIT 12");
        
        $ort = $pdo->query("SELECT AVG(ovr) FROM it_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
        $guc = round($ort ?: 78);
        $pdo->exec("UPDATE it_takimlar SET hucum = $guc, savunma = $guc WHERE id = $takim_id");

        echo "<div style='color:#22c55e;'><i class='fa-solid fa-chess-board'></i> İlk 11 dizildi. (OVR: $guc)</div>";
    } else {
        echo "<div style='color:#10b981;'>⚠️ Siteye bağlanılamadı, bu takım atlandı.</div>";
    }

    echo "</div>";

    $sonraki_idx = $idx + 1;
    $yuzde = round(($sonraki_idx / 20) * 100);
    echo "<meta http-equiv='refresh' content='2;url=?idx=$sonraki_idx'>";
    echo "<p style='margin-top:20px; color:#10b981;'>2 Saniye içinde sıradaki takıma geçiliyor... Tamamlanan: %$yuzde</p>";
    
    echo "</body>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Serie A - Güncel Kadro Motoru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0d0d0d; color: #fff; font-family: 'Segoe UI', sans-serif; }
        .hero-box { background: linear-gradient(135deg, #10b981, #059669); padding: 50px; text-align: center; border-radius: 15px; margin-top: 100px; box-shadow: 0 10px 40px rgba(16,185,129,0.3);}
    </style>
</head>
<body>

<div class="container">
    <div class="hero-box">
        <div style="font-size: 4rem; margin-bottom: 15px;">🇮🇹</div>
        <h1 class="fw-bold text-white text-uppercase" style="letter-spacing: 2px;">SERIE A GÜNCEL KADRO PAKETİ</h1>
        <p class="fs-5 text-light opacity-75">Tüm eski oyuncular kalıcı olarak silinecek, 20 İtalyan kulübünün <strong>tam ve en güncel aktif kadroları</strong> çekilip En İyi 11'leri otomatik dizilecektir.</p>
        
        <a href="?basla=1" class="btn btn-dark btn-lg fw-bold px-5 py-3 mt-4 rounded-pill shadow-lg" onclick="return confirm('DİKKAT: Mevcut tüm Serie A oyuncu verileri silinecek! Devam etmek istiyor musunuz?')">
            <i class="fa-solid fa-power-off"></i> SERIE A KADRO MOTORUNU BAŞLAT
        </a>
        
        <div class="mt-3">
            <a href="serie_a.php" class="btn btn-outline-light btn-sm">Kurulum yapmadan Serie A'ya Git</a>
        </div>
    </div>
</div>

</body>
</html>
