<?php
// ==============================================================================
// SERIE A - TAKIM PROFİLİ VE SCOUT EKRANI (BLUE & GREEN ITALIAN THEME)
// ==============================================================================
include '../db.php';

$takim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$takim_id) { header("Location: serie_a.php"); exit; }

$ayar = $pdo->query("SELECT * FROM it_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;

// Takım Bilgilerini Çek
$takim = $pdo->query("SELECT * FROM it_takimlar WHERE id = $takim_id")->fetch(PDO::FETCH_ASSOC);
if(!$takim) { header("Location: serie_a.php"); exit; }

// Oyuncuları Çek (Mevkiye ve Güce göre sıralı)
$oyuncular = $pdo->query("SELECT * FROM it_oyuncular WHERE takim_id = $takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM it_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;

function paraFormatla($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    if ($sayi >= 1000) return "€" . number_format($sayi / 1000, 1) . "K";
    return "€" . $sayi;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title><?= htmlspecialchars($takim['takim_adi']) ?> | Serie A Scout</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* SERIE A TEMASI */
        :root {
            --it-primary: #10b981;
            --it-secondary: #059669;
            --it-accent: #ff6b35;
            --it-dark: #0d0d0d;

            --color-win: #22c55e;
            --color-draw: #9ca3af;
            --color-loss: #34d399;

            --bg-body: #0d0d0d;
            --bg-panel: #1a1a1a;
            --border-color: rgba(5, 150, 105, 0.2);
            
            --text-primary: #f9fafb;
            --text-muted: #10b981;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(16,185,129,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(5,150,105,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* NAVBAR */
        .pro-navbar { background: rgba(10, 10, 10, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--it-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--it-primary); }
        .nav-brand i { color: var(--it-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--it-secondary); text-shadow: 0 0 10px var(--it-secondary); }
        .btn-action-outline { background: transparent; border: 1px solid var(--it-primary); color: var(--it-primary); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.3s;}
        .btn-action-outline:hover { background: var(--it-primary); color: #fff; box-shadow: 0 0 15px var(--it-primary); }

        /* HERO KISMI */
        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        /* PANEL VE SCOUT KARTLARI */
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.3); font-weight: 700;}

        .scout-box { background: rgba(0,0,0,0.4); border: 1px solid rgba(5,150,105,0.2); padding: 15px; border-radius: 8px; text-align: center; height: 100%; transition:0.3s;}
        .scout-box:hover { border-color: var(--it-secondary); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(5,150,105,0.15);}
        .scout-title { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 5px;}
        .scout-val { font-family: 'Oswald'; font-size: 1.5rem; color: #fff;}

        /* SERIE A FUT KART TASARIMI */
        .fut-card {
            background: linear-gradient(135deg, rgba(26,26,26,0.9), rgba(21,0,3,0.9));
            border: 1px solid rgba(5,150,105,0.3);
            border-radius: 12px;
            padding: 15px;
            position: relative;
            transition: all 0.3s ease;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .fut-card:hover { transform: translateY(-8px); border-color: var(--it-secondary); box-shadow: 0 10px 25px rgba(5,150,105,0.2); }
        
        .fut-card.ilk11 { border-top: 3px solid var(--it-secondary); }
        .fut-card.yedek { border-top: 3px solid #f87171; }
        
        .fut-ovr { font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; line-height: 1; color: var(--it-secondary); text-shadow: 0 0 10px rgba(5,150,105,0.3);}
        .fut-pos { font-family: 'Oswald'; font-size: 1rem; color: var(--it-primary); font-weight: 700; margin-bottom: 10px;}
        .fut-name { font-weight: 700; font-size: 1rem; color: #fff; text-align: center; border-bottom: 1px solid rgba(5,150,105,0.2); padding-bottom: 10px; margin-bottom: 10px; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        
        .fut-stats { display: flex; justify-content: space-between; width: 100%; font-size: 0.8rem; color: var(--text-muted); font-weight: 600;}
        .fut-stat-val { color: #fff; font-weight: 700;}

        @media (max-width: 992px) { .d-mobile-none { display: none !important; } }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="serie_a.php" class="nav-brand text-decoration-none">
            <i class="fa-solid fa-shield-halved"></i> 
            <span class="font-oswald text-white">SERIE A</span>
        </a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="serie_a.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="sa_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro / Taktik</a>
            <a href="sa_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="sa_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
        </div>
        
        <div>
            <a href="javascript:history.back()" class="btn-action-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Geri Dön</a>
        </div>
    </nav>

    <div class="hero-banner">
        <img src="<?= htmlspecialchars($takim['logo']) ?>" alt="Logo" style="width: 110px; height: 110px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 0 25px rgba(5,150,105,0.4));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(5,150,105,0.3);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
        
        <?php if($takim['id'] == $kullanici_takim_id): ?>
            <span class="badge mt-2 fs-6" style="background:var(--it-secondary); color:var(--it-dark);"><i class="fa-solid fa-user-tie"></i> Senin Takımın</span>
        <?php else: ?>
            <span class="badge bg-dark border mt-2 fs-6" style="border-color:var(--it-primary) !important; color:var(--it-primary);"><i class="fa-solid fa-robot"></i> Rakip Kulüp</span>
        <?php endif; ?>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <div class="panel-card mb-5">
            <div class="panel-header" style="color: var(--it-secondary);">
                <span class="font-oswald"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> SCOUTING REPORT (Kulüp Scout Raporu)</span>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">OVR</div><div class="scout-val text-warning"><?= $takim_kalitesi ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Formasyon</div><div class="scout-val text-white"><?= htmlspecialchars($takim['dizilis'] ?? 'Bilinmiyor') ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Oyun Tarzı</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= htmlspecialchars($takim['oyun_tarzi'] ?? 'Dengeli') ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Pres</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= htmlspecialchars($takim['pres'] ?? 'Orta') ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Tempo</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= htmlspecialchars($takim['tempo'] ?? 'Normal') ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Bütçe</div><div class="scout-val" style="color:var(--color-win); font-size:1.2rem;"><?= paraFormatla($takim['butce']) ?></div></div></div>
                </div>
            </div>
        </div>

        <h3 class="font-oswald mb-4 border-bottom pb-2" style="border-color:var(--border-color); color:var(--it-primary);">
            <i class="fa-solid fa-users"></i> Kulüp Kadrosu
        </h3>
        
        <div class="row g-3">
            <?php foreach($oyuncular as $o): 
                $card_class = "";
                $durum_text = "Kadro Dışı";
                if($o['ilk_11'] == 1) { $card_class = "ilk11"; $durum_text = "İlk 11"; }
                elseif($o['yedek'] == 1) { $card_class = "yedek"; $durum_text = "Yedek"; }
            ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-6" style="transition: 0.3s;">
                <div class="fut-card <?= $card_class ?>">
                    <div class="d-flex justify-content-between align-items-start w-100 mb-2">
                        <div class="d-flex flex-column">
                            <span class="fut-ovr"><?= $o['ovr'] ?></span>
                            <span class="fut-pos"><?= $o['mevki'] ?></span>
                        </div>
                        <img src="<?= htmlspecialchars($takim['logo']) ?>" style="width:28px; height:28px; filter: grayscale(30%) opacity(0.8);">
                    </div>
                    
                    <div class="fut-name" title="<?= htmlspecialchars($o['isim']) ?>"><?= htmlspecialchars($o['isim']) ?></div>
                    
                    <div class="fut-stats">
                        <div>FRM <span class="fut-stat-val" style="color:<?= $o['form']>=7?'var(--color-win)':'#fff' ?>;"><?= $o['form'] ?></span></div>
                        <div>FİT <span class="fut-stat-val" style="color:<?= $o['fitness']>=80?'var(--color-win)':'#fff' ?>;"><?= $o['fitness'] ?></span></div>
                        <div>YAŞ <span class="fut-stat-val text-white"><?= $o['yas'] ?></span></div>
                    </div>
                    
                    <div class="mt-3 w-100 text-center">
                        <span class="badge w-100" style="background:rgba(255,255,255,0.1); color:var(--it-secondary); font-size:0.7rem;"><?= $durum_text ?></span>
                    </div>
                    
                    <?php if($takim['id'] != $kullanici_takim_id): ?>
                        <a href="sa_transfer.php?q=<?= urlencode($o['isim']) ?>" class="btn btn-sm w-100 mt-2" style="background:var(--it-primary); color:#fff; font-weight:700; font-size:0.75rem; border:none;">TEKLİF YAP</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($oyuncular)): ?>
                <div class="col-12 text-center py-5 text-muted font-oswald fs-4">Bu kulübe ait kayıtlı oyuncu bulunamadı.</div>
            <?php endif; ?>
        </div>

    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(13,13,13,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important; border-top-color: var(--it-secondary) !important;">
        <a href="serie_a.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="sa_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="sa_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="sa_puan.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block text-white"></i> Veri
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
