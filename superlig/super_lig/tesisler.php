<?php
// ==============================================================================
// SÜPER LİG - KULÜP TESİSLERİ VE ALTYAPI MERKEZİ (SÜTUN HATASI DÜZELTİLDİ)
// ==============================================================================
include '../db.php';

// Veritabanına yeni Tesis ve Lig sütunlarını güvenli şekilde ekle
function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

// EKSİK SÜTUNLARI OTOMATİK OLUŞTUR (HATA BURADAN ÇÖZÜLÜYOR)
sutunEkle($pdo, 'takimlar', 'stadyum_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'takimlar', 'altyapi_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'takimlar', 'saglik_merkezi_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Süper Lig'");
sutunEkle($pdo, 'oyuncular', 'play_styles', "VARCHAR(255) DEFAULT NULL");
sutunEkle($pdo, 'oyuncular', 'ulke', "VARCHAR(60) DEFAULT 'Türkiye'");
sutunEkle($pdo, 'oyuncular', 'sakatlik_turu', "VARCHAR(100) DEFAULT NULL");

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) { header("Location: superlig.php"); exit; }

$takim = $pdo->query("SELECT * FROM takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// STADYUM GELİŞTİRME
if(isset($_POST['stadyum_gelistir'])) {
    $mevcut_seviye = $takim['stadyum_seviye'];
    $maliyet = $mevcut_seviye * 15000000; // Her seviye 15 Milyon Euro artar
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Stadyum zaten maksimum (10) seviyede!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE takimlar SET butce = butce - $maliyet, stadyum_seviye = stadyum_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Stadyum genişletildi! Yeni Seviye: " . ($mevcut_seviye + 1) . ". Maç günü gelirleri arttı.";
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['stadyum_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Stadyum genişletme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPI GELİŞTİRME
if(isset($_POST['altyapi_gelistir'])) {
    $mevcut_seviye = $takim['altyapi_seviye'];
    $maliyet = $mevcut_seviye * 10000000; // Her seviye 10 Milyon Euro artar
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Altyapı tesisleri maksimum (10) seviyede!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE takimlar SET butce = butce - $maliyet, altyapi_seviye = altyapi_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Altyapı modernize edildi! Yeni Seviye: " . ($mevcut_seviye + 1) . ". Artık daha potansiyelli gençler çıkacak.";
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['altyapi_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Altyapı geliştirme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPIDAN OYUNCU ÇIKARMA (SCOUTİNG)
if(isset($_POST['genc_cikar'])) {
    $scout_maliyeti = 1500000; // 1.5 Milyon Euro
    
    if($takim['butce'] >= $scout_maliyeti) {
        $altyapi_lvl = $takim['altyapi_seviye'];
        
        // İsim Havuzu
        $isimler = ['Can', 'Emre', 'Burak', 'Ali', 'Ege', 'Arda', 'Kerem', 'Kenan', 'Ozan', 'Semih', 'Uğur', 'Deniz', 'Cem', 'Volkan', 'Barış'];
        $soyadlar = ['Yılmaz', 'Kılıç', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Öztürk', 'Arslan', 'Doğan', 'Kaya', 'Koç', 'Kurt', 'Güneş', 'Korkmaz'];
        $yeni_isim = $isimler[array_rand($isimler)] . ' ' . $soyadlar[array_rand($soyadlar)];
        
        $mevkiler = ['K', 'D', 'OS', 'F'];
        $mevki = $mevkiler[array_rand($mevkiler)];
        
        $yas = rand(16, 18); // Sadece gençler
        
        // OVR Hesaplama (Altyapı seviyesine göre kalite artar)
        $min_ovr = 50 + ($altyapi_lvl * 2);
        $max_ovr = 60 + ($altyapi_lvl * 3);
        $ovr = rand($min_ovr, $max_ovr);
        
        // Efsane (Wonderkid) çıkma şansı (%5)
        if(rand(1,100) <= 5) {
            $ovr += rand(5, 10);
            $mesaj_ek = " BİR WONDERKID BULDUK!";
        } else {
            $mesaj_ek = "";
        }
        
        $fiyat = ($ovr * $ovr) * 1500;
        
        // Bütçeden düş ve oyuncuyu A Takım yedeklerine ekle
        $pdo->exec("UPDATE takimlar SET butce = butce - $scout_maliyeti WHERE id = $kullanici_takim_id");
        $stmt = $pdo->prepare("INSERT INTO oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek, ulke) VALUES (?, ?, ?, ?, ?, ?, 'Süper Lig', 0, 1, 'Türkiye')");
        $stmt->execute([$kullanici_takim_id, $yeni_isim, $mevki, $ovr, $yas, $fiyat]);
        
        $mesaj = "Altyapıdan yeni bir oyuncu A Takıma yükseldi: $yeni_isim (OVR: $ovr).$mesaj_ek";
        $mesaj_tipi = "success";
        $takim['butce'] -= $scout_maliyeti;
        
    } else {
        $mesaj = "Scout Maliyeti Yetersiz! Altyapı taraması için €1.5M gerekiyor."; $mesaj_tipi = "danger";
    }
}

// SAĞLIK MERKEZİ GELİŞTİRME (FAZ 3)
if(isset($_POST['saglik_gelistir'])) {
    $mevcut_seviye = $takim['saglik_merkezi_seviye'] ?? 1;
    $maliyet = $mevcut_seviye * 12000000; // Her seviye 12 Milyon Euro artar
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Sağlık Merkezi zaten maksimum (10) seviyede!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE takimlar SET butce = butce - $maliyet, saglik_merkezi_seviye = saglik_merkezi_seviye + 1 WHERE id = $kullanici_takim_id");
        $yeni_seviye = $mevcut_seviye + 1;
        $indirim = min(45, ($yeni_seviye - 1) * 5);
        $mesaj = "Sağlık Merkezi modernize edildi! Yeni Seviye: " . $yeni_seviye . ". Sakatlık süreleri %" . $indirim . " daha kısa!";
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['saglik_merkezi_seviye'] = $mevcut_seviye + 1;
    } else {
        $mesaj = "Yetersiz Bütçe! Sağlık Merkezi geliştirme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

function paraFormatla($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    return "€" . number_format($sayi / 1000, 0) . "K";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kulüp Tesisleri | Süper Lig</title>
    
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

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); text-align: center; }

        .budget-box { background: rgba(0,0,0,0.5); border: 1px solid var(--sl-accent); display: inline-block; padding: 15px 30px; border-radius: 12px; margin-top: 15px;}
        .budget-val { font-family: 'Oswald'; font-size: 2.5rem; color: var(--sl-accent); margin:0; line-height:1;}

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); text-align: center; padding: 30px; position: relative;}
        .panel-card:hover { border-color: var(--sl-secondary); }
        
        .icon-wrapper { width: 80px; height: 80px; background: rgba(225,29,72,0.1); border: 2px solid var(--sl-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--sl-secondary); margin: 0 auto 20px;}
        
        .level-text { font-family: 'Oswald'; font-size: 2rem; color: #fff; margin-bottom: 5px;}
        .level-badge { background: rgba(255,255,255,0.1); padding: 5px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; color: var(--sl-accent); margin-bottom: 20px; display: inline-block;}
        
        .btn-upgrade { background: var(--sl-secondary); color: #fff; font-weight: 800; border: none; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-upgrade:hover { background: #be123c; box-shadow: 0 0 15px rgba(225,29,72,0.5); transform: translateY(-2px);}
        
        .btn-scout { background: transparent; border: 2px solid var(--sl-accent); color: var(--sl-accent); font-weight: 800; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-scout:hover { background: var(--sl-accent); color: #000; box-shadow: 0 0 15px rgba(250,204,21,0.5); transform: translateY(-2px);}

    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="superlig.php" class="nav-brand"><i class="fa-solid fa-moon"></i> <span class="font-oswald">SÜPER LİG</span></a>
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Hub</a>
            <a href="superlig.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="transfer.php" class="nav-link-item"><i class="fa-solid fa-money-bill-transfer"></i> Pazar</a>
            <a href="tesisler.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--sl-secondary);"><i class="fa-solid fa-building"></i> Tesisler</a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem;">KULÜP YÖNETİMİ</h1>
        <p class="text-muted fs-5 mt-2 fw-bold">Geleceğe yatırım yapın, geliri ve genç yetenekleri artırın.</p>
        
        <div class="budget-box">
            <div class="text-muted fw-bold text-uppercase mb-1" style="font-size:0.8rem; letter-spacing:1px;">Güncel Kasa</div>
            <p class="budget-val"><?= paraFormatla($takim['butce']) ?></p>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1200px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-5" style="background: <?= $mesaj_tipi == 'success' ? '#10b981' : ($mesaj_tipi == 'warning' ? '#f59e0b' : '#ef4444') ?>; color: <?= $mesaj_tipi == 'warning' ? '#000' : '#fff' ?>;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 justify-content-center">
            
            <div class="col-lg-5 col-md-6">
                <div class="panel-card">
                    <div class="icon-wrapper"><i class="fa-solid fa-stadium"></i></div>
                    <h3 class="font-oswald text-white mb-1">STADYUM</h3>
                    <p class="text-muted small fw-bold mb-3">Maç günü bilet gelirlerini artırır.</p>
                    
                    <div class="level-text">LVL <?= $takim['stadyum_seviye'] ?></div>
                    <div class="level-badge">Kapasite: <?= number_format($takim['stadyum_seviye'] * 12500) ?> Kişi</div>
                    
                    <form method="POST" class="mt-3">
                        <button type="submit" name="stadyum_gelistir" class="btn-upgrade" onclick="return confirm('Stadyumu genişletmek için €<?= number_format(($takim['stadyum_seviye']*15), 1) ?>M harcanacak. Onaylıyor musunuz?');">
                            Genişlet (€<?= number_format(($takim['stadyum_seviye']*15), 1) ?>M)
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5 col-md-6">
                <div class="panel-card" style="border-color: rgba(250,204,21,0.3);">
                    <div class="icon-wrapper" style="background: rgba(250,204,21,0.1); border-color: var(--sl-accent); color: var(--sl-accent);"><i class="fa-solid fa-seedling"></i></div>
                    <h3 class="font-oswald text-white mb-1">GENÇLİK AKADEMİSİ</h3>
                    <p class="text-muted small fw-bold mb-3">Çıkan gençlerin (Regen) OVR kalitesini artırır.</p>
                    
                    <div class="level-text" style="color: var(--sl-accent);">LVL <?= $takim['altyapi_seviye'] ?></div>
                    <div class="level-badge" style="background: rgba(250,204,21,0.1); border: 1px solid var(--sl-accent);">Beklenen OVR: <?= 50+($takim['altyapi_seviye']*2) ?> - <?= 60+($takim['altyapi_seviye']*3) ?></div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" class="w-50">
                            <button type="submit" name="altyapi_gelistir" class="btn-upgrade" style="background: #0f172a; border: 1px solid #475569; color: #fff;" onclick="return confirm('Altyapı tesislerini modernize etmek için €<?= number_format(($takim['altyapi_seviye']*10), 1) ?>M harcanacak. Onaylıyor musunuz?');">
                                Geliştir (€<?= number_format(($takim['altyapi_seviye']*10), 1) ?>M)
                            </button>
                        </form>
                        <form method="POST" class="w-50">
                            <button type="submit" name="genc_cikar" class="btn-scout">
                                <i class="fa-solid fa-magnifying-glass"></i> Oyuncu Çıkar (€1.5M)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- FAZ 3: SAĞLIK MERKEZİ -->
            <?php $sm_seviye = $takim['saglik_merkezi_seviye'] ?? 1; $sm_indirim = min(45, ($sm_seviye-1)*5); ?>
            <div class="col-lg-5 col-md-6">
                <div class="panel-card" style="border-color: rgba(16,185,129,0.3);">
                    <div class="icon-wrapper" style="background: rgba(16,185,129,0.1); border-color: #10b981; color: #10b981;"><i class="fa-solid fa-hospital"></i></div>
                    <h3 class="font-oswald text-white mb-1">SAĞLIK MERKEZİ</h3>
                    <p class="text-muted small fw-bold mb-3">Oyuncuların sakatlık sürelerini kısaltır. Gelişmiş ekipman = hızlı iyileşme.</p>
                    
                    <div class="level-text" style="color: #10b981;">LVL <?= $sm_seviye ?></div>
                    <div class="level-badge" style="background: rgba(16,185,129,0.1); border: 1px solid #10b981;">
                        Sakatlık Süresi: -%<?= $sm_indirim ?> İndirim
                    </div>
                    <p class="text-muted small mt-2">
                        <?php if($sm_seviye >= 10): ?>
                            <span class="text-success fw-bold">⭐ Dünya Standartlarında Sağlık Merkezi!</span>
                        <?php else: ?>
                            Bir üst seviyede -%<?= min(45, $sm_seviye*5) ?> indirime ulaşırsınız.
                        <?php endif; ?>
                    </p>
                    
                    <form method="POST" class="mt-3">
                        <?php if($sm_seviye < 10): ?>
                            <button type="submit" name="saglik_gelistir" class="btn-upgrade"
                                style="background: #059669; border: none;"
                                onclick="return confirm('Sağlık Merkezini geliştirmek için €<?= number_format(($sm_seviye*12), 1) ?>M harcanacak. Onaylıyor musunuz?');">
                                <i class="fa-solid fa-circle-plus"></i> Geliştir (€<?= number_format(($sm_seviye*12), 1) ?>M)
                            </button>
                        <?php else: ?>
                            <button class="btn-upgrade" disabled style="background:#374151; cursor:not-allowed;">Maksimum Seviye</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>