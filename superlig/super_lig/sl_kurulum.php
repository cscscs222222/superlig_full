<?php
// 1. HATA RAPORLAMA VE BAĞLANTI
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists('../db.php')) {
    include '../db.php';
} else {
    die("Kritik Hata: db.php bulunamadı! Lütfen dosya düzenini kontrol edin.");
}

set_time_limit(0); 

// 2. TABLOLARI SENİN ATTIĞIN SÜTUNLARLA SIFIRDAN KUR
if(isset($_GET['basla'])) {
    // Mevcut tabloları temizle
    $pdo->exec("DROP TABLE IF EXISTS oyuncular");
    $pdo->exec("DROP TABLE IF EXISTS takimlar");
    $pdo->exec("DROP TABLE IF EXISTS ayar");
    $pdo->exec("DROP TABLE IF EXISTS maclar");
    $pdo->exec("DROP TABLE IF EXISTS cl_maclar"); // Temizlik için

    // TAKIMLAR TABLOSU (Senin superlig.php'deki sütunlarınla tam uyumlu)
    $pdo->exec("CREATE TABLE takimlar (
        id INT PRIMARY KEY, 
        takim_adi VARCHAR(255), 
        logo VARCHAR(500), 
        hucum INT DEFAULT 75, 
        savunma INT DEFAULT 75, 
        butce BIGINT DEFAULT 25000000, 
        itibar INT DEFAULT 65,
        lig_id INT DEFAULT 1,
        avrupa_durumu VARCHAR(10) DEFAULT 'Yok',
        puan INT DEFAULT 0, 
        galibiyet INT DEFAULT 0, 
        beraberlik INT DEFAULT 0, 
        malubiyet INT DEFAULT 0, 
        atilan_gol INT DEFAULT 0, 
        yenilen_gol INT DEFAULT 0
    )");

    // OYUNCULAR TABLOSU (ovr, form, fitness, ceza, sakatlık dahil)
    $pdo->exec("CREATE TABLE oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        takim_id INT, 
        isim VARCHAR(255), 
        mevki VARCHAR(10), 
        ovr INT DEFAULT 70, 
        guc INT DEFAULT 70, 
        form INT DEFAULT 6, 
        fitness INT DEFAULT 100, 
        dayaniklilik INT DEFAULT 100,
        ilk11 TINYINT(1) DEFAULT 0, 
        yedek TINYINT(1) DEFAULT 0, 
        fiyat BIGINT DEFAULT 5000000, 
        yas INT DEFAULT 24,
        sakatlik_durumu VARCHAR(50) DEFAULT 'Sağlam', 
        ceza_hafta INT DEFAULT 0, 
        sakatlik_hafta INT DEFAULT 0, 
        saha_pozisyon VARCHAR(50) DEFAULT NULL
    )");

    // AYARLAR TABLOSU
    $pdo->exec("CREATE TABLE ayar (
        id INT PRIMARY KEY DEFAULT 1, 
        hafta INT DEFAULT 1, 
        sezon_yil INT DEFAULT 2025, 
        kullanici_takim_id INT DEFAULT NULL,
        dizilis VARCHAR(10) DEFAULT '4-3-3',
        transfer_donemi_acik_mi TINYINT(1) DEFAULT 1
    )");
    $pdo->exec("INSERT INTO ayar (id, hafta, sezon_yil) VALUES (1, 1, 2025)");

    // MAÇLAR TABLOSU (Sakatlar ve olaylar için JSON sütunlarıyla)
    $pdo->exec("CREATE TABLE maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        ev INT, 
        dep INT, 
        hafta INT, 
        ev_skor INT DEFAULT NULL, 
        dep_skor INT DEFAULT NULL, 
        ev_olaylar TEXT, 
        dep_olaylar TEXT, 
        ev_kartlar TEXT, 
        dep_kartlar TEXT, 
        ev_sakatlar TEXT, 
        dep_sakatlar TEXT
    )");

    // 3. TRANSFERMARKT'TAN TAKIMLARI ÇEK
    $url = "https://www.transfermarkt.com.tr/super-lig/startseite/wettbewerb/TR1";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//table[@class='items']//td[@class='hauptlink no-border-links']//a");
    
    $islenen_idler = [];
    foreach($nodes as $node) {
        $link = $node->getAttribute('href'); 
        if (preg_match('/verein\/([0-9]+)/', $link, $matches)) {
            $t_id = (int)$matches[1];
            if(!in_array($t_id, $islenen_idler)) {
                $t_ad = trim($node->nodeValue);
                $logo = "https://tmssl.akamaized.net/images/wappen/head/{$t_id}.png";
                $pdo->prepare("INSERT INTO takimlar (id, takim_adi, logo) VALUES (?, ?, ?)")->execute([$t_id, $t_ad, $logo]);
                $islenen_idler[] = $t_id;
            }
        }
    }
    header("Location: sl_kurulum.php?idx=0");
    exit;
}

// 4. TAKIMLARI TEK TEK İŞLE VE OYUNCULARI ÇEK
if(isset($_GET['idx'])) {
    $idx = (int)$_GET['idx'];
    $takimlar = $pdo->query("SELECT id, takim_adi FROM takimlar ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo "<body style='background:#05070a; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";

    if ($idx >= count($takimlar)) {
        echo "<h1 style='color:#00ff85;'>🏁 SÜPER LİG KURULUMU TAMAMLANDI!</h1>";
        echo "<p>Tüm veritabanı senin yeni kod yapına göre güncellendi.</p>";
        echo "<a href='superlig.php' style='display:inline-block; padding:15px 30px; background:#e30613; color:#fff; font-weight:bold; border-radius:8px; text-decoration:none;'>Kariyerine Başla</a>";
        exit;
    }

    $t = $takimlar[$idx];
    echo "<h3>İşleniyor: <span style='color:#f1c40f;'>{$t['takim_adi']}</span> (" . ($idx+1) . "/" . count($takimlar) . ")</h3>";

    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$t['id']}";
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
        
        $stmt = $pdo->prepare("INSERT INTO oyuncular (takim_id, isim, mevki, ovr, guc, fiyat, form, fitness, yas) VALUES (?, ?, ?, ?, ?, ?, ?, 100, ?)");
        
        foreach($rows as $row) {
            $n = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
            $p = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
            $v = $xpath->query(".//td[@class='rechts hauptlink']", $row)->item(0);
            $y = $xpath->query(".//td[3]", $row)->item(0); // Yaş sütunu

            if ($n && $p) {
                $isim = trim($n->nodeValue);
                $mevki_m = mb_strtolower(trim($p->nodeValue), 'UTF-8');
                $mevki = (strpos($mevki_m, 'kaleci') !== false) ? 'K' : ((strpos($mevki_m, 'bek') !== false || strpos($mevki_m, 'defans') !== false) ? 'D' : ((strpos($mevki_m, 'santrafor') !== false || strpos($mevki_m, 'forvet') !== false) ? 'F' : 'OS'));
                $yas = $y ? (int)$y->nodeValue : 24;

                // Değer çekme
                $deger = 500000;
                if ($v) {
                    $vm = mb_strtolower(trim($v->nodeValue), 'UTF-8');
                    $carpan = strpos($vm, 'mil') !== false ? 1000000 : (strpos($vm, 'bin') !== false ? 1000 : 1);
                    preg_match('/[0-9,\.]+/', $vm, $match);
                    if(!empty($match)) $deger = (float)str_replace(',', '.', $match[0]) * $carpan;
                }

                // Senin guc ve ovr sütunların için algoritma
                $ovr = ($deger >= 15000000) ? rand(82, 86) : (($deger >= 8000000) ? rand(78, 81) : (($deger >= 4000000) ? rand(74, 77) : rand(65, 73)));
                
                $stmt->execute([$t['id'], $isim, $mevki, $ovr, $ovr, $deger, rand(6,9), $yas]);
            }
        }
        
        // En İyi 11'i ve Yedekleri Belirle (Senin kadro.php ve superlig.php mantığına hazırlık)
        $m_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($m_limit as $mvk => $lim) {
            $ids = $pdo->query("SELECT id FROM oyuncular WHERE takim_id = {$t['id']} AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $lim")->fetchAll(PDO::FETCH_COLUMN);
            if(!empty($ids)) $pdo->exec("UPDATE oyuncular SET ilk11 = 1 WHERE id IN (".implode(',', $ids).")");
        }
        $pdo->exec("UPDATE oyuncular SET yedek = 1 WHERE takim_id = {$t['id']} AND ilk11 = 0 ORDER BY ovr DESC LIMIT 12");
    }
    
    $sonraki = $idx + 1;
    echo "<meta http-equiv='refresh' content='1;url=?idx=$sonraki'></body>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Süper Lig - Yeni Nesil Kurulum</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #05070a; color: #fff; text-align: center; padding-top: 100px;">
    <div style="background: linear-gradient(135deg, #1a1c23 0%, #e30613 100%); padding: 60px; border-radius: 30px; display: inline-block; border: 1px solid rgba(255,255,255,0.1);">
        <h1 style="font-weight: 800;">SÜPER LİG MODERN KURULUM</h1>
        <p>Attığın yeni kod yapısına tam uyumlu (ceza, sakatlık, bütçe, ovr) <br>veritabanı sıfırdan oluşturulacaktır.</p>
        <a href="?basla=1" style="background: #fff; color: #e30613; padding: 15px 50px; border-radius: 50px; font-weight: bold; text-decoration: none; display: inline-block; margin-top: 20px;">OPERASYONU BAŞLAT</a>
    </div>
</body>
</html>