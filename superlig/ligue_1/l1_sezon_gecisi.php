<?php
// ==============================================================================
// LIGUE 1 - SEZON SONU VE ŞAMPİYON KUTLAMASI
// ==============================================================================
include '../db.php';

$ayar = [];
try { $ayar = $pdo->query("SELECT * FROM fr_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC); } catch(Throwable $e) {}
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

$puan_durumu = [];
try { $puan_durumu = $pdo->query("SELECT * FROM fr_takimlar ORDER BY puan DESC,(atilan_gol-yenilen_gol) DESC,atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC); } catch(Throwable $e) {}
$sampiyon = $puan_durumu[0] ?? ['takim_adi'=>'Bilinmiyor','logo'=>''];

if(isset($_POST['yeni_sezona_gec'])) {
    // 1. Geçen sezon şampiyonunu kaydet
    try { $pdo->prepare("UPDATE fr_ayar SET gecen_sezon_sampiyon=? WHERE id=1")->execute([$sampiyon['takim_adi']]); } catch(Throwable $e) {}

    // 2. Ödüller
    foreach($puan_durumu as $idx => $t) {
        $sira=$idx+1; $odul=0;
        if($sira==1) $odul=70000000;
        elseif($sira==2) $odul=50000000;
        elseif($sira<=4) $odul=38000000;
        elseif($sira<=10) $odul=18000000;
        else $odul=9000000;
        try { $pdo->exec("UPDATE fr_takimlar SET butce=butce+$odul WHERE id={$t['id']}"); } catch(Throwable $e) {}
    }

    // 3. CL (İlk 4)
    foreach(array_slice($puan_durumu,0,4) as $ct) {
        try {
            $var_mi=$pdo->query("SELECT id FROM cl_takimlar WHERE takim_adi=".$pdo->quote($ct['takim_adi']))->fetchColumn();
            if(!$var_mi) {
                $pdo->prepare("INSERT INTO cl_takimlar (takim_adi,logo,hucum,savunma,butce,lig) VALUES (?,?,?,?,?,'Ligue 1')")->execute([$ct['takim_adi'],$ct['logo'],$ct['hucum'],$ct['savunma'],50000000]);
                $ncl=$pdo->lastInsertId();
                $oyuncular=$pdo->query("SELECT * FROM fr_oyuncular WHERE takim_id={$ct['id']}")->fetchAll(PDO::FETCH_ASSOC);
                foreach($oyuncular as $o) $pdo->prepare("INSERT INTO cl_oyuncular (takim_id,isim,mevki,ovr,yas,fiyat,lig,ilk_11,yedek) VALUES (?,?,?,?,?,?,'Ligue 1',?,?)")->execute([$ncl,$o['isim'],$o['mevki'],$o['ovr'],$o['yas'],$o['fiyat'],$o['ilk_11'],$o['yedek']]);
            }
        } catch(Throwable $e) {}
    }

    // 4. UEL (5-6)
    foreach(array_slice($puan_durumu,4,2) as $ut) {
        try {
            $var_mi=$pdo->query("SELECT id FROM uel_takimlar WHERE takim_adi=".$pdo->quote($ut['takim_adi']))->fetchColumn();
            if(!$var_mi) $pdo->prepare("INSERT INTO uel_takimlar (takim_adi,logo,hucum,savunma,butce,lig) VALUES (?,?,?,?,?,'Ligue 1')")->execute([$ut['takim_adi'],$ut['logo'],$ut['hucum'],$ut['savunma'],18000000]);
        } catch(Throwable $e) {}
    }

    // 5. UECL - Conference League (7-8)
    foreach(array_slice($puan_durumu,6,2) as $ct) {
        try {
            $var_mi=$pdo->query("SELECT id FROM uecl_takimlar WHERE takim_adi=".$pdo->quote($ct['takim_adi']))->fetchColumn();
            if(!$var_mi) $pdo->prepare("INSERT INTO uecl_takimlar (takim_adi,logo,hucum,savunma,butce,lig) VALUES (?,?,?,?,?,'Ligue 1')")->execute([$ct['takim_adi'],$ct['logo'],$ct['hucum'],$ct['savunma'],8000000]);
        } catch(Throwable $e) {}
    }

    // 5. Sezon sıfırla
    $yeni_sezon=$guncel_sezon+1;
    try {
        $pdo->exec("UPDATE fr_takimlar SET puan=0,galibiyet=0,beraberlik=0,malubiyet=0,atilan_gol=0,yenilen_gol=0");
        $pdo->exec("DELETE FROM fr_maclar WHERE sezon_yil=$guncel_sezon");
        $pdo->exec("UPDATE fr_ayar SET hafta=1,sezon_yil=$yeni_sezon");
    } catch(Throwable $e) {}
    header("Location: ligue1.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Ligue 1 Sezon Sonu | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700;900&family=Inter:wght@400;600&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background:#0d0d0d; color:#fff; font-family:'Inter',sans-serif; min-height:100vh; }
.font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
.hero { text-align:center; padding:60px 20px 40px; }
.hero h1 { font-family:'Oswald',sans-serif; font-size:3.5rem; font-weight:900; color:#d4af37; }
.hero p { color:#94a3b8; font-size:1.1rem; }
.champion-card { background:linear-gradient(135deg,rgba(0,63,138,0.4),rgba(239,65,53,0.2)); border:2px solid #d4af37; border-radius:20px; padding:40px; text-align:center; max-width:500px; margin:0 auto 40px; }
.champion-card img { width:80px; height:80px; object-fit:contain; margin-bottom:16px; }
.champion-card .label { font-size:0.8rem; color:#94a3b8; text-transform:uppercase; letter-spacing:2px; }
.champion-card .name { font-family:'Oswald',sans-serif; font-size:2.5rem; font-weight:900; color:#fff; }
.sira-table { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:14px; overflow:hidden; }
.sira-row { display:flex; align-items:center; gap:12px; padding:12px 20px; border-bottom:1px solid rgba(255,255,255,0.05); color:#fff; }
.sira-row:last-child { border-bottom:none; }
.sira-num { font-family:'Oswald',sans-serif; font-size:1.1rem; font-weight:700; color:#94a3b8; width:28px; text-align:center; }
.sira-img { width:28px; height:28px; object-fit:contain; }
.sira-name { flex:1; font-weight:600; }
.sira-puan { font-family:'Oswald',sans-serif; font-weight:900; color:#d4af37; }
.btn-yeni { display:inline-flex; align-items:center; gap:10px; background:linear-gradient(135deg,#003f8a,#ef4135); color:#fff; border:none; border-radius:12px; padding:16px 40px; font-family:'Oswald',sans-serif; font-size:1.2rem; font-weight:800; text-transform:uppercase; cursor:pointer; transition:all .2s; }
.btn-yeni:hover { transform:translateY(-3px); box-shadow:0 8px 30px rgba(0,63,138,0.5); }
</style>
</head>
<body>
<div class="container py-5" style="max-width:700px;">
    <a href="../index.php" style="color:#94a3b8;text-decoration:none;"><i class="fa-solid fa-arrow-left me-2"></i>Ana Menü</a>

    <div class="hero">
        <div style="font-size:4rem;margin-bottom:12px;">🏆</div>
        <h1 class="font-oswald"><?=$guncel_sezon?> Sezonu Sona Erdi!</h1>
        <p>Ligue 1'de şampiyonluk yarışı tamamlandı. İşte final sıralaması:</p>
    </div>

    <div class="champion-card">
        <img src="<?=htmlspecialchars($sampiyon['logo']??'')?>" onerror="this.style.display='none'">
        <div class="label">🏆 <?=$guncel_sezon?> Ligue 1 Şampiyonu</div>
        <div class="name"><?=htmlspecialchars($sampiyon['takim_adi']??'Bilinmiyor')?></div>
        <div style="color:#94a3b8;margin-top:8px;"><?=$sampiyon['puan']??0?> puan</div>
    </div>

    <div class="sira-table mb-5">
    <?php foreach(array_slice($puan_durumu,0,10) as $idx=>$t): $s=$idx+1;
        $badge=''; if($s<=4) $badge='<span style="font-size:0.7rem;background:rgba(0,63,138,0.3);color:#60a5fa;border:1px solid rgba(96,165,250,0.4);border-radius:20px;padding:2px 8px;margin-left:6px;">UCL</span>';
        elseif($s<=6) $badge='<span style="font-size:0.7rem;background:rgba(239,65,53,0.2);color:#f87171;border:1px solid rgba(248,113,113,0.4);border-radius:20px;padding:2px 8px;margin-left:6px;">UEL</span>';
        elseif($s<=8) $badge='<span style="font-size:0.7rem;background:rgba(46,204,113,0.2);color:#4ade80;border:1px solid rgba(74,222,128,0.4);border-radius:20px;padding:2px 8px;margin-left:6px;">UECL</span>';
    ?>
    <div class="sira-row">
        <div class="sira-num"><?=$s?></div>
        <img src="<?=htmlspecialchars($t['logo']??'')?>" class="sira-img" onerror="this.style.display='none'">
        <div class="sira-name"><?=htmlspecialchars($t['takim_adi'])?> <?=$badge?></div>
        <div class="sira-puan"><?=$t['puan']?> P</div>
    </div>
    <?php endforeach; ?>
    </div>

    <div class="text-center">
        <form method="POST">
            <button type="submit" name="yeni_sezona_gec" class="btn-yeni" onclick="return confirm('Yeni sezona geçmek istediğinize emin misiniz?');">
                <i class="fa-solid fa-forward-fast"></i> <?=$guncel_sezon+1?> Sezonuna Geç
            </button>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
