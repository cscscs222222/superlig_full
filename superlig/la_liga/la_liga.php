<?php
// ==============================================================================
// LA LIGA - ANA MERKEZ VE FİKSTÜR (RED & GOLD SPANISH THEME)
// ==============================================================================
include '../db.php';

// Merkez Maç Motorunu Bağla (ÖN EK OLARAK 'es_' VERİYORUZ!)
if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, 'es_');
} else {
    die("<h2 style='color:red; text-align:center; padding:50px;'>HATA: MatchEngine.php bulunamadı!</h2>");
}

// --- GÜVENLİ SÜTUN VE TABLO EKLEME ---
function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT, savunma INT, butce BIGINT, lig VARCHAR(50),
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS es_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT, yas INT, fiyat BIGINT, lig VARCHAR(50),
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50'
    )");
} catch (Throwable $e) {}

sutunEkle($pdo, 'es_takimlar', 'lig', "VARCHAR(50) DEFAULT 'La Liga'");
sutunEkle($pdo, 'es_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'La Liga'");

try {
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM es_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO es_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

// --- LA LİGA TAKIMLARINI OLUŞTUR (20 TAKIM) ---
$es_takim_sayisi = $pdo->query("SELECT COUNT(*) FROM es_takimlar")->fetchColumn();
if ($es_takim_sayisi < 20) {
    $ispanya_devleri = [
        ['Real Madrid',          'https://tmssl.akamaized.net/images/wappen/head/418.png',   88, 88],
        ['FC Barcelona',         'https://tmssl.akamaized.net/images/wappen/head/131.png',   87, 87],
        ['Atlético Madrid',      'https://tmssl.akamaized.net/images/wappen/head/13.png',    82, 82],
        ['Athletic Bilbao',      'https://tmssl.akamaized.net/images/wappen/head/621.png',   78, 78],
        ['Real Sociedad',        'https://tmssl.akamaized.net/images/wappen/head/681.png',   78, 78],
        ['Villarreal CF',        'https://tmssl.akamaized.net/images/wappen/head/1050.png',  78, 78],
        ['Real Betis',           'https://tmssl.akamaized.net/images/wappen/head/150.png',   77, 77],
        ['Celta Vigo',           'https://tmssl.akamaized.net/images/wappen/head/940.png',   76, 76],
        ['Girona FC',            'https://tmssl.akamaized.net/images/wappen/head/12321.png', 75, 75],
        ['Valencia CF',          'https://tmssl.akamaized.net/images/wappen/head/1049.png',  75, 75],
        ['Sevilla FC',           'https://tmssl.akamaized.net/images/wappen/head/368.png',   74, 74],
        ['Espanyol Barcelona',   'https://tmssl.akamaized.net/images/wappen/head/714.png',   74, 74],
        ['CA Osasuna',           'https://tmssl.akamaized.net/images/wappen/head/331.png',   73, 73],
        ['Rayo Vallecano',       'https://tmssl.akamaized.net/images/wappen/head/367.png',   73, 73],
        ['Levante',              'https://tmssl.akamaized.net/images/wappen/head/3368.png',  73, 73],
        ['Getafe CF',            'https://tmssl.akamaized.net/images/wappen/head/3709.png',  72, 72],
        ['Elche CF',             'https://tmssl.akamaized.net/images/wappen/head/1531.png',  72, 72],
        ['RCD Mallorca',         'https://tmssl.akamaized.net/images/wappen/head/237.png',   72, 72],
        ['Deportivo Alavés',     'https://tmssl.akamaized.net/images/wappen/head/1108.png',  71, 71],
        ['Real Oviedo',          'https://tmssl.akamaized.net/images/wappen/head/2497.png',  71, 71],
    ];
    
    foreach($ispanya_devleri as $d) {
        $ad = $d[0]; $logo = $d[1]; $huc = $d[2]; $sav = $d[3];
        $var_mi = $pdo->query("SELECT COUNT(*) FROM es_takimlar WHERE takim_adi = " . $pdo->quote($ad))->fetchColumn();
        if($var_mi == 0) {
            $butce = rand(20000000, 200000000);
            $stmt = $pdo->prepare("INSERT INTO es_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'La Liga')");
            $stmt->execute([$ad, $logo, $huc, $sav, $butce]);
            $yeni_id = $pdo->lastInsertId();
            
            for($i=0; $i<22; $i++) {
                $isim = $ad . " Oyuncusu " . ($i+1);
                $mevkiler = ['K', 'K', 'D', 'D', 'D', 'D', 'D', 'D', 'OS', 'OS', 'OS', 'OS', 'OS', 'OS', 'F', 'F', 'F', 'F', 'D', 'OS', 'F', 'K'];
                $mvk = $mevkiler[$i];
                $ovr = rand($sav-6, $huc+4);
                $ilk11 = ($i < 11) ? 1 : 0;
                $yedek = ($i >= 11 && $i < 18) ? 1 : 0;
                $fiyat = ($ovr * $ovr) * 4000;
                $stmt2 = $pdo->prepare("INSERT INTO es_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'La Liga', ?, ?)");
                $stmt2->execute([$yeni_id, $isim, $mvk, $ovr, rand(18,34), $fiyat, $ilk11, $yedek]);
            }
        }
    }
}

// Varolan takımlar için oyuncu yoksa oluştur
$takimlar_listesi = $pdo->query("SELECT id, takim_adi, hucum, savunma FROM es_takimlar")->fetchAll(PDO::FETCH_ASSOC);
foreach($takimlar_listesi as $t) {
    $oyuncu_sayisi = $pdo->query("SELECT COUNT(*) FROM es_oyuncular WHERE takim_id = {$t['id']}")->fetchColumn();
    if($oyuncu_sayisi == 0) {
        for($i=0; $i<22; $i++) {
            $isim = $t['takim_adi'] . " Oyuncusu " . ($i+1);
            $mevkiler = ['K', 'K', 'D', 'D', 'D', 'D', 'D', 'D', 'OS', 'OS', 'OS', 'OS', 'OS', 'OS', 'F', 'F', 'F', 'F', 'D', 'OS', 'F', 'K'];
            $mvk = $mevkiler[$i];
            $huc = $t['hucum'] ?? 75; $sav = $t['savunma'] ?? 75;
            $ovr = rand(max(60,$sav-6), min(99,$huc+4));
            $ilk11 = ($i < 11) ? 1 : 0;
            $yedek = ($i >= 11 && $i < 18) ? 1 : 0;
            $fiyat = ($ovr * $ovr) * 4000;
            $stmt = $pdo->prepare("INSERT INTO es_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'La Liga', ?, ?)");
            $stmt->execute([$t['id'], $isim, $mvk, $ovr, rand(18,34), $fiyat, $ilk11, $yedek]);
        }
    }
}

// --- GARANTİ OLAY ÜRETİCİ ---
function garanti_olay_uret($pdo, $takim_id, $skor) {
    $oyuncular = $pdo->query("SELECT isim FROM es_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = $pdo->query("SELECT isim FROM es_oyuncular WHERE takim_id = $takim_id")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = ['Bilinmeyen Oyuncu'];

    $olaylar = [];
    for($i=0; $i<$skor; $i++) {
        $golcu = $oyuncular[array_rand($oyuncular)];
        $asistci = (rand(1,100)>50) ? $oyuncular[array_rand($oyuncular)] : '-';
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

$ayar = $pdo->query("SELECT * FROM es_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = $ayar['hafta'] ?? 1;
$sezon_yili = $ayar['sezon_yil'] ?? 2025;
$kullanici_takim = $ayar['kullanici_takim_id'] ?? null;

$max_hafta = 38; // La Liga 20 takım, 38 haftadır.

// --- FİKSTÜR OLUŞTURMA ---
$mac_sayisi = 0;
try { $mac_sayisi = $pdo->query("SELECT COUNT(*) FROM es_maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e){}

if($mac_sayisi == 0) {
    $takimlar = $pdo->query("SELECT id FROM es_takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar) > 1) {
        if(count($takimlar) % 2 != 0) $takimlar[] = 0;
        $t_sayisi = count($takimlar); $yari = $t_sayisi - 1; $m_sayisi = $t_sayisi / 2;

        for ($h = 1; $h <= $yari; $h++) {
            for ($i = 0; $i < $m_sayisi; $i++) {
                $ev = $takimlar[$i]; $dep = $takimlar[$t_sayisi - 1 - $i];
                if ($ev != 0 && $dep != 0) {
                    if ($i % 2 == 0) { 
                        $pdo->exec("INSERT INTO es_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, $h, $sezon_yili)"); 
                        $pdo->exec("INSERT INTO es_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, ".($h+$yari).", $sezon_yili)"); 
                    } else { 
                        $pdo->exec("INSERT INTO es_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, $h, $sezon_yili)"); 
                        $pdo->exec("INSERT INTO es_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, ".($h+$yari).", $sezon_yili)"); 
                    }
                }
            }
            $son = array_pop($takimlar); array_splice($takimlar, 1, 0, [$son]);
        }
        $pdo->exec("INSERT INTO es_haberler (hafta, metin, tip) VALUES (1, 'La Liga Başlıyor! İspanya''da nefesler tutuldu.', 'sistem')");
        header("Location: la_liga.php"); exit;
    }
}

// --- AKSİYON YÖNETİMİ ---
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if($action == 'takim_sec' && isset($_GET['tid'])) {
        $tid = (int)$_GET['tid'];
        $pdo->exec("UPDATE es_ayar SET kullanici_takim_id = $tid WHERE id=1");
        header("Location: la_liga.php"); exit;
    }
    
    if($action == 'tek_mac_simule' && isset($_GET['mac_id'])) {
        $mac_id = (int)$_GET['mac_id'];
        $hedef_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta; 
        
        $m = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                           FROM es_maclar m JOIN es_takimlar t1 ON m.ev=t1.id JOIN es_takimlar t2 ON m.dep=t2.id 
                           WHERE m.id = $mac_id AND m.ev_skor IS NULL")->fetch(PDO::FETCH_ASSOC);
        if($m) {
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $ev_olay_kontrol = json_decode($ev_detay['olaylar'], true);
            if((empty($ev_olay_kontrol) && $ev_skor > 0) || !is_array($ev_olay_kontrol)) {
                $garanti = garanti_olay_uret($pdo, $m['ev'], $ev_skor);
                $ev_detay['olaylar'] = $garanti['olaylar']; $ev_detay['kartlar'] = $garanti['kartlar'];
            }
            $dep_olay_kontrol = json_decode($dep_detay['olaylar'], true);
            if((empty($dep_olay_kontrol) && $dep_skor > 0) || !is_array($dep_olay_kontrol)) {
                $garanti = garanti_olay_uret($pdo, $m['dep'], $dep_skor);
                $dep_detay['olaylar'] = $garanti['olaylar']; $dep_detay['kartlar'] = $garanti['kartlar'];
            }

            $stmt = $pdo->prepare("UPDATE es_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE es_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE es_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE es_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE es_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE es_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE es_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE es_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM es_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE es_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: la_liga.php?hafta=$hedef_hafta"); exit;
    }

    if($action == 'hafta') {
        $maclar = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                               FROM es_maclar m JOIN es_takimlar t1 ON m.ev=t1.id JOIN es_takimlar t2 ON m.dep=t2.id 
                               WHERE m.hafta = $hafta AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($maclar as $m) {
            if($kullanici_takim && ($m['ev'] == $kullanici_takim || $m['dep'] == $kullanici_takim)) continue; 
            
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $ev_olay_kontrol = json_decode($ev_detay['olaylar'], true);
            if((empty($ev_olay_kontrol) && $ev_skor > 0) || !is_array($ev_olay_kontrol)) {
                $garanti = garanti_olay_uret($pdo, $m['ev'], $ev_skor);
                $ev_detay['olaylar'] = $garanti['olaylar']; $ev_detay['kartlar'] = $garanti['kartlar'];
            }
            $dep_olay_kontrol = json_decode($dep_detay['olaylar'], true);
            if((empty($dep_olay_kontrol) && $dep_skor > 0) || !is_array($dep_olay_kontrol)) {
                $garanti = garanti_olay_uret($pdo, $m['dep'], $dep_skor);
                $dep_detay['olaylar'] = $garanti['olaylar']; $dep_detay['kartlar'] = $garanti['kartlar'];
            }

            $stmt = $pdo->prepare("UPDATE es_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE es_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE es_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE es_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE es_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE es_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE es_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE es_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM es_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE es_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: la_liga.php"); exit;
    }
}

// --- VERİ ÇEKİMİ ---
$puan_durumu = $pdo->query("SELECT * FROM es_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

$goster_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta;
if ($goster_hafta < 1) $goster_hafta = 1;
if ($goster_hafta > $max_hafta) $goster_hafta = $max_hafta;

$haftanin_fiksturu = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM es_maclar m JOIN es_takimlar t1 ON m.ev = t1.id JOIN es_takimlar t2 ON m.dep = t2.id WHERE m.hafta = $goster_hafta")->fetchAll(PDO::FETCH_ASSOC);
$yayinlanacak_maclar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] === NULL; });

$benim_macim_id = null;
if($kullanici_takim) {
    $benim_macim_id = $pdo->query("SELECT id FROM es_maclar WHERE hafta=$goster_hafta AND ev_skor IS NULL AND (ev=$kullanici_takim OR dep=$kullanici_takim)")->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>La Liga | Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* LA LİGA TEMASI (KIRMIZI, ALTIN, ISPANYA) */
        :root {
            --es-primary: #c8102e;   /* İspanya Kırmızısı */
            --es-secondary: #f5c518; /* Altın Sarısı */
            --es-accent: #ff6b35;    /* Turuncu Vurgu */
            --es-dark: #1a0008;
            
            --bg-body: #150003;
            --bg-panel: #2a0008;
            --border-color: rgba(245, 197, 24, 0.2);
            
            --text-primary: #f9fafb;
            --text-muted: #f59e0b;
            
            --color-win: #22c55e;
            --color-loss: #ef4444;
            --color-draw: #9ca3af;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(200,16,46,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(245,197,24,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* NAVBAR */
        .pro-navbar { background: rgba(26, 0, 8, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--es-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--es-primary); }
        .nav-brand i { color: var(--es-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--es-secondary); text-shadow: 0 0 10px var(--es-secondary); }
        
        .btn-action-primary { background: var(--es-secondary); color: var(--es-dark); font-weight: 800; padding: 8px 20px; border-radius: 4px; text-decoration: none; border: none; transition: 0.3s;}
        .btn-action-primary:hover { background: #ffd700; color: #000; box-shadow: 0 0 15px var(--es-secondary); }
        .btn-action-outline { background: transparent; border: 1px solid var(--es-primary); color: var(--es-primary); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.3s;}
        .btn-action-outline:hover { background: var(--es-primary); color: #fff; box-shadow: 0 0 15px var(--es-primary); }

        /* DASHBOARD KARTLARI */
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); display: flex; flex-direction: column; }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); flex-shrink: 0;}
        
        /* Puan Tablosu */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--es-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; }
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; transition: 0.2s; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(245,197,24,0.05); }
        
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 700; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.8));}
        
        /* La Liga Bölgeleme Kuralları */
        .data-table tbody tr td:first-child { border-left: 4px solid transparent; }
        .zone-cl td:first-child { border-left-color: var(--es-secondary) !important; background: rgba(245,197,24,0.05); }
        .zone-el td:first-child { border-left-color: #fb923c !important; }
        .zone-rel td:first-child { border-left-color: var(--es-primary) !important; opacity: 0.8;}

        /* FİKSTÜR */
        .fixture-wrapper { display: flex; flex-direction: column; gap: 15px; overflow-y: auto; padding: 1rem; flex: 1; }
        
        .scorebug-container { background: rgba(0,0,0,0.5); border: 1px solid rgba(200,16,46,0.2); border-radius: 10px; overflow: hidden; transition: 0.3s; width: 100%; flex-shrink: 0; }
        .scorebug-container:hover { border-color: var(--es-secondary); box-shadow: 0 5px 15px rgba(245,197,24,0.15); transform: translateY(-2px);}
        
        .score-grid { display: flex; width: 100%; min-height: 80px; align-items: stretch; }
        
        .team-block { display: flex; align-items: center; gap: 10px; padding: 0 15px; flex: 1; min-width: 0; }
        .team-block.home { justify-content: flex-end; }
        .team-block.away { justify-content: flex-start; }
        
        .team-name { font-weight: 700; font-size: 1.05rem; color: #f8fafc; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px; }
        .team-block.home .team-name { text-align: right; }
        .team-block.away .team-name { text-align: left; }
        
        .team-logo { width: 38px !important; height: 38px !important; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.8)); }
        
        .center-block { 
            width: 100px; flex-shrink: 0;
            background: rgba(200,16,46,0.3); 
            border-left: 1px solid rgba(245,197,24,0.2); 
            border-right: 1px solid rgba(245,197,24,0.2); 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            padding: 10px 0;
        }
        .match-score { font-family: 'Oswald', sans-serif; font-size: 1.8rem; font-weight: 900; color: var(--es-secondary); line-height: 1; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }
        .match-status { font-size: 0.75rem; color: #fff; font-weight: 700; letter-spacing: 1px; margin-top: 4px; }
        
        .match-actions { display: flex; background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.1); }
        .action-btn { flex: 1; padding: 10px; text-align: center; text-decoration: none; color: var(--es-secondary); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .action-btn:hover { background: var(--es-secondary); color: var(--es-dark) !important; }
        
        /* GOL DETAYLARI */
        .events-grid { display: flex; width: 100%; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(200,16,46,0.2); padding: 8px 0; font-size: 0.8rem; }
        .event-col { display: flex; flex-direction: column; gap: 6px; padding: 0 15px; flex: 1; min-width: 0;}
        .event-col.home { align-items: flex-end; text-align: right; } 
        .event-col.away { align-items: flex-start; text-align: left; }
        .event-col.center { width: 100px; flex: none; }
        
        .event-time { font-family: 'Oswald'; font-weight: 700; color: var(--es-primary); flex-shrink: 0; min-width: 25px;}
        .event-item { display: flex; align-items: center; gap: 8px; font-weight: 600; max-width: 100%; }
        .event-player { color: #fff; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px;}
        .event-assist { color: var(--text-muted); font-size: 0.7rem; font-style: italic; white-space: nowrap;}

        .ref-card { width: 10px; height: 14px; border-radius: 2px; flex-shrink: 0; transform: rotate(3deg); box-shadow: 0 1px 3px rgba(0,0,0,0.5);}
        .ref-card.yellow { background-color: #fbbf24; }
        .ref-card.red { background-color: var(--color-loss); }

        .hover-lift { transition: 0.3s; cursor: pointer; }
        .hover-lift:hover { transform: translateY(-5px); }
        
        @keyframes pulse_glow {
            0% { box-shadow: 0 0 10px rgba(245,197,24,0.5); }
            50% { box-shadow: 0 0 30px rgba(245,197,24,0.8), 0 0 50px rgba(200,16,46,0.3); }
            100% { box-shadow: 0 0 10px rgba(245,197,24,0.5); }
        }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="la_liga.php" class="nav-brand"><i class="fa-solid fa-sun"></i> <span class="font-oswald">LA LIGA</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="la_liga.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--es-secondary);"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="ll_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro / Taktik</a>
            <a href="ll_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="ll_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
            <a href="ll_basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Medya</a>
            <a href="ll_tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
        </div>

        <div class="d-flex gap-3">
            <?php if($kullanici_takim): ?>
                <?php if($benim_macim_id): ?>
                    <a href="?action=tek_mac_simule&mac_id=<?= $benim_macim_id ?>&hafta=<?= $goster_hafta ?>" class="btn-action-primary">
                        <i class="fa-solid fa-play"></i> Maçına Çık
                    </a>
                <?php endif; ?>
                <a href="?action=hafta" class="btn-action-outline">
                    <i class="fa-solid fa-forward-fast"></i> Haftayı Oyna
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if(!$kullanici_takim): ?>
        <div class="container py-5 text-center" style="max-width: 1200px;">
            <div style="font-size: 5rem; margin-bottom: 20px;">🇪🇸</div>
            <h1 class="font-oswald mb-4" style="font-size: 4rem; color: var(--es-secondary); text-shadow: 0 0 20px rgba(245,197,24,0.3);">LA LİGA'YA HOŞ GELDİN</h1>
            <p class="text-white mb-5 fs-5">İspanya'nın en prestijli liginde yönetmek istediğin kulübü seç.</p>
            
            <div class="row g-4 justify-content-center">
                <?php 
                $secilebilir = $pdo->query("SELECT * FROM es_takimlar ORDER BY takim_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($secilebilir as $t): 
                ?>
                <div class="col-md-3 col-sm-4 col-6">
                    <div class="panel-card p-4 text-center hover-lift" onclick="window.location='?action=takim_sec&tid=<?= $t['id'] ?>';">
                        <img src="<?= htmlspecialchars($t['logo']) ?>" style="width:70px; height:70px; object-fit:contain; margin-bottom:15px; filter:drop-shadow(0 5px 10px rgba(0,0,0,0.5));">
                        <h5 class="font-oswald text-white mb-1"><?= htmlspecialchars($t['takim_adi']) ?></h5>
                        <small style="color: var(--es-secondary);">Hücum: <?= $t['hucum'] ?> | Savunma: <?= $t['savunma'] ?></small>
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
                            <span class="font-oswald text-white fs-5"><i class="fa-solid fa-list-ol me-2" style="color:var(--es-secondary);"></i> CLASIFICACIÓN</span>
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
                                        if($sira <= 4) $row_class = "zone-cl";    // Şampiyonlar Ligi
                                        elseif($sira == 5 || $sira == 6) $row_class = "zone-el"; // Avrupa Ligi / Konferans
                                        elseif($sira >= 18) $row_class = "zone-rel"; // Küme Düşme
                                        
                                        $av = $t['atilan_gol'] - $t['yenilen_gol'];
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td class="font-oswald fs-6 text-muted"><?= $sira ?></td>
                                        <td>
                                            <div class="cell-club">
                                                <img src="<?= htmlspecialchars($t['logo']) ?>"> 
                                                <span style="<?= $t['id'] == $kullanici_takim ? 'color: var(--es-secondary);' : '' ?>"><?= htmlspecialchars($t['takim_adi']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'] ?></td>
                                        <td><?= $t['galibiyet'] ?></td>
                                        <td><?= $t['beraberlik'] ?></td>
                                        <td><?= $t['malubiyet'] ?></td>
                                        <td style="color: <?= $av>0?'var(--color-win)':($av<0?'var(--color-loss)':'') ?>;"><?= $av>0?'+'.$av:$av ?></td>
                                        <td class="font-oswald fs-5" style="color: <?= $t['id'] == $kullanici_takim ? 'var(--es-secondary)' : '#fff' ?>;"><?= $t['puan'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top" style="border-color:var(--border-color); font-size:0.8rem; background:rgba(0,0,0,0.5);">
                            <span style="color:var(--es-secondary);"><i class="fa-solid fa-square"></i> 1-4: Şampiyonlar Ligi</span>
                            <span class="ms-3 text-warning"><i class="fa-solid fa-square"></i> 5-6: Avrupa Kupası</span>
                            <span class="ms-3" style="color:var(--es-primary);"><i class="fa-solid fa-square"></i> 18-20: Küme Düşme</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="panel-card" style="height: 100%; max-height:850px;">
                        <div class="panel-header">
                            <a href="?hafta=<?= max(1, $goster_hafta-1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                            <span class="font-oswald text-white fs-5">JORNADA <?= $goster_hafta ?></span>
                            <a href="?hafta=<?= min($max_hafta, $goster_hafta+1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
                        </div>
                        
                        <div class="fixture-wrapper">
                            <?php foreach($yayinlanacak_maclar as $mac): ?>
                                <div class="scorebug-container">
                                    <div class="score-grid">
                                        <div class="team-block home">
                                            <span class="team-name"><?= htmlspecialchars($mac['ev_ad']) ?></span>
                                            <img src="<?= htmlspecialchars($mac['ev_logo']) ?>" class="team-logo">
                                        </div>
                                        <div class="center-block">
                                            <span class="match-score text-muted" style="font-size:1.2rem;">V</span>
                                            <span class="match-status text-muted">BEKLİYOR</span>
                                        </div>
                                        <div class="team-block away">
                                            <img src="<?= htmlspecialchars($mac['dep_logo']) ?>" class="team-logo">
                                            <span class="team-name"><?= htmlspecialchars($mac['dep_ad']) ?></span>
                                        </div>
                                    </div>
                                    <div class="match-actions">
                                        <a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=es&hafta=<?= $goster_hafta ?>" class="action-btn text-success"><i class="fa-solid fa-satellite-dish"></i> CANLI İZLE</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php 
                            $oynananlar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] !== NULL; });
                            foreach($oynananlar as $mac): 
                            ?>
                                <div class="scorebug-container" style="border-color: rgba(245,197,24,0.5); box-shadow: 0 0 10px rgba(245,197,24,0.1);">
                                    <div class="score-grid">
                                        <div class="team-block home">
                                            <span class="team-name"><?= htmlspecialchars($mac['ev_ad']) ?></span>
                                            <img src="<?= htmlspecialchars($mac['ev_logo']) ?>" class="team-logo">
                                        </div>
                                        <div class="center-block">
                                            <span class="match-score"><?= $mac['ev_skor'] ?> - <?= $mac['dep_skor'] ?></span>
                                            <span class="match-status">FT</span>
                                        </div>
                                        <div class="team-block away">
                                            <img src="<?= htmlspecialchars($mac['dep_logo']) ?>" class="team-logo">
                                            <span class="team-name"><?= htmlspecialchars($mac['dep_ad']) ?></span>
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
                                                $oyuncu = htmlspecialchars($o['oyuncu'] ?? 'Bilinmiyor');
                                                $dakika = $o['dakika'] ?? rand(1,90);
                                                $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: " . htmlspecialchars($o['asist']) . ")</span>" : "";
                                                echo "<div class='event-item justify-content-end'>$asist <span class='event-player'>$oyuncu</span> <span class='event-time'>$dakika'</span> <i class='fa-solid fa-futbol' style='color:var(--es-secondary);'></i></div>"; 
                                                $yazilan_gol_ev++;
                                            }
                                            foreach($ev_kartlar as $k) { 
                                                $oyuncu = htmlspecialchars($k['oyuncu'] ?? 'Bilinmiyor');
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
                                                $oyuncu = htmlspecialchars($o['oyuncu'] ?? 'Bilinmiyor');
                                                $dakika = $o['dakika'] ?? rand(1,90);
                                                $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: " . htmlspecialchars($o['asist']) . ")</span>" : "";
                                                echo "<div class='event-item'><i class='fa-solid fa-futbol' style='color:var(--es-secondary);'></i> <span class='event-time'>$dakika'</span> <span class='event-player'>$oyuncu</span> $asist</div>"; 
                                                $yazilan_gol_dep++;
                                            }
                                            foreach($dep_kartlar as $k) { 
                                                $oyuncu = htmlspecialchars($k['oyuncu'] ?? 'Bilinmiyor');
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
                            
                            <?php if(empty($haftanin_fiksturu)): ?>
                                <div class="text-center p-4 text-muted font-oswald fs-5">Fikstür bulunamadı.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>

    <?php if($kullanici_takim): ?>
    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(21,0,3,0.97); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important; border-top-color: var(--es-secondary) !important;">
        <a href="la_liga.php" class="text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 20%; color: var(--es-secondary);">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block"></i> Fikstür
        </a>
        <a href="ll_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 20%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="ll_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 20%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="ll_puan.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 20%;">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block text-white"></i> Veri
        </a>
        <a href="ll_basin.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 20%;">
            <i class="fa-solid fa-microphone fs-5 mb-1 d-block text-white"></i> Medya
        </a>
    </div>
    <?php endif; ?>

    <?php if($kullanici_takim && $hafta > $max_hafta): ?>
    <div style="position:fixed; bottom: 80px; left:50%; transform:translateX(-50%); z-index: 3000;">
        <a href="ll_sezon_gecisi.php" class="btn fw-bold py-3 px-5" style="background:var(--es-secondary); color:var(--es-dark); border-radius:50px; box-shadow: 0 5px 20px rgba(245,197,24,0.5); animation: pulse_glow 2s infinite; font-size:1.1rem;">
            <i class="fa-solid fa-trophy me-2"></i> SEZON BİTTİ! Şampiyonu Gör
        </a>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
