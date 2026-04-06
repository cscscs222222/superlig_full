<?php
// ==============================================================================
// UEFA CONFERENCE LEAGUE - ANA MERKEZ VE MAÇ MOTORU (ORANGE & WHITE THEME)
// ==============================================================================
include '../db.php';

if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, 'uecl_');
} else {
    die("<h2 style='color:red;text-align:center;padding:50px;'>HATA: MatchEngine.php bulunamadı!</h2>");
}

function sutunEkleUecl($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

// ============================================================
// TABLO KURULUMU
// ============================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS uecl_ayar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hafta INT DEFAULT 1,
        sezon_yil INT DEFAULT 2025,
        kullanici_takim_id INT DEFAULT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS uecl_haberler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        hafta INT,
        metin TEXT,
        tip VARCHAR(50)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS uecl_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ev INT,
        dep INT,
        hafta INT,
        sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL,
        dep_skor INT DEFAULT NULL,
        ev_olaylar TEXT,
        dep_olaylar TEXT,
        ev_kartlar TEXT,
        dep_kartlar TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS uecl_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        takim_adi VARCHAR(100),
        logo VARCHAR(255),
        hucum INT DEFAULT 70,
        savunma INT DEFAULT 70,
        butce BIGINT DEFAULT 20000000,
        lig VARCHAR(50) DEFAULT 'Avrupa',
        puan INT DEFAULT 0,
        galibiyet INT DEFAULT 0,
        beraberlik INT DEFAULT 0,
        malubiyet INT DEFAULT 0,
        atilan_gol INT DEFAULT 0,
        yenilen_gol INT DEFAULT 0,
        dizilis VARCHAR(20) DEFAULT '4-3-3',
        oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli'
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS uecl_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY,
        takim_id INT,
        isim VARCHAR(100),
        mevki VARCHAR(10),
        ovr INT DEFAULT 70,
        yas INT DEFAULT 25,
        fiyat BIGINT DEFAULT 5000000,
        lig VARCHAR(50) DEFAULT 'Avrupa',
        ilk_11 TINYINT(1) DEFAULT 0,
        yedek TINYINT(1) DEFAULT 0,
        form INT DEFAULT 6,
        fitness INT DEFAULT 100,
        moral INT DEFAULT 80,
        ceza_hafta INT DEFAULT 0,
        sakatlik_hafta INT DEFAULT 0
    )");

    // Tournaments tablosu (Super Cup kazanan takımları takip eder)
    $pdo->exec("CREATE TABLE IF NOT EXISTS tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        turnuva VARCHAR(50),
        sezon_yil INT,
        sampiyon_id INT DEFAULT NULL,
        sampiyon_adi VARCHAR(100) DEFAULT NULL,
        sampiyon_lig VARCHAR(50) DEFAULT NULL,
        UNIQUE KEY uniq_turnuva_sezon (turnuva, sezon_yil)
    )");

    // UEFA Coefficients tablosu
    $pdo->exec("CREATE TABLE IF NOT EXISTS uefa_coefficients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ulke_adi VARCHAR(50) UNIQUE,
        toplam_puan DECIMAL(10,3) DEFAULT 0,
        sezon_puan DECIMAL(10,3) DEFAULT 0,
        ucl_kota INT DEFAULT 2,
        uel_kota INT DEFAULT 2,
        uecl_kota INT DEFAULT 1
    )");

    // Başlangıç katsayıları (UEFA 2024-25 verileri baz alınarak)
    $count = $pdo->query("SELECT COUNT(*) FROM uefa_coefficients")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO uefa_coefficients (ulke_adi, toplam_puan, ucl_kota, uel_kota, uecl_kota) VALUES
            ('İngiltere', 103.160, 4, 2, 1),
            ('İspanya',   96.231, 4, 2, 1),
            ('Almanya',   82.946, 4, 2, 1),
            ('İtalya',    82.946, 4, 2, 1),
            ('Fransa',    67.164, 3, 2, 1),
            ('Türkiye',   38.500, 2, 2, 1),
            ('Portekiz',  61.866, 3, 2, 1)
        ");
    }

    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM uecl_ayar")->fetchColumn();
    if ($ayar_sayisi == 0) {
        $pdo->exec("INSERT INTO uecl_ayar (hafta, sezon_yil) VALUES (1, 2025)");
    }
} catch (Throwable $e) {}

sutunEkleUecl($pdo, 'uecl_takimlar', 'lig', "VARCHAR(50) DEFAULT 'Avrupa'");
sutunEkleUecl($pdo, 'uecl_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Avrupa'");

// ============================================================
// UEFA KATSAYISİ GÜNCELLEME FONKSİYONU
// ============================================================
function uecl_ulke_puani_ekle($pdo, $takim_id, $puan) {
    try {
        $lig = $pdo->query("SELECT lig FROM uecl_takimlar WHERE id = " . (int)$takim_id)->fetchColumn();
        $ulke = uecl_uecl_ulke_bul($lig);
        if ($ulke) {
            $pdo->exec("UPDATE uefa_coefficients SET toplam_puan = toplam_puan + $puan, sezon_puan = sezon_puan + $puan WHERE ulke_adi = '$ulke'");
            $pdo->exec("UPDATE uefa_siralamasi SET toplam_puan = toplam_puan + " . (int)($puan * 1000) . ", guncel_sezon_puan = guncel_sezon_puan + " . (int)($puan * 1000) . " WHERE ulke_adi = '$ulke'");
        }
    } catch(Throwable $e) {}
}

function uecl_uecl_ulke_bul($lig) {
    $map = [
        'Süper Lig'  => 'Türkiye',
        'Premier Lig' => 'İngiltere',
        'La Liga'    => 'İspanya',
        'Bundesliga' => 'Almanya',
        'Serie A'    => 'İtalya',
        'Ligue 1'    => 'Fransa',
        'Liga NOS'   => 'Portekiz',
    ];
    return $map[$lig] ?? null;
}

// ============================================================
// GARANTI OLAY ÜRETİCİ
// ============================================================
function uecl_olay_uret($pdo, $takim_id, $skor) {
    $oyuncular = $pdo->query("SELECT isim FROM uecl_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = $pdo->query("SELECT isim FROM uecl_oyuncular WHERE takim_id = $takim_id")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular = ['Bilinmeyen Oyuncu'];
    $olaylar = [];
    for($i=0; $i<$skor; $i++) {
        $golcu = $oyuncular[array_rand($oyuncular)];
        $asistci = (rand(1,100)>40) ? $oyuncular[array_rand($oyuncular)] : '-';
        if($golcu == $asistci) $asistci = '-';
        $olaylar[] = ['tip'=>'gol','oyuncu'=>$golcu,'asist'=>$asistci,'dakika'=>rand(1,90)];
    }
    usort($olaylar, fn($a,$b) => $a['dakika'] <=> $b['dakika']);
    $kartlar = [];
    $ks = rand(0,3);
    for($i=0;$i<$ks;$i++) {
        $kartlar[] = ['tip'=>(rand(1,100)>85?'Kırmızı':'Sarı'),'oyuncu'=>$oyuncular[array_rand($oyuncular)],'dakika'=>rand(1,90)];
    }
    usort($kartlar, fn($a,$b) => $a['dakika'] <=> $b['dakika']);
    return ['olaylar'=>json_encode($olaylar,JSON_UNESCAPED_UNICODE),'kartlar'=>json_encode($kartlar,JSON_UNESCAPED_UNICODE)];
}

$ayar = $pdo->query("SELECT * FROM uecl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = (int)($ayar['hafta'] ?? 1);
$sezon_yili = (int)($ayar['sezon_yil'] ?? 2025);
$kullanici_takim = $ayar['kullanici_takim_id'] ?? null;

$uecl_takim_sayisi = (int)$pdo->query("SELECT COUNT(*) FROM uecl_takimlar")->fetchColumn();

// ============================================================
// FİKSTÜR OLUŞTURMA (32 takım, 8 haftalık lig aşaması)
// ============================================================
$mac_sayisi = 0;
try { $mac_sayisi = $pdo->query("SELECT COUNT(*) FROM uecl_maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e){}

if ($uecl_takim_sayisi >= 8 && $mac_sayisi == 0) {
    $ids = $pdo->query("SELECT id FROM uecl_takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    $ids = array_map('intval', $ids);
    $n = count($ids);
    if ($n % 2 != 0) { $ids[] = 0; $n++; }
    $arr = $ids;
    $all_matchups = [];
    for ($h = 1; $h <= 8; $h++) {
        $round = [];
        for ($i = 0; $i < $n / 2; $i++) {
            $t1 = $arr[$i]; $t2 = $arr[$n - 1 - $i];
            if ($t1 > 0 && $t2 > 0) $round[] = [$t1, $t2];
        }
        $all_matchups[$h] = $round;
        $last = array_pop($arr);
        array_splice($arr, 1, 0, [$last]);
    }
    $home_count = array_fill_keys($ids, 0);
    foreach ($all_matchups as $h => $round) {
        foreach ($round as [$t1, $t2]) {
            if (!isset($home_count[$t1])) $home_count[$t1] = 0;
            if (!isset($home_count[$t2])) $home_count[$t2] = 0;
            $h1 = $home_count[$t1]; $h2 = $home_count[$t2];
            if ($h1 < $h2) { $ev = $t1; $dep = $t2; }
            elseif ($h2 < $h1) { $ev = $t2; $dep = $t1; }
            else { $ev = (($t1 + $h) % 2 == 0) ? $t1 : $t2; $dep = (($t1 + $h) % 2 == 0) ? $t2 : $t1; }
            $home_count[$ev]++;
            $pdo->exec("INSERT INTO uecl_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, $h, $sezon_yili)");
        }
    }
    $pdo->exec("INSERT INTO uecl_haberler (hafta, metin, tip) VALUES (1, 'Conference League kuraları çekildi. Perşembe geceleri başlıyor!', 'sistem')");
    header("Location: uecl.php"); exit;
}

// ============================================================
// AKSİYON YÖNETİMİ
// ============================================================
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'takim_sec' && isset($_GET['tid'])) {
        $tid = (int)$_GET['tid'];
        $pdo->exec("UPDATE uecl_ayar SET kullanici_takim_id = $tid WHERE id = 1");
        header("Location: uecl.php"); exit;
    }

    if ($action == 'sifirla') {
        $pdo->exec("TRUNCATE TABLE uecl_maclar");
        $pdo->exec("UPDATE uecl_takimlar SET puan=0,galibiyet=0,beraberlik=0,malubiyet=0,atilan_gol=0,yenilen_gol=0");
        $pdo->exec("UPDATE uecl_ayar SET hafta=1");
        header("Location: uecl.php"); exit;
    }

    // TEK MAÇ SİMÜLE
    if ($action == 'tek_mac_simule' && isset($_GET['mac_id'])) {
        $mac_id = (int)$_GET['mac_id'];
        $hedef_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta;
        $m = $pdo->query("SELECT m.id, m.ev, m.dep FROM uecl_maclar m WHERE m.id = $mac_id AND m.ev_skor IS NULL")->fetch(PDO::FETCH_ASSOC);
        if ($m) {
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = uecl_olay_uret($pdo, $m['ev'], $ev_skor);
            $dep_detay = uecl_olay_uret($pdo, $m['dep'], $dep_skor);
            $stmt = $pdo->prepare("UPDATE uecl_maclar SET ev_skor=?,dep_skor=?,ev_olaylar=?,dep_olaylar=?,ev_kartlar=?,dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor,$dep_skor,$ev_detay['olaylar'],$dep_detay['olaylar'],$ev_detay['kartlar'],$dep_detay['kartlar'],$m['id']]);
            $pdo->exec("UPDATE uecl_takimlar SET atilan_gol=atilan_gol+$ev_skor,yenilen_gol=yenilen_gol+$dep_skor WHERE id={$m['ev']}");
            $pdo->exec("UPDATE uecl_takimlar SET atilan_gol=atilan_gol+$dep_skor,yenilen_gol=yenilen_gol+$ev_skor WHERE id={$m['dep']}");
            if ($ev_skor > $dep_skor) {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['ev']}");
                $pdo->exec("UPDATE uecl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}");
                uecl_ulke_puani_ekle($pdo, $m['ev'], 0.3);
            } elseif ($ev_skor == $dep_skor) {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+1,beraberlik=beraberlik+1 WHERE id IN ({$m['ev']},{$m['dep']})");
                uecl_ulke_puani_ekle($pdo, $m['ev'], 0.1); uecl_ulke_puani_ekle($pdo, $m['dep'], 0.1);
            } else {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['dep']}");
                $pdo->exec("UPDATE uecl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}");
                uecl_ulke_puani_ekle($pdo, $m['dep'], 0.3);
            }
        }
        $kalan = $pdo->query("SELECT COUNT(*) FROM uecl_maclar WHERE hafta=$hafta AND ev_skor IS NULL")->fetchColumn();
        if ($kalan == 0) {
            $pdo->exec("UPDATE uecl_oyuncular SET ceza_hafta=GREATEST(0,ceza_hafta-1) WHERE ceza_hafta>0");
            $pdo->exec("UPDATE uecl_oyuncular SET fitness=GREATEST(30,fitness-ROUND(RAND()*15+5)) WHERE ilk_11=1");
            $pdo->exec("UPDATE uecl_oyuncular SET fitness=LEAST(100,fitness+ROUND(RAND()*20+10)) WHERE ilk_11=0");
            $pdo->exec("UPDATE uecl_ayar SET hafta=LEAST(9,hafta+1)");
        }
        header("Location: uecl.php?hafta=$hedef_hafta"); exit;
    }

    // HAFTA OYNATma
    if ($action == 'hafta' || $action == 'hafta_full') {
        $pdo->exec("UPDATE uecl_oyuncular SET ilk_11=0,yedek=1 WHERE ilk_11=1 AND (ceza_hafta>0 OR sakatlik_hafta>0)");
        $maclar = $pdo->query("SELECT m.id, m.ev, m.dep FROM uecl_maclar m WHERE m.hafta=$hafta AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($maclar as $m) {
            if ($action == 'hafta' && $kullanici_takim && ($m['ev'] == $kullanici_takim || $m['dep'] == $kullanici_takim)) continue;
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = uecl_olay_uret($pdo, $m['ev'], $ev_skor);
            $dep_detay = uecl_olay_uret($pdo, $m['dep'], $dep_skor);
            $stmt = $pdo->prepare("UPDATE uecl_maclar SET ev_skor=?,dep_skor=?,ev_olaylar=?,dep_olaylar=?,ev_kartlar=?,dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor,$dep_skor,$ev_detay['olaylar'],$dep_detay['olaylar'],$ev_detay['kartlar'],$dep_detay['kartlar'],$m['id']]);
            $pdo->exec("UPDATE uecl_takimlar SET atilan_gol=atilan_gol+$ev_skor,yenilen_gol=yenilen_gol+$dep_skor WHERE id={$m['ev']}");
            $pdo->exec("UPDATE uecl_takimlar SET atilan_gol=atilan_gol+$dep_skor,yenilen_gol=yenilen_gol+$ev_skor WHERE id={$m['dep']}");
            if ($ev_skor > $dep_skor) {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['ev']}");
                $pdo->exec("UPDATE uecl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}");
                uecl_ulke_puani_ekle($pdo, $m['ev'], 0.3);
            } elseif ($ev_skor == $dep_skor) {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+1,beraberlik=beraberlik+1 WHERE id IN ({$m['ev']},{$m['dep']})");
                uecl_ulke_puani_ekle($pdo, $m['ev'], 0.1); uecl_ulke_puani_ekle($pdo, $m['dep'], 0.1);
            } else {
                $pdo->exec("UPDATE uecl_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['dep']}");
                $pdo->exec("UPDATE uecl_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}");
                uecl_ulke_puani_ekle($pdo, $m['dep'], 0.3);
            }
        }
        $kalan = $pdo->query("SELECT COUNT(*) FROM uecl_maclar WHERE hafta=$hafta AND ev_skor IS NULL")->fetchColumn();
        if ($kalan == 0) {
            $pdo->exec("UPDATE uecl_oyuncular SET ceza_hafta=GREATEST(0,ceza_hafta-1) WHERE ceza_hafta>0");
            $pdo->exec("UPDATE uecl_oyuncular SET fitness=GREATEST(30,fitness-ROUND(RAND()*15+5)) WHERE ilk_11=1");
            $pdo->exec("UPDATE uecl_oyuncular SET fitness=LEAST(100,fitness+ROUND(RAND()*20+10)) WHERE ilk_11=0");
            $pdo->exec("UPDATE uecl_ayar SET hafta=LEAST(9,hafta+1)");
        }
        header("Location: uecl.php"); exit;
    }
}

// ============================================================
// VERİ ÇEKİMİ
// ============================================================
$maclar_bu_hafta = [];
if ($uecl_takim_sayisi >= 8) {
    try {
        $maclar_bu_hafta = $pdo->query(
            "SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo
             FROM uecl_maclar m
             JOIN uecl_takimlar t1 ON m.ev=t1.id
             JOIN uecl_takimlar t2 ON m.dep=t2.id
             WHERE m.hafta=$hafta AND m.sezon_yil=$sezon_yili
             ORDER BY m.id"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e) {}
}
$puan_durumu = $pdo->query("SELECT * FROM uecl_takimlar ORDER BY puan DESC,(atilan_gol-yenilen_gol) DESC,atilan_gol DESC LIMIT 16")->fetchAll(PDO::FETCH_ASSOC);
$kullanici_takim_bilgi = null;
if ($kullanici_takim) {
    try { $kullanici_takim_bilgi = $pdo->query("SELECT * FROM uecl_takimlar WHERE id=$kullanici_takim")->fetch(PDO::FETCH_ASSOC); } catch(Throwable $e){}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UEFA Conference League | UECL Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --uecl-green: #2ecc71;
            --uecl-dark: #021a08;
            --uecl-panel: #08180a;
            --uecl-border: rgba(46,204,113,0.2);
            --uecl-accent: #27ae60;
            --gold: #d4af37;
        }
        body { background-color: var(--uecl-dark); color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        .pro-navbar { background: rgba(8,24,10,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid var(--uecl-border); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 70px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; }
        .nav-brand img { height: 40px; }
        .nav-links a { color: #ccc; font-size: 0.9rem; padding: 8px 14px; text-decoration: none; border-radius: 8px; transition: 0.2s; }
        .nav-links a:hover, .nav-links a.active { background: rgba(46,204,113,0.15); color: var(--uecl-accent); }
        .hero-banner { background: linear-gradient(135deg, #021a08 0%, #0d2d14 50%, #021a08 100%); border-bottom: 2px solid var(--uecl-green); padding: 40px 2rem; text-align: center; }
        .hero-title { font-size: 3.5rem; font-weight: 900; color: var(--uecl-accent); text-shadow: 0 0 30px rgba(46,204,113,0.4); margin: 0; }
        .hero-subtitle { color: #999; font-size: 1rem; letter-spacing: 2px; margin-top: 6px; }
        .panel { background: var(--uecl-panel); border: 1px solid var(--uecl-border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .panel-title { font-family: 'Oswald', sans-serif; font-size: 1.1rem; font-weight: 700; color: var(--uecl-accent); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--uecl-border); display: flex; align-items: center; gap: 8px; }
        .btn-uecl { background: linear-gradient(135deg, var(--uecl-green), #1a8a3a); color: #fff; border: none; border-radius: 10px; padding: 10px 20px; font-weight: 700; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-uecl:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46,204,113,0.4); color: #fff; }
        .btn-uecl-outline { background: transparent; color: var(--uecl-accent); border: 1px solid var(--uecl-green); border-radius: 10px; padding: 8px 16px; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-uecl-outline:hover { background: rgba(46,204,113,0.15); color: var(--uecl-accent); }
        .mac-kart { background: rgba(255,255,255,0.03); border: 1px solid var(--uecl-border); border-radius: 12px; padding: 16px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between; transition: 0.2s; }
        .mac-kart:hover { background: rgba(46,204,113,0.07); border-color: var(--uecl-green); }
        .takim-col { display: flex; align-items: center; gap: 10px; width: 35%; }
        .takim-col.dep { justify-content: flex-end; }
        .takim-logo { width: 36px; height: 36px; object-fit: contain; }
        .takim-adi { font-weight: 600; font-size: 0.9rem; }
        .skor-col { text-align: center; flex: 1; }
        .skor-badge { background: rgba(46,204,113,0.15); border: 1px solid var(--uecl-green); border-radius: 8px; padding: 8px 18px; display: inline-block; }
        .skor-text { font-family: 'Oswald', sans-serif; font-size: 1.4rem; font-weight: 900; color: #fff; }
        .vs-text { color: #666; font-size: 0.8rem; font-weight: 700; }
        .mac-hafta-badge { font-size: 0.7rem; color: var(--uecl-accent); font-weight: 700; letter-spacing: 1px; }
        .puan-tbl { width: 100%; }
        .puan-tbl th { font-size: 0.75rem; color: #666; letter-spacing: 1px; text-transform: uppercase; padding: 8px 12px; border-bottom: 1px solid var(--uecl-border); }
        .puan-tbl td { padding: 10px 12px; font-size: 0.9rem; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .puan-tbl tr:hover td { background: rgba(46,204,113,0.05); }
        .puan-tbl tr.kullanici-satir td { background: rgba(46,204,113,0.1); border-left: 3px solid var(--uecl-green); }
        .sira-badge { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
        .sira-ucl { background: rgba(0,229,255,0.15); color: #00e5ff; border: 1px solid rgba(0,229,255,0.3); }
        .sira-normal { background: rgba(255,255,255,0.05); color: #888; }
        .no-teams-card { background: var(--uecl-panel); border: 2px dashed var(--uecl-border); border-radius: 20px; padding: 60px; text-align: center; }
        .hafta-nav { display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.03); border: 1px solid var(--uecl-border); border-radius: 12px; padding: 12px 20px; margin-bottom: 20px; }
        .hafta-badge { background: rgba(46,204,113,0.15); color: var(--uecl-accent); border: 1px solid var(--uecl-green); border-radius: 20px; padding: 4px 16px; font-weight: 700; font-size: 0.85rem; }
        .nokaut-badge { background: rgba(212,175,55,0.15); color: var(--gold); border: 1px solid rgba(212,175,55,0.3); border-radius: 20px; padding: 4px 16px; font-weight: 700; font-size: 0.85rem; }
    </style>
</head>
<body>

<nav class="pro-navbar">
    <a href="../index.php" class="nav-brand font-oswald">
        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/3/35/UEFA_Europa_League_logo_%282.0%29.svg/200px-UEFA_Europa_League_logo_%282.0%29.svg.png"
             onerror="this.style.display='none'" alt="UECL">
        &nbsp;CONFERENCE LEAGUE
    </a>
    <div class="nav-links d-flex gap-2">
        <a href="uecl.php" class="active"><i class="fa-solid fa-calendar-days"></i> Fikstür</a>
        <a href="uecl_puan.php"><i class="fa-solid fa-table"></i> Puan Durumu</a>
        <a href="../champions_league/cl_uefa.php"><i class="fa-solid fa-globe"></i> Ülke Puanları</a>
        <a href="../takvim.php"><i class="fa-solid fa-clock"></i> Takvim</a>
        <a href="../index.php"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
    </div>
</nav>

<div class="hero-banner">
    <h1 class="hero-title font-oswald"><i class="fa-solid fa-fire me-2"></i>UEFA CONFERENCE LEAGUE</h1>
    <p class="hero-subtitle">KONFERANS LİGİ — <?= $sezon_yili ?>/<?= $sezon_yili+1 ?> SEZONU</p>
</div>

<div class="container-fluid py-4 px-4">

<?php if ($uecl_takim_sayisi == 0): ?>
    <div class="no-teams-card">
        <i class="fa-solid fa-fire" style="font-size:4rem; color:var(--uecl-accent); margin-bottom:20px;"></i>
        <h3 class="font-oswald" style="color:var(--uecl-accent);">CONFERENCE LEAGUE KATILIMCILARI BEKLENİYOR</h3>
        <p class="text-muted mt-3">Sezon sonunda liglerin 7. ve 8. sıralarını bitiren takımlar<br>otomatik olarak Avrupa Ligi'ne katılım hakkı kazanacak.</p>
        <div class="mt-4">
            <span class="badge bg-secondary me-2">Süper Lig 7.-8.</span>
            <span class="badge bg-secondary me-2">Premier Lig 7.-8.</span>
            <span class="badge bg-secondary me-2">La Liga 7.-8.</span>
            <span class="badge bg-secondary me-2">Bundesliga 7.-8.</span>
            <span class="badge bg-secondary">Serie A 7.-8.</span>
        </div>
        <div class="mt-4">
            <a href="../index.php" class="btn-uecl"><i class="fa-solid fa-arrow-left"></i> Ana Menüye Dön</a>
        </div>
    </div>

<?php elseif ($uecl_takim_sayisi < 8): ?>
    <div class="no-teams-card">
        <i class="fa-solid fa-users" style="font-size:4rem; color:var(--uecl-accent); margin-bottom:20px;"></i>
        <h3 class="font-oswald" style="color:var(--uecl-accent);">YETERSİZ TAKIM (<?= $uecl_takim_sayisi ?>/8)</h3>
        <p class="text-muted">En az 8 takım gerekli. Lütfen lig sezonu sonlarını bekleyin.</p>
    </div>

<?php else: ?>
    <!-- HAFTA KONTROLÜ VE NOKAUT YÖNLENDİRME -->
    <?php if ($hafta > 8): ?>
        <div class="alert" style="background:rgba(212,175,55,0.1);border:1px solid rgba(212,175,55,0.3);border-radius:12px;color:var(--gold);text-align:center;padding:20px;">
            <i class="fa-solid fa-trophy me-2"></i>
            <strong>Lig aşaması tamamlandı!</strong> Eleme turları için <a href="uecl_nokaut.php" style="color:var(--gold);font-weight:700;">buraya tıklayın</a>.
        </div>
    <?php endif; ?>

    <!-- SEZON SONU -->
    <?php
    $final_bitti = false;
    try {
        $final_mac_count = $pdo->query("SELECT COUNT(*) FROM uecl_maclar WHERE hafta=15 AND ev_skor IS NOT NULL AND sezon_yil=$sezon_yili")->fetchColumn();
        if ($final_mac_count > 0) $final_bitti = true;
    } catch(Throwable $e) {}
    if ($final_bitti):
    ?>
        <div class="alert" style="background:rgba(46,204,113,0.15);border:2px solid var(--uecl-green);border-radius:16px;color:#fff;text-align:center;padding:30px;">
            <i class="fa-solid fa-trophy" style="color:var(--gold);font-size:2rem;"></i>
            <h4 class="mt-2 font-oswald">CONFERENCE LEAGUE FİNALİ OYNANMIŞTIR!</h4>
            <a href="uecl_sezon_gecisi.php" class="btn-uecl mt-3"><i class="fa-solid fa-forward-step"></i> Sezon Sonu Ekranına Git</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <!-- HAFTA NAVİGASYONU -->
            <div class="hafta-nav">
                <div>
                    <?php if ($hafta <= 8): ?>
                        <span class="hafta-badge"><i class="fa-solid fa-calendar-week me-1"></i> LİG AŞAMASI - HAFTA <?= $hafta ?>/8</span>
                    <?php else: ?>
                        <span class="nokaut-badge"><i class="fa-solid fa-trophy me-1"></i> ELEME AŞAMASI - TUR <?= $hafta-8 ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($hafta <= 8): ?>
                    <a href="?action=hafta" class="btn-uecl-outline"><i class="fa-solid fa-play"></i> Haftayı Oyna</a>
                    <a href="?action=hafta_full" class="btn-uecl" onclick="return confirm('Kendi maçınız dahil tüm maçlar simüle edilecek.')"><i class="fa-solid fa-forward-fast"></i> Hepsini Oyna</a>
                    <?php else: ?>
                    <a href="uecl_nokaut.php" class="btn-uecl"><i class="fa-solid fa-trophy"></i> Eleme Aşaması</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MAÇ LİSTESİ -->
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-futbol"></i> Hafta <?= $hafta ?> Maçları</div>
                <?php if (empty($maclar_bu_hafta)): ?>
                    <div class="text-center text-muted py-4">Bu haftada maç bulunmuyor.</div>
                <?php else: ?>
                    <?php foreach ($maclar_bu_hafta as $m): ?>
                    <div class="mac-kart">
                        <div class="takim-col">
                            <img src="<?= htmlspecialchars($m['ev_logo'] ?? '') ?>" alt="" class="takim-logo" onerror="this.style.display='none'">
                            <span class="takim-adi <?= ($kullanici_takim == $m['ev']) ? 'text-warning' : '' ?>"><?= htmlspecialchars($m['ev_ad']) ?></span>
                        </div>
                        <div class="skor-col">
                            <?php if ($m['ev_skor'] !== null): ?>
                                <div class="skor-badge"><span class="skor-text"><?= $m['ev_skor'] ?> - <?= $m['dep_skor'] ?></span></div>
                            <?php else: ?>
                                <?php if ($kullanici_takim && ($m['ev'] == $kullanici_takim || $m['dep'] == $kullanici_takim)): ?>
                                    <a href="../canli_mac.php?mac_id=<?= $m['id'] ?>&lig=uel" class="btn-uecl" style="font-size:0.75rem;padding:6px 14px;"><i class="fa-solid fa-eye"></i> Canlı İzle</a>
                                <?php else: ?>
                                    <a href="?action=tek_mac_simule&mac_id=<?= $m['id'] ?>&hafta=<?= $hafta ?>" class="btn-uecl-outline" style="font-size:0.75rem;"><i class="fa-solid fa-bolt"></i> Simüle</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="takim-col dep">
                            <span class="takim-adi <?= ($kullanici_takim == $m['dep']) ? 'text-warning' : '' ?>"><?= htmlspecialchars($m['dep_ad']) ?></span>
                            <img src="<?= htmlspecialchars($m['dep_logo'] ?? '') ?>" alt="" class="takim-logo" onerror="this.style.display='none'">
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- KULLANICI TAKIMI -->
            <?php if ($kullanici_takim_bilgi): ?>
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-shield-halved"></i> Takımım</div>
                <div class="d-flex align-items-center gap-3">
                    <img src="<?= htmlspecialchars($kullanici_takim_bilgi['logo'] ?? '') ?>" style="width:60px;height:60px;object-fit:contain;" onerror="this.style.display='none'">
                    <div>
                        <div class="fw-bold fs-5"><?= htmlspecialchars($kullanici_takim_bilgi['takim_adi']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($kullanici_takim_bilgi['lig'] ?? 'Avrupa') ?></div>
                        <div class="mt-1">
                            <span class="badge" style="background:rgba(46,204,113,0.2);color:var(--uecl-accent);">
                                <?= $kullanici_takim_bilgi['puan'] ?> puan
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- TAKIM SEÇ -->
            <?php if (!$kullanici_takim): ?>
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-user-tie"></i> Takım Seç</div>
                <div style="max-height:300px;overflow-y:auto;">
                    <?php
                    $tum_takimlar = $pdo->query("SELECT * FROM uecl_takimlar ORDER BY takim_adi")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($tum_takimlar as $t):
                    ?>
                    <a href="?action=takim_sec&tid=<?= $t['id'] ?>" style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:8px;text-decoration:none;color:#fff;transition:0.2s;" onmouseover="this.style.background='rgba(46,204,113,0.1)'" onmouseout="this.style.background='transparent'">
                        <img src="<?= htmlspecialchars($t['logo'] ?? '') ?>" style="width:28px;height:28px;object-fit:contain;" onerror="this.style.display='none'">
                        <span class="fw-semibold" style="font-size:0.9rem;"><?= htmlspecialchars($t['takim_adi']) ?></span>
                        <span class="text-muted ms-auto" style="font-size:0.75rem;"><?= htmlspecialchars($t['lig'] ?? '') ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- MINI PUAN DURUMU (TOP 8) -->
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-ranking-star"></i> Puan Durumu (Top 8)</div>
                <table class="puan-tbl">
                    <thead><tr>
                        <th>#</th><th>Takım</th><th>O</th><th>P</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach (array_slice($puan_durumu, 0, 8) as $i => $t): ?>
                        <tr class="<?= ($kullanici_takim == $t['id']) ? 'kullanici-satir' : '' ?>">
                            <td>
                                <span class="sira-badge <?= $i < 4 ? 'sira-ucl' : 'sira-normal' ?>"><?= $i+1 ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= htmlspecialchars($t['logo'] ?? '') ?>" style="width:22px;height:22px;object-fit:contain;" onerror="this.style.display='none'">
                                    <span style="font-size:0.85rem;"><?= htmlspecialchars($t['takim_adi']) ?></span>
                                </div>
                            </td>
                            <td style="color:#888;font-size:0.85rem;"><?= $t['galibiyet']+$t['beraberlik']+$t['malubiyet'] ?></td>
                            <td><strong style="color:var(--uecl-accent);"><?= $t['puan'] ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="mt-3">
                    <a href="uecl_puan.php" class="btn-uecl-outline w-100 justify-content-center"><i class="fa-solid fa-table"></i> Tam Puan Durumu</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
