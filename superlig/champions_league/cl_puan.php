<?php
// ==============================================================================
// CHAMPIONS LEAGUE - İSTATİSTİK VE VERİ MERKEZİ (BLUE & CYAN THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;

// 1. GÜNCEL PUAN DURUMUNU ÇEK
$puan_durumu = $pdo->query("SELECT * FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

// FORM (W, D, L) HESAPLAMASI
$takim_form = [];
$tum_oynanan_maclar_form = $pdo->query("SELECT ev, dep, ev_skor, dep_skor FROM cl_maclar WHERE ev_skor IS NOT NULL ORDER BY hafta DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach($puan_durumu as $t) { $takim_form[$t['id']] = []; }

foreach($tum_oynanan_maclar_form as $m) {
    $ev = $m['ev']; $dep = $m['dep'];
    if(isset($takim_form[$ev]) && count($takim_form[$ev]) < 5) {
        if($m['ev_skor'] > $m['dep_skor']) $takim_form[$ev][] = 'W';
        elseif($m['ev_skor'] == $m['dep_skor']) $takim_form[$ev][] = 'D';
        else $takim_form[$ev][] = 'L';
    }
    if(isset($takim_form[$dep]) && count($takim_form[$dep]) < 5) {
        if($m['dep_skor'] > $m['ev_skor']) $takim_form[$dep][] = 'W';
        elseif($m['ev_skor'] == $m['dep_skor']) $takim_form[$dep][] = 'D';
        else $takim_form[$dep][] = 'L';
    }
}
foreach($takim_form as $id => $f) { $takim_form[$id] = array_reverse($f); }

// 2. İSTATİSTİKLERİ HESAPLA (Gol, Asist, Kartlar)
$tum_maclar = $pdo->query("SELECT ev_olaylar, dep_olaylar, ev_kartlar, dep_kartlar FROM cl_maclar WHERE ev_skor IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
$goller = []; $asistler = []; $sarilar = []; $kirmizilar = [];

foreach($tum_maclar as $m) {
    // GOLLER VE ASİSTLER
    $ev_olaylar = json_decode($m['ev_olaylar'], true) ?: [];
    $dep_olaylar = json_decode($m['dep_olaylar'], true) ?: [];
    $olaylar = array_merge($ev_olaylar, $dep_olaylar);
    
    foreach($olaylar as $o) {
        $tip = $o['tip'] ?? 'gol';
        if(strtolower($tip) == 'gol') {
            $oyuncu = trim($o['oyuncu'] ?? '');
            if($oyuncu && $oyuncu != 'Bilinmiyor') {
                $goller[$oyuncu] = ($goller[$oyuncu] ?? 0) + 1;
            }
            
            $asist = trim($o['asist'] ?? '-');
            if($asist && $asist != '-' && $asist != 'Bilinmiyor') {
                $asistler[$asist] = ($asistler[$asist] ?? 0) + 1;
            }
        }
    }
    
    // KARTLAR
    $ev_kartlar = json_decode($m['ev_kartlar'], true) ?: [];
    $dep_kartlar = json_decode($m['dep_kartlar'], true) ?: [];
    $kartlar = array_merge($ev_kartlar, $dep_kartlar);
    
    foreach($kartlar as $k) {
        $oyuncu = trim($k['oyuncu'] ?? '');
        $detay = $k['detay'] ?? ($k['tip'] ?? ''); 
        if($oyuncu && $oyuncu != 'Bilinmiyor') {
            if($detay == 'Kırmızı') $kirmizilar[$oyuncu] = ($kirmizilar[$oyuncu] ?? 0) + 1;
            else $sarilar[$oyuncu] = ($sarilar[$oyuncu] ?? 0) + 1;
        }
    }
}

arsort($goller); arsort($asistler); arsort($kirmizilar); arsort($sarilar);
$top_gol = array_slice($goller, 0, 10, true);
$top_asist = array_slice($asistler, 0, 10, true);
$top_kirmizi = array_slice($kirmizilar, 0, 5, true);

// Oyuncuların takım logolarını bul
$oyuncu_kulupleri = [];
try {
    $oyuncular_db = $pdo->query("SELECT o.isim, t.logo, t.takim_adi FROM cl_oyuncular o JOIN cl_takimlar t ON o.takim_id = t.id")->fetchAll(PDO::FETCH_ASSOC);
    foreach($oyuncular_db as $odb) {
        $oyuncu_kulupleri[$odb['isim']] = ['logo' => $odb['logo'], 'takim' => $odb['takim_adi']];
    }
} catch(Exception $e) {}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Avrupa Veri Merkezi | CL Manager</title>
    
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

        /* NAVBAR */
        .pro-navbar { background: rgba(10, 28, 82, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--cl-accent); }
        .nav-brand i { color: var(--cl-accent); }
        .nav-link-item { color: var(--cl-silver); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; text-shadow: 0 0 10px var(--cl-accent); }
        .btn-action-outline { background: transparent; border: 1px solid var(--cl-accent); color: var(--cl-accent); font-weight: 600; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-outline:hover { background: var(--cl-accent); color: #000; }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0,229,255,0.03); text-align: center; }

        /* DASHBOARD KARTLARI */
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,229,255,0.05); display: flex; flex-direction: column; }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: rgba(0,229,255,0.05); flex-shrink: 0;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--cl-accent); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.3);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; transition: 0.2s; }
        .data-table tbody tr:hover td { background: rgba(0,229,255,0.05); }
        
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 600; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; }
        
        /* ŞL Yeni Format - İlk 8 ve Play-Off Kuralları */
        .data-table tbody tr td:first-child { border-left: 4px solid transparent; }
        .zone-direct td:first-child { border-left-color: var(--cl-accent) !important; background: rgba(0,229,255,0.05); }
        .zone-playoff td:first-child { border-left-color: #fbbf24 !important; }
        .zone-eliminated td:first-child { border-left-color: var(--color-loss) !important; opacity: 0.7; }
        .my-team-row td { background: rgba(255,255,255,0.05); font-weight: bold; }

        .form-dot { width: 20px; height: 20px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: #fff; }
        .form-dot.W { background: var(--color-win); } .form-dot.D { background: var(--color-draw); } .form-dot.L { background: var(--color-loss); }

        /* KRALLIK KARTLARI (LEADERBOARDS) */
        .leaderboard-list { list-style: none; padding: 0; margin: 0; }
        .leaderboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid var(--border-color); transition: 0.2s;}
        .leaderboard-item:hover { background: rgba(0,229,255,0.05); }
        .leaderboard-item:last-child { border-bottom: none; }
        
        .lb-rank { font-family: 'Oswald'; font-size: 1.2rem; font-weight: 700; width: 30px; color: var(--text-muted); text-align:center;}
        .lb-rank.first { color: var(--cl-accent); font-size: 1.5rem; text-shadow: 0 0 10px rgba(0,229,255,0.5);}
        .lb-rank.second { color: #cbd5e1; }
        .lb-rank.third { color: #b45309; } /* Bronz */
        
        .lb-player { flex: 1; display: flex; align-items: center; gap: 10px; }
        .lb-logo { width: 30px; height: 30px; object-fit: contain; }
        .lb-name { font-weight: 600; color: #fff; font-size: 0.95rem; display: flex; flex-direction: column; line-height: 1.2;}
        .lb-teamname { font-size: 0.7rem; color: var(--text-muted); font-weight: 400;}
        
        .lb-stat { font-family: 'Oswald'; font-size: 1.3rem; font-weight: 700; color: #fff; background: rgba(0,0,0,0.4); padding: 2px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.1);}

        @media (max-width: 992px) { .d-mobile-none { display: none !important; } }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand text-decoration-none">
            <i class="fa-solid fa-futbol"></i> 
            <span class="font-oswald text-white">CHAMPIONS LEAGUE</span>
        </a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="cl.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="cl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Taktik Odası</a>
            <a href="cl_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="cl_puan.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--cl-accent);"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
        </div>
        
        <div class="d-flex gap-3">
            <a href="../super_lig/superlig.php" class="btn-action-outline text-danger border-danger hover-lift">
                <i class="fa-solid fa-arrow-left"></i> Yerel Lige Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(0,229,255,0.5);"><i class="fa-solid fa-chart-line" style="color:var(--cl-accent);"></i> AVRUPA VERİ MERKEZİ</h1>
        <p class="text-info fs-5 mt-2 fw-bold">Detaylı lig tablosu ve istatistik krallıkları.</p>
    </div>

    <div class="container py-5" style="max-width: 1600px;">
        <div class="row g-4">
            
            <div class="col-xl-8 col-lg-7">
                <div class="panel-card" style="height: 100%;">
                    <div class="panel-header">
                        <span class="font-oswald text-white fs-5"><i class="fa-solid fa-list-ol me-2" style="color:var(--cl-accent);"></i> İsviçre Sistemi (Tek Lig) Tablosu</span>
                        <span class="badge bg-dark border border-info text-info">Maç Günü <?= $hafta > 17 ? 17 : $hafta ?></span>
                    </div>
                    
                    <div class="table-responsive p-0" style="flex: 1;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Sıra</th>
                                    <th>Takım</th>
                                    <th>O</th>
                                    <th>G</th>
                                    <th>B</th>
                                    <th>M</th>
                                    <th>AV</th>
                                    <th style="font-size: 0.9rem; color: #fff;">P</th>
                                    <th>Form</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach($puan_durumu as $index => $t): 
                                    $sira = $index + 1; 
                                    
                                    $row_class = "";
                                    if($sira <= 8) $row_class = "zone-direct"; 
                                    elseif($sira <= 16) $row_class = "zone-playoff"; 
                                    else $row_class = "zone-eliminated"; 

                                    if($t['id'] == $kullanici_takim_id) $row_class .= " my-team-row";
                                    
                                    $mp = $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'];
                                    $gd = $t['atilan_gol'] - $t['yenilen_gol'];
                                    
                                    $form_html = "";
                                    $takimin_formu = $takim_form[$t['id']] ?? [];
                                    for($i=0; $i<5; $i++) {
                                        if(isset($takimin_formu[$i])) {
                                            $snc = $takimin_formu[$i];
                                            $form_html .= "<div class='form-dot $snc'>$snc</div>";
                                        } else {
                                            $form_html .= "<div class='form-dot' style='background:rgba(255,255,255,0.05); color:transparent;'>-</div>";
                                        }
                                    }
                                ?>
                                <tr class="<?= $row_class ?>">
                                    <td class="font-oswald" style="font-size: 1.1rem; color: var(--text-muted);">
                                        <?= $sira == 1 ? '<i class="fa-solid fa-crown me-1 text-warning"></i> 1' : $sira ?>
                                    </td>
                                    <td>
                                        <a href="cl_takim.php?id=<?= $t['id'] ?>" class="cell-club">
                                            <img src="<?= $t['logo'] ?>" alt="logo"> 
                                            <span class="text-truncate" style="max-width:180px; letter-spacing:0.5px; <?= $t['id'] == $kullanici_takim_id ? 'color:var(--cl-accent);' : '' ?>"><?= $t['takim_adi'] ?></span>
                                        </a>
                                    </td>
                                    <td style="font-weight:600; color:var(--text-muted);"><?= $mp ?></td>
                                    <td style="font-weight:600;"><?= $t['galibiyet'] ?></td>
                                    <td style="font-weight:600;"><?= $t['beraberlik'] ?></td>
                                    <td style="font-weight:600;"><?= $t['malubiyet'] ?></td>
                                    <td style="font-weight:600; color: <?= $gd>0?'var(--color-win)':($gd<0?'var(--color-loss)':'') ?>;"><?= $gd>0?'+'.$gd:$gd ?></td>
                                    <td class="font-oswald" style="font-size:1.3rem; font-weight:700; color:#fff; text-shadow: 0 0 5px rgba(255,255,255,0.3);"><?= $t['puan'] ?></td>
                                    <td><div class="d-flex gap-1 justify-content-center"><?= $form_html ?></div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-4 py-3 border-top" style="border-color: var(--border-color); background: rgba(0,0,0,0.5); font-size:0.8rem; font-weight:600; color:var(--cl-silver);">
                        <div class="d-flex flex-wrap gap-4 justify-content-center">
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:var(--cl-accent);border-radius:2px; box-shadow: 0 0 5px var(--cl-accent);"></div> İlk 8: Doğrudan Son 16</div>
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:#fbbf24;border-radius:2px;"></div> 9-16: Play-Off Turu</div>
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:var(--color-loss);border-radius:2px;"></div> 17-18: Elenir</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5 d-flex flex-column gap-4">
                
                <div class="panel-card" style="border-color: rgba(0,229,255,0.4);">
                    <div class="panel-header" style="background: rgba(0, 229, 255, 0.1); color: var(--cl-accent);">
                        <span class="font-oswald"><i class="fa-solid fa-futbol me-2"></i> GOL KRALLIĞI</span>
                    </div>
                    <ul class="leaderboard-list">
                        <?php 
                        $sira = 1;
                        foreach($top_gol as $isim => $gol_sayisi): 
                            $kulup = $oyuncu_kulupleri[$isim] ?? ['logo' => 'https://cdn-icons-png.flaticon.com/512/32/32441.png', 'takim' => 'Bilinmiyor'];
                            $rankClass = ($sira == 1) ? 'first' : (($sira == 2) ? 'second' : (($sira == 3) ? 'third' : ''));
                        ?>
                        <li class="leaderboard-item">
                            <div class="lb-rank <?= $rankClass ?>"><?= $sira ?></div>
                            <div class="lb-player">
                                <img src="<?= $kulup['logo'] ?>" class="lb-logo">
                                <div class="lb-name">
                                    <?= htmlspecialchars($isim) ?>
                                    <span class="lb-teamname"><?= htmlspecialchars($kulup['takim']) ?></span>
                                </div>
                            </div>
                            <div class="lb-stat" style="color: var(--cl-accent); border-color: var(--cl-accent);"><?= $gol_sayisi ?></div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_gol)): ?><li class="p-4 text-center text-muted">Devler liginde henüz gol atılmadı.</li><?php endif; ?>
                    </ul>
                </div>

                <div class="panel-card" style="border-color: rgba(16, 185, 129, 0.4);">
                    <div class="panel-header" style="background: rgba(16, 185, 129, 0.1); color: var(--color-win);">
                        <span class="font-oswald"><i class="fa-solid fa-shoe-prints me-2"></i> ASİST KRALLIĞI</span>
                    </div>
                    <ul class="leaderboard-list">
                        <?php 
                        $sira = 1;
                        foreach($top_asist as $isim => $asist_sayisi): 
                            $kulup = $oyuncu_kulupleri[$isim] ?? ['logo' => 'https://cdn-icons-png.flaticon.com/512/32/32441.png', 'takim' => 'Bilinmiyor'];
                            $rankClass = ($sira == 1) ? 'first' : (($sira == 2) ? 'second' : (($sira == 3) ? 'third' : ''));
                        ?>
                        <li class="leaderboard-item">
                            <div class="lb-rank <?= $rankClass ?>"><?= $sira ?></div>
                            <div class="lb-player">
                                <img src="<?= $kulup['logo'] ?>" class="lb-logo">
                                <div class="lb-name">
                                    <?= htmlspecialchars($isim) ?>
                                    <span class="lb-teamname"><?= htmlspecialchars($kulup['takim']) ?></span>
                                </div>
                            </div>
                            <div class="lb-stat text-success" style="border-color: var(--color-win);"><?= $asist_sayisi ?></div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_asist)): ?><li class="p-4 text-center text-muted">Henüz asist yapılmadı.</li><?php endif; ?>
                    </ul>
                </div>

                <div class="panel-card border-secondary">
                    <div class="panel-header text-muted">
                        <span class="font-oswald"><i class="fa-solid fa-square" style="color: var(--color-loss);"></i> KIRMIZI KARTLAR</span>
                    </div>
                    <ul class="leaderboard-list">
                        <?php 
                        $sira = 1;
                        foreach($top_kirmizi as $isim => $kart_sayisi): 
                            $kulup = $oyuncu_kulupleri[$isim] ?? ['logo' => 'https://cdn-icons-png.flaticon.com/512/32/32441.png', 'takim' => 'Bilinmiyor'];
                        ?>
                        <li class="leaderboard-item py-2">
                            <div class="lb-player ms-2">
                                <img src="<?= $kulup['logo'] ?>" class="lb-logo" style="width:20px;height:20px;">
                                <div class="lb-name" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($isim) ?>
                                </div>
                            </div>
                            <div class="fw-bold" style="color: var(--color-loss);"><?= $kart_sayisi ?> Kırmızı</div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_kirmizi)): ?><li class="p-3 text-center text-muted small">Henüz kırmızı kart gören oyuncu yok.</li><?php endif; ?>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(11,15,25,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important;">
        <a href="cl.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="cl_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="cl_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="cl_puan.php" class="text-info text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block"></i> Veri
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>