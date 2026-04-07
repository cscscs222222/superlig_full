<?php
// ==============================================================================
// FAZ 5: MENAJER KARİYER MODU (Kovulma ve İşsiz Menajerlik)
// Özellikler: Yönetim Güven Puanı, Kovulma, İşsiz Mod, İş Teklifleri
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

// --- Tablo Haritalaması ---
$tbl_takim = [
    'tr' => 'takimlar',    'cl' => 'cl_takimlar', 'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar', 'fr' => 'fr_takimlar',
    'it' => 'it_takimlar', 'pt' => 'pt_takimlar',
];
$ayar_tablosu = [
    'tr' => 'ayar',    'pl' => 'pl_ayar',   'es' => 'es_ayar',
    'de' => 'de_ayar', 'it' => 'it_ayar',   'cl' => 'cl_ayar',
    'fr' => 'fr_ayar', 'pt' => 'pt_ayar',
];
$gecerli_liglar = array_keys($tbl_takim);

// Kullanıcı takımını çek
function fetch_kullanici_kariyer($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    try {
        $ayar_stmt = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1");
        $ayar_id   = $ayar_stmt ? $ayar_stmt->fetchColumn() : false;
        if (!$ayar_id) return null;
        $stmt = $pdo->prepare("SELECT * FROM $takim_tablo WHERE id = ?");
        $stmt->execute([$ayar_id]);
        $takim = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($takim) { $takim['kaynak'] = $lig_kodu; return $takim; }
    } catch (Throwable $e) {}
    return null;
}

$benim_takimlarim = [];
foreach ($ayar_tablosu as $kod => $ayar_tbl) {
    $t = fetch_kullanici_kariyer($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
    if ($t) $benim_takimlarim[] = $t;
}

$guncel_hafta = 1;
$guncel_sezon = 2025;
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

$aktif_lig = $_GET['lig'] ?? ($benim_takimlarim[0]['kaynak'] ?? 'tr');
if (!in_array($aktif_lig, $gecerli_liglar, true)) $aktif_lig = 'tr';

$aktif_takim = null;
foreach ($benim_takimlarim as $t) { if ($t['kaynak'] === $aktif_lig) { $aktif_takim = $t; break; } }
if (!$aktif_takim && !empty($benim_takimlarim)) { $aktif_takim = $benim_takimlarim[0]; $aktif_lig = $aktif_takim['kaynak']; }

// ============================================================
// Beklenti etiketleri
// ============================================================
$beklenti_etiketler = [
    'sampiyonluk'  => ['label' => '🏆 Şampiyonluk',      'min_sira' => 1,  'renk' => '#d4af37'],
    'ilk_4'        => ['label' => '🥇 İlk 4\'e Gir',     'min_sira' => 4,  'renk' => '#3b82f6'],
    'ilk_yari'     => ['label' => '📊 İlk Yarıya Gir',   'min_sira' => 10, 'renk' => '#8b5cf6'],
    'kupayi_kazan' => ['label' => '🏅 Kupayı Kazan',     'min_sira' => 5,  'renk' => '#f59e0b'],
    'kupaliga_kal' => ['label' => '🔐 Küme Düşme Yok',  'min_sira' => 17, 'renk' => '#10b981'],
];

// Takım sıralamasını çek
function takim_sira_al($pdo, $takim_id, $tbl_takim) {
    try {
        $rows = $pdo->query("SELECT id FROM $tbl_takim ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_COLUMN);
        $pos  = array_search($takim_id, $rows);
        return $pos !== false ? ($pos + 1) : 0;
    } catch (Throwable $e) { return 0; }
}

// Kariyer kaydını al/oluştur
$kariyer = null;
if ($aktif_takim) {
    $takim_id = (int)$aktif_takim['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM menajer_kariyer WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ?");
        $stmt->execute([$takim_id, $aktif_lig, $guncel_sezon]);
        $kariyer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    if (!$kariyer) {
        // İlk kayıt oluştur
        $mevcut_sira = takim_sira_al($pdo, $takim_id, $tbl_takim[$aktif_lig]);
        try {
            $pdo->prepare("INSERT IGNORE INTO menajer_kariyer
                (takim_id, takim_lig, takim_adi, sezon_yil, beklenti, beklenti_min_sira, mevcut_sira, guven_puani, durum)
                VALUES (?, ?, ?, ?, 'ilk_yari', 10, ?, 70, 'aktif')"
            )->execute([$takim_id, $aktif_lig, $aktif_takim['takim_adi'], $guncel_sezon, $mevcut_sira]);
            $stmt = $pdo->prepare("SELECT * FROM menajer_kariyer WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ?");
            $stmt->execute([$takim_id, $aktif_lig, $guncel_sezon]);
            $kariyer = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
    }
}

// ============================================================
// EYLEMLER
// ============================================================

// 1. Beklentiyi Güncelle
if (isset($_POST['beklenti_guncelle']) && $kariyer) {
    $yeni_beklenti = $_POST['beklenti'] ?? 'ilk_yari';
    if (!isset($beklenti_etiketler[$yeni_beklenti])) $yeni_beklenti = 'ilk_yari';
    $min_sira = $beklenti_etiketler[$yeni_beklenti]['min_sira'];
    try {
        $pdo->prepare("UPDATE menajer_kariyer SET beklenti = ?, beklenti_min_sira = ? WHERE id = ?"
        )->execute([$yeni_beklenti, $min_sira, $kariyer['id']]);
        $kariyer['beklenti']         = $yeni_beklenti;
        $kariyer['beklenti_min_sira'] = $min_sira;
        $mesaj = "Yönetim beklentisi güncellendi: " . $beklenti_etiketler[$yeni_beklenti]['label'];
        $mesaj_tipi = "success";
    } catch (Throwable $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_tipi = "danger"; }
}

// 2. Güven Puanını Güncelle (Haftalık)
if (isset($_POST['guven_guncelle']) && $kariyer && $aktif_takim) {
    $takim_id    = (int)$aktif_takim['id'];
    $mevcut_sira = takim_sira_al($pdo, $takim_id, $tbl_takim[$aktif_lig]);
    $beklenti_k  = $kariyer['beklenti'];
    $hedef_sira  = $kariyer['beklenti_min_sira'];
    $eski_guven  = (int)$kariyer['guven_puani'];

    // Güven değişimi hesapla
    // Beklentiden iyi: +5 | Tam hedef: 0 | Kötü: -8 | Çok kötü (2 kat daha kötü): -15
    $fark = $mevcut_sira - $hedef_sira;
    if ($fark <= -2)      $delta = +7;
    elseif ($fark <= 0)   $delta = +3;
    elseif ($fark <= 2)   $delta = -5;
    elseif ($fark <= 5)   $delta = -10;
    else                   $delta = -18;

    // Sezon ortasında (hafta >= 17) daha sert yargılanır
    if ($guncel_hafta >= 17 && $delta < 0) $delta = (int)($delta * 1.5);

    $yeni_guven = max(0, min(100, $eski_guven + $delta));

    try {
        $pdo->prepare("UPDATE menajer_kariyer SET mevcut_sira = ?, guven_puani = ? WHERE id = ?"
        )->execute([$mevcut_sira, $yeni_guven, $kariyer['id']]);

        // Güven puanı düşük → kovulma
        if ($yeni_guven <= 20 && $kariyer['durum'] === 'aktif') {
            $sebep = "Yönetim güveni %{$yeni_guven}'e düştü. Beklentiler karşılanmadı.";
            $pdo->prepare("UPDATE menajer_kariyer SET durum = 'issiz', kovulma_hafta = ?, kovulma_sebebi = ? WHERE id = ?"
            )->execute([$guncel_hafta, $sebep, $kariyer['id']]);
            // İş teklifleri oluştur
            _is_teklifleri_olustur($pdo, $kariyer['id'], $aktif_lig);
            $kariyer['durum'] = 'issiz';
            $mesaj = "🚨 KOVULDUNUZ! " . $sebep . " İş teklifleri bekleniyor...";
            $mesaj_tipi = "danger";
        } else {
            $dir = $delta > 0 ? "⬆ +" : "⬇ ";
            $mesaj = "Güven puanı güncellendi: {$eski_guven}% → {$yeni_guven}% ({$dir}{$delta}) | Sıra: {$mevcut_sira}";
            $mesaj_tipi = $delta >= 0 ? "success" : "warning";
        }

        $kariyer['guven_puani'] = $yeni_guven;
        $kariyer['mevcut_sira'] = $mevcut_sira;
    } catch (Throwable $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_tipi = "danger"; }
}

// 3. İş Teklifini Kabul Et
if (isset($_POST['teklif_kabul']) && $kariyer) {
    $teklif_id = (int)($_POST['teklif_id'] ?? 0);
    try {
        $teklif = $pdo->prepare("SELECT * FROM is_teklifleri WHERE id = ? AND menajer_kariyer_id = ?");
        $teklif->execute([$teklif_id, $kariyer['id']]);
        $teklif = $teklif->fetch(PDO::FETCH_ASSOC);
        if ($teklif) {
            $pdo->prepare("UPDATE is_teklifleri SET durum = 'kabul' WHERE id = ?")->execute([$teklif_id]);
            $pdo->prepare("UPDATE is_teklifleri SET durum = 'ret' WHERE menajer_kariyer_id = ? AND id != ?"
            )->execute([$kariyer['id'], $teklif_id]);
            // Menajer kariyerini yeniden aktifleştir (yeni takım)
            $pdo->prepare("UPDATE menajer_kariyer SET durum = 'aktif', takim_adi = ?,
                takim_lig = ?, beklenti = ?, guven_puani = 65, kovulma_hafta = NULL, kovulma_sebebi = NULL
                WHERE id = ?"
            )->execute([$teklif['teklif_takim_adi'], $teklif['teklif_lig'], $teklif['teklif_beklentisi'], $kariyer['id']]);
            $kariyer['durum']    = 'aktif';
            $kariyer['takim_adi'] = $teklif['teklif_takim_adi'];
            $mesaj = "✅ Teklif kabul edildi! " . htmlspecialchars($teklif['teklif_takim_adi']) . " ile anlaştınız. Yeni kariyer başlıyor!";
            $mesaj_tipi = "success";
        }
    } catch (Throwable $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_tipi = "danger"; }
}

// 4. Teklifi Reddet
if (isset($_POST['teklif_ret']) && $kariyer) {
    $teklif_id = (int)($_POST['teklif_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE is_teklifleri SET durum = 'ret' WHERE id = ? AND menajer_kariyer_id = ?"
        )->execute([$teklif_id, $kariyer['id']]);
        $mesaj = "Teklif reddedildi."; $mesaj_tipi = "info";
    } catch (Throwable $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_tipi = "danger"; }
}

// 5. İş Teklifleri Oluştur (Manuel Tetikleme)
if (isset($_POST['yeni_teklifler']) && $kariyer && $kariyer['durum'] === 'issiz') {
    _is_teklifleri_olustur($pdo, $kariyer['id'], $aktif_lig);
    $mesaj = "Yeni iş teklifleri oluşturuldu!"; $mesaj_tipi = "info";
}

// ============================================================
// YARDIMCI FONKSİYON: İş Teklifleri Oluştur
// ============================================================
function _is_teklifleri_olustur($pdo, $menajer_id, $kaynak_lig) {
    // Düşük tablo / küçük kulüpler
    $teklifler = [
        ['Ankaraspor',   'tr', 3000000,  'kupaliga_kal', 2],
        ['Altay',        'tr', 4000000,  'ilk_yari',     2],
        ['Rayo Vallecano','es', 8000000, 'kupaliga_kal', 2],
        ['Watford',      'pl', 12000000, 'ilk_yari',     3],
        ['Bochum',       'de', 7000000,  'kupaliga_kal', 2],
        ['Salernitana',  'it', 5000000,  'kupaliga_kal', 1],
    ];
    shuffle($teklifler);
    $secilen = array_slice($teklifler, 0, 3);
    foreach ($secilen as $t) {
        try {
            $pdo->prepare("INSERT INTO is_teklifleri
                (menajer_kariyer_id, teklif_takim_adi, teklif_lig, teklif_butce, teklif_beklentisi, teklif_suresi_sezon)
                VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([$menajer_id, $t[0], $t[1], $t[2], $t[3], $t[4]]);
        } catch (Throwable $e) {}
    }
}

// --- Veri çekme ---
$is_teklifleri = [];
if ($kariyer) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM is_teklifleri WHERE menajer_kariyer_id = ? AND durum = 'beklemede' ORDER BY id DESC");
        $stmt->execute([$kariyer['id']]);
        $is_teklifleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

$beklenti_k   = $kariyer['beklenti']    ?? 'ilk_yari';
$beklenti_obj = $beklenti_etiketler[$beklenti_k] ?? $beklenti_etiketler['ilk_yari'];
$guven        = (int)($kariyer['guven_puani'] ?? 70);
$mevcut_sira  = (int)($kariyer['mevcut_sira']  ?? 0);
$hedef_sira   = (int)($kariyer['beklenti_min_sira'] ?? 10);
$durum        = $kariyer['durum'] ?? 'aktif';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menajer Kariyer | Ultimate Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body,html { margin:0; padding:0; min-height:100vh; font-family:'Poppins',sans-serif; background:#050505; color:#fff; }
        .bg-image { position:fixed; top:0; left:0; width:100vw; height:100vh; background:url('https://images.unsplash.com/photo-1517649763962-0c623066013b?q=80&w=2000') no-repeat center center; background-size:cover; z-index:-2; animation:slowZoom 20s infinite alternate; }
        @keyframes slowZoom { 0%{transform:scale(1);} 100%{transform:scale(1.05);} }
        .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:linear-gradient(135deg,rgba(5,5,5,0.93) 0%,rgba(8,12,25,0.85) 100%); backdrop-filter:blur(6px); z-index:-1; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .gold-text { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .modern-nav { padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:1.6rem; font-weight:900; color:#fff; text-decoration:none; letter-spacing:2px; display:flex; align-items:center; gap:12px; }
        .nav-brand i { color:#d4af37; }

        .hero-section { text-align:center; padding:30px 20px 10px; }
        .hero-title { font-size:3.2rem; font-weight:900; color:#fff; line-height:1.1; margin-bottom:8px; text-shadow:0 10px 30px rgba(0,0,0,0.8); }
        .hero-subtitle { font-size:1rem; font-weight:300; color:#94a3b8; letter-spacing:2px; }

        .ceo-card { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:20px; padding:28px; backdrop-filter:blur(12px); transition:all 0.3s ease; margin-bottom:28px; }
        .ceo-card:hover { border-color:rgba(212,175,55,0.3); box-shadow:0 8px 32px rgba(212,175,55,0.1); }
        .section-title { font-size:1.15rem; font-weight:700; color:#d4af37; margin-bottom:18px; display:flex; align-items:center; gap:10px; }

        /* Güven barı */
        .guven-bar-wrap { background:rgba(255,255,255,0.07); border-radius:10px; height:18px; overflow:hidden; margin:12px 0; position:relative; }
        .guven-bar { height:100%; border-radius:10px; transition:width 1.2s ease; }
        .guven-ok    { background:linear-gradient(90deg,#10b981,#34d399); }
        .guven-warn  { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .guven-danger{ background:linear-gradient(90deg,#ef4444,#f87171); }
        .guven-pct   { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); font-size:0.75rem; font-weight:800; color:#fff; text-shadow:0 1px 3px rgba(0,0,0,0.8); }

        /* Beklenti seçici */
        .beklenti-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .beklenti-card { background:rgba(255,255,255,0.04); border:2px solid rgba(255,255,255,0.1); border-radius:14px; padding:16px; cursor:pointer; transition:all 0.3s; text-align:center; }
        .beklenti-card.selected { background:rgba(212,175,55,0.12); border-color:#d4af37; }
        .beklenti-card:hover { border-color:rgba(212,175,55,0.5); }
        .bek-emoji { font-size:2rem; display:block; margin-bottom:6px; }
        .bek-label { font-size:0.8rem; font-weight:700; color:#fff; }
        .bek-sira  { font-size:0.7rem; color:#64748b; }

        /* İş teklif kartı */
        .teklif-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:16px; padding:20px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px; }
        .teklif-takim { font-size:1.05rem; font-weight:700; color:#fff; }
        .teklif-meta  { font-size:0.82rem; color:#94a3b8; }
        .teklif-meta span { color:#d4af37; font-weight:700; }

        /* Kovulma ekranı */
        .kovulma-screen { text-align:center; padding:40px 20px; }
        .kovulma-icon { font-size:5rem; display:block; margin-bottom:18px; }
        .kovulma-title { font-size:2.5rem; font-weight:900; color:#ef4444; }

        .btn-gold { background:linear-gradient(135deg,#d4af37,#92731b); color:#000; font-weight:800; border:none; border-radius:12px; padding:10px 24px; font-size:0.9rem; transition:all 0.3s; }
        .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(212,175,55,0.4); color:#000; }
        .btn-green-dark { background:rgba(16,185,129,0.2); color:#10b981; font-weight:700; border:1px solid #10b981; border-radius:12px; padding:10px 24px; }
        .btn-green-dark:hover { background:rgba(16,185,129,0.35); color:#6ee7b7; }
        .btn-red-dark { background:rgba(239,68,68,0.2); color:#ef4444; font-weight:700; border:1px solid #ef4444; border-radius:12px; padding:9px 18px; font-size:0.85rem; }

        .alert-custom { border-radius:14px; padding:14px 20px; margin-bottom:20px; font-weight:600; font-size:0.9rem; }
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:20px; }
        .stat-box { background:rgba(255,255,255,0.05); border-radius:14px; padding:16px; text-align:center; border:1px solid rgba(255,255,255,0.1); }
        .stat-val { font-size:1.5rem; font-weight:800; color:#d4af37; }
        .stat-lbl { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; }
        .lig-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; }
        .lig-tab { padding:8px 18px; border-radius:30px; font-size:0.8rem; font-weight:700; text-decoration:none; border:1px solid rgba(255,255,255,0.15); color:#94a3b8; transition:all 0.3s; text-transform:uppercase; }
        .lig-tab.active,.lig-tab:hover { background:rgba(212,175,55,0.2); border-color:#d4af37; color:#d4af37; }
        .footer-note { text-align:center; padding:30px; font-size:0.75rem; color:#334155; font-family:'Oswald',sans-serif; letter-spacing:2px; }
    </style>
</head>
<body>
<div class="bg-image"></div>
<div class="bg-overlay"></div>

<div class="modern-nav">
    <a href="index.php" class="nav-brand font-oswald">
        <i class="fa-solid fa-chess-knight"></i>
        ULTIMATE <span class="gold-text">MANAGER</span>
    </a>
    <div style="color:#94a3b8; font-size:0.85rem;">
        <i class="fa-solid fa-calendar-week"></i>
        Hafta <strong style="color:#d4af37;"><?= $guncel_hafta ?></strong> &nbsp;|&nbsp;
        Sezon <strong style="color:#d4af37;"><?= $guncel_sezon ?></strong>
    </div>
</div>

<div class="hero-section">
    <h1 class="hero-title font-oswald">MENAJER <span class="gold-text">KARİYER</span></h1>
    <p class="hero-subtitle">Yönetim Güveni · Beklentiler · Kovulma · İşsiz Mod · Yeni Fırsatlar</p>
</div>

<div class="container" style="max-width:1000px; padding-bottom:40px;">

<?php if (empty($benim_takimlarim) && (!$kariyer || $kariyer['durum'] === 'aktif')): ?>
    <div style="text-align:center; padding:60px 20px; color:#475569;">
        <i class="fa-solid fa-user-slash" style="font-size:4rem; display:block; margin-bottom:18px; color:#1e293b;"></i>
        <h4>Kariyer kaydı bulunamadı.</h4>
        <p>Önce bir ligde takım yönetin, sonra buraya gelin.</p>
        <a href="index.php" class="btn btn-gold mt-2"><i class="fa-solid fa-arrow-left"></i> Ana Menüye Dön</a>
    </div>
<?php else: ?>

    <!-- Lig Sekmeleri -->
    <?php if (!empty($benim_takimlarim)): ?>
    <div class="lig-tabs">
        <?php foreach ($benim_takimlarim as $t): $kod = $t['kaynak']; ?>
        <a href="?lig=<?= $kod ?>" class="lig-tab <?= $aktif_lig === $kod ? 'active' : '' ?>">
            <i class="fa-solid fa-flag"></i> <?= strtoupper($kod) ?> — <?= htmlspecialchars($t['takim_adi']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($mesaj): ?>
    <div class="alert-custom alert alert-<?= $mesaj_tipi ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
    <?php endif; ?>

    <?php if ($kariyer && $durum === 'issiz'): ?>
    <!-- ============================================================ -->
    <!-- KOVULMA / İŞSİZ MOD                                         -->
    <!-- ============================================================ -->
    <div class="ceo-card">
        <div class="kovulma-screen">
            <span class="kovulma-icon">📦</span>
            <div class="kovulma-title font-oswald">KOVULDUNUZ!</div>
            <p style="color:#94a3b8; margin:16px 0 4px;">
                <?= htmlspecialchars($kariyer['takim_adi']) ?> ile yollar ayrıldı.
            </p>
            <?php if ($kariyer['kovulma_sebebi']): ?>
            <p style="color:#ef4444; font-size:0.88rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($kariyer['kovulma_sebebi']) ?>
            </p>
            <?php endif; ?>
            <p style="color:#64748b; font-size:0.85rem; margin-top:14px;">
                Yeni bir fırsatı kabul ederek kariyerinize devam edin veya teklif bekleyin.
            </p>
        </div>
    </div>

    <!-- İş Teklifleri -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-envelope-open-text"></i> İş Teklifleri
        </div>

        <?php if (empty($is_teklifleri)): ?>
        <p style="color:#475569; text-align:center; padding:20px 0;">
            Henüz aktif bir iş teklifi yok. Tekliflerin gelmesini bekleyin.
        </p>
        <form method="post">
            <button type="submit" name="yeni_teklifler" class="btn btn-gold">
                <i class="fa-solid fa-rotate"></i> Yeni Teklifler Oluştur
            </button>
        </form>
        <?php else: ?>
        <?php foreach ($is_teklifleri as $teklif): ?>
        <div class="teklif-card">
            <div>
                <div class="teklif-takim">
                    <i class="fa-solid fa-shield-halved" style="color:#d4af37;"></i>
                    <?= htmlspecialchars($teklif['teklif_takim_adi']) ?>
                    <span style="font-size:0.75rem; color:#64748b; margin-left:8px;">(<?= strtoupper($teklif['teklif_lig']) ?>)</span>
                </div>
                <div class="teklif-meta">
                    Bütçe: <span>€<?= number_format($teklif['teklif_butce']/1000000,1) ?>M</span> &nbsp;|&nbsp;
                    Beklenti: <span><?= htmlspecialchars($beklenti_etiketler[$teklif['teklif_beklentisi']]['label'] ?? $teklif['teklif_beklentisi']) ?></span> &nbsp;|&nbsp;
                    Süre: <span><?= $teklif['teklif_suresi_sezon'] ?> sezon</span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <form method="post" style="display:inline;">
                    <input type="hidden" name="teklif_id" value="<?= $teklif['id'] ?>">
                    <button type="submit" name="teklif_kabul" class="btn btn-green-dark">
                        <i class="fa-solid fa-check"></i> Kabul Et
                    </button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="teklif_id" value="<?= $teklif['id'] ?>">
                    <button type="submit" name="teklif_ret" class="btn btn-red-dark">
                        <i class="fa-solid fa-xmark"></i> Reddet
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php elseif ($kariyer): ?>
    <!-- ============================================================ -->
    <!-- AKTİF KARİYER PANELİ                                       -->
    <!-- ============================================================ -->

    <!-- Stat Kutuları -->
    <div class="stat-grid">
        <div class="stat-box">
            <div class="stat-val"><?= htmlspecialchars($kariyer['takim_adi']) ?></div>
            <div class="stat-lbl">Takım</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $mevcut_sira > 0 ? $mevcut_sira . '. Sıra' : '—' ?></div>
            <div class="stat-lbl">Mevcut Sıra</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $beklenti_obj['label'] ?></div>
            <div class="stat-lbl">Yönetim Beklentisi</div>
        </div>
        <div class="stat-box">
            <div class="stat-val" style="color:<?= $guven >= 60 ? '#10b981' : ($guven >= 35 ? '#f59e0b' : '#ef4444') ?>;">
                %<?= $guven ?>
            </div>
            <div class="stat-lbl">Güven Puanı</div>
        </div>
        <div class="stat-box">
            <div class="stat-val">Hafta <?= $guncel_hafta ?></div>
            <div class="stat-lbl">Sezon İlerlemesi</div>
        </div>
    </div>

    <!-- Güven Barı -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-chart-line"></i> Yönetim Güveni
        </div>
        <div class="d-flex justify-content-between mb-1" style="font-size:0.82rem; color:#94a3b8;">
            <span>Güven Puanı: <strong style="color:#fff;"><?= $guven ?>%</strong></span>
            <span>
                <?php if ($guven <= 20): ?><span style="color:#ef4444;">⚠️ KRİTİK — Kovulma riski!</span>
                <?php elseif ($guven <= 40): ?><span style="color:#f59e0b;">⚠️ Baskı altında</span>
                <?php elseif ($guven <= 65): ?><span style="color:#d4af37;">🔶 Kabul edilebilir</span>
                <?php else: ?><span style="color:#10b981;">✅ Güvende</span>
                <?php endif; ?>
            </span>
        </div>
        <div class="guven-bar-wrap">
            <?php $guven_class = $guven >= 60 ? 'guven-ok' : ($guven >= 35 ? 'guven-warn' : 'guven-danger'); ?>
            <div class="guven-bar <?= $guven_class ?>" style="width:<?= $guven ?>%;"></div>
            <div class="guven-pct"><?= $guven ?>%</div>
        </div>
        <p style="color:#475569; font-size:0.78rem; margin-top:8px;">
            30% altı → Kovulma uyarısı oluşur &nbsp;|&nbsp; 20% altı → Otomatik kovulma tetiklenir.
            <?php if ($guncel_hafta >= 17): ?>
            <strong style="color:#f59e0b;"> Sezon ortasını geçtiniz — Yönetim daha sert değerlendiriyor!</strong>
            <?php endif; ?>
        </p>

        <div class="d-flex gap-2 mt-3">
            <form method="post" class="d-inline">
                <button type="submit" name="guven_guncelle" class="btn btn-gold">
                    <i class="fa-solid fa-arrows-rotate"></i> Güveni Güncelle (Sıralama Kontrol Et)
                </button>
            </form>
            <a href="kulup_yonetimi.php?lig=<?= $aktif_lig ?>" class="btn btn-green-dark">
                <i class="fa-solid fa-building-columns"></i> CEO Paneline Git
            </a>
        </div>
    </div>

    <!-- Beklenti Güncelleme -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-bullseye"></i> Yönetim Beklentisi
        </div>
        <p style="color:#94a3b8; font-size:0.87rem; margin-bottom:18px;">
            Sezon başında yönetimin sizden ne beklediğini belirleyin. Bu hedef, güven puanınızın
            hesaplanmasında temel alınır. Gerçekçi bir hedef koyun!
        </p>
        <form method="post">
            <div class="beklenti-grid">
                <?php foreach ($beklenti_etiketler as $key => $bek): ?>
                <label class="beklenti-card <?= $beklenti_k === $key ? 'selected' : '' ?>">
                    <input type="radio" name="beklenti" value="<?= $key ?>"
                        <?= $beklenti_k === $key ? 'checked' : '' ?> style="display:none;"
                        onclick="this.closest('form').querySelectorAll('.beklenti-card').forEach(c=>c.classList.remove('selected')); this.closest('.beklenti-card').classList.add('selected');">
                    <span class="bek-emoji">
                        <?php
                        $emojis = ['sampiyonluk'=>'🏆','ilk_4'=>'🥇','ilk_yari'=>'📊','kupayi_kazan'=>'🏅','kupaliga_kal'=>'🔐'];
                        echo $emojis[$key] ?? '📋';
                        ?>
                    </span>
                    <div class="bek-label"><?= htmlspecialchars($bek['label']) ?></div>
                    <div class="bek-sira">Hedef sıra ≤ <?= $bek['min_sira'] ?></div>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <button type="submit" name="beklenti_guncelle" class="btn btn-gold">
                    <i class="fa-solid fa-floppy-disk"></i> Beklentiyi Kaydet
                </button>
            </div>
        </form>
    </div>

    <?php endif; // kariyer durum ?>
<?php endif; // takimlar ?>

</div><!-- /container -->

<div class="footer-note font-oswald">
    V5.0.0 PHASE 5 — MENAJER KARİYER · GÜVEN · KOVULMA · İŞSİZ MOD · İŞ TEKLİFLERİ
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
