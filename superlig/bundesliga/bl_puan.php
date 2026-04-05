<?php
// ==============================================================================
// BUNDESLIGA - İSTATİSTİK VE VERİ MERKEZİ (RED & BLACK GERMAN THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM de_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;

// 1. GÜNCEL PUAN DURUMUNU ÇEK
$puan_durumu = $pdo->query("SELECT * FROM de_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

// FORM (W, D, L) HESAPLAMASI
$takim_form = [];
$tum_oynanan_maclar_form = $pdo->query("SELECT ev, dep, ev_skor, dep_skor FROM de_maclar WHERE ev_skor IS NOT NULL ORDER BY hafta DESC")->fetchAll(PDO::FETCH_ASSOC);
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
$tum_maclar = $pdo->query("SELECT ev_olaylar, dep_olaylar, ev_kartlar, dep_kartlar FROM de_maclar WHERE ev_skor IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
$goller = []; $asistler = []; $sarilar = []; $kirmizilar = [];

foreach($tum_maclar as $m) {
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
    $oyuncular_db = $pdo->query("SELECT o.isim, t.logo, t.takim_adi FROM de_oyuncular o JOIN de_takimlar t ON o.takim_id = t.id")->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Bundesliga Veri Merkezi | Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --de-primary: #e11d48;
            --de-secondary: #dc2626;
            --de-accent: #ff6b35;
            --de-dark: #0d0d0d;
            
            --color-win: #22c55e;
            --color-draw: #9ca3af;
            --color-loss: #ef4444;

            --bg-body: #0d0d0d;
            --bg-panel: #1a1a1a;
            --border-color: rgba(220, 38, 38, 0.2);
            
            --text-primary: #f9fafb;
            --text-muted: #e11d48;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(200,16,46,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(220,38,38,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(10, 10, 10, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--de-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--de-primary); }
        .nav-brand i { color: var(--de-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--de-secondary); text-shadow: 0 0 10px var(--de-secondary); }
        .btn-action-outline { background: transparent; border: 1px solid var(--de-primary); color: var(--de-primary); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.3s;}
        .btn-action-outline:hover { background: var(--de-primary); color: #fff; box-shadow: 0 0 15px var(--de-primary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.3); font-weight: 700;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--de-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.4);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; transition: 0.2s; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(220,38,38,0.05); }
        
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 700; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.8));}
        
        .data-table tbody tr td:first-child { border-left: 4px solid transparent; }
        .zone-cl td:first-child { border-left-color: var(--de-secondary) !important; background: rgba(220,38,38,0.05); }
        .zone-el td:first-child { border-left-color: #f87171 !important; }
        .zone-rel td:first-child { border-left-color: var(--de-primary) !important; opacity: 0.8;}
        .my-team-row td { background: rgba(220,38,38,0.1); font-weight: 800; color: var(--de-secondary) !important;}

        .form-dot { width: 20px; height: 20px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: #000; }
        .form-dot.W { background: var(--color-win); } .form-dot.D { background: var(--color-draw); color:#fff; } .form-dot.L { background: var(--color-loss); color:#fff; }

        .leaderboard-list { list-style: none; padding: 0; margin: 0; }
        .leaderboard-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: 0.2s;}
        .leaderboard-item:hover { background: rgba(220,38,38,0.05); }
        .leaderboard-item:last-child { border-bottom: none; }
        
        .lb-rank { font-family: 'Oswald'; font-size: 1.2rem; font-weight: 700; width: 30px; color: var(--text-muted); text-align:center;}
        .lb-rank.first { color: var(--de-secondary); font-size: 1.5rem; text-shadow: 0 0 10px rgba(220,38,38,0.5);}
        .lb-rank.second { color: #cbd5e1; }
        .lb-rank.third { color: #b45309; } 
        
        .lb-player { flex: 1; display: flex; align-items: center; gap: 10px; }
        .lb-logo { width: 30px; height: 30px; object-fit: contain; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.8));}
        .lb-name { font-weight: 700; color: #fff; font-size: 0.95rem; display: flex; flex-direction: column; line-height: 1.2; letter-spacing: 0.5px;}
        .lb-teamname { font-size: 0.7rem; color: var(--text-muted); font-weight: 500;}
        
        .lb-stat { font-family: 'Oswald'; font-size: 1.3rem; font-weight: 900; color: var(--de-dark); background: var(--de-secondary); padding: 2px 12px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.5);}
        .lb-stat.assists { background: #f87171; }
        .lb-stat.cards { background: var(--de-primary); color: #fff;}

        @media (max-width: 992px) { .d-mobile-none { display: none !important; } }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="bundesliga.php" class="nav-brand text-decoration-none">
            <i class="fa-solid fa-shield-halved"></i> 
            <span class="font-oswald text-white">BUNDESLIGA</span>
        </a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="bundesliga.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="bl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro / Taktik</a>
            <a href="bl_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="bl_puan.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--de-secondary);"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
        </div>
        
        <div class="d-flex gap-3">
            <a href="bundesliga.php" class="btn-action-outline">
                <i class="fa-solid fa-flag"></i> Bundesliga'ya Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(220,38,38,0.3);"><i class="fa-solid fa-chart-line" style="color:var(--de-secondary);"></i> BUNDESLIGA DATENZENTRUM</h1>
        <p class="fs-5 mt-2 fw-bold" style="color: var(--text-muted);">Bundesliga tablosu, istatistik krallıkları ve kart bilgileri.</p>
    </div>

    <div class="container py-5" style="max-width: 1600px;">
        <div class="row g-4">
            
            <div class="col-xl-8 col-lg-7">
                <div class="panel-card" style="height: 100%;">
                    <div class="panel-header">
                        <span class="font-oswald text-white fs-5"><i class="fa-solid fa-list-ol me-2" style="color:var(--de-secondary);"></i> BUNDESLIGA TABELLE</span>
                        <span class="badge bg-dark border text-white" style="border-color: var(--de-primary) !important;">Spieltag <?= $hafta > 34 ? 34 : $hafta ?></span>
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
                                    if($sira <= 4) $row_class = "zone-cl"; 
                                    elseif($sira == 5 || $sira == 6) $row_class = "zone-el"; 
                                    elseif($sira >= 16) $row_class = "zone-rel"; 

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
                                        <?= $sira == 1 ? '<i class="fa-solid fa-crown me-1" style="color:var(--de-secondary);"></i> 1' : $sira ?>
                                    </td>
                                    <td>
                                        <a href="bl_takim.php?id=<?= $t['id'] ?>" class="cell-club">
                                            <img src="<?= htmlspecialchars($t['logo']) ?>" alt="logo"> 
                                            <span class="text-truncate" style="max-width:180px; letter-spacing:0.5px;"><?= htmlspecialchars($t['takim_adi']) ?></span>
                                        </a>
                                    </td>
                                    <td style="color:var(--text-muted);"><?= $mp ?></td>
                                    <td><?= $t['galibiyet'] ?></td>
                                    <td><?= $t['beraberlik'] ?></td>
                                    <td><?= $t['malubiyet'] ?></td>
                                    <td style="color: <?= $gd>0?'var(--color-win)':($gd<0?'var(--color-loss)':'') ?>;"><?= $gd>0?'+'.$gd:$gd ?></td>
                                    <td class="font-oswald" style="font-size:1.3rem; font-weight:900; color:#fff; text-shadow: 0 2px 4px rgba(0,0,0,0.8);"><?= $t['puan'] ?></td>
                                    <td><div class="d-flex gap-1 justify-content-center"><?= $form_html ?></div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="px-4 py-3 border-top" style="border-color: var(--border-color); background: rgba(0,0,0,0.5); font-size:0.8rem; font-weight:600; color:var(--text-muted);">
                        <div class="d-flex flex-wrap gap-4 justify-content-center">
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:var(--de-secondary);border-radius:2px; box-shadow: 0 0 5px var(--de-secondary);"></div> 1-4: Şampiyonlar Ligi</div>
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:#f87171;border-radius:2px;"></div> 5-6: Avrupa Kupası</div>
                            <div class="d-flex align-items-center gap-2"><div style="width:12px;height:12px;background:var(--de-primary);border-radius:2px;"></div> 16-18: Küme Düşme</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5 d-flex flex-column gap-4">
                
                <div class="panel-card" style="border-color: rgba(220,38,38,0.3);">
                    <div class="panel-header" style="background: rgba(220,38,38,0.05); color: var(--de-secondary);">
                        <span class="font-oswald"><i class="fa-solid fa-futbol me-2"></i> TORJÄGER RANGLISTE (Gol Krallığı)</span>
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
                                <img src="<?= htmlspecialchars($kulup['logo']) ?>" class="lb-logo">
                                <div class="lb-name">
                                    <?= htmlspecialchars($isim) ?>
                                    <span class="lb-teamname"><?= htmlspecialchars($kulup['takim']) ?></span>
                                </div>
                            </div>
                            <div class="lb-stat"><?= $gol_sayisi ?></div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_gol)): ?><li class="p-4 text-center text-muted">Bundesliga'da henüz gol atılmadı.</li><?php endif; ?>
                    </ul>
                </div>

                <div class="panel-card" style="border-color: rgba(251,191,36,0.3);">
                    <div class="panel-header" style="background: rgba(251,191,36,0.05); color: #f87171;">
                        <span class="font-oswald"><i class="fa-solid fa-shoe-prints me-2"></i> VORLAGENGEBER (Asist Krallığı)</span>
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
                                <img src="<?= htmlspecialchars($kulup['logo']) ?>" class="lb-logo">
                                <div class="lb-name">
                                    <?= htmlspecialchars($isim) ?>
                                    <span class="lb-teamname"><?= htmlspecialchars($kulup['takim']) ?></span>
                                </div>
                            </div>
                            <div class="lb-stat assists"><?= $asist_sayisi ?></div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_asist)): ?><li class="p-4 text-center text-muted">Henüz asist yapılmadı.</li><?php endif; ?>
                    </ul>
                </div>

                <div class="panel-card" style="border-color: rgba(200,16,46,0.3);">
                    <div class="panel-header" style="color: var(--de-primary); background: rgba(200,16,46,0.05);">
                        <span class="font-oswald"><i class="fa-solid fa-square"></i> ROTE KARTEN (Kırmızı Kartlar)</span>
                    </div>
                    <ul class="leaderboard-list">
                        <?php 
                        $sira = 1;
                        foreach($top_kirmizi as $isim => $kart_sayisi): 
                            $kulup = $oyuncu_kulupleri[$isim] ?? ['logo' => 'https://cdn-icons-png.flaticon.com/512/32/32441.png', 'takim' => 'Bilinmiyor'];
                        ?>
                        <li class="leaderboard-item py-2">
                            <div class="lb-player ms-2">
                                <img src="<?= htmlspecialchars($kulup['logo']) ?>" class="lb-logo" style="width:24px;height:24px;">
                                <div class="lb-name" style="font-size: 0.85rem;">
                                    <?= htmlspecialchars($isim) ?>
                                </div>
                            </div>
                            <div class="lb-stat cards fs-6 py-1"><?= $kart_sayisi ?></div>
                        </li>
                        <?php $sira++; endforeach; ?>
                        <?php if(empty($top_kirmizi)): ?><li class="p-3 text-center text-muted small">Henüz kırmızı kart gören oyuncu yok.</li><?php endif; ?>
                    </ul>
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(13,13,13,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important; border-top-color: var(--de-secondary) !important;">
        <a href="bundesliga.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="bl_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="bl_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="bl_puan.php" class="text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 25%; color: var(--de-secondary);">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block"></i> Veri
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
