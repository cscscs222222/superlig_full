<?php
// ==============================================================================
// PREMIER LEAGUE - ANA MERKEZ VE FİKSTÜR (PURPLE & NEON GREEN THEME)
// ==============================================================================
include '../db.php';

// Merkez Maç Motorunu Bağla (ÖN EK OLARAK 'pl_' VERİYORUZ!)
if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, 'pl_');
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
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT, savunma INT, butce BIGINT, lig VARCHAR(50),
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pl_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT, yas INT, fiyat BIGINT, lig VARCHAR(50),
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50'
    )");
} catch (Throwable $e) {}

sutunEkle($pdo, 'pl_takimlar', 'lig', "VARCHAR(50) DEFAULT 'Premier Lig'");
sutunEkle($pdo, 'pl_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Premier Lig'");

try {
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM pl_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO pl_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

// --- PREMIER LİG TAKIMLARINI OLUŞTUR (20 TAKIM) ---
$pl_takim_sayisi = $pdo->query("SELECT COUNT(*) FROM pl_takimlar")->fetchColumn();
if ($pl_takim_sayisi < 20) {
    $ingiliz_devleri = [
        ['Manchester City', 'https://cdn-icons-png.flaticon.com/512/825/825501.png', 92, 89],
        ['Arsenal', 'https://cdn-icons-png.flaticon.com/512/825/825528.png', 89, 88],
        ['Liverpool', 'https://cdn-icons-png.flaticon.com/512/825/825553.png', 89, 87],
        ['Aston Villa', 'https://cdn-icons-png.flaticon.com/512/825/825506.png', 84, 82],
        ['Tottenham', 'https://cdn-icons-png.flaticon.com/512/825/825513.png', 85, 83],
        ['Chelsea', 'https://cdn-icons-png.flaticon.com/512/825/825532.png', 86, 84],
        ['Newcastle Utd', 'https://cdn-icons-png.flaticon.com/512/825/825514.png', 84, 82],
        ['Manchester Utd', 'https://cdn-icons-png.flaticon.com/512/825/825503.png', 85, 83],
        ['West Ham', 'https://cdn-icons-png.flaticon.com/512/825/825515.png', 82, 80],
        ['Crystal Palace', 'https://cdn-icons-png.flaticon.com/512/825/825516.png', 80, 79],
        ['Brighton', 'https://cdn-icons-png.flaticon.com/512/825/825508.png', 81, 79],
        ['Bournemouth', 'https://cdn-icons-png.flaticon.com/512/825/825517.png', 79, 78],
        ['Fulham', 'https://cdn-icons-png.flaticon.com/512/825/825518.png', 79, 78],
        ['Wolves', 'https://cdn-icons-png.flaticon.com/512/825/825519.png', 80, 78],
        ['Everton', 'https://cdn-icons-png.flaticon.com/512/825/825520.png', 80, 79],
        ['Brentford', 'https://cdn-icons-png.flaticon.com/512/825/825521.png', 78, 77],
        ['Nottm Forest', 'https://cdn-icons-png.flaticon.com/512/825/825522.png', 78, 77],
        ['Leicester City', 'https://cdn-icons-png.flaticon.com/512/825/825523.png', 77, 76],
        ['Southampton', 'https://cdn-icons-png.flaticon.com/512/825/825524.png', 76, 75],
        ['Ipswich Town', 'https://cdn-icons-png.flaticon.com/512/825/825525.png', 75, 74]
    ];
    
    foreach($ingiliz_devleri as $d) {
        $ad = $d[0]; $logo = $d[1]; $huc = $d[2]; $sav = $d[3];
        $var_mi = $pdo->query("SELECT COUNT(*) FROM pl_takimlar WHERE takim_adi = '$ad'")->fetchColumn();
        if($var_mi == 0) {
            $butce = rand(30000000, 150000000); // İngiltere bütçeleri yüksektir
            $pdo->exec("INSERT INTO pl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES ('$ad', '$logo', $huc, $sav, $butce, 'Premier Lig')");
            $yeni_id = $pdo->lastInsertId();
            
            for($i=0; $i<22; $i++) { // 22 Kişilik geniş kadrolar
                $isim = $ad . " Oyuncusu " . ($i+1);
                $mevkiler = ['K', 'K', 'D', 'D', 'D', 'D', 'D', 'D', 'OS', 'OS', 'OS', 'OS', 'OS', 'OS', 'F', 'F', 'F', 'F', 'D', 'OS', 'F', 'K'];
                $mvk = $mevkiler[$i];
                $ovr = rand($sav-6, $huc+4);
                $ilk11 = ($i < 11) ? 1 : 0;
                $yedek = ($i >= 11 && $i < 18) ? 1 : 0;
                $fiyat = ($ovr * $ovr) * 4500; // İngiliz pazarı şişiktir
                $pdo->exec("INSERT INTO pl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES ($yeni_id, '$isim', '$mvk', $ovr, ".rand(18,34).", $fiyat, 'Premier Lig', $ilk11, $yedek)");
            }
        }
    }
}

// --- GARANTİ OLAY ÜRETİCİ (CL'DEKİ GİBİ KUSURSUZ) ---
function garanti_olay_uret($pdo, $takim_id, $skor) {
    $oyuncular = $pdo->query("SELECT isim FROM pl_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = $pdo->query("SELECT isim FROM pl_oyuncular WHERE takim_id = $takim_id")->fetchAll(PDO::FETCH_COLUMN);
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

$ayar = $pdo->query("SELECT * FROM pl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = $ayar['hafta'] ?? 1;
$sezon_yili = $ayar['sezon_yil'] ?? 2025;
$kullanici_takim = $ayar['kullanici_takim_id'] ?? null;

$max_hafta = 38; // Premier Lig 20 takım, 38 haftadır.

// --- FİKSTÜR ÇEKİMİ ---
$mac_sayisi = 0;
try { $mac_sayisi = $pdo->query("SELECT COUNT(*) FROM pl_maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e){}

if($mac_sayisi == 0) {
    $takimlar = $pdo->query("SELECT id FROM pl_takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar) > 1) {
        if(count($takimlar) % 2 != 0) $takimlar[] = 0;
        $t_sayisi = count($takimlar); $yari = $t_sayisi - 1; $m_sayisi = $t_sayisi / 2;

        for ($h = 1; $h <= $yari; $h++) {
            for ($i = 0; $i < $m_sayisi; $i++) {
                $ev = $takimlar[$i]; $dep = $takimlar[$t_sayisi - 1 - $i];
                if ($ev != 0 && $dep != 0) {
                    if ($i % 2 == 0) { 
                        $pdo->exec("INSERT INTO pl_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, $h, $sezon_yili)"); 
                        $pdo->exec("INSERT INTO pl_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, ".($h+$yari).", $sezon_yili)"); 
                    } else { 
                        $pdo->exec("INSERT INTO pl_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, $h, $sezon_yili)"); 
                        $pdo->exec("INSERT INTO pl_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, ".($h+$yari).", $sezon_yili)"); 
                    }
                }
            }
            $son = array_pop($takimlar); array_splice($takimlar, 1, 0, [$son]);
        }
        $pdo->exec("INSERT INTO pl_haberler (hafta, metin, tip) VALUES (1, 'Premier Lig Başlıyor! İngiltere''de nefesler tutuldu.', 'sistem')");
        header("Location: premier_lig.php"); exit;
    }
}

// --- AKSİYON YÖNETİMİ ---
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if($action == 'takim_sec' && isset($_GET['tid'])) {
        $tid = (int)$_GET['tid'];
        $pdo->exec("UPDATE pl_ayar SET kullanici_takim_id = $tid WHERE id=1");
        header("Location: premier_lig.php"); exit;
    }
    
    if($action == 'tek_mac_simule' && isset($_GET['mac_id'])) {
        $mac_id = (int)$_GET['mac_id'];
        $hedef_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta; 
        
        $m = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                           FROM pl_maclar m JOIN pl_takimlar t1 ON m.ev=t1.id JOIN pl_takimlar t2 ON m.dep=t2.id 
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

            $stmt = $pdo->prepare("UPDATE pl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE pl_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE pl_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE pl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE pl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE pl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE pl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE pl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM pl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE pl_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: premier_lig.php?hafta=$hedef_hafta"); exit;
    }

    if($action == 'hafta') {
        $maclar = $pdo->query("SELECT m.id, m.ev, m.dep, t1.takim_adi as ev_ad, t2.takim_adi as dep_ad, t1.hucum as ev_hucum, t1.savunma as ev_savunma, t2.hucum as dep_hucum, t2.savunma as dep_savunma 
                               FROM pl_maclar m JOIN pl_takimlar t1 ON m.ev=t1.id JOIN pl_takimlar t2 ON m.dep=t2.id 
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

            $stmt = $pdo->prepare("UPDATE pl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE pl_takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE pl_takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE pl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE pl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE pl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE pl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE pl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac = $pdo->query("SELECT COUNT(*) FROM pl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac == 0) { 
            $pdo->exec("UPDATE pl_ayar SET hafta = LEAST($max_hafta, hafta + 1)"); 
        }
        header("Location: premier_lig.php"); exit;
    }
}

// --- VERİ ÇEKİMİ ---
$puan_durumu = $pdo->query("SELECT * FROM pl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

$goster_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta;
if ($goster_hafta < 1) $goster_hafta = 1;
if ($goster_hafta > $max_hafta) $goster_hafta = $max_hafta;

$haftanin_fiksturu = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM pl_maclar m JOIN pl_takimlar t1 ON m.ev = t1.id JOIN pl_takimlar t2 ON m.dep = t2.id WHERE m.hafta = $goster_hafta")->fetchAll(PDO::FETCH_ASSOC);
$yayinlanacak_maclar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] === NULL; });

$benim_macim_id = null;
if($kullanici_takim) {
    $benim_macim_id = $pdo->query("SELECT id FROM pl_maclar WHERE hafta=$goster_hafta AND ev_skor IS NULL AND (ev=$kullanici_takim OR dep=$kullanici_takim)")->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Premier League | Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* PREMIER LİG TEMASI (PURPLE, NEON GREEN, HOT PINK) */
        :root {
            --pl-primary: #3d195b; /* PL Moru */
            --pl-secondary: #e2f89c; /* PL Neon Yeşili */
            --pl-accent: #00ff85; /* Daha parlak yeşil */
            --pl-pink: #ff2882; /* PL Pembesi */
            
            --bg-body: #1a0b2e;
            --bg-panel: #2d114f;
            --border-color: rgba(226, 248, 156, 0.2);
            
            --text-primary: #f9fafb;
            --text-muted: #a78bfa;
            
            --color-win: #00ff85;
            --color-loss: #ff2882;
            --color-draw: #9ca3af;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(255,40,130,0.1) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(0,255,133,0.1) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* NAVBAR */
        .pro-navbar { background: rgba(61, 25, 91, 0.95); backdrop-filter: blur(24px); border-bottom: 2px solid var(--pl-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--pl-pink); }
        .nav-brand i { color: var(--pl-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--pl-secondary); text-shadow: 0 0 10px var(--pl-secondary); }
        
        .btn-action-primary { background: var(--pl-secondary); color: var(--pl-primary); font-weight: 800; padding: 8px 20px; border-radius: 4px; text-decoration: none; border: none; transition: 0.3s;}
        .btn-action-primary:hover { background: var(--pl-accent); color: #000; box-shadow: 0 0 15px var(--pl-accent); }
        .btn-action-outline { background: transparent; border: 1px solid var(--pl-pink); color: var(--pl-pink); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.3s;}
        .btn-action-outline:hover { background: var(--pl-pink); color: #fff; box-shadow: 0 0 15px var(--pl-pink); }

        /* DASHBOARD KARTLARI */
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); display: flex; flex-direction: column; }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.2); flex-shrink: 0;}
        
        /* Puan Tablosu */
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--pl-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; }
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; transition: 0.2s; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(226,248,156,0.05); }
        
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 700; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.8));}
        
        /* Premier Lig Bölgeleme Kuralları */
        .data-table tbody tr td:first-child { border-left: 4px solid transparent; }
        .zone-cl td:first-child { border-left-color: var(--pl-secondary) !important; background: rgba(226,248,156,0.05); }
        .zone-el td:first-child { border-left-color: #fbbf24 !important; }
        .zone-rel td:first-child { border-left-color: var(--pl-pink) !important; opacity: 0.8;}

        /* FİKSTÜR (SIKIŞMASI ENGELLENMİŞ KUSURSUZ FLEXBOX) */
        .fixture-wrapper { display: flex; flex-direction: column; gap: 15px; overflow-y: auto; padding: 1rem; flex: 1; }
        
        .scorebug-container { background: rgba(0,0,0,0.5); border: 1px solid rgba(255,40,130,0.2); border-radius: 10px; overflow: hidden; transition: 0.3s; width: 100%; flex-shrink: 0; }
        .scorebug-container:hover { border-color: var(--pl-secondary); box-shadow: 0 5px 15px rgba(226,248,156,0.15); transform: translateY(-2px);}
        
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
            background: var(--pl-primary); 
            border-left: 1px solid rgba(226,248,156,0.2); 
            border-right: 1px solid rgba(226,248,156,0.2); 
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            padding: 10px 0;
        }
        .match-score { font-family: 'Oswald', sans-serif; font-size: 1.8rem; font-weight: 900; color: var(--pl-secondary); line-height: 1; letter-spacing: 1px; text-shadow: 0 2px 4px rgba(0,0,0,0.8); }
        .match-status { font-size: 0.75rem; color: #fff; font-weight: 700; letter-spacing: 1px; margin-top: 4px; }
        
        .match-actions { display: flex; background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.1); }
        .action-btn { flex: 1; padding: 10px; text-align: center; text-decoration: none; color: var(--pl-secondary); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; transition: 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px;}
        .action-btn:hover { background: var(--pl-secondary); color: var(--pl-primary) !important; }
        
        /* GOL DETAYLARI (SKOR KİLİDİ İLE KORUMALI) */
        .events-grid { display: flex; width: 100%; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(255,40,130,0.2); padding: 8px 0; font-size: 0.8rem; }
        .event-col { display: flex; flex-direction: column; gap: 6px; padding: 0 15px; flex: 1; min-width: 0;}
        .event-col.home { align-items: flex-end; text-align: right; } 
        .event-col.away { align-items: flex-start; text-align: left; }
        .event-col.center { width: 100px; flex: none; }
        
        .event-time { font-family: 'Oswald'; font-weight: 700; color: var(--pl-pink); flex-shrink: 0; min-width: 25px;}
        .event-item { display: flex; align-items: center; gap: 8px; font-weight: 600; max-width: 100%; }
        .event-player { color: #fff; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px;}
        .event-assist { color: var(--text-muted); font-size: 0.7rem; font-style: italic; white-space: nowrap;}

        .ref-card { width: 10px; height: 14px; border-radius: 2px; flex-shrink: 0; transform: rotate(3deg); box-shadow: 0 1px 3px rgba(0,0,0,0.5);}
        .ref-card.yellow { background-color: #fbbf24; }
        .ref-card.red { background-color: var(--color-loss); }

        .hover-lift { transition: 0.3s; cursor: pointer; }
        .hover-lift:hover { transform: translateY(-5px); }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="premier_lig.php" class="nav-brand"><i class="fa-solid fa-crown"></i> <span class="font-oswald">PREMIER LEAGUE</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="#" class="nav-link-item text-white fw-bold"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="#" class="nav-link-item" onclick="alert('Yakında: PL Kadro');"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="pl_basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Medya & Psikoloji</a>
            <a href="../super_lig/superlig.php" class="nav-link-item text-danger"><i class="fa-solid fa-plane"></i> Türkiye'ye Dön</a>
            <a href="../la_liga/la_liga.php" class="nav-link-item" style="color:#f5c518;"><i class="fa-solid fa-sun"></i> La Liga</a>
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
            <img src="https://cdn-icons-png.flaticon.com/512/825/825550.png" style="width: 100px; margin-bottom: 20px; filter: drop-shadow(0 0 15px var(--pl-secondary));">
            <h1 class="font-oswald mb-4" style="font-size: 4rem; color: var(--pl-secondary); text-shadow: 0 0 20px rgba(226,248,156,0.3);">İNGİLTERE'YE HOŞ GELDİN</h1>
            <p class="text-white mb-5 fs-5">Dünyanın en zorlu liginde yönetmek istediğin kulübü seç.</p>
            
            <div class="row g-4 justify-content-center">
                <?php 
                $secilebilir = $pdo->query("SELECT * FROM pl_takimlar ORDER BY takim_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($secilebilir as $t): 
                ?>
                <div class="col-md-3 col-sm-4 col-6">
                    <div class="panel-card p-4 text-center hover-lift" onclick="window.location='?action=takim_sec&tid=<?= $t['id'] ?>';">
                        <img src="<?= $t['logo'] ?>" style="width:70px; height:70px; object-fit:contain; margin-bottom:15px; filter:drop-shadow(0 5px 10px rgba(0,0,0,0.5));">
                        <h5 class="font-oswald text-white mb-1"><?= $t['takim_adi'] ?></h5>
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
                            <span class="font-oswald text-white fs-5"><i class="fa-solid fa-list-ol me-2" style="color:var(--pl-secondary);"></i> LEAGUE TABLE</span>
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
                                        if($sira <= 4) $row_class = "zone-cl"; // Şampiyonlar Ligi
                                        elseif($sira == 5) $row_class = "zone-el"; // Avrupa Ligi
                                        elseif($sira >= 18) $row_class = "zone-rel"; // Küme Düşme
                                        
                                        $av = $t['atilan_gol'] - $t['yenilen_gol'];
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td class="font-oswald fs-6 text-muted"><?= $sira ?></td>
                                        <td>
                                            <div class="cell-club">
                                                <img src="<?= $t['logo'] ?>"> 
                                                <span style="<?= $t['id'] == $kullanici_takim ? 'color: var(--pl-secondary);' : '' ?>"><?= $t['takim_adi'] ?></span>
                                            </div>
                                        </td>
                                        <td><?= $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'] ?></td>
                                        <td><?= $t['galibiyet'] ?></td>
                                        <td><?= $t['beraberlik'] ?></td>
                                        <td><?= $t['malubiyet'] ?></td>
                                        <td style="color: <?= $av>0?'var(--color-win)':($av<0?'var(--color-loss)':'') ?>;"><?= $av>0?'+'.$av:$av ?></td>
                                        <td class="font-oswald fs-5" style="color: <?= $t['id'] == $kullanici_takim ? 'var(--pl-secondary)' : '#fff' ?>;"><?= $t['puan'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top" style="border-color:var(--border-color); font-size:0.8rem; background:rgba(0,0,0,0.5);">
                            <span style="color:var(--pl-secondary);"><i class="fa-solid fa-square"></i> 1-4: Şampiyonlar Ligi</span>
                            <span class="ms-3 text-warning"><i class="fa-solid fa-square"></i> 5: Avrupa Ligi</span>
                            <span class="ms-3" style="color:var(--pl-pink);"><i class="fa-solid fa-square"></i> 18-20: Küme Düşme</span>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="panel-card" style="height: 100%; max-height:850px;">
                        <div class="panel-header">
                            <a href="?hafta=<?= max(1, $goster_hafta-1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-left"></i></a>
                            <span class="font-oswald text-white fs-5">MATCHWEEK <?= $goster_hafta ?></span>
                            <a href="?hafta=<?= min($max_hafta, $goster_hafta+1) ?>" class="btn-action-outline btn-sm"><i class="fa-solid fa-chevron-right"></i></a>
                        </div>
                        
                        <div class="fixture-wrapper">
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
                                        <a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=pl&hafta=<?= $goster_hafta ?>" class="action-btn text-success"><i class="fa-solid fa-satellite-dish"></i> CANLI İZLE</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php 
                            $oynananlar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] !== NULL; });
                            foreach($oynananlar as $mac): 
                            ?>
                                <div class="scorebug-container" style="border-color: rgba(226,248,156,0.5); box-shadow: 0 0 10px rgba(226,248,156,0.1);">
                                    <div class="score-grid">
                                        <div class="team-block home">
                                            <span class="team-name"><?= $mac['ev_ad'] ?></span>
                                            <img src="<?= $mac['ev_logo'] ?>" class="team-logo">
                                        </div>
                                        <div class="center-block">
                                            <span class="match-score"><?= $mac['ev_skor'] ?> - <?= $mac['dep_skor'] ?></span>
                                            <span class="match-status">FT</span>
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
                                            // EV SAHİBİ GOL YAZDIRMA (SKOR KİLİDİ İLE)
                                            $yazilan_gol_ev = 0;
                                            foreach($ev_olaylar as $o) {
                                                if ($yazilan_gol_ev >= $mac['ev_skor']) break; 
                                                
                                                $tip = $o['tip'] ?? 'gol';
                                                if(strtolower($tip) != 'gol') continue; 

                                                $oyuncu = $o['oyuncu'] ?? 'Bilinmiyor';
                                                $dakika = $o['dakika'] ?? rand(1,90);
                                                $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                                
                                                echo "<div class='event-item justify-content-end'>
                                                        $asist 
                                                        <span class='event-player'>$oyuncu</span> 
                                                        <span class='event-time'>$dakika'</span> 
                                                        <i class='fa-solid fa-futbol' style='color:var(--pl-secondary);'></i>
                                                      </div>"; 
                                                $yazilan_gol_ev++;
                                            }
                                            
                                            // KART YAZDIRMA
                                            foreach($ev_kartlar as $k) { 
                                                $oyuncu = $k['oyuncu'] ?? 'Bilinmiyor';
                                                $dakika = $k['dakika'] ?? rand(1,90);
                                                $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı');
                                                $renk_class = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                                echo "<div class='event-item justify-content-end'>
                                                        <span class='event-player'>$oyuncu</span> 
                                                        <span class='event-time'>$dakika'</span> 
                                                        <div class='ref-card $renk_class'></div>
                                                      </div>"; 
                                            }
                                            ?>
                                        </div>
                                        
                                        <div class="event-col center"></div>

                                        <div class="event-col away">
                                            <?php 
                                            // DEPLASMAN GOL YAZDIRMA (SKOR KİLİDİ İLE)
                                            $yazilan_gol_dep = 0;
                                            foreach($dep_olaylar as $o) {
                                                if ($yazilan_gol_dep >= $mac['dep_skor']) break; 
                                                
                                                $tip = $o['tip'] ?? 'gol';
                                                if(strtolower($tip) != 'gol') continue; 

                                                $oyuncu = $o['oyuncu'] ?? 'Bilinmiyor';
                                                $dakika = $o['dakika'] ?? rand(1,90);
                                                $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                                
                                                echo "<div class='event-item'>
                                                        <i class='fa-solid fa-futbol' style='color:var(--pl-secondary);'></i> 
                                                        <span class='event-time'>$dakika'</span> 
                                                        <span class='event-player'>$oyuncu</span> 
                                                        $asist
                                                      </div>"; 
                                                $yazilan_gol_dep++;
                                            }
                                            
                                            // KART YAZDIRMA
                                            foreach($dep_kartlar as $k) { 
                                                $oyuncu = $k['oyuncu'] ?? 'Bilinmiyor';
                                                $dakika = $k['dakika'] ?? rand(1,90);
                                                $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı');
                                                $renk_class = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                                echo "<div class='event-item'>
                                                        <div class='ref-card $renk_class'></div> 
                                                        <span class='event-time'>$dakika'</span> 
                                                        <span class='event-player'>$oyuncu</span>
                                                      </div>"; 
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>