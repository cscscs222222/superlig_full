<?php
// ==============================================================================
// CHAMPIONS LEAGUE - KADRO VE TAKTİK MERKEZİ (BLUE & CYAN THEME)
// ==============================================================================
include '../db.php';

// --- VERİTABANI GÜVENLİK KONTROLLERİ (HATA ÖNLEME) ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cl_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM cl_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO cl_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

// Eğer takım seçilmediyse ŞL ana sayfasına geri yolla
if (!$kullanici_takim_id) {
    header("Location: cl.php");
    exit;
}

$mesaj = "";
$mesaj_tipi = "";

// Kullanıcının Takımını Çek
$takim = $pdo->query("SELECT * FROM cl_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

// --- TAKTİK KAYDETME ---
if (isset($_POST['taktik_kaydet'])) {
    $dizilis = $_POST['dizilis'] ?? '4-3-3';
    $oyun_tarzi = $_POST['oyun_tarzi'] ?? 'Dengeli';
    $pres = $_POST['pres'] ?? 'Orta';
    $tempo = $_POST['tempo'] ?? 'Normal';
    
    $stmt = $pdo->prepare("UPDATE cl_takimlar SET dizilis=?, oyun_tarzi=?, pres=?, tempo=? WHERE id=?");
    $stmt->execute([$dizilis, $oyun_tarzi, $pres, $tempo, $kullanici_takim_id]);
    
    $mesaj = "Avrupa taktikleri başarıyla güncellendi!";
    $mesaj_tipi = "success";
    
    $takim['dizilis'] = $dizilis; $takim['oyun_tarzi'] = $oyun_tarzi; $takim['pres'] = $pres; $takim['tempo'] = $tempo;
}

// --- OYUNCU STATÜ DEĞİŞTİRME (İLK 11, YEDEK, KADRO DIŞI) ---
if (isset($_GET['islem']) && isset($_GET['oyuncu_id'])) {
    $islem = $_GET['islem'];
    $oyuncu_id = (int)$_GET['oyuncu_id'];
    
    // Güvenlik: Oyuncu benim mi?
    $benim_mi = $pdo->query("SELECT COUNT(*) FROM cl_oyuncular WHERE id=$oyuncu_id AND takim_id=$kullanici_takim_id")->fetchColumn();
    
    if ($benim_mi) {
        if ($islem == 'ilk11_yap') {
            $ilk11_sayisi = $pdo->query("SELECT COUNT(*) FROM cl_oyuncular WHERE takim_id=$kullanici_takim_id AND ilk_11=1")->fetchColumn();
            if ($ilk11_sayisi >= 11) {
                $mesaj = "Sahaya en fazla 11 oyuncu sürebilirsiniz! Önce birini yedeğe çekin.";
                $mesaj_tipi = "danger";
            } else {
                $pdo->exec("UPDATE cl_oyuncular SET ilk_11=1, yedek=0 WHERE id=$oyuncu_id");
            }
        } elseif ($islem == 'yedek_yap') {
            $pdo->exec("UPDATE cl_oyuncular SET ilk_11=0, yedek=1 WHERE id=$oyuncu_id");
        } elseif ($islem == 'kadro_disi_yap') {
            $pdo->exec("UPDATE cl_oyuncular SET ilk_11=0, yedek=0 WHERE id=$oyuncu_id");
        }
        
        if(!$mesaj) {
            header("Location: cl_kadro.php"); 
            exit;
        }
    }
}

// Tüm Kadroyu Çek ve Kategorize Et
$tum_kadro = $pdo->query("SELECT * FROM cl_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$ilk_11 = []; $yedekler = []; $kadro_disi = [];
foreach($tum_kadro as $o) {
    if($o['ilk_11'] == 1) $ilk_11[] = $o;
    elseif($o['yedek'] == 1) $yedekler[] = $o;
    else $kadro_disi[] = $o;
}

$ilk11_count = count($ilk_11);
$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM cl_oyuncular WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kadro & Taktik | Champions League</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
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

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 229, 255, 0.05); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-header { background: rgba(0,229,255,0.05); border-bottom: 1px solid var(--border-color); padding: 1.2rem; font-weight: 700; font-size: 1.2rem;}

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--cl-accent); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.2);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle;}
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.05); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #facc15; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--cl-accent); color: #000;}
        
        .ovr-box { background: rgba(0,229,255,0.1); color: var(--cl-accent); font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(0,229,255,0.3);}
        
        .btn-taktik { background: linear-gradient(45deg, var(--cl-secondary), var(--cl-accent)); color: #fff; font-weight: 700; border: none; padding: 10px; width: 100%; border-radius: 6px; transition: 0.2s;}
        .btn-taktik:hover { color: #000; box-shadow: 0 0 15px var(--cl-accent); }

        .progress-bar-custom { height: 6px; border-radius: 3px; background-color: #000; overflow: hidden; width: 60px; margin: 0 auto; margin-top: 5px; border: 1px solid rgba(255,255,255,0.1);}
        .progress-fill { height: 100%; }

        .action-icon { color: var(--cl-silver); font-size: 1.1rem; margin: 0 5px; transition: 0.2s; cursor: pointer; text-decoration: none;}
        .action-icon.up:hover { color: var(--cl-accent); transform: translateY(-2px); text-shadow: 0 0 5px var(--cl-accent);}
        .action-icon.down:hover { color: #fbbf24; transform: translateY(2px);}
        .action-icon.out:hover { color: var(--color-loss); }
        
        .status-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 50px; font-weight: bold; margin-left: 8px;}
        .status-injured { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid #ef4444;}
        .status-banned { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid #fbbf24;}
    </style>
</head>
<body>

    <div class="bg-gradient-layer"></div>

    <nav class="pro-navbar">
        <a href="cl.php" class="nav-brand"><i class="fa-solid fa-futbol"></i> <span class="font-oswald">CHAMPIONS LEAGUE</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="cl.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="cl_kadro.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--cl-accent);"><i class="fa-solid fa-users-viewfinder"></i> Taktik Odası</a>
        </div>

        <div class="d-flex gap-3">
            <a href="../super_lig/superlig.php" class="btn-action-outline text-danger border-danger hover-lift">
                <i class="fa-solid fa-arrow-left"></i> Yerel Lige Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <img src="<?= $takim['logo'] ?>" alt="Logo" style="width: 80px; height: 80px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 0 20px rgba(0,229,255,0.6));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3rem; text-shadow: 0 0 20px rgba(0,229,255,0.5);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
        <p class="text-info fw-bold mt-2"><i class="fa-solid fa-star text-warning"></i> Takım Kalitesi (OVR): <span class="text-white"><?= $takim_kalitesi ?></span> &nbsp;|&nbsp; İlk 11: <span class="<?= $ilk11_count == 11 ? 'text-success' : 'text-danger' ?>"><?= $ilk11_count ?>/11</span></p>
    </div>

    <div class="container py-5" style="max-width: 1600px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--color-loss)' ?>; color: <?= $mesaj_tipi == 'success' ? '#000' : '#fff' ?>;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4">
                <div class="panel-card p-4">
                    <h3 class="font-oswald mb-4 text-center border-bottom pb-3" style="border-color:var(--border-color); color:var(--cl-accent);">
                        <i class="fa-solid fa-chess-board"></i> Taktik Odası
                    </h3>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label text-info fw-bold text-uppercase" style="font-size:0.8rem;">Diziliş (Formasyon)</label>
                            <select name="dizilis" class="form-select bg-dark text-white border-info fw-bold">
                                <option value="4-3-3" <?= $takim['dizilis']=='4-3-3'?'selected':'' ?>>4-3-3 Ofansif</option>
                                <option value="4-4-2" <?= $takim['dizilis']=='4-4-2'?'selected':'' ?>>4-4-2 Dengeli</option>
                                <option value="4-2-3-1" <?= $takim['dizilis']=='4-2-3-1'?'selected':'' ?>>4-2-3-1 Kontrol</option>
                                <option value="3-5-2" <?= $takim['dizilis']=='3-5-2'?'selected':'' ?>>3-5-2 Kanat Oyunu</option>
                                <option value="5-3-2" <?= $takim['dizilis']=='5-3-2'?'selected':'' ?>>5-3-2 Katı Savunma</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-info fw-bold text-uppercase" style="font-size:0.8rem;">Oyun Tarzı</label>
                            <select name="oyun_tarzi" class="form-select bg-dark text-white border-info fw-bold">
                                <option value="Dengeli" <?= $takim['oyun_tarzi']=='Dengeli'?'selected':'' ?>>Dengeli</option>
                                <option value="Hücum" <?= $takim['oyun_tarzi']=='Hücum'?'selected':'' ?>>Tam Saha Hücum</option>
                                <option value="Kontratak" <?= $takim['oyun_tarzi']=='Kontratak'?'selected':'' ?>>Kontratak</option>
                                <option value="Topa Sahip Olma" <?= $takim['oyun_tarzi']=='Topa Sahip Olma'?'selected':'' ?>>Topa Sahip Olma (Tiki-Taka)</option>
                                <option value="Otobüs Çek" <?= $takim['oyun_tarzi']=='Otobüs Çek'?'selected':'' ?>>Kaleye Otobüs Çek</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-info fw-bold text-uppercase" style="font-size:0.8rem;">Takım Presi</label>
                            <select name="pres" class="form-select bg-dark text-white border-info fw-bold">
                                <option value="Düşük" <?= $takim['pres']=='Düşük'?'selected':'' ?>>Düşük Pres (Geri Çekil)</option>
                                <option value="Orta" <?= $takim['pres']=='Orta'?'selected':'' ?>>Orta Saha Presi</option>
                                <option value="Yüksek" <?= $takim['pres']=='Yüksek'?'selected':'' ?>>Önde Yüksek Şok Pres</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-info fw-bold text-uppercase" style="font-size:0.8rem;">Oyun Temposu</label>
                            <select name="tempo" class="form-select bg-dark text-white border-info fw-bold">
                                <option value="Yavaş" <?= $takim['tempo']=='Yavaş'?'selected':'' ?>>Yavaş & Sabırlı</option>
                                <option value="Normal" <?= $takim['tempo']=='Normal'?'selected':'' ?>>Normal</option>
                                <option value="Çok Hızlı" <?= $takim['tempo']=='Çok Hızlı'?'selected':'' ?>>Çok Hızlı & Direkt</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="taktik_kaydet" class="btn-taktik"><i class="fa-solid fa-satellite-dish"></i> Avrupa Taktiklerini Kurgula</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-9 col-lg-8">
                
                <div class="panel-card mb-4" style="border-color: var(--cl-accent);">
                    <div class="panel-header text-white" style="background: rgba(0, 229, 255, 0.1); border-bottom-color: var(--cl-accent);">
                        <i class="fa-solid fa-shirt" style="color:var(--cl-accent);"></i> İLK 11 (Sahaya Çıkacaklar)
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
                                    elseif($o['sakatlik_hafta'] > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-info':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
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

                <div class="panel-card mb-4" style="border-color: #fbbf24;">
                    <div class="panel-header text-warning" style="background: rgba(251, 191, 36, 0.1); border-bottom-color:#fbbf24;">
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
                                    elseif($o['sakatlik_hafta'] > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-info':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
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

                <div class="panel-card">
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
                                    elseif($o['sakatlik_hafta'] > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box" style="filter:grayscale(100%);"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold <?= $o['form'] >= 8 ? 'text-success' : ($o['form'] <= 4 ? 'text-danger' : 'text-warning') ?>"><?= $o['form'] ?>.0</td>
                                    <td>
                                        <div class="fw-bold text-muted" style="font-size:0.75rem;"><?= $o['fitness'] ?>%</div>
                                        <div class="progress-bar-custom"><div class="progress-fill <?= $o['fitness']>70?'bg-info':($o['fitness']>40?'bg-warning':'bg-danger') ?>" style="width: <?= $o['fitness'] ?>%;"></div></div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>