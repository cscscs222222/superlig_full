<?php
// ==============================================================================
// UEFA CONFERENCE LEAGUE - PUAN DURUMU VE İSTATİSTİKLER
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM uecl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;
$sezon_yili = $ayar['sezon_yil'] ?? 2025;

$puan_durumu = $pdo->query("SELECT * FROM uecl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

// İstatistikler: gol, asist, kart
$tum_maclar = [];
try { $tum_maclar = $pdo->query("SELECT ev_olaylar, dep_olaylar, ev_kartlar, dep_kartlar FROM uecl_maclar WHERE ev_skor IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e){}
$goller = []; $asistler = [];
foreach ($tum_maclar as $m) {
    $olaylar = array_merge(json_decode($m['ev_olaylar'] ?? '[]', true) ?: [], json_decode($m['dep_olaylar'] ?? '[]', true) ?: []);
    foreach ($olaylar as $o) {
        if (strtolower($o['tip'] ?? 'gol') == 'gol') {
            $g = trim($o['oyuncu'] ?? ''); if ($g && $g != 'Bilinmeyen Oyuncu') $goller[$g] = ($goller[$g] ?? 0) + 1;
            $a = trim($o['asist'] ?? '-'); if ($a && $a != '-') $asistler[$a] = ($asistler[$a] ?? 0) + 1;
        }
    }
}
arsort($goller); arsort($asistler);
$top_gol = array_slice($goller, 0, 10, true);
$top_asist = array_slice($asistler, 0, 10, true);

// Form hesapla
$takim_form = [];
$form_maclar = [];
try { $form_maclar = $pdo->query("SELECT ev, dep, ev_skor, dep_skor FROM uecl_maclar WHERE ev_skor IS NOT NULL ORDER BY hafta DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e){}
foreach ($puan_durumu as $t) { $takim_form[$t['id']] = []; }
foreach ($form_maclar as $m) {
    $ev = $m['ev']; $dep = $m['dep'];
    if (isset($takim_form[$ev]) && count($takim_form[$ev]) < 5) {
        $takim_form[$ev][] = ($m['ev_skor'] > $m['dep_skor']) ? 'W' : (($m['ev_skor'] == $m['dep_skor']) ? 'D' : 'L');
    }
    if (isset($takim_form[$dep]) && count($takim_form[$dep]) < 5) {
        $takim_form[$dep][] = ($m['dep_skor'] > $m['ev_skor']) ? 'W' : (($m['ev_skor'] == $m['dep_skor']) ? 'D' : 'L');
    }
}
foreach ($takim_form as $id => $f) { $takim_form[$id] = array_reverse($f); }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UECL Puan Durumu</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --uecl-green:#2ecc71; --uecl-dark:#021a08; --uecl-panel:#08180a; --uecl-border:rgba(46,204,113,0.2); --uecl-accent:#27ae60; --gold:#d4af37; }
        body { background:var(--uecl-dark); color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(8,24,10,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--uecl-border); position:sticky; top:0; z-index:1000; padding:0 2rem; height:70px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:700; color:#fff; text-decoration:none; }
        .nav-links a { color:#ccc; font-size:0.9rem; padding:8px 14px; text-decoration:none; border-radius:8px; transition:0.2s; }
        .nav-links a:hover, .nav-links a.active { background:rgba(46,204,113,0.15); color:var(--uecl-accent); }
        .panel { background:var(--uecl-panel); border:1px solid var(--uecl-border); border-radius:16px; padding:24px; margin-bottom:24px; }
        .panel-title { font-family:'Oswald',sans-serif; font-size:1.1rem; font-weight:700; color:var(--uecl-accent); letter-spacing:2px; text-transform:uppercase; margin-bottom:16px; padding-bottom:12px; border-bottom:1px solid var(--uecl-border); }
        .puan-tbl { width:100%; border-collapse:collapse; }
        .puan-tbl th { font-size:0.75rem; color:#666; letter-spacing:1px; text-transform:uppercase; padding:8px 10px; border-bottom:1px solid var(--uecl-border); text-align:center; }
        .puan-tbl th:first-child, .puan-tbl th:nth-child(2) { text-align:left; }
        .puan-tbl td { padding:10px; font-size:0.88rem; border-bottom:1px solid rgba(255,255,255,0.03); text-align:center; }
        .puan-tbl td:nth-child(2) { text-align:left; }
        .puan-tbl tr:hover td { background:rgba(46,204,113,0.05); }
        .puan-tbl tr.kullanici-satir td { background:rgba(46,204,113,0.12); border-left:3px solid var(--uecl-green); }
        .sira-badge { width:28px; height:28px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:0.8rem; }
        .sira-ko { background:rgba(46,204,113,0.2); color:var(--uecl-accent); border:1px solid rgba(46,204,113,0.4); }
        .sira-playoff { background:rgba(212,175,55,0.15); color:var(--gold); border:1px solid rgba(212,175,55,0.3); }
        .sira-elendi { background:rgba(255,255,255,0.05); color:#666; }
        .form-badge { display:inline-block; width:20px; height:20px; border-radius:50%; font-size:0.65rem; font-weight:800; line-height:20px; text-align:center; }
        .form-W { background:#22c55e; color:#fff; }
        .form-D { background:#f59e0b; color:#fff; }
        .form-L { background:#ef4444; color:#fff; }
        .stat-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.05); font-size:0.88rem; }
        .stat-pos { font-weight:700; color:var(--uecl-accent); width:20px; }
        .stat-name { flex:1; }
        .stat-val { font-weight:800; color:#fff; }
        .legend { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:16px; font-size:0.78rem; }
        .legend-item { display:flex; align-items:center; gap:6px; }
        .legend-dot { width:10px; height:10px; border-radius:50%; }
    </style>
</head>
<body>
<nav class="pro-navbar">
    <a href="uecl.php" class="nav-brand font-oswald"><i class="fa-solid fa-fire" style="color:var(--uecl-accent);"></i>&nbsp;CONFERENCE LEAGUE</a>
    <div class="nav-links d-flex gap-2">
        <a href="uecl.php"><i class="fa-solid fa-calendar-days"></i> Fikstür</a>
        <a href="uecl_puan.php" class="active"><i class="fa-solid fa-table"></i> Puan Durumu</a>
        <a href="../champions_league/cl_uefa.php"><i class="fa-solid fa-globe"></i> Ülke Puanları</a>
        <a href="../takvim.php"><i class="fa-solid fa-clock"></i> Takvim</a>
        <a href="../index.php"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
    </div>
</nav>

<div class="container-fluid py-4 px-4">
    <h2 class="font-oswald mb-4" style="color:var(--uecl-accent);font-size:2rem;"><i class="fa-solid fa-ranking-star me-2"></i>CONFERENCE LEAGUE PUAN DURUMU — <?= $sezon_yili ?>/<?= $sezon_yili+1 ?></h2>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-table me-2"></i>Genel Sıralama</div>
                <div class="legend">
                    <div class="legend-item"><div class="legend-dot" style="background:var(--uecl-accent);"></div><span style="color:#999;">Son 16 (1-16. sıra)</span></div>
                    <div class="legend-item"><div class="legend-dot" style="background:var(--gold);"></div><span style="color:#999;">Play-off (17-24. sıra)</span></div>
                    <div class="legend-item"><div class="legend-dot" style="background:#666;"></div><span style="color:#999;">Elendi (25-32. sıra)</span></div>
                </div>
                <table class="puan-tbl">
                    <thead><tr>
                        <th>#</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AY</th><th>YY</th><th>AV</th><th>P</th><th>Form</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($puan_durumu as $i => $t): ?>
                        <?php
                        $sira = $i + 1;
                        $sinif = ($sira <= 16) ? 'sira-ko' : (($sira <= 24) ? 'sira-playoff' : 'sira-elendi');
                        $oynadigi = $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'];
                        $av = $t['atilan_gol'] - $t['yenilen_gol'];
                        ?>
                        <tr class="<?= ($kullanici_takim_id == $t['id']) ? 'kullanici-satir' : '' ?>">
                            <td><span class="sira-badge <?= $sinif ?>"><?= $sira ?></span></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= htmlspecialchars($t['logo'] ?? '') ?>" style="width:24px;height:24px;object-fit:contain;" onerror="this.style.display='none'">
                                    <span class="fw-semibold"><?= htmlspecialchars($t['takim_adi']) ?></span>
                                    <?php if ($kullanici_takim_id == $t['id']): ?>
                                    <span class="badge" style="background:rgba(46,204,113,0.2);color:var(--uecl-accent);font-size:0.65rem;">SEN</span>
                                    <?php endif; ?>
                                    <span class="text-muted ms-1" style="font-size:0.72rem;"><?= htmlspecialchars($t['lig'] ?? '') ?></span>
                                </div>
                            </td>
                            <td><?= $oynadigi ?></td>
                            <td><?= $t['galibiyet'] ?></td>
                            <td><?= $t['beraberlik'] ?></td>
                            <td><?= $t['malubiyet'] ?></td>
                            <td><?= $t['atilan_gol'] ?></td>
                            <td><?= $t['yenilen_gol'] ?></td>
                            <td style="color:<?= $av >= 0 ? '#22c55e' : '#ef4444' ?>;"><?= ($av > 0 ? '+' : '') . $av ?></td>
                            <td><strong style="color:var(--uecl-accent);"><?= $t['puan'] ?></strong></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <?php foreach ($takim_form[$t['id']] ?? [] as $f): ?>
                                    <span class="form-badge form-<?= $f ?>"><?= $f ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- GOL KRALLARI -->
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-futbol me-2"></i>Gol Krallığı</div>
                <?php if (empty($top_gol)): ?>
                    <div class="text-muted text-center py-3">Henüz gol atılmadı.</div>
                <?php else: ?>
                    <?php $gi = 1; foreach ($top_gol as $isim => $sayi): ?>
                    <div class="stat-row">
                        <span class="stat-pos"><?= $gi++ ?>.</span>
                        <span class="stat-name"><?= htmlspecialchars($isim) ?></span>
                        <span class="stat-val"><?= $sayi ?> <span style="color:#666;font-weight:400;font-size:0.75rem;">gol</span></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ASİST KRALLARI -->
            <div class="panel">
                <div class="panel-title"><i class="fa-solid fa-shoe-prints me-2"></i>Asist Krallığı</div>
                <?php if (empty($top_asist)): ?>
                    <div class="text-muted text-center py-3">Henüz asist yapılmadı.</div>
                <?php else: ?>
                    <?php $ai = 1; foreach ($top_asist as $isim => $sayi): ?>
                    <div class="stat-row">
                        <span class="stat-pos"><?= $ai++ ?>.</span>
                        <span class="stat-name"><?= htmlspecialchars($isim) ?></span>
                        <span class="stat-val"><?= $sayi ?> <span style="color:#666;font-weight:400;font-size:0.75rem;">asist</span></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
