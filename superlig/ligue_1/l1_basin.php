<?php
// ==============================================================================
// LIGUE 1 - BASIN ODASI VE PSİKOLOJİ MERKEZİ (BLUE & RED FRENCH THEME)
// ==============================================================================
include '../db.php';

function sutunEkleBasinFr($pdo, $tablo, $sutun, $tip) {
    try {
        if($pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount() == 0)
            $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip");
    } catch(Throwable $e) {}
}
sutunEkleBasinFr($pdo, 'fr_oyuncular', 'moral', 'INT DEFAULT 80');
sutunEkleBasinFr($pdo, 'fr_ayar', 'son_basin_haftasi', 'INT DEFAULT 0');

$ayar = $pdo->query("SELECT * FROM fr_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_hafta = $ayar['hafta'] ?? 1;

if (!$kullanici_takim_id) { header("Location: ligue1.php"); exit; }

$takim = $pdo->query("SELECT * FROM fr_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
$mesaj = "";
$mesaj_tipi = "";

// PSİKOLOJİ MOTORU
if(isset($_POST['moralleri_guncelle'])) {
    $pdo->exec("UPDATE fr_oyuncular SET moral = LEAST(100, moral + 5) WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1");
    $pdo->exec("UPDATE fr_oyuncular SET moral = GREATEST(10, moral - 15) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr >= 80");
    $pdo->exec("UPDATE fr_oyuncular SET moral = GREATEST(10, moral - 5) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr < 80");
    $mesaj = "Fransız basını yedek kalan yıldızları konuşuyor! Moraller güncellendi.";
    $mesaj_tipi = "warning";
}

// TAKIM YEMEĞİ
if(isset($_POST['takim_yemegi'])) {
    $yemek_maliyeti = 500000;
    if($takim['butce'] >= $yemek_maliyeti) {
        $pdo->exec("UPDATE fr_takimlar SET butce = butce - $yemek_maliyeti WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE fr_oyuncular SET moral = LEAST(100, moral + 20) WHERE takim_id = $kullanici_takim_id");
        $mesaj = "Paris'in en lüks restoranında takım yemeği verildi! (+20 Moral, -€500K)";
        $mesaj_tipi = "success";
        $takim['butce'] -= $yemek_maliyeti;
    } else {
        $mesaj = "Bütçeniz yetersiz! Takım yemeği için €500K gerekiyor."; $mesaj_tipi = "danger";
    }
}

// BASIN İLE TOPLANTI
if(isset($_POST['cevap_ver'])) {
    $cevap_id = (int)$_POST['cevap_id'];
    if(($ayar['son_basin_haftasi'] ?? 0) == $guncel_hafta) {
        $mesaj = "Bu hafta zaten L'Équipe'e konuştunuz! Haftaya tekrar gelin."; $mesaj_tipi = "warning";
    } else {
        if($cevap_id == 1) {
            $pdo->exec("UPDATE fr_oyuncular SET moral = GREATEST(10, moral - 10) WHERE takim_id = $kullanici_takim_id");
            $pdo->exec("UPDATE fr_takimlar SET butce = butce + 1500000 WHERE id = $kullanici_takim_id");
            $mesaj = "Otoriter tavrınız Fransız basınında takdir edildi (+€1.5M Yönetim Desteği) ama oyuncular gerildi (-10 Moral).";
        } elseif($cevap_id == 2) {
            $pdo->exec("UPDATE fr_oyuncular SET moral = LEAST(100, moral + 15) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Takımı basının önüne atmadınız! Soyunma odasında harika bir atmosfer var (+15 Moral)!";
        } elseif($cevap_id == 3) {
            $pdo->exec("UPDATE fr_takimlar SET butce = GREATEST(0, butce - 800000) WHERE id = $kullanici_takim_id");
            $pdo->exec("UPDATE fr_oyuncular SET moral = LEAST(100, moral + 25) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Agresif açıklamalarınız taraftarı ateşledi (+25 Moral) ama FFF (Fransız Federasyonu) ceza kesti (-€800K)!";
        }
        $pdo->exec("UPDATE fr_ayar SET son_basin_haftasi = $guncel_hafta WHERE id = 1");
        $mesaj_tipi = "success";
        $takim = $pdo->query("SELECT * FROM fr_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
    }
}

$oyuncular = $pdo->query("SELECT * FROM fr_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY moral ASC")->fetchAll(PDO::FETCH_ASSOC);
$ortalama_moral = $pdo->query("SELECT AVG(moral) FROM fr_oyuncular WHERE takim_id = $kullanici_takim_id")->fetchColumn();
$ortalama_moral = $ortalama_moral ? round($ortalama_moral) : 80;

$basin_sorulari = [
    "PSG baskısı altında nasıl motivasyonu koruyorsunuz?",
    "Şampiyonlar Ligi hedefleriniz neler?",
    "Fransız oyuncuların kadrodaki rolü hakkında ne düşünüyorsunuz?",
    "Fikstür yoğunluğu kadronuzu nasıl etkiliyor?",
    "Coupe de France şampiyonluğu bu sezon önceliğiniz mi?",
];
$soru = $basin_sorulari[($guncel_hafta - 1) % count($basin_sorulari)];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basın Odası | Ligue 1</title>
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
        .moral-bar-bg { background:rgba(255,255,255,0.08); border-radius:4px; height:8px; overflow:hidden; }
        .moral-bar { height:8px; border-radius:4px; transition:width 0.5s; }
        .player-moral-row { display:flex; align-items:center; gap:12px; padding:10px 16px; border-bottom:1px solid rgba(255,255,255,0.04); }
        .player-moral-row:hover { background:rgba(0,63,138,0.06); }
        .press-question { background:rgba(0,63,138,0.15); border:1px solid rgba(0,63,138,0.4); border-radius:12px; padding:20px; margin-bottom:16px; }
        .press-question .q-text { font-size:1rem; font-weight:600; color:#fff; margin-bottom:16px; font-style:italic; }
        .answer-btn { display:flex; align-items:center; gap:12px; padding:12px 16px; border:1px solid rgba(255,255,255,0.1); border-radius:8px; margin-bottom:8px; cursor:pointer; background:rgba(255,255,255,0.03); text-align:left; color:#fff; width:100%; font-size:0.9rem; transition:0.2s; }
        .answer-btn:hover { background:rgba(0,63,138,0.2); border-color:var(--fr-primary); }
        .answer-icon { font-size:1.5rem; }
        .big-moral { font-family:'Oswald',sans-serif; font-size:4rem; font-weight:900; text-align:center; }
        .action-form { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:10px; padding:16px; }
        .btn-action { background:linear-gradient(135deg,var(--fr-primary),#0060c0); color:#fff; border:none; border-radius:8px; padding:12px 20px; font-weight:700; cursor:pointer; width:100%; transition:0.2s; }
        .btn-action:hover { transform:translateY(-2px); }
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
        <a href="l1_tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
        <a href="l1_basin.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-microphone"></i> Basın</a>
    </div>
    <a href="ligue1.php" class="btn-ap"><i class="fa-solid fa-arrow-left"></i> Fikstüre Dön</a>
</nav>

<div class="container py-4" style="max-width:1100px;">

    <?php if($mesaj): ?>
    <div class="alert alert-<?=$mesaj_tipi?> alert-dismissible fade show"><i class="fa-solid fa-info-circle me-2"></i><?=$mesaj?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- SOL SÜTUN: MORAL DURUMU -->
        <div class="col-lg-5">
            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-heart-pulse me-2"></i>Takım Morali</h5></div>
                <div class="p-4 text-center">
                    <?php
                    $moral_color = $ortalama_moral >= 70 ? '#4ade80' : ($ortalama_moral >= 50 ? '#fbbf24' : '#f87171');
                    ?>
                    <div class="big-moral" style="color:<?=$moral_color?>"><?=$ortalama_moral?></div>
                    <div style="color:var(--muted);font-size:0.85rem;margin-bottom:20px;">Ortalama Moral Puanı</div>
                    <form method="POST" class="action-form mb-3">
                        <button type="submit" name="moralleri_guncelle" class="btn-action">
                            <i class="fa-solid fa-chart-line me-2"></i>Moral Durumunu Güncelle
                        </button>
                    </form>
                    <form method="POST" class="action-form">
                        <div style="font-size:0.8rem;color:var(--muted);margin-bottom:8px;">Bütçe: €<?=number_format(($takim['butce']??0)/1000000, 1)?>M</div>
                        <button type="submit" name="takim_yemegi" class="btn-action" style="background:linear-gradient(135deg,#7e22ce,#9333ea);"
                            onclick="return confirm('Takım yemeği için €500K ödenecek. Onaylıyor musunuz?')">
                            <i class="fa-solid fa-utensils me-2"></i>Paris'te Takım Yemeği (€500K)
                        </button>
                    </form>
                </div>
                <div style="max-height:350px;overflow-y:auto;">
                    <?php foreach($oyuncular as $o): ?>
                    <div class="player-moral-row">
                        <div style="width:36px;height:36px;background:var(--fr-primary);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:'Oswald';font-weight:900;color:#fff;font-size:1rem;flex-shrink:0;"><?=$o['ovr']?></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;color:#fff;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?=htmlspecialchars($o['isim'])?></div>
                            <div class="moral-bar-bg mt-1">
                                <?php $mc = ($o['moral']??80)>=70?'#4ade80':(($o['moral']??80)>=50?'#fbbf24':'#f87171'); ?>
                                <div class="moral-bar" style="width:<?=($o['moral']??80)?>%;background:<?=$mc?>;"></div>
                            </div>
                        </div>
                        <div style="font-family:'Oswald';font-weight:700;color:<?=$mc?>;font-size:0.9rem;flex-shrink:0;"><?=$o['moral']??80?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- SAĞ SÜTUN: BASIN TOPLANTISI -->
        <div class="col-lg-7">
            <div class="panel-card">
                <div class="panel-header">
                    <h5><i class="fa-solid fa-newspaper me-2"></i>L'Équipe Basın Toplantısı — Hafta <?=$guncel_hafta?></h5>
                </div>
                <div class="p-4">
                    <?php if(($ayar['son_basin_haftasi']??0) == $guncel_hafta): ?>
                    <div style="background:rgba(255,193,7,0.1);border:1px solid rgba(255,193,7,0.3);border-radius:10px;padding:16px;text-align:center;color:#fbbf24;">
                        <i class="fa-solid fa-check-circle fa-2x mb-2"></i>
                        <div style="font-weight:700;">Bu hafta L'Équipe ile konuştunuz!</div>
                        <div style="font-size:0.85rem;margin-top:4px;color:var(--muted);">Haftaya yeni sorular için gelin.</div>
                    </div>
                    <?php else: ?>
                    <div class="press-question">
                        <div style="font-size:0.75rem;color:var(--fr-secondary);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:700;">📰 L'Équipe Sorusu</div>
                        <div class="q-text">"<?=$soru?>"</div>
                        <form method="POST">
                            <input type="hidden" name="cevap_id" value="1">
                            <button type="submit" name="cevap_ver" class="answer-btn">
                                <span class="answer-icon">💼</span>
                                <div>
                                    <div style="font-weight:700;">Otoriter Cevap</div>
                                    <div style="font-size:0.8rem;color:var(--muted);">+€1.5M Yönetim Desteği, -10 Moral</div>
                                </div>
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="cevap_id" value="2">
                            <button type="submit" name="cevap_ver" class="answer-btn">
                                <span class="answer-icon">🤝</span>
                                <div>
                                    <div style="font-weight:700;">Takımı Koruyan Cevap</div>
                                    <div style="font-size:0.8rem;color:var(--muted);">+15 Moral, para yok</div>
                                </div>
                            </button>
                        </form>
                        <form method="POST">
                            <input type="hidden" name="cevap_id" value="3">
                            <button type="submit" name="cevap_ver" class="answer-btn">
                                <span class="answer-icon">🔥</span>
                                <div>
                                    <div style="font-weight:700;">Agresif Cevap</div>
                                    <div style="font-size:0.8rem;color:var(--muted);">+25 Moral, -€800K FFF Cezası</div>
                                </div>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- PSİKOLOJİ İPUÇLARI -->
                    <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07);border-radius:10px;padding:16px;margin-top:16px;">
                        <div style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;font-weight:700;">⚽ Psikoloji İpuçları</div>
                        <div style="font-size:0.85rem;color:#94a3b8;line-height:1.8;">
                            • <span style="color:#fff;font-weight:700;">80+ Moral:</span> Oyuncular %10 daha iyi performans gösterir<br>
                            • <span style="color:#fff;font-weight:700;">50-79 Moral:</span> Normal performans<br>
                            • <span style="color:#fff;font-weight:700;">50 altı Moral:</span> Oyuncular transfer isteyebilir<br>
                            • <span style="color:var(--fr-gold);font-weight:700;">İlk 11 oyuncular</span> daha yüksek moral alır<br>
                            • <span style="color:var(--fr-secondary);font-weight:700;">Yedekte kalan yıldızlar</span> moral kaybeder
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
