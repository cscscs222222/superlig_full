<?php
// ==============================================================================
// EUROPA LEAGUE - NOKAUT AŞAMASI V5.0 (İLK 8 DİREKT SON 16 MANTIĞI)
// ==============================================================================
include '../db.php';
require_once '../MatchEngine.php';
$engine = new MatchEngine($pdo, 'uel_');
$ayar = $pdo->query("SELECT * FROM uel_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = (int)$ayar['hafta'];
$sezon_yili = (int)($ayar['sezon_yil'] ?? 2025);
$kullanici_takim_id = (int)($ayar['kullanici_takim_id'] ?? 0);

if ($hafta <= 8) { header("Location: uel.php"); exit; }

function mac_var_mi($pdo, $h, $sezon_yil) {
    $h = (int)$h; $sezon_yil = (int)$sezon_yil;
    return $pdo->query("SELECT COUNT(*) FROM uel_maclar WHERE hafta = $h AND sezon_yil = $sezon_yil")->fetchColumn() > 0;
}

function get_kazananlar($pdo, $h1, $h2, $sezon_yil) {
    $kazananlar = [];
    $h1 = (int)$h1; $h2 = (int)$h2; $sezon_yil = (int)$sezon_yil;
    $maclar1 = $pdo->query("SELECT * FROM uel_maclar WHERE hafta=$h1 AND sezon_yil=$sezon_yil")->fetchAll(PDO::FETCH_ASSOC);
    foreach($maclar1 as $m1) {
        $ev = (int)$m1['ev']; $dep = (int)$m1['dep'];
        $m2 = $pdo->query("SELECT * FROM uel_maclar WHERE hafta=$h2 AND sezon_yil=$sezon_yil AND ev=$dep AND dep=$ev")->fetch(PDO::FETCH_ASSOC);
        if($m2 && $m1['ev_skor'] !== null && $m2['ev_skor'] !== null) {
            $t1_toplam = $m1['ev_skor'] + $m2['dep_skor'];
            $t2_toplam = $m1['dep_skor'] + $m2['ev_skor'];
            if($t1_toplam > $t2_toplam) $kazananlar[] = $ev;
            elseif($t2_toplam > $t1_toplam) $kazananlar[] = $dep;
            else $kazananlar[] = (rand(0,1) == 0) ? $ev : $dep; 
        }
    }
    return $kazananlar;
}

// --- OTOMATİK EŞLEŞME MOTORU (SWISS SİSTEMİ) ---
// Hafta 9-10: PLAY-OFF – SADECE 9-24. SIRALAR ARASI (ilk 8 direkt Son 16, 25-36 elenir)
if ($hafta == 9 && !mac_var_mi($pdo, 9, $sezon_yili)) {
    // Sıralama 9-24: 9-16 seri başı (seeded), 17-24 seri başı olmayan (unseeded)
    $takimlar = array_map('intval', $pdo->query("SELECT id FROM uel_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC LIMIT 8, 16")->fetchAll(PDO::FETCH_COLUMN));
    $n = count($takimlar);
    if($n == 16) {
        $yari = $n / 2;
        for($i=0; $i<$yari; $i++) {
            $seeded   = $takimlar[$i];       // Sıra 9-16 (seri başı, daha yüksek sıralı)
            $unseeded = $takimlar[$n-1-$i];  // Sıra 17-24 (seri başı olmayan, daha düşük sıralı)
            // 1. Maç (Hafta 9): Düşük sıralı (unseeded) takımın evinde
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($unseeded, $seeded, 9, $sezon_yili)");
            // Rövanş (Hafta 10): Yüksek sıralı (seeded) takımın evinde
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($seeded, $unseeded, 10, $sezon_yili)");
        }
    }
}
if ($hafta == 11 && !mac_var_mi($pdo, 11, $sezon_yili)) {
    // Hafta 11-12: SON 16 – İlk 8 takım sahneye çıkar, play-off kazananlarıyla eşleşir
    $ilk8 = array_map('intval', $pdo->query("SELECT id FROM uel_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC LIMIT 8")->fetchAll(PDO::FETCH_COLUMN));
    $playoff = get_kazananlar($pdo, 9, 10, $sezon_yili);
    if(count($playoff) == 8 && count($ilk8) == 8) {
        for($i=0; $i<8; $i++) {
            $ev = (int)$ilk8[$i]; $dep = (int)$playoff[$i];
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, 11, $sezon_yili)");
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, 12, $sezon_yili)");
        }
    }
}
// Hafta 13-14: ÇEYREK FİNAL
if ($hafta == 13 && !mac_var_mi($pdo, 13, $sezon_yili)) {
    $c = get_kazananlar($pdo, 11, 12, $sezon_yili); shuffle($c);
    if(count($c) == 8) {
        for($i=0; $i<4; $i++) {
            $ev = (int)$c[$i*2]; $dep = (int)$c[$i*2+1];
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, 13, $sezon_yili)");
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, 14, $sezon_yili)");
        }
    }
}
// Hafta 15-16: YARI FİNAL
if ($hafta == 15 && !mac_var_mi($pdo, 15, $sezon_yili)) {
    $y = get_kazananlar($pdo, 13, 14, $sezon_yili); shuffle($y);
    if(count($y) == 4) {
        for($i=0; $i<2; $i++) {
            $ev = (int)$y[$i*2]; $dep = (int)$y[$i*2+1];
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, 15, $sezon_yili)");
            $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($dep, $ev, 16, $sezon_yili)");
        }
    }
}
// Hafta 17: FİNAL (tek maç)
if ($hafta == 17 && !mac_var_mi($pdo, 17, $sezon_yili)) {
    $f = get_kazananlar($pdo, 15, 16, $sezon_yili);
    if(count($f) == 2) {
        $ev = (int)$f[0]; $dep = (int)$f[1];
        $pdo->exec("INSERT INTO uel_maclar (ev, dep, hafta, sezon_yil) VALUES ($ev, $dep, 17, $sezon_yili)");
    }
}

// SİMÜLASYON İŞLEMİ
if(isset($_GET['simule'])) {
    $maclar = $pdo->query("SELECT * FROM uel_maclar WHERE hafta = $hafta AND sezon_yil = $sezon_yili AND ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach($maclar as $m) {
        if($kullanici_takim_id && ($m['ev'] == $kullanici_takim_id || $m['dep'] == $kullanici_takim_id) && !isset($_GET['full'])) continue;
        
        $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep']);
        $ev_d = $engine->mac_olay_uret($m['ev'], $skorlar['ev']);
        $dep_d = $engine->mac_olay_uret($m['dep'], $skorlar['dep']);
        $stmt = $pdo->prepare("UPDATE uel_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
        $stmt->execute([$skorlar['ev'], $skorlar['dep'], $ev_d['olaylar'], $dep_d['olaylar'], $ev_d['kartlar'], $dep_d['kartlar'], $m['id']]);
    }
    
    $kalan = $pdo->query("SELECT COUNT(*) FROM uel_maclar WHERE hafta = $hafta AND sezon_yil = $sezon_yili AND ev_skor IS NULL")->fetchColumn();
    if($kalan == 0) { $pdo->exec("UPDATE uel_ayar SET hafta = hafta + 1"); }
    
    header("Location: uel_nokaut.php?asama=".($_GET['asama'] ?? 'po')); exit;
}

// GÖRÜNÜM (SEKMELER) YÖNETİMİ
$asama_map = [
    'po' => ['h'=>[9,10], 'ad'=>'PLAY-OFF (9-24. Sıralar)'],
    's16'=> ['h'=>[11,12], 'ad'=>'SON 16 TURU'],
    'cf' => ['h'=>[13,14], 'ad'=>'ÇEYREK FİNAL'],
    'yf' => ['h'=>[15,16], 'ad'=>'YARI FİNAL'],
    'f'  => ['h'=>[17], 'ad'=>'BÜYÜK FİNAL']
];

if(!isset($_GET['asama'])) {
    if($hafta <= 10) $asama = 'po';
    elseif($hafta <= 12) $asama = 's16';
    elseif($hafta <= 14) $asama = 'cf';
    elseif($hafta <= 16) $asama = 'yf';
    else $asama = 'f';
} else {
    $asama = isset($_GET['asama']) && array_key_exists($_GET['asama'], $asama_map) ? $_GET['asama'] : 'po';
}

$hedef_h = implode(',', array_map('intval', $asama_map[$asama]['h']));
$maclar_ui = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM uel_maclar m JOIN uel_takimlar t1 ON m.ev = t1.id JOIN uel_takimlar t2 ON m.dep = t2.id WHERE m.hafta IN ($hedef_h) AND m.sezon_yil = $sezon_yili ORDER BY m.hafta ASC")->fetchAll(PDO::FETCH_ASSOC);

$benim_macim_var_mi = $pdo->query("SELECT COUNT(*) FROM uel_maclar WHERE hafta=$hafta AND sezon_yil=$sezon_yili AND ev_skor IS NULL AND (ev=$kullanici_takim_id OR dep=$kullanici_takim_id)")->fetchColumn();

// Hafta numarasından okunabilir tur adı
$hafta_tur_adi = [
    9  => 'PO 1. MAÇI',
    10 => 'PO RÖVANŞI',
    11 => 'S16 1. MAÇI',
    12 => 'S16 RÖVANŞI',
    13 => 'ÇF 1. MAÇI',
    14 => 'ÇF RÖVANŞI',
    15 => 'YF 1. MAÇI',
    16 => 'YF RÖVANŞI',
    17 => 'FİNAL',
];

// Final sonrası şampiyon ve finalist (Hafta 17 maçından)
$final_sampiyon = null;
$final_finalist = null;
$final_skor_goster = null;
if ($hafta > 17) {
    $final_mac = $pdo->query(
        "SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t1.id as ev_id,
                t2.takim_adi as dep_ad, t2.logo as dep_logo, t2.id as dep_id
         FROM uel_maclar m
         JOIN uel_takimlar t1 ON m.ev = t1.id
         JOIN uel_takimlar t2 ON m.dep = t2.id
         WHERE m.hafta = 17 AND m.ev_skor IS NOT NULL AND m.sezon_yil = $sezon_yili
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if ($final_mac) {
        if ((int)$final_mac['ev_skor'] > (int)$final_mac['dep_skor']) {
            $s_id = (int)$final_mac['ev_id']; $f_id = (int)$final_mac['dep_id'];
        } elseif ((int)$final_mac['dep_skor'] > (int)$final_mac['ev_skor']) {
            $s_id = (int)$final_mac['dep_id']; $f_id = (int)$final_mac['ev_id'];
        } else {
            // Berabere biten final: ev sahibi kazanır (simülasyon limiti)
            $s_id = (int)$final_mac['ev_id']; $f_id = (int)$final_mac['dep_id'];
        }
        $stmt = $pdo->prepare("SELECT * FROM uel_takimlar WHERE id = ?");
        $stmt->execute([$s_id]);
        $final_sampiyon = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->execute([$f_id]);
        $final_finalist = $stmt->fetch(PDO::FETCH_ASSOC);
        $final_skor_goster = $final_mac['ev_skor'] . ' - ' . $final_mac['dep_skor'];
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>UEL Nokaut Aşaması</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #020617; color: #fff; font-family: 'Inter', sans-serif; background-image: radial-gradient(circle at 50% 0%, rgba(0, 229, 255, 0.1) 0%, transparent 60%); min-height: 100vh;}
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .hero-banner { padding: 2rem; text-align: center; border-bottom: 1px solid rgba(0,229,255,0.2); margin-bottom: 30px;}
        
        .nav-tabs-custom { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { background: rgba(15,23,42,0.8); border: 1px solid rgba(0,229,255,0.2); color: #94a3b8; padding: 12px 30px; border-radius: 8px; font-family: 'Oswald'; font-size: 1.1rem; text-decoration: none; transition: 0.3s; letter-spacing: 1px;}
        .tab-btn:hover { background: rgba(0,229,255,0.1); color: #fff; border-color: #f04e23; }
        .tab-btn.active { background: #f04e23; color: #000; font-weight: 800; border-color: #f04e23; box-shadow: 0 0 20px rgba(0,229,255,0.4); transform: translateY(-2px);}

        .match-card { background: rgba(0,0,0,0.6); border: 1px solid rgba(0,229,255,0.15); border-radius: 12px; margin-bottom: 20px; overflow: hidden; transition: 0.3s;}
        .match-card:hover { border-color: #f04e23; box-shadow: 0 5px 20px rgba(0,229,255,0.15); transform: translateY(-2px);}
        
        .score-grid { display: flex; width: 100%; min-height: 100px; align-items: stretch; padding: 15px;}
        
        .team-block { display: flex; align-items: center; gap: 15px; flex: 1; }
        .team-block.home { justify-content: flex-end; text-align: right; }
        .team-block.away { justify-content: flex-start; text-align: left; }
        .team-name { font-weight: 800; font-size: 1.3rem; color: #f8fafc; }
        .team-logo { width: 55px; height: 55px; object-fit: contain; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.8)); }
        
        .vs-box { width: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(180deg, #1a0a02, #2d0e00); border-radius: 8px; border: 1px solid rgba(0,229,255,0.3); box-shadow: inset 0 0 10px rgba(0,0,0,0.8); margin: 0 20px;}
        .match-score { font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; color: #ffffff; line-height: 1; text-shadow: 0 3px 5px rgba(0,0,0,0.9); }
        .match-status { font-size: 0.8rem; color: #f04e23; font-weight: 800; letter-spacing: 2px; margin-top: 5px; }

        .events-grid { display: flex; width: 100%; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(0,229,255,0.1); padding: 15px 0; font-size: 0.9rem; }
        .event-col { display: flex; flex-direction: column; gap: 8px; padding: 0 20px; flex: 1;}
        .event-col.home { align-items: flex-end; text-align: right; } 
        .event-col.away { align-items: flex-start; text-align: left; }
        .event-col.center { width: 120px; flex: none; }
        
        .event-item { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #fff;}
        .event-time { font-family: 'Oswald'; font-weight: 700; color: #f04e23;}
        .event-assist { color: #94a3b8; font-size: 0.8rem; font-style: italic;}
        
        .ref-card { width: 12px; height: 16px; border-radius: 2px; transform: rotate(5deg); box-shadow: 0 1px 4px rgba(0,0,0,0.8);}
        .ref-card.yellow { background-color: #fbbf24; }
        .ref-card.red { background-color: #ef4444; }
    </style>
</head>
<body>

    <!-- TOP NAV -->
    <nav style="background: rgba(5,11,20,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(0,229,255,0.2); padding: 0 2rem; height: 65px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000;">
        <a href="uel.php" style="color:#f04e23; font-family:'Oswald',sans-serif; font-size:1.3rem; text-decoration:none; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-futbol"></i> EUROPA LEAGUE
        </a>
        <div style="display:flex; gap:4px; flex-wrap:wrap; align-items:center;">
            <a href="uel.php" class="tab-btn" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-house me-1"></i>Ana Sayfa</a>
            <a href="uel.php" class="tab-btn" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-list-ol me-1"></i>Lig Aşaması</a>
            <a href="uel_nokaut.php?asama=po" class="tab-btn <?= $asama=='po' ? 'active' : '' ?>" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-shield-halved me-1"></i>Playoff</a>
            <a href="uel_nokaut.php" class="tab-btn active" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-bolt me-1"></i>Eleme Turları</a>
            <a href="uel.php" class="tab-btn" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-table me-1"></i>Puan Tablosu</a>
            <a href="uel.php" class="tab-btn" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-calendar-days me-1"></i>Fikstür / Maçlar</a>
            <a href="uel_puan.php" class="tab-btn" style="padding:8px 14px; font-size:0.85rem;"><i class="fa-solid fa-chart-bar me-1"></i>İstatistikler</a>
        </div>
    </nav>

    <!-- HERO -->
    <div class="hero-banner">
        <div style="display:inline-block; background: rgba(0,229,255,0.1); border: 1px solid rgba(0,229,255,0.3); border-radius: 50px; padding: 6px 20px; margin-bottom: 16px;">
            <span style="color:#f04e23; font-size:0.85rem; font-weight:700; letter-spacing:3px;">UEFA EUROPA LEAGUE</span>
        </div>
        <h1 class="font-oswald" style="color: #ffffff; font-size: 3rem; text-shadow: 0 0 30px rgba(0,229,255,0.4); margin-bottom: 6px;">ROAD TO GLORY</h1>
        <h3 style="color:#f04e23; font-family:'Oswald',sans-serif; font-size:1.4rem; letter-spacing:2px;"><?= $asama_map[$asama]['ad'] ?></h3>
        
        <!-- TOURNAMENT PROGRESS BAR -->
        <div style="display:flex; justify-content:center; gap:0; margin: 20px auto; max-width: 700px; background: rgba(255,255,255,0.03); border-radius: 8px; overflow:hidden; border: 1px solid rgba(0,229,255,0.1);">
            <?php
            $stages = ['po'=>'PLAY-OFF','s16'=>'SON 16','cf'=>'ÇEYREK','yf'=>'YARI FİNAL','f'=>'FİNAL'];
            $stage_order = ['po','s16','cf','yf','f'];
            $current_idx = array_search($asama, $stage_order);
            foreach($stages as $k => $label):
                $idx = array_search($k, $stage_order);
                $is_active = ($k == $asama);
                $is_done = ($idx < $current_idx);
                $bg = $is_active ? 'rgba(0,229,255,0.25)' : ($is_done ? 'rgba(0,229,255,0.08)' : 'transparent');
                $color = $is_active ? '#f04e23' : ($is_done ? '#4ade80' : '#475569');
                $border = $is_active ? 'border-bottom: 3px solid #f04e23;' : ($is_done ? 'border-bottom: 3px solid #4ade80;' : 'border-bottom: 3px solid transparent;');
            ?>
            <div style="flex:1; padding:10px 5px; text-align:center; background:<?=$bg?>; <?=$border?> transition:0.3s;">
                <div style="color:<?=$color?>; font-family:'Oswald',sans-serif; font-size:0.75rem; letter-spacing:1px;"><?=$label?></div>
                <?php if($is_done): ?><div style="color:#4ade80; font-size:0.7rem;">✓ Bitti</div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if(($hafta == 9 || $hafta == 10) && $benim_macim_var_mi == 0): ?>
            <div style="max-width: 550px; margin: 10px auto; background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.4); border-radius: 10px; padding: 14px 20px; display:flex; align-items:center; gap:12px;">
                <i class="fa-solid fa-star text-warning fs-4"></i>
                <div style="text-align:left;">
                    <strong style="color:#fbbf24;">Tebrikler! İlk 8'e Girdiniz!</strong>
                    <div style="color:#94a3b8; font-size:0.85rem; margin-top:2px;">Takımınız Play-Off turunu atlıyor. Direkt Son 16'ya katılıyorsunuz.</div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-3 d-flex justify-content-center gap-3 flex-wrap">
            <?php if($hafta > 17): ?>
                <a href="uel_sezon_gecisi.php" class="btn btn-warning fw-bold font-oswald text-dark px-4 py-3" style="font-size: 1.1rem; box-shadow: 0 0 20px rgba(255,193,7,0.5); border-radius:8px;">
                    🏆 KUPA TÖRENİ &amp; SEZONU BİTİR
                </a>
            <?php else: ?>
                <a href="?asama=<?= $asama ?>&simule=1&full=1" 
                   style="background: linear-gradient(90deg,#1d4ed8,#f04e23); color:#000; font-family:'Oswald',sans-serif; font-weight:800; padding:12px 30px; border-radius:8px; text-decoration:none; font-size:1.1rem; letter-spacing:1px; box-shadow:0 0 20px rgba(0,229,255,0.4);">
                    <i class="fa-solid fa-forward-fast me-2"></i> AKTİF TURU SİMÜLE ET
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if($final_sampiyon): ?>
    <!-- ŞAMPİYON BLOKU - FİNAL SONRASI -->
    <div style="max-width:860px; margin: 0 auto 30px; padding: 0 1rem;">
        <div style="background: linear-gradient(135deg, rgba(212,175,55,0.15), rgba(0,229,255,0.08)); border: 2px solid #d4af37; border-radius: 18px; padding: 36px 30px; text-align:center; box-shadow: 0 0 40px rgba(212,175,55,0.25);">
            <div style="font-family:'Oswald',sans-serif; font-size:0.9rem; color:#94a3b8; letter-spacing:3px; margin-bottom:12px;">UEFA AVRUPA LİGİ FİNALİ</div>
            <div style="display:flex; justify-content:center; align-items:center; gap:30px; flex-wrap:wrap;">
                <div style="text-align:center;">
                    <img src="<?= htmlspecialchars($final_sampiyon['logo']) ?>" style="width:90px; height:90px; object-fit:contain; filter:drop-shadow(0 0 14px #d4af37);">
                    <div style="font-family:'Oswald',sans-serif; font-size:1.5rem; color:#d4af37; font-weight:900; margin-top:8px;">🏆 Şampiyon</div>
                    <div style="font-size:1.2rem; color:#fff; font-weight:700;"><?= htmlspecialchars($final_sampiyon['takim_adi']) ?></div>
                </div>
                <?php if($final_skor_goster): ?>
                <div style="text-align:center; background:rgba(0,0,0,0.5); border:1px solid rgba(0,229,255,0.3); border-radius:12px; padding:14px 24px;">
                    <div style="color:#94a3b8; font-size:0.75rem; letter-spacing:2px; margin-bottom:4px;">FİNAL SKORU</div>
                    <div style="font-family:'Oswald',sans-serif; font-size:2.4rem; color:#fff; font-weight:900; line-height:1;"><?= htmlspecialchars($final_skor_goster) ?></div>
                </div>
                <?php endif; ?>
                <div style="text-align:center;">
                    <img src="<?= htmlspecialchars($final_finalist['logo']) ?>" style="width:70px; height:70px; object-fit:contain; opacity:0.85; filter:drop-shadow(0 0 8px rgba(148,163,184,0.5));">
                    <div style="font-family:'Oswald',sans-serif; font-size:1.2rem; color:#cbd5e1; font-weight:700; margin-top:8px;">🥈 Finalist</div>
                    <div style="font-size:1rem; color:#94a3b8;"><?= htmlspecialchars($final_finalist['takim_adi']) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container pb-5" style="max-width: 1050px;">
        
        <!-- PHASE TABS -->
        <div class="nav-tabs-custom">
            <?php 
            $tab_icons = ['po'=>'fa-shield-halved','s16'=>'fa-trophy','cf'=>'fa-star','yf'=>'fa-fire','f'=>'fa-crown'];
            foreach($asama_map as $key => $data): 
                $is_locked = false;
                if($key=='s16' && $hafta < 11) $is_locked = true;
                if($key=='cf' && $hafta < 13) $is_locked = true;
                if($key=='yf' && $hafta < 15) $is_locked = true;
                if($key=='f' && $hafta < 17) $is_locked = true;
            ?>
                <a href="<?= $is_locked ? '#' : '?asama='.$key ?>" 
                   class="tab-btn <?= $asama == $key ? 'active' : '' ?>"
                   style="<?= $is_locked ? 'opacity:0.45; cursor:not-allowed;' : '' ?>">
                    <i class="fa-solid <?= $tab_icons[$key] ?> me-1"></i>
                    <?= $data['ad'] ?>
                    <?php if($is_locked): ?><i class="fa-solid fa-lock ms-1" style="font-size:0.7rem;"></i><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if(empty($maclar_ui)): ?>
            <div class="text-center p-5" style="background: rgba(15,23,42,0.8); border: 1px dashed rgba(0,229,255,0.3); border-radius: 15px;">
                <i class="fa-solid fa-lock text-muted mb-3" style="font-size: 3.5rem;"></i>
                <h4 class="font-oswald text-muted mt-3">EŞLEŞMELER HENÜZ BELLİ OLMADI</h4>
                <p class="text-muted">Bu aşamanın maçları, bir önceki tur tamamlandıktan sonra otomatik olarak belirlenir.</p>
                <a href="?asama=po" class="btn btn-outline-info mt-2">← Aktif Aşamaya Dön</a>
            </div>
        <?php else: ?>
            
            <?php 
            // Group matches by round pair (e.g., leg 1 & leg 2)
            $tur_haftalari = $asama_map[$asama]['h'];
            if(count($tur_haftalari) == 2):
                $tur1 = array_filter($maclar_ui, fn($m) => $m['hafta'] == $tur_haftalari[0]);
                $tur2 = array_filter($maclar_ui, fn($m) => $m['hafta'] == $tur_haftalari[1]);
                if(!empty($tur1)):
            ?>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:10px;">
                <h5 class="font-oswald text-center" style="color:#f04e23; font-size:1rem; border-bottom:1px solid rgba(0,229,255,0.2); padding-bottom:8px;">İLK BACAK</h5>
                <h5 class="font-oswald text-center" style="color:#f04e23; font-size:1rem; border-bottom:1px solid rgba(0,229,255,0.2); padding-bottom:8px;">RÖVANŞ</h5>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <?php foreach($maclar_ui as $mac): ?>
                <div class="match-card">
                    <div class="score-grid">
                        <div class="team-block home">
                            <span class="team-name"><?= htmlspecialchars($mac['ev_ad']) ?></span>
                            <img src="<?= $mac['ev_logo'] ?>" class="team-logo">
                        </div>
                        <div class="vs-box">
                            <?php if($mac['ev_skor'] !== null): ?>
                                <span class="match-score"><?= $mac['ev_skor'] ?> - <?= $mac['dep_skor'] ?></span>
                                <span class="match-status" style="color:#4ade80; font-size:0.7rem;">MAÇ BİTTİ</span>
                            <?php else: ?>
                                <span class="match-score" style="font-size:1.4rem; color:#475569;">vs</span>
                                <span class="match-status"><?= $hafta_tur_adi[$mac['hafta']] ?? ('H'.$mac['hafta']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="team-block away">
                            <img src="<?= $mac['dep_logo'] ?>" class="team-logo">
                            <span class="team-name"><?= htmlspecialchars($mac['dep_ad']) ?></span>
                        </div>
                    </div>
                    
                    <?php if($mac['ev_skor'] === null): ?>
                        <div class="text-center p-2 border-top" style="border-color:rgba(0,229,255,0.1); background:rgba(0,0,0,0.3);">
                            <a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=cl&hafta=<?= $mac['hafta'] ?>" class="btn btn-outline-info px-4 fw-bold" style="font-size:0.9rem;">
                                <i class="fa-solid fa-satellite-dish me-1"></i> CANLI İZLE
                            </a>
                        </div>
                    <?php else: ?>
                        <?php 
                        $ev_olaylar = json_decode($mac['ev_olaylar'], true) ?: [];
                        $dep_olaylar = json_decode($mac['dep_olaylar'], true) ?: [];
                        $ev_kartlar = json_decode($mac['ev_kartlar'], true) ?: [];
                        $dep_kartlar = json_decode($mac['dep_kartlar'], true) ?: [];
                        
                        if(count($ev_olaylar)>0 || count($dep_olaylar)>0 || count($ev_kartlar)>0 || count($dep_kartlar)>0): 
                        ?>
                        <div class="events-grid">
                            <div class="event-col home">
                                <?php 
                                foreach($ev_olaylar as $o) {
                                    if(strtolower($o['tip'] ?? 'gol') != 'gol') continue; 
                                    $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                    echo "<div class='event-item justify-content-end'>$asist ".htmlspecialchars($o['oyuncu'])." <span class='event-time'>{$o['dakika']}'</span> <i class='fa-solid fa-futbol text-success'></i></div>"; 
                                }
                                foreach($ev_kartlar as $k) { 
                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı'); $renk = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                    echo "<div class='event-item justify-content-end'>".htmlspecialchars($k['oyuncu'])." <span class='event-time'>{$k['dakika']}'</span> <div class='ref-card $renk'></div></div>"; 
                                }
                                ?>
                            </div>
                            <div class="event-col center"></div>
                            <div class="event-col away">
                                <?php 
                                foreach($dep_olaylar as $o) {
                                    if(strtolower($o['tip'] ?? 'gol') != 'gol') continue; 
                                    $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                    echo "<div class='event-item'><i class='fa-solid fa-futbol text-success'></i> <span class='event-time'>{$o['dakika']}'</span> ".htmlspecialchars($o['oyuncu'])." $asist</div>"; 
                                }
                                foreach($dep_kartlar as $k) { 
                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı'); $renk = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                    echo "<div class='event-item'><div class='ref-card $renk'></div> <span class='event-time'>{$k['dakika']}'</span> ".htmlspecialchars($k['oyuncu'])."</div>"; 
                                }
                                ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>