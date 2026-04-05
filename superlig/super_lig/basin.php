<?php
// ==============================================================================
// SÜPER LİG - MEDYA, PSİKOLOJİ VE BASIN TOPLANTISI MERKEZİ (DARK RED THEME)
// ==============================================================================
include '../db.php';

// Güvenli Sütun Ekleme (Eğer yoksa Moral ve Basın Haftası sütunlarını ekler)
function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}
sutunEkle($pdo, 'oyuncular', 'moral', 'INT DEFAULT 80');
sutunEkle($pdo, 'ayar', 'son_basin_haftasi', 'INT DEFAULT 0');

$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_hafta = $ayar['hafta'] ?? 1;

if (!$kullanici_takim_id) { header("Location: superlig.php"); exit; }

$takim = $pdo->query("SELECT * FROM takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// --- PSİKOLOJİ MOTORU (Yedeklerin moralini düşür, oynayanlarınkini artır) ---
// Bu butona basıldığında takımın güncel durumuna göre moraller hesaplanır
if(isset($_POST['moralleri_guncelle'])) {
    // 11'de oynayanların morali artar
    $pdo->exec("UPDATE oyuncular SET moral = LEAST(100, moral + 5) WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1");
    // OVR'si yüksek olup (yıldız) yedek kalanların morali hızla düşer
    $pdo->exec("UPDATE oyuncular SET moral = GREATEST(10, moral - 15) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr >= 75");
    // Sıradan yedeklerin morali yavaş düşer
    $pdo->exec("UPDATE oyuncular SET moral = GREATEST(10, moral - 5) WHERE takim_id = $kullanici_takim_id AND yedek = 1 AND ovr < 75");
    
    $mesaj = "Takım psikolojisi güncellendi. Yıldız oyuncular yedek kalmaktan hiç hoşlanmıyor!";
    $mesaj_tipi = "warning";
}

// --- TAKIM YEMEĞİ (Bütçe harcayarak moral düzeltme) ---
if(isset($_POST['takim_yemegi'])) {
    $yemek_maliyeti = 500000; // 500 Bin Euro
    if($takim['butce'] >= $yemek_maliyeti) {
        $pdo->exec("UPDATE takimlar SET butce = butce - $yemek_maliyeti WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE oyuncular SET moral = LEAST(100, moral + 20) WHERE takim_id = $kullanici_takim_id");
        $mesaj = "Lüks bir restoranda takım yemeği verildi. Tüm oyuncuların morali fırladı! (-€500K)";
        $mesaj_tipi = "success";
        $takim['butce'] -= $yemek_maliyeti;
    } else {
        $mesaj = "Bütçeniz yetersiz! Takım yemeği için €500K gerekiyor."; $mesaj_tipi = "danger";
    }
}

// --- BASIN TOPLANTISI CEVAPLARI ---
if(isset($_POST['cevap_ver'])) {
    $cevap_id = $_POST['cevap_id'];
    
    if($ayar['son_basin_haftasi'] == $guncel_hafta) {
        $mesaj = "Bu hafta zaten basına konuştunuz! Haftaya tekrar gelin."; $mesaj_tipi = "warning";
    } else {
        // Cevaba göre senaryolar
        if($cevap_id == 1) {
            // "Formayı hak eden giyer" (Otoriter)
            $pdo->exec("UPDATE oyuncular SET moral = GREATEST(10, moral - 10) WHERE takim_id = $kullanici_takim_id");
            $pdo->exec("UPDATE takimlar SET butce = butce + 1000000 WHERE id = $kullanici_takim_id"); // Yönetim otoriteyi sever, bütçe verir
            $mesaj = "Sert tavrınız yönetimin hoşuna gitti (+€1M Bütçe), ancak oyuncular biraz gerildi (-10 Moral).";
        } elseif($cevap_id == 2) {
            // "Oyuncularımla aileyiz" (Babacan)
            $pdo->exec("UPDATE oyuncular SET moral = LEAST(100, moral + 15) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Basın önünde takımı korumanız soyunma odasında harika bir atmosfer yarattı (+15 Moral)!";
        } elseif($cevap_id == 3) {
            // "Hakemler ve sistem bize karşı!" (Agresif)
            $pdo->exec("UPDATE takimlar SET butce = GREATEST(0, butce - 500000) WHERE id = $kullanici_takim_id"); // Ceza yer
            $pdo->exec("UPDATE oyuncular SET moral = LEAST(100, moral + 25) WHERE takim_id = $kullanici_takim_id");
            $mesaj = "Hedef şaşırtarak takımı motive ettiniz (+25 Moral) ama TFF size ceza kesti (-€500K Bütçe)!";
        }
        
        $pdo->exec("UPDATE ayar SET son_basin_haftasi = $guncel_hafta WHERE id = 1");
        $mesaj_tipi = "success";
        $takim = $pdo->query("SELECT * FROM takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC); // Bütçeyi yenile
    }
}

// Oyuncuları Çek
$oyuncular = $pdo->query("SELECT * FROM oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY moral ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ortalama Moral
$ortalama_moral = $pdo->query("SELECT AVG(moral) FROM oyuncular WHERE takim_id = $kullanici_takim_id")->fetchColumn();
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
    <title>Medya ve Psikoloji | Süper Lig</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sl-primary: #1e1b4b; 
            --sl-secondary: #e11d48; 
            --sl-accent: #facc15; 
            --bg-body: #0f172a;
            --bg-panel: #1e293b;
            --border-color: rgba(255,255,255,0.1);
        }

        body { background-color: var(--bg-body); color: #f8fafc; font-family: 'Inter', sans-serif; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(15,23,42,0.95); border-bottom: 2px solid var(--sl-secondary); padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center; position: sticky; top:0; z-index:1000;}
        .nav-brand { font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; }
        .nav-brand i { color: var(--sl-secondary); }
        .nav-link-item { color: #94a3b8; font-weight: 600; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: url('https://images.unsplash.com/photo-1541534401786-2077eed87a74?q=80&w=2000') center/cover; position: relative; }
        .hero-overlay { position: absolute; top:0; left:0; width:100%; height:100%; background: linear-gradient(90deg, rgba(15,23,42,0.95) 0%, rgba(225,29,72,0.6) 100%); z-index: 1;}
        .hero-content { position: relative; z-index: 2; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); font-weight: 700;}

        .btn-action-primary { background: var(--sl-secondary); color: #fff; font-weight: 800; padding: 10px 20px; border-radius: 4px; text-decoration: none; border: none; transition: 0.3s;}
        .btn-action-primary:hover { background: #be123c; color: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(225,29,72,0.4);}
        
        .btn-gold { background: var(--sl-accent); color: #000; font-weight: 800; padding: 10px 20px; border-radius: 4px; border: none; transition: 0.3s;}
        .btn-gold:hover { background: #fff; transform: translateY(-2px);}

        /* Basın Toplantısı Seçenekleri */
        .press-option { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: 0.3s; display: block; width: 100%; text-align: left; color: #fff;}
        .press-option:hover { background: rgba(225,29,72,0.1); border-color: var(--sl-secondary); padding-left: 25px;}

        .moral-bar-container { background: rgba(0,0,0,0.5); height: 12px; border-radius: 6px; overflow: hidden; margin-top: 10px;}
        .moral-fill { height: 100%; transition: width 1s ease-in-out; }

        .player-row { border-bottom: 1px solid rgba(255,255,255,0.05); padding: 12px 15px; display: flex; align-items: center; justify-content: space-between; transition: 0.2s;}
        .player-row:hover { background: rgba(255,255,255,0.03); }
        
        .crisis-alert { animation: pulse 2s infinite; border-left: 4px solid #ef4444; background: rgba(239,68,68,0.1);}
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); } 70% { box-shadow: 0 0 0 10px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }

    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="superlig.php" class="nav-brand"><i class="fa-solid fa-moon"></i> <span class="font-oswald">SÜPER LİG</span></a>
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="superlig.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
            <a href="basin.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--sl-secondary);"><i class="fa-solid fa-microphone"></i> Medya & Psikoloji</a>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="hero-overlay"></div>
        <div class="hero-content text-start container" style="max-width: 1400px;">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem;">BASIN ODASI VE SOYUNMA ODASI</h1>
                    <p class="text-white fs-5 mt-2 fw-bold opacity-75">Kelimeleriniz sahaya yansır. Krizi yönetin veya yeni bir kriz yaratın.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="badge bg-dark border border-secondary p-3 text-start d-inline-block">
                        <div class="text-muted small fw-bold mb-1">Takım Moral Ortalaması</div>
                        <div class="d-flex align-items-center gap-3">
                            <h2 class="font-oswald m-0 text-white"><?= $ortalama_moral ?>%</h2>
                            <?php 
                                $bg_color = $ortalama_moral >= 70 ? '#10b981' : ($ortalama_moral >= 40 ? '#facc15' : '#ef4444');
                            ?>
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
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? '#10b981' : ($mesaj_tipi == 'warning' ? '#f59e0b' : '#ef4444') ?>; color: <?= $mesaj_tipi == 'warning' ? '#000' : '#fff' ?>;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-xl-6">
                <div class="panel-card h-100">
                    <div class="panel-header" style="color: var(--sl-accent);">
                        <i class="fa-solid fa-microphone-lines me-2"></i> HAFTALIK BASIN TOPLANTISI (HAFTA <?= $guncel_hafta ?>)
                    </div>
                    <div class="p-4">
                        <?php if($ayar['son_basin_haftasi'] == $guncel_hafta): ?>
                            <div class="text-center py-5">
                                <i class="fa-solid fa-circle-check text-success" style="font-size: 4rem; margin-bottom: 15px;"></i>
                                <h4 class="font-oswald">AÇIKLAMA YAPILDI</h4>
                                <p class="text-muted">Bu haftaki basın toplantısını tamamladınız. Basın mensupları haftaya tekrar gelecek.</p>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <div class="d-flex gap-3 mb-3">
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #fff; display:flex; align-items:center; justify-content:center;">
                                        <i class="fa-solid fa-camera text-dark"></i>
                                    </div>
                                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 0 15px 15px 15px;">
                                        <p class="m-0 text-white fw-bold">"Sayın Menajer, takım içindeki atmosfere dair çeşitli dedikodular var. Bazı yıldızların yedek kaldığı için sorun çıkardığı konuşuluyor. Taraftara ve basına ne söylemek istersiniz?"</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="1">
                                    <strong>A)</strong> "Burada formayı hak eden giyer. İsimlerin bir önemi yok!" <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> Yönetim Bütçesi | <i class="fa-solid fa-arrow-trend-down text-danger"></i> Takım Morali</span>
                                </button>
                            </form>
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="2">
                                    <strong>B)</strong> "Biz büyük bir aileyiz, aramızda hiçbir sorun yok." <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> Takım Morali</span>
                                </button>
                            </form>
                            <form method="POST">
                                <button type="submit" name="cevap_ver" class="press-option">
                                    <input type="hidden" name="cevap_id" value="3">
                                    <strong>C)</strong> "Hakemler ve fikstür bizi engellemeye çalışıyor ama yıkılmayacağız!" <br>
                                    <span class="text-muted small"><i class="fa-solid fa-arrow-trend-up text-success"></i> Çok Yüksek Moral | <i class="fa-solid fa-arrow-trend-down text-danger"></i> TFF Para Cezası</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="panel-card h-100">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <span style="color: #fff;"><i class="fa-solid fa-face-angry text-danger me-2"></i> SOYUNMA ODASI DURUMU</span>
                        <form method="POST" class="m-0">
                            <button type="submit" name="moralleri_guncelle" class="btn btn-sm btn-outline-secondary text-white" title="Moralleri Yedeklere Göre Hesapla">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                        </form>
                    </div>
                    
                    <div class="p-3">
                        <form method="POST" class="mb-4">
                            <button type="submit" name="takim_yemegi" class="btn-action-primary w-100">
                                <i class="fa-solid fa-utensils"></i> Takım Yemeği Ver (€500K) - Moralleri Yükselt
                            </button>
                        </form>

                        <h6 class="font-oswald text-muted border-bottom pb-2">OYUNCU PSİKOLOJİLERİ</h6>
                        
                        <div style="max-height: 350px; overflow-y: auto; padding-right: 5px;">
                            <?php foreach($oyuncular as $o): 
                                $moral_data = getMoralEmoji($o['moral']);
                                $emoji = $moral_data[0];
                                $text_class = $moral_data[1];
                                $durum = $moral_data[2];
                                
                                $is_crisis = ($o['moral'] < 30) ? "crisis-alert" : "";
                                $kadro_durum = $o['ilk_11'] ? '<span class="badge bg-success">İlk 11</span>' : '<span class="badge bg-secondary">Yedek</span>';
                            ?>
                            <div class="player-row <?= $is_crisis ?>">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="fs-4"><?= $emoji ?></div>
                                    <div>
                                        <div class="fw-bold text-white"><?= htmlspecialchars($o['isim']) ?> <span class="text-muted small">(OVR: <?= $o['ovr'] ?>)</span></div>
                                        <div class="small <?= $text_class ?>"><?= $durum ?> • %<?= $o['moral'] ?> Moral</div>
                                    </div>
                                </div>
                                <div>
                                    <?= $kadro_durum ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>