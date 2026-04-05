<?php
// ==============================================================================
// CHAMPIONS LEAGUE - UEFA ÜLKE KATSAYISI (BLUE & CYAN THEME)
// ==============================================================================
include '../db.php';

// TABLOYU OLUŞTUR VE İLK VERİLERİ GİR
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS uefa_siralamasi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ulke_adi VARCHAR(50) UNIQUE,
        toplam_puan INT DEFAULT 0,
        guncel_sezon_puan INT DEFAULT 0
    )");

    $count = $pdo->query("SELECT COUNT(*) FROM uefa_siralamasi")->fetchColumn();
    if($count == 0) {
        $pdo->exec("INSERT INTO uefa_siralamasi (ulke_adi, toplam_puan) VALUES
            ('İngiltere', 89000),
            ('İspanya', 75000),
            ('İtalya', 71000),
            ('Almanya', 71000),
            ('Fransa', 65000),
            ('Türkiye', 38000),
            ('Avrupa', 30000)
        ");
    }
} catch(Throwable $e) {}

// Sıralamayı Çek
$siralamalar = $pdo->query("SELECT * FROM uefa_siralamasi ORDER BY toplam_puan DESC")->fetchAll(PDO::FETCH_ASSOC);

function uefaFormat($puan) {
    return number_format($puan / 1000, 3);
}

function getClSlot($sira) {
    if ($sira <= 4) return 4;
    if ($sira <= 6) return 3;
    if ($sira <= 10) return 2;
    return 1;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UEFA Ülke Sıralaması | CL Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --cl-primary: #0a1c52; 
            --cl-secondary: #002878; 
            --cl-accent: #00e5ff; 
            --cl-silver: #cbd5e1;
            --bg-body: #050b14;
            --bg-panel: #0d1a38;
            --border-color: rgba(0, 229, 255, 0.15);
            --text-primary: #f9fafb;
            --text-muted: #94a3b8;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 0%, var(--cl-secondary) 0%, transparent 60%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(10, 28, 82, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--cl-accent); }
        .nav-brand i { color: var(--cl-accent); }
        .nav-link-item { color: var(--cl-silver); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; text-shadow: 0 0 10px var(--cl-accent); }
        .btn-action-outline { background: transparent; border: 1px solid var(--cl-accent); color: var(--cl-accent); font-weight: 600; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.2s;}
        .btn-action-outline:hover { background: var(--cl-accent); color: #000; }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 229, 255, 0.03); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.95rem; }
        .data-table th { padding: 1.2rem 1rem; color: var(--cl-accent); font-weight: 700; text-transform: uppercase; font-size: 0.8rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.3);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; font-weight: 600;}
        .data-table tbody tr:hover td { background: rgba(0,229,255,0.05); }

        .rank-box { background: rgba(0,229,255,0.1); color: var(--cl-accent); font-weight: 800; padding: 4px 12px; border-radius: 6px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(0,229,255,0.3);}
        .rank-1 { background: #d4af37; color: #000; border-color: #d4af37; box-shadow: 0 0 10px #d4af37;}
        
        .pts-current { color: #10b981; font-weight: 800; font-family: 'Oswald'; font-size: 1.1rem;}
        .pts-total { color: #fff; font-weight: 900; font-family: 'Oswald'; font-size: 1.4rem; text-shadow: 0 2px 4px rgba(0,0,0,0.8);}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand"><i class="fa-solid fa-futbol"></i> <span class="font-oswald">CHAMPIONS LEAGUE</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="cl.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="cl_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
            <a href="cl_uefa.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--cl-accent);"><i class="fa-solid fa-earth-europe"></i> Ülke Puanı</a>
        </div>
        
        <div>
            <a href="cl.php" class="btn-action-outline text-danger border-danger"><i class="fa-solid fa-arrow-left"></i> Lige Dön</a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(0,229,255,0.5);"><i class="fa-solid fa-globe" style="color:var(--cl-accent);"></i> UEFA ÜLKE KATSAYISI</h1>
        <p class="fs-5 mt-2 fw-bold text-muted">Şampiyonlar Ligi'nde aldığınız puanlar ülkenizin kaderini belirler.</p>
    </div>

    <div class="container py-5" style="max-width: 1200px;">
        <div class="panel-card shadow-lg">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th width="10%">Sıra</th>
                            <th>Ülke</th>
                            <th>Bu Sezon Kazanılan</th>
                            <th>Toplam Katsayı</th>
                            <th>Gelecek Sezon CL Kotası</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($siralamalar as $index => $u): 
                            $sira = $index + 1;
                            $rank_class = ($sira == 1) ? 'rank-1' : 'rank-box';
                            $slot = getClSlot($sira);
                            $bg_row = ($u['ulke_adi'] == 'Türkiye') ? 'background: rgba(225, 29, 72, 0.1);' : '';
                        ?>
                        <tr style="<?= $bg_row ?>">
                            <td><span class="<?= $rank_class ?>"><?= $sira ?></span></td>
                            <td class="text-start">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <i class="fa-solid fa-flag fs-4" style="color: <?= $u['ulke_adi'] == 'Türkiye' ? '#e11d48' : 'var(--cl-silver)' ?>;"></i>
                                    <span class="fs-5 text-white" style="letter-spacing:0.5px;"><?= htmlspecialchars($u['ulke_adi']) ?></span>
                                </div>
                            </td>
                            <td><span class="pts-current">+<?= uefaFormat($u['guncel_sezon_puan']) ?></span></td>
                            <td><span class="pts-total"><?= uefaFormat($u['toplam_puan']) ?></span></td>
                            <td><span class="badge bg-dark border border-secondary fs-6 py-2 px-3"><?= $slot ?> Takım</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top text-center text-muted" style="border-color:var(--border-color); background:rgba(0,0,0,0.5); font-size:0.85rem;">
                <i class="fa-solid fa-circle-info text-info"></i> Bilgi: Şampiyonlar Ligi'nde her galibiyet <strong>0.400</strong>, her beraberlik <strong>0.200</strong> ülke puanı kazandırır.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>