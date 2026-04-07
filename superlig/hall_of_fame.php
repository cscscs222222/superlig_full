<?php
// ==============================================================================
// FAZ 6: ŞÖHRETLER MÜZESİ / HALL OF FAME
// 200+ maç veya 100+ gol yapan oyuncuları kulüp tarihine kazır.
// Emekli olan / ayrılan efsaneleri hall_of_fame tablosuna ekler.
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

$tbl_oyuncu = [
    'tr' => 'oyuncular',    'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular', 'de' => 'de_oyuncular',
    'it' => 'it_oyuncular', 'fr' => 'fr_oyuncular',
    'pt' => 'pt_oyuncular',
];
$tbl_takim = [
    'tr' => 'takimlar',    'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar',
    'it' => 'it_takimlar', 'fr' => 'fr_takimlar',
    'pt' => 'pt_takimlar',
];
$lig_etiket = [
    'tr' => 'Süper Lig',    'pl' => 'Premier League',
    'es' => 'La Liga',      'de' => 'Bundesliga',
    'it' => 'Serie A',      'fr' => 'Ligue 1',
    'pt' => 'Liga NOS',
];
$ayar_tablosu = [
    'tr' => 'ayar',    'pl' => 'pl_ayar',   'es' => 'es_ayar',
    'de' => 'de_ayar', 'it' => 'it_ayar',   'fr' => 'fr_ayar',
    'pt' => 'pt_ayar',
];

$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// ================================================================
// KULLANICI TAKIMLARINı ÇEK
// ================================================================
function fetch_kullanici_takimi_hof($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    try {
        $id = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1")->fetchColumn();
        if (!$id) return null;
        $stmt = $pdo->prepare("SELECT * FROM $takim_tablo WHERE id = ?");
        $stmt->execute([$id]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t) { $t['kaynak'] = $lig_kodu; return $t; }
    } catch (Throwable $e) {}
    return null;
}

$benim_takimlarim = [];
foreach ($ayar_tablosu as $kod => $ayar_tbl) {
    $t = fetch_kullanici_takimi_hof($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
    if ($t) $benim_takimlarim[] = $t;
}

$aktif_lig   = $_GET['lig'] ?? ($benim_takimlarim[0]['kaynak'] ?? 'tr');
$aktif_takim = null;
foreach ($benim_takimlarim as $t) {
    if ($t['kaynak'] === $aktif_lig) { $aktif_takim = $t; break; }
}
if (!$aktif_takim && !empty($benim_takimlarim)) {
    $aktif_takim = $benim_takimlarim[0];
    $aktif_lig   = $aktif_takim['kaynak'];
}

// ================================================================
// EFSANE ADAY TARAMA (200+ maç veya 100+ gol)
// ================================================================
$efsane_adaylar = [];
if ($aktif_takim) {
    $o_tbl = $tbl_oyuncu[$aktif_lig];
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM $o_tbl
             WHERE takim_id = ?
               AND (COALESCE(toplam_mac, 0) >= 200 OR COALESCE(toplam_gol, 0) >= 100)
             ORDER BY toplam_mac DESC"
        );
        $stmt->execute([$aktif_takim['id']]);
        $efsane_adaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

// ================================================================
// HALL OF FAME'E EKLE
// ================================================================
if (isset($_POST['ekle_hof']) && $aktif_takim) {
    $oyuncu_isim = trim($_POST['oyuncu_isim'] ?? '');
    $toplam_mac  = (int)($_POST['toplam_mac'] ?? 0);
    $toplam_gol  = (int)($_POST['toplam_gol'] ?? 0);
    $mevki       = trim($_POST['mevki'] ?? 'ORT');
    $ovr         = (int)($_POST['ovr'] ?? 70);
    $basari_notu = trim($_POST['basari_notu'] ?? '');

    if ($oyuncu_isim !== '') {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO hall_of_fame
                    (takim_id, takim_lig, takim_adi, oyuncu_isim, mevki, ovr, toplam_mac, toplam_gol, basari_notu, emeklilik_yil)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $aktif_takim['id'],
                $aktif_lig,
                $aktif_takim['takim_adi'],
                $oyuncu_isim,
                $mevki,
                $ovr,
                $toplam_mac,
                $toplam_gol,
                $basari_notu,
                $guncel_sezon,
            ]);
            $mesaj      = "🏛️ " . htmlspecialchars($oyuncu_isim) . " Şöhretler Müzesi'ne alındı!";
            $mesaj_tipi = "success";
        } catch (Throwable $e) {
            $mesaj      = "Hata: " . $e->getMessage();
            $mesaj_tipi = "danger";
        }
    }
}

// Hızlı ekle (aday listesinden)
if (isset($_POST['hizli_ekle']) && $aktif_takim) {
    $isim = trim($_POST['h_isim'] ?? '');
    $mac  = (int)($_POST['h_mac'] ?? 0);
    $gol  = (int)($_POST['h_gol'] ?? 0);
    $mevki = trim($_POST['h_mevki'] ?? 'ORT');
    $ovr  = (int)($_POST['h_ovr'] ?? 70);
    if ($isim) {
        try {
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO hall_of_fame
                    (takim_id, takim_lig, takim_adi, oyuncu_isim, mevki, ovr, toplam_mac, toplam_gol, emeklilik_yil)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$aktif_takim['id'], $aktif_lig, $aktif_takim['takim_adi'], $isim, $mevki, $ovr, $mac, $gol, $guncel_sezon]);
            $mesaj      = "✅ " . htmlspecialchars($isim) . " Şöhretler Müzesi'ne eklendi!";
            $mesaj_tipi = "success";
        } catch (Throwable $e) {
            $mesaj      = "Hata: " . $e->getMessage();
            $mesaj_tipi = "danger";
        }
    }
}

// Mevcut Hall of Fame girişleri
$hof_listesi = [];
if ($aktif_takim) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM hall_of_fame WHERE takim_id = ? AND takim_lig = ? ORDER BY toplam_mac DESC");
        $stmt->execute([$aktif_takim['id'], $aktif_lig]);
        $hof_listesi = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Şöhretler Müzesi | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#050505; color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
    .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:radial-gradient(ellipse at top, #0a0010 0%, #050505 70%); z-index:-1; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    .gold { color:#d4af37; }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .glass-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:20px; backdrop-filter:blur(20px); padding:24px; margin-bottom:24px; }
    .hof-card { background:linear-gradient(135deg,rgba(139,92,246,0.18),rgba(109,40,217,0.06)); border:1px solid rgba(139,92,246,0.4); border-radius:20px; padding:24px; transition:all .3s; }
    .hof-card:hover { transform:translateY(-4px); box-shadow:0 10px 30px rgba(139,92,246,0.25); }
    .hof-num { font-size:2.5rem; font-weight:900; color:#8b5cf6; }
    .hof-num-label { font-size:0.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; }
    .mevki-badge { font-size:0.75rem; padding:4px 12px; border-radius:20px; background:rgba(139,92,246,0.2); border:1px solid rgba(139,92,246,0.4); color:#c4b5fd; }
    .aday-card { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:16px; transition:all .3s; }
    .aday-card:hover { border-color:rgba(212,175,55,0.4); background:rgba(212,175,55,0.05); }
    .btn-purple { background:linear-gradient(135deg,#8b5cf6,#a78bfa); color:#fff; font-weight:700; border:none; border-radius:10px; padding:8px 20px; font-size:0.85rem; }
    .btn-purple:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(139,92,246,0.4); color:#fff; }
    .btn-gold { background:linear-gradient(135deg,#d4af37,#fde047); color:#000; font-weight:800; border:none; border-radius:12px; padding:12px 30px; }
    .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(212,175,55,0.4); color:#000; }
    .table-dark-custom { background:transparent; }
    .table-dark-custom th { color:#8b5cf6; border-color:rgba(255,255,255,0.08); font-family:'Oswald',sans-serif; letter-spacing:1px; }
    .table-dark-custom td { border-color:rgba(255,255,255,0.06); vertical-align:middle; }
    .lig-tab { display:inline-block; padding:8px 20px; border-radius:20px; font-size:0.85rem; font-weight:600; cursor:pointer; border:1px solid rgba(255,255,255,0.15); margin:4px; text-decoration:none; color:#94a3b8; transition:all .2s; }
    .lig-tab.active, .lig-tab:hover { background:rgba(139,92,246,0.2); border-color:rgba(139,92,246,0.5); color:#c4b5fd; }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.9rem; }
    .back-btn:hover { color:#8b5cf6; }
    .section-title { font-size:1.5rem; font-weight:800; margin-bottom:20px; border-left:3px solid #8b5cf6; padding-left:14px; }
    .form-control-dark { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#fff; border-radius:10px; }
    .form-control-dark:focus { background:rgba(255,255,255,0.1); border-color:#8b5cf6; box-shadow:0 0 0 0.2rem rgba(139,92,246,0.25); color:#fff; }
    .empty-state { text-align:center; padding:60px 20px; color:#94a3b8; }
</style>
</head>
<body>
<div class="bg-overlay"></div>
<div class="container-fluid px-4 pb-5">
    <!-- NAVBAR -->
    <div class="d-flex align-items-center py-4">
        <a href="index.php" class="back-btn me-auto"><i class="fa-solid fa-arrow-left me-2"></i>Ana Menü</a>
        <span class="font-oswald gold" style="font-size:1.1rem;letter-spacing:2px;">ULTIMATE MANAGER</span>
    </div>

    <!-- HERO -->
    <div class="text-center py-4 mb-4">
        <div style="font-size:4rem; margin-bottom:10px;">🏛️</div>
        <h1 class="font-oswald" style="font-size:3rem;"><span class="gold-gradient">ŞÖHRETLER MÜZESİ</span></h1>
        <p class="text-secondary mt-2">Hall of Fame — Kulüp Efsaneleri Sonsuza Yaşar</p>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj_tipi ?> glass-card mb-4"><?= $mesaj ?></div>
    <?php endif; ?>

    <!-- LİG SEÇİCİ -->
    <?php if (count($benim_takimlarim) > 1): ?>
    <div class="text-center mb-4">
        <?php foreach ($benim_takimlarim as $t): ?>
            <a href="?lig=<?= $t['kaynak'] ?>"
               class="lig-tab <?= $t['kaynak'] === $aktif_lig ? 'active' : '' ?>">
                <?= htmlspecialchars($lig_etiket[$t['kaynak']] ?? $t['kaynak']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($aktif_takim): ?>

    <!-- KULÜP BAŞLIĞI -->
    <div class="glass-card mb-4 text-center">
        <h3 class="font-oswald" style="font-size:2rem; color:#8b5cf6;">
            <i class="fa-solid fa-shield-halved me-2"></i>
            <?= htmlspecialchars($aktif_takim['takim_adi']) ?> — <?= htmlspecialchars($lig_etiket[$aktif_lig] ?? $aktif_lig) ?>
        </h3>
        <p class="text-secondary mb-0"><?= count($hof_listesi) ?> Efsane Oyuncu • Şöhretler Müzesi</p>
    </div>

    <!-- HALL OF FAME LİSTESİ -->
    <?php if (!empty($hof_listesi)): ?>
    <div class="glass-card mb-4">
        <div class="section-title">🌟 Efsaneler</div>
        <div class="row g-3">
        <?php foreach ($hof_listesi as $efsane): ?>
            <div class="col-md-4 col-lg-3">
                <div class="hof-card">
                    <div class="d-flex align-items-start justify-content-between mb-3">
                        <span class="mevki-badge"><?= htmlspecialchars($efsane['mevki']) ?></span>
                        <span style="font-size:1.5rem;">⭐</span>
                    </div>
                    <div class="fw-bold" style="font-size:1.1rem; margin-bottom:4px;"><?= htmlspecialchars($efsane['oyuncu_isim']) ?></div>
                    <div style="font-size:0.8rem; color:#94a3b8; margin-bottom:12px;"><?= $efsane['emeklilik_yil'] ?> sezonu</div>
                    <div class="d-flex gap-3">
                        <div class="text-center">
                            <div class="hof-num"><?= $efsane['toplam_mac'] ?></div>
                            <div class="hof-num-label">Maç</div>
                        </div>
                        <div class="text-center">
                            <div class="hof-num" style="color:#10b981;"><?= $efsane['toplam_gol'] ?></div>
                            <div class="hof-num-label">Gol</div>
                        </div>
                        <div class="text-center">
                            <div class="hof-num" style="color:#d4af37; font-size:1.8rem;"><?= $efsane['ovr'] ?></div>
                            <div class="hof-num-label">OVR</div>
                        </div>
                    </div>
                    <?php if ($efsane['basari_notu']): ?>
                    <div style="font-size:0.75rem; color:#c4b5fd; margin-top:10px; border-top:1px solid rgba(255,255,255,0.08); padding-top:8px;">
                        🏆 <?= htmlspecialchars($efsane['basari_notu']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card empty-state mb-4">
        <i class="fa-solid fa-monument" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-4">Henüz Hall of Fame'e girmiş oyuncu yok. 200+ maç veya 100+ gol yapan oyuncuları aşağıdan ekleyebilirsiniz.</p>
    </div>
    <?php endif; ?>

    <!-- UYGUN ADAYLAR (200+ maç / 100+ gol) -->
    <?php if (!empty($efsane_adaylar)): ?>
    <div class="glass-card mb-4">
        <div class="section-title">🔍 Efsane Adayları (200+ Maç / 100+ Gol)</div>
        <div class="row g-3">
        <?php foreach ($efsane_adaylar as $a): ?>
            <div class="col-md-4 col-lg-3">
                <div class="aday-card">
                    <div class="fw-bold mb-1"><?= htmlspecialchars($a['isim']) ?></div>
                    <div style="font-size:0.8rem; color:#94a3b8;"><?= $a['mevki'] ?> • OVR <?= $a['ovr'] ?></div>
                    <div class="d-flex gap-3 my-2">
                        <span style="font-size:0.85rem;"><i class="fa-solid fa-shirt me-1 text-primary"></i><?= $a['toplam_mac'] ?? 0 ?> maç</span>
                        <span style="font-size:0.85rem;"><i class="fa-solid fa-futbol me-1 text-success"></i><?= $a['toplam_gol'] ?? 0 ?> gol</span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="h_isim" value="<?= htmlspecialchars($a['isim']) ?>">
                        <input type="hidden" name="h_mac"  value="<?= $a['toplam_mac'] ?? 0 ?>">
                        <input type="hidden" name="h_gol"  value="<?= $a['toplam_gol'] ?? 0 ?>">
                        <input type="hidden" name="h_mevki" value="<?= htmlspecialchars($a['mevki']) ?>">
                        <input type="hidden" name="h_ovr"  value="<?= $a['ovr'] ?>">
                        <input type="hidden" name="lig"    value="<?= $aktif_lig ?>">
                        <button type="submit" name="hizli_ekle" class="btn btn-purple btn-sm w-100">
                            <i class="fa-solid fa-star me-1"></i>Müzeye Ekle
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- MANUEL EKLEME FORMU -->
    <div class="glass-card">
        <div class="section-title">➕ Manuel Efsane Ekle</div>
        <form method="POST">
            <input type="hidden" name="lig" value="<?= $aktif_lig ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">Oyuncu Adı</label>
                    <input type="text" name="oyuncu_isim" class="form-control form-control-dark" placeholder="Örn: Hakan Şükür" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">Mevki</label>
                    <select name="mevki" class="form-control form-control-dark">
                        <option>KLK</option><option>STK</option><option selected>ORT</option>
                        <option>SAT</option><option>SOL</option><option>MDO</option><option>MDÖ</option>
                        <option>SAÇ</option><option>SOÇ</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">OVR</label>
                    <input type="number" name="ovr" class="form-control form-control-dark" value="80" min="50" max="99">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">Toplam Maç</label>
                    <input type="number" name="toplam_mac" class="form-control form-control-dark" value="200" min="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">Toplam Gol</label>
                    <input type="number" name="toplam_gol" class="form-control form-control-dark" value="50" min="0">
                </div>
                <div class="col-12">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">Başarı Notu (opsiyonel)</label>
                    <input type="text" name="basari_notu" class="form-control form-control-dark" placeholder="Örn: 3x Süper Lig Şampiyonu, UCL Finalisti">
                </div>
                <div class="col-12">
                    <button type="submit" name="ekle_hof" class="btn btn-gold font-oswald">
                        <i class="fa-solid fa-monument me-2"></i>ŞÖHRETLER MÜZESİ'NE EKLE
                    </button>
                </div>
            </div>
        </form>
    </div>

    <?php else: ?>
    <div class="glass-card text-center py-5">
        <i class="fa-solid fa-question-circle" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-4 text-secondary">Aktif takım bulunamadı. Önce bir kulüp seçin.</p>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
