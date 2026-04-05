<?php
// ==============================================================================
// SUPER LIG - TRANSFER MERKEZİ (PRO DASHBOARD)
// ==============================================================================
include '../db.php';

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) {
    header("Location: superlig.php");
    exit;
}

// Kullanıcı takımını çek
$benim_takim = $pdo->query("SELECT * FROM takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// --- SATIN ALMA İŞLEMİ ---
if (isset($_POST['satin_al'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $hedef_oyuncu = $pdo->query("SELECT * FROM oyuncular WHERE id = $oyuncu_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($hedef_oyuncu) {
        $fiyat = $hedef_oyuncu['fiyat'];
        $eski_takim_id = $hedef_oyuncu['takim_id'];
        
        if ($benim_takim['butce'] >= $fiyat) {
            // Bütçeden düş ve eski takıma ekle
            $pdo->exec("UPDATE takimlar SET butce = butce - $fiyat WHERE id = $kullanici_takim_id");
            $pdo->exec("UPDATE takimlar SET butce = butce + $fiyat WHERE id = $eski_takim_id");
            
            // Oyuncuyu transfer et (Kendi takımına al, formayı yedeğe ver)
            $pdo->exec("UPDATE oyuncular SET takim_id = $kullanici_takim_id, ilk_11 = 0, yedek = 1, saha_pozisyon = '50,50' WHERE id = $oyuncu_id");
            
            // Haberi sisteme düş
            $hafta = $ayar['hafta'];
            $haber_metni = "BOMBA TRANSFER! " . $benim_takim['takim_adi'] . ", " . $hedef_oyuncu['isim'] . " için " . number_format($fiyat/1000000, 1) . " Milyon Euro ödedi.";
            try { $pdo->exec("INSERT INTO haberler (hafta, metin, tip) VALUES ($hafta, '$haber_metni', 'transfer')"); } catch(Throwable $e){}
            
            $mesaj = "Transfer Başarılı! " . $hedef_oyuncu['isim'] . " artık kadronuzda.";
            $mesaj_tipi = "success";
            
            // Bütçeyi ekranda anlık güncelle
            $benim_takim['butce'] -= $fiyat;
        } else {
            $mesaj = "Yetersiz Bütçe! Bu oyuncu için kulübün kasasında yeterli para yok.";
            $mesaj_tipi = "danger";
        }
    }
}

// --- SATIŞ İŞLEMİ ---
if (isset($_POST['sat'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $benim_oyuncu = $pdo->query("SELECT * FROM oyuncular WHERE id = $oyuncu_id AND takim_id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($benim_oyuncu) {
        $fiyat = $benim_oyuncu['fiyat'];
        
        // Rastgele bir yapay zeka takımı bul ve ona sat
        $ai_takim = $pdo->query("SELECT id FROM takimlar WHERE id != $kullanici_takim_id ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $yeni_takim_id = $ai_takim['id'];
        
        // Bütçe işlemleri
        $pdo->exec("UPDATE takimlar SET butce = butce + $fiyat WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE takimlar SET butce = GREATEST(0, butce - $fiyat) WHERE id = $yeni_takim_id");
        
        // Oyuncuyu yolla
        $pdo->exec("UPDATE oyuncular SET takim_id = $yeni_takim_id, ilk_11 = 0, yedek = 1, saha_pozisyon = '50,50' WHERE id = $oyuncu_id");
        
        $mesaj = $benim_oyuncu['isim'] . " başarıyla satıldı. Kasamıza " . number_format($fiyat/1000000, 1) . " Milyon Euro girdi.";
        $mesaj_tipi = "success";
        
        $benim_takim['butce'] += $fiyat;
    }
}

// Arama ve Filtreleme
$arama = $_GET['q'] ?? '';
$mevki_filtre = $_GET['mevki'] ?? '';

$sql_pazar = "SELECT o.*, t.takim_adi, t.logo FROM oyuncular o JOIN takimlar t ON o.takim_id = t.id WHERE o.takim_id != $kullanici_takim_id";
if ($arama) { $sql_pazar .= " AND o.isim LIKE '%" . addslashes($arama) . "%'"; }
if ($mevki_filtre) { $sql_pazar .= " AND o.mevki = '$mevki_filtre'"; }
$sql_pazar .= " ORDER BY o.ovr DESC LIMIT 100";

$pazardaki_oyuncular = $pdo->query($sql_pazar)->fetchAll(PDO::FETCH_ASSOC);
$benim_oyuncularim = $pdo->query("SELECT * FROM oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

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
    <title>SL Transfer | Pro Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sl-red: #e30613;
            --sl-dark-red: #8a040b;
            --sl-accent: #facc15; 
            --bg-body: #0b0f19;
            --bg-panel: #161b22;
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f9fafb;
            --text-muted: #6b7280;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; 
        }

        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none; z-index: -1;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .pro-navbar {
            background: rgba(18, 22, 28, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); 
            position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;
        }
        
        .hero-banner { 
            padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); 
            background: linear-gradient(135deg, rgba(227,6,19,0.15) 0%, rgba(11,15,25,1) 100%);
            text-align: center;
        }

        .budget-card {
            background: rgba(0,0,0,0.4); border: 1px solid var(--sl-red); border-radius: 12px; 
            padding: 1.5rem 3rem; display: inline-block; box-shadow: 0 10px 30px rgba(227,6,19,0.2);
        }

        .nav-pills .nav-link { color: var(--text-muted); font-weight: 600; border-radius: 50px; padding: 10px 30px; border: 1px solid transparent;}
        .nav-pills .nav-link.active { background: var(--sl-red); color: #fff; border-color: var(--sl-red); box-shadow: 0 4px 15px rgba(227,6,19,0.4);}
        
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 10px; overflow: hidden;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.2);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #facc15; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--sl-red); color: #fff;}
        
        .ovr-box { background: rgba(255,255,255,0.1); color: #fff; font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem;}
        
        .btn-buy { background: var(--color-win); color: #000; font-weight: 700; border: none; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-buy:hover { background: #fff; transform: scale(1.05);}
        .btn-sell { background: var(--sl-red); color: #fff; font-weight: 700; border: none; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-sell:hover { background: #fff; color: var(--sl-red); transform: scale(1.05);}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="superlig.php" class="nav-brand text-decoration-none"><i class="fa-solid fa-arrow-left"></i> <span class="font-oswald">Merkeze Dön</span></a>
        <div class="font-oswald fs-4 d-none d-md-block text-white">TRANSFER BORSASI</div>
    </nav>

    <div class="hero-banner">
        <div class="budget-card">
            <div style="font-size: 0.85rem; color:var(--text-muted); font-weight:700; letter-spacing:2px; margin-bottom:5px;" class="text-uppercase">Kullanılabilir Bütçe</div>
            <h1 class="font-oswald m-0" style="font-size: 3rem; color: var(--sl-accent);"><?= paraFormatla($benim_takim['butce']) ?></h1>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <?php if($mesaj): ?>
            <div class="alert alert-<?= $mesaj_tipi ?> fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--sl-red)' ?>; color: #fff;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills justify-content-center mb-4 gap-3" id="transferTabs">
            <li class="nav-item">
                <button class="nav-link active font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#pazar"><i class="fa-solid fa-globe"></i> Global Pazar</button>
            </li>
            <li class="nav-item">
                <button class="nav-link font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#satis"><i class="fa-solid fa-hand-holding-dollar"></i> Oyuncu Sat</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pazar">
                <div class="panel-card shadow-lg">
                    <div class="p-3 border-bottom d-flex gap-2" style="border-color: var(--border-color); background: rgba(0,0,0,0.2);">
                        <form method="GET" class="d-flex gap-2 w-100">
                            <input type="text" name="q" class="form-control bg-dark text-white border-0" placeholder="Yıldız oyuncu ara..." value="<?= htmlspecialchars($arama) ?>">
                            <select name="mevki" class="form-select bg-dark text-white border-0" style="width: 150px;">
                                <option value="">Tüm Mevkiler</option>
                                <option value="K" <?= $mevki_filtre=='K'?'selected':'' ?>>Kaleci</option>
                                <option value="D" <?= $mevki_filtre=='D'?'selected':'' ?>>Defans</option>
                                <option value="OS" <?= $mevki_filtre=='OS'?'selected':'' ?>>Orta Saha</option>
                                <option value="F" <?= $mevki_filtre=='F'?'selected':'' ?>>Forvet</option>
                            </select>
                            <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </form>
                    </div>
                    
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th width="5%">Pos</th>
                                    <th>Oyuncu İsmi</th>
                                    <th>Kulüp</th>
                                    <th>Yaş</th>
                                    <th>OVR</th>
                                    <th>Form</th>
                                    <th class="text-end">Değeri</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pazardaki_oyuncular as $o): ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-start text-white"><?= htmlspecialchars($o['isim']) ?></td>
                                    <td class="text-start"><img src="<?= $o['logo'] ?>" style="width:24px; height:24px; object-fit:contain; margin-right:8px;"><?= htmlspecialchars($o['takim_adi']) ?></td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="text-warning fw-bold"><?= $o['form'] ?>.0</td>
                                    <td class="text-end fw-bold" style="color: var(--color-win);"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <?php if($benim_takim['butce'] >= $o['fiyat']): ?>
                                                <button type="submit" name="satin_al" class="btn-buy" onclick="return confirm('<?= $o['isim'] ?> adlı oyuncuyu <?= paraFormatla($o['fiyat']) ?> karşılığında satın almayı onaylıyor musunuz?');"><i class="fa-solid fa-check"></i> Al</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm fw-bold disabled" style="font-size: 0.75rem;">Yetersiz Bütçe</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($pazardaki_oyuncular)): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted font-oswald fs-5">Aradığınız kriterlerde oyuncu bulunamadı.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="satis">
                <div class="panel-card shadow-lg">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th width="5%">Pos</th>
                                    <th>Oyuncu İsmi</th>
                                    <th>Durum</th>
                                    <th>Yaş</th>
                                    <th>OVR</th>
                                    <th class="text-end">Değeri</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($benim_oyuncularim as $o): ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-start text-white"><?= htmlspecialchars($o['isim']) ?></td>
                                    <td>
                                        <?php if($o['ilk_11']==1): ?><span class="badge bg-success">İlk 11</span>
                                        <?php elseif($o['yedek']==1): ?><span class="badge bg-warning text-dark">Yedek</span>
                                        <?php else: ?><span class="badge bg-secondary">Kadro Dışı</span><?php endif; ?>
                                    </td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="text-end fw-bold" style="color: var(--color-win);"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <button type="submit" name="sat" class="btn-sell" onclick="return confirm('<?= $o['isim'] ?> adlı oyuncuyu kasaya <?= paraFormatla($o['fiyat']) ?> koymak için satmak istediğinize emin misiniz?');"><i class="fa-solid fa-money-bill-wave"></i> Sat</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>