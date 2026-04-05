<?php
// ==============================================================================
// LA LIGA - MEDYA, PSİKOLOJİ VE BASIN ODASI (RED & GOLD SPANISH THEME)
// ==============================================================================
include '../db.php';

function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}
sutunEkle($pdo, 'es_oyuncular', 'moral', 'INT DEFAULT 80');
sutunEkle($pdo, 'es_ayar', 'son_basin_haftasi', 'INT DEFAULT 0');

$ayar = $pdo->query("SELECT * FROM es_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_hafta = $ayar['hafta'] ?? 1;

if (!$kullanici_takim_id) { header("Location: la_liga.php"); exit; }

$takim = $pdo->query("SELECT * FROM es_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// --- PSİKOLOJİ MOTORU ---
if(isset($_POST['moralleri_guncelle'])) {
    $pdo->exec("UPDATE es_oyuncular SET moral = LEAST(100, moral + 5) WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1");
    $pdo->exec("UPDATE es_oyuncular SET moral = GREATEST(10, moral - 15) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr >= 80");
    $pdo->exec("UPDATE es_oyuncular SET moral = GREATEST(10, moral - 5) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr < 80");
    
    $mesaj = "İspanyol medyası yedek kalan yıldızları konuşuyor! Moraller güncellendi.";
    $mesaj_tipi = "warning";
}

// --- TAKIMA ESPANYOL YEMEĞİ ---
if(isset($_POST['takim_yemegi'])) {
    $yemek_maliyeti = 800000; // 800K Euro
    if($takim['butce'] >= $yemek_maliyeti) {
        $pdo->exec("UPDATE es_takimlar SET butce = butce - $yemek_maliyeti WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE es_oyuncular SET moral = LEAST(100, moral + 20) WHERE takim_id = $kullanici_takim_id");
        $mesaj = "Madrid'in en gözde restoranında paella ve tapas şöleni! (-€800K) Takım morali yukarı!";
        $mesaj_tipi = "success";
        $takim['butce'] -= $yemek_maliyeti;
    } else {
        $mesaj = "Bütçeniz yetersiz! Takım yemeği için €800K gerekiyor."; $mesaj_tipi = "danger";
    }
}

// --- İSPANYOL BASINI İLE TOPLANTI ---
if(isset($_POST['cevap_ver'])) {
    $cevap_id = $_POST['cevap_id'];
    
    if(($ayar['son_basin_haftasi'] ?? 0) == $guncel_hafta) {
        $mesaj = "Bu hafta zaten Marca'ya konuştunuz! Haftaya tekrar gelin."; $mesaj_tipi = "warning";
    } else {
        if($cevap_id == 1) {
            $pdo->exec("UPDATE es_oyuncular SET moral = GREATEST(10, moral - 10) WHERE takim_id = $kullanici_takim_id");
            $pdo->exec("UPDATE es_takimlar SET butce = butce + 1500000 WHERE id = $kullanici_takim_id");
            $mesaj = "Sert tutumunuz La Liga yönetiminden destek gördü (+€1.5M) ama oyuncular gerildi (-10 Moral).";
        } elseif($cevap_id == 2) {
            $pdo->exec("UPDATE es_oyuncular SET moral = LEAST(100, moral + 15) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Takımınızı Marca'nın önünde savundunuz! Soyunma odasında harika bir atmosfer var (+15 Moral)!";
        } elseif($cevap_id == 3) {
            $pdo->exec("UPDATE es_takimlar SET butce = GREATEST(0, butce - 800000) WHERE id = $kullanici_takim_id"); 
            $pdo->exec("UPDATE es_oyuncular SET moral = LEAST(100, moral + 25) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Agresif açıklamalarınız Bernabéu'yu ateşledi (+25 Moral) ama La Liga Disiplin Kurulu ceza kesti (-€800K)!";
        }
        
        $pdo->exec("UPDATE es_ayar SET son_basin_haftasi = $guncel_hafta WHERE id = 1");
        $mesaj_tipi = "success";
        $takim = $pdo->query("SELECT * FROM es_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC); 
    }
}

$oyuncular = $pdo->query("SELECT * FROM es_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY moral ASC")->fetchAll(PDO::FETCH_ASSOC);
$ortalama_moral = $pdo->query("SELECT AVG(moral) FROM es_oyuncular WHERE takim_id = $kullanici_takim_id")->fetchColumn();
$ortalama_moral = $ortalama_moral ? round($ortalama_moral) : 80;

function paraFormatla($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    return "€" . number_format($sayi / 1000, 0) . "K";
}

function getMoralEmoji($moral) {
    if($moral >= 80) return ['😁', 'text-success', 'Çok Mutlu'];
    if($moral >= 50) return ['🙂', 'text-warning', 'Normal'];
    if($moral >= 30) return ['😠', 'text-danger', 'Mutsuz'];
    return ['🤬', 'text-danger fw-bold', 'Transfer İstiyor!'];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prensa & Psicología | La Liga</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --es-primary: #c8102e;
            --es-secondary: #f5c518;
            --es-accent: #ff6b35;
            --bg-body: #150003;
            --bg-panel: #2a0008;
            --border-color: rgba(245, 197, 24, 0.2);
        }

        body { background-color: var(--bg-body); color: #f9fafb; font-family: 'Inter', sans-serif; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(26, 0, 8, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--es-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; }
        .nav-brand i { color: var(--es-secondary); }
        .nav-link-item { color: #f59e0b; font-weight: 600; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--es-secondary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.3); position: relative; }
        .hero-content { position: relative; z-index: 2; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.3); font-weight: 700;}

        .btn-action-primary { background: var(--es-secondary); color: var(--es-dark); font-weight: 900; padding: 10px 20px; border-radius: 4px; text-decoration: none; border: none; transition: 0.3s;}
        .btn-action-primary:hover { background: #ffd700; color: #000; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(245,197,24,0.4);}
        
        .press-option { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.3s; display: block; width: 100%; text-align: left; color: #fff;}
        .press-option:hover { background: rgba(245,197,24,0.1); border-color: var(--es-secondary); padding-left: 25px;}

        .player-row { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; transition: 0.2s;}
        .player-row:hover { background: rgba(255,255,255,0.03); }
        
        .crisis-alert { animation: pulse 2s infinite; border-left: 4px solid var(--es-primary); background: rgba(200,16,46,0.1);}
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(200,16,46,0.4); } 70% { box-shadow: 0 0 0 10px rgba(200,16,46,0); } 100% { box-shadow: 0 0 0 0 rgba(200,16,46,0); } }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="la_liga.php" class="nav-brand"><i class="fa-solid fa-sun"></i> <span class="font-oswald">LA LIGA</span></a>
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="la_liga.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="ll_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="ll_tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
            <a href="ll_basin.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--es-secondary);"><i class="fa-solid fa-microphone"></i> Medya & Psikoloji</a>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="hero-content container" style="max-width: 1400px;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem;">RUEDA DE PRENSA (BASIN ODASI)</h1>
                    <p class="text-white fs-5 mt-2 fw-bold opacity-75">Marca, AS, El País... İspanyol basını her şeyi konuşuyor. Sözlerini dikkatli seç.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="badge bg-dark border p-3 text-start d-inline-block" style="border-color: var(--es-secondary) !important;">
                        <div class="text-muted small fw-bold mb-1">Takım Moral Ortalaması</div>
                        <div class="d-flex align-items-center gap-3">
                            <h2 class="font-oswald m-0 text-white"><?= $ortalama_moral ?>%</h2>
                            <?php $bg_color = $ortalama_moral >= 70 ? '#22c55e' : ($ortalama_moral >= 40 ? '#f5c518' : '#c8102e'); ?>
                            <div style="width: 100px; height: 8px; background: rgba(255,255,255,0.2); border-radius: 4px;">
                                <div style="width: <?= $ortalama_moral ?>%; height: 100%; background: <?= $bg_color ?>; border-radius: 4px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? '#22c55e' : ($mesaj_tipi == 'warning' ? '#f59e0b' : 'var(--es-primary)') ?>; color: #000;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-xl-6">
                <div class="panel-card h-100">
                    <div class="panel-header" style="color: var(--es-secondary);">
                        <i class="fa-solid fa-microphone-lines me-2"></i> MARCA PRESS CONFERENCE (JORNADA <?= $guncel_hafta ?>)
                    </div>
                    <div class="p-4">
                        <?php if(($ayar['son_basin_haftasi'] ?? 0) == $guncel_hafta): ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem; margin-bottom: 15px;"></i>
                                <h4 class="font-oswald">RUEDA DE PRENSA TAMAMLANDI</h4>
                                <p class="text-muted">İspanyol basınına gerekli açıklamaları yaptınız.</p>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <div class="d-flex gap-3 mb-3">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--es-primary); display:flex; align-items:center; justify-content:center;">
                                        <i class="fa-solid fa-camera text-white"></i>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 0 15px 15px 15px;">
                                        <p class="m-0 text-white fw-bold">"Entrenador, los aficionados están exigiendo mejores resultados y hay rumores de tensiones en el vestuario. ¿Cómo gestiona esta presión en La Liga?" <br><span class="text-muted small">(Teknik direktör, taraftarlar daha iyi sonuçlar bekliyor ve soyunma odasında gerilim var. Bu baskıyı nasıl yönetiyorsunuz?)</span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="1">
                                    <strong>A)</strong> "Kimse bu kulüpten büyük değil. Beğenmeyen çıkabilir!" <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> +€1.5M Bütçe | <i class="fa-solid fa-arrow-trend-down text-danger"></i> -10 Moral</span>
                                </button>
                            </form>
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="2">
                                    <strong>B)</strong> "Oyuncularıma tam güveniyorum. Eleştirmenleri sahada susturacağız." <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> +15 Moral</span>
                                </button>
                            </form>
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="3">
                                    <strong>C)</strong> "Hakem kararları korkunçtu! Bize karşı oynanıyor, herkese söylüyorum!" <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> +25 Moral | <i class="fa-solid fa-arrow-trend-down text-danger"></i> -€800K Ceza</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="panel-card h-100">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <span style="color: #fff;"><i class="fa-solid fa-face-angry text-danger me-2"></i> VESTUARIO (Soyunma Odası)</span>
                        <form method="POST" class="m-0">
                            <button type="submit" name="moralleri_guncelle" class="btn btn-sm btn-outline-light" title="Moralleri Güncelle">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="p-3">
                        <form method="POST" class="mb-4">
                            <button type="submit" name="takim_yemegi" class="btn-action-primary w-100">
                                <i class="fa-solid fa-utensils"></i> Takım Yemeği Ver (€800K)
                            </button>
                        </form>

                        <div style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach($oyuncular as $o): 
                                $moral_data = getMoralEmoji($o['moral'] ?? 80);
                                $emoji = $moral_data[0]; $text_class = $moral_data[1]; $durum = $moral_data[2];
                                $is_crisis = (($o['moral'] ?? 80) < 30) ? "crisis-alert" : "";
                                $kadro_durum = ($o['ilk_11'] ?? 0) ? '<span class="badge bg-success">İlk 11</span>' : '<span class="badge bg-secondary">Yedek</span>';
                            ?>
                            <div class="player-row <?= $is_crisis ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="fs-4"><?= $emoji ?></div>
                                    <div>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($o['isim']) ?> <span class="text-muted small">(OVR: <?= $o['ovr'] ?>)</span></div>
                                        <div class="small <?= $text_class ?>"><?= $durum ?> • %<?= $o['moral'] ?? 80 ?> Moral</div>
                                    </div>
                                </div>
                                <div><?= $kadro_durum ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
