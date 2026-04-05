<?php
// ==============================================================================
// BUNDESLIGA - KADRO VE TAKTİK MERKEZİ (RED & GOLD SPANISH THEME)
// ==============================================================================
include '../db.php';

// --- VERİTABANI GÜVENLİK KONTROLLERİ ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS de_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM de_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO de_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

// Kullanıcı ayarlarını çek
$ayar = $pdo->query("SELECT * FROM de_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) {
    header("Location: bundesliga.php");
    exit;
}

$mesaj = "";
$mesaj_tipi = "";

$takim = $pdo->query("SELECT * FROM de_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

// --- TAKTİK KAYDETME ---
if (isset($_POST['taktik_kaydet'])) {
    $dizilis = $_POST['dizilis'] ?? '4-3-3';
    $oyun_tarzi = $_POST['oyun_tarzi'] ?? 'Dengeli';
    $pres = $_POST['pres'] ?? 'Orta';
    $tempo = $_POST['tempo'] ?? 'Normal';
    
    $stmt = $pdo->prepare("UPDATE de_takimlar SET dizilis=?, oyun_tarzi=?, pres=?, tempo=? WHERE id=?");
    $stmt->execute([$dizilis, $oyun_tarzi, $pres, $tempo, $kullanici_takim_id]);
    
    $mesaj = "Alman taktikleri başarıyla güncellendi! ¡Vamos!";
    $mesaj_tipi = "success";
    
    $takim['dizilis'] = $dizilis; $takim['oyun_tarzi'] = $oyun_tarzi; $takim['pres'] = $pres; $takim['tempo'] = $tempo;
}

// --- OYUNCU STATÜ DEĞİŞTİRME ---
if (isset($_GET['islem']) && isset($_GET['oyuncu_id'])) {
    $islem = $_GET['islem'];
    $oyuncu_id = (int)$_GET['oyuncu_id'];
    
    $benim_mi = $pdo->query("SELECT COUNT(*) FROM de_oyuncular WHERE id=$oyuncu_id AND takim_id=$kullanici_takim_id")->fetchColumn();
    
    if ($benim_mi) {
        if ($islem == 'ilk11_yap') {
            $ilk11_sayisi = $pdo->query("SELECT COUNT(*) FROM de_oyuncular WHERE takim_id=$kullanici_takim_id AND ilk_11=1")->fetchColumn();
            if ($ilk11_sayisi >= 11) {
                $mesaj = "Sahaya en fazla 11 oyuncu sürebilirsiniz! Önce birini yedeğe çekin.";
                $mesaj_tipi = "danger";
            } else {
                $pdo->exec("UPDATE de_oyuncular SET ilk_11=1, yedek=0 WHERE id=$oyuncu_id");
            }
        } elseif ($islem == 'yedek_yap') {
            $pdo->exec("UPDATE de_oyuncular SET ilk_11=0, yedek=1 WHERE id=$oyuncu_id");
        } elseif ($islem == 'kadro_disi_yap') {
            $pdo->exec("UPDATE de_oyuncular SET ilk_11=0, yedek=0 WHERE id=$oyuncu_id");
        }
        
        if(!$mesaj) {
            header("Location: bl_kadro.php"); 
            exit;
        }
    }
}

// Tüm Kadroyu Çek ve Kategorize Et
$tum_kadro = $pdo->query("SELECT * FROM de_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$ilk_11 = []; $yedekler = []; $kadro_disi = [];
foreach($tum_kadro as $o) {
    if($o['ilk_11'] == 1) $ilk_11[] = $o;
    elseif($o['yedek'] == 1) $yedekler[] = $o;
    else $kadro_disi[] = $o;
}

$ilk11_count = count($ilk_11);
$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM de_oyuncular WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kadro & Taktik | Bundesliga</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* BUNDESLIGA TEMASI */
        :root {
            --de-primary: #e11d48;
            --de-secondary: #dc2626;
            --de-accent: #ff6b35;
            --de-dark: #0d0d0d;
            
            --bg-body: #0d0d0d;
            --bg-panel: #1a1a1a;
            --border-color: rgba(220, 38, 38, 0.2);
            
            --text-primary: #f9fafb;
            --text-muted: #e11d48;
            
            --color-win: #22c55e;
            --color-loss: #ef4444;
            --color-warning: #f87171;
        }

        body { 
            background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; position: relative;
            background-image: radial-gradient(circle at 0% 0%, rgba(200,16,46,0.12) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(220,38,38,0.08) 0%, transparent 50%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        
        .pro-navbar { background: rgba(10, 10, 10, 0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--de-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--de-primary); }
        .nav-brand i { color: var(--de-secondary); }
        .nav-link-item { color: var(--text-muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: var(--de-secondary); text-shadow: 0 0 10px var(--de-secondary); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.2); text-align: center; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .panel-header { background: rgba(0,0,0,0.3); border-bottom: 1px solid var(--border-color); padding: 1.2rem; font-weight: 700; font-size: 1.2rem;}

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--de-secondary); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.4);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(220,38,38,0.05); }

        .pos-badge { display: inline-block; width: 35px; text-align: center; padding: 4px 0; border-radius: 4px; font-weight: 800; font-size: 0.75rem; color: #000; }
        .pos-K { background: #fca5a5; } .pos-D { background: #3b82f6; color: #fff; } .pos-OS { background: #10b981; } .pos-F { background: var(--de-primary); color: #fff;}
        
        .ovr-box { background: rgba(0,0,0,0.3); color: var(--de-secondary); font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(220,38,38,0.3);}
        
        .btn-taktik { background: var(--de-secondary); color: var(--de-dark); font-weight: 800; border: none; padding: 10px; width: 100%; border-radius: 6px; transition: 0.3s;}
        .btn-taktik:hover { background: #ef4444; color: #000; box-shadow: 0 0 15px var(--de-secondary); transform: translateY(-2px);}

        .progress-bar-custom { height: 6px; border-radius: 3px; background-color: #000; overflow: hidden; width: 60px; margin: 0 auto; margin-top: 5px; border: 1px solid rgba(255,255,255,0.1);}
        .progress-fill { height: 100%; }

        .action-icon { color: var(--text-muted); font-size: 1.1rem; margin: 0 5px; transition: 0.2s; cursor: pointer; text-decoration: none;}
        .action-icon.up:hover { color: var(--de-secondary); transform: translateY(-2px); text-shadow: 0 0 5px var(--de-secondary);}
        .action-icon.down:hover { color: var(--color-warning); transform: translateY(2px);}
        .action-icon.out:hover { color: var(--color-loss); transform: scale(1.1);}
        
        .status-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 50px; font-weight: bold; margin-left: 8px;}
        .status-injured { background: rgba(200,16,46,0.2); color: var(--color-loss); border: 1px solid var(--color-loss);}
        .status-banned { background: rgba(220,38,38,0.2); color: var(--color-warning); border: 1px solid var(--color-warning);}
        
        .form-select { background-color: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; font-weight: 600;}
        .form-select:focus { border-color: var(--de-secondary); box-shadow: 0 0 0 0.25rem rgba(220,38,38,0.25); background-color: rgba(0,0,0,0.5); color: #fff;}
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="bundesliga.php" class="nav-brand"><i class="fa-solid fa-shield-halved"></i> <span class="font-oswald">BUNDESLIGA</span></a>
        
        <div class="nav-menu d-none d-lg-flex gap-3">
            <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
            <a href="bundesliga.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Maç Merkezi</a>
            <a href="bl_kadro.php" class="nav-link-item text-white fw-bold" style="text-shadow: 0 0 10px var(--de-secondary);"><i class="fa-solid fa-users"></i> Kadro / Taktik</a>
        </div>

        <div class="d-flex gap-3">
            <a href="bundesliga.php" class="nav-link-item text-danger border-danger" style="border: 1px solid; padding: 8px 20px; border-radius: 4px;">
                <i class="fa-solid fa-flag"></i> Bundesliga'ya Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <img src="<?= htmlspecialchars($takim['logo']) ?>" alt="Logo" style="width: 90px; height: 90px; object-fit: contain; margin-bottom: 15px; filter: drop-shadow(0 0 15px rgba(255,255,255,0.4));">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3rem; text-shadow: 0 0 20px rgba(220,38,38,0.3);"><?= htmlspecialchars($takim['takim_adi']) ?></h1>
        <p class="fw-bold mt-2" style="color: var(--de-secondary);">
            <i class="fa-solid fa-bolt text-warning"></i> OVR: <span class="text-white"><?= $takim_kalitesi ?></span> &nbsp;|&nbsp; 
            Sahadaki Oyuncular: <span class="<?= $ilk11_count == 11 ? 'text-success' : 'text-danger' ?>"><?= $ilk11_count ?>/11</span>
        </p>
    </div>

    <div class="container py-5" style="max-width: 1600px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? 'var(--color-win)' : 'var(--color-loss)' ?>; color: #000;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-3 col-lg-4">
                <div class="panel-card p-4">
                    <h3 class="font-oswald mb-4 text-center border-bottom pb-3" style="border-color:var(--border-color); color:var(--de-primary);">
                        <i class="fa-solid fa-chess-board"></i> Taktik Odası
                    </h3>
                    
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase" style="font-size:0.8rem; color:var(--de-secondary);">Diziliş (Formasyon)</label>
                            <select name="dizilis" class="form-select">
                                <option value="4-3-3" <?= ($takim['dizilis'] ?? '')==='4-3-3'?'selected':'' ?>>4-3-3 Ofansif (Tiki-Taka)</option>
                                <option value="4-4-2" <?= ($takim['dizilis'] ?? '')==='4-4-2'?'selected':'' ?>>4-4-2 Dengeli</option>
                                <option value="4-2-3-1" <?= ($takim['dizilis'] ?? '')==='4-2-3-1'?'selected':'' ?>>4-2-3-1 Kontrol</option>
                                <option value="3-5-2" <?= ($takim['dizilis'] ?? '')==='3-5-2'?'selected':'' ?>>3-5-2 Kanat Oyunu</option>
                                <option value="5-3-2" <?= ($takim['dizilis'] ?? '')==='5-3-2'?'selected':'' ?>>5-3-2 Katı Savunma</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase" style="font-size:0.8rem; color:var(--de-secondary);">Oyun Tarzı</label>
                            <select name="oyun_tarzi" class="form-select">
                                <option value="Dengeli" <?= ($takim['oyun_tarzi'] ?? '')==='Dengeli'?'selected':'' ?>>Dengeli</option>
                                <option value="Hücum" <?= ($takim['oyun_tarzi'] ?? '')==='Hücum'?'selected':'' ?>>Tam Saha Hücum</option>
                                <option value="Kontratak" <?= ($takim['oyun_tarzi'] ?? '')==='Kontratak'?'selected':'' ?>>Kontratak</option>
                                <option value="Topa Sahip Olma" <?= ($takim['oyun_tarzi'] ?? '')==='Topa Sahip Olma'?'selected':'' ?>>Topa Sahip Olma (Tiki-Taka)</option>
                                <option value="Otobüs Çek" <?= ($takim['oyun_tarzi'] ?? '')==='Otobüs Çek'?'selected':'' ?>>Kaleye Otobüs Çek</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase" style="font-size:0.8rem; color:var(--de-secondary);">Takım Presi</label>
                            <select name="pres" class="form-select">
                                <option value="Düşük" <?= ($takim['pres'] ?? '')==='Düşük'?'selected':'' ?>>Düşük Pres (Geri Çekil)</option>
                                <option value="Orta" <?= ($takim['pres'] ?? '')==='Orta'?'selected':'' ?>>Orta Saha Presi</option>
                                <option value="Yüksek" <?= ($takim['pres'] ?? '')==='Yüksek'?'selected':'' ?>>Gegenpressing (Şok Pres)</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase" style="font-size:0.8rem; color:var(--de-secondary);">Oyun Temposu</label>
                            <select name="tempo" class="form-select">
                                <option value="Yavaş" <?= ($takim['tempo'] ?? '')==='Yavaş'?'selected':'' ?>>Yavaş & Sabırlı (Tiki-Taka)</option>
                                <option value="Normal" <?= ($takim['tempo'] ?? '')==='Normal'?'selected':'' ?>>Normal</option>
                                <option value="Çok Hızlı" <?= ($takim['tempo'] ?? '')==='Çok Hızlı'?'selected':'' ?>>Alman Temposu (Çok Hızlı)</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="taktik_kaydet" class="btn-taktik"><i class="fa-solid fa-satellite-dish"></i> ¡Táctica al Campo!</button>
                    </form>
                </div>
            </div>

            <div class="col-xl-9 col-lg-8">
                
                <div class="panel-card mb-4" style="border-color: var(--de-secondary);">
                    <div class="panel-header text-white" style="background: rgba(220, 38, 38, 0.1); border-bottom-color: var(--de-secondary);">
                        <i class="fa-solid fa-shirt" style="color:var(--de-secondary);"></i> ONCE TITULAR (İlk 11)
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($ilk_11 as $o): 
                                    $status = "";
                                    if(($o['ceza_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif(($o['sakatlik_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold" style="color: <?= $o['form'] >= 7 ? 'var(--color-win)' : ($o['form'] <= 4 ? 'var(--color-loss)' : 'var(--color-warning)') ?>;"><?= $o['form'] ?>.0</td>
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

                <div class="panel-card mb-4" style="border-color: rgba(251, 191, 36, 0.4);">
                    <div class="panel-header text-warning" style="background: rgba(251, 191, 36, 0.1); border-bottom-color: rgba(251, 191, 36, 0.3);">
                        <i class="fa-solid fa-chair"></i> SUPLENTES (Yedek Kulübesi)
                    </div>
                    <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($yedekler as $o): 
                                    $status = "";
                                    if(($o['ceza_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif(($o['sakatlik_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box" style="color: var(--text-muted); border-color: rgba(255,255,255,0.1);"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold" style="color: <?= $o['form'] >= 7 ? 'var(--color-win)' : ($o['form'] <= 4 ? 'var(--color-loss)' : 'var(--color-warning)') ?>;"><?= $o['form'] ?>.0</td>
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

                <div class="panel-card" style="border-color: rgba(200, 16, 46, 0.3);">
                    <div class="panel-header" style="background: rgba(200, 16, 46, 0.05); color: var(--de-primary); border-bottom-color: rgba(200, 16, 46, 0.2);">
                        <i class="fa-solid fa-ban"></i> RESERVAS (Kadro Dışı)
                    </div>
                    <div class="table-responsive" style="max-height: 350px; overflow-y:auto;">
                        <table class="data-table">
                            <thead>
                                <tr><th width="5%">Pos</th><th>Oyuncu</th><th class="text-center">OVR</th><th class="text-center">Form</th><th class="text-center">Fitness</th><th class="text-center">İşlem</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($kadro_disi as $o): 
                                    $status = "";
                                    if(($o['ceza_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-banned'>{$o['ceza_hafta']}H Cezalı</span>";
                                    elseif(($o['sakatlik_hafta'] ?? 0) > 0) $status = "<span class='status-badge status-injured'>Sakat</span>";
                                ?>
                                <tr>
                                    <td><span class="pos-badge pos-<?= $o['mevki'] ?>" style="opacity:0.7;"><?= $o['mevki'] ?></span></td>
                                    <td class="fw-bold text-white text-start opacity-75"><?= htmlspecialchars($o['isim']) ?> <?= $status ?></td>
                                    <td><span class="ovr-box" style="filter:grayscale(100%); opacity:0.5; border:none;"><?= $o['ovr'] ?></span></td>
                                    <td class="fw-bold" style="color: <?= $o['form'] >= 7 ? 'var(--color-win)' : ($o['form'] <= 4 ? 'var(--color-loss)' : 'var(--color-warning)') ?>; opacity:0.7;"><?= $o['form'] ?>.0</td>
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

    <div class="d-flex d-lg-none fixed-bottom p-2 justify-content-around align-items-center border-top" style="background: rgba(13,13,13,0.95); backdrop-filter: blur(10px); z-index:2000; padding-bottom: 15px !important; border-top-color: var(--de-secondary) !important;">
        <a href="bundesliga.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-tv fs-5 mb-1 d-block text-white"></i> Fikstür
        </a>
        <a href="bl_kadro.php" class="text-decoration-none text-center fw-bold" style="font-size: 0.8rem; width: 33%; color: var(--de-secondary);">
            <i class="fa-solid fa-users fs-5 mb-1 d-block"></i> Kadro
        </a>
        <a href="bl_transfer.php" class="text-secondary text-decoration-none text-center" style="font-size: 0.8rem; width: 33%;">
            <i class="fa-solid fa-comments-dollar fs-5 mb-1 d-block text-white"></i> Transfer
        </a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
