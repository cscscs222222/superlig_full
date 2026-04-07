<?php
// ==============================================================================
// FAZ 6: HAFTANIN 11'İ VE SEZONUN 11'İ (TOTW & TOTS)
// Maç motoru puanlarına göre en iyi 11'i seçer, totw_secim ve tots_secim'e kaydeder.
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

$guncel_sezon = 2025;
$guncel_hafta = 1;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

$aktif_sekme = $_GET['sekme'] ?? 'totw';
$aktif_lig   = $_GET['lig'] ?? 'tr';
if (!array_key_exists($aktif_lig, $tbl_oyuncu)) $aktif_lig = 'tr';

// ================================================================
// EN İYİ 11'İ OLUŞTUR (TOTW)
// Maç puanı + gol + asist skorlaması ile formasyon: 1 KLK, 4 DEF, 3 ORT, 3 HUC
// ================================================================
function secim_yap_11(array $oyuncular): array {
    // Mevkileri grupla
    $klk  = []; $def = []; $ort = []; $huc = [];
    foreach ($oyuncular as $o) {
        $m = strtoupper($o['mevki'] ?? 'ORT');
        if (in_array($m, ['KLK', 'GK', 'KALECI']))        $klk[] = $o;
        elseif (in_array($m, ['STK', 'SAT', 'SOL', 'MDO', 'MDÖ', 'SAÇ', 'SOÇ', 'MDA']))
            $ort[] = $o;
        elseif (in_array($m, ['ST', 'OFS', 'SAH', 'SOH', 'SS', 'FOR', 'FW'])) $huc[] = $o;
        else  $def[] = $o;
    }
    // Sırala (puan'a göre)
    $sort = fn($a, $b) => ($b['puan'] ?? $b['mac_puani_ort'] ?? 0) <=> ($a['puan'] ?? $a['mac_puani_ort'] ?? 0);
    usort($klk, $sort); usort($def, $sort); usort($ort, $sort); usort($huc, $sort);

    $onbir = [];
    if (!empty($klk)) $onbir[] = array_shift($klk);
    for ($i = 0; $i < 4 && !empty($def); $i++) $onbir[] = array_shift($def);
    for ($i = 0; $i < 3 && !empty($ort); $i++) $onbir[] = array_shift($ort);
    for ($i = 0; $i < 3 && !empty($huc); $i++) $onbir[] = array_shift($huc);

    // Formasyonu 11'e tamamla (kalan en iyi adaylar)
    if (count($onbir) < 11) {
        $tum = array_merge($klk, $def, $ort, $huc);
        usort($tum, $sort);
        while (count($onbir) < 11 && !empty($tum)) $onbir[] = array_shift($tum);
    }
    return $onbir;
}

// ================================================================
// HAFTANIN 11'İNİ HESAPLA VE KAYDET
// ================================================================
if (isset($_POST['totw_hesapla'])) {
    $lig = $_POST['totw_lig'] ?? $aktif_lig;
    if (!array_key_exists($lig, $tbl_oyuncu)) $lig = 'tr';
    $o_tbl  = $tbl_oyuncu[$lig];
    $tk_tbl = $tbl_takim[$lig];
    $hafta  = (int)($_POST['totw_hafta'] ?? $guncel_hafta);

    try {
        // Oyuncuları çek (simüle edilmiş haftalık puan = mac_puani_ort + gol * 2 + asist)
        $stmt = $pdo->query(
            "SELECT o.id, o.isim, o.mevki, o.ovr,
                    COALESCE(o.sezon_gol,   0)   AS gol,
                    COALESCE(o.sezon_asist, 0)   AS asist,
                    COALESCE(o.mac_puani_ort, 6) AS mac_puani_ort,
                    t.takim_adi,
                    (COALESCE(o.mac_puani_ort,6) + COALESCE(o.sezon_gol,0)*2 + COALESCE(o.sezon_asist,0)) AS puan
             FROM $o_tbl o
             JOIN $tk_tbl t ON t.id = o.takim_id
             WHERE COALESCE(o.mac_puani_ort, 6) >= 6
             ORDER BY puan DESC
             LIMIT 50"
        );
        $oyuncular = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $onbir = secim_yap_11($oyuncular);

        // Kaydet
        $pdo->prepare("DELETE FROM totw_secim WHERE sezon_yil=? AND hafta=? AND lig_kodu=?")
            ->execute([$guncel_sezon, $hafta, $lig]);

        $ins = $pdo->prepare(
            "INSERT INTO totw_secim (sezon_yil, hafta, lig_kodu, oyuncu_isim, takim_adi, mevki, mac_puani, gol, asist)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($onbir as $o) {
            $ins->execute([$guncel_sezon, $hafta, $lig, $o['isim'], $o['takim_adi'], $o['mevki'], $o['mac_puani_ort'], $o['gol'], $o['asist']]);
        }
        $mesaj      = "✅ Hafta $hafta ({$lig_etiket[$lig]}) Haftanın 11'i seçildi!";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// ================================================================
// SEZONUN 11'İNİ HESAPLA VE KAYDET (TOTS)
// ================================================================
if (isset($_POST['tots_hesapla'])) {
    $lig = $_POST['tots_lig'] ?? $aktif_lig;
    if (!array_key_exists($lig, $tbl_oyuncu)) $lig = 'tr';
    $o_tbl  = $tbl_oyuncu[$lig];
    $tk_tbl = $tbl_takim[$lig];

    try {
        $stmt = $pdo->query(
            "SELECT o.id, o.isim, o.mevki, o.ovr,
                    COALESCE(o.sezon_gol,     0)   AS gol,
                    COALESCE(o.sezon_asist,   0)   AS asist,
                    COALESCE(o.mac_puani_ort, 6)   AS mac_puani_ort,
                    t.takim_adi,
                    (o.ovr * 0.5
                     + COALESCE(o.mac_puani_ort,6) * 5
                     + COALESCE(o.sezon_gol,0)   * 3
                     + COALESCE(o.sezon_asist,0) * 1.5) AS puan
             FROM $o_tbl o
             JOIN $tk_tbl t ON t.id = o.takim_id
             ORDER BY puan DESC
             LIMIT 80"
        );
        $oyuncular = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $onbir = secim_yap_11($oyuncular);

        $pdo->prepare("DELETE FROM tots_secim WHERE sezon_yil=? AND lig_kodu=?")
            ->execute([$guncel_sezon, $lig]);

        $ins = $pdo->prepare(
            "INSERT INTO tots_secim (sezon_yil, lig_kodu, oyuncu_isim, takim_adi, mevki, ovr, sezon_gol, sezon_asist, mac_puani_ort, puan)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($onbir as $o) {
            $ins->execute([$guncel_sezon, $lig, $o['isim'], $o['takim_adi'], $o['mevki'], $o['ovr'], $o['gol'], $o['asist'], $o['mac_puani_ort'], $o['puan']]);
        }
        $mesaj      = "🏆 {$guncel_sezon} sezonu ({$lig_etiket[$lig]}) Sezonun 11'i seçildi!";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// ================================================================
// VERİ ÇEK: TOTW & TOTS
// ================================================================
$totw_hafta = (int)($_GET['hafta'] ?? $guncel_hafta);
$totw_listesi = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM totw_secim WHERE sezon_yil=? AND hafta=? AND lig_kodu=? ORDER BY mac_puani DESC");
    $stmt->execute([$guncel_sezon, $totw_hafta, $aktif_lig]);
    $totw_listesi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$tots_listesi = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM tots_secim WHERE sezon_yil=? AND lig_kodu=? ORDER BY puan DESC");
    $stmt->execute([$guncel_sezon, $aktif_lig]);
    $tots_listesi = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// Mevki renkler
$mevki_renk = [
    'KLK' => '#f59e0b', 'GK' => '#f59e0b',
    'STK' => '#3b82f6', 'SAT' => '#3b82f6', 'SOL' => '#3b82f6', 'MDO' => '#3b82f6', 'MDÖ' => '#3b82f6', 'SAÇ' => '#3b82f6', 'SOÇ' => '#3b82f6', 'MDA' => '#3b82f6',
    'ST' => '#10b981', 'OFS' => '#10b981', 'SAH' => '#10b981', 'SOH' => '#10b981', 'SS' => '#10b981', 'FOR' => '#10b981', 'FW' => '#10b981',
];
function mevki_rengi($m, $map) { return $map[strtoupper($m)] ?? '#8b5cf6'; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TOTW & TOTS | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#050505; color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
    .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:radial-gradient(ellipse at top, #001020 0%, #050505 70%); z-index:-1; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    .gold { color:#d4af37; }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .glass-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:20px; backdrop-filter:blur(20px); padding:24px; margin-bottom:24px; }
    .player-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:16px; text-align:center; transition:all .3s; }
    .player-card:hover { transform:translateY(-4px); box-shadow:0 10px 30px rgba(0,0,0,0.5); }
    .player-ovr { font-size:2rem; font-weight:900; line-height:1; }
    .player-name { font-size:0.9rem; font-weight:700; margin-top:6px; }
    .player-team { font-size:0.75rem; color:#94a3b8; }
    .mevki-pill { display:inline-block; padding:3px 10px; border-radius:20px; font-size:0.7rem; font-weight:700; margin-bottom:8px; }
    .tab-btn { display:inline-block; padding:10px 28px; border-radius:30px; font-weight:700; cursor:pointer; border:1px solid rgba(255,255,255,0.15); margin:4px; text-decoration:none; color:#94a3b8; transition:all .2s; font-size:0.9rem; }
    .tab-btn.active { background:rgba(212,175,55,0.2); border-color:#d4af37; color:#d4af37; }
    .tab-btn:hover { border-color:rgba(255,255,255,0.3); color:#fff; }
    .lig-tab { display:inline-block; padding:6px 18px; border-radius:20px; font-size:0.8rem; font-weight:600; cursor:pointer; border:1px solid rgba(255,255,255,0.15); margin:3px; text-decoration:none; color:#94a3b8; transition:all .2s; }
    .lig-tab.active { background:rgba(59,130,246,0.2); border-color:#3b82f6; color:#93c5fd; }
    .lig-tab:hover { border-color:rgba(255,255,255,0.3); color:#fff; }
    .btn-gold { background:linear-gradient(135deg,#d4af37,#fde047); color:#000; font-weight:800; border:none; border-radius:12px; padding:12px 30px; letter-spacing:1px; }
    .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(212,175,55,0.4); color:#000; }
    .btn-blue { background:linear-gradient(135deg,#3b82f6,#60a5fa); color:#fff; font-weight:800; border:none; border-radius:12px; padding:12px 30px; }
    .btn-blue:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(59,130,246,0.4); color:#fff; }
    .form-control-dark { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#fff; border-radius:10px; }
    .form-control-dark:focus { background:rgba(255,255,255,0.1); border-color:#3b82f6; color:#fff; box-shadow:0 0 0 0.2rem rgba(59,130,246,0.25); }
    .empty-state { text-align:center; padding:50px 20px; color:#94a3b8; }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.9rem; }
    .back-btn:hover { color:#d4af37; }
    .section-title { font-size:1.5rem; font-weight:800; margin-bottom:20px; border-left:3px solid #d4af37; padding-left:14px; }
    .hafta-nav { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
    .hafta-btn { padding:4px 14px; border-radius:20px; font-size:0.8rem; border:1px solid rgba(255,255,255,0.15); color:#94a3b8; text-decoration:none; }
    .hafta-btn:hover, .hafta-btn.active { background:rgba(212,175,55,0.15); border-color:#d4af37; color:#d4af37; }
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
    <div class="text-center py-3 mb-4">
        <h1 class="font-oswald" style="font-size:3rem;"><span class="gold-gradient">HAFTANIN & SEZONUN 11'İ</span></h1>
        <p class="text-secondary mt-2">TOTW & TOTS — <?= $guncel_sezon ?> Sezonu</p>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj_tipi ?> glass-card mb-4"><?= $mesaj ?></div>
    <?php endif; ?>

    <!-- SEKME SEÇİCİ -->
    <div class="text-center mb-4">
        <a href="?sekme=totw&lig=<?= $aktif_lig ?>" class="tab-btn <?= $aktif_sekme==='totw' ? 'active' : '' ?>">
            <i class="fa-solid fa-calendar-week me-1"></i>Haftanın 11'i (TOTW)
        </a>
        <a href="?sekme=tots&lig=<?= $aktif_lig ?>" class="tab-btn <?= $aktif_sekme==='tots' ? 'active' : '' ?>">
            <i class="fa-solid fa-trophy me-1"></i>Sezonun 11'i (TOTS)
        </a>
    </div>

    <!-- LİG SEÇİCİ -->
    <div class="text-center mb-4">
        <?php foreach ($lig_etiket as $k => $v): ?>
        <a href="?sekme=<?= $aktif_sekme ?>&lig=<?= $k ?>" class="lig-tab <?= $k===$aktif_lig ? 'active' : '' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>

    <!-- ================ TOTW ================ -->
    <?php if ($aktif_sekme === 'totw'): ?>

    <!-- HESAPLA BUTONU -->
    <div class="glass-card mb-4">
        <div class="section-title">⚡ Haftanın 11'ini Seç</div>
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="totw_lig" value="<?= $aktif_lig ?>">
            <div class="col-md-3">
                <label class="form-label text-secondary" style="font-size:0.85rem;">Hafta</label>
                <input type="number" name="totw_hafta" class="form-control form-control-dark" value="<?= $guncel_hafta ?>" min="1" max="38">
            </div>
            <div class="col-md-3">
                <button type="submit" name="totw_hesapla" class="btn btn-gold font-oswald">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>SEÇ VE KAYDET
                </button>
            </div>
        </form>
    </div>

    <!-- HAFTA NAVİGASYON -->
    <div class="hafta-nav justify-content-center">
        <?php for ($h = max(1, $totw_hafta-3); $h <= min(38, $totw_hafta+3); $h++): ?>
        <a href="?sekme=totw&lig=<?= $aktif_lig ?>&hafta=<?= $h ?>" class="hafta-btn <?= $h===$totw_hafta ? 'active' : '' ?>">H<?= $h ?></a>
        <?php endfor; ?>
    </div>

    <!-- TOTW KADROLAR -->
    <?php if (!empty($totw_listesi)): ?>
    <div class="glass-card">
        <div class="section-title">🌟 <?= $lig_etiket[$aktif_lig] ?> — Hafta <?= $totw_hafta ?> Haftanın 11'i</div>
        <div class="row g-3">
        <?php foreach ($totw_listesi as $p): ?>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="player-card">
                    <span class="mevki-pill" style="background:<?= mevki_rengi($p['mevki'], $mevki_renk) ?>22; color:<?= mevki_rengi($p['mevki'], $mevki_renk) ?>; border:1px solid <?= mevki_rengi($p['mevki'], $mevki_renk) ?>55;">
                        <?= htmlspecialchars($p['mevki']) ?>
                    </span>
                    <div class="player-ovr" style="color:<?= mevki_rengi($p['mevki'], $mevki_renk) ?>;"><?= number_format($p['mac_puani'], 1) ?></div>
                    <div class="player-name"><?= htmlspecialchars($p['oyuncu_isim']) ?></div>
                    <div class="player-team"><?= htmlspecialchars($p['takim_adi']) ?></div>
                    <div style="font-size:0.75rem; color:#94a3b8; margin-top:4px;">
                        <?= $p['gol'] ?>G &bull; <?= $p['asist'] ?>A
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card empty-state">
        <i class="fa-solid fa-users" style="font-size:4rem; opacity:0.3;"></i>
        <p class="mt-4">Hafta <?= $totw_hafta ?> için henüz seçim yapılmamış. Yukarıdaki butonu kullanın.</p>
    </div>
    <?php endif; ?>

    <!-- ================ TOTS ================ -->
    <?php else: ?>

    <div class="glass-card mb-4">
        <div class="section-title">🏆 Sezonun 11'ini Seç</div>
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="tots_lig" value="<?= $aktif_lig ?>">
            <div class="col-md-4">
                <p class="text-secondary mb-2" style="font-size:0.85rem;">Tüm sezon istatistikleri (OVR, maç puanı, gol, asist) hesaplanarak <?= $guncel_sezon ?> sezonunun en iyi 11'i seçilir ve kaydedilir.</p>
                <button type="submit" name="tots_hesapla" class="btn btn-blue font-oswald">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>SEZONUN 11'İNİ HESAPLA
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($tots_listesi)): ?>
    <div class="glass-card">
        <div class="section-title">⭐ <?= $lig_etiket[$aktif_lig] ?> — <?= $guncel_sezon ?> Sezonun 11'i</div>
        <div class="row g-3">
        <?php foreach ($tots_listesi as $p): ?>
            <div class="col-md-2 col-sm-4 col-6">
                <div class="player-card" style="border-color:rgba(212,175,55,0.3);">
                    <span class="mevki-pill" style="background:<?= mevki_rengi($p['mevki'], $mevki_renk) ?>22; color:<?= mevki_rengi($p['mevki'], $mevki_renk) ?>; border:1px solid <?= mevki_rengi($p['mevki'], $mevki_renk) ?>55;">
                        <?= htmlspecialchars($p['mevki']) ?>
                    </span>
                    <div class="player-ovr gold"><?= $p['ovr'] ?></div>
                    <div class="player-name"><?= htmlspecialchars($p['oyuncu_isim']) ?></div>
                    <div class="player-team"><?= htmlspecialchars($p['takim_adi']) ?></div>
                    <div style="font-size:0.75rem; color:#94a3b8; margin-top:4px;">
                        <?= $p['sezon_gol'] ?>G &bull; <?= $p['sezon_asist'] ?>A &bull; <?= number_format($p['mac_puani_ort'], 2) ?>★
                    </div>
                    <div style="font-size:0.7rem; color:#d4af37; margin-top:2px;"><?= number_format($p['puan'], 1) ?> puan</div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card empty-state">
        <i class="fa-solid fa-trophy" style="font-size:4rem; opacity:0.3; color:#d4af37;"></i>
        <p class="mt-4">Henüz sezonun 11'i seçilmemiş. Yukarıdaki butonu kullanın.</p>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
