<?php
// ==============================================================================
// LIGUE 1 - KADRO VE TAKTİK MERKEZİ (BLUE & RED FRENCH THEME)
// ==============================================================================
include '../db.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fr_ayar ( id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL )");
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM fr_ayar")->fetchColumn();
    if($ayar_sayisi == 0) { $pdo->exec("INSERT INTO fr_ayar (hafta, sezon_yil) VALUES (1, 2025)"); }
} catch (Throwable $e) {}

$ayar = $pdo->query("SELECT * FROM fr_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;

if (!$kullanici_takim_id) { header("Location: ligue1.php"); exit; }

$mesaj = "";
$mesaj_tipi = "";

$takim = $pdo->query("SELECT * FROM fr_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);

if (isset($_POST['taktik_kaydet'])) {
    $dizilis    = $_POST['dizilis']    ?? '4-3-3';
    $oyun_tarzi = $_POST['oyun_tarzi'] ?? 'Dengeli';
    $pres       = $_POST['pres']       ?? 'Orta';
    $tempo      = $_POST['tempo']      ?? 'Normal';
    $stmt = $pdo->prepare("UPDATE fr_takimlar SET dizilis=?, oyun_tarzi=?, pres=?, tempo=? WHERE id=?");
    $stmt->execute([$dizilis, $oyun_tarzi, $pres, $tempo, $kullanici_takim_id]);
    $mesaj = "Ligue 1 taktikleri başarıyla güncellendi!";
    $mesaj_tipi = "success";
    $takim['dizilis'] = $dizilis; $takim['oyun_tarzi'] = $oyun_tarzi; $takim['pres'] = $pres; $takim['tempo'] = $tempo;
}

if (isset($_GET['islem']) && isset($_GET['oyuncu_id'])) {
    $islem     = $_GET['islem'];
    $oyuncu_id = (int)$_GET['oyuncu_id'];
    $benim_mi  = $pdo->query("SELECT COUNT(*) FROM fr_oyuncular WHERE id=$oyuncu_id AND takim_id=$kullanici_takim_id")->fetchColumn();
    if ($benim_mi) {
        if ($islem == 'ilk11_yap') {
            $ilk11_sayisi = $pdo->query("SELECT COUNT(*) FROM fr_oyuncular WHERE takim_id=$kullanici_takim_id AND ilk_11=1")->fetchColumn();
            if ($ilk11_sayisi >= 11) {
                $mesaj = "Sahaya en fazla 11 oyuncu sürebilirsiniz! Önce birini yedeğe çekin.";
                $mesaj_tipi = "danger";
            } else {
                $pdo->exec("UPDATE fr_oyuncular SET ilk_11=1, yedek=0 WHERE id=$oyuncu_id");
            }
        } elseif ($islem == 'yedek_yap') {
            $pdo->exec("UPDATE fr_oyuncular SET ilk_11=0, yedek=1 WHERE id=$oyuncu_id");
        } elseif ($islem == 'kadro_disi_yap') {
            $pdo->exec("UPDATE fr_oyuncular SET ilk_11=0, yedek=0 WHERE id=$oyuncu_id");
        }
        if(!$mesaj) { header("Location: l1_kadro.php"); exit; }
    }
}

$tum_kadro = $pdo->query("SELECT * FROM fr_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

$ilk_11 = []; $yedekler = []; $kadro_disi = [];
foreach($tum_kadro as $o) {
    if($o['ilk_11'] == 1) $ilk_11[] = $o;
    elseif($o['yedek'] == 1) $yedekler[] = $o;
    else $kadro_disi[] = $o;
}

$ilk11_count    = count($ilk_11);
$takim_kalitesi = $pdo->query("SELECT AVG(ovr) FROM fr_oyuncular WHERE takim_id = $kullanici_takim_id AND ilk_11 = 1")->fetchColumn();
$takim_kalitesi = $takim_kalitesi ? round($takim_kalitesi, 1) : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kadro & Taktik | Ligue 1</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --fr-primary: #003f8a; --fr-secondary: #ef4135; --fr-gold: #d4af37;
            --bg: #0d0d0d; --panel: #1a1a1a; --border: rgba(0,63,138,0.25);
            --text: #f9fafb; --muted: #94a3b8;
        }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }
        .pro-navbar { background: rgba(10,10,10,0.97); backdrop-filter: blur(24px); border-bottom: 2px solid var(--fr-secondary); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; }
        .nav-brand i { color: var(--fr-secondary); }
        .nav-link-item { color: var(--muted); font-weight: 600; font-size: 0.95rem; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; }
        .btn-ap { background: var(--fr-primary); color: #fff; font-weight: 800; padding: 8px 20px; border-radius: 4px; text-decoration: none; border: none; transition: 0.3s; }
        .btn-ap:hover { background: var(--fr-secondary); color: #fff; }
        .panel-card { background: var(--panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
        .panel-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.3); }
        .panel-header h5 { color: var(--fr-gold); margin: 0; font-family: 'Oswald', sans-serif; font-size: 1rem; text-transform: uppercase; }
        .player-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid rgba(255,255,255,0.04); transition: 0.2s; }
        .player-row:hover { background: rgba(0,63,138,0.08); }
        .ovr-badge { width: 38px; height: 38px; border-radius: 8px; background: var(--fr-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-family: 'Oswald', sans-serif; font-size: 1.1rem; font-weight: 900; flex-shrink: 0; }
        .pos-badge { font-size: 0.7rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; background: rgba(239,65,53,0.2); color: var(--fr-secondary); border: 1px solid rgba(239,65,53,0.3); }
        .player-name { flex: 1; font-weight: 700; color: #fff; font-size: 0.95rem; }
        .player-stats { display: flex; gap: 12px; font-size: 0.8rem; color: var(--muted); }
        .stat-val { color: #fff; font-weight: 700; }
        .action-btns { display: flex; gap: 6px; flex-shrink: 0; }
        .btn-xs { font-size: 0.7rem; padding: 4px 10px; border-radius: 4px; border: none; cursor: pointer; font-weight: 700; transition: 0.2s; }
        .btn-ilk11 { background: rgba(0,63,138,0.3); color: #60a5fa; border: 1px solid rgba(96,165,250,0.3); }
        .btn-ilk11:hover { background: var(--fr-primary); color: #fff; }
        .btn-yedek { background: rgba(251,191,36,0.2); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
        .btn-yedek:hover { background: #fbbf24; color: #000; }
        .btn-disi { background: rgba(107,114,128,0.2); color: #9ca3af; border: 1px solid rgba(107,114,128,0.3); }
        .btn-disi:hover { background: #6b7280; color: #fff; }
        .section-title { font-family: 'Oswald', sans-serif; font-size: 1.2rem; color: var(--fr-gold); text-transform: uppercase; border-bottom: 2px solid var(--border); padding-bottom: 8px; margin: 24px 0 12px; }
        .tactic-select { background: rgba(255,255,255,0.07); color: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px; width: 100%; }
        .tactic-select option { background: #1a1a1a; color: #fff; }
        .stat-card { background: rgba(0,63,138,0.1); border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; text-align: center; }
        .stat-card-val { font-family: 'Oswald', sans-serif; font-size: 2rem; font-weight: 900; color: var(--fr-gold); }
        .stat-card-lbl { font-size: 0.7rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
    </style>
</head>
<body>
<nav class="pro-navbar">
    <a href="ligue1.php" class="nav-brand font-oswald"><i class="fa-solid fa-flag"></i> LIGUE 1</a>
    <div class="d-none d-lg-flex gap-2">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="ligue1.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Fikstür</a>
        <a href="l1_kadro.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-users"></i> Kadro</a>
        <a href="l1_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
        <a href="l1_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-bar"></i> Puan</a>
        <a href="l1_basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Basın</a>
        <a href="l1_tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
    </div>
    <a href="ligue1.php" class="btn-ap"><i class="fa-solid fa-arrow-left"></i> Fikstüre Dön</a>
</nav>

<div class="container py-4" style="max-width:1200px;">

    <?php if($mesaj): ?>
    <div class="alert alert-<?=$mesaj_tipi?> alert-dismissible fade show" role="alert">
        <?=$mesaj?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-val"><?=$takim_kalitesi?></div><div class="stat-card-lbl">Takım OVR</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-val"><?=$ilk11_count?>/11</div><div class="stat-card-lbl">İlk 11</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-val"><?=$takim['dizilis']??'4-3-3'?></div><div class="stat-card-lbl">Formasyon</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-card-val"><?=$takim['oyun_tarzi']??'Dengeli'?></div><div class="stat-card-lbl">Oyun Tarzı</div></div></div>
    </div>

    <!-- TAKTİK PANELİ -->
    <div class="panel-card">
        <div class="panel-header"><h5><i class="fa-solid fa-chess me-2"></i>Taktik Ayarları</h5></div>
        <div class="p-3">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label text-white fw-bold" style="font-size:0.8rem;">Formasyon</label>
                        <select name="dizilis" class="tactic-select">
                            <?php foreach(['4-3-3','4-4-2','4-2-3-1','3-5-2','5-3-2','4-1-4-1','3-4-3'] as $f): ?>
                            <option value="<?=$f?>" <?=($takim['dizilis']??'')==$f?'selected':''?>><?=$f?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-white fw-bold" style="font-size:0.8rem;">Oyun Tarzı</label>
                        <select name="oyun_tarzi" class="tactic-select">
                            <?php foreach(['Dengeli','Hücum','Savunma','Kontrol','Yüksek Pres','Tiki-Taka','Uzun Top'] as $t): ?>
                            <option value="<?=$t?>" <?=($takim['oyun_tarzi']??'')==$t?'selected':''?>><?=$t?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-white fw-bold" style="font-size:0.8rem;">Pres Şiddeti</label>
                        <select name="pres" class="tactic-select">
                            <?php foreach(['Düşük','Orta','Yüksek','Çılgın'] as $p): ?>
                            <option value="<?=$p?>" <?=($takim['pres']??'')==$p?'selected':''?>><?=$p?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-white fw-bold" style="font-size:0.8rem;">Tempo</label>
                        <select name="tempo" class="tactic-select">
                            <?php foreach(['Yavaş','Normal','Hızlı','Çok Hızlı'] as $tp): ?>
                            <option value="<?=$tp?>" <?=($takim['tempo']??'')==$tp?'selected':''?>><?=$tp?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="taktik_kaydet" class="btn-ap"><i class="fa-solid fa-floppy-disk me-2"></i>Taktikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- İLK 11 -->
    <div class="section-title"><i class="fa-solid fa-star me-2"></i>İlk 11 (<?=$ilk11_count?>/11)</div>
    <div class="panel-card">
        <?php if(empty($ilk_11)): ?>
        <div class="p-4 text-center" style="color:var(--muted);">Henüz ilk 11 belirlenmedi.</div>
        <?php else: ?>
        <?php foreach($ilk_11 as $o): ?>
        <div class="player-row">
            <div class="ovr-badge"><?=$o['ovr']?></div>
            <span class="pos-badge"><?=$o['mevki']?></span>
            <div class="player-name"><?=htmlspecialchars($o['isim'])?></div>
            <div class="player-stats">
                <span>FRM <span class="stat-val"><?=$o['form']?></span></span>
                <span>FİT <span class="stat-val"><?=$o['fitness']?></span></span>
                <span>YAŞ <span class="stat-val"><?=$o['yas']?></span></span>
                <?php if(!empty($o['moral'])): ?><span>MOR <span class="stat-val"><?=$o['moral']?></span></span><?php endif; ?>
            </div>
            <div class="action-btns">
                <a href="?islem=yedek_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-yedek">Yedeğe</a>
                <a href="?islem=kadro_disi_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-disi">Dışarı</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- YEDEKLER -->
    <div class="section-title"><i class="fa-solid fa-user-clock me-2"></i>Yedekler (<?=count($yedekler)?>)</div>
    <div class="panel-card">
        <?php if(empty($yedekler)): ?>
        <div class="p-4 text-center" style="color:var(--muted);">Yedek oyuncu yok.</div>
        <?php else: ?>
        <?php foreach($yedekler as $o): ?>
        <div class="player-row">
            <div class="ovr-badge" style="background:#5b3e00;"><?=$o['ovr']?></div>
            <span class="pos-badge"><?=$o['mevki']?></span>
            <div class="player-name"><?=htmlspecialchars($o['isim'])?></div>
            <div class="player-stats">
                <span>FRM <span class="stat-val"><?=$o['form']?></span></span>
                <span>FİT <span class="stat-val"><?=$o['fitness']?></span></span>
                <span>YAŞ <span class="stat-val"><?=$o['yas']?></span></span>
            </div>
            <div class="action-btns">
                <?php if($ilk11_count < 11): ?><a href="?islem=ilk11_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-ilk11">İlk 11'e</a><?php endif; ?>
                <a href="?islem=kadro_disi_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-disi">Dışarı</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- KADRO DIŞI -->
    <?php if(!empty($kadro_disi)): ?>
    <div class="section-title"><i class="fa-solid fa-user-xmark me-2"></i>Kadro Dışı (<?=count($kadro_disi)?>)</div>
    <div class="panel-card">
        <?php foreach($kadro_disi as $o): ?>
        <div class="player-row">
            <div class="ovr-badge" style="background:#333;"><?=$o['ovr']?></div>
            <span class="pos-badge" style="color:#6b7280;border-color:#6b7280;background:rgba(107,114,128,0.1);"><?=$o['mevki']?></span>
            <div class="player-name" style="color:#6b7280;"><?=htmlspecialchars($o['isim'])?></div>
            <div class="player-stats"><span>YAŞ <span class="stat-val"><?=$o['yas']?></span></span></div>
            <div class="action-btns">
                <?php if($ilk11_count < 11): ?><a href="?islem=ilk11_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-ilk11">İlk 11'e</a><?php endif; ?>
                <a href="?islem=yedek_yap&oyuncu_id=<?=$o['id']?>" class="btn-xs btn-yedek">Yedeğe</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
