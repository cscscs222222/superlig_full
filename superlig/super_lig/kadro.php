<?php
// ==============================================================================
// SUPER LIG - KADRO VE TAKTİK MERKEZİ (PRO DASHBOARD - FAZ 3)
// ==============================================================================
include '../db.php';

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

// Eğer takım seçilmediyse ana sayfaya geri yolla
if (!$kullanici_takim_id) {
    header("Location: superlig.php");
    exit;
}

// FAZ 3: Eksik sütunları otomatik ekle
function sutunEkleKadro($pdo, $tablo, $sutun, $tip) {
    try {
        if ($pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip");
        }
    } catch(Throwable $e) {}
}
sutunEkleKadro($pdo, 'oyuncular', 'play_styles',   "VARCHAR(255) DEFAULT NULL");
sutunEkleKadro($pdo, 'oyuncular', 'sakatlik_turu', "VARCHAR(100) DEFAULT NULL");
sutunEkleKadro($pdo, 'oyuncular', 'ulke',          "VARCHAR(60)  DEFAULT 'Türkiye'");

$mesaj = "";
$mesaj_tipi = "";

// Kullanıcının Takımını Çek
$takim = $pdo->query("SELECT * FROM takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

// --- TAKTİK KAYDETME ---
if (isset($_POST['taktik_kaydet'])) {
    $dizilis = $_POST['dizilis'] ?? '4-3-3';
    $oyun_tarzi = $_POST['oyun_tarzi'] ?? 'Dengeli';
    $pres = $_POST['pres'] ?? 'Orta';
    $tempo = $_POST['tempo'] ?? 'Normal';
    
    $stmt = $pdo->prepare("UPDATE takimlar SET dizilis=?, oyun_tarzi=?, pres=?, tempo=? WHERE id=?");
    $stmt->execute([$dizilis, $oyun_tarzi, $pres, $tempo, $kullanici_takim_id]);
    
    $mesaj = "Taktik ve diziliş başarıyla güncellendi!";
    $mesaj_tipi = "success";
    
    // Değerleri anlık güncelle
    $takim['dizilis'] = $dizilis; $takim['oyun_tarzi'] = $oyun_tarzi; $takim['pres'] = $pres; $takim['tempo'] = $tempo;
}

// --- OYUNCU STATÜ DEĞİŞTİRME (İLK 11, YEDEK, KADRO DIŞI) ---
if (isset($_GET['islem']) && isset($_GET['oyuncu_id'])) {
    $islem = $_GET['islem'];
    $oyuncu_id = (int)$_GET['oyuncu_id'];
    
    // Güvenlik: Oyuncu benim mi?
    $benim_mi = $pdo->query("SELECT COUNT(*) FROM oyuncular WHERE id=$oyuncu_id AND takim_id=$kullanici_takim_id")->fetchColumn();
    
    if ($benim_mi) {
        if ($islem == 'ilk11_yap') {
            $ilk11_sayisi = $pdo->query("SELECT COUNT(*) FROM oyuncular WHERE takim_id=$kullanici_takim_id AND ilk_11=1")->fetchColumn();
            if ($ilk11_sayisi >= 11) {
                $mesaj = "Sahaya en fazla 11 oyuncu sürebilirsiniz! Önce birini yedeğe çekin.";
                $mesaj_tipi = "danger";
            } else {
                $pdo->exec("UPDATE oyuncular SET ilk_11=1, yedek=0 WHERE id=$oyuncu_id");
            }
        } elseif ($islem == 'yedek_yap') {
            $pdo->exec("UPDATE oyuncular SET ilk_11=0, yedek=1 WHERE id=$oyuncu_id");
        } elseif ($islem == 'kadro_disi_yap') {
            $pdo->exec("UPDATE oyuncular SET ilk_11=0, yedek=0 WHERE id=$oyuncu_id");
        }
        
        if(!$mesaj) {
            header("Location: kadro.php"); 
            exit;
        }
    }
}

// Tüm Oyuncuları Çek ve Kategorize Et
$tum_kadro = $pdo->query("SELECT * FROM oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$ilk_11 = []; $yedekler = []; $kadro_disi = [];
foreach($tum_kadro as $o) {
    if($o['ilk_11'] == 1) $ilk_11[] = $o;
    elseif($o['yedek'] == 1) $yedekler[] = $o;
    else $kadro_disi[] = $o;
}

$ilk11_count = count($ilk_11);
$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM oyuncular WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SL Manager | Kadro & Taktik</title>
    
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

        body { background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative; }
        body::before {
            content: ""; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none; z-index: -1;
        }
        .bg-gradient-layer {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -2;
            background-image: radial-gradient(circle at 0% 0%, var(--sl-dark-red) 0%, transparent 60%), radial-gradient(circle at 100% 100%, rgba(227,6,19,0.06) 0%, transparent 50%);
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .pro-navbar { background: rgba(18, 22, 28, 0.85); backdrop-filter: blur(24px); border-bottom: 1px solid var(--border-color); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 700; color: #fff; text-decoration: none; }
        .nav-link-item { color: var(--text-secondary); font-weight: 500; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: linear-gradient(135deg, rgba(227,6,19,0.15) 0%, rgba(11,15,25,1) 100%); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-header { background: rgba(0,0,0,0.3); border-bottom: 1px solid var(--border-color); padding: 1.2rem; font-weight: 700; font-size: 1.2rem;}

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.2);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.02); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #facc15; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--sl-red); color: #fff;}
        
        .ovr-box { background: rgba(255,255,255,0.1); color: #fff; font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem;}
        
        .btn-taktik { background: var(--sl-red); color: #fff; font-weight: 700; border: none; padding: 10px; width: 100%; border-radius: 6px; transition: 0.2s;}
        .btn-taktik:hover { background: #fff; color: var(--sl-red); }

        .progress-bar-custom { height: 6px; border-radius: 3px; background-color: #333; overflow: hidden; width: 60px; margin: 0 auto; margin-top: 5px;}
        .progress-fill { height: 100%; }

        .action-icon { color: var(--text-muted); font-size: 1.1rem; margin: 0 5px; transition: 0.2s; cursor: pointer; text-decoration: none;}
        .action-icon.up:hover { color: #10b981; transform: translateY(-2px);}
        .action-icon.down:hover { color: var(--sl-accent); transform: translateY(2px);}
        .action-icon.out:hover { color: var(--sl-red); }
        
        .status-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 50px; font-weight: bold; margin-left: 8px;}
        .status-injured { background: rgba(227,6,19,0.2); color: #ef4444; border: 1px solid #ef4444;}
        .status-banned { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid #fbbf24;}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="superlig.php" class="nav-brand text-decoration-none">
            <i class="fa-solid fa-star-and-crescent" style="color: var(--sl-red);"></i> 
            <span class="font-oswald text-white">Süper Lig</span>
        </a>
        
        <div class="nav-menu d-none d-lg-flex gap-1">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Ana Hub</a>
            <a href="superlig.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="kadro.php" class="nav-link-item text-white fw-bold"><i class="fa-solid fa-users-viewfinder"></i> Kadro / Taktik</a>
            <a href="transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
            <a href="puan.php" class="nav-link-item"><i class="fa-solid fa-chart-pie"></i> İstatistik</a>
        </div>
        <div></div> </nav>

    <div class="hero-banner">
        <img src="<?= $takim['logo'] ?>" alt="Logo" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 5px 15px rgba(227,6,19,0.4));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3rem; text-shadow: 0 5px 15px rgba(0,0,0,0.5);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
        <p class="text-secondary fw-bold mt-2"><i class="fa-solid fa-star text-warning"></i> Takım Kalitesi (OVR): <span class="text-white"><?= $takim_kalitesi ?></span> &nbsp;|&nbsp; İlk 11: <span class="<?= $ilk11_count == 11 ? 'text-success' : 'text-danger' ?>"><?= $ilk11_count ?>/11</span></p>
    </div>

    <div class="container py-5" style="max-width: 1600px;">
        
        <?php if($mesaj): ?>
            <div class="alert alert-<?= $mesaj_tipi ?> fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--sl-red)' ?>; color: #fff;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4">
                <div class="panel-card p-4">
                    <h3 class="font-oswald mb-4 text-center border-bottom border-secondary pb-3"><i class="fa-solid fa-chess-board text-danger"></i> Taktik Odası</h3>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size:0.8rem;">Diziliş (Formasyon)</label>
                            <select name="dizilis" class="form-select bg-dark text-white border-secondary fw-bold">
                                <option value="4-3-3" <?= $takim['dizilis']=='4-3-3'?'selected':'' ?>>4-3-3 Ofansif</option>
                                <option value="4-4-2" <?= $takim['dizilis']=='4-4-2'?'selected':'' ?>>4-4-2 Dengeli</option>
                                <option value="4-2-3-1" <?= $takim['dizilis']=='4-2-3-1'?'selected':'' ?>>4-2-3-1 Kontrol</option>
                                <option value="3-5-2" <?= $takim['dizilis']=='3-5-2'?'selected':'' ?>>3-5-2 Kanat Oyunu</option>
                                <option value="5-3-2" <?= $takim['dizilis']=='5-3-2'?'selected':'' ?>>5-3-2 Katı Savunma</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size:0.8rem;">Oyun Tarzı</label>
                            <select name="oyun_tarzi" class="form-select bg-dark text-white border-secondary fw-bold">
                                <option value="Dengeli" <?= $takim['oyun_tarzi']=='Dengeli'?'selected':'' ?>>Dengeli</option>
                                <option value="Hücum" <?= $takim['oyun_tarzi']=='Hücum'?'selected':'' ?>>Tam Saha Hücum</option>
                                <option value="Kontratak" <?= $takim['oyun_tarzi']=='Kontratak'?'selected':'' ?>>Kontratak</option>
                                <option value="Topa Sahip Olma" <?= $takim['oyun_tarzi']=='Topa Sahip Olma'?'selected':'' ?>>Topa Sahip Olma (Tiki-Taka)</option>
                                <option value="Otobüs Çek" <?= $takim['oyun_tarzi']=='Otobüs Çek'?'selected':'' ?>>Kaleye Otobüs Çek</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size:0.8rem;">Takım Presi</label>
                            <select name="pres" class="form-select bg-dark text-white border-secondary fw-bold">
                                <option value="Düşük" <?= $takim['pres']=='Düşük'?'selected':'' ?>>Düşük Pres (Geri Çekil)</option>
                                <option value="Orta" <?= $takim['pres']=='Orta'?'selected':'' ?>>Orta Saha Presi</option>
                                <option value="Yüksek" <?= $takim['pres']=='Yüksek'?'selected':'' ?>>Önde Yüksek Şok Pres</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary fw-bold text-uppercase" style="font-size:0.8rem;">Oyun Temposu</label>
                            <select name="tempo" class="form-select bg-dark text-white border-secondary fw-bold">
                                <option value="Yavaş" <?= $takim['tempo']=='Yavaş'?'selected':'' ?>>Yavaş & Sabırlı</option>
                                <option value="Normal" <?= $takim['tempo']=='Normal'?'selected':'' ?>>Normal</option>
                                <option value="Çok Hızlı" <?= $takim['tempo']=='Çok Hızlı'?'selected':'' ?>>Çok Hızlı & Direkt</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="taktik_kaydet" class="btn-taktik"><i class="fa-solid fa-floppy-disk"></i> Taktikleri Kaydet</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-9 col-lg-8">
                
                <div class="panel-card mb-4 border-success">
                    <div class="panel-header text-success" style="background: rgba(16, 185, 129, 0.1);">
                        <i class="fa-solid fa-shirt"></i> İLK 11 (Sahaya Çıkacaklar)
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($ilk_11 as $o): 
                                    $status = "";
                                    if($o['ceza_hafta'] > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif($o['sakatlik_hafta'] > 0) {
                                        $tur_str = !empty($o['sakatlik_turu']) ? htmlspecialchars($o['sakatlik_turu']) . ' ' : '';
                                        $status = "<span class='status-badge status-injured'>{$tur_str}({$o['sakatlik_hafta']}H)</span>";
                                    }
                                    $styles_html = !empty($o['play_styles']) ? '<div class="mt-1">' . implode('', array_map(fn($s) => "<span style='background:rgba(245,158,11,0.15);border:1px solid #f59e0b;color:#f59e0b;padding:1px 5px;border-radius:3px;font-size:0.65rem;margin:1px;display:inline-block;'>⚡ " . htmlspecialchars(trim($s)) . "</span>", explode(',', $o['play_styles']))) . '</div>' : '';
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?><?= $styles_html ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-success':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
                                    </td>
                                    <td>
                                        <a href="?islem=yedek_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon down" title="Yedeğe Çek"><i class="fa-solid fa-arrow-down"></i></a>
                                        <a href="?islem=kadro_disi_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon out" title="Kadro Dışı Bırak"><i class="fa-solid fa-xmark"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($ilk_11)): ?><tr><td colspan="6" class="py-4 text-muted">Sahaya sürülecek oyuncu seçilmedi! Lütfen yedeklerden 11 kişi seçin.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel-card mb-4 border-warning">
                    <div class="panel-header text-warning" style="background: rgba(251, 191, 36, 0.1);">
                        <i class="fa-solid fa-chair"></i> YEDEKLER (Maç Kadrosunda)
                    </div>
                    <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($yedekler as $o): 
                                    $status = "";
                                    if($o['ceza_hafta'] > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif($o['sakatlik_hafta'] > 0) {
                                        $tur_str = !empty($o['sakatlik_turu']) ? htmlspecialchars($o['sakatlik_turu']) . ' ' : '';
                                        $status = "<span class='status-badge status-injured'>{$tur_str}({$o['sakatlik_hafta']}H)</span>";
                                    }
                                    $styles_html = !empty($o['play_styles']) ? '<div class="mt-1">' . implode('', array_map(fn($s) => "<span style='background:rgba(245,158,11,0.15);border:1px solid #f59e0b;color:#f59e0b;padding:1px 5px;border-radius:3px;font-size:0.65rem;margin:1px;display:inline-block;'>⚡ " . htmlspecialchars(trim($s)) . "</span>", explode(',', $o['play_styles']))) . '</div>' : '';
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?><?= $styles_html ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-success':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
                                    </td>
                                    <td>
                                        <a href="?islem=ilk11_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon up" title="İlk 11'e Al"><i class="fa-solid fa-arrow-up"></i></a>
                                        <a href="?islem=kadro_disi_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon out" title="Kadro Dışı Bırak"><i class="fa-solid fa-xmark"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($yedekler)): ?><tr><td colspan="6" class="py-3 text-muted">Yedek kulübesi boş.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel-card border-secondary">
                    <div class="panel-header text-secondary" style="background: rgba(255, 255, 255, 0.05);">
                        <i class="fa-solid fa-ban"></i> KADRO DIŞI / REZERV
                    </div>
                    <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($kadro_disi as $o): 
                                    $status = "";
                                    if($o['ceza_hafta'] > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif($o['sakatlik_hafta'] > 0) {
                                        $tur_str = !empty($o['sakatlik_turu']) ? htmlspecialchars($o['sakatlik_turu']) . ' ' : '';
                                        $status = "<span class='status-badge status-injured'>{$tur_str}({$o['sakatlik_hafta']}H)</span>";
                                    }
                                    $styles_html = !empty($o['play_styles']) ? '<div class="mt-1">' . implode('', array_map(fn($s) => "<span style='background:rgba(245,158,11,0.15);border:1px solid #f59e0b;color:#f59e0b;padding:1px 5px;border-radius:3px;font-size:0.65rem;margin:1px;display:inline-block;'>⚡ " . htmlspecialchars(trim($s)) . "</span>", explode(',', $o['play_styles']))) . '</div>' : '';
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?><?= $styles_html ?></td>
                                    <td><span class="ovr-box" style="filter:grayscale(100%);"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-success':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
                                    </td>
                                    <td>
                                        <a href="?islem=ilk11_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon up" title="İlk 11'e Al"><i class="fa-solid fa-arrow-up"></i></a>
                                        <a href="?islem=yedek_yap&oyuncu_id=<?= $o['id'] ?>" class="action-icon down" title="Yedeğe Al"><i class="fa-solid fa-arrow-down"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($kadro_disi)): ?><tr><td colspan="6" class="py-3 text-muted">Kadro dışı bırakılan oyuncu yok.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(11,15,25,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important;">
        <a href="superlig.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="kadro.php" class="text-warning text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-users fs-5 mb-1 d-block"></i> Kadro
        </a>
        <a href="transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
        <a href="puan.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 25%;">
            <i class="fa-solid fa-chart-pie fs-5 mb-1 d-block text-white"></i> Veri
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>