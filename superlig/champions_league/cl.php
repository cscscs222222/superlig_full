<?php
// ==============================================================================
// CHAMPIONS LEAGUE - ANA MERKEZ VE UEFA PUAN MOTORU (BLUE & CYAN THEME)
// ==============================================================================
include '../db.php';

// Merkez Maç Motorunu Bağla
if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, 'cl_');
} else {
    die("<h2 style='color:red; text-align:center; padding:50px;'>HATA: MatchEngine.php bulunamadı!</h2>");
}

function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT, savunma INT, butce BIGINT, lig VARCHAR(50),
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT, yas INT, fiyat BIGINT, lig VARCHAR(50),
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50'
    )");
} catch (Throwable $e) {}

sutunEkle($pdo, 'cl_takimlar', 'lig', "VARCHAR(50) DEFAULT 'Avrupa'");
sutunEkle($pdo, 'cl_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Avrupa'");
sutunEkle($pdo, 'cl_takimlar', 'butce', 'BIGINT DEFAULT 50000000');
sutunEkle($pdo, 'cl_takimlar', 'puan', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_takimlar', 'galibiyet', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_takimlar', 'beraberlik', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_takimlar', 'malubiyet', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_takimlar', 'atilan_gol', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_takimlar', 'yenilen_gol', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_oyuncular', 'ilk_11', 'TINYINT(1) DEFAULT 0');
sutunEkle($pdo, 'cl_oyuncular', 'yedek', 'TINYINT(1) DEFAULT 0');
sutunEkle($pdo, 'cl_oyuncular', 'form', 'INT DEFAULT 6');
sutunEkle($pdo, 'cl_oyuncular', 'fitness', 'INT DEFAULT 100');
sutunEkle($pdo, 'cl_oyuncular', 'ceza_hafta', 'INT DEFAULT 0');
sutunEkle($pdo, 'cl_oyuncular', 'sakatlik_hafta', 'INT DEFAULT 0');

try {
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM cl_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO cl_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

function uefa_puani_ekle($pdo, $takim_id, $puan) {
    try {
        $lig = $pdo->query("SELECT lig FROM cl_takimlar WHERE id = $takim_id")->fetchColumn();
        $ulke = 'Avrupa'; 
        if($lig == 'Süper Lig') $ulke = 'Türkiye';
        elseif($lig == 'Premier Lig') $ulke = 'İngiltere';
        
        $pdo->exec("UPDATE uefa_siralamasi SET toplam_puan = toplam_puan + $puan, guncel_sezon_puan = guncel_sezon_puan + $puan WHERE ulke_adi = '$ulke'");
    } catch(Throwable $e) {}
}

$cl_takim_sayisi = $pdo->query("SELECT COUNT(*) FROM cl_takimlar")->fetchColumn();
if ($cl_takim_sayisi < 25) {
    $devler = [
        ['Real Madrid', 'https://cdn-icons-png.flaticon.com/512/5041/5041042.png', 95, 92],
        ['Manchester City', 'https://cdn-icons-png.flaticon.com/512/825/825501.png', 96, 90],
        ['Bayern Münih', 'https://cdn-icons-png.flaticon.com/512/5041/5041050.png', 94, 90],
        ['Barcelona', 'https://cdn-icons-png.flaticon.com/512/5041/5041040.png', 92, 88],
        ['Liverpool', 'https://cdn-icons-png.flaticon.com/512/825/825553.png', 93, 91],
        ['Paris SG', 'https://cdn-icons-png.flaticon.com/512/825/825530.png', 91, 88],
        ['Inter Milan', 'https://cdn-icons-png.flaticon.com/512/5041/5041060.png', 90, 92],
        ['Juventus', 'https://cdn-icons-png.flaticon.com/512/5041/5041063.png', 88, 90],
        ['AC Milan', 'https://cdn-icons-png.flaticon.com/512/5041/5041065.png', 89, 87],
        ['Arsenal', 'https://cdn-icons-png.flaticon.com/512/825/825528.png', 92, 90],
        ['B. Dortmund', 'https://cdn-icons-png.flaticon.com/512/5041/5041052.png', 88, 86],
        ['A. Madrid', 'https://cdn-icons-png.flaticon.com/512/5041/5041045.png', 87, 89],
        ['Leverkusen', 'https://cdn-icons-png.flaticon.com/512/5041/5041055.png', 89, 86],
        ['Napoli', 'https://cdn-icons-png.flaticon.com/512/5041/5041068.png', 86, 88],
        ['Chelsea', 'https://cdn-icons-png.flaticon.com/512/825/825532.png', 88, 87],
        ['Man United', 'https://cdn-icons-png.flaticon.com/512/825/825503.png', 87, 85],
        ['Porto', 'https://cdn-icons-png.flaticon.com/512/5041/5041047.png', 84, 85],
        ['Benfica', 'https://cdn-icons-png.flaticon.com/512/825/825553.png', 83, 82],
        ['Ajax', 'https://cdn-icons-png.flaticon.com/512/5041/5041056.png', 82, 80],
        ['PSV', 'https://cdn-icons-png.flaticon.com/512/5041/5041049.png', 81, 79],
        ['Sevilla', 'https://cdn-icons-png.flaticon.com/512/5041/5041045.png', 80, 81],
        ['Sporting CP', 'https://cdn-icons-png.flaticon.com/512/5041/5041071.png', 79, 80],
        ['Celtic', 'https://cdn-icons-png.flaticon.com/512/825/825528.png', 78, 77],
        ['Club Brugge', 'https://cdn-icons-png.flaticon.com/512/825/825530.png', 77, 78],
    ];
    foreach($devler as $d) {
        $ad = $d[0]; $logo = $d[1]; $huc = $d[2]; $sav = $d[3];
        $var_mi = $pdo->query("SELECT COUNT(*) FROM cl_takimlar WHERE takim_adi = '$ad'")->fetchColumn();
        if($var_mi == 0) {
            $pdo->exec("INSERT INTO cl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES ('$ad', '$logo', $huc, $sav, 100000000, 'Avrupa')");
            $yeni_id = $pdo->lastInsertId();
            for($i=0; $i<18; $i++) {
                $isim = $ad . " Yıldızı " . ($i+1);
                $mevkiler = ['K', 'D', 'D', 'D', 'D', 'OS', 'OS', 'OS', 'F', 'F', 'F', 'D', 'OS', 'F', 'K', 'OS', 'D', 'F'];
                $pdo->exec("INSERT INTO cl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES ($yeni_id, '$isim', '{$mevkiler[$i]}', ".rand($sav-5, $huc+3).", 25, 30000000, 'Avrupa', ".($i<11?1:0).", ".($i>=11?1:0).")");
            }
        }
    }
}

function garanti_olay_uret($pdo, $takim_id, $skor) {
    $oyuncular = $pdo->query("SELECT isim FROM cl_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = $pdo->query("SELECT isim FROM cl_oyuncular WHERE takim_id = $takim_id")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = ['Bilinmeyen Oyuncu'];

    $olaylar = [];
    for($i=0; $i<$skor; $i++) {
        $golcu = $oyuncular[array_rand($oyuncular)];
        $asistci = (rand(1,100)>40) ? $oyuncular[array_rand($oyuncular)] : '-';
        if($golcu == $asistci) $asistci = '-';
        $olaylar[] = ['tip'=>'gol', 'oyuncu'=>$golcu, 'asist'=>$asistci, 'dakika'=>rand(1,90)];
    }
    usort($olaylar, function($a,$b) { return $a['dakika'] <=> $b['dakika']; });
    
    $kartlar = [];
    $kart_sayisi = rand(0, 3);
    for($i=0; $i<$kart_sayisi; $i++) {
        $kartlar[] = ['tip'=> (rand(1,100)>85 ? 'Kırmızı' : 'Sarı'), 'oyuncu'=>$oyuncular[array_rand($oyuncular)], 'dakika'=>rand(1,90)];
    }
    usort($kartlar, function($a,$b) { return $a['dakika'] <=> $b['dakika']; });
    
    return ['olaylar' => json_encode($olaylar, JSON_UNESCAPED_UNICODE), 'kartlar' => json_encode($kartlar, JSON_UNESCAPED_UNICODE)];
}

$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = $ayar['hafta'] ?? 1;
$sezon_yili = $ayar['sezon_yil'] ?? 2025;
$kullanici_takim = $ayar['kullanici_takim_id'] ?? null;

$max_hafta = $pdo->query("SELECT MAX(hafta) FROM cl_maclar WHERE sezon_yil = $sezon_yili")->fetchColumn();
if(!$max_hafta) $max_hafta = 17;

$mac_sayisi = 0;
try { $mac_sayisi = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e){}

if($mac_sayisi == 0) {
    $takimlar = $pdo->query("SELECT id FROM cl_takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar) > 1) {
        if(count($takimlar) % 2 != 0) $takimlar[] = 0;
        $t_sayisi = count($takimlar); $yari = $t_sayisi - 1; $m_sayisi = $t_sayisi / 2;

        for ($h = 1; $h <= min(8, $yari); $h++) {
            for ($i = 0; $i < $m_sayisi; $i++) {
                $ev = $takimlar[$i]; $dep = $takimlar[$t_sayisi - 1 - $i];
                if ($ev != 0 && $dep != 0) {
                    if ($i % 2 == 0) { $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, $h, $sezon_yili)"); } 
                    else { $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, $h, $sezon_yili)"); }
                }
            }
            $son = array_pop($takimlar); array_splice($takimlar, 1, 0, [$son]);
        }
        $pdo->exec("INSERT INTO cl_haberler (hafta, metin, tip) VALUES (1, 'Şampiyonlar Ligi kuraları çekildi. Macera başlıyor!', 'sistem')");
        header("Location: cl.php"); exit;
    }
}

if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if($action == 'takim_sec' && isset($_GET['tid'])) {
        $tid = (int)$_GET['tid'];
        $pdo->exec("UPDATE cl_ayar SET kullanici_takim_id = $tid WHERE id=1");
        header("Location: cl.php"); exit;
    }

    if($action == 'sifirla') {
        $pdo->exec("TRUNCATE TABLE cl_maclar");
        $pdo->exec("UPDATE cl_takimlar SET puan=0, galibiyet=0, beraberlik=0, malubiyet=0, atilan_gol=0, yenilen_gol=0");
        $pdo->exec("UPDATE cl_ayar SET hafta=1");
        $pdo->exec("UPDATE cl_oyuncular SET form=6, fitness=100, ceza_hafta=0, sakatlik_hafta=0");
        header("Location: cl.php"); exit;
    }
    
    if($action == 'tek_mac_simule' && isset($_GET['mac_id'])) {
        $mac_id = (int)$_GET['mac_id'];
        $hedef_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta; 
        
        $m = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                           FROM cl_maclar m JOIN cl_takimlar t1 ON m.ev=t1.id JOIN cl_takimlar t2 ON m.dep=t2.id 
                           WHERE m.id = $mac_id AND m.ev_skor IS NULL")->fetch(PDO::FETCH_ASSOC);
        if($m) {
            $pdo->exec("UPDATE cl_oyuncular SET ilk_11 = 0, yedek = 1 WHERE ilk_11 = 1 AND (ceza_hafta > 0 OR sakatlik_hafta > 0)");
            
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $stmt = $pdo->prepare("UPDATE cl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); 
                $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); 
                uefa_puani_ekle($pdo, $m['ev'], 400); 
            }
            elseif($ev_skor == $dep_skor) { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); 
                uefa_puani_ekle($pdo, $m['ev'], 200); uefa_puani_ekle($pdo, $m['dep'], 200); 
            }
            else { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); 
                $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); 
                uefa_puani_ekle($pdo, $m['dep'], 400); 
            }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE cl_oyuncular SET ceza_hafta = GREATEST(0, ceza_hafta - 1) WHERE ceza_hafta > 0");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = GREATEST(30, fitness - ROUND(RAND() * 15 + 5)) WHERE ilk_11 = 1");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = LEAST(100, fitness + ROUND(RAND() * 20 + 10)) WHERE ilk_11 = 0");
            $pdo->exec("UPDATE cl_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: cl.php?hafta=$hedef_hafta"); exit;
    }

    // YENİ: HEM DİĞER MAÇLARI HEM DE KENDİ MAÇINI SİMÜLE EDEN AKILLI BUTON
    if($action == 'hafta' || $action == 'hafta_full') {
        
        // Sakat ve cezalıları temizle (AI için)
        $pdo->exec("UPDATE cl_oyuncular SET ilk_11 = 0, yedek = 1 WHERE ilk_11 = 1 AND (ceza_hafta > 0 OR sakatlik_hafta > 0)");

        $maclar = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                               FROM cl_maclar m JOIN cl_takimlar t1 ON m.ev=t1.id JOIN cl_takimlar t2 ON m.dep=t2.id 
                               WHERE m.hafta = $hafta AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($maclar as $m) {
            // Eğer action 'hafta' ise kendi maçımızı atla. 'hafta_full' ise onu da oyna!
            if($action == 'hafta' && $kullanici_takim && ($m['ev'] == $kullanici_takim || $m['dep'] == $kullanici_takim)) continue; 
            
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $stmt = $pdo->prepare("UPDATE cl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); 
                $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); 
                uefa_puani_ekle($pdo, $m['ev'], 400); 
            }
            elseif($ev_skor == $dep_skor) { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); 
                uefa_puani_ekle($pdo, $m['ev'], 200); uefa_puani_ekle($pdo, $m['dep'], 200); 
            }
            else { 
                $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); 
                $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); 
                uefa_puani_ekle($pdo, $m['dep'], 400); 
            }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE cl_oyuncular SET ceza_hafta = GREATEST(0, ceza_hafta - 1) WHERE ceza_hafta > 0");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = GREATEST(30, fitness - ROUND(RAND() * 15 + 5)) WHERE ilk_11 = 1");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = LEAST(100, fitness + ROUND(RAND() * 20 + 10)) WHERE ilk_11 = 0");
            $pdo->exec("UPDATE cl_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: cl.php"); exit;
    }

    // TÜM 8 HAFTAYI SİMÜLE ET
    if($action == 'tum_8_hafta') {
        $pdo->exec("UPDATE cl_oyuncular SET ilk_11 = 0, yedek = 1 WHERE ilk_11 = 1 AND (ceza_hafta > 0 OR sakatlik_hafta > 0)");
        $baslangic_hafta = (int)$pdo->query("SELECT hafta FROM cl_ayar LIMIT 1")->fetchColumn();
        
        for ($h = $baslangic_hafta; $h <= 8; $h++) {
            $maclar_h = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                                     FROM cl_maclar m JOIN cl_takimlar t1 ON m.ev=t1.id JOIN cl_takimlar t2 ON m.dep=t2.id 
                                     WHERE m.hafta = $h AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            foreach($maclar_h as $m) {
                $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
                $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
                $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
                $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);
                $stmt = $pdo->prepare("UPDATE cl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
                $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
                $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
                $pdo->exec("UPDATE cl_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
                if($ev_skor > $dep_skor) { 
                    $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); 
                    $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); 
                    uefa_puani_ekle($pdo, $m['ev'], 400);
                } elseif($ev_skor == $dep_skor) { 
                    $pdo->exec("UPDATE cl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); 
                    uefa_puani_ekle($pdo, $m['ev'], 200); uefa_puani_ekle($pdo, $m['dep'], 200);
                } else { 
                    $pdo->exec("UPDATE cl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); 
                    $pdo->exec("UPDATE cl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); 
                    uefa_puani_ekle($pdo, $m['dep'], 400);
                }
            }
            $pdo->exec("UPDATE cl_oyuncular SET ceza_hafta = GREATEST(0, ceza_hafta - 1) WHERE ceza_hafta > 0");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = GREATEST(30, fitness - ROUND(RAND() * 15 + 5)) WHERE ilk_11 = 1");
            $pdo->exec("UPDATE cl_oyuncular SET fitness = LEAST(100, fitness + ROUND(RAND() * 20 + 10)) WHERE ilk_11 = 0");
        }
        $pdo->exec("UPDATE cl_ayar SET hafta = 9");
        header("Location: cl_nokaut.php"); exit;
    }
}

$puan_durumu = $pdo->query("SELECT * FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

$goster_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta;
if ($goster_hafta < 1) $goster_hafta = 1;
if ($goster_hafta > $max_hafta) $goster_hafta = $max_hafta;

$haftanin_fiksturu = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM cl_maclar m JOIN cl_takimlar t1 ON m.ev = t1.id JOIN cl_takimlar t2 ON m.dep = t2.id WHERE m.hafta = $goster_hafta AND m.hafta <= 8")->fetchAll(PDO::FETCH_ASSOC);
$yayinlanacak_maclar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] === NULL; });

$benim_macim_id = null;
if($kullanici_takim) {
    $benim_macim_id = $pdo->query("SELECT id FROM cl_maclar WHERE hafta=$goster_hafta AND ev_skor IS NULL AND (ev=$kullanici_takim OR dep=$kullanici_takim)")->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Champions League | Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --cl-primary: #0a1c52; 
            --cl-secondary: #002878; 
            --cl-accent: #00e5ff; 
            --cl-silver: #cbd5e1;
            
            --color-win: #10b981;
            --color-draw: #6b7280;
            --color-loss: #ef4444;

            --bg-body: #050b14;
            --bg-panel: #0d1a38;
            --border-color: rgba(0, 229, 255, 0.15);
            
            --text-primary: #f9fafb;
            --text-muted: #94a3b8;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 50% 0%, var(--cl-secondary) 0%, transparent 60%);
        }

        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-image: radial-gradient(white, rgba(255,255,255,.2) 2px, transparent 40px),
                              radial-gradient(white, rgba(255,255,255,.15) 1px, transparent 30px),
                              radial-gradient(white, rgba(255,255,255,.1) 2px, transparent 40px);
            background-size: 550px 550px, 350px 350px, 250px 250px; 
            background-position: 0 0, 40px 60px, 130px 270px;
            opacity: 0.15; pointer-events: none; z-index: -1;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(10, 28, 82, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--cl-accent); }
        .nav-brand i { color: var(--cl-accent); }
        .nav-link-item { color: var(--cl-silver); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; text-shadow: 0 0 10px var(--cl-accent); }
        
        .btn-action-primary { background: linear-gradient(45deg, var(--cl-secondary), var(--cl-accent)); color: #fff; font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; border: none; }
        .btn-action-primary:hover { color: #000; box-shadow: 0 0 15px var(--cl-accent); }
        .btn-action-outline { background: transparent; border: 1px solid var(--cl-accent); color: var(--cl-accent); font-weight: 600; padding: 8px 20px; border-radius: 4px; text-decoration: none; }
        .btn-action-outline:hover { background: var(--cl-accent); color: #000; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,229,255,0.05); display: flex; flex-direction: column; }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,229,255,0.05); flex-shrink: 0;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--cl-accent); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; }
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; transition: 0.2s; }
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.05); }
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 600; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; }
        
        .data-table tbody tr td:first-child { border-left: 4px solid transparent; }
        .zone-direct td:first-child { border-left-color: var(--cl-accent) !important; background: rgba(0,229,255,0.05); }
        .zone-playoff td:first-child { border-left-color: #fbbf24 !important; }

        .fixture-wrapper { display: flex; flex-direction: column; gap: 15px; overflow-y: auto; padding: 1rem; flex: 1; }
        
        .scorebug-container { background: rgba(0,0,0,0.4); border: 1px solid rgba(0, 229, 255, 0.1); border-radius: 10px; overflow: hidden; transition: 0.3s; width: 100%; flex-shrink: 0; }
        .scorebug-container:hover { border-color: var(--cl-accent); box-shadow: 0 5px 15px rgba(0,229,255,0.15); transform: translateY(-2px);}
        
        .score-grid { display: flex; width: 100%; min-height: 80px; align-items: stretch; }
        
        .team-block { display: flex; align-items: center; gap: 10px; padding: 0 15px; flex: 1; min-width: 0; }
        .team-block.home { justify-content: flex-end; }
        .team-block.away { justify-content: flex-start; }
        
        .team-name { font-weight: 600; font-size: 1.05rem; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px; }
        .team-block.home .team-name { text-align: right; }
        .team-block.away .team-name { text-align: left; }
        
        .team-logo { width: 38px !important; height: 38px !important; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); }
        
        .center-block { 
            width: 100px; flex-shrink: 0;
            background: linear-gradient(180deg, rgba(10, 28, 82, 0.9), rgba(0, 40, 120, 0.9)); 
            border-left: 1px solid rgba(0, 229, 255, 0.2); 
            border-right: 1px solid rgba(0, 229, 255, 0.2); 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            box-shadow: inset 0 0 10px rgba(0,0,0,0.5);
            padding: 10px 0;
        }
        .match-score { font-family: 'Oswald', sans-serif; font-size: 1.8rem; font-weight: 700; color: #fff; line-height: 1; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }
        .match-status { font-size: 0.75rem; color: var(--cl-accent); font-weight: 700; letter-spacing: 1px; margin-top: 4px; }
        
        .match-actions { display: flex; background: rgba(0, 229, 255, 0.05); border-top: 1px solid rgba(0, 229, 255, 0.1); }
        .action-btn { flex: 1; padding: 10px; text-align: center; text-decoration: none; color: #fff; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .action-btn:hover { background: var(--cl-accent); color: #000 !important; }
        .action-btn:hover i { color: #000 !important; }
        
        .events-grid { display: flex; width: 100%; background: rgba(0,0,0,0.6); border-top: 1px solid rgba(0,229,255,0.1); padding: 8px 0; font-size: 0.8rem; }
        .event-col { display: flex; flex-direction: column; gap: 6px; padding: 0 15px; flex: 1; min-width: 0;}
        .event-col.home { align-items: flex-end; text-align: right; } 
        .event-col.away { align-items: flex-start; text-align: left; }
        .event-col.center { width: 100px; flex: none; }
        
        .event-time { font-family: 'Oswald'; font-weight: 600; color: var(--cl-accent); flex-shrink: 0; min-width: 25px;}
        .event-item { display: flex; align-items: center; gap: 8px; font-weight: 500; max-width: 100%; }
        .event-player { color: #fff; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px;}
        .event-assist { color: var(--text-muted); font-size: 0.7rem; font-style: italic; white-space: nowrap;}
        
        .ref-card { width: 10px; height: 14px; border-radius: 2px; flex-shrink: 0; transform: rotate(3deg); box-shadow: 0 1px 3px rgba(0,0,0,0.5);}
        .ref-card.yellow { background-color: #fbbf24; }
        .ref-card.red { background-color: var(--color-loss); }

        .hover-lift { transition: 0.3s; }
        .hover-lift:hover { transform: translateY(-5px); }
        
        @keyframes blink { 50% { opacity: 0.5; box-shadow: 0 0 15px #00e5ff; } }
        .blink-effect { animation: blink 1.5s infinite; }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand"><i class="fa-solid fa-futbol"></i> <span class="font-oswald">CHAMPIONS LEAGUE</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-2">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
            <a href="cl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="cl_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-bar"></i> İstatistik</a>
            <a href="cl_nokaut.php" class="nav-link-item" style="color:#fbbf24; font-weight:700;"><i class="fa-solid fa-bolt"></i> Eleme Turları</a>
            <a href="cl_uefa.php" class="nav-link-item text-warning fw-bold"><i class="fa-solid fa-flag"></i> UEFA Puanı</a>
            <a href="?action=sifirla" class="nav-link-item text-danger" onclick="return confirm('TÜM SEZON SIFIRLANACAK! Maçlar silinecek ve takımlar 1. Haftaya dönecek. Emin misiniz?');">
                <i class="fa-solid fa-power-off"></i> Sıfırla
            </a>
            <a href="../super_lig/superlig.php" class="nav-link-item" style="color:#94a3b8;"><i class="fa-solid fa-arrow-left"></i> Yerel Lig</a>
        </div>

        <div class="d-flex gap-3">
            <?php if($kullanici_takim): ?>
                <?php if($benim_macim_id): ?>
                    <a href="../canli_mac.php?id=<?= $benim_macim_id ?>&lig=cl&hafta=<?= $goster_hafta ?>" class="btn-action-primary">
                        <i class="fa-solid fa-satellite-dish"></i> Maçını İzle
                    </a>
                <?php endif; ?>
                <a href="?action=hafta" class="btn-action-outline text-white border-white">
                    <i class="fa-solid fa-forward"></i> Diğer Maçları Oyna
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if(!$kullanici_takim): ?>
        <div class="container py-5 text-center" style="max-width: 1200px;">
            <i class="fa-solid fa-trophy mb-3" style="font-size: 5rem; color: var(--cl-silver);"></i>
            <h1 class="font-oswald mb-4 text-white" style="font-size: 3.5rem;">AVRUPA SAHNESİNE HOŞ GELDİN</h1>
            <p class="text-muted mb-5 fs-5">Lütfen Şampiyonlar Ligi'nde yöneteceğin kulübü seç.</p>
            
            <div class="row g-4 justify-content-center">
                <?php 
                $secilebilir = $pdo->query("SELECT * FROM cl_takimlar ORDER BY lig DESC, takim_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($secilebilir as $t): 
                ?>
                <div class="col-md-3 col-sm-4 col-6">
                    <div class="panel-card p-4 text-center hover-lift" style="cursor:pointer;" onclick="window.location='?action=takim_sec&tid=<?= $t['id'] ?>';">
                        <img src="<?= $t['logo'] ?>" style="width:70px; height:70px; object-fit:contain; margin-bottom:15px; filter:drop-shadow(0 0 10px rgba(0,229,255,0.3));">
                        <h5 class="font-oswald text-white mb-1"><?= $t['takim_adi'] ?></h5>
                        <span class="badge <?= $t['lig']=='Süper Lig' ? 'bg-danger' : 'bg-dark border border-secondary' ?>"><?= $t['lig'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="container-fluid py-4" style="max-width: 1700px;">
            <div class="row g-4">
                
                <div class="col-xl-7">
                    <div class="panel-card" style="height: 100%;">
                        <div class="panel-header">
                            <span class="font-oswald text-white fs-5"><i class="fa-solid fa-list-ol me-2" style="color:var(--cl-accent);"></i> LİG AŞAMASI (SWISS SYSTEM)</span>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Sıra</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AV</th><th>P</th></tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach($puan_durumu as $index => $t): 
                                        $sira = $index + 1; 
                                        $row_class = "";
                                        if($sira <= 8) $row_class = "zone-direct"; 
                                        elseif($sira <= 24) $row_class = "zone-playoff"; 
                                        
                                        $av = $t['atilan_gol'] - $t['yenilen_gol'];
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td class="font-oswald fs-5 text-white fw-bold"><?= $sira ?></td>
                                        <td>
                                            <div class="cell-club">
                                                <img src="<?= $t['logo'] ?>"> 
                                                <span style="<?= $t['id'] == $kullanici_takim ? 'color: var(--cl-accent);' : '' ?>"><?= $t['takim_adi'] ?></span>
                                            </div>
                                        </td>
                                        <td><?= $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'] ?></td>
                                        <td><?= $t['galibiyet'] ?></td>
                                        <td><?= $t['beraberlik'] ?></td>
                                        <td><?= $t['malubiyet'] ?></td>
                                        <td style="color: <?= $av>0?'var(--color-win)':($av<0?'var(--color-loss)':'') ?>;"><?= $av>0?'+'.$av:$av ?></td>
                                        <td class="font-oswald fs-5 text-white"><?= $t['puan'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="p-3 border-top d-flex flex-wrap gap-3 align-items-center" style="border-color:var(--border-color); font-size:0.88rem; background:rgba(0,0,0,0.5);">
                            <span style="color:var(--cl-accent); font-weight:700;"><i class="fa-solid fa-square me-1"></i>1-8: Son 16 (Direkt)</span>
                            <span class="text-warning" style="font-weight:700;"><i class="fa-solid fa-square me-1"></i>9-24: Play-Off</span>
                            <span class="text-muted ms-auto fst-italic" style="font-size:0.8rem;">Swiss System · 8 Maç Günü</span>
                        </div>
                        
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="panel-card" style="height: 100%; max-height:850px;">
                        
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <div>
                                <a href="?hafta=<?= max(1, $goster_hafta-1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                                <span class="font-oswald text-white fs-5 mx-2">MAÇ GÜNÜ <?= $goster_hafta ?></span>
                                <a href="?hafta=<?= min($max_hafta, $goster_hafta+1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
                            </div>
                            <?php 
                            $kalan_grup_maci = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta <= 8 AND ev_skor IS NULL")->fetchColumn();
                            if($hafta >= 9 || ($hafta == 8 && $kalan_grup_maci == 0)): 
                            ?>
                                <a href="cl_nokaut.php" class="badge bg-info text-dark text-decoration-none p-2 blink-effect" style="font-size: 0.9rem; border: 1px solid #fff;"><i class="fa-solid fa-bolt"></i> ELEME TURLARI</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="fixture-wrapper">
                            
                            <?php if($hafta <= 8): ?>
                                <a href="?action=tum_8_hafta" class="btn fw-bold w-100 mb-2 py-3 font-oswald" 
                                   style="background: linear-gradient(90deg,#1d4ed8,#00e5ff); color:#000; box-shadow:0 0 20px rgba(0,229,255,0.5); font-size:1.15rem; border-radius:8px; border:none;"
                                   onclick="return confirm('Tüm 8 haftayı simüle etmek istediğinizden emin misiniz?')">
                                    <i class="fa-solid fa-bolt me-2"></i> TÜM 8 HAFTAYI SİMÜLE ET
                                </a>
                            <?php endif; ?>
                            
                            <?php if($goster_hafta == $hafta && count($yayinlanacak_maclar) > 0 && $hafta <= 8): ?>
                                <a href="?action=hafta_full" class="btn btn-outline-info fw-bold w-100 mb-2 py-2 font-oswald" style="font-size: 1rem; border-radius: 8px;">
                                    <i class="fa-solid fa-forward-fast me-2"></i> SADECE BU HAFTAYI SİMÜLE ET
                                </a>
                            <?php endif; ?>

                            <?php if($goster_hafta >= 9): ?>
                                <div class="text-center p-5 text-white font-oswald fs-4" style="background: rgba(0, 229, 255, 0.1); border-radius: 10px; border: 1px dashed #00e5ff; margin-top: 50px;">
                                    <i class="fa-solid fa-bolt mb-3" style="font-size: 3rem; color: #00e5ff;"></i><br>
                                    GRUP AŞAMASI BİTTİ!<br>
                                    <span class="fs-6 text-muted mt-2 d-block" style="text-transform: none;">Lütfen Şampiyonlar Ligi'nin kalan maçlarını oynamak için Nokaut aşamasına geçin.</span>
                                    <a href="cl_nokaut.php" class="btn btn-info fw-bold mt-4 p-3"><i class="fa-solid fa-arrow-right"></i> ELEME TURLARINA GİT</a>
                                </div>
                            
                            <?php elseif(empty($haftanin_fiksturu)): ?>
                                <div class="text-center p-4 text-muted font-oswald fs-5">Bu haftaya ait fikstür bulunamadı.</div>
                                
                            <?php else: ?>
                            
                                <?php foreach($yayinlanacak_maclar as $mac): ?>
                                    <div class="scorebug-container">
                                        <div class="score-grid">
                                            <div class="team-block home">
                                                <span class="team-name"><?= $mac['ev_ad'] ?></span>
                                                <img src="<?= $mac['ev_logo'] ?>" class="team-logo">
                                            </div>
                                            <div class="center-block">
                                                <span class="match-score text-muted" style="font-size:1.2rem;">V</span>
                                                <span class="match-status text-muted">BEKLİYOR</span>
                                            </div>
                                            <div class="team-block away">
                                                <img src="<?= $mac['dep_logo'] ?>" class="team-logo">
                                                <span class="team-name"><?= $mac['dep_ad'] ?></span>
                                            </div>
                                        </div>
                                        <div class="match-actions">
                                            <a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=cl&hafta=<?= $goster_hafta ?>" class="action-btn text-white w-100" style="background: rgba(0, 229, 255, 0.1);">
                                                <i class="fa-solid fa-satellite-dish text-info"></i> CANLI İZLE
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php 
                                $oynananlar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] !== NULL; });
                                foreach($oynananlar as $mac): 
                                ?>
                                    <div class="scorebug-container" style="border-color: rgba(0,229,255,0.4); box-shadow: 0 0 10px rgba(0,229,255,0.1);">
                                        <div class="score-grid">
                                            <div class="team-block home">
                                                <span class="team-name"><?= $mac['ev_ad'] ?></span>
                                                <img src="<?= $mac['ev_logo'] ?>" class="team-logo">
                                            </div>
                                            <div class="center-block">
                                                <span class="match-score text-white"><?= $mac['ev_skor'] ?> - <?= $mac['dep_skor'] ?></span>
                                                <span class="match-status">MS</span>
                                            </div>
                                            <div class="team-block away">
                                                <img src="<?= $mac['dep_logo'] ?>" class="team-logo">
                                                <span class="team-name"><?= $mac['dep_ad'] ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php 
                                        $ev_olaylar = json_decode($mac['ev_olaylar'], true) ?: [];
                                        $dep_olaylar = json_decode($mac['dep_olaylar'], true) ?: [];
                                        $ev_kartlar = json_decode($mac['ev_kartlar'], true) ?: [];
                                        $dep_kartlar = json_decode($mac['dep_kartlar'], true) ?: [];
                                        
                                        if(count($ev_olaylar) > 0 || count($dep_olaylar) > 0 || count($ev_kartlar) > 0 || count($dep_kartlar) > 0): 
                                        ?>
                                        <div class="events-grid">
                                            <div class="event-col home">
                                                <?php 
                                                $yazilan_gol_ev = 0;
                                                foreach($ev_olaylar as $o) {
                                                    if ($yazilan_gol_ev >= $mac['ev_skor']) break; 
                                                    $tip = $o['tip'] ?? 'gol';
                                                    if(strtolower($tip) != 'gol') continue; 
                                                    $oyuncu = $o['oyuncu'] ?? 'Bilinmiyor';
                                                    $dakika = $o['dakika'] ?? rand(1,90);
                                                    $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                                    echo "<div class='event-item justify-content-end'>$asist <span class='event-player'>$oyuncu</span> <span class='event-time'>$dakika'</span> <i class='fa-solid fa-futbol text-success'></i></div>"; 
                                                    $yazilan_gol_ev++;
                                                }
                                                foreach($ev_kartlar as $k) { 
                                                    $oyuncu = $k['oyuncu'] ?? 'Bilinmiyor';
                                                    $dakika = $k['dakika'] ?? rand(1,90);
                                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı');
                                                    $renk_class = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                                    echo "<div class='event-item justify-content-end'><span class='event-player'>$oyuncu</span> <span class='event-time'>$dakika'</span> <div class='ref-card $renk_class'></div></div>"; 
                                                }
                                                ?>
                                            </div>
                                            <div class="event-col center"></div>
                                            <div class="event-col away">
                                                <?php 
                                                $yazilan_gol_dep = 0;
                                                foreach($dep_olaylar as $o) {
                                                    if ($yazilan_gol_dep >= $mac['dep_skor']) break; 
                                                    $tip = $o['tip'] ?? 'gol';
                                                    if(strtolower($tip) != 'gol') continue; 
                                                    $oyuncu = $o['oyuncu'] ?? 'Bilinmiyor';
                                                    $dakika = $o['dakika'] ?? rand(1,90);
                                                    $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                                    echo "<div class='event-item'><i class='fa-solid fa-futbol text-success'></i> <span class='event-time'>$dakika'</span> <span class='event-player'>$oyuncu</span> $asist</div>"; 
                                                    $yazilan_gol_dep++;
                                                }
                                                foreach($dep_kartlar as $k) { 
                                                    $oyuncu = $k['oyuncu'] ?? 'Bilinmiyor';
                                                    $dakika = $k['dakika'] ?? rand(1,90);
                                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı');
                                                    $renk_class = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                                    echo "<div class='event-item'><div class='ref-card $renk_class'></div> <span class='event-time'>$dakika'</span> <span class='event-player'>$oyuncu</span></div>"; 
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>