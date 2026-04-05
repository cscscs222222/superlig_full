<?php
// ==============================================================================
// CHAMPIONS LEAGUE - NOKAUT AŞAMASI V5.0 (İLK 8 DİREKT SON 16 MANTIĞI)
// ==============================================================================
include '../db.php';
require_once '../MatchEngine.php';
$engine = new MatchEngine($pdo, 'cl_');
$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = $ayar['hafta'];
$kullanici_takim_id = $ayar['kullanici_takim_id'];

if ($hafta <= 8) { header("Location: cl.php"); exit; }

function mac_var_mi($pdo, $h) { return $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta = $h")->fetchColumn() > 0; }

function get_kazananlar($pdo, $h1, $h2) {
    $kazananlar = [];
    $maclar1 = $pdo->query("SELECT * FROM cl_maclar WHERE hafta=$h1")->fetchAll(PDO::FETCH_ASSOC);
    foreach($maclar1 as $m1) {
        $m2 = $pdo->query("SELECT * FROM cl_maclar WHERE hafta=$h2 AND ev={$m1['dep']} AND dep={$m1['ev']}")->fetch(PDO::FETCH_ASSOC);
        if($m2 && $m1['ev_skor'] !== null && $m2['ev_skor'] !== null) {
            $t1_toplam = $m1['ev_skor'] + $m2['dep_skor'];
            $t2_toplam = $m1['dep_skor'] + $m2['ev_skor'];
            if($t1_toplam > $t2_toplam) $kazananlar[] = $m1['ev'];
            elseif($t2_toplam > $t1_toplam) $kazananlar[] = $m1['dep'];
            else $kazananlar[] = (rand(0,1) == 0) ? $m1['ev'] : $m1['dep']; 
        }
    }
    return $kazananlar;
}

// --- OTOMATİK EŞLEŞME MOTORU (SWISS SİSTEMİ) ---
if ($hafta == 9 && !mac_var_mi($pdo, 9)) {
    // SADECE 9 VE 24. SIRALAR ARASI PLAY-OFF OYNAR! (İlk 8 dinleniyor)
    $takimlar = $pdo->query("SELECT id FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC LIMIT 8, 16")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar) == 16) {
        for($i=0; $i<8; $i++) {
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$takimlar[$i]}, {$takimlar[15-$i]}, 9)");
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$takimlar[15-$i]}, {$takimlar[$i]}, 10)");
        }
    }
}
if ($hafta == 11 && !mac_var_mi($pdo, 11)) {
    // İLK 8 TAKIM SAHNEYE ÇIKAR! Play-Off kazananlarıyla eşleşirler.
    $ilk8 = $pdo->query("SELECT id FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC LIMIT 8")->fetchAll(PDO::FETCH_COLUMN);
    $playoff = get_kazananlar($pdo, 9, 10);
    if(count($playoff) == 8 && count($ilk8) == 8) {
        for($i=0; $i<8; $i++) {
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$ilk8[$i]}, {$playoff[$i]}, 11)");
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$playoff[$i]}, {$ilk8[$i]}, 12)");
        }
    }
}
if ($hafta == 13 && !mac_var_mi($pdo, 13)) {
    $c = get_kazananlar($pdo, 11, 12); shuffle($c);
    if(count($c) == 8) {
        for($i=0; $i<4; $i++) {
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$c[$i*2]}, {$c[$i*2+1]}, 13)");
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$c[$i*2+1]}, {$c[$i*2]}, 14)");
        }
    }
}
if ($hafta == 15 && !mac_var_mi($pdo, 15)) {
    $y = get_kazananlar($pdo, 13, 14); shuffle($y);
    if(count($y) == 4) {
        for($i=0; $i<2; $i++) {
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$y[$i*2]}, {$y[$i*2+1]}, 15)");
            $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$y[$i*2+1]}, {$y[$i*2]}, 16)");
        }
    }
}
if ($hafta == 17 && !mac_var_mi($pdo, 17)) {
    $f = get_kazananlar($pdo, 15, 16);
    if(count($f) == 2) {
        $pdo->exec("INSERT INTO cl_maclar (ev, dep, hafta) VALUES ({$f[0]}, {$f[1]}, 17)");
    }
}

// SİMÜLASYON İŞLEMİ
if(isset($_GET['simule'])) {
    $maclar = $pdo->query("SELECT * FROM cl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach($maclar as $m) {
        if($kullanici_takim_id && ($m['ev'] == $kullanici_takim_id || $m['dep'] == $kullanici_takim_id) && !isset($_GET['full'])) continue; // full eklendiyse kendi maçını da simüle eder
        
        $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep']);
        $ev_d = $engine->mac_olay_uret($m['ev'], $skorlar['ev']);
        $dep_d = $engine->mac_olay_uret($m['dep'], $skorlar['dep']);
        $stmt = $pdo->prepare("UPDATE cl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
        $stmt->execute([$skorlar['ev'], $skorlar['dep'], $ev_d['olaylar'], $dep_d['olaylar'], $ev_d['kartlar'], $dep_d['kartlar'], $m['id']]);
    }
    
    $kalan = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
    if($kalan == 0) { $pdo->exec("UPDATE cl_ayar SET hafta = hafta + 1"); }
    
    header("Location: cl_nokaut.php?asama=".($_GET['asama'] ?? 'po')); exit;
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
    $asama = $_GET['asama'];
}

$hedef_h = implode(',', $asama_map[$asama]['h']);
$maclar_ui = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM cl_maclar m JOIN cl_takimlar t1 ON m.ev = t1.id JOIN cl_takimlar t2 ON m.dep = t2.id WHERE m.hafta IN ($hedef_h) ORDER BY m.hafta ASC")->fetchAll(PDO::FETCH_ASSOC);

$benim_macim_var_mi = $pdo->query("SELECT COUNT(*) FROM cl_maclar WHERE hafta=$hafta AND ev_skor IS NULL AND (ev=$kullanici_takim_id OR dep=$kullanici_takim_id)")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>CL Nokaut Aşaması</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Oswald:wght@700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #020617; color: #fff; font-family: 'Inter', sans-serif; background-image: radial-gradient(circle at 50% 0%, rgba(0, 229, 255, 0.1) 0%, transparent 60%); min-height: 100vh;}
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .hero-banner { padding: 2rem; text-align: center; border-bottom: 1px solid rgba(0,229,255,0.2); margin-bottom: 30px;}
        
        .nav-tabs-custom { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { background: rgba(15,23,42,0.8); border: 1px solid rgba(0,229,255,0.2); color: #94a3b8; padding: 12px 30px; border-radius: 8px; font-family: 'Oswald'; font-size: 1.1rem; text-decoration: none; transition: 0.3s; letter-spacing: 1px;}
        .tab-btn:hover { background: rgba(0,229,255,0.1); color: #fff; border-color: #00e5ff; }
        .tab-btn.active { background: #00e5ff; color: #000; font-weight: 800; border-color: #00e5ff; box-shadow: 0 0 20px rgba(0,229,255,0.4); transform: translateY(-2px);}

        .match-card { background: rgba(0,0,0,0.6); border: 1px solid rgba(0,229,255,0.15); border-radius: 12px; margin-bottom: 20px; overflow: hidden; transition: 0.3s;}
        .match-card:hover { border-color: #00e5ff; box-shadow: 0 5px 20px rgba(0,229,255,0.15); transform: translateY(-2px);}
        
        .score-grid { display: flex; width: 100%; min-height: 100px; align-items: stretch; padding: 15px;}
        
        .team-block { display: flex; align-items: center; gap: 15px; flex: 1; }
        .team-block.home { justify-content: flex-end; text-align: right; }
        .team-block.away { justify-content: flex-start; text-align: left; }
        .team-name { font-weight: 800; font-size: 1.3rem; color: #f8fafc; }
        .team-logo { width: 55px; height: 55px; object-fit: contain; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.8)); }
        
        .vs-box { width: 120px; display: flex; flex-direction: column; align-items: center; justify-content: center; background: linear-gradient(180deg, #0a1c52, #002878); border-radius: 8px; border: 1px solid rgba(0,229,255,0.3); box-shadow: inset 0 0 10px rgba(0,0,0,0.8); margin: 0 20px;}
        .match-score { font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; color: #ffffff; line-height: 1; text-shadow: 0 3px 5px rgba(0,0,0,0.9); }
        .match-status { font-size: 0.8rem; color: #00e5ff; font-weight: 800; letter-spacing: 2px; margin-top: 5px; }

        .events-grid { display: flex; width: 100%; background: rgba(0,0,0,0.8); border-top: 1px solid rgba(0,229,255,0.1); padding: 15px 0; font-size: 0.9rem; }
        .event-col { display: flex; flex-direction: column; gap: 8px; padding: 0 20px; flex: 1;}
        .event-col.home { align-items: flex-end; text-align: right; } 
        .event-col.away { align-items: flex-start; text-align: left; }
        .event-col.center { width: 120px; flex: none; }
        
        .event-item { display: flex; align-items: center; gap: 10px; font-weight: 600; color: #fff;}
        .event-time { font-family: 'Oswald'; font-weight: 700; color: #00e5ff;}
        .event-assist { color: #94a3b8; font-size: 0.8rem; font-style: italic;}
        
        .ref-card { width: 12px; height: 16px; border-radius: 2px; transform: rotate(5deg); box-shadow: 0 1px 4px rgba(0,0,0,0.8);}
        .ref-card.yellow { background-color: #fbbf24; }
        .ref-card.red { background-color: #ef4444; }
    </style>
</head>
<body>

    <div class="hero-banner">
        <h1 class="font-oswald" style="color: #00e5ff; font-size: 3.5rem; text-shadow: 0 0 30px rgba(0,229,255,0.5);">ROAD TO GLORY</h1>
        <h3 class="text-muted"><?= $asama_map[$asama]['ad'] ?></h3>
        
        <?php if($hafta == 9 || $hafta == 10): ?>
            <?php if($benim_macim_var_mi == 0): ?>
                <div class="alert alert-info mt-3 mx-auto" style="max-width: 600px; border: 1px dashed #00e5ff; background: rgba(0,229,255,0.1);">
                    <i class="fa-solid fa-star text-warning fs-4 me-2"></i> 
                    <strong>Tebrikler!</strong> Takımınız İlk 8'e girdiği için Play-Off turunu BAY geçiyor. Rakiplerinize Son 16'da başarılar!
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="mt-4 d-flex justify-content-center gap-3">
            <a href="cl.php" class="btn btn-outline-light fw-bold px-4 py-2"><i class="fa-solid fa-arrow-left"></i> Lig Tablosu</a>
            
            <?php if($hafta > 17): ?>
                <a href="cl_sezon_gecisi.php" class="btn btn-warning fw-bold font-oswald text-dark px-4 py-2" style="font-size: 1.1rem; box-shadow: 0 0 15px rgba(255,193,7,0.5);">🏆 KUPA TÖRENİ & SEZONU BİTİR</a>
            <?php else: ?>
                <a href="?asama=<?= $asama ?>&simule=1&full=1" class="btn btn-info fw-bold text-dark px-4 py-2">
                    <i class="fa-solid fa-forward-fast"></i> Aktif Turu Simüle Et
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container pb-5" style="max-width: 1000px;">
        
        <div class="nav-tabs-custom">
            <?php foreach($asama_map as $key => $data): ?>
                <a href="?asama=<?= $key ?>" class="tab-btn <?= $asama == $key ? 'active' : '' ?>">
                    <?= $data['ad'] ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if(empty($maclar_ui)): ?>
            <div class="text-center p-5" style="background: rgba(15,23,42,0.8); border: 1px dashed rgba(0,229,255,0.3); border-radius: 15px;">
                <i class="fa-solid fa-lock text-muted mb-3" style="font-size: 3rem;"></i>
                <h4 class="font-oswald text-muted">EŞLEŞMELER HENÜZ BELLİ OLMADI</h4>
                <p class="text-muted">Bu aşamanın maçları, bir önceki tur tamamlandıktan sonra çekilecektir.</p>
            </div>
        <?php else: ?>
            
            <?php foreach($maclar_ui as $mac): ?>
                <div class="match-card">
                    <div class="score-grid">
                        <div class="team-block home">
                            <span class="team-name"><?= $mac['ev_ad'] ?></span>
                            <img src="<?= $mac['ev_logo'] ?>" class="team-logo">
                        </div>
                        <div class="vs-box">
                            <?php if($mac['ev_skor'] !== null): ?>
                                <span class="match-score"><?= $mac['ev_skor'] ?> - <?= $mac['dep_skor'] ?></span>
                                <span class="match-status" style="color: #cbd5e1;">MS</span>
                            <?php else: ?>
                                <span class="match-score">VS</span>
                                <span class="match-status">HAFTA <?= $mac['hafta'] ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="team-block away">
                            <img src="<?= $mac['dep_logo'] ?>" class="team-logo">
                            <span class="team-name"><?= $mac['dep_ad'] ?></span>
                        </div>
                    </div>
                    
                    <?php if($mac['ev_skor'] === null): ?>
                        <div class="text-center bg-dark p-2 border-top border-secondary">
                            <a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=cl&hafta=<?= $mac['hafta'] ?>" class="btn btn-outline-info px-4 fw-bold"><i class="fa-solid fa-satellite-dish"></i> CANLI İZLE</a>
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
                                    echo "<div class='event-item justify-content-end'>$asist {$o['oyuncu']} <span class='event-time'>{$o['dakika']}'</span> <i class='fa-solid fa-futbol text-success fs-5'></i></div>"; 
                                }
                                foreach($ev_kartlar as $k) { 
                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı'); $renk = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                    echo "<div class='event-item justify-content-end'>{$k['oyuncu']} <span class='event-time'>{$k['dakika']}'</span> <div class='ref-card $renk'></div></div>"; 
                                }
                                ?>
                            </div>
                            <div class="event-col center"></div>
                            <div class="event-col away">
                                <?php 
                                foreach($dep_olaylar as $o) {
                                    if(strtolower($o['tip'] ?? 'gol') != 'gol') continue; 
                                    $asist = (isset($o['asist']) && $o['asist'] !== '-') ? "<span class='event-assist'>(A: {$o['asist']})</span>" : "";
                                    echo "<div class='event-item'><i class='fa-solid fa-futbol text-success fs-5'></i> <span class='event-time'>{$o['dakika']}'</span> {$o['oyuncu']} $asist</div>"; 
                                }
                                foreach($dep_kartlar as $k) { 
                                    $tip = $k['detay'] ?? ($k['tip'] ?? 'Sarı'); $renk = ($tip == 'Kırmızı') ? 'red' : 'yellow';
                                    echo "<div class='event-item'><div class='ref-card $renk'></div> <span class='event-time'>{$k['dakika']}'</span> {$k['oyuncu']}</div>"; 
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
</body>
</html>