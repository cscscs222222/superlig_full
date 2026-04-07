<?php
// ==============================================================================
// LIGUE 1 - PUAN TABLOSU VE İSTATİSTİKLER
// ==============================================================================
include '../db.php';

$ayar = [];
try { $ayar = $pdo->query("SELECT * FROM fr_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC); } catch(Throwable $e) {}
$hafta = $ayar['hafta'] ?? 1;
$gecen_sezon_sampiyon = $ayar['gecen_sezon_sampiyon'] ?? 'Paris Saint-Germain';

$puan_durumu = [];
try { $puan_durumu = $pdo->query("SELECT * FROM fr_takimlar ORDER BY puan DESC,(atilan_gol-yenilen_gol) DESC,atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) {}

// İstatistikler (goller, asistler)
$goller=[]; $asistler=[];
try {
    $tum_maclar = $pdo->query("SELECT ev_olaylar,dep_olaylar FROM fr_maclar WHERE ev_skor IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    foreach($tum_maclar as $m) {
        foreach([json_decode($m['ev_olaylar']??'[]',true)??[], json_decode($m['dep_olaylar']??'[]',true)??[]] as $olaylar) {
            foreach($olaylar as $o) {
                if(strtolower($o['tip']??'')=='gol') {
                    $p=trim($o['oyuncu']??''); if($p&&$p!='Bilinmiyor') $goller[$p]=($goller[$p]??0)+1;
                    $a=trim($o['asist']??'-'); if($a&&$a!='-'&&$a!='Bilinmiyor') $asistler[$a]=($asistler[$a]??0)+1;
                }
            }
        }
    }
} catch(Throwable $e) {}
arsort($goller); arsort($asistler);
$top_goller = array_slice($goller, 0, 10, true);
$top_asistler = array_slice($asistler, 0, 10, true);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ligue 1 Puan Tablosu | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --fr-primary:#003f8a; --fr-secondary:#ef4135; --fr-gold:#d4af37; --bg:#0d0d0d; --panel:#1a1a1a; --border:rgba(0,63,138,0.25); }
body { background:var(--bg); color:#fff; font-family:'Inter',sans-serif; min-height:100vh; }
.font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
.pro-navbar { background:rgba(10,10,10,0.97); backdrop-filter:blur(24px); border-bottom:2px solid var(--fr-secondary); position:sticky; top:0; z-index:1000; padding:0 2rem; height:75px; display:flex; justify-content:space-between; align-items:center; }
.nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:900; color:#fff; text-decoration:none; }
.nav-brand i { color:var(--fr-secondary); }
.nav-link-item { color:#94a3b8; font-weight:600; padding:8px 16px; text-decoration:none; transition:0.2s; }
.nav-link-item:hover { color:#fff; }
.panel-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.panel-header { padding:1.2rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.2); }
.panel-header h5 { color:#fff; margin:0; font-weight:700; font-family:'Oswald',sans-serif; font-size:1rem; text-transform:uppercase; }
.data-table { width:100%; border-collapse:separate; border-spacing:0; font-size:0.88rem; }
.data-table th { padding:0.8rem 1rem; color:var(--fr-gold); font-weight:700; text-transform:uppercase; font-size:0.72rem; border-bottom:1px solid var(--border); text-align:center; }
.data-table th:nth-child(2) { text-align:left; }
.data-table td { padding:0.75rem 1rem; text-align:center; border-bottom:1px solid rgba(255,255,255,0.03); vertical-align:middle; color:#fff; }
.data-table tbody tr:hover td { background:rgba(0,63,138,0.08); }
.cell-club { display:flex; align-items:center; gap:12px; text-decoration:none; color:#fff; font-weight:700; text-align:left; }
.cell-club img { width:26px; height:26px; object-fit:contain; }
.data-table tbody tr td:first-child { border-left:4px solid transparent; }
.zone-cl td:first-child { border-left-color:var(--fr-primary)!important; background:rgba(0,63,138,0.07); }
.zone-el td:first-child { border-left-color:var(--fr-secondary)!important; }
.zone-rel td:first-child { border-left-color:#6b7280!important; opacity:0.8; }
.puan-lbl { font-weight:900; color:var(--fr-gold); }
.champion-banner { background:linear-gradient(135deg,rgba(0,63,138,0.3),rgba(239,65,53,0.2)); border:1px solid rgba(212,175,55,0.4); border-radius:12px; padding:16px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; }
.champion-banner i { font-size:2rem; color:var(--fr-gold); }
.champion-banner .title { font-size:0.75rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; }
.champion-banner .name { font-size:1.2rem; font-weight:900; color:#fff; font-family:'Oswald',sans-serif; }
.stat-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
.stat-rank { width:24px; text-align:center; font-family:'Oswald',sans-serif; font-weight:700; color:#94a3b8; font-size:0.9rem; }
.stat-name { flex:1; font-weight:600; color:#fff; }
.stat-val { font-family:'Oswald',sans-serif; font-weight:900; color:var(--fr-gold); font-size:1.1rem; min-width:32px; text-align:right; }
</style>
</head>
<body>
<nav class="pro-navbar">
    <a href="ligue1.php" class="nav-brand font-oswald"><i class="fa-solid fa-flag"></i> LIGUE 1</a>
    <div class="d-none d-lg-flex gap-2">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="ligue1.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Fikstür</a>
        <a href="l1_puan.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-chart-bar"></i> Puan Tablosu</a>
        <a href="l1_sezon_gecisi.php" class="nav-link-item"><i class="fa-solid fa-trophy"></i> Sezon Sonu</a>
    </div>
    <span class="nav-link-item">Hafta <?=$hafta?></span>
</nav>

<div class="container-fluid py-4 px-4">
    <div class="champion-banner">
        <i class="fa-solid fa-crown"></i>
        <div>
            <div class="title">🏆 Geçen Sezon Ligue 1 Şampiyonu</div>
            <div class="name"><?=htmlspecialchars($gecen_sezon_sampiyon)?></div>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-table-list me-2"></i>Puan Durumu — Ligue 1</h5></div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AG</th><th>YG</th><th>AV</th><th>P</th></tr></thead>
                        <tbody>
                        <?php foreach($puan_durumu as $i => $t): $s=$i+1;
                            $cls = $s<=4?'zone-cl':($s<=6?'zone-el':($s>=18?'zone-rel':''));
                            $o=($t['galibiyet']+$t['beraberlik']+$t['malubiyet']);
                            $av=$t['atilan_gol']-$t['yenilen_gol'];
                        ?>
                        <tr class="<?=$cls?>">
                            <td style="color:#94a3b8;font-weight:700;"><?=$s?></td>
                            <td><div class="cell-club"><img src="<?=htmlspecialchars($t['logo']??'')?>" onerror="this.style.display='none'"><span><?=htmlspecialchars($t['takim_adi'])?></span></div></td>
                            <td><?=$o?></td><td><?=$t['galibiyet']?></td><td><?=$t['beraberlik']?></td><td><?=$t['malubiyet']?></td>
                            <td><?=$t['atilan_gol']?></td><td><?=$t['yenilen_gol']?></td>
                            <td style="color:<?=$av>0?'#22c55e':($av<0?'#ef4444':'#fff')?>"><?=($av>0?'+':'')?><?=$av?></td>
                            <td class="puan-lbl"><?=$t['puan']?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px;font-size:0.75rem;color:#94a3b8;border-top:1px solid var(--border);display:flex;gap:16px;">
                    <span style="border-left:3px solid var(--fr-primary);padding-left:6px;">1-4: Şampiyonlar Ligi</span>
                    <span style="border-left:3px solid var(--fr-secondary);padding-left:6px;">5-6: Avrupa Ligi</span>
                    <span style="border-left:3px solid #6b7280;padding-left:6px;">18-20: Küme Düşme</span>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel-card mb-4">
                <div class="panel-header"><h5><i class="fa-solid fa-futbol me-2"></i>Gol Krallığı</h5></div>
                <div style="padding:12px 16px;">
                <?php if(empty($top_goller)): ?>
                    <div style="color:#94a3b8;text-align:center;padding:20px;">Henüz gol atılmadı.</div>
                <?php else: $r=1; foreach($top_goller as $isim => $adet): ?>
                    <div class="stat-row">
                        <div class="stat-rank"><?=$r++?></div>
                        <div class="stat-name"><?=htmlspecialchars($isim)?></div>
                        <div class="stat-val"><?=$adet?> ⚽</div>
                    </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-hands-helping me-2"></i>Asist Krallığı</h5></div>
                <div style="padding:12px 16px;">
                <?php if(empty($top_asistler)): ?>
                    <div style="color:#94a3b8;text-align:center;padding:20px;">Henüz asist yapılmadı.</div>
                <?php else: $r=1; foreach($top_asistler as $isim => $adet): ?>
                    <div class="stat-row">
                        <div class="stat-rank"><?=$r++?></div>
                        <div class="stat-name"><?=htmlspecialchars($isim)?></div>
                        <div class="stat-val"><?=$adet?> 🅰️</div>
                    </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
