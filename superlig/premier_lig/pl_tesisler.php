<?php
// ==============================================================================
// PREMIER LİG - KULÜP TESİSLERİ VE ALTYAPI MERKEZİ (PURPLE & NEON GREEN)
// ==============================================================================
include '../db.php';

// Veritabanına yeni Tesis sütunlarını güvenli şekilde ekle
function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

sutunEkle($pdo, 'pl_takimlar', 'stadyum_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'pl_takimlar', 'altyapi_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'pl_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Premier Lig'");

$ayar = $pdo->query("SELECT * FROM pl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) { header("Location: premier_lig.php"); exit; }

$takim = $pdo->query("SELECT * FROM pl_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// STADYUM GELİŞTİRME (PL Maliyetleri daha yüksektir)
if(isset($_POST['stadyum_gelistir'])) {
    $mevcut_seviye = $takim['stadyum_seviye'];
    $maliyet = $mevcut_seviye * 25000000; // Her seviye 25 Milyon Euro
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Stadium is at maximum level (10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE pl_takimlar SET butce = butce - $maliyet, stadyum_seviye = stadyum_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Stadyum genişletildi! Yeni Seviye: " . ($mevcut_seviye + 1);
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['stadyum_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Genişletme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPI GELİŞTİRME
if(isset($_POST['altyapi_gelistir'])) {
    $mevcut_seviye = $takim['altyapi_seviye'];
    $maliyet = $mevcut_seviye * 15000000; // Her seviye 15 Milyon Euro
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Academy is at maximum level (10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE pl_takimlar SET butce = butce - $maliyet, altyapi_seviye = altyapi_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Altyapı modernize edildi! Yeni Seviye: " . ($mevcut_seviye + 1);
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['altyapi_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Geliştirme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPIDAN OYUNCU ÇIKARMA (İNGİLİZ İSİMLERİ)
if(isset($_POST['genc_cikar'])) {
    $scout_maliyeti = 2500000; // PL Scout maliyeti 2.5M
    
    if($takim['butce'] >= $scout_maliyeti) {
        $altyapi_lvl = $takim['altyapi_seviye'];
        
        // İNGİLİZ İsim Havuzu
        $isimler = ['Jack', 'Harry', 'George', 'Oliver', 'Charlie', 'Thomas', 'William', 'James', 'Arthur', 'Noah', 'Liam', 'Mason'];
        $soyadlar = ['Smith', 'Jones', 'Taylor', 'Brown', 'Williams', 'Wilson', 'Johnson', 'Davies', 'Robinson', 'Wright', 'Thompson', 'Evans'];
        $yeni_isim = $isimler[array_rand($isimler)] . ' ' . $soyadlar[array_rand($soyadlar)];
        
        $mevkiler = ['K', 'D', 'OS', 'F'];
        $mevki = $mevkiler[array_rand($mevkiler)];
        $yas = rand(16, 18);
        
        $min_ovr = 50 + ($altyapi_lvl * 2);
        $max_ovr = 60 + ($altyapi_lvl * 3);
        $ovr = rand($min_ovr, $max_ovr);
        
        if(rand(1,100) <= 5) {
            $ovr += rand(6, 12);
            $mesaj_ek = " A BRITISH WONDERKID FOUND!";
        } else {
            $mesaj_ek = "";
        }
        
        $fiyat = ($ovr * $ovr) * 2000; // İngiliz oyuncular daha pahalı
        
        $pdo->exec("UPDATE pl_takimlar SET butce = butce - $scout_maliyeti WHERE id = $kullanici_takim_id");
        $stmt = $pdo->prepare("INSERT INTO pl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Premier Lig', 0, 1)");
        $stmt->execute([$kullanici_takim_id, $yeni_isim, $mevki, $ovr, $yas, $fiyat]);
        
        $mesaj = "Akademiden A Takıma yükseldi: $yeni_isim (OVR: $ovr).$mesaj_ek";
        $mesaj_tipi = "success";
        $takim['butce'] -= $scout_maliyeti;
        
    } else {
        $mesaj = "Scout Maliyeti Yetersiz! Altyapı taraması için €2.5M gerekiyor."; $mesaj_tipi = "danger";
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
    <title>Club Facilities | Premier League</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --pl-primary: #3d195b; 
            --pl-secondary: #e2f89c; 
            --pl-accent: #00ff85; 
            --pl-pink: #ff2882; 
            --bg-body: #1a0b2e;
            --bg-panel: #2d114f;
            --border-color: rgba(226, 248, 156, 0.2);
        }

        body { 
            background-color: var(--bg-body); color: #f9fafb; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 0% 0%, rgba(255,40,130,0.1) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(0,255,133,0.1) 0%, transparent 50%);
            min-height: 100vh;
        }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(61, 25, 91, 0.95); backdrop-filter: blur(24px); border-bottom: 2px solid var(--pl-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--pl-pink); }
        .nav-brand i { color: var(--pl-secondary); }
        .nav-link-item { color: #a78bfa; font-weight: 600; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--pl-secondary); text-shadow: 0 0 10px var(--pl-secondary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        .budget-box { background: rgba(0,0,0,0.5); border: 1px solid var(--pl-secondary); display: inline-block; padding: 15px 30px; border-radius: 12px; margin-top: 15px; box-shadow: 0 0 15px rgba(226,248,156,0.2);}
        .budget-val { font-family: 'Oswald'; font-size: 2.5rem; color: var(--pl-secondary); margin:0; line-height:1; text-shadow: 0 0 10px rgba(226,248,156,0.5);}

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; padding: 30px; transition: 0.3s;}
        .panel-card:hover { border-color: var(--pl-secondary); transform: translateY(-5px);}
        
        .icon-wrapper { width: 80px; height: 80px; background: rgba(0,255,133,0.1); border: 2px solid var(--pl-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--pl-accent); margin: 0 auto 20px;}
        
        .level-text { font-family: 'Oswald'; font-size: 2rem; color: #fff; margin-bottom: 5px;}
        .level-badge { background: rgba(0,0,0,0.4); padding: 5px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; color: var(--pl-secondary); margin-bottom: 20px; display: inline-block; border: 1px solid var(--border-color);}
        
        .btn-upgrade { background: var(--pl-secondary); color: var(--pl-primary); font-weight: 900; border: none; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-upgrade:hover { background: var(--pl-accent); color: #000; box-shadow: 0 0 15px var(--pl-accent);}
        
        .btn-scout { background: transparent; border: 2px solid var(--pl-pink); color: var(--pl-pink); font-weight: 900; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-scout:hover { background: var(--pl-pink); color: #fff; box-shadow: 0 0 15px var(--pl-pink);}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="premier_lig.php" class="nav-brand"><i class="fa-solid fa-crown"></i> <span class="font-oswald">PREMIER LEAGUE</span></a>
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Hub</a>
            <a href="premier_lig.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="pl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="pl_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Pazar</a>
            <a href="pl_tesisler.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--pl-secondary);"><i class="fa-solid fa-building"></i> Tesisler</a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(226,248,156,0.3);">CLUB FACILITIES</h1>
        <p class="text-muted fs-5 mt-2 fw-bold">Premier Lig gelirlerini ve genç İngiliz yeteneklerini artırın.</p>
        
        <div class="budget-box">
            <div class="text-muted fw-bold text-uppercase mb-1" style="font-size:0.8rem; letter-spacing:1px;">Club Budget</div>
            <p class="budget-val"><?= paraFormatla($takim['butce']) ?></p>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1200px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-5" style="background: <?= $mesaj_tipi == 'success' ? 'var(--pl-accent)' : ($mesaj_tipi == 'warning' ? '#f59e0b' : 'var(--pl-pink)') ?>; color: #000;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 justify-content-center">
            
            <div class="col-lg-5 col-md-6">
                <div class="panel-card">
                    <div class="icon-wrapper"><i class="fa-solid fa-stadium"></i></div>
                    <h3 class="font-oswald text-white mb-1">STADIUM</h3>
                    <p class="text-muted small fw-bold mb-3">Maç günü bilet gelirlerini artırır.</p>
                    
                    <div class="level-text">LVL <?= $takim['stadyum_seviye'] ?></div>
                    <div class="level-badge">Kapasite: <?= number_format($takim['stadyum_seviye'] * 15000) ?> Kişi</div>
                    
                    <form method="POST" class="mt-3">
                        <button type="submit" name="stadyum_gelistir" class="btn-upgrade" onclick="return confirm('Stadyumu genişletmek için €<?= number_format(($takim['stadyum_seviye']*25), 1) ?>M harcanacak. Onaylıyor musunuz?');">
                            Genişlet (€<?= number_format(($takim['stadyum_seviye']*25), 1) ?>M)
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5 col-md-6">
                <div class="panel-card" style="border-color: rgba(255,40,130,0.3);">
                    <div class="icon-wrapper" style="background: rgba(255,40,130,0.1); border-color: var(--pl-pink); color: var(--pl-pink);"><i class="fa-solid fa-seedling"></i></div>
                    <h3 class="font-oswald text-white mb-1">YOUTH ACADEMY</h3>
                    <p class="text-muted small fw-bold mb-3">Çıkan gençlerin (Regen) OVR kalitesini artırır.</p>
                    
                    <div class="level-text" style="color: var(--pl-pink);">LVL <?= $takim['altyapi_seviye'] ?></div>
                    <div class="level-badge">Beklenen OVR: <?= 50+($takim['altyapi_seviye']*2) ?> - <?= 60+($takim['altyapi_seviye']*3) ?></div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" class="w-50">
                            <button type="submit" name="altyapi_gelistir" class="btn-upgrade" style="background: rgba(0,0,0,0.5); border: 1px solid var(--pl-secondary); color: var(--pl-secondary);" onclick="return confirm('Altyapı tesislerini modernize etmek için €<?= number_format(($takim['altyapi_seviye']*15), 1) ?>M harcanacak. Onaylıyor musunuz?');">
                                Geliştir (€<?= number_format(($takim['altyapi_seviye']*15), 1) ?>M)
                            </button>
                        </form>
                        <form method="POST" class="w-50">
                            <button type="submit" name="genc_cikar" class="btn-scout">
                                Oyuncu Çıkar (€2.5M)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>