<?php
// ==============================================================================
// CHAMPIONS LEAGUE - TAKIM PROFİLİ VE SCOUT EKRANI (BLUE & CYAN THEME - FIX)
// ==============================================================================
include '../db.php';

$takim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$takim_id) { header("Location: cl.php"); exit; }

$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;

// Takım Bilgilerini Çek
$takim = $pdo->query("SELECT * FROM cl_takimlar WHERE id = $takim_id")->fetch(PDO::FETCH_ASSOC);
if(!$takim) { header("Location: cl.php"); exit; }

// Oyuncuları Çek (Mevkiye ve Güce göre sıralı)
$oyuncular = $pdo->query("SELECT * FROM cl_oyuncular WHERE takim_id = $takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM cl_oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
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
    <title><?= htmlspecialchars($takim['takim_adi']) ?> | CL Scout</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ŞAMPİYONLAR LİGİ TEMASI (DARK BLUE & CYAN) */
        :root {
            --cl-primary: #0a1c52; 
            --cl-secondary: #002878; 
            --cl-accent: #00e5ff; 
            --cl-silver: #cbd5e1;
            
            --color-win: #10b981;
            --color-draw: #6b7280;
            --color-loss: #ef4444;

            --bg-body: #050b14;
            --bg-panel: #0d1a38;
            --border-color: rgba(0, 229, 255, 0.15);
            
            --text-primary: #f9fafb;
            --text-muted: #94a3b8;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 50% 0%, var(--cl-secondary) 0%, transparent 60%);
        }

        /* YILDIZLI ARKA PLAN EFEKTİ */
        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-image: radial-gradient(white, rgba(255,255,255,.2) 2px, transparent 40px),
                              radial-gradient(white, rgba(255,255,255,.15) 1px, transparent 30px),
                              radial-gradient(white, rgba(255,255,255,.1) 2px, transparent 40px);
            background-size: 550px 550px, 350px 350px, 250px 250px; 
            background-position: 0 0, 40px 60px, 130px 270px;
            opacity: 0.15; pointer-events: none; z-index: -1;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* NAVBAR */
        .pro-navbar { background: rgba(10, 28, 82, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--cl-accent); }
        .nav-brand i { color: var(--cl-accent); }
        .nav-link-item { color: var(--cl-silver); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; text-shadow: 0 0 10px var(--cl-accent); }
        .btn-action-outline { background: transparent; border: 1px solid var(--cl-accent); color: var(--cl-accent); font-weight: 600; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.2s; }
        .btn-action-outline:hover { background: var(--cl-accent); color: #000; }

        /* HERO KISMI */
        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 229, 255, 0.03); text-align: center; }

        /* PANEL VE SCOUT KARTLARI */
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,229,255,0.05); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,229,255,0.05); font-weight: 700;}

        .scout-box { background: rgba(0,0,0,0.4); border: 1px solid rgba(0,229,255,0.15); padding: 15px; border-radius: 8px; text-align: center; height: 100%; transition:0.3s;}
        .scout-box:hover { border-color: var(--cl-accent); transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,229,255,0.1);}
        .scout-title { font-size: 0.75rem; color: var(--cl-silver); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 5px;}
        .scout-val { font-family: 'Oswald'; font-size: 1.5rem; color: #fff;}

        /* ŞAMPİYONLAR LİGİ FUT KART TASARIMI */
        .fut-card {
            background: linear-gradient(135deg, rgba(10,28,82,0.9), rgba(0,0,0,0.8));
            border: 1px solid rgba(0,229,255,0.3);
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
        .fut-card:hover { transform: translateY(-8px); border-color: var(--cl-accent); box-shadow: 0 10px 25px rgba(0,229,255,0.3); }
        .fut-card::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: linear-gradient(transparent, rgba(0,229,255,0.1), transparent);
            transform: rotate(45deg); pointer-events: none; opacity: 0; transition: 0.5s;
        }
        .fut-card:hover::after { opacity: 1; animation: shine 2s infinite; }
        @keyframes shine { 0% { top: -100%; left: -100%; } 100% { top: 100%; left: 100%; } }

        .fut-card.ilk11 { border-top: 3px solid var(--color-win); }
        .fut-card.yedek { border-top: 3px solid #fbbf24; }
        
        .fut-ovr { font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; line-height: 1; color: #fff; text-shadow: 0 0 10px rgba(0,229,255,0.5);}
        .fut-pos { font-family: 'Oswald'; font-size: 1rem; color: var(--cl-accent); font-weight: 700; margin-bottom: 10px;}
        .fut-name { font-weight: 700; font-size: 1rem; color: #fff; text-align: center; border-bottom: 1px solid rgba(0,229,255,0.2); padding-bottom: 10px; margin-bottom: 10px; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        
        .fut-stats { display: flex; justify-content: space-between; width: 100%; font-size: 0.8rem; color: var(--cl-silver); font-weight: 600;}
        .fut-stat-val { color: var(--cl-accent); font-weight: 700;}

        @media (max-width: 992px) { .d-mobile-none { display: none !important; } }
    </style>
</head>
<body>

    <div class="bg-gradient-layer"></div>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand text-decoration-none">
            <i class="fa-solid fa-futbol"></i> 
            <span class="font-oswald text-white">CHAMPIONS LEAGUE</span>
        </a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="cl.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="cl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Taktik Odası</a>
            <a href="cl_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="cl_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
        </div>
        
        <div>
            <a href="javascript:history.back()" class="btn-action-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Geri Dön</a>
        </div>
    </nav>

    <div class="hero-banner">
        <img src="<?= $takim['logo'] ?>" alt="Logo" style="width: 110px; height: 110px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 0 25px rgba(0,229,255,0.6));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(0,229,255,0.5);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
        
        <?php if($takim['id'] == $kullanici_takim_id): ?>
            <span class="badge bg-success mt-2 fs-6"><i class="fa-solid fa-user-tie"></i> Senin Takımın</span>
        <?php else: ?>
            <span class="badge bg-dark border border-info text-info mt-2 fs-6"><i class="fa-solid fa-earth-europe"></i> Avrupa Temsilcisi</span>
        <?php endif; ?>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <div class="panel-card mb-5">
            <div class="panel-header text-info">
                <span class="font-oswald"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> KULÜP SCOUT RAPORU</span>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">OVR</div><div class="scout-val text-warning"><?= $takim_kalitesi ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Formasyon</div><div class="scout-val text-white"><?= $takim['dizilis'] ?? 'Bilinmiyor' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Oyun Tarzı</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= $takim['oyun_tarzi'] ?? 'Dengeli' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Pres</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= $takim['pres'] ?? 'Orta' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Tempo</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= $takim['tempo'] ?? 'Normal' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Bütçe</div><div class="scout-val" style="color:var(--color-win); font-size:1.2rem;"><?= paraFormatla($takim['butce']) ?></div></div></div>
                </div>
            </div>
        </div>

        <h3 class="font-oswald mb-4 border-bottom pb-2" style="border-color:var(--border-color); color:var(--cl-accent);">
            <i class="fa-solid fa-users"></i> Kulüp Kadrosu
        </h3>
        
        <div class="row g-3">
            <?php foreach($oyuncular as $o): 
                $card_class = "";
                $durum_text = "Kadro Dışı";
                if($o['ilk_11'] == 1) { $card_class = "ilk11"; $durum_text = "İlk 11"; }
                elseif($o['yedek'] == 1) { $card_class = "yedek"; $durum_text = "Yedek"; }
            ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-6 hover-lift" style="transition: 0.3s;">
                <div class="fut-card <?= $card_class ?>">
                    <div class="d-flex justify-content-between align-items-start w-100 mb-2">
                        <div class="d-flex flex-column">
                            <span class="fut-ovr"><?= $o['ovr'] ?></span>
                            <span class="fut-pos"><?= $o['mevki'] ?></span>
                        </div>
                        <img src="<?= $takim['logo'] ?>" style="width:28px; height:28px; filter: grayscale(50%) opacity(0.8);">
                    </div>
                    
                    <div class="fut-name" title="<?= htmlspecialchars($o['isim']) ?>"><?= htmlspecialchars($o['isim']) ?></div>
                    
                    <div class="fut-stats">
                        <div>FRM <span class="fut-stat-val" style="color:<?= $o['form']>=7?'var(--color-win)':'#fff' ?>;"><?= $o['form'] ?></span></div>
                        <div>FİT <span class="fut-stat-val" style="color:<?= $o['fitness']>=80?'var(--color-win)':'#fff' ?>;"><?= $o['fitness'] ?></span></div>
                        <div>YAŞ <span class="fut-stat-val text-white"><?= $o['yas'] ?></span></div>
                    </div>
                    
                    <div class="mt-3 w-100 text-center">
                        <span class="badge w-100" style="background:rgba(255,255,255,0.1); color:var(--cl-silver); font-size:0.7rem;"><?= $durum_text ?></span>
                    </div>
                    
                    <?php if($takim['id'] != $kullanici_takim_id): ?>
                        <a href="cl_transfer.php?q=<?= urlencode($o['isim']) ?>" class="btn btn-sm w-100 mt-2" style="background:var(--cl-accent); color:#000; font-weight:700; font-size:0.75rem;">TEKLİF YAP</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if(empty($oyuncular)): ?>
                <div class="col-12 text-center py-5 text-muted font-oswald fs-4">Bu kulübe ait kayıtlı oyuncu bulunamadı.</div>
            <?php endif; ?>
        </div>

    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(11,15,25,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important;">
        <a href="cl.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="cl_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="cl_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="cl_puan.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block text-white"></i> Veri
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>