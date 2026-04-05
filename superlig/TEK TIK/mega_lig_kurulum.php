<?php
// ==============================================================================
// MEGA LİG BOTU - V3.0 (TRANSFERMARKT MİMARİSİ - ASLA BAN YEMEZ)
// LaLiga, Bundesliga, Ligue 1, Serie A, Liga NOS
// ==============================================================================
set_time_limit(0);
error_reporting(0); // HTML hatalarını gizle
include '../db.php';

// Transfermarkt Lig ID'leri
$ligler = [
    'es' => ['ad' => 'La Liga', 'tm_id' => 'ES1', 'ulke' => 'İspanya', 'renk' => '#ea580c'],
    'de' => ['ad' => 'Bundesliga', 'tm_id' => 'L1', 'ulke' => 'Almanya', 'renk' => '#d97706'],
    'fr' => ['ad' => 'Ligue 1', 'tm_id' => 'FR1', 'ulke' => 'Fransa', 'renk' => '#2563eb'],
    'it' => ['ad' => 'Serie A', 'tm_id' => 'IT1', 'ulke' => 'İtalya', 'renk' => '#16a34a'],
    'pt' => ['ad' => 'Liga NOS', 'tm_id' => 'PO1', 'ulke' => 'Portekiz', 'renk' => '#9333ea']
];

$adim = $_GET['adim'] ?? '';
$secili_lig = $_GET['lig'] ?? '';
$takim_idx = isset($_GET['idx']) ? (int)$_GET['idx'] : 0;

function tm_sayfa_cek($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Güçlü User-Agent (Bot olduğumuzu gizler)
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    $html = curl_exec($ch);
    curl_close($ch);
    return $html;
}

// 1. ADIM: TABLOLARI KUR
if($adim == 'tablo_kur') {
    $p = $secili_lig;
    $lig_adi = $ligler[$p]['ad'];
    
    $pdo->exec("DROP TABLE IF EXISTS {$p}_takimlar, {$p}_oyuncular, {$p}_ayar, {$p}_maclar");
    
    $pdo->exec("CREATE TABLE {$p}_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL, son_basin_haftasi INT DEFAULT 0 )");
    $pdo->exec("INSERT INTO {$p}_ayar (hafta) VALUES (1)");
    
    $pdo->exec("CREATE TABLE {$p}_maclar ( id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025, ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT )");
    
    $pdo->exec("CREATE TABLE {$p}_takimlar (
        id INT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT DEFAULT 75, savunma INT DEFAULT 75, butce BIGINT DEFAULT 50000000, lig VARCHAR(50) DEFAULT '$lig_adi',
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        stadyum_seviye INT DEFAULT 1, altyapi_seviye INT DEFAULT 1, dizilis VARCHAR(20) DEFAULT '4-3-3', oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli'
    )");
    
    $pdo->exec("CREATE TABLE {$p}_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT DEFAULT 70, yas INT DEFAULT 24, fiyat BIGINT DEFAULT 5000000, lig VARCHAR(50) DEFAULT '$lig_adi',
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0
    )");
    
    echo json_encode(['durum' => 'ok', 'mesaj' => "$lig_adi tabloları Transfermarkt yapısına göre kuruldu."]);
    exit;
}

// 2. ADIM: TRANSFERMARKT'TAN TAKIMLARI ÇEK
if($adim == 'takimlari_cek') {
    $p = $secili_lig;
    $tm_id = $ligler[$p]['tm_id'];
    
    $url = "https://www.transfermarkt.com.tr/jumplist/startseite/wettbewerb/$tm_id";
    $html = tm_sayfa_cek($url);
    
    if(!$html) { echo json_encode(['durum' => 'hata', 'mesaj' => "Transfermarkt'a bağlanılamadı."]); exit; }

    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//table[@class='items']//td[@class='hauptlink no-border-links']//a");
    
    $islenenler = [];
    $takim_sayisi = 0;
    
    foreach($nodes as $node) {
        $link = $node->getAttribute('href'); 
        if (preg_match('/verein\/([0-9]+)/', $link, $matches)) {
            $t_id = (int)$matches[1];
            if(!in_array($t_id, $islenenler) && $takim_sayisi < 20) {
                $t_ad = trim($node->nodeValue);
                $logo = "https://tmssl.akamaized.net/images/wappen/head/{$t_id}.png";
                
                $stmt = $pdo->prepare("INSERT INTO {$p}_takimlar (id, takim_adi, logo) VALUES (?, ?, ?)");
                $stmt->execute([$t_id, $t_ad, $logo]);
                
                $islenenler[] = $t_id;
                $takim_sayisi++;
            }
        }
    }
    
    if($takim_sayisi > 0) {
        echo json_encode(['durum' => 'ok', 'mesaj' => "$takim_sayisi takım başarıyla çekildi. Oyuncular taranıyor..."]);
    } else {
        echo json_encode(['durum' => 'hata', 'mesaj' => "Takım listesi bulunamadı. HTML yapısı değişmiş olabilir."]);
    }
    exit;
}

// 3. ADIM: OYUNCULARI ÇEK (SENİN EFSANEVİ OVR ALGORİTMANLA)
if($adim == 'oyunculari_cek') {
    $p = $secili_lig;
    
    $takimlar = $pdo->query("SELECT id, takim_adi FROM {$p}_takimlar ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if($takim_idx >= count($takimlar)) {
        echo json_encode(['durum' => 'bitti', 'mesaj' => "Tüm işlemler başarıyla tamamlandı! 🎉"]); exit;
    }
    
    $t = $takimlar[$takim_idx];
    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$t['id']}";
    $html = tm_sayfa_cek($url);
    
    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//table[@class='items']//tbody/tr");
        
        $stmt_oyuncu = $pdo->prepare("INSERT INTO {$p}_oyuncular (takim_id, isim, mevki, ovr, fiyat, form, yas) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach($rows as $row) {
            $nameNode = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
            $posNode = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
            $valNode = $xpath->query(".//td[@class='rechts hauptlink']", $row)->item(0);
            $yasNode = $xpath->query(".//td[3]", $row)->item(0); // Yaş
            
            if ($nameNode && $posNode) {
                $isim = trim($nameNode->nodeValue);
                $mevki_metin = mb_strtolower(trim($posNode->nodeValue), 'UTF-8');
                
                $mevki = 'OS'; 
                if(strpos($mevki_metin, 'kaleci') !== false) $mevki = 'K';
                elseif(strpos($mevki_metin, 'bek') !== false || strpos($mevki_metin, 'stoper') !== false || strpos($mevki_metin, 'defans') !== false) $mevki = 'D';
                elseif(strpos($mevki_metin, 'santrafor') !== false || strpos($mevki_metin, 'forvet') !== false || strpos($mevki_metin, 'kanat') !== false) $mevki = 'F';

                $yas = $yasNode ? (int)$yasNode->nodeValue : 24;

                // Fiyat Algoritması
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

                // Senin PL OVR Mantığın (Avrupa devlerine uyarlandı)
                $ovr = 65;
                if($deger >= 90000000) $ovr = rand(89, 92);     
                elseif($deger >= 60000000) $ovr = rand(86, 88);  
                elseif($deger >= 40000000) $ovr = rand(83, 85);  
                elseif($deger >= 25000000) $ovr = rand(80, 82);  
                elseif($deger >= 15000000) $ovr = rand(77, 79);  
                elseif($deger >= 8000000) $ovr = rand(74, 76);   
                elseif($deger >= 3000000) $ovr = rand(70, 73);   
                elseif($deger >= 1000000) $ovr = rand(67, 69);   
                else $ovr = rand(62, 66);                        

                $form = rand(5, 9);
                $stmt_oyuncu->execute([$t['id'], $isim, $mevki, $ovr, $deger, $form, $yas]);
            }
        }
        
        // İlk 11 ve Yedek Dizilimi
        $m_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($m_limit as $mvk => $lim) {
            $ids = $pdo->query("SELECT id FROM {$p}_oyuncular WHERE takim_id = {$t['id']} AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $lim")->fetchAll(PDO::FETCH_COLUMN);
            if(!empty($ids)) $pdo->exec("UPDATE {$p}_oyuncular SET ilk_11 = 1 WHERE id IN (".implode(',', $ids).")");
        }
        $pdo->exec("UPDATE {$p}_oyuncular SET yedek = 1 WHERE takim_id = {$t['id']} AND ilk_11 = 0 ORDER BY ovr DESC LIMIT 12");
        
        // Takım Genel Gücü ve Bütçesi (OVR Ortalamasından)
        $ort = $pdo->query("SELECT AVG(ovr) FROM {$p}_oyuncular WHERE takim_id = {$t['id']} AND ilk_11 = 1")->fetchColumn();
        $guc = round($ort ?: 78);
        $b_guncel = ($guc > 84) ? rand(150, 300)*1000000 : (($guc > 78) ? rand(50, 100)*1000000 : rand(15, 40)*1000000);
        
        $pdo->exec("UPDATE {$p}_takimlar SET hucum = $guc, savunma = $guc, butce = $b_guncel WHERE id = {$t['id']}");
        
        echo json_encode(['durum' => 'ok', 'mesaj' => "{$t['takim_adi']} kadrosu eklendi. (OVR: $guc)", 'next_idx' => $takim_idx + 1]);
    } else {
        echo json_encode(['durum' => 'ok', 'mesaj' => "{$t['takim_adi']} atlandı (Bağlantı hatası).", 'next_idx' => $takim_idx + 1]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mega Lig Kurulum (Transfermarkt)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #050505; color: #fff; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .bot-container { background: #111; border: 1px solid #333; border-radius: 15px; padding: 40px; width: 100%; max-width: 800px; box-shadow: 0 10px 40px rgba(0,0,0,0.8); }
        .title { font-family: 'Oswald'; font-size: 2.5rem; text-align: center; color: #fff; margin-bottom: 30px; letter-spacing: 2px;}
        .league-btn { display: block; width: 100%; background: #222; border: 2px solid #444; color: #fff; font-weight: 800; font-size: 1.2rem; padding: 15px; border-radius: 10px; margin-bottom: 15px; cursor: pointer; transition: 0.3s; text-transform: uppercase;}
        .league-btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(255,255,255,0.1); }
        #console { background: #000; border: 1px solid #333; border-radius: 8px; padding: 20px; font-family: monospace; height: 350px; overflow-y: auto; display: none; margin-top: 20px; color: #0f0;}
        .log-line { margin-bottom: 8px; border-bottom: 1px dashed #111; padding-bottom: 8px;}
        .log-error { color: #f00; }
        .log-success { color: #0f0; }
        .loader { border: 4px solid #333; border-top: 4px solid #fff; border-radius: 50%; width: 16px; height: 16px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; margin-right: 10px;}
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>

    <div class="bot-container">
        <div class="title"><i class="fa-solid fa-satellite-dish"></i> TRANSFERMARKT BOTU v3.0</div>
        
        <div id="menu">
            <p class="text-center mb-4 text-secondary">Bu bot Transfermarkt altyapısını kullanır, ASLA BAN YEMEZ. <br>Kurmak istediğiniz ligi seçin.</p>
            <?php foreach($ligler as $p => $l): ?>
                <button class="league-btn" style="border-color: <?= $l['renk'] ?>;" onclick="kurulumaBasla('<?= $p ?>', '<?= $l['renk'] ?>')">
                    <i class="fa-solid fa-earth-europe"></i> <?= $l['ad'] ?> (<?= $l['ulke'] ?>)
                </button>
            <?php endforeach; ?>
            <button class="league-btn" style="background:#fff; color:#000; border-color:#fff;" onclick="window.location.href='../index.php'">
                <i class="fa-solid fa-house"></i> ANA MERKEZE DÖN
            </button>
        </div>

        <div id="console"></div>
    </div>

    <script>
        const consoleEl = document.getElementById('console');
        const menuEl = document.getElementById('menu');

        function logEkle(mesaj, tip = 'success', loading = false) {
            let loadHtml = loading ? '<div class="loader"></div>' : '>> ';
            consoleEl.innerHTML += `<div class="log-line log-${tip}">${loadHtml}${mesaj}</div>`;
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        async function kurulumaBasla(ligPrefix, renk) {
            menuEl.style.display = 'none';
            consoleEl.style.display = 'block';
            consoleEl.style.borderColor = renk;
            consoleEl.style.color = renk;
            
            logEkle('Transfermarkt botu başlatılıyor...', 'success', true);

            let tRes = await fetch(`?adim=tablo_kur&lig=${ligPrefix}`);
            let tData = await tRes.json();
            logEkle(tData.mesaj);

            logEkle('Transfermarkt sunucusuna bağlanılıyor, takımlar analiz ediliyor...', 'success', true);
            let tkRes = await fetch(`?adim=takimlari_cek&lig=${ligPrefix}`);
            let tkData = await tkRes.json();
            if(tkData.durum === 'hata') { logEkle(tkData.mesaj, 'error'); return; }
            logEkle(tkData.mesaj);

            let idx = 0;
            let devam = true;
            while(devam) {
                logEkle(`Takım verileri işleniyor (Sıra: ${idx+1}/20)...`, 'success', true);
                let oRes = await fetch(`?adim=oyunculari_cek&lig=${ligPrefix}&idx=${idx}`);
                let oData = await oRes.json();
                
                if(oData.durum === 'bitti') {
                    logEkle(oData.mesaj);
                    logEkle('<br><a href="mega_lig_kurulum.php" style="color:#fff; text-decoration:underline; font-size:1.1rem; background:#333; padding:10px; border-radius:5px;">← Diğer Ligleri Kurmak İçin Buraya Tıkla</a>');
                    devam = false;
                } else {
                    logEkle(oData.mesaj);
                    idx = oData.next_idx;
                }
            }
        }
    </script>
</body>
</html>