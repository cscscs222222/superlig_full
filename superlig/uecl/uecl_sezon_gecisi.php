<?php
// ==============================================================================
// UEFA CONFERENCE LEAGUE - SEZON SONU VE ŞAMPİYON KUTLAMASI
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM uecl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$guncel_sezon = (int)($ayar['sezon_yil'] ?? 2025);

// Final maçından şampiyonu belirle (hafta 15)
$final_mac = null;
try {
    $final_mac = $pdo->query(
        "SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t1.id as ev_id, t1.lig as ev_lig,
                t2.takim_adi as dep_ad, t2.logo as dep_logo, t2.id as dep_id, t2.lig as dep_lig
         FROM uecl_maclar m
         JOIN uecl_takimlar t1 ON m.ev = t1.id
         JOIN uecl_takimlar t2 ON m.dep = t2.id
         WHERE m.hafta = 15 AND m.ev_skor IS NOT NULL AND m.sezon_yil = $guncel_sezon
         LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
} catch(Throwable $e) {}

$sampiyon = null; $finalist = null; $final_skor = null;
$sampiyon_lig = null;

if ($final_mac) {
    if ((int)$final_mac['ev_skor'] >= (int)$final_mac['dep_skor']) {
        $sampiyon_id = (int)$final_mac['ev_id'];
        $finalist_id = (int)$final_mac['dep_id'];
        $sampiyon_adi = $final_mac['ev_ad'];
        $sampiyon_lig = $final_mac['ev_lig'];
    } else {
        $sampiyon_id = (int)$final_mac['dep_id'];
        $finalist_id = (int)$final_mac['ev_id'];
        $sampiyon_adi = $final_mac['dep_ad'];
        $sampiyon_lig = $final_mac['dep_lig'];
    }
    $sampiyon = $pdo->query("SELECT * FROM uecl_takimlar WHERE id = $sampiyon_id")->fetch(PDO::FETCH_ASSOC);
    $finalist = $pdo->query("SELECT * FROM uecl_takimlar WHERE id = $finalist_id")->fetch(PDO::FETCH_ASSOC);
    $final_skor = $final_mac['ev_skor'] . ' - ' . $final_mac['dep_skor'];
}

$puan_durumu = $pdo->query("SELECT * FROM uecl_takimlar ORDER BY puan DESC, (atilan_gol-yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
if (empty($puan_durumu) || !$sampiyon) { header("Location: uecl.php"); exit; }

if (isset($_POST['yeni_sezona_gec'])) {

    // 1. ÖDÜL DAĞITIMI
    foreach ($puan_durumu as $index => $t) {
        $sira = $index + 1;
        $odul = 0;
        if ($sira == 1) $odul = 40000000;
        elseif ($sira == 2) $odul = 25000000;
        elseif ($sira <= 8) $odul = 15000000;
        elseif ($sira <= 16) $odul = 10000000;
        else $odul = 5000000;
        $pdo->exec("UPDATE uecl_takimlar SET butce = butce + $odul WHERE id = {$t['id']}");
    }

    // 2. UEFA KATSAYİSİ: Şampiyona ekstra puan
    if ($sampiyon) {
        $sl = addslashes($sampiyon_lig ?? '');
        $ulke_map = ['Süper Lig'=>'Türkiye','Premier Lig'=>'İngiltere','La Liga'=>'İspanya','Bundesliga'=>'Almanya','Serie A'=>'İtalya'];
        $ulke = $ulke_map[$sl] ?? null;
        if ($ulke) {
            try {
                $pdo->exec("UPDATE uefa_coefficients SET toplam_puan = toplam_puan + 2.0, sezon_puan = sezon_puan + 2.0 WHERE ulke_adi = '$ulke'");
                $pdo->exec("UPDATE uefa_siralamasi SET toplam_puan = toplam_puan + 2000, guncel_sezon_puan = guncel_sezon_puan + 2000 WHERE ulke_adi = '$ulke'");
            } catch(Throwable $e) {}
        }
    }

    // 3. TOURNAMENTS TABLOSUNA ŞAMPİYON KAYDET (Super Cup için)
    if ($sampiyon) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tournaments (turnuva, sezon_yil, sampiyon_id, sampiyon_adi, sampiyon_lig) VALUES ('UECL', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE sampiyon_id=VALUES(sampiyon_id), sampiyon_adi=VALUES(sampiyon_adi), sampiyon_lig=VALUES(sampiyon_lig)");
            $stmt->execute([$guncel_sezon, $sampiyon_id, $sampiyon_adi, $sampiyon_lig]);
        } catch(Throwable $e) {}
    }

    // 4. OYUNCULARI YAŞLANDIR
    $pdo->exec("UPDATE uecl_oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    $pdo->exec("DELETE FROM uecl_oyuncular WHERE yas >= 38");

    // 5. İSTATİSTİKLERİ SIFIRLA
    $pdo->exec("UPDATE uecl_takimlar SET puan=0,galibiyet=0,beraberlik=0,malubiyet=0,atilan_gol=0,yenilen_gol=0");
    $pdo->exec("TRUNCATE TABLE uecl_maclar");

    // 6. TÜM TAKIMLARI KALDIR (bir sonraki sezonda yeniden belirlenir)
    $pdo->exec("TRUNCATE TABLE uecl_takimlar");
    $pdo->exec("TRUNCATE TABLE uecl_oyuncular");

    // 7. YILI İLERLET
    $yeni_sezon = $guncel_sezon + 1;
    $pdo->exec("UPDATE uecl_ayar SET hafta = 1, sezon_yil = $yeni_sezon, kullanici_takim_id = NULL");

    header("Location: ../index.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UECL Şampiyonu | Conference League</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;700;900&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --uecl-green:#2ecc71; --gold:#d4af37; --bg:#100501; }
        body { background:var(--bg); color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; display:flex; align-items:center; justify-content:center;
            background-image:radial-gradient(circle at 50% 30%, rgba(46,204,113,0.15) 0%, transparent 70%); }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .celebration-card { background:linear-gradient(135deg,rgba(8,24,10,0.95),rgba(0,0,0,0.95)); border:2px solid var(--uecl-green); border-radius:24px; padding:50px; text-align:center; max-width:750px; width:100%; box-shadow:0 0 60px rgba(46,204,113,0.25); }
        .champ-logo { width:160px; height:160px; object-fit:contain; filter:drop-shadow(0 0 25px var(--gold)); animation:float 3s ease-in-out infinite; margin-bottom:20px; }
        @keyframes float { 0%{transform:translateY(0);} 50%{transform:translateY(-12px);} 100%{transform:translateY(0);} }
        .btn-uecl { background:linear-gradient(135deg,var(--uecl-green),#1a8a3a); color:#fff; font-weight:900; font-size:1.1rem; padding:14px 36px; border:none; border-radius:50px; cursor:pointer; text-transform:uppercase; transition:0.3s; text-decoration:none; display:inline-flex; align-items:center; gap:10px; }
        .btn-uecl:hover { box-shadow:0 0 30px rgba(46,204,113,0.5); transform:scale(1.05); color:#fff; }
        .season-summary { display:flex; justify-content:center; gap:24px; margin-top:30px; border-top:1px solid rgba(46,204,113,0.3); padding-top:28px; flex-wrap:wrap; }
        .summary-item { background:rgba(0,0,0,0.5); padding:14px 22px; border-radius:10px; border:1px solid rgba(255,255,255,0.08); min-width:120px; }
        .summary-title { font-size:0.75rem; color:var(--uecl-green); font-weight:700; letter-spacing:1px; }
        .summary-val { font-family:'Oswald',sans-serif; font-size:1.4rem; color:var(--gold); }
    </style>
</head>
<body>
<div class="container">
    <div class="celebration-card mx-auto">
        <i class="fa-solid fa-fire mb-3" style="font-size:3.5rem;color:var(--uecl-green);"></i>
        <div class="text-muted fw-bold mb-2" style="letter-spacing:2px;font-size:0.85rem;"><?= $guncel_sezon ?>/<?= $guncel_sezon+1 ?> CONFERENCE LEAGUE ŞAMPIYONU</div>

        <img src="<?= htmlspecialchars($sampiyon['logo'] ?? '') ?>" class="champ-logo" onerror="this.style.display='none'">

        <h1 class="font-oswald" style="font-size:4rem;color:var(--gold);line-height:1;text-shadow:0 5px 15px rgba(0,0,0,0.8);">🏆 <?= htmlspecialchars($sampiyon['takim_adi']) ?></h1>
        <p class="fs-6 mt-2 text-light">Avrupa Ligi kupasını kaldırmaya hak kazandılar!</p>

        <?php if ($final_skor): ?>
        <div style="margin:16px auto;background:rgba(0,0,0,0.6);border:1px solid rgba(46,204,113,0.3);border-radius:12px;padding:14px 28px;display:inline-block;">
            <div style="color:#94a3b8;font-size:0.75rem;letter-spacing:2px;margin-bottom:6px;">FİNAL SKORU</div>
            <div style="font-family:'Oswald',sans-serif;font-size:2rem;font-weight:900;"><?= htmlspecialchars($final_skor) ?></div>
        </div>
        <?php endif; ?>

        <div class="season-summary">
            <div class="summary-item">
                <div class="summary-title">🏆 ŞAMPİYON</div>
                <div class="summary-val mt-1"><?= htmlspecialchars($sampiyon['takim_adi']) ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-title">🥈 FİNALİST</div>
                <div class="summary-val mt-1" style="font-size:1.1rem;color:#fff;"><?= $finalist ? htmlspecialchars($finalist['takim_adi']) : '-' ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-title">UEFA ŞAMPİYONLUK ÖDÜLÜ</div>
                <div class="summary-val mt-1">€40.0M</div>
            </div>
            <div class="summary-item">
                <div class="summary-title">SUPER CUP</div>
                <div class="summary-val mt-1" style="font-size:0.9rem;color:#94a3b8;">Sezon Başı</div>
            </div>
        </div>

        <form method="POST" class="mt-4">
            <button type="submit" name="yeni_sezona_gec" class="btn-uecl" onclick="return confirm('Tüm istatistikler sıfırlanacak. Sezon sona erecek. Emin misiniz?');">
                <i class="fa-solid fa-forward-step"></i> Yeni Sezonu Başlat
            </button>
        </form>
    </div>
</div>
</body>
</html>
