<?php
// ==============================================================================
// CHAMPIONS LEAGUE - TRANSFER BORSASI (BLUE & CYAN THEME)
// ==============================================================================
include '../db.php';

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) {
    header("Location: cl.php");
    exit;
}

// Kullanıcının Avrupa takımını çek
$benim_takim = $pdo->query("SELECT * FROM cl_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// --- SATIN ALMA İŞLEMİ ---
if (isset($_POST['satin_al'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $hedef_oyuncu = $pdo->query("SELECT * FROM cl_oyuncular WHERE id = $oyuncu_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($hedef_oyuncu) {
        $fiyat = $hedef_oyuncu['fiyat'];
        $eski_takim_id = $hedef_oyuncu['takim_id'];
        
        if ($benim_takim['butce'] >= $fiyat) {
            // Bütçeden düş ve eski takıma ekle
            $pdo->exec("UPDATE cl_takimlar SET butce = butce - $fiyat WHERE id = $kullanici_takim_id");
            $pdo->exec("UPDATE cl_takimlar SET butce = butce + $fiyat WHERE id = $eski_takim_id");
            
            // Oyuncuyu transfer et (İlk 11'i bozmamak için yedeğe atılır)
            $pdo->exec("UPDATE cl_oyuncular SET takim_id = $kullanici_takim_id, ilk_11 = 0, yedek = 1, saha_pozisyon = '50,50' WHERE id = $oyuncu_id");
            
            // Haberi sisteme düş
            $hafta = $ayar['hafta'];
            $fiyat_milyon = number_format($fiyat/1000000, 1);
            $haber_metni = "YÜZYILIN TRANSFERİ! " . $benim_takim['takim_adi'] . ", dünya yıldızı " . $hedef_oyuncu['isim'] . "'i €{$fiyat_milyon}M karşılığında kadrosuna kattı.";
            try { $pdo->exec("INSERT INTO cl_haberler (hafta, metin, tip) VALUES ($hafta, '$haber_metni', 'transfer')"); } catch(Throwable $e){}
            
            $mesaj = "Transfer Tamamlandı! " . $hedef_oyuncu['isim'] . " resmen kadronuzda.";
            $mesaj_tipi = "success";
            
            // Bütçeyi anlık güncelle
            $benim_takim['butce'] -= $fiyat;
        } else {
            $mesaj = "Finansal Fair Play Uyarısı! Bu yıldız için kulübün kasasında yeterli bütçe yok.";
            $mesaj_tipi = "danger";
        }
    }
}

// --- SATIŞ İŞLEMİ ---
if (isset($_POST['sat'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $benim_oyuncu = $pdo->query("SELECT * FROM cl_oyuncular WHERE id = $oyuncu_id AND takim_id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($benim_oyuncu) {
        $fiyat = $benim_oyuncu['fiyat'];
        
        // Rastgele bir Avrupa takımına sat
        $ai_takim = $pdo->query("SELECT id FROM cl_takimlar WHERE id != $kullanici_takim_id ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $yeni_takim_id = $ai_takim['id'];
        
        // Bütçe işlemleri
        $pdo->exec("UPDATE cl_takimlar SET butce = butce + $fiyat WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE cl_takimlar SET butce = GREATEST(0, butce - $fiyat) WHERE id = $yeni_takim_id");
        
        // Oyuncuyu yolla
        $pdo->exec("UPDATE cl_oyuncular SET takim_id = $yeni_takim_id, ilk_11 = 0, yedek = 1, saha_pozisyon = '50,50' WHERE id = $oyuncu_id");
        
        $mesaj = $benim_oyuncu['isim'] . " satıldı. Kulübümüzün kasasına " . number_format($fiyat/1000000, 1) . " Milyon Euro girdi.";
        $mesaj_tipi = "success";
        
        $benim_takim['butce'] += $fiyat;
    }
}

// Arama ve Filtreleme
$arama = $_GET['q'] ?? '';
$mevki_filtre = $_GET['mevki'] ?? '';

$sql_pazar = "SELECT o.*, t.takim_adi, t.logo FROM cl_oyuncular o JOIN cl_takimlar t ON o.takim_id = t.id WHERE o.takim_id != $kullanici_takim_id";
if ($arama) { $sql_pazar .= " AND o.isim LIKE '%" . addslashes($arama) . "%'"; }
if ($mevki_filtre) { $sql_pazar .= " AND o.mevki = '$mevki_filtre'"; }
$sql_pazar .= " ORDER BY o.ovr DESC LIMIT 100";

$pazardaki_oyuncular = $pdo->query($sql_pazar)->fetchAll(PDO::FETCH_ASSOC);
$benim_oyuncularim = $pdo->query("SELECT * FROM cl_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Avrupa Transfer Borsası | CL Manager</title>
    
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
            
            --color-win: #10b981;
            --color-loss: #ef4444;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 50% 0%, var(--cl-secondary) 0%, transparent 60%);
        }

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
        
        .pro-navbar { background: rgba(10, 28, 82, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--cl-accent); }
        .nav-brand i { color: var(--cl-accent); }
        .nav-link-item { color: var(--cl-silver); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; text-shadow: 0 0 10px var(--cl-accent); }

        .hero-banner { 
            padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); 
            background: rgba(0, 229, 255, 0.03); text-align: center;
        }

        .budget-card {
            background: linear-gradient(135deg, rgba(10,28,82,0.8), rgba(0,0,0,0.6)); 
            border: 1px solid var(--cl-accent); border-radius: 12px; 
            padding: 1.5rem 3rem; display: inline-block; box-shadow: 0 10px 30px rgba(0,229,255,0.2);
            position: relative; overflow: hidden;
        }
        
        .budget-card::after {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: linear-gradient(transparent, rgba(0,229,255,0.1), transparent);
            transform: rotate(45deg); animation: shine 3s infinite; pointer-events: none;
        }
        @keyframes shine { 0% { top: -100%; left: -100%; } 100% { top: 100%; left: 100%; } }

        .nav-pills .nav-link { color: var(--cl-silver); font-weight: 600; border-radius: 50px; padding: 10px 30px; border: 1px solid transparent; transition: 0.3s;}
        .nav-pills .nav-link:hover { color: #fff; }
        .nav-pills .nav-link.active { background: var(--cl-accent); color: #000; border-color: var(--cl-accent); box-shadow: 0 4px 15px rgba(0,229,255,0.4);}
        
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--cl-accent); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.3);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .data-table tbody tr:hover td { background: rgba(0,229,255,0.05); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #facc15; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--cl-accent); color: #000;}
        
        .ovr-box { background: rgba(0,229,255,0.1); color: var(--cl-accent); font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(0,229,255,0.2);}
        
        .btn-buy { background: linear-gradient(45deg, var(--cl-secondary), var(--cl-accent)); color: #fff; font-weight: 700; border: none; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-buy:hover { color: #000; box-shadow: 0 0 10px var(--cl-accent); transform: scale(1.05);}
        
        .btn-sell { background: transparent; border: 1px solid var(--color-loss); color: var(--color-loss); font-weight: 700; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-sell:hover { background: var(--color-loss); color: #fff; transform: scale(1.05); box-shadow: 0 0 10px rgba(239,68,68,0.5);}

        /* FİLTRE ÇUBUĞU */
        .filter-bar { background: rgba(0,0,0,0.4); border-bottom: 1px solid var(--border-color); padding: 15px; display: flex; gap: 10px; }
        .filter-input { background: rgba(255,255,255,0.05); border: 1px solid rgba(0,229,255,0.3); color: #fff; border-radius: 6px; padding: 8px 15px; width: 100%; transition: 0.3s; }
        .filter-input:focus { outline: none; border-color: var(--cl-accent); background: rgba(0,229,255,0.1); }
        .filter-select { background: rgba(255,255,255,0.05); border: 1px solid rgba(0,229,255,0.3); color: #fff; border-radius: 6px; padding: 8px 15px; width: 150px; cursor: pointer;}
        .filter-select:focus { outline: none; border-color: var(--cl-accent); }
        .filter-select option { background: var(--bg-panel); color: #fff; }
        .btn-search { background: var(--cl-accent); color: #000; border: none; font-weight: bold; padding: 0 20px; border-radius: 6px; transition: 0.2s;}
        .btn-search:hover { background: #fff; }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand"><i class="fa-solid fa-futbol"></i> <span class="font-oswald">CHAMPIONS LEAGUE</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="cl.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="cl_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Taktik Odası</a>
        </div>

        <div class="d-flex gap-3">
            <a href="../super_lig/superlig.php" class="btn-action-outline text-danger border-danger hover-lift">
                <i class="fa-solid fa-arrow-left"></i> Yerel Lige Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="budget-card">
            <div style="font-size: 0.85rem; color:var(--cl-silver); font-weight:700; letter-spacing:2px; margin-bottom:5px;" class="text-uppercase"><i class="fa-solid fa-vault text-info"></i> Kulüp Kasası</div>
            <h1 class="font-oswald m-0" style="font-size: 3rem; color: var(--cl-accent); text-shadow: 0 0 15px rgba(0,229,255,0.5);"><?= paraFormatla($benim_takim['butce']) ?></h1>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--color-loss)' ?>; color: <?= $mesaj_tipi == 'success' ? '#000' : '#fff' ?>;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills justify-content-center mb-4 gap-3" id="transferTabs">
            <li class="nav-item">
                <button class="nav-link active font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#pazar"><i class="fa-solid fa-globe"></i> Avrupa Pazarı</button>
            </li>
            <li class="nav-item">
                <button class="nav-link font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#satis"><i class="fa-solid fa-hand-holding-dollar"></i> Oyuncu Sat</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pazar">
                <div class="panel-card shadow-lg">
                    <form method="GET" class="filter-bar">
                        <input type="text" name="q" class="filter-input" placeholder="Yıldız oyuncu veya takım ara..." value="<?= htmlspecialchars($arama) ?>">
                        <select name="mevki" class="filter-select">
                            <option value="">Tüm Mevkiler</option>
                            <option value="K" <?= $mevki_filtre=='K'?'selected':'' ?>>Kaleci</option>
                            <option value="D" <?= $mevki_filtre=='D'?'selected':'' ?>>Defans</option>
                            <option value="OS" <?= $mevki_filtre=='OS'?'selected':'' ?>>Orta Saha</option>
                            <option value="F" <?= $mevki_filtre=='F'?'selected':'' ?>>Forvet</option>
                        </select>
                        <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Ara</button>
                    </form>
                    
                    <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th width="5%">Pos</th>
                                    <th>Oyuncu İsmi</th>
                                    <th>Kulüp</th>
                                    <th>Yaş</th>
                                    <th>OVR</th>
                                    <th>Form</th>
                                    <th class="text-end">Piyasa Değeri</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pazardaki_oyuncular as $o): ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-start text-white"><?= htmlspecialchars($o['isim']) ?></td>
                                    <td class="text-start">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <img src="<?= $o['logo'] ?>" style="width:24px; height:24px; object-fit:contain;">
                                            <span class="text-muted"><?= htmlspecialchars($o['takim_adi']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="text-warning fw-bold"><?= $o['form'] ?>.0</td>
                                    <td class="text-end font-oswald fs-5" style="color: var(--color-win); letter-spacing:0.5px;"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <?php if($benim_takim['butce'] >= $o['fiyat']): ?>
                                                <button type="submit" name="satin_al" class="btn-buy" onclick="return confirm('<?= $o['isim'] ?> adlı oyuncuyu <?= paraFormatla($o['fiyat']) ?> karşılığında satın almayı onaylıyor musunuz?');"><i class="fa-solid fa-file-signature"></i> Teklif Yap</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm fw-bold disabled border-0" style="font-size: 0.75rem; background:rgba(255,255,255,0.1);">Bütçe Yetersiz</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($pazardaki_oyuncular)): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted font-oswald fs-5"><i class="fa-solid fa-ghost fs-3 mb-2 d-block opacity-50"></i> Aradığınız kriterlerde oyuncu bulunamadı.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="satis">
                <div class="panel-card shadow-lg border-danger">
                    <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th width="5%">Pos</th>
                                    <th>Oyuncu İsmi</th>
                                    <th>Kadro Durumu</th>
                                    <th>Yaş</th>
                                    <th>OVR</th>
                                    <th class="text-end">Beklenen Gelir</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($benim_oyuncularim as $o): ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-start text-white"><?= htmlspecialchars($o['isim']) ?></td>
                                    <td>
                                        <?php if($o['ilk_11']==1): ?><span class="badge" style="background:var(--cl-accent); color:#000;">İlk 11</span>
                                        <?php elseif($o['yedek']==1): ?><span class="badge bg-warning text-dark">Yedek</span>
                                        <?php else: ?><span class="badge bg-secondary">Kadro Dışı</span><?php endif; ?>
                                    </td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box border-secondary" style="color:#fff;"><?= $o['ovr'] ?></span></td>
                                    <td class="text-end font-oswald fs-5" style="color: var(--color-win);"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <button type="submit" name="sat" class="btn-sell" onclick="return confirm('<?= $o['isim'] ?> adlı oyuncuyu kasaya <?= paraFormatla($o['fiyat']) ?> koymak için Avrupa pazarına satmak istediğinize emin misiniz?');"><i class="fa-solid fa-money-bill-wave"></i> Satışa Çıkar</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(11,15,25,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important;">
        <a href="cl.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="cl_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="cl_transfer.php" class="text-info text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block"></i> Transfer
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>