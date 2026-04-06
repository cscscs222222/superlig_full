<?php
// ==============================================================================
// UEFA SÜPER KUPA - UCL ŞAMPİYONU VS UEL ŞAMPİYONU
// ==============================================================================
include '../db.php';

if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
}

// Gerekli tabloları oluştur
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tournaments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        turnuva VARCHAR(50),
        sezon_yil INT,
        sampiyon_id INT DEFAULT NULL,
        sampiyon_adi VARCHAR(100) DEFAULT NULL,
        sampiyon_lig VARCHAR(50) DEFAULT NULL,
        UNIQUE KEY uniq_turnuva_sezon (turnuva, sezon_yil)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS super_cup_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sezon_yil INT,
        ucl_takim VARCHAR(100),
        ucl_logo VARCHAR(255),
        ucl_skor INT DEFAULT NULL,
        uel_takim VARCHAR(100),
        uel_logo VARCHAR(255),
        uel_skor INT DEFAULT NULL,
        kazanan VARCHAR(100) DEFAULT NULL,
        tarih TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ucl_olaylar TEXT,
        uel_olaylar TEXT
    )");
} catch(Throwable $e) {}

// Global ayar
$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$guncel_sezon = (int)($ayar['sezon_yil'] ?? 2025);
$onceki_sezon = $guncel_sezon - 1;

// Önceki sezonun UCL ve UEL şampiyonlarını bul
$ucl_sampiyon = null;
$uel_sampiyon = null;

try {
    $ucl_sampiyon = $pdo->query("SELECT * FROM tournaments WHERE turnuva = 'UCL' AND sezon_yil = $onceki_sezon LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uel_sampiyon = $pdo->query("SELECT * FROM tournaments WHERE turnuva = 'UEL' AND sezon_yil = $onceki_sezon LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// Bu sezon oynanan Süper Kupa var mı?
$super_cup_mac = null;
try {
    $super_cup_mac = $pdo->query("SELECT * FROM super_cup_maclar WHERE sezon_yil = $guncel_sezon LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// Geçmiş Süper Kupa sonuçları
$gecmis_maclar = [];
try {
    $gecmis_maclar = $pdo->query("SELECT * FROM super_cup_maclar ORDER BY sezon_yil DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

// MAÇ SİMÜLASYONU
if (isset($_POST['oyna']) && !$super_cup_mac && $ucl_sampiyon && $uel_sampiyon) {
    // Basit güç hesabı
    $ucl_guc = 82; $uel_guc = 76;

    // UCL şampiyonu daha güçlü olma ihtimali
    $ucl_beklenen = max(0.1, 1.5 + (($ucl_guc - $uel_guc) * 0.05));
    $uel_beklenen = max(0.1, 1.2 - (($ucl_guc - $uel_guc) * 0.05));

    $ucl_skor = 0; $uel_skor = 0;
    for($i=0;$i<6;$i++) { if((rand(0,100)/100) < ($ucl_beklenen/4.5)) $ucl_skor++; }
    for($i=0;$i<6;$i++) { if((rand(0,100)/100) < ($uel_beklenen/4.5)) $uel_skor++; }

    // Uzatma: Berabere biten maçlarda rastgele belirle
    if ($ucl_skor == $uel_skor) {
        $karar = rand(0, 1);
        if ($karar == 0) $ucl_skor++; else $uel_skor++;
    }

    $kazanan = ($ucl_skor > $uel_skor) ? $ucl_sampiyon['sampiyon_adi'] : $uel_sampiyon['sampiyon_adi'];

    // Gol olayları üret
    $ucl_olaylar = [];
    for($i=0;$i<$ucl_skor;$i++) { $ucl_olaylar[] = ['dakika'=>rand(1,120),'tip'=>'gol','oyuncu'=>'UCL Golcüsü']; }
    $uel_olaylar = [];
    for($i=0;$i<$uel_skor;$i++) { $uel_olaylar[] = ['dakika'=>rand(1,120),'tip'=>'gol','oyuncu'=>'UEL Golcüsü']; }
    usort($ucl_olaylar, fn($a,$b) => $a['dakika'] <=> $b['dakika']);
    usort($uel_olaylar, fn($a,$b) => $a['dakika'] <=> $b['dakika']);

    $stmt = $pdo->prepare("INSERT INTO super_cup_maclar (sezon_yil, ucl_takim, ucl_logo, ucl_skor, uel_takim, uel_logo, uel_skor, kazanan, ucl_olaylar, uel_olaylar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $guncel_sezon,
        $ucl_sampiyon['sampiyon_adi'],
        '', // Logo kısıtlama - takım veritabanından çekilemez
        $ucl_skor,
        $uel_sampiyon['sampiyon_adi'],
        '',
        $uel_skor,
        $kazanan,
        json_encode($ucl_olaylar, JSON_UNESCAPED_UNICODE),
        json_encode($uel_olaylar, JSON_UNESCAPED_UNICODE)
    ]);

    // Kazananın ülkesine UEFA katsayısı ekle (bonus)
    $kazanan_lig = ($ucl_skor > $uel_skor) ? ($ucl_sampiyon['sampiyon_lig'] ?? null) : ($uel_sampiyon['sampiyon_lig'] ?? null);
    $ulke_map = ['Süper Lig'=>'Türkiye','Premier Lig'=>'İngiltere','La Liga'=>'İspanya','Bundesliga'=>'Almanya','Serie A'=>'İtalya'];
    $ulke = $ulke_map[$kazanan_lig ?? ''] ?? null;
    if ($ulke) {
        try {
            $pdo->exec("UPDATE uefa_coefficients SET toplam_puan = toplam_puan + 0.5 WHERE ulke_adi = '$ulke'");
        } catch(Throwable $e) {}
    }

    header("Location: super_cup.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UEFA Süper Kupa</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold:#d4af37; --silver:#c0c0c0; --bg:#050505; --panel:#0d0d0d; --border:rgba(212,175,55,0.2); }
        body { background:var(--bg); color:#fff; font-family:'Poppins',sans-serif; min-height:100vh;
            background-image:radial-gradient(circle at 50% 20%, rgba(212,175,55,0.12) 0%, transparent 65%); }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(5,5,5,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; padding:0 2rem; height:70px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:700; color:var(--gold); text-decoration:none; }
        .nav-links a { color:#ccc; font-size:0.9rem; padding:8px 14px; text-decoration:none; border-radius:8px; transition:0.2s; }
        .nav-links a:hover, .nav-links a.active { background:rgba(212,175,55,0.1); color:var(--gold); }
        .hero-banner { text-align:center; padding:60px 20px 40px; }
        .hero-title { font-size:3.5rem; font-weight:900; color:var(--gold); text-shadow:0 0 40px rgba(212,175,55,0.4); margin:0; }
        .hero-sub { color:#999; font-size:1rem; letter-spacing:3px; margin-top:8px; }
        .panel { background:var(--panel); border:1px solid var(--border); border-radius:20px; padding:32px; margin-bottom:24px; }
        .match-arena { display:flex; align-items:center; justify-content:center; gap:40px; padding:40px 20px; flex-wrap:wrap; }
        .team-block { text-align:center; min-width:180px; }
        .team-logo { width:100px; height:100px; object-fit:contain; filter:drop-shadow(0 4px 20px rgba(0,0,0,0.5)); margin-bottom:16px; }
        .team-name { font-family:'Oswald',sans-serif; font-size:1.6rem; font-weight:900; line-height:1.2; }
        .team-label { font-size:0.75rem; letter-spacing:2px; color:#666; margin-top:4px; }
        .vs-block { text-align:center; }
        .vs-text { font-family:'Oswald',sans-serif; font-size:2.5rem; font-weight:900; color:#333; }
        .skor-big { font-family:'Oswald',sans-serif; font-size:4rem; font-weight:900; color:#fff; line-height:1; }
        .skor-separator { color:#555; margin:0 16px; font-size:3rem; }
        .btn-gold { background:linear-gradient(135deg,var(--gold),#997a00); color:#000; font-weight:900; font-size:1.1rem; padding:14px 40px; border:none; border-radius:50px; cursor:pointer; text-transform:uppercase; letter-spacing:1px; transition:0.3s; text-decoration:none; display:inline-flex; align-items:center; gap:10px; }
        .btn-gold:hover { box-shadow:0 0 30px rgba(212,175,55,0.5); transform:scale(1.05); color:#000; }
        .kazanan-banner { background:linear-gradient(135deg,rgba(212,175,55,0.15),rgba(212,175,55,0.05)); border:2px solid var(--gold); border-radius:16px; padding:24px; text-align:center; margin-bottom:24px; }
        .gecmis-tbl { width:100%; border-collapse:collapse; }
        .gecmis-tbl th { font-size:0.75rem; color:#555; text-transform:uppercase; letter-spacing:1px; padding:8px 12px; border-bottom:1px solid var(--border); text-align:left; }
        .gecmis-tbl td { padding:12px; font-size:0.9rem; border-bottom:1px solid rgba(255,255,255,0.04); }
        .gecmis-tbl tr:hover td { background:rgba(212,175,55,0.04); }
        .gol-olay { display:flex; align-items:center; gap:8px; margin:6px 0; font-size:0.88rem; }
        .event-icon { color:var(--gold); }
    </style>
</head>
<body>

<nav class="pro-navbar">
    <a href="../index.php" class="nav-brand font-oswald"><i class="fa-solid fa-star me-2"></i>UEFA SÜPER KUPA</a>
    <div class="nav-links d-flex gap-2">
        <a href="super_cup.php" class="active"><i class="fa-solid fa-trophy"></i> Süper Kupa</a>
        <a href="../champions_league/cl.php"><i class="fa-solid fa-star"></i> Champions League</a>
        <a href="../uel/uel.php"><i class="fa-solid fa-fire"></i> Europa League</a>
        <a href="../takvim.php"><i class="fa-solid fa-clock"></i> Takvim</a>
        <a href="../index.php"><i class="fa-solid fa-house"></i> Ana Sayfa</a>
    </div>
</nav>

<div class="hero-banner">
    <i class="fa-solid fa-trophy" style="font-size:3rem;color:var(--gold);margin-bottom:16px;display:block;"></i>
    <h1 class="hero-title font-oswald">UEFA SÜPER KUPA</h1>
    <p class="hero-sub">ŞAMPİYONLAR LİGİ × AVRUPA LİGİ — <?= $guncel_sezon ?></p>
</div>

<div class="container py-2 pb-5">

    <?php if ($super_cup_mac): ?>
    <!-- OYNANMIŞ MAÇ SONUCU -->
    <div class="kazanan-banner">
        <i class="fa-solid fa-crown" style="color:var(--gold);font-size:2rem;margin-bottom:10px;"></i>
        <div style="font-size:0.8rem;color:#888;letter-spacing:2px;margin-bottom:8px;">SÜPER KUPA KAZANANI</div>
        <h2 class="font-oswald" style="color:var(--gold);font-size:2.5rem;"><?= htmlspecialchars($super_cup_mac['kazanan'] ?? '-') ?></h2>
    </div>

    <div class="panel">
        <h5 class="font-oswald text-center mb-4" style="color:var(--gold);letter-spacing:2px;">MAÇ SONUCU</h5>
        <div class="match-arena">
            <div class="team-block">
                <div class="team-name"><?= htmlspecialchars($super_cup_mac['ucl_takim']) ?></div>
                <div class="team-label">UCL ŞAMPİYONU</div>
            </div>
            <div class="vs-block">
                <div class="d-flex align-items-center">
                    <span class="skor-big" style="color:<?= $super_cup_mac['ucl_skor'] > $super_cup_mac['uel_skor'] ? 'var(--gold)' : '#fff' ?>;"><?= $super_cup_mac['ucl_skor'] ?></span>
                    <span class="skor-separator">–</span>
                    <span class="skor-big" style="color:<?= $super_cup_mac['uel_skor'] > $super_cup_mac['ucl_skor'] ? 'var(--gold)' : '#fff' ?>;"><?= $super_cup_mac['uel_skor'] ?></span>
                </div>
                <div style="font-size:0.8rem;color:#555;letter-spacing:1px;text-align:center;margin-top:8px;">FİNAL SKORU</div>
            </div>
            <div class="team-block">
                <div class="team-name"><?= htmlspecialchars($super_cup_mac['uel_takim']) ?></div>
                <div class="team-label">UEL ŞAMPİYONU</div>
            </div>
        </div>
    </div>

    <?php elseif (!$ucl_sampiyon || !$uel_sampiyon): ?>
    <!-- BEKLEYİŞ EKRANI -->
    <div class="panel text-center" style="padding:60px;">
        <i class="fa-solid fa-hourglass-half" style="font-size:4rem;color:#333;margin-bottom:24px;"></i>
        <h3 class="font-oswald" style="color:#555;">SÜPER KUPA İÇİN BEKLENİYOR</h3>
        <p class="text-muted mt-3">Süper Kupa her sezonun başında önceki sezonun<br>Şampiyonlar Ligi ve Avrupa Ligi şampiyonları arasında oynanır.</p>
        <div class="row mt-4 g-3 justify-content-center">
            <div class="col-md-4">
                <div style="background:rgba(0,229,255,0.08);border:1px solid rgba(0,229,255,0.2);border-radius:12px;padding:20px;">
                    <div style="font-size:0.75rem;color:#00e5ff;letter-spacing:1px;">UCL ŞAMPİYONU</div>
                    <div class="font-oswald mt-2" style="font-size:1.2rem;color:#aaa;">
                        <?= $ucl_sampiyon ? htmlspecialchars($ucl_sampiyon['sampiyon_adi']) : '— Bekleniyor —' ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div style="background:rgba(240,78,35,0.08);border:1px solid rgba(240,78,35,0.2);border-radius:12px;padding:20px;">
                    <div style="font-size:0.75rem;color:#f04e23;letter-spacing:1px;">UEL ŞAMPİYONU</div>
                    <div class="font-oswald mt-2" style="font-size:1.2rem;color:#aaa;">
                        <?= $uel_sampiyon ? htmlspecialchars($uel_sampiyon['sampiyon_adi']) : '— Bekleniyor —' ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-4 text-muted" style="font-size:0.85rem;">
            Süper Kupa oynayabilmek için önce Şampiyonlar Ligi ve Avrupa Ligi sezonlarını tamamlayın.
        </div>
    </div>

    <?php else: ?>
    <!-- OYNANACAK MAÇ -->
    <div class="panel" style="border-color:rgba(212,175,55,0.4);">
        <h5 class="font-oswald text-center mb-2" style="color:var(--gold);letter-spacing:3px;font-size:1.4rem;">SÜPER KUPA FİNALİ</h5>
        <p class="text-center text-muted mb-4" style="font-size:0.85rem;">UEFA Süper Kupa için her iki şampiyon hazır!</p>

        <div class="match-arena">
            <div class="team-block">
                <i class="fa-solid fa-trophy" style="font-size:3rem;color:#00e5ff;margin-bottom:12px;"></i>
                <div class="team-name" style="color:#00e5ff;"><?= htmlspecialchars($ucl_sampiyon['sampiyon_adi']) ?></div>
                <div class="team-label">ŞAMPİYONLAR LİGİ KAZANANI</div>
                <div class="text-muted mt-1" style="font-size:0.78rem;"><?= htmlspecialchars($ucl_sampiyon['sampiyon_lig'] ?? '') ?> | <?= $onceki_sezon ?>/<?= $onceki_sezon+1 ?></div>
            </div>
            <div class="vs-block text-center">
                <div class="vs-text">VS</div>
                <div style="color:#333;font-size:0.75rem;margin-top:4px;">UEFA SÜPER KUPA</div>
            </div>
            <div class="team-block">
                <i class="fa-solid fa-fire" style="font-size:3rem;color:#f04e23;margin-bottom:12px;"></i>
                <div class="team-name" style="color:#f04e23;"><?= htmlspecialchars($uel_sampiyon['sampiyon_adi']) ?></div>
                <div class="team-label">AVRUPA LİGİ KAZANANI</div>
                <div class="text-muted mt-1" style="font-size:0.78rem;"><?= htmlspecialchars($uel_sampiyon['sampiyon_lig'] ?? '') ?> | <?= $onceki_sezon ?>/<?= $onceki_sezon+1 ?></div>
            </div>
        </div>

        <div class="text-center mt-4">
            <form method="POST" style="display:inline;">
                <button type="submit" name="oyna" class="btn-gold" onclick="return confirm('UEFA Süper Kupa finalini oynamak istiyor musunuz?')">
                    <i class="fa-solid fa-play"></i> SÜPER KUPA'YI OYNA!
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- GEÇMİŞ SÜPER KUPA SONUÇLARI -->
    <?php if (!empty($gecmis_maclar)): ?>
    <div class="panel">
        <h5 class="font-oswald mb-3" style="color:#888;font-size:1rem;letter-spacing:2px;"><i class="fa-solid fa-clock-rotate-left me-2"></i>GEÇMİŞ SÜPER KUPA SONUÇLARI</h5>
        <table class="gecmis-tbl">
            <thead><tr>
                <th>Sezon</th><th>UCL Şampiyonu</th><th>Skor</th><th>UEL Şampiyonu</th><th>Kazanan</th>
            </tr></thead>
            <tbody>
                <?php foreach ($gecmis_maclar as $gm): ?>
                <tr>
                    <td><span style="color:#555;"><?= $gm['sezon_yil'] ?></span></td>
                    <td><?= htmlspecialchars($gm['ucl_takim']) ?></td>
                    <td class="text-center">
                        <span style="font-family:'Oswald',sans-serif;font-size:1.1rem;"><?= $gm['ucl_skor'] ?> – <?= $gm['uel_skor'] ?></span>
                    </td>
                    <td><?= htmlspecialchars($gm['uel_takim']) ?></td>
                    <td><strong style="color:var(--gold);">🏆 <?= htmlspecialchars($gm['kazanan'] ?? '-') ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
