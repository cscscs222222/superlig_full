<?php
// ==============================================================================
// TAÇA DE PORTUGAL - PORTEKİZ KUPA TURNUVASI
// Türkiye Kupası / FA Cup / Coupe de France ile aynı mantık, Liga NOS takımları ile
// ==============================================================================
include '../db.php';

set_time_limit(120);

// TABLOLARI HAZIRLA
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tdp_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ev INT, dep INT,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL,
        tur VARCHAR(30) DEFAULT 'Tur 1',
        sezon_yil INT DEFAULT 2025,
        ev_olaylar TEXT DEFAULT NULL,
        dep_olaylar TEXT DEFAULT NULL,
        ev_kartlar TEXT DEFAULT NULL,
        dep_kartlar TEXT DEFAULT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tdp_ayar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mevcut_tur VARCHAR(30) DEFAULT 'Tur 1',
        sezon_yil INT DEFAULT 2025,
        sampiyon VARCHAR(255) DEFAULT NULL
    )");
    $ayar_sayisi = $pdo->query("SELECT COUNT(*) FROM tdp_ayar")->fetchColumn();
    if($ayar_sayisi == 0) $pdo->exec("INSERT INTO tdp_ayar (mevcut_tur, sezon_yil) VALUES ('Tur 1', 2025)");
} catch(Throwable $e) {}

$ayar = $pdo->query("SELECT * FROM tdp_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$mevcut_tur = $ayar['mevcut_tur'] ?? 'Tur 1';
$sezon_yil = $ayar['sezon_yil'] ?? 2025;
$sampiyon = $ayar['sampiyon'] ?? null;

$turler = ['Tur 1', 'Tur 2', 'Çeyrek Final', 'Yarı Final', 'Final'];

$pt_takimlar = [];
try { $pt_takimlar = $pdo->query("SELECT * FROM pt_takimlar ORDER BY puan DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) {}

$mesaj = "";
$mesaj_tipi = "";

// KUPAYA BAŞLA / YENIDEN BAŞLAT
if(isset($_POST['kupaya_basla'])) {
    try {
        $pdo->exec("DELETE FROM tdp_maclar WHERE sezon_yil = $sezon_yil");
        $pdo->exec("UPDATE tdp_ayar SET mevcut_tur='Tur 1', sampiyon=NULL WHERE id=1");
        $takimlar_ids = array_column($pt_takimlar, 'id');
        shuffle($takimlar_ids);
        if(count($takimlar_ids) % 2 != 0) array_pop($takimlar_ids);
        for($i=0; $i<count($takimlar_ids); $i+=2) {
            $pdo->exec("INSERT INTO tdp_maclar (ev, dep, tur, sezon_yil) VALUES ({$takimlar_ids[$i]}, {$takimlar_ids[$i+1]}, 'Tur 1', $sezon_yil)");
        }
        $mesaj = "Taça de Portugal başladı! Tur 1 kuraları çekildi."; $mesaj_tipi = "success";
        $mevcut_tur = 'Tur 1';
        header("Location: taca_de_portugal.php"); exit;
    } catch(Throwable $e) { $mesaj = "Hata: " . $e->getMessage(); $mesaj_tipi = "danger"; }
}

// TURU SİMÜLE ET
if(isset($_POST['turu_simule'])) {
    try {
        $mevcut_maclar = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.hucum as ev_h, t1.savunma as ev_s, t2.takim_adi as dep_ad, t2.hucum as dep_h, t2.savunma as dep_s, t1.logo as ev_logo, t2.logo as dep_logo FROM tdp_maclar m JOIN pt_takimlar t1 ON m.ev=t1.id JOIN pt_takimlar t2 ON m.dep=t2.id WHERE m.tur='$mevcut_tur' AND m.sezon_yil=$sezon_yil AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);

        $kazananlar = [];
        foreach($mevcut_maclar as $m) {
            $ev_g = rand(0, 3); $dep_g = rand(0, 3);
            if($ev_g == $dep_g) { $ev_g += rand(0,2); $dep_g += rand(0,1); }
            if($ev_g == $dep_g) $dep_g++; // Penaltı kazananı (dep)
            $pdo->prepare("UPDATE tdp_maclar SET ev_skor=?, dep_skor=? WHERE id=?")->execute([$ev_g, $dep_g, $m['id']]);
            $kazananlar[] = $ev_g > $dep_g ? $m['ev'] : $m['dep'];
        }

        $tur_idx = array_search($mevcut_tur, $turler);
        if($tur_idx !== false && $tur_idx < count($turler)-1) {
            $sonraki_tur = $turler[$tur_idx + 1];
            if($sonraki_tur == 'Final' && count($kazananlar) == 2) {
                $pdo->exec("INSERT INTO tdp_maclar (ev, dep, tur, sezon_yil) VALUES ({$kazananlar[0]}, {$kazananlar[1]}, 'Final', $sezon_yil)");
            } elseif($sonraki_tur != 'Final') {
                shuffle($kazananlar);
                if(count($kazananlar) % 2 != 0) array_pop($kazananlar);
                for($i=0; $i<count($kazananlar); $i+=2) {
                    $pdo->exec("INSERT INTO tdp_maclar (ev, dep, tur, sezon_yil) VALUES ({$kazananlar[$i]}, {$kazananlar[$i+1]}, '$sonraki_tur', $sezon_yil)");
                }
            }
            $pdo->exec("UPDATE tdp_ayar SET mevcut_tur='$sonraki_tur' WHERE id=1");
            $mesaj = "$mevcut_tur tamamlandı! Sıradaki: $sonraki_tur"; $mesaj_tipi = "success";
            $mevcut_tur = $sonraki_tur;
        } elseif($mevcut_tur == 'Final' && !empty($kazananlar)) {
            $sampiyon_takim = $pdo->query("SELECT takim_adi FROM pt_takimlar WHERE id={$kazananlar[0]}")->fetchColumn();
            $pdo->prepare("UPDATE tdp_ayar SET sampiyon=? WHERE id=1")->execute([$sampiyon_takim]);
            $pdo->exec("UPDATE pt_takimlar SET butce=butce+2500000 WHERE id={$kazananlar[0]}");
            $sampiyon = $sampiyon_takim;
            $mesaj = "TAÇA DE PORTUGAL ŞAMPIYONU: $sampiyon_takim! 🏆 (€2.5M Ödül)"; $mesaj_tipi = "success";
        }
    } catch(Throwable $e) { $mesaj = "Simülasyon hatası: " . $e->getMessage(); $mesaj_tipi = "danger"; }
    header("Location: taca_de_portugal.php"); exit;
}

// VERİ ÇEKİMİ
$tum_maclar = [];
try {
    foreach($turler as $tur) {
        $maclar = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM tdp_maclar m JOIN pt_takimlar t1 ON m.ev=t1.id JOIN pt_takimlar t2 ON m.dep=t2.id WHERE m.tur='$tur' AND m.sezon_yil=$sezon_yil ORDER BY m.id")->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($maclar)) $tum_maclar[$tur] = $maclar;
    }
} catch(Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taça de Portugal | Ultimate Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pt-primary:#006600; --pt-secondary:#cc0000; --pt-gold:#d4af37; --bg:#0d0d0d; --panel:#1a1a1a; --border:rgba(0,102,0,0.25); --text:#f9fafb; --muted:#94a3b8; }
        body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; background-image:radial-gradient(circle at 0% 0%,rgba(0,102,0,0.12) 0%,transparent 50%); }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(10,10,10,0.97); backdrop-filter:blur(24px); border-bottom:2px solid var(--pt-secondary); position:sticky; top:0; z-index:1000; padding:0 2rem; height:75px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:900; color:#fff; text-decoration:none; }
        .nav-brand i { color:var(--pt-secondary); }
        .nav-link-item { color:var(--muted); font-weight:600; font-size:0.95rem; padding:8px 16px; text-decoration:none; transition:0.2s; }
        .nav-link-item:hover { color:#fff; }
        .btn-ap { background:var(--pt-primary); color:#fff; font-weight:800; padding:8px 20px; border-radius:4px; text-decoration:none; border:none; transition:0.3s; cursor:pointer; }
        .btn-ap:hover { background:var(--pt-secondary); color:#fff; }
        .panel-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px; }
        .panel-header { padding:1rem 1.5rem; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.3); display:flex; align-items:center; justify-content:space-between; }
        .panel-header h5 { color:var(--pt-gold); margin:0; font-family:'Oswald',sans-serif; font-size:1rem; text-transform:uppercase; }
        .match-row { display:flex; align-items:center; padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.04); gap:12px; }
        .match-row:hover { background:rgba(0,102,0,0.06); }
        .team-side { flex:1; display:flex; align-items:center; gap:10px; }
        .team-side.right { flex-direction:row-reverse; text-align:right; }
        .team-name { font-weight:700; color:#fff; font-size:0.9rem; }
        .team-logo { width:28px; height:28px; object-fit:contain; }
        .score-cell { width:80px; text-align:center; flex-shrink:0; }
        .score-text { font-family:'Oswald',sans-serif; font-size:1.4rem; font-weight:900; color:var(--pt-gold); }
        .vs-text { color:var(--muted); font-size:0.85rem; font-weight:700; }
        .hero-cup { text-align:center; padding:40px 20px; }
        .hero-cup h1 { font-family:'Oswald',sans-serif; font-size:3rem; color:var(--pt-gold); }
        .champion-card { background:linear-gradient(135deg,rgba(0,102,0,0.4),rgba(204,0,0,0.2)); border:2px solid var(--pt-gold); border-radius:20px; padding:30px; text-align:center; max-width:400px; margin:0 auto 30px; }
        .btn-simulate { background:linear-gradient(135deg,var(--pt-primary),var(--pt-secondary)); color:#fff; border:none; border-radius:8px; padding:12px 24px; font-family:'Oswald',sans-serif; font-size:1rem; font-weight:800; cursor:pointer; transition:0.2s; }
        .btn-simulate:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,102,0,0.4); }
        .tur-badge { display:inline-block; background:rgba(0,102,0,0.3); border:1px solid rgba(0,102,0,0.5); padding:4px 12px; border-radius:20px; font-size:0.75rem; font-weight:700; color:#4ade80; text-transform:uppercase; }
    </style>
</head>
<body>
<nav class="pro-navbar">
    <a href="../liga_nos/liga_nos.php" class="nav-brand font-oswald"><i class="fa-solid fa-shield-halved"></i> TAÇA DE PORTUGAL</a>
    <div class="d-none d-lg-flex gap-2">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="../liga_nos/liga_nos.php" class="nav-link-item"><i class="fa-solid fa-star"></i> Liga NOS</a>
        <a href="../liga_nos/ln_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
        <a href="../liga_nos/ln_transfer.php" class="nav-link-item"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
    </div>
    <a href="../liga_nos/liga_nos.php" class="btn-ap"><i class="fa-solid fa-arrow-left"></i> Liga NOS</a>
</nav>

<div class="container py-4" style="max-width:900px;">

    <?php if($mesaj): ?>
    <div class="alert alert-<?=$mesaj_tipi?> alert-dismissible fade show"><i class="fa-solid fa-info-circle me-2"></i><?=$mesaj?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="hero-cup">
        <div style="font-size:4rem;">🏆</div>
        <h1>TAÇA DE PORTUGAL <?=$sezon_yil?></h1>
        <p style="color:var(--muted);">Portekiz'in en prestijli ulusal kupası — Tüm Liga NOS takımları katılır</p>
    </div>

    <?php if($sampiyon): ?>
    <div class="champion-card">
        <div style="font-size:3rem;margin-bottom:10px;">🏆</div>
        <div style="font-size:0.8rem;color:var(--muted);text-transform:uppercase;letter-spacing:2px;">Taça de Portugal Şampiyonu</div>
        <div class="font-oswald" style="font-size:2rem;color:#fff;margin-top:6px;"><?=htmlspecialchars($sampiyon)?></div>
    </div>
    <?php endif; ?>

    <div class="panel-card">
        <div class="panel-header"><h5><i class="fa-solid fa-gears me-2"></i>Kupa Yönetimi</h5></div>
        <div class="p-4 d-flex gap-3 flex-wrap">
            <?php if(empty($tum_maclar)): ?>
            <form method="POST">
                <button type="submit" name="kupaya_basla" class="btn-simulate">
                    <i class="fa-solid fa-play me-2"></i>Taça de Portugal'ı Başlat
                </button>
            </form>
            <?php else: ?>
            <form method="POST">
                <button type="submit" name="kupaya_basla" class="btn-simulate" style="background:rgba(107,114,128,0.5);" onclick="return confirm('Mevcut kupa sıfırlanacak. Onaylıyor musunuz?')">
                    <i class="fa-solid fa-rotate me-2"></i>Yeniden Başlat
                </button>
            </form>
            <?php if(!$sampiyon): ?>
            <form method="POST">
                <button type="submit" name="turu_simule" class="btn-simulate">
                    <i class="fa-solid fa-forward me-2"></i><?=$mevcut_tur?>u Simüle Et
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <div class="px-4 pb-3" style="font-size:0.85rem;color:var(--muted);">
            Mevcut Tur: <strong style="color:#fff;"><?=$mevcut_tur?></strong>
            <?php if(empty($pt_takimlar)): ?>
            <span class="ms-3" style="color:#f87171;"><i class="fa-solid fa-exclamation-triangle"></i> Liga NOS takımları bulunamadı. Önce Liga NOS'a gidin.</span>
            <?php endif; ?>
        </div>
    </div>

    <?php foreach($tum_maclar as $tur => $maclar): ?>
    <div class="panel-card">
        <div class="panel-header">
            <h5><i class="fa-solid fa-calendar me-2"></i><?=$tur?></h5>
            <span class="tur-badge"><?=count($maclar)?> Maç</span>
        </div>
        <?php foreach($maclar as $m): ?>
        <div class="match-row">
            <div class="team-side">
                <img src="<?=$m['ev_logo']?>" class="team-logo" onerror="this.style.display='none'">
                <div class="team-name"><?=htmlspecialchars($m['ev_ad'])?></div>
            </div>
            <div class="score-cell">
                <?php if($m['ev_skor'] !== null): ?>
                <div class="score-text"><?=$m['ev_skor']?> - <?=$m['dep_skor']?></div>
                <div style="font-size:0.7rem;color:var(--muted);">TAMAM</div>
                <?php else: ?>
                <div class="vs-text">VS</div>
                <?php endif; ?>
            </div>
            <div class="team-side right">
                <img src="<?=$m['dep_logo']?>" class="team-logo" onerror="this.style.display='none'">
                <div class="team-name"><?=htmlspecialchars($m['dep_ad'])?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if(empty($tum_maclar)): ?>
    <div class="panel-card">
        <div class="p-5 text-center" style="color:var(--muted);">
            <i class="fa-solid fa-trophy fa-3x mb-3" style="color:var(--pt-gold);"></i>
            <div class="font-oswald fs-3">Taça de Portugal Başlamadı</div>
            <div class="mt-2">Turnuvayı başlatmak için yukarıdaki butona tıklayın.</div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
