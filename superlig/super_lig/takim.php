<?php
// ==============================================================================
// SÜPER LİG - TAKIM PROFİLİ VE SCOUT EKRANI (DARK RED & GOLD THEME)
// ==============================================================================
include '../db.php';

$takim_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$takim_id) { header("Location: superlig.php"); exit; }

$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

// Takım Bilgilerini Çek
$takim = $pdo->query("SELECT * FROM takimlar WHERE id = $takim_id")->fetch(PDO::FETCH_ASSOC);
if(!$takim) { header("Location: superlig.php"); exit; }

// Oyuncuları Çek (Mevkiye ve Güce göre sıralı)
$oyuncular = $pdo->query("SELECT * FROM oyuncular WHERE takim_id = $takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM oyuncular WHERE takim_id = $takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;

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
    <title><?= htmlspecialchars($takim['takim_adi']) ?> | SL Scout</title>
    
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

        .btn-action-outline { background: transparent; border: 1px solid var(--sl-accent); color: var(--sl-accent); font-weight: 600; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.2s;}
        .btn-action-outline:hover { background: var(--sl-accent); color: #000; }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); font-weight: 700;}

        .scout-box { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; text-align: center; height: 100%; transition:0.3s;}
        .scout-box:hover { border-color: var(--sl-secondary); transform: translateY(-3px);}
        .scout-title { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; margin-bottom: 5px;}
        .scout-val { font-family: 'Oswald'; font-size: 1.5rem; color: #fff;}

        /* SÜPER LİG FUT KART TASARIMI */
        .fut-card {
            background: linear-gradient(135deg, #1e1b4b, #0f172a);
            border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 15px;
            position: relative; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center;
        }
        .fut-card:hover { transform: translateY(-8px); border-color: var(--sl-secondary); box-shadow: 0 10px 25px rgba(225,29,72,0.2); }
        .fut-card.ilk11 { border-top: 3px solid var(--sl-secondary); }
        .fut-card.yedek { border-top: 3px solid var(--sl-accent); }
        
        .fut-ovr { font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; line-height: 1; color: var(--sl-accent);}
        .fut-pos { font-family: 'Oswald'; font-size: 1rem; color: var(--sl-secondary); font-weight: 700; margin-bottom: 10px;}
        .fut-name { font-weight: 700; font-size: 1rem; color: #fff; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 10px; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .fut-stats { display: flex; justify-content: space-between; width: 100%; font-size: 0.8rem; color: #94a3b8; font-weight: 600;}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="superlig.php" class="nav-brand"><i class="fa-solid fa-moon"></i> <span class="font-oswald">SÜPER LİG</span></a>
        <div><a href="javascript:history.back()" class="btn-action-outline btn-sm"><i class="fa-solid fa-arrow-left"></i> Geri Dön</a></div>
    </nav>

    <div class="hero-banner">
        <img src="<?= $takim['logo'] ?>" alt="Logo" style="width: 110px; height: 110px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 0 20px rgba(225,29,72,0.4));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(225,29,72,0.3);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        <div class="panel-card mb-5">
            <div class="panel-header" style="color: var(--sl-secondary);">
                <span class="font-oswald"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> KULÜP SCOUT RAPORU</span>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">OVR</div><div class="scout-val text-warning"><?= $takim_kalitesi ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Formasyon</div><div class="scout-val text-white"><?= $takim['dizilis'] ?? 'Bilinmiyor' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Oyun Tarzı</div><div class="scout-val text-white" style="font-size:1.2rem;"><?= $takim['oyun_tarzi'] ?? 'Dengeli' ?></div></div></div>
                    <div class="col-md-2 col-6"><div class="scout-box"><div class="scout-title">Tesisler</div><div class="scout-val text-white" style="font-size:1.2rem;">Lvl <?= $takim['altyapi_seviye'] ?? 1 ?></div></div></div>
                    <div class="col-md-4 col-12"><div class="scout-box"><div class="scout-title">Bütçe</div><div class="scout-val" style="color:#10b981; font-size:1.5rem;"><?= paraFormatla($takim['butce']) ?></div></div></div>
                </div>
            </div>
        </div>

        <h3 class="font-oswald mb-4 border-bottom pb-2" style="border-color:var(--border-color); color:var(--sl-accent);">
            <i class="fa-solid fa-users"></i> Kulüp Kadrosu
        </h3>
        
        <div class="row g-3">
            <?php foreach($oyuncular as $o): 
                $card_class = $o['ilk_11'] == 1 ? "ilk11" : ($o['yedek'] == 1 ? "yedek" : "");
                $durum_text = $o['ilk_11'] == 1 ? "İlk 11" : ($o['yedek'] == 1 ? "Yedek" : "Kadro Dışı");
            ?>
            <div class="col-xl-2 col-lg-3 col-md-4 col-6">
                <div class="fut-card <?= $card_class ?>">
                    <div class="d-flex justify-content-between align-items-start w-100 mb-2">
                        <div class="d-flex flex-column">
                            <span class="fut-ovr"><?= $o['ovr'] ?></span>
                            <span class="fut-pos"><?= $o['mevki'] ?></span>
                        </div>
                        <img src="<?= $takim['logo'] ?>" style="width:28px; height:28px; filter: grayscale(30%) opacity(0.8);">
                    </div>
                    <div class="fut-name" title="<?= htmlspecialchars($o['isim']) ?>"><?= htmlspecialchars($o['isim']) ?></div>
                    <div class="fut-stats">
                        <div>FRM <span class="text-white"><?= $o['form'] ?></span></div>
                        <div>FİT <span class="text-white"><?= $o['fitness'] ?></span></div>
                        <div>YAŞ <span class="text-white"><?= $o['yas'] ?></span></div>
                    </div>
                    <div class="mt-3 w-100 text-center"><span class="badge w-100" style="background:rgba(255,255,255,0.1); color:var(--sl-accent); font-size:0.7rem;"><?= $durum_text ?></span></div>
                    
                    <?php if($takim['id'] != $kullanici_takim_id): ?>
                        <a href="transfer.php?q=<?= urlencode($o['isim']) ?>" class="btn btn-sm w-100 mt-2" style="background:var(--sl-secondary); color:#fff; font-weight:700; font-size:0.75rem; border:none;">TEKLİF YAP</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>