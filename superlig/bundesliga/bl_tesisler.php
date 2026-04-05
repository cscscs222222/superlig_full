<?php
// ==============================================================================
// BUNDESLIGA - KULÜP TESİSLERİ VE ALTYAPI MERKEZİ (RED & BLACK GERMAN THEME)
// ==============================================================================
include '../db.php';

// Tesis sütunlarını güvenli şekilde ekle
function sutunEkle($pdo, $tablo, $sutun, $tip) {
    try {
        $kontrol = $pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount();
        if ($kontrol == 0) { $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip"); }
    } catch(Throwable $e) {}
}

sutunEkle($pdo, 'de_takimlar', 'stadyum_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'de_takimlar', 'altyapi_seviye', 'INT DEFAULT 1');
sutunEkle($pdo, 'de_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Bundesliga'");

$ayar = $pdo->query("SELECT * FROM de_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) { header("Location: bundesliga.php"); exit; }

$takim = $pdo->query("SELECT * FROM de_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// STADYUM GELİŞTİRME
if(isset($_POST['stadyum_gelistir'])) {
    $mevcut_seviye = $takim['stadyum_seviye'];
    $maliyet = $mevcut_seviye * 20000000; // Her seviye 20 Milyon Euro
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Stadion zirvededir (Maksimum seviye 10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE de_takimlar SET butce = butce - $maliyet, stadyum_seviye = stadyum_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Stadion büyütüldü! Yeni Seviye: " . ($mevcut_seviye + 1);
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
    $maliyet = $mevcut_seviye * 12000000; // Her seviye 12 Milyon Euro
    
    if($mevcut_seviye >= 10) {
        $mesaj = "Nachwuchszentrum zirvededir (Maksimum seviye 10)!"; $mesaj_tipi = "warning";
    } elseif($takim['butce'] >= $maliyet) {
        $pdo->exec("UPDATE de_takimlar SET butce = butce - $maliyet, altyapi_seviye = altyapi_seviye + 1 WHERE id = $kullanici_takim_id");
        $mesaj = "Altyapı modernize edildi! Yeni Seviye: " . ($mevcut_seviye + 1);
        $mesaj_tipi = "success";
        $takim['butce'] -= $maliyet;
        $takim['altyapi_seviye']++;
    } else {
        $mesaj = "Yetersiz Bütçe! Geliştirme maliyeti: €" . number_format($maliyet/1000000, 1) . "M"; $mesaj_tipi = "danger";
    }
}

// ALTYAPIDAN OYUNCU ÇIKARMA (ALMAN İSİMLERİ)
if(isset($_POST['genc_cikar'])) {
    $scout_maliyeti = 2000000; // Scout maliyeti 2M

    if($takim['butce'] >= $scout_maliyeti) {
        $altyapi_lvl = $takim['altyapi_seviye'];
        
        // ALMAN İsim Havuzu
        $isimler = ['Leon', 'Lukas', 'Finn', 'Jonas', 'Elias', 'Noah', 'Paul', 'Felix', 'Max', 'Luca', 'Jan', 'Tobias', 'Marco', 'Florian', 'Erik'];
        $soyadlar = ['Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Schäfer', 'Koch', 'Bauer', 'Richter', 'Klein'];
        $yeni_isim = $isimler[array_rand($isimler)] . ' ' . $soyadlar[array_rand($soyadlar)];
        
        $mevkiler = ['K', 'D', 'OS', 'F'];
        $mevki = $mevkiler[array_rand($mevkiler)];
        $yas = rand(16, 18);
        
        $min_ovr = 50 + ($altyapi_lvl * 2);
        $max_ovr = 60 + ($altyapi_lvl * 3);
        $ovr = rand($min_ovr, $max_ovr);
        
        if(rand(1,100) <= 5) {
            $ovr += rand(6, 12);
            $mesaj_ek = " DEUTSCHES TALENT ENTDECKT!";
        } else {
            $mesaj_ek = "";
        }
        
        $fiyat = ($ovr * $ovr) * 3500;
        
        $pdo->exec("UPDATE de_takimlar SET butce = butce - $scout_maliyeti WHERE id = $kullanici_takim_id");
        $stmt = $pdo->prepare("INSERT INTO de_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Bundesliga', 0, 1)");
        $stmt->execute([$kullanici_takim_id, $yeni_isim, $mevki, $ovr, $yas, $fiyat]);
        
        $mesaj = "Altyapıdan A Takıma: $yeni_isim (OVR: $ovr).$mesaj_ek";
        $mesaj_tipi = "success";
        $takim['butce'] -= $scout_maliyeti;
        
    } else {
        $mesaj = "Scout Maliyeti Yetersiz! Altyapı taraması için €2M gerekiyor."; $mesaj_tipi = "danger";
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
    <title>Vereinsanlagen | Bundesliga</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --de-primary: #e11d48;
            --de-secondary: #dc2626;
            --de-accent: #ff6b35;
            --de-dark: #0d0d0d;
            --bg-body: #0d0d0d;
            --bg-panel: #1a1a1a;
            --border-color: rgba(220, 38, 38, 0.2);
        }

        body { 
            background-color: var(--bg-body); color: #f9fafb; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 0% 0%, rgba(200,16,46,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(220,38,38,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(10, 10, 10, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--de-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--de-primary); }
        .nav-brand i { color: var(--de-secondary); }
        .nav-link-item { color: #e11d48; font-weight: 600; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--de-secondary); text-shadow: 0 0 10px var(--de-secondary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        .budget-box { background: rgba(0,0,0,0.5); border: 1px solid var(--de-secondary); display: inline-block; padding: 15px 30px; border-radius: 12px; margin-top: 15px; box-shadow: 0 0 15px rgba(220,38,38,0.2);}
        .budget-val { font-family: 'Oswald'; font-size: 2.5rem; color: var(--de-secondary); margin:0; line-height:1; text-shadow: 0 0 10px rgba(220,38,38,0.5);}

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); text-align: center; padding: 30px; transition: 0.3s;}
        .panel-card:hover { border-color: var(--de-secondary); transform: translateY(-5px);}
        
        .icon-wrapper { width: 80px; height: 80px; background: rgba(220,38,38,0.1); border: 2px solid var(--de-secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: var(--de-secondary); margin: 0 auto 20px;}
        
        .level-text { font-family: 'Oswald'; font-size: 2rem; color: #fff; margin-bottom: 5px;}
        .level-badge { background: rgba(0,0,0,0.4); padding: 5px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; color: var(--de-secondary); margin-bottom: 20px; display: inline-block; border: 1px solid var(--border-color);}
        
        .btn-upgrade { background: var(--de-secondary); color: var(--de-dark); font-weight: 900; border: none; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-upgrade:hover { background: #ef4444; color: #000; box-shadow: 0 0 15px var(--de-secondary);}
        
        .btn-scout { background: transparent; border: 2px solid var(--de-primary); color: var(--de-primary); font-weight: 900; padding: 12px; width: 100%; border-radius: 8px; transition: 0.3s; font-size: 1rem; text-transform: uppercase;}
        .btn-scout:hover { background: var(--de-primary); color: #fff; box-shadow: 0 0 15px var(--de-primary);}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="bundesliga.php" class="nav-brand"><i class="fa-solid fa-shield-halved"></i> <span class="font-oswald">BUNDESLIGA</span></a>
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Hub</a>
            <a href="bundesliga.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="bl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
            <a href="bl_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="bl_tesisler.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--de-secondary);"><i class="fa-solid fa-building"></i> Tesisler</a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(220,38,38,0.3);">VEREINSANLAGEN</h1>
        <p class="text-muted fs-5 mt-2 fw-bold">Bundesliga gelirlerini ve Alman yeteneklerini artırın.</p>
        
        <div class="budget-box">
            <div class="text-muted fw-bold text-uppercase mb-1" style="font-size:0.8rem; letter-spacing:1px;">Bütçe</div>
            <p class="budget-val"><?= paraFormatla($takim['butce']) ?></p>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1200px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-5" style="background: <?= $mesaj_tipi == 'success' ? '#22c55e' : ($mesaj_tipi == 'warning' ? '#e11d48' : 'var(--de-primary)') ?>; color: #000;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 justify-content-center">
            
            <div class="col-lg-5 col-md-6">
                <div class="panel-card">
                    <div class="icon-wrapper"><i class="fa-solid fa-stadium"></i></div>
                    <h3 class="font-oswald text-white mb-1">STADION</h3>
                    <p class="text-muted small fw-bold mb-3">Maç günü bilet gelirlerini artırır.</p>
                    
                    <div class="level-text">LVL <?= $takim['stadyum_seviye'] ?></div>
                    <div class="level-badge">Kapasite: <?= number_format($takim['stadyum_seviye'] * 12000) ?> Kişi</div>
                    
                    <form method="POST" class="mt-3">
                        <button type="submit" name="stadyum_gelistir" class="btn-upgrade" onclick="return confirm('Stadion büyütmek için €<?= number_format(($takim['stadyum_seviye']*20), 1) ?>M harcanacak. Bestätigen?');">
                            Büyüt (€<?= number_format(($takim['stadyum_seviye']*20), 1) ?>M)
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-5 col-md-6">
                <div class="panel-card" style="border-color: rgba(200,16,46,0.3);">
                    <div class="icon-wrapper" style="background: rgba(200,16,46,0.1); border-color: var(--de-primary); color: var(--de-primary);"><i class="fa-solid fa-seedling"></i></div>
                    <h3 class="font-oswald text-white mb-1">NACHWUCHSZENTRUM (Altyapı)</h3>
                    <p class="text-muted small fw-bold mb-3">Çıkan Alman gençlerin OVR kalitesini artırır.</p>
                    
                    <div class="level-text" style="color: var(--de-primary);">LVL <?= $takim['altyapi_seviye'] ?></div>
                    <div class="level-badge">Beklenen OVR: <?= 50+($takim['altyapi_seviye']*2) ?> - <?= 60+($takim['altyapi_seviye']*3) ?></div>
                    
                    <div class="d-flex gap-2 mt-3">
                        <form method="POST" class="w-50">
                            <button type="submit" name="altyapi_gelistir" class="btn-upgrade" style="background: rgba(0,0,0,0.5); border: 1px solid var(--de-secondary); color: var(--de-secondary);" onclick="return confirm('Nachwuchszentrum\'u geliştirmek için €<?= number_format(($takim['altyapi_seviye']*12), 1) ?>M harcanacak. Bestätigen?');">
                                Geliştir (€<?= number_format(($takim['altyapi_seviye']*12), 1) ?>M)
                            </button>
                        </form>
                        <form method="POST" class="w-50">
                            <button type="submit" name="genc_cikar" class="btn-scout">
                                Keşif (€2M)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
