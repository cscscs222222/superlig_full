<?php
// ==============================================================================
// GLOBAL TAKVİM - TÜM LİGLER VE AVRUPA KUPALARININ HAFTALIK TAKVİMİ
// ==============================================================================
include 'db.php';

// Her ligin mevcut haftasını çek
$ligler_durumu = [];

// Süper Lig
try {
    $sl = $pdo->query("SELECT hafta, sezon_yil FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($sl) $ligler_durumu[] = ['adi' => 'Süper Lig', 'url' => 'super_lig/superlig.php', 'hafta' => $sl['hafta'], 'max_hafta' => 38, 'sezon' => $sl['sezon_yil'], 'renk' => '#e11d48', 'ikon' => 'fa-moon', 'kod' => 'TR'];
} catch(Throwable $e) {}

// Premier Lig
try {
    $pl = $pdo->query("SELECT hafta, sezon_yil FROM pl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($pl) $ligler_durumu[] = ['adi' => 'Premier League', 'url' => 'premier_lig/premier_lig.php', 'hafta' => $pl['hafta'], 'max_hafta' => 38, 'sezon' => $pl['sezon_yil'], 'renk' => '#a855f7', 'ikon' => 'fa-crown', 'kod' => 'EN'];
} catch(Throwable $e) {}

// La Liga
try {
    $ll = $pdo->query("SELECT hafta, sezon_yil FROM es_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($ll) $ligler_durumu[] = ['adi' => 'La Liga', 'url' => 'la_liga/la_liga.php', 'hafta' => $ll['hafta'], 'max_hafta' => 38, 'sezon' => $ll['sezon_yil'], 'renk' => '#f59e0b', 'ikon' => 'fa-sun', 'kod' => 'ES'];
} catch(Throwable $e) {}

// Bundesliga
try {
    $bl = $pdo->query("SELECT hafta, sezon_yil FROM de_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($bl) $ligler_durumu[] = ['adi' => 'Bundesliga', 'url' => 'bundesliga/bundesliga.php', 'hafta' => $bl['hafta'], 'max_hafta' => 34, 'sezon' => $bl['sezon_yil'], 'renk' => '#ef4444', 'ikon' => 'fa-bolt', 'kod' => 'DE'];
} catch(Throwable $e) {}

// Serie A
try {
    $sa = $pdo->query("SELECT hafta, sezon_yil FROM it_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($sa) $ligler_durumu[] = ['adi' => 'Serie A', 'url' => 'serie_a/serie_a.php', 'hafta' => $sa['hafta'], 'max_hafta' => 38, 'sezon' => $sa['sezon_yil'], 'renk' => '#10b981', 'ikon' => 'fa-shield-halved', 'kod' => 'IT'];
} catch(Throwable $e) {}

// Champions League
try {
    $cl = $pdo->query("SELECT hafta, sezon_yil FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($cl) $ligler_durumu[] = ['adi' => 'Champions League', 'url' => 'champions_league/cl.php', 'hafta' => $cl['hafta'], 'max_hafta' => 17, 'sezon' => $cl['sezon_yil'], 'renk' => '#00e5ff', 'ikon' => 'fa-trophy', 'kod' => 'UCL'];
} catch(Throwable $e) {}

// Europa League
try {
    $uel = $pdo->query("SELECT hafta, sezon_yil FROM uel_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($uel) {
        $uel_takimlar = (int)$pdo->query("SELECT COUNT(*) FROM uel_takimlar")->fetchColumn();
        $ligler_durumu[] = ['adi' => 'Europa League', 'url' => 'uel/uel.php', 'hafta' => $uel['hafta'], 'max_hafta' => 15, 'sezon' => $uel['sezon_yil'], 'renk' => '#f04e23', 'ikon' => 'fa-fire', 'kod' => 'UEL', 'takim_sayisi' => $uel_takimlar];
    }
} catch(Throwable $e) {}

// Conference League
try {
    $uecl = $pdo->query("SELECT hafta, sezon_yil FROM uecl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($uecl) {
        $uecl_takimlar = (int)$pdo->query("SELECT COUNT(*) FROM uecl_takimlar")->fetchColumn();
        $ligler_durumu[] = ['adi' => 'Conference League', 'url' => 'uecl/uecl.php', 'hafta' => $uecl['hafta'], 'max_hafta' => 15, 'sezon' => $uecl['sezon_yil'], 'renk' => '#2ecc71', 'ikon' => 'fa-earth-europe', 'kod' => 'UECL', 'takim_sayisi' => $uecl_takimlar];
    }
} catch(Throwable $e) {}

// UEFA Katsayıları
$ulke_puanlari = [];
try {
    $ulke_puanlari = $pdo->query("SELECT * FROM uefa_coefficients ORDER BY toplam_puan DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {
    try {
        $ulke_puanlari = $pdo->query("SELECT ulke_adi, toplam_puan as toplam_puan, guncel_sezon_puan as sezon_puan FROM uefa_siralamasi ORDER BY toplam_puan DESC")->fetchAll(PDO::FETCH_ASSOC);
    } catch(Throwable $e2) {}
}

// Super Cup durumu
$super_cup = null;
try {
    $super_cup = $pdo->query("SELECT * FROM super_cup_maclar ORDER BY sezon_yil DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// Tournaments tablosu
$son_sampiyon = [];
try {
    $row_ucl = $pdo->query("SELECT * FROM tournaments WHERE turnuva='UCL' ORDER BY sezon_yil DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row_ucl) $son_sampiyon['UCL'] = $row_ucl;
    $row_uel = $pdo->query("SELECT * FROM tournaments WHERE turnuva='UEL' ORDER BY sezon_yil DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row_uel) $son_sampiyon['UEL'] = $row_uel;
} catch(Throwable $e) {}

// Ortalama global hafta (tüm liglerin ortalaması)
$lig_only = array_filter($ligler_durumu, fn($l) => in_array($l['kod'], ['TR','EN','ES','DE','IT']));
$ortalama_hafta = count($lig_only) > 0 ? round(array_sum(array_column($lig_only, 'hafta')) / count($lig_only)) : 1;
$ortalama_sezon = $ligler_durumu[0]['sezon'] ?? date('Y');
$sezon_ilerleme = count($lig_only) > 0 ? round(array_sum(array_map(fn($l) => ($l['hafta'] / $l['max_hafta']), $lig_only)) / count($lig_only) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Takvim | Ultimate Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold:#d4af37; --bg:#050505; --panel:rgba(255,255,255,0.04); --border:rgba(255,255,255,0.08); }
        body { background:var(--bg); color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(5,5,5,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; padding:0 2rem; height:70px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:700; color:#fff; text-decoration:none; }
        .nav-brand i { color:var(--gold); }
        .nav-links a { color:#ccc; font-size:0.9rem; padding:8px 14px; text-decoration:none; border-radius:8px; transition:0.2s; }
        .nav-links a:hover { background:rgba(212,175,55,0.1); color:var(--gold); }
        .hero-section { text-align:center; padding:50px 20px 30px; }
        .hero-title { font-size:3rem; font-weight:900; color:#fff; margin:0; }
        .hero-sub { color:#666; font-size:0.95rem; letter-spacing:2px; margin-top:8px; }
        .global-stats { display:flex; justify-content:center; gap:32px; flex-wrap:wrap; margin:24px 0; }
        .global-stat { background:var(--panel); border:1px solid var(--border); border-radius:12px; padding:16px 28px; text-align:center; }
        .global-stat-val { font-family:'Oswald',sans-serif; font-size:2.2rem; font-weight:900; color:var(--gold); }
        .global-stat-lbl { font-size:0.72rem; color:#555; text-transform:uppercase; letter-spacing:1.5px; margin-top:4px; }
        .section-title { font-family:'Oswald',sans-serif; font-size:1.1rem; color:#555; letter-spacing:2px; text-transform:uppercase; margin-bottom:16px; padding-bottom:10px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px; }
        .lig-kart { background:var(--panel); border:1px solid var(--border); border-radius:16px; padding:20px; text-decoration:none; color:#fff; display:block; transition:0.3s; position:relative; overflow:hidden; }
        .lig-kart:hover { transform:translateY(-4px); color:#fff; background:rgba(255,255,255,0.07); }
        .lig-kart .lig-accent-bar { position:absolute; left:0; top:0; bottom:0; width:4px; border-radius:16px 0 0 16px; }
        .lig-adi { font-family:'Oswald',sans-serif; font-size:1.3rem; font-weight:800; }
        .lig-hafta-text { font-size:0.8rem; color:#666; margin-top:4px; }
        .hafta-progress { height:6px; background:rgba(255,255,255,0.06); border-radius:3px; margin-top:12px; overflow:hidden; }
        .hafta-bar { height:100%; border-radius:3px; transition:width 0.4s ease; }
        .avrupa-badge { font-size:0.65rem; font-weight:800; padding:3px 8px; border-radius:20px; letter-spacing:1px; text-transform:uppercase; vertical-align:middle; display:inline-flex; align-items:center; gap:4px; }
        .sezon-badge { background:rgba(255,255,255,0.06); color:#666; border:1px solid var(--border); border-radius:20px; padding:3px 10px; font-size:0.72rem; }
        .coefficient-tbl { width:100%; border-collapse:collapse; }
        .coefficient-tbl th { font-size:0.72rem; color:#444; text-transform:uppercase; letter-spacing:1px; padding:8px 10px; border-bottom:1px solid var(--border); text-align:left; }
        .coefficient-tbl td { padding:10px; font-size:0.88rem; border-bottom:1px solid rgba(255,255,255,0.03); }
        .coefficient-tbl tr:hover td { background:rgba(212,175,55,0.04); }
        .coef-bar-bg { background:rgba(255,255,255,0.05); border-radius:3px; height:6px; margin-top:4px; }
        .coef-bar-fill { height:6px; border-radius:3px; }
        .star-badge { color:var(--gold); font-size:0.75rem; }
        .kota-pill { font-size:0.65rem; font-weight:700; padding:2px 7px; border-radius:10px; margin-left:4px; }
        .kota-ucl { background:rgba(0,229,255,0.15); color:#00e5ff; }
        .kota-uel { background:rgba(240,78,35,0.15); color:#f04e23; }
        .kota-uecl { background:rgba(46,204,113,0.15); color:#2ecc71; }
        .super-cup-card { background:linear-gradient(135deg,rgba(212,175,55,0.08),rgba(0,0,0,0.4)); border:1px solid rgba(212,175,55,0.25); border-radius:16px; padding:20px; }
        .takvim-header { background:rgba(212,175,55,0.05); border:1px solid rgba(212,175,55,0.1); border-radius:12px; padding:16px 24px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
        .global-hafta-badge { background:linear-gradient(135deg,rgba(212,175,55,0.2),rgba(212,175,55,0.05)); border:1px solid rgba(212,175,55,0.3); color:var(--gold); border-radius:24px; padding:6px 18px; font-family:'Oswald',sans-serif; font-weight:700; font-size:1rem; }
        .sezon-progress-wrap { margin:24px 0 8px; background:rgba(255,255,255,0.05); border-radius:8px; height:10px; overflow:hidden; }
        .sezon-progress-bar { height:10px; background:linear-gradient(90deg,var(--gold),#997a00); border-radius:8px; transition:width 0.5s; }
    </style>
</head>
<body>

<nav class="pro-navbar">
    <a href="index.php" class="nav-brand font-oswald"><i class="fa-solid fa-chess-knight"></i>&nbsp;ULTIMATE MANAGER</a>
    <div class="nav-links d-flex gap-2">
        <a href="index.php"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
        <a href="champions_league/cl.php"><i class="fa-solid fa-trophy"></i> UCL</a>
        <a href="uel/uel.php"><i class="fa-solid fa-fire"></i> UEL</a>
        <a href="uecl/uecl.php"><i class="fa-solid fa-earth-europe"></i> UECL</a>
        <a href="super_cup/super_cup.php"><i class="fa-solid fa-star"></i> Süper Kupa</a>
        <a href="champions_league/cl_uefa.php"><i class="fa-solid fa-globe"></i> Ülke Puanları</a>
    </div>
</nav>

<div class="hero-section">
    <h1 class="hero-title font-oswald"><i class="fa-solid fa-clock me-3" style="color:var(--gold);"></i>GLOBAL TAKVİM</h1>
    <p class="hero-sub">TÜM LİGLER VE AVRUPA KUPALARININ HAFTALIK DURUMU</p>
</div>

<div class="container-fluid px-4 pb-5">

    <!-- GLOBAL İSTATİSTİK -->
    <div class="global-stats">
        <div class="global-stat">
            <div class="global-stat-val"><?= $ortalama_sezon ?></div>
            <div class="global-stat-lbl">Aktif Sezon</div>
        </div>
        <div class="global-stat">
            <div class="global-stat-val"><?= $ortalama_hafta ?></div>
            <div class="global-stat-lbl">Ortalama Hafta</div>
        </div>
        <div class="global-stat">
            <div class="global-stat-val"><?= count($ligler_durumu) ?></div>
            <div class="global-stat-lbl">Aktif Turnuva</div>
        </div>
        <div class="global-stat">
            <div class="global-stat-val"><?= $sezon_ilerleme ?>%</div>
            <div class="global-stat-lbl">Sezon Tamamlandı</div>
        </div>
    </div>

    <!-- SEZON İLERLEME BARI -->
    <div class="container" style="max-width:700px;">
        <div class="d-flex justify-content-between" style="font-size:0.75rem;color:#444;margin-bottom:4px;">
            <span>SEZON BAŞI</span>
            <span><?= $sezon_ilerleme ?>% TAMAMLANDI</span>
            <span>SEZON SONU</span>
        </div>
        <div class="sezon-progress-wrap">
            <div class="sezon-progress-bar" style="width:<?= $sezon_ilerleme ?>%;"></div>
        </div>
    </div>

    <div class="row g-4 mt-2">

        <!-- LİG TAKVİMİ -->
        <div class="col-lg-7">
            <div class="section-title"><i class="fa-solid fa-list-check"></i> Lig ve Kupa Durumu</div>

            <!-- TAKVIM BAŞLIĞI -->
            <div class="takvim-header">
                <div>
                    <div style="font-size:0.75rem;color:#555;letter-spacing:1px;">KÜRESEL ZAMAN</div>
                    <div class="font-oswald" style="font-size:1.4rem;color:#fff;">Sezon <?= $ortalama_sezon ?>/<?= $ortalama_sezon+1 ?></div>
                </div>
                <span class="global-hafta-badge">HAFTA <?= $ortalama_hafta ?></span>
            </div>

            <!-- LİGLER -->
            <div class="mb-3" style="font-size:0.7rem;color:#444;letter-spacing:1px;text-transform:uppercase;">
                <i class="fa-solid fa-flag me-1"></i>Ulusal Ligler
            </div>
            <div class="row g-3 mb-4">
                <?php foreach ($ligler_durumu as $l):
                    if (!in_array($l['kod'], ['TR','EN','ES','DE','IT'])) continue;
                    $pct = round(($l['hafta'] / max($l['max_hafta'], 1)) * 100);
                    $kalan = $l['max_hafta'] - $l['hafta'] + 1;
                ?>
                <div class="col-md-6">
                    <a href="<?= htmlspecialchars($l['url']) ?>" class="lig-kart">
                        <div class="lig-accent-bar" style="background:<?= $l['renk'] ?>;"></div>
                        <div class="d-flex align-items-center gap-3" style="padding-left:12px;">
                            <i class="fa-solid <?= $l['ikon'] ?>" style="color:<?= $l['renk'] ?>;font-size:1.6rem;width:30px;text-align:center;"></i>
                            <div style="flex:1;">
                                <div class="lig-adi"><?= htmlspecialchars($l['adi']) ?></div>
                                <div class="lig-hafta-text">
                                    Hafta <?= $l['hafta'] ?> / <?= $l['max_hafta'] ?>
                                    &nbsp;·&nbsp; Kalan: <?= max(0, $kalan) ?> hafta
                                </div>
                                <div class="hafta-progress">
                                    <div class="hafta-bar" style="width:<?= $pct ?>%;background:<?= $l['renk'] ?>;opacity:0.7;"></div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <span class="sezon-badge"><?= $l['sezon'] ?></span>
                                <div style="font-size:0.75rem;color:<?= $l['renk'] ?>;margin-top:6px;font-weight:700;"><?= $pct ?>%</div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- AVRUPA KUPALARI -->
            <div class="mb-3" style="font-size:0.7rem;color:#444;letter-spacing:1px;text-transform:uppercase;">
                <i class="fa-solid fa-earth-europe me-1"></i>Avrupa Kupaları
            </div>
            <div class="row g-3">
                <?php foreach ($ligler_durumu as $l):
                    if (!in_array($l['kod'], ['UCL','UEL','UECL'])) continue;
                    $pct = round(($l['hafta'] / max($l['max_hafta'], 1)) * 100);
                    $takim_sayisi = $l['takim_sayisi'] ?? null;
                    $is_active = ($takim_sayisi === null || $takim_sayisi > 0);
                ?>
                <div class="col-md-4">
                    <a href="<?= htmlspecialchars($l['url']) ?>" class="lig-kart <?= !$is_active ? 'opacity-50' : '' ?>">
                        <div class="lig-accent-bar" style="background:<?= $l['renk'] ?>;"></div>
                        <div style="padding-left:12px;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fa-solid <?= $l['ikon'] ?>" style="color:<?= $l['renk'] ?>;font-size:1.4rem;width:26px;text-align:center;"></i>
                                <span class="lig-adi" style="font-size:1rem;"><?= htmlspecialchars($l['adi']) ?></span>
                            </div>
                            <?php if ($is_active): ?>
                            <div class="lig-hafta-text">Hafta <?= $l['hafta'] ?> / <?= $l['max_hafta'] ?></div>
                            <div class="hafta-progress mt-2">
                                <div class="hafta-bar" style="width:<?= $pct ?>%;background:<?= $l['renk'] ?>;opacity:0.8;"></div>
                            </div>
                            <?php else: ?>
                            <div class="lig-hafta-text" style="color:#444;">Takımlar bekleniyor</div>
                            <?php endif; ?>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- UEFA SUPER CUP -->
            <div class="mt-4">
                <div class="section-title"><i class="fa-solid fa-star"></i> UEFA Süper Kupa</div>
                <div class="super-cup-card">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div style="font-size:0.75rem;color:#666;letter-spacing:1px;">UCL ŞAMPİYONU</div>
                            <div class="font-oswald" style="font-size:1.3rem;color:#00e5ff;">
                                <?= isset($son_sampiyon['UCL']) ? htmlspecialchars($son_sampiyon['UCL']['sampiyon_adi']) : '— Henüz Yok —' ?>
                            </div>
                            <?php if (isset($son_sampiyon['UCL'])): ?>
                            <div style="font-size:0.72rem;color:#444;"><?= $son_sampiyon['UCL']['sezon_yil'] ?> sezonu</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2 text-center">
                            <span class="font-oswald" style="color:#333;font-size:1.4rem;">VS</span>
                        </div>
                        <div class="col-md-4">
                            <div style="font-size:0.75rem;color:#666;letter-spacing:1px;">UEL ŞAMPİYONU</div>
                            <div class="font-oswald" style="font-size:1.3rem;color:#f04e23;">
                                <?= isset($son_sampiyon['UEL']) ? htmlspecialchars($son_sampiyon['UEL']['sampiyon_adi']) : '— Henüz Yok —' ?>
                            </div>
                            <?php if (isset($son_sampiyon['UEL'])): ?>
                            <div style="font-size:0.72rem;color:#444;"><?= $son_sampiyon['UEL']['sezon_yil'] ?> sezonu</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (isset($son_sampiyon['UCL']) && isset($son_sampiyon['UEL'])): ?>
                    <div class="mt-3">
                        <a href="super_cup/super_cup.php" class="btn btn-sm" style="background:rgba(212,175,55,0.15);color:var(--gold);border:1px solid rgba(212,175,55,0.3);font-size:0.8rem;font-weight:700;">
                            <i class="fa-solid fa-play me-1"></i> Süper Kupa'ya Git
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($super_cup): ?>
                    <div class="mt-2" style="font-size:0.8rem;color:#555;">
                        Son Süper Kupa: <strong style="color:var(--gold);"><?= htmlspecialchars($super_cup['kazanan']) ?></strong> (<?= $super_cup['ucl_skor'] ?> – <?= $super_cup['uel_skor'] ?>)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- UEFA KATSAYİLAR -->
        <div class="col-lg-5">
            <div class="section-title"><i class="fa-solid fa-globe"></i> UEFA Ülke Katsayıları</div>

            <?php if (!empty($ulke_puanlari)): ?>
            <div style="background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:20px;">
                <p style="font-size:0.78rem;color:#555;margin-bottom:16px;">
                    Ülkelerin Avrupa kupalarındaki performansına göre belirlenen katsayılar, 
                    bir sonraki sezonda kaç takımın hangi kupaya gireceğini belirler.
                </p>
                <?php
                $max_puan = max(array_column($ulke_puanlari, 'toplam_puan'));
                $ulke_bayrak = ['İngiltere'=>'🏴󠁧󠁢󠁥󠁮󠁧󠁿','İspanya'=>'🇪🇸','Almanya'=>'🇩🇪','İtalya'=>'🇮🇹','Fransa'=>'🇫🇷','Türkiye'=>'🇹🇷','Portekiz'=>'🇵🇹'];
                foreach ($ulke_puanlari as $i => $up):
                    $sira = $i + 1;
                    $pct = $max_puan > 0 ? round(($up['toplam_puan'] / $max_puan) * 100) : 0;
                    $renk_str = ['İngiltere'=>'#a855f7','İspanya'=>'#f59e0b','Almanya'=>'#ef4444','İtalya'=>'#10b981','Fransa'=>'#3b82f6','Türkiye'=>'#e11d48','Portekiz'=>'#f97316'];
                    $renk = $renk_str[$up['ulke_adi']] ?? '#666';
                    $bayrak = $ulke_bayrak[$up['ulke_adi']] ?? '🌍';
                    // Kota bilgisi
                    $ucl_kota = $up['ucl_kota'] ?? ($sira <= 4 ? 4 : ($sira <= 6 ? 3 : 2));
                    $uel_kota = $up['uel_kota'] ?? 2;
                    $uecl_kota = $up['uecl_kota'] ?? 1;
                ?>
                <div style="margin-bottom:18px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-family:'Oswald',sans-serif;font-size:0.9rem;color:#333;width:18px;text-align:right;"><?= $sira ?>.</span>
                            <span style="font-size:1.1rem;"><?= $bayrak ?></span>
                            <span style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($up['ulke_adi']) ?></span>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-family:'Oswald',sans-serif;font-size:1rem;font-weight:900;color:<?= $renk ?>;"><?= number_format((float)$up['toplam_puan'], 3) ?></span>
                        </div>
                    </div>
                    <div class="coef-bar-bg">
                        <div class="coef-bar-fill" style="width:<?= $pct ?>%;background:<?= $renk ?>;opacity:0.7;"></div>
                    </div>
                    <div style="display:flex;gap:4px;margin-top:5px;align-items:center;">
                        <span class="kota-pill kota-ucl" title="Champions League kotası"><?= $ucl_kota ?>×UCL</span>
                        <span class="kota-pill kota-uel" title="Europa League kotası"><?= $uel_kota ?>×UEL</span>
                        <span class="kota-pill kota-uecl" title="Conference League kotası"><?= $uecl_kota ?>×UECL</span>
                        <?php if (!empty($up['sezon_puan']) && $up['sezon_puan'] > 0): ?>
                        <span style="font-size:0.65rem;color:#22c55e;margin-left:4px;">+<?= number_format((float)$up['sezon_puan'], 3) ?> bu sezon</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);font-size:0.72rem;color:#444;line-height:1.7;">
                    <strong style="color:#555;">Kota Sistemi:</strong><br>
                    • Her galibiyet: +0.3 puan (UCL), +0.3 (UEL), +0.2 (UECL)<br>
                    • Şampiyonluk bonusu: UCL +5.0, UEL +2.0, UECL +1.0<br>
                    • Üst 4 ülke: 4 UCL kotası | 5-6. ülke: 3 UCL kotası
                </div>
            </div>
            <?php else: ?>
            <div style="background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:40px;text-align:center;">
                <i class="fa-solid fa-globe" style="font-size:2.5rem;color:#333;margin-bottom:12px;"></i>
                <div style="color:#444;">UEFA katsayı verisi henüz yüklenmedi.</div>
                <div class="mt-3">
                    <a href="champions_league/cl_uefa.php" style="color:#555;font-size:0.85rem;">→ UEFA Sayfasına Git</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- KATILıM REHBERİ -->
            <div class="mt-3" style="background:var(--panel);border:1px solid var(--border);border-radius:16px;padding:20px;">
                <div class="section-title" style="font-size:0.9rem;"><i class="fa-solid fa-info-circle"></i> Avrupa'ya Nasıl Gidilir?</div>
                <div style="font-size:0.82rem;line-height:2;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:#00e5ff;width:28px;text-align:right;font-weight:700;font-family:'Oswald',sans-serif;">1-2.</span>
                        <span>→</span>
                        <span>🏆 <strong style="color:#00e5ff;">Champions League</strong> (Grup Aşaması)</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:#888;width:28px;text-align:right;font-weight:700;font-family:'Oswald',sans-serif;">3.</span>
                        <span>→</span>
                        <span>🏆 <strong style="color:#888;">Champions League</strong> (Eleme)</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:#f04e23;width:28px;text-align:right;font-weight:700;font-family:'Oswald',sans-serif;">5-6.</span>
                        <span>→</span>
                        <span>🔥 <strong style="color:#f04e23;">Europa League</strong></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="color:#2ecc71;width:28px;text-align:right;font-weight:700;font-family:'Oswald',sans-serif;">7-8.</span>
                        <span>→</span>
                        <span>🌍 <strong style="color:#2ecc71;">Conference League</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
