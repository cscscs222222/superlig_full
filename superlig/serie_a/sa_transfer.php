<?php
// ==============================================================================
// SERIE A - TRANSFER BORSASI (BLUE & GREEN ITALIAN THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM it_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) {
    header("Location: serie_a.php");
    exit;
}

$benim_takim = $pdo->query("SELECT * FROM it_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

$mesaj = "";
$mesaj_tipi = "";

// --- SATIN ALMA İŞLEMİ ---
if (isset($_POST['satin_al'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $hedef_oyuncu = $pdo->query("SELECT * FROM it_oyuncular WHERE id = $oyuncu_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($hedef_oyuncu) {
        $fiyat = $hedef_oyuncu['fiyat'];
        $eski_takim_id = $hedef_oyuncu['takim_id'];
        
        if ($benim_takim['butce'] >= $fiyat) {
            try {
                $pdo->beginTransaction();
                $stmt_upd1 = $pdo->prepare("UPDATE it_takimlar SET butce = butce - ? WHERE id = ?");
                $stmt_upd1->execute([$fiyat, $kullanici_takim_id]);
                $stmt_upd2 = $pdo->prepare("UPDATE it_takimlar SET butce = butce + ? WHERE id = ?");
                $stmt_upd2->execute([$fiyat, $eski_takim_id]);
                $stmt_upd3 = $pdo->prepare("UPDATE it_oyuncular SET takim_id = ?, ilk_11 = 0, yedek = 1 WHERE id = ?");
                $stmt_upd3->execute([$kullanici_takim_id, $oyuncu_id]);
                $pdo->commit();
            } catch(Throwable $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                $mesaj = "Transfer işlemi sırasında hata oluştu. Lütfen tekrar deneyin.";
                $mesaj_tipi = "danger";
                goto skip_news;
            }
            
            $hafta = $ayar['hafta'];
            $fiyat_milyon = number_format($fiyat/1000000, 1);
            $haber_metni = "TRANSFER! " . $benim_takim['takim_adi'] . " yıldız oyuncu " . $hedef_oyuncu['isim'] . "'i €{$fiyat_milyon}M bonservisle kadrosuna kattı.";
            try { 
                $stmt_haber = $pdo->prepare("INSERT INTO it_haberler (hafta, metin, tip) VALUES (?, ?, 'transfer')");
                $stmt_haber->execute([$hafta, $haber_metni]);
            } catch(Throwable $e){}
            
            $mesaj = "Transfer tamam! " . $hedef_oyuncu['isim'] . " resmen kulübümüzde.";
            $mesaj_tipi = "success";
            
            $benim_takim['butce'] -= $fiyat;
            skip_news:
        } else {
            $mesaj = "Bütçe yetersiz! Bu transfer için Serie A kasanızda yeterli para yok.";
            $mesaj_tipi = "danger";
        }
    }
}

// --- SATIŞ İŞLEMİ ---
if (isset($_POST['sat'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $benim_oyuncu = $pdo->query("SELECT * FROM it_oyuncular WHERE id = $oyuncu_id AND takim_id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
    
    if ($benim_oyuncu) {
        $fiyat = $benim_oyuncu['fiyat'];
        
        $ai_takim_stmt = $pdo->prepare("SELECT id FROM it_takimlar WHERE id != ? ORDER BY RAND() LIMIT 1");
        $ai_takim_stmt->execute([$kullanici_takim_id]);
        $ai_takim = $ai_takim_stmt->fetch(PDO::FETCH_ASSOC);
        $yeni_takim_id = $ai_takim['id'];
        
        try {
            $pdo->beginTransaction();
            $stmt_s1 = $pdo->prepare("UPDATE it_takimlar SET butce = butce + ? WHERE id = ?");
            $stmt_s1->execute([$fiyat, $kullanici_takim_id]);
            $stmt_s2 = $pdo->prepare("UPDATE it_takimlar SET butce = GREATEST(0, butce - ?) WHERE id = ?");
            $stmt_s2->execute([$fiyat, $yeni_takim_id]);
            $stmt_s3 = $pdo->prepare("UPDATE it_oyuncular SET takim_id = ?, ilk_11 = 0, yedek = 1 WHERE id = ?");
            $stmt_s3->execute([$yeni_takim_id, $oyuncu_id]);
            $pdo->commit();
        } catch(Throwable $e) {
            if($pdo->inTransaction()) $pdo->rollBack();
            $mesaj = "Satış işlemi sırasında hata oluştu."; $mesaj_tipi = "danger";
            goto skip_sale;
        }
        
        $mesaj = $benim_oyuncu['isim'] . " satıldı. Kasamıza " . number_format($fiyat/1000000, 1) . " Milyon Euro girdi.";
        $mesaj_tipi = "success";
        
        $benim_takim['butce'] += $fiyat;
        skip_sale:
    }
}

// Arama ve Filtreleme
$arama = $_GET['q'] ?? '';
$mevki_filtre = $_GET['mevki'] ?? '';

$allowed_mevki = ['K', 'D', 'OS', 'F'];
$mevki_guvensiz = in_array($mevki_filtre, $allowed_mevki) ? $mevki_filtre : '';

$sql_pazar = "SELECT o.*, t.takim_adi, t.logo FROM it_oyuncular o JOIN it_takimlar t ON o.takim_id = t.id WHERE o.takim_id != :kid";
$params = ['kid' => $kullanici_takim_id];
if ($arama) { $sql_pazar .= " AND o.isim LIKE :arama"; $params['arama'] = '%' . $arama . '%'; }
if ($mevki_guvensiz) { $sql_pazar .= " AND o.mevki = :mevki"; $params['mevki'] = $mevki_guvensiz; }
$sql_pazar .= " ORDER BY o.ovr DESC LIMIT 150";

$stmt_pazar = $pdo->prepare($sql_pazar);
$stmt_pazar->execute($params);
$pazardaki_oyuncular = $stmt_pazar->fetchAll(PDO::FETCH_ASSOC);
$benim_oyuncularim = $pdo->prepare("SELECT * FROM it_oyuncular WHERE takim_id = ? ORDER BY ovr DESC");
$benim_oyuncularim->execute([$kullanici_takim_id]);
$benim_oyuncularim = $benim_oyuncularim->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Transfer Borsası | Serie A</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --it-primary: #10b981;
            --it-secondary: #059669;
            --it-accent: #ff6b35;
            --it-dark: #0d0d0d;
            --bg-body: #0d0d0d;
            --bg-panel: #1a1a1a;
            --border-color: rgba(5, 150, 105, 0.2);
            --text-primary: #f9fafb;
            --text-muted: #10b981;
            --color-win: #22c55e;
            --color-loss: #34d399;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(16,185,129,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(5,150,105,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .pro-navbar { background: rgba(10, 10, 10, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--it-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--it-primary); }
        .nav-brand i { color: var(--it-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--it-secondary); text-shadow: 0 0 10px var(--it-secondary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        .budget-card {
            background: linear-gradient(135deg, rgba(0,42,21,0.8), rgba(0,0,0,0.6)); 
            border: 1px solid var(--it-secondary); border-radius: 12px; 
            padding: 1.5rem 3rem; display: inline-block; box-shadow: 0 10px 30px rgba(5,150,105,0.2);
        }

        .nav-pills .nav-link { color: var(--text-muted); font-weight: 600; border-radius: 50px; padding: 10px 30px; border: 1px solid transparent; transition: 0.3s;}
        .nav-pills .nav-link:hover { color: #fff; }
        .nav-pills .nav-link.active { background: var(--it-secondary); color: var(--it-dark); border-color: var(--it-secondary); box-shadow: 0 4px 15px rgba(5,150,105,0.4);}
        
        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;}
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--it-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.4);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(5,150,105,0.05); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #fca5a5; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--it-primary); color: #fff;}
        
        .ovr-box { background: rgba(0,0,0,0.3); color: var(--it-secondary); font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(5,150,105,0.3);}
        
        .btn-buy { background: var(--it-secondary); color: var(--it-dark); font-weight: 800; border: none; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-buy:hover { background: #34d399; color: #000; box-shadow: 0 0 10px var(--it-secondary); transform: scale(1.05);}
        
        .btn-sell { background: transparent; border: 1px solid var(--it-primary); color: var(--it-primary); font-weight: 700; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-sell:hover { background: var(--it-primary); color: #fff; transform: scale(1.05); box-shadow: 0 0 10px rgba(16,185,129,0.5);}

        .filter-bar { background: rgba(0,0,0,0.5); border-bottom: 1px solid var(--border-color); padding: 15px; display: flex; gap: 10px; }
        .filter-input { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; border-radius: 6px; padding: 8px 15px; width: 100%; transition: 0.3s; font-weight:600;}
        .filter-input:focus { outline: none; border-color: var(--it-secondary); background: rgba(5,150,105,0.05); }
        .filter-select { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; border-radius: 6px; padding: 8px 15px; width: 150px; cursor: pointer; font-weight:600;}
        .filter-select:focus { outline: none; border-color: var(--it-secondary); }
        .filter-select option { background: var(--bg-panel); color: #fff; }
        .btn-search { background: var(--it-secondary); color: var(--it-dark); border: none; font-weight: 800; padding: 0 20px; border-radius: 6px; transition: 0.2s;}
        .btn-search:hover { background: #34d399; color: #000;}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="serie_a.php" class="nav-brand"><i class="fa-solid fa-shield-halved"></i> <span class="font-oswald">SERIE A</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="serie_a.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="sa_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro / Taktik</a>
            <a href="sa_transfer.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--it-secondary);"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
        </div>

        <div class="d-flex gap-3">
            <a href="serie_a.php" class="nav-link-item" style="border: 1px solid var(--it-primary); border-radius: 4px; color: var(--it-primary);">
                <i class="fa-solid fa-flag"></i> Serie A'ya Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <div class="budget-card">
            <div style="font-size: 0.85rem; color:var(--text-muted); font-weight:700; letter-spacing:2px; margin-bottom:5px;" class="text-uppercase"><i class="fa-solid fa-vault" style="color:var(--it-secondary);"></i> Transfer Bütçesi</div>
            <h1 class="font-oswald m-0" style="font-size: 3rem; color: var(--it-secondary); text-shadow: 0 0 15px rgba(5,150,105,0.5);"><?= paraFormatla($benim_takim['butce']) ?></h1>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1400px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--color-loss)' ?>; color: #000;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills justify-content-center mb-4 gap-3" id="transferTabs">
            <li class="nav-item">
                <button class="nav-link active font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#pazar"><i class="fa-solid fa-globe"></i> Serie A Pazarı</button>
            </li>
            <li class="nav-item">
                <button class="nav-link font-oswald fs-5" data-bs-toggle="pill" data-bs-target="#satis"><i class="fa-solid fa-hand-holding-dollar"></i> Oyuncu Sat</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="pazar">
                <div class="panel-card shadow-lg">
                    <form method="GET" class="filter-bar">
                        <input type="text" name="q" class="filter-input" placeholder="Serie A yıldızlarını veya takım adını ara..." value="<?= htmlspecialchars($arama) ?>">
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
                                            <img src="<?= htmlspecialchars($o['logo']) ?>" style="width:24px; height:24px; object-fit:contain;">
                                            <span style="color:var(--text-muted); font-weight:600;"><?= htmlspecialchars($o['takim_adi']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td style="color: var(--it-secondary); font-weight: 700;"><?= $o['form'] ?>.0</td>
                                    <td class="text-end font-oswald fs-5" style="color: var(--it-secondary); letter-spacing:0.5px;"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <?php if($benim_takim['butce'] >= $o['fiyat']): ?>
                                                <button type="submit" name="satin_al" class="btn-buy" onclick="return confirm('<?= htmlspecialchars($o['isim'], ENT_QUOTES) ?> adlı oyuncuyu <?= paraFormatla($o['fiyat']) ?> karşılığında satın almayı onaylıyor musunuz?');"><i class="fa-solid fa-file-signature"></i> Teklif Yap</button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary btn-sm fw-bold disabled border-0" style="font-size: 0.75rem; background:rgba(255,255,255,0.1); color:rgba(255,255,255,0.3);">Bütçe Yetersiz</button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($pazardaki_oyuncular)): ?>
                                    <tr><td colspan="8" class="text-center py-5 text-muted font-oswald fs-5"><i class="fa-solid fa-ghost fs-3 mb-2 d-block opacity-50"></i> Serie A pazarında aradığınız kriterlerde oyuncu bulunamadı.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="satis">
                <div class="panel-card shadow-lg" style="border-color: rgba(16,185,129,0.5);">
                    <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                        <table class="data-table">
                            <thead style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th width="5%" style="color:var(--it-primary);">Pos</th>
                                    <th style="color:var(--it-primary);">Oyuncu İsmi</th>
                                    <th style="color:var(--it-primary);">Kadro Durumu</th>
                                    <th style="color:var(--it-primary);">Yaş</th>
                                    <th style="color:var(--it-primary);">OVR</th>
                                    <th class="text-end" style="color:var(--it-primary);">Beklenen Gelir</th>
                                    <th class="text-center" style="color:var(--it-primary);">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($benim_oyuncularim as $o): ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-start text-white"><?= htmlspecialchars($o['isim']) ?></td>
                                    <td>
                                        <?php if($o['ilk_11']==1): ?><span class="badge" style="background:var(--it-secondary); color:var(--it-dark);">İlk 11</span>
                                        <?php elseif($o['yedek']==1): ?><span class="badge bg-warning text-dark">Yedek</span>
                                        <?php else: ?><span class="badge bg-secondary">Kadro Dışı</span><?php endif; ?>
                                    </td>
                                    <td class="text-muted fw-bold"><?= $o['yas'] ?></td>
                                    <td><span class="ovr-box" style="border-color:rgba(255,255,255,0.1); color:#fff;"><?= $o['ovr'] ?></span></td>
                                    <td class="text-end font-oswald fs-5" style="color: var(--it-secondary);"><?= paraFormatla($o['fiyat']) ?></td>
                                    <td class="text-center">
                                        <form method="POST">
                                            <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                            <button type="submit" name="sat" class="btn-sell" onclick="return confirm('<?= htmlspecialchars($o['isim'], ENT_QUOTES) ?> adlı oyuncuyu kasaya <?= paraFormatla($o['fiyat']) ?> koymak için satmak istediğinize emin misiniz?');"><i class="fa-solid fa-money-bill-wave"></i> Satışa Çıkar</button>
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

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(13,13,13,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important; border-top-color: var(--it-secondary) !important;">
        <a href="serie_a.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="sa_kadro.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block text-white"></i> Kadro
        </a>
        <a href="sa_transfer.php" class="text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 33%; color: var(--it-secondary);">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block"></i> Transfer
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
