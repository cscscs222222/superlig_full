<?php
// ==============================================================================
// LIGUE 1 - KULÜP TESİSLERİ VE ALTYAPI MERKEZİ (BLUE & RED FRENCH THEME)
// ==============================================================================
include '../db.php';

function sutunEkleFr($pdo, $tablo, $sutun, $tip) {
    try {
        if($pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount() == 0)
            $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip");
    } catch(Throwable $e) {}
}

sutunEkleFr($pdo, 'fr_takimlar', 'stadyum_seviye', 'INT DEFAULT 1');
sutunEkleFr($pdo, 'fr_takimlar', 'altyapi_seviye', 'INT DEFAULT 1');

$ayar = $pdo->query("SELECT * FROM fr_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) { header("Location: ligue1.php"); exit; }

$takim = $pdo->query("SELECT * FROM fr_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
$mesaj = "";
$mesaj_tipi = "";

// STADYUM GELİŞTİRME
if(isset($_POST['stadyum_gelistir'])) {
    $mevcut_seviye = $takim['stadyum_seviye'] ?? 1;
    $maliyet = $mevcut_seviye * 18000000; // Her seviye 18 Milyon Euro
    if($mevcut_seviye >= 10) {
        $mesaj = "Stadyum maksimum seviyede (10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE fr_takimlar SET butce = butce - $maliyet, stadyum_seviye = stadyum_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Parc des Princes genişletildi! Yeni Seviye: " . ($mevcut_seviye + 1);
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet; $takim['stadyum_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Genişletme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPI GELİŞTİRME
if(isset($_POST['altyapi_gelistir'])) {
    $mevcut_seviye = $takim['altyapi_seviye'] ?? 1;
    $maliyet = $mevcut_seviye * 10000000;
    if($mevcut_seviye >= 10) {
        $mesaj = "Altyapı maksimum seviyede (10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE fr_takimlar SET butce = butce - $maliyet, altyapi_seviye = altyapi_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Altyapı modernize edildi! Yeni Seviye: " . ($mevcut_seviye + 1);
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet; $takim['altyapi_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Maliyet: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPIDAN OYUNCU ÇIKARMA (FRANSIZ İSİMLERİ)
if(isset($_POST['genc_cikar'])) {
    $scout_maliyeti = 1500000;
    if($takim['butce'] >= $scout_maliyeti) {
        $altyapi_lvl = $takim['altyapi_seviye'] ?? 1;
        $isimler = ['Antoine', 'Kylian', 'Lucas', 'Paul', 'Hugo', 'Olivier', 'Benjamin', 'Théo', 'Adrien', 'Mattéo'];
        $soyadlar = ['Martin', 'Bernard', 'Dubois', 'Thomas', 'Robert', 'Richard', 'Petit', 'Durand', 'Leroy', 'Moreau'];
        $yeni_isim = $isimler[array_rand($isimler)] . ' ' . $soyadlar[array_rand($soyadlar)];
        $mevkiler = ['K', 'D', 'OS', 'F'];
        $mevki = $mevkiler[array_rand($mevkiler)];
        $yas = rand(16, 19);
        $ovr = rand(55 + $altyapi_lvl * 2, 68 + $altyapi_lvl * 2);
        $fiyat = $ovr * $ovr * 2500;
        $pdo->exec("UPDATE fr_takimlar SET butce = butce - $scout_maliyeti WHERE id = $kullanici_takim_id");
        $stmt = $pdo->prepare("INSERT INTO fr_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Ligue 1', 0, 0)");
        $stmt->execute([$kullanici_takim_id, $yeni_isim, $mevki, $ovr, $yas, $fiyat]);
        $mesaj = "Fransız yeteneği keşfedildi! $yeni_isim ($mevki, OVR:$ovr, $yas yaş) akademiye katıldı!";
        $mesaj_tipi = "success";
        $takim['butce'] -= $scout_maliyeti;
    } else {
        $mesaj = "Yetersiz Bütçe! Scout maliyeti: €1.5M"; $mesaj_tipi = "danger";
    }
}

// BİLET GELİRİ
if(isset($_POST['bilet_geliri'])) {
    $stadyum_lvl = $takim['stadyum_seviye'] ?? 1;
    $kapasite = 20000 + $stadyum_lvl * 8000;
    $doluluk = rand(65, 95);
    $bilet_fiyati = rand(25, 60);
    $gelir = (int)($kapasite * ($doluluk/100) * $bilet_fiyati);
    $pdo->exec("UPDATE fr_takimlar SET butce = butce + $gelir WHERE id = $kullanici_takim_id");
    $mesaj = "Maç günü geliri kasaya eklendi! Kapasite: " . number_format($kapasite) . " | Doluluk: $doluluk% | Gelir: €" . number_format($gelir);
    $mesaj_tipi = "success";
    $takim['butce'] += $gelir;
}

// SPONSORLUK GELİRİ
if(isset($_POST['sponsorluk'])) {
    $gelir = rand(3000000, 12000000);
    $sponsorlar = ['Orange France', 'Accor Hotels', 'Renault', 'BNP Paribas', 'LVMH', 'Canal+', 'Air France'];
    $sponsor = $sponsorlar[array_rand($sponsorlar)];
    $pdo->exec("UPDATE fr_takimlar SET butce = butce + $gelir WHERE id = $kullanici_takim_id");
    $mesaj = "$sponsor sponsorluk anlaşması imzalandı! Kasamıza €" . number_format($gelir/1000000, 1) . "M girdi.";
    $mesaj_tipi = "success";
    $takim['butce'] += $gelir;
}

$stadyum_lvl = $takim['stadyum_seviye'] ?? 1;
$altyapi_lvl = $takim['altyapi_seviye'] ?? 1;

function paraFrT($sayi) {
    if($sayi >= 1000000) return "€" . number_format($sayi/1000000, 1) . "M";
    if($sayi >= 1000) return "€" . number_format($sayi/1000, 1) . "K";
    return "€" . $sayi;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tesisler | Ligue 1</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --fr-primary:#003f8a; --fr-secondary:#ef4135; --fr-gold:#d4af37; --bg:#0d0d0d; --panel:#1a1a1a; --border:rgba(0,63,138,0.25); --text:#f9fafb; --muted:#94a3b8; }
        body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(10,10,10,0.97); backdrop-filter:blur(24px); border-bottom:2px solid var(--fr-secondary); position:sticky; top:0; z-index:1000; padding:0 2rem; height:75px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:900; color:#fff; text-decoration:none; }
        .nav-brand i { color:var(--fr-secondary); }
        .nav-link-item { color:var(--muted); font-weight:600; font-size:0.95rem; padding:8px 16px; text-decoration:none; transition:0.2s; }
        .nav-link-item:hover { color:#fff; }
        .btn-ap { background:var(--fr-primary); color:#fff; font-weight:800; padding:8px 20px; border-radius:4px; text-decoration:none; border:none; transition:0.3s; cursor:pointer; }
        .btn-ap:hover { background:var(--fr-secondary); color:#fff; }
        .panel-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px; }
        .panel-header { padding:1rem 1.5rem; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.3); }
        .panel-header h5 { color:var(--fr-gold); margin:0; font-family:'Oswald',sans-serif; font-size:1rem; text-transform:uppercase; }
        .facility-card { background:rgba(0,63,138,0.07); border:1px solid rgba(0,63,138,0.2); border-radius:12px; padding:24px; height:100%; }
        .facility-icon { font-size:2.5rem; margin-bottom:12px; }
        .facility-title { font-family:'Oswald',sans-serif; font-size:1.3rem; font-weight:700; color:#fff; margin-bottom:8px; }
        .level-display { display:flex; gap:4px; margin:12px 0; }
        .level-dot { width:22px; height:8px; border-radius:4px; background:rgba(255,255,255,0.1); }
        .level-dot.active { background:var(--fr-primary); box-shadow:0 0 6px rgba(0,63,138,0.5); }
        .stat-line { display:flex; justify-content:space-between; font-size:0.85rem; color:var(--muted); margin-bottom:6px; }
        .stat-line span:last-child { color:#fff; font-weight:700; }
        .btn-upgrade { background:linear-gradient(135deg,var(--fr-primary),#0060c0); color:#fff; border:none; border-radius:8px; padding:12px 20px; font-weight:800; font-family:'Oswald',sans-serif; cursor:pointer; width:100%; transition:0.2s; margin-top:12px; font-size:0.9rem; }
        .btn-upgrade:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(0,63,138,0.4); }
        .btn-income { background:linear-gradient(135deg,#166534,#16a34a); color:#fff; border:none; border-radius:8px; padding:12px 20px; font-weight:800; font-family:'Oswald',sans-serif; cursor:pointer; width:100%; transition:0.2s; margin-top:8px; font-size:0.9rem; }
        .btn-income:hover { transform:translateY(-2px); box-shadow:0 4px 15px rgba(22,163,74,0.4); }
        .budget-display { background:rgba(0,63,138,0.15); border:1px solid rgba(0,63,138,0.3); border-radius:10px; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
    </style>
</head>
<body>
<nav class="pro-navbar">
    <a href="ligue1.php" class="nav-brand font-oswald"><i class="fa-solid fa-flag"></i> LIGUE 1</a>
    <div class="d-none d-lg-flex gap-2">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="ligue1.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Fikstür</a>
        <a href="l1_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
        <a href="l1_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
        <a href="l1_tesisler.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-building"></i> Tesisler</a>
        <a href="l1_basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Basın</a>
    </div>
    <a href="ligue1.php" class="btn-ap"><i class="fa-solid fa-arrow-left"></i> Fikstüre Dön</a>
</nav>

<div class="container py-4" style="max-width:1100px;">

    <?php if($mesaj): ?>
    <div class="alert alert-<?=$mesaj_tipi?> alert-dismissible fade show"><i class="fa-solid fa-info-circle me-2"></i><?=$mesaj?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="budget-display">
        <div>
            <div style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Transfer Bütçesi</div>
            <div class="font-oswald" style="font-size:1.8rem;color:var(--fr-gold);"><?=paraFrT($takim['butce']??0)?></div>
        </div>
        <div class="text-end">
            <div style="font-size:0.75rem;color:var(--muted);">Kulüp</div>
            <div style="font-weight:800;color:#fff;"><?=htmlspecialchars($takim['takim_adi'])?></div>
        </div>
    </div>

    <div class="row g-4">
        <!-- STADYUM -->
        <div class="col-md-6">
            <div class="facility-card">
                <div class="facility-icon">🏟️</div>
                <div class="facility-title">Stadyum</div>
                <p style="color:var(--muted);font-size:0.85rem;">Kapasiteyi artır, atmosferi güçlendir ve daha yüksek bilet geliri elde et.</p>
                <div class="level-display">
                    <?php for($i=1;$i<=10;$i++): ?>
                    <div class="level-dot <?=$i<=$stadyum_lvl?'active':''?>"></div>
                    <?php endfor; ?>
                </div>
                <div class="stat-line"><span>Mevcut Seviye</span><span><?=$stadyum_lvl?>/10</span></div>
                <div class="stat-line"><span>Kapasite</span><span><?=number_format(20000+$stadyum_lvl*8000)?></span></div>
                <div class="stat-line"><span>Geliştirme Maliyeti</span><span style="color:var(--fr-gold);">€<?=number_format($stadyum_lvl*18)?>M</span></div>
                <form method="POST">
                    <button type="submit" name="stadyum_gelistir" class="btn-upgrade" <?=$stadyum_lvl>=10?'disabled':''?>>
                        <i class="fa-solid fa-arrow-up me-2"></i>Stadyumu Genişlet
                    </button>
                </form>
                <form method="POST">
                    <button type="submit" name="bilet_geliri" class="btn-income">
                        <i class="fa-solid fa-ticket me-2"></i>Maç Günü Geliri Al
                    </button>
                </form>
            </div>
        </div>

        <!-- ALTYAPI -->
        <div class="col-md-6">
            <div class="facility-card">
                <div class="facility-icon">⚽</div>
                <div class="facility-title">Fransız Altyapısı</div>
                <p style="color:var(--muted);font-size:0.85rem;">Dünyaca ünlü Fransız akademisini güçlendir, gelecek yıldızları yetiştir.</p>
                <div class="level-display">
                    <?php for($i=1;$i<=10;$i++): ?>
                    <div class="level-dot <?=$i<=$altyapi_lvl?'active':''?>"></div>
                    <?php endfor; ?>
                </div>
                <div class="stat-line"><span>Mevcut Seviye</span><span><?=$altyapi_lvl?>/10</span></div>
                <div class="stat-line"><span>Max OVR Aralığı</span><span><?=57+$altyapi_lvl*2?>-<?=70+$altyapi_lvl*2?></span></div>
                <div class="stat-line"><span>Geliştirme Maliyeti</span><span style="color:var(--fr-gold);">€<?=number_format($altyapi_lvl*10)?>M</span></div>
                <form method="POST">
                    <button type="submit" name="altyapi_gelistir" class="btn-upgrade" <?=$altyapi_lvl>=10?'disabled':''?>>
                        <i class="fa-solid fa-graduation-cap me-2"></i>Altyapıyı Geliştir
                    </button>
                </form>
                <form method="POST">
                    <button type="submit" name="genc_cikar" class="btn-income" style="background:linear-gradient(135deg,#7e22ce,#9333ea);">
                        <i class="fa-solid fa-star me-2"></i>Fransız Yeteneği Keşfet (€1.5M)
                    </button>
                </form>
            </div>
        </div>

        <!-- SPONSORLUK -->
        <div class="col-12">
            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-handshake me-2"></i>Fransız Sponsorluk Anlaşmaları</h5></div>
                <div class="p-4">
                    <p style="color:var(--muted);">Orange, Renault, BNP Paribas gibi büyük Fransız markalarıyla sponsorluk anlaşması imzala. Sezon başı rastgele bir gelir garantisi!</p>
                    <div class="row g-3 mb-3">
                        <?php foreach([['Orange France','3M - 8M','📱'],['Renault Sport','4M - 9M','🚗'],['BNP Paribas','5M - 12M','🏦'],['Air France','4M - 10M','✈️']] as $s): ?>
                        <div class="col-md-3 col-6">
                            <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:14px;text-align:center;">
                                <div style="font-size:1.8rem;margin-bottom:6px;"><?=$s[2]?></div>
                                <div style="font-weight:700;color:#fff;font-size:0.9rem;"><?=$s[0]?></div>
                                <div style="color:var(--fr-gold);font-size:0.8rem;">€<?=$s[1]?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST">
                        <button type="submit" name="sponsorluk" class="btn-upgrade" style="background:linear-gradient(135deg,#b45309,#d97706);" onclick="return confirm('Sponsorluk anlaşması imzalanacak. Onaylıyor musunuz?')">
                            <i class="fa-solid fa-file-signature me-2"></i>Sponsorluk Anlaşması İmzala
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
