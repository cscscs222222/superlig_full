<?php
// ==============================================================================
// FAZ 4: TRANSFER SON GÜNÜ (DEADLINE DAY) - PANİK TEKLİFLERİ SİSTEMİ
// ==============================================================================
include 'db.php';

$mesaj = "";
$mesaj_tipi = "";

// --- Tablo Haritalaması ---
$tbl_oyuncu = [
    'tr' => 'oyuncular',    'cl' => 'cl_oyuncular', 'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular', 'de' => 'de_oyuncular', 'fr' => 'fr_oyuncular',
    'it' => 'it_oyuncular', 'pt' => 'pt_oyuncular',
];
$tbl_takim = [
    'tr' => 'takimlar',    'cl' => 'cl_takimlar', 'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar', 'fr' => 'fr_takimlar',
    'it' => 'it_takimlar', 'pt' => 'pt_takimlar',
];
$gecerli_liglar = array_keys($tbl_oyuncu);

// --- Kullanıcı takımını güvenli şekilde çeken yardımcı fonksiyon ---
function fetch_kullanici_takimi_dd($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    $ayar_stmt = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1");
    $ayar_id = $ayar_stmt ? $ayar_stmt->fetchColumn() : false;
    if (!$ayar_id) return null;
    $stmt = $pdo->prepare("SELECT id, takim_adi, logo, butce FROM $takim_tablo WHERE id = ?");
    $stmt->execute([$ayar_id]);
    $takim = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($takim) { $takim['kaynak'] = $lig_kodu; return $takim; }
    return null;
}

$benim_takimlarim = [];
$ayar_tablosu = [
    'tr' => 'ayar',    'pl' => 'pl_ayar',   'es' => 'es_ayar',
    'de' => 'de_ayar', 'it' => 'it_ayar',   'cl' => 'cl_ayar',
    'fr' => 'fr_ayar', 'pt' => 'pt_ayar',
];
foreach ($ayar_tablosu as $kod => $ayar_tbl) {
    try {
        $t = fetch_kullanici_takimi_dd($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
        if ($t) $benim_takimlarim[] = $t;
    } catch (Throwable $e) {}
}

// --- Transfer Penceresi Durumunu Çek ---
$pencere = null;
try {
    $pencere = $pdo->query("SELECT * FROM transfer_pencere ORDER BY sezon_yil DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

if (!$pencere) {
    $pencere = ['durum' => 'acik', 'sezon_yil' => date('Y'), 'acilis_hafta' => 1, 'kapanis_hafta' => 20];
}

// --- Transfer Penceresi Durumunu Değiştir (Admin Aksiyonu) ---
if (isset($_POST['pencere_degistir'])) {
    $yeni_durum = $_POST['yeni_durum'] ?? 'acik';
    if (in_array($yeni_durum, ['acik', 'kapanis', 'kapali'], true)) {
        try {
            $pdo->prepare("UPDATE transfer_pencere SET durum = ? WHERE sezon_yil = ?")->execute([$yeni_durum, $pencere['sezon_yil']]);
            $pencere['durum'] = $yeni_durum;
            $mesaj = "Transfer penceresi durumu güncellendi: " . htmlspecialchars(strtoupper($yeni_durum));
            $mesaj_tipi = "success";
        } catch (Throwable $e) {
            $mesaj = "Durum güncellenemedi."; $mesaj_tipi = "danger";
        }
    }
}

// --- Panik Teklifi Üret (Deadline Day AI Baskısı) ---
if (isset($_POST['panik_teklif_uret']) && !empty($benim_takimlarim)) {
    if ($pencere['durum'] === 'kapanis') {
        $yapay_zeka_kulupleri = [
            'Manchester City', 'Real Madrid', 'PSG', 'Bayern Munich',
            'Chelsea', 'Liverpool', 'Barcelona', 'Juventus', 'Inter Milan',
            'Atlético Madrid', 'Borussia Dortmund', 'Napoli', 'Arsenal',
        ];

        $uretilen = 0;
        foreach ($benim_takimlarim as $bt) {
            $oyuncu_tbl = $tbl_oyuncu[$bt['kaynak']];
            try {
                $oyuncular = $pdo->prepare(
                    "SELECT id, isim, ovr, fiyat FROM $oyuncu_tbl
                     WHERE takim_id = ? AND ovr >= 75
                     ORDER BY ovr DESC LIMIT 3"
                );
                $oyuncular->execute([$bt['id']]);
                $hedefler = $oyuncular->fetchAll(PDO::FETCH_ASSOC);

                foreach ($hedefler as $o) {
                    // Zaten bekleyen teklif varsa üretme
                    $mevcut = $pdo->prepare(
                        "SELECT id FROM deadline_panik_teklif
                         WHERE oyuncu_id = ? AND oyuncu_lig = ? AND durum = 'beklemede'"
                    );
                    $mevcut->execute([$o['id'], $bt['kaynak']]);
                    if ($mevcut->fetchColumn()) continue;

                    // Piyasa değerinin %120-160'ı arası rastgele teklif
                    $carpan = mt_rand(120, 160) / 100;
                    $teklif = (int)($o['fiyat'] * $carpan);
                    $teklif_eden = $yapay_zeka_kulupleri[array_rand($yapay_zeka_kulupleri)];

                    $hafta = 1;
                    try { $hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

                    $pdo->prepare(
                        "INSERT INTO deadline_panik_teklif
                         (sezon_yil, hafta, teklif_eden, oyuncu_id, oyuncu_isim, oyuncu_lig, teklif_tutari, durum)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'beklemede')"
                    )->execute([
                        $pencere['sezon_yil'], $hafta, $teklif_eden,
                        $o['id'], $o['isim'], $bt['kaynak'], $teklif,
                    ]);
                    $uretilen++;
                }
            } catch (Throwable $e) {}
        }
        $mesaj = "⚡ SON GÜN! $uretilen adet yapay zeka panik teklifi oluşturuldu!";
        $mesaj_tipi = "warning";
    } else {
        $mesaj = "Panik teklifleri yalnızca kapanış modunda (son 24 saat) oluşturulabilir.";
        $mesaj_tipi = "info";
    }
}

// --- Teklif Kabul / Ret ---
if (isset($_POST['teklif_cevap'])) {
    $teklif_id  = (int)($_POST['teklif_id'] ?? 0);
    $cevap      = $_POST['cevap'] ?? '';
    if ($teklif_id > 0 && in_array($cevap, ['kabul', 'ret'], true)) {
        try {
            $pdo->beginTransaction();
            $teklif = $pdo->prepare("SELECT * FROM deadline_panik_teklif WHERE id = ? AND durum = 'beklemede'");
            $teklif->execute([$teklif_id]);
            $tek = $teklif->fetch(PDO::FETCH_ASSOC);

            if ($tek && $cevap === 'kabul') {
                $lig = $tek['oyuncu_lig'];
                if (in_array($lig, $gecerli_liglar, true)) {
                    $oyuncu_tbl = $tbl_oyuncu[$lig];
                    $takim_tbl  = $tbl_takim[$lig];

                    // Oyuncuyu çek
                    $o_stmt = $pdo->prepare("SELECT * FROM $oyuncu_tbl WHERE id = ?");
                    $o_stmt->execute([$tek['oyuncu_id']]);
                    $oyuncu = $o_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($oyuncu) {
                        // Kulüp kasasına transferi yatır
                        $pdo->prepare("UPDATE $takim_tbl SET butce = butce + ? WHERE id = ?")->execute([
                            $tek['teklif_tutari'], $oyuncu['takim_id']
                        ]);
                        // Oyuncuyu sil
                        $pdo->prepare("DELETE FROM $oyuncu_tbl WHERE id = ?")->execute([$tek['oyuncu_id']]);
                    }
                }
                $pdo->prepare("UPDATE deadline_panik_teklif SET durum = 'kabul' WHERE id = ?")->execute([$teklif_id]);
                $pdo->commit();
                $mesaj = "✅ Teklif kabul edildi! " . htmlspecialchars($tek['oyuncu_isim']) . " " . htmlspecialchars($tek['teklif_eden']) . "'e transfer oldu.";
                $mesaj_tipi = "success";
            } elseif ($tek && $cevap === 'ret') {
                $pdo->prepare("UPDATE deadline_panik_teklif SET durum = 'ret' WHERE id = ?")->execute([$teklif_id]);
                $pdo->commit();
                $mesaj = "❌ Teklif reddedildi."; $mesaj_tipi = "info";
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mesaj = "İşlem sırasında hata oluştu."; $mesaj_tipi = "danger";
        }
    }
}

// --- Bekleyen Teklifleri Çek ---
$bekleyen_teklifler = [];
try {
    $bekleyen_teklifler = $pdo->query(
        "SELECT * FROM deadline_panik_teklif WHERE durum = 'beklemede' ORDER BY teklif_tutari DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// --- Son 10 Teklif Geçmişi ---
$gecmis_teklifler = [];
try {
    $gecmis_teklifler = $pdo->query(
        "SELECT * FROM deadline_panik_teklif WHERE durum != 'beklemede' ORDER BY created_at DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

function paraFormatla($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    if ($sayi >= 1000) return "€" . number_format($sayi / 1000, 1) . "K";
    return "€" . $sayi;
}

$durum_renk = ['acik' => '#10b981', 'kapanis' => '#f59e0b', 'kapali' => '#ef4444'];
$durum_etiket = ['acik' => 'AÇIK', 'kapanis' => 'SON 24 SAAT', 'kapali' => 'KAPALI'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deadline Day | Transfer Son Günü</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --dd-bg: #0a0a0a;
            --dd-panel: #111;
            --dd-red: #ef4444;
            --dd-gold: #d4af37;
            --dd-amber: #f59e0b;
            --border: rgba(239,68,68,0.3);
        }
        body { background: var(--dd-bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .dd-navbar { background: rgba(10,10,10,0.95); border-bottom: 2px solid var(--dd-red); padding: 0 2rem; height: 70px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }

        /* Deadline Day Banner */
        .dd-hero { background: linear-gradient(135deg, #1a0000 0%, #0a0a0a 100%); border-bottom: 2px solid var(--dd-red); padding: 2.5rem 2rem; text-align: center; position: relative; overflow: hidden; }
        .dd-hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at 50% 50%, rgba(239,68,68,0.15) 0%, transparent 70%); pointer-events: none; }
        .dd-hero h1 { font-family: 'Oswald', sans-serif; font-size: clamp(2rem,5vw,4rem); color: #fff; text-shadow: 0 0 30px rgba(239,68,68,0.6); letter-spacing: 4px; margin: 0; }
        .dd-hero p { color: #aaa; font-size: 1.1rem; margin-top: 0.5rem; }

        /* Countdown Timer */
        .countdown-wrap { display: flex; gap: 1.5rem; justify-content: center; margin: 1.5rem 0; }
        .countdown-box { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.4); border-radius: 12px; padding: 1rem 1.5rem; min-width: 90px; text-align: center; }
        .countdown-val { font-family: 'Oswald', sans-serif; font-size: 2.5rem; color: var(--dd-red); line-height: 1; }
        .countdown-lbl { font-size: 0.7rem; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-top: 4px; }

        /* Window Status Badge */
        .window-badge { display: inline-block; border-radius: 50px; padding: 8px 24px; font-family: 'Oswald', sans-serif; font-size: 1.3rem; font-weight: 700; letter-spacing: 2px; border: 2px solid; }

        /* Panel Card */
        .panel-card { background: var(--dd-panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 2rem; }
        .panel-hdr { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .panel-hdr i { color: var(--dd-red); }
        .panel-hdr h5 { margin: 0; font-family: 'Oswald', sans-serif; color: #fff; font-size: 1.1rem; letter-spacing: 1px; }

        /* Teklif kartları */
        .teklif-card { background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.25); border-radius: 10px; padding: 1rem 1.25rem; margin: 0.75rem 1rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
        .teklif-card.kabul { border-color: rgba(16,185,129,0.4); background: rgba(16,185,129,0.06); }
        .teklif-card.ret { border-color: rgba(100,100,100,0.3); background: rgba(0,0,0,0.3); opacity: 0.6; }
        .teklif-tutari { font-family: 'Oswald', sans-serif; font-size: 1.6rem; color: var(--dd-amber); }
        .teklif-oyuncu { font-weight: 700; font-size: 1.05rem; }
        .teklif-eden { color: #aaa; font-size: 0.9rem; }
        .btn-kabul { background: #10b981; border: none; color: #fff; font-weight: 700; padding: 7px 18px; border-radius: 6px; }
        .btn-kabul:hover { background: #059669; color: #fff; }
        .btn-ret { background: transparent; border: 1px solid #555; color: #aaa; font-weight: 700; padding: 7px 18px; border-radius: 6px; }
        .btn-ret:hover { border-color: #ef4444; color: #ef4444; }

        /* Pencere kontrol butonları */
        .btn-pencere { font-family: 'Oswald', sans-serif; font-weight: 700; border: none; padding: 10px 22px; border-radius: 6px; font-size: 1rem; letter-spacing: 1px; transition: 0.2s; }
        .btn-pencere:hover { transform: scale(1.04); }

        .btn-action-outline { background: transparent; border: 1px solid var(--dd-gold); color: var(--dd-gold); font-weight: 700; padding: 8px 18px; border-radius: 4px; text-decoration: none; transition: 0.3s; font-size: 0.9rem; }
        .btn-action-outline:hover { background: var(--dd-gold); color: #000; }

        .empty-state { padding: 3rem; text-align: center; color: #555; font-family: 'Oswald', sans-serif; font-size: 1.2rem; }
    </style>
</head>
<body>

<nav class="dd-navbar">
    <div class="font-oswald fs-5 fw-bold" style="color:var(--dd-red); text-shadow:0 0 10px rgba(239,68,68,0.5);">
        <i class="fa-solid fa-clock-rotate-left me-2"></i>DEADLINE DAY
    </div>
    <div class="d-flex gap-2">
        <a href="global_transfer.php" class="btn-action-outline"><i class="fa-solid fa-globe me-1"></i>Transfer Borsası</a>
        <a href="index.php" class="btn-action-outline"><i class="fa-solid fa-house me-1"></i>Ana Merkez</a>
    </div>
</nav>

<!-- HERO BANNER -->
<div class="dd-hero">
    <?php $renk = $durum_renk[$pencere['durum']] ?? '#10b981'; ?>
    <div class="mb-3">
        <span class="window-badge" style="color:<?= $renk ?>; border-color:<?= $renk ?>; text-shadow:0 0 15px <?= $renk ?>50;">
            <i class="fa-solid fa-circle-dot me-2" style="font-size:0.8rem;"></i>
            TRANSFER PENCERESİ: <?= $durum_etiket[$pencere['durum']] ?? 'BİLİNMİYOR' ?>
        </span>
    </div>
    <h1>⚡ TRANSFER SON GÜNÜ</h1>
    <p>Yapay zeka kulüpleri saldırmaya hazır. Teklifleri değerlendir, kadronunu koru!</p>

    <?php if ($pencere['durum'] === 'kapanis'): ?>
    <!-- Geri sayım sayacı - kapanis modunda göster -->
    <div class="countdown-wrap" id="countdown-container">
        <div class="countdown-box"><div class="countdown-val" id="cd-hr">23</div><div class="countdown-lbl">Saat</div></div>
        <div class="countdown-box"><div class="countdown-val" id="cd-min">59</div><div class="countdown-lbl">Dakika</div></div>
        <div class="countdown-box"><div class="countdown-val" id="cd-sec">59</div><div class="countdown-lbl">Saniye</div></div>
    </div>
    <?php endif; ?>
</div>

<div class="container py-4" style="max-width:1200px;">

    <?php if ($mesaj): ?>
        <div class="alert border-0 fw-bold text-center mb-4" style="background:<?= $mesaj_tipi=='success'?'#10b981':($mesaj_tipi=='danger'?'#ef4444':($mesaj_tipi=='warning'?'#f59e0b':'#3b82f6')) ?>; color:<?= $mesaj_tipi=='warning'?'#000':'#fff' ?>;">
            <?= htmlspecialchars($mesaj) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sol: Pencere Kontrol -->
        <div class="col-lg-4">
            <div class="panel-card">
                <div class="panel-hdr"><i class="fa-solid fa-sliders"></i><h5>Pencere Yönetimi</h5></div>
                <div class="p-3">
                    <p class="text-muted small mb-3">Transfer penceresinin mevcut durumunu değiştir. "Kapanış" modu son 24 saati simüle eder ve panik teklifleri aktif olur.</p>
                    <form method="POST" class="d-flex flex-column gap-2">
                        <button type="submit" name="pencere_degistir" value="1"
                                style="--btn-bg:#10b981;"
                                onclick="this.form.elements['yeni_durum'].value='acik';"
                                class="btn-pencere" style="background:#10b981; color:#fff;">
                            <i class="fa-solid fa-door-open me-2"></i>Pencere Aç
                        </button>
                        <button type="submit" name="pencere_degistir" value="1"
                                onclick="this.form.elements['yeni_durum'].value='kapanis';"
                                class="btn-pencere" style="background:#f59e0b; color:#000;">
                            <i class="fa-solid fa-hourglass-half me-2"></i>Son 24 Saate Gir
                        </button>
                        <button type="submit" name="pencere_degistir" value="1"
                                onclick="this.form.elements['yeni_durum'].value='kapali';"
                                class="btn-pencere" style="background:#ef4444; color:#fff;">
                            <i class="fa-solid fa-lock me-2"></i>Pencereyi Kapat
                        </button>
                        <input type="hidden" name="yeni_durum" value="acik">
                    </form>

                    <?php if ($pencere['durum'] === 'kapanis'): ?>
                    <hr style="border-color:rgba(239,68,68,0.3); margin:1rem 0;">
                    <form method="POST">
                        <button type="submit" name="panik_teklif_uret" class="btn-pencere w-100"
                                style="background:linear-gradient(45deg,#7c0000,#ef4444); color:#fff; font-size:0.95rem; box-shadow:0 0 15px rgba(239,68,68,0.4);">
                            <i class="fa-solid fa-bolt me-2"></i>PANİK TEKLİFLERİ ÜRET
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kulüp Bütçe Özeti -->
            <?php if (!empty($benim_takimlarim)): ?>
            <div class="panel-card">
                <div class="panel-hdr"><i class="fa-solid fa-wallet"></i><h5>Kulüp Kasası</h5></div>
                <div class="p-3">
                    <?php foreach ($benim_takimlarim as $bt): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                        <div style="font-size:0.85rem; font-weight:600;">
                            <img src="<?= htmlspecialchars($bt['logo']) ?>" style="width:18px;height:18px;object-fit:contain;margin-right:6px;">
                            <?= htmlspecialchars($bt['takim_adi']) ?>
                        </div>
                        <div class="font-oswald" style="color:var(--dd-gold); font-size:1.1rem;">
                            <?= paraFormatla($bt['butce']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sağ: Teklifler -->
        <div class="col-lg-8">
            <!-- Bekleyen Panik Teklifleri -->
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-bell" style="color:var(--dd-amber);"></i>
                    <h5 style="color:var(--dd-amber);">Bekleyen Panik Teklifleri</h5>
                    <?php if (!empty($bekleyen_teklifler)): ?>
                        <span class="badge rounded-pill ms-2" style="background:var(--dd-red); font-family:'Oswald'; font-size:0.9rem;"><?= count($bekleyen_teklifler) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($bekleyen_teklifler)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox fa-2x mb-3" style="color:#333;"></i><br>
                        <?php if ($pencere['durum'] === 'kapanis'): ?>
                            Henüz panik teklifi yok. "Panik Teklifleri Üret" butonuna bas!
                        <?php else: ?>
                            Panik teklifleri yalnızca "Son 24 Saat" modunda aktif olur.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($bekleyen_teklifler as $tek): ?>
                    <div class="teklif-card">
                        <div class="flex-grow-1">
                            <div class="teklif-oyuncu"><?= htmlspecialchars($tek['oyuncu_isim']) ?></div>
                            <div class="teklif-eden">
                                <span class="badge" style="background:rgba(239,68,68,0.2); color:#ef4444; font-size:0.7rem; margin-right:6px;"><?= strtoupper($tek['oyuncu_lig']) ?></span>
                                <?= htmlspecialchars($tek['teklif_eden']) ?> teklif yapıyor
                            </div>
                        </div>
                        <div class="teklif-tutari"><?= paraFormatla($tek['teklif_tutari']) ?></div>
                        <div class="d-flex gap-2">
                            <form method="POST">
                                <input type="hidden" name="teklif_id" value="<?= $tek['id'] ?>">
                                <input type="hidden" name="cevap" value="kabul">
                                <button type="submit" name="teklif_cevap" class="btn-kabul">
                                    <i class="fa-solid fa-check me-1"></i>Kabul
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="teklif_id" value="<?= $tek['id'] ?>">
                                <input type="hidden" name="cevap" value="ret">
                                <button type="submit" name="teklif_cevap" class="btn-ret">
                                    <i class="fa-solid fa-xmark me-1"></i>Ret
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Geçmiş Teklifler -->
            <?php if (!empty($gecmis_teklifler)): ?>
            <div class="panel-card">
                <div class="panel-hdr"><i class="fa-solid fa-clock-rotate-left"></i><h5>Teklif Geçmişi</h5></div>
                <?php foreach ($gecmis_teklifler as $g): ?>
                <div class="teklif-card <?= $g['durum'] ?>">
                    <div class="flex-grow-1">
                        <div class="teklif-oyuncu" style="opacity:<?= $g['durum']==='ret'?'0.6':'1' ?>;">
                            <?= htmlspecialchars($g['oyuncu_isim']) ?>
                        </div>
                        <div class="teklif-eden"><?= htmlspecialchars($g['teklif_eden']) ?></div>
                    </div>
                    <div style="font-family:'Oswald'; font-size:1.3rem; color:<?= $g['durum']==='kabul'?'#10b981':'#555' ?>;">
                        <?= paraFormatla($g['teklif_tutari']) ?>
                    </div>
                    <span class="badge" style="background:<?= $g['durum']==='kabul'?'rgba(16,185,129,0.2)':'rgba(100,100,100,0.2)' ?>; color:<?= $g['durum']==='kabul'?'#10b981':'#888' ?>; font-family:'Oswald'; font-size:0.85rem; padding:6px 12px;">
                        <?= $g['durum'] === 'kabul' ? '✅ KABUL' : '❌ RET' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($pencere['durum'] === 'kapanis'): ?>
<script>
// Deadline Day geri sayım sayacı (simülasyon - 23:59:59'dan başlar)
let totalSec = 23 * 3600 + 59 * 60 + 59;
const hrEl  = document.getElementById('cd-hr');
const minEl = document.getElementById('cd-min');
const secEl = document.getElementById('cd-sec');

function tick() {
    if (totalSec <= 0) {
        hrEl.textContent = '00'; minEl.textContent = '00'; secEl.textContent = '00';
        return;
    }
    totalSec--;
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    hrEl.textContent  = String(h).padStart(2,'0');
    minEl.textContent = String(m).padStart(2,'0');
    secEl.textContent = String(s).padStart(2,'0');
}
setInterval(tick, 1000);
</script>
<?php endif; ?>
</body>
</html>
