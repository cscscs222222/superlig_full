<?php
// ==============================================================================
// GLOBAL HAFTA OYNATICI - TÜM LİGLERİ EŞZAMANLI İLERLET
// Dünya genelindeki tüm ligleri ve Avrupa kupalarını tek seferde simüle eder.
// ==============================================================================
include 'db.php';
include 'MatchEngine.php';

set_time_limit(300); // Sezon simülasyonu için yeterli süre

// ================================================================
// YARDIMCI FONKSİYON: Tek bir lig haftasını simüle et
// ================================================================
function simulate_league_week(
    $pdo, $engine,
    string $maclar_tbl,
    string $takimlar_tbl,
    string $ayar_tbl,
    int    $hafta,
    int    $max_hafta,
    int    $kullanici_takim_id = 0
): int {
    $simulated = 0;
    try {
        $stmt = $pdo->prepare(
            "SELECT m.id, m.ev, m.dep
               FROM $maclar_tbl m
              WHERE m.hafta = ? AND m.ev_skor IS NULL"
        );
        $stmt->execute([$hafta]);
        $maclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return 0;
    }

    foreach ($maclar as $m) {
        // Kullanıcı takımının maçını otomatik simüle etme (oyuncu kendisi oynasın)
        if ($kullanici_takim_id && ($m['ev'] == $kullanici_takim_id || $m['dep'] == $kullanici_takim_id)) {
            continue;
        }
        try {
            $skorlar  = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor  = $skorlar['ev'];
            $dep_skor = $skorlar['dep'];
            $ev_det   = $engine->mac_olay_uret($m['ev'],  $ev_skor);
            $dep_det  = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $pdo->prepare(
                "UPDATE $maclar_tbl
                    SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=?
                  WHERE id=?"
            )->execute([
                $ev_skor, $dep_skor,
                $ev_det['olaylar'],  $dep_det['olaylar'],
                $ev_det['kartlar'],  $dep_det['kartlar'],
                $m['id'],
            ]);

            $ev_id  = (int)$m['ev'];
            $dep_id = (int)$m['dep'];
            $ev_s   = (int)$ev_skor;
            $dep_s  = (int)$dep_skor;

            $pdo->exec("UPDATE $takimlar_tbl SET atilan_gol = atilan_gol + $ev_s,  yenilen_gol = yenilen_gol + $dep_s WHERE id = $ev_id");
            $pdo->exec("UPDATE $takimlar_tbl SET atilan_gol = atilan_gol + $dep_s, yenilen_gol = yenilen_gol + $ev_s  WHERE id = $dep_id");

            if ($ev_s > $dep_s) {
                $pdo->exec("UPDATE $takimlar_tbl SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=$ev_id");
                $pdo->exec("UPDATE $takimlar_tbl SET malubiyet=malubiyet+1 WHERE id=$dep_id");
            } elseif ($ev_s === $dep_s) {
                $pdo->exec("UPDATE $takimlar_tbl SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ($ev_id,$dep_id)");
            } else {
                $pdo->exec("UPDATE $takimlar_tbl SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=$dep_id");
                $pdo->exec("UPDATE $takimlar_tbl SET malubiyet=malubiyet+1 WHERE id=$ev_id");
            }
            $simulated++;
        } catch (Throwable $e) {}
    }

    // Hafta bittiyse sayacı artır
    try {
        $hafta_int    = (int)$hafta;
        $max_hafta_int = (int)$max_hafta;
        $kalan = $pdo->query("SELECT COUNT(*) FROM $maclar_tbl WHERE hafta=$hafta_int AND ev_skor IS NULL")->fetchColumn();
        if ($kalan == 0 && $hafta_int < $max_hafta_int) {
            $pdo->exec("UPDATE $ayar_tbl SET hafta = hafta + 1");
        }
    } catch (Throwable $e) {}

    return $simulated;
}

// ================================================================
// LİG / TURNUVA TANIMI
// ================================================================
$ligler = [
    ['prefix' => '',     'maclar' => 'maclar',     'takimlar' => 'takimlar',     'ayar' => 'ayar',     'max' => 38, 'ad' => 'Süper Lig'],
    ['prefix' => 'pl_',  'maclar' => 'pl_maclar',  'takimlar' => 'pl_takimlar',  'ayar' => 'pl_ayar',  'max' => 38, 'ad' => 'Premier League'],
    ['prefix' => 'es_',  'maclar' => 'es_maclar',  'takimlar' => 'es_takimlar',  'ayar' => 'es_ayar',  'max' => 38, 'ad' => 'La Liga'],
    ['prefix' => 'de_',  'maclar' => 'de_maclar',  'takimlar' => 'de_takimlar',  'ayar' => 'de_ayar',  'max' => 34, 'ad' => 'Bundesliga'],
    ['prefix' => 'it_',  'maclar' => 'it_maclar',  'takimlar' => 'it_takimlar',  'ayar' => 'it_ayar',  'max' => 38, 'ad' => 'Serie A'],
    ['prefix' => 'fr_',  'maclar' => 'fr_maclar',  'takimlar' => 'fr_takimlar',  'ayar' => 'fr_ayar',  'max' => 38, 'ad' => 'Ligue 1'],
    ['prefix' => 'cl_',  'maclar' => 'cl_maclar',  'takimlar' => 'cl_takimlar',  'ayar' => 'cl_ayar',  'max' => 17, 'ad' => 'Champions League'],
    ['prefix' => 'uel_', 'maclar' => 'uel_maclar', 'takimlar' => 'uel_takimlar', 'ayar' => 'uel_ayar', 'max' => 15, 'ad' => 'Europa League'],
    ['prefix' => 'uecl_','maclar' => 'uecl_maclar','takimlar' => 'uecl_takimlar','ayar' => 'uecl_ayar','max' => 15, 'ad' => 'Conference League'],
];

// Kullanıcı takımı (Süper Lig ayarından)
$kullanici_takim_id = 0;
try {
    $kullanici_takim_id = (int)$pdo->query("SELECT kullanici_takim_id FROM ayar LIMIT 1")->fetchColumn();
} catch (Throwable $e) {}

// ================================================================
// AKSİYON: GLOBAL HAFTAYI OYNA
// ================================================================
$sonuc_mesajlari = [];

if (isset($_POST['global_hafta_oyna'])) {
    foreach ($ligler as $lig) {
        try {
            $hafta = (int)$pdo->query("SELECT hafta FROM {$lig['ayar']} LIMIT 1")->fetchColumn();
        } catch (Throwable $e) { continue; }

        $engine = new MatchEngine($pdo, $lig['prefix']);
        $simulated = simulate_league_week(
            $pdo, $engine,
            $lig['maclar'], $lig['takimlar'], $lig['ayar'],
            $hafta, $lig['max'], $kullanici_takim_id
        );
        if ($simulated > 0) {
            $sonuc_mesajlari[] = "<strong>{$lig['ad']}</strong>: {$simulated} maç oynandı (Hafta {$hafta})";
        }
    }
    // Bildirim için session
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['play_week_sonuc'] = $sonuc_mesajlari;
    header("Location: play_week.php?done=1");
    exit;
}

// ================================================================
// AKSİYON: TÜM SEZONU SİMÜLE ET
// ================================================================
if (isset($_POST['tum_sezonu_simule'])) {
    foreach ($ligler as $lig) {
        try {
            $hafta_row = $pdo->query("SELECT hafta FROM {$lig['ayar']} LIMIT 1")->fetchColumn();
            $hafta_baslangic = (int)$hafta_row;
        } catch (Throwable $e) { continue; }

        $engine = new MatchEngine($pdo, $lig['prefix']);
        for ($h = $hafta_baslangic; $h <= $lig['max']; $h++) {
            simulate_league_week(
                $pdo, $engine,
                $lig['maclar'], $lig['takimlar'], $lig['ayar'],
                $h, $lig['max'], 0  // Kullanıcı takımı dahil her maç simüle edilir
            );
        }
        // Hafta sayacını sona al
        try {
            $max_son = (int)$lig['max'];
            $pdo->exec("UPDATE {$lig['ayar']} SET hafta = $max_son");
        } catch (Throwable $e) {}
    }
    // Sezon sonu ekranına yönlendir (Super Lig sezon geçişi)
    header("Location: super_lig/sezon_gecisi.php");
    exit;
}

// ================================================================
// VERİ ÇEKİMİ (MEVCUT DURUM)
// ================================================================
$durum_listesi = [];
foreach ($ligler as $lig) {
    try {
        $row = $pdo->query("SELECT hafta, sezon_yil FROM {$lig['ayar']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $kalan = (int)$pdo->query(
            "SELECT COUNT(*) FROM {$lig['maclar']} WHERE hafta={$row['hafta']} AND ev_skor IS NULL"
        )->fetchColumn();
        $toplam = (int)$pdo->query(
            "SELECT COUNT(*) FROM {$lig['maclar']} WHERE hafta={$row['hafta']}"
        )->fetchColumn();
        $durum_listesi[] = [
            'ad'     => $lig['ad'],
            'hafta'  => (int)$row['hafta'],
            'max'    => $lig['max'],
            'sezon'  => (int)$row['sezon_yil'],
            'kalan'  => $kalan,
            'toplam' => $toplam,
            'tamam'  => $toplam > 0 && $kalan === 0,
        ];
    } catch (Throwable $e) {}
}

// Session mesajları
if (session_status() === PHP_SESSION_NONE) session_start();
$flash = $_SESSION['play_week_sonuc'] ?? [];
unset($_SESSION['play_week_sonuc']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Global Hafta Oynat | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root { --gold:#d4af37; --bg:#050505; --panel:rgba(255,255,255,0.05); --border:rgba(255,255,255,0.1); }
    body { background:var(--bg); color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    /* Navbar */
    .pro-navbar { background:rgba(5,5,5,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; padding:0 2rem; height:68px; display:flex; align-items:center; justify-content:space-between; }
    .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:700; color:#fff; text-decoration:none; }
    .nav-brand i { color:var(--gold); }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.88rem; display:flex; align-items:center; gap:6px; }
    .back-btn:hover { color:#fff; }
    /* Hero */
    .hero { text-align:center; padding:52px 20px 28px; }
    .hero h1 { font-size:2.8rem; font-weight:900; line-height:1.1; margin-bottom:8px; }
    .hero p { color:#94a3b8; font-size:0.95rem; letter-spacing:1px; }
    .gold { color:var(--gold); }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    /* Glass panels */
    .glass { background:var(--panel); border:1px solid var(--border); border-radius:18px; padding:24px; }
    /* League status cards */
    .lig-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px; margin-bottom:32px; }
    .lig-kart { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:18px 20px; position:relative; }
    .lig-kart.bitti { border-color:rgba(16,185,129,0.3); }
    .lig-ad { font-family:'Oswald',sans-serif; font-size:1.15rem; font-weight:700; }
    .lig-meta { font-size:0.78rem; color:#94a3b8; margin-top:4px; }
    .lig-bar-bg { height:6px; background:rgba(255,255,255,0.07); border-radius:3px; margin-top:10px; overflow:hidden; }
    .lig-bar { height:6px; border-radius:3px; background:linear-gradient(90deg,var(--gold),#7c5c00); }
    .hafta-pill { font-size:0.7rem; font-weight:700; padding:2px 10px; border-radius:20px; background:rgba(212,175,55,0.15); color:var(--gold); border:1px solid rgba(212,175,55,0.3); white-space:nowrap; }
    .done-badge { position:absolute; top:14px; right:14px; font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:20px; background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.4); }
    /* Action buttons */
    .btn-global { display:block; width:100%; padding:18px; border-radius:14px; font-family:'Oswald',sans-serif; font-size:1.35rem; font-weight:800; letter-spacing:1.5px; text-align:center; cursor:pointer; border:none; transition:all .25s; }
    .btn-play { background:linear-gradient(135deg,#1e3a5f,#2563eb); color:#fff; box-shadow:0 4px 24px rgba(37,99,235,0.4); }
    .btn-play:hover { transform:translateY(-3px); box-shadow:0 8px 32px rgba(37,99,235,0.6); }
    .btn-simulate { background:linear-gradient(135deg,#7f1d1d,#dc2626); color:#fff; box-shadow:0 4px 24px rgba(220,38,38,0.5); margin-top:16px; }
    .btn-simulate:hover { transform:translateY(-3px); box-shadow:0 10px 36px rgba(220,38,38,0.7); }
    .btn-simulate i { font-size:1.3rem; }
    /* Flash messages */
    .flash-box { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:14px; padding:16px 20px; margin-bottom:24px; }
    .flash-item { font-size:0.88rem; color:#d1fae5; padding:3px 0; }
    /* Section label */
    .section-lbl { font-family:'Oswald',sans-serif; font-size:0.85rem; color:#94a3b8; letter-spacing:2px; text-transform:uppercase; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--border); }
</style>
</head>
<body>

<nav class="pro-navbar">
    <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Ana Menü</a>
    <a href="index.php" class="nav-brand font-oswald">
        <i class="fa-solid fa-chess-knight"></i> ULTIMATE <span style="color:var(--gold);">MANAGER</span>
    </a>
    <a href="takvim.php" class="back-btn"><i class="fa-solid fa-calendar-days"></i> Takvim</a>
</nav>

<div class="container pb-5" style="max-width:900px;">

    <!-- HERO -->
    <div class="hero">
        <div style="font-size:3rem; margin-bottom:12px;">🌍</div>
        <h1 class="font-oswald"><span class="gold-gradient">GLOBAL HAFTA OYNAT</span></h1>
        <p>Tek tıkla tüm dünya ligleri ve Avrupa kupaları eşzamanlı ilerler</p>
    </div>

    <?php if (!empty($flash)): ?>
    <div class="flash-box mb-4">
        <div class="fw-bold text-success mb-2"><i class="fa-solid fa-check-circle me-2"></i>Hafta başarıyla oynandı!</div>
        <?php foreach ($flash as $msg): ?>
            <div class="flash-item">• <?= $msg ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- LİG DURUM KARTLARI -->
    <div class="section-lbl"><i class="fa-solid fa-earth-europe me-2"></i>Mevcut Hafta Durumu</div>
    <div class="lig-grid mb-4">
        <?php foreach ($durum_listesi as $d): ?>
        <div class="lig-kart <?= $d['tamam'] ? 'bitti' : '' ?>">
            <?php if ($d['tamam']): ?><div class="done-badge"><i class="fa-solid fa-check me-1"></i>Tamamlandı</div><?php endif; ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="lig-ad"><?= htmlspecialchars($d['ad']) ?></div>
            </div>
            <div class="lig-meta">
                Hafta <?= $d['hafta'] ?> / <?= $d['max'] ?>
                &bull; <?= $d['kalan'] ?> maç kaldı
                &bull; <?= $d['sezon'] ?> Sezonu
            </div>
            <div class="lig-bar-bg">
                <div class="lig-bar" style="width:<?= round($d['hafta'] / $d['max'] * 100) ?>%;"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($durum_listesi)): ?>
        <div class="glass text-center py-4 text-secondary">
            <i class="fa-solid fa-circle-info me-2"></i>Ligler henüz kurulmamış. Lütfen önce ligleri başlatın.
        </div>
        <?php endif; ?>
    </div>

    <!-- EYLEM BUTONLARI -->
    <div class="glass">
        <div class="section-lbl"><i class="fa-solid fa-bolt me-2"></i>Eylemler</div>

        <!-- GLOBAL HAFTAYI OYNA -->
        <form method="POST">
            <button type="submit" name="global_hafta_oyna" class="btn-global btn-play">
                <i class="fa-solid fa-play me-2"></i>
                GLOBAL HAFTAYI OYNA
                <span style="font-size:0.85rem; font-weight:400; display:block; margin-top:4px; letter-spacing:0; text-transform:none; opacity:0.8;">
                    Tüm ligler ve Avrupa kupaları bu haftaki maçları aynı anda oynar
                </span>
            </button>
        </form>

        <!-- TÜM SEZONU SİMÜLE ET -->
        <form method="POST" onsubmit="return confirm('Tüm sezon simüle edilecek! Kullanıcı takımınızın maçları dahil hepsi otomatik oynanacak. Devam edilsin mi?');">
            <button type="submit" name="tum_sezonu_simule" class="btn-global btn-simulate">
                <i class="fa-solid fa-forward-fast me-2"></i>
                TÜM SEZONU SİMÜLE ET
                <span style="font-size:0.85rem; font-weight:400; display:block; margin-top:4px; letter-spacing:0; text-transform:none; opacity:0.85;">
                    Kalan tüm haftalar saniyeler içinde oynanır → Sezon Sonu Ekranına geçilir
                </span>
            </button>
        </form>
    </div>

    <!-- NAVİGASYON -->
    <div class="d-flex gap-3 flex-wrap justify-content-center mt-4">
        <a href="super_lig/superlig.php" class="back-btn" style="color:#e11d48;"><i class="fa-solid fa-moon me-1"></i> Süper Lig</a>
        <a href="premier_lig/premier_lig.php" class="back-btn" style="color:#a855f7;"><i class="fa-solid fa-crown me-1"></i> Premier League</a>
        <a href="la_liga/la_liga.php" class="back-btn" style="color:#f59e0b;"><i class="fa-solid fa-sun me-1"></i> La Liga</a>
        <a href="champions_league/cl.php" class="back-btn" style="color:#00e5ff;"><i class="fa-solid fa-trophy me-1"></i> Champions League</a>
        <a href="takvim.php" class="back-btn" style="color:#94a3b8;"><i class="fa-solid fa-calendar-days me-1"></i> Global Takvim</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
