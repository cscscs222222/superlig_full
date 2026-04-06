<?php
// ==============================================================================
// PREMIER LEAGUE - SEZON SONU KUTLAMASI VE YENİ SEZON GEÇİŞİ
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM pl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

// Şampiyonu ve sıralamayı belirle
$puan_durumu = $pdo->query("SELECT * FROM pl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
$sampiyon = $puan_durumu[0];

if(isset($_POST['yeni_sezona_gec'])) {
    
    // 1. ÖDÜL DAĞITIMI (İNGİLİZ YAYIN GELİRLERİ DEVASADIR)
    foreach($puan_durumu as $index => $t) {
        $sira = $index + 1;
        $odul = 0;
        if($sira == 1) $odul = 80000000;      // Şampiyon: 80 Milyon Euro
        elseif($sira == 2) $odul = 60000000;   // İkinci: 60 Milyon Euro
        elseif($sira <= 4) $odul = 45000000;   // CL Kotası: 45 Milyon Euro
        elseif($sira <= 10) $odul = 20000000;  // İlk 10: 20 Milyon Euro
        else $odul = 10000000;                 // Diğerleri: 10 Milyon Euro
        
        $pdo->exec("UPDATE pl_takimlar SET butce = butce + $odul WHERE id = {$t['id']}");
    }

    // 2. ŞAMPİYONLAR LİGİ'NE İHRAÇ (İLK 4 TAKIM İNGİLTERE'DEN GİDER)
    $cl_gidenler = array_slice($puan_durumu, 0, 4);
    foreach($cl_gidenler as $cl_takim) {
        $var_mi = $pdo->query("SELECT id FROM cl_takimlar WHERE takim_adi = '" . addslashes($cl_takim['takim_adi']) . "'")->fetchColumn();
        
        if(!$var_mi) {
            $stmt = $pdo->prepare("INSERT INTO cl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'Premier Lig')");
            $stmt->execute([$cl_takim['takim_adi'], $cl_takim['logo'], $cl_takim['hucum'], $cl_takim['savunma'], 50000000]);
            $yeni_cl_id = $pdo->lastInsertId();
            
            $oyuncular = $pdo->query("SELECT * FROM pl_oyuncular WHERE takim_id = {$cl_takim['id']}")->fetchAll(PDO::FETCH_ASSOC);
            foreach($oyuncular as $o) {
                $stmt_o = $pdo->prepare("INSERT INTO cl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Premier Lig', ?, ?)");
                $stmt_o->execute([$yeni_cl_id, $o['isim'], $o['mevki'], $o['ovr'], $o['yas'], $o['fiyat'], $o['ilk_11'], $o['yedek']]);
            }
        }
    }

    // 3. AVRUPA LİGİ (UEL) - 5. ve 6. Sıralar
    foreach ([$puan_durumu[4] ?? null, $puan_durumu[5] ?? null] as $uel_takim) {
        if (!$uel_takim) continue;
        try {
            $var_mi = $pdo->query("SELECT id FROM uel_takimlar WHERE takim_adi = '" . addslashes($uel_takim['takim_adi']) . "'")->fetchColumn();
            if (!$var_mi) {
                $stmt = $pdo->prepare("INSERT INTO uel_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'Premier Lig')");
                $stmt->execute([$uel_takim['takim_adi'], $uel_takim['logo'], $uel_takim['hucum'], $uel_takim['savunma'], 20000000]);
                $yeni_uel_id = $pdo->lastInsertId();
                $oyuncular_uel = $pdo->query("SELECT * FROM pl_oyuncular WHERE takim_id = {$uel_takim['id']}")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($oyuncular_uel as $o) {
                    $stmt_o = $pdo->prepare("INSERT INTO uel_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Premier Lig', ?, ?)");
                    $stmt_o->execute([$yeni_uel_id, $o['isim'], $o['mevki'], $o['ovr'] ?? 70, $o['yas'] ?? 25, $o['fiyat'] ?? 5000000, $o['ilk_11'] ?? 0, $o['yedek'] ?? 0]);
                }
            }
        } catch(Throwable $e) {}
    }

    // 4. KONFERANS LİGİ (UECL) - 7. ve 8. Sıralar
    foreach ([$puan_durumu[6] ?? null, $puan_durumu[7] ?? null] as $uecl_takim) {
        if (!$uecl_takim) continue;
        try {
            $var_mi = $pdo->query("SELECT id FROM uecl_takimlar WHERE takim_adi = '" . addslashes($uecl_takim['takim_adi']) . "'")->fetchColumn();
            if (!$var_mi) {
                $stmt = $pdo->prepare("INSERT INTO uecl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'Premier Lig')");
                $stmt->execute([$uecl_takim['takim_adi'], $uecl_takim['logo'], $uecl_takim['hucum'], $uecl_takim['savunma'], 12000000]);
                $yeni_uecl_id = $pdo->lastInsertId();
                $oyuncular_uecl = $pdo->query("SELECT * FROM pl_oyuncular WHERE takim_id = {$uecl_takim['id']}")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($oyuncular_uecl as $o) {
                    $stmt_o = $pdo->prepare("INSERT INTO uecl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Premier Lig', ?, ?)");
                    $stmt_o->execute([$yeni_uecl_id, $o['isim'], $o['mevki'], $o['ovr'] ?? 70, $o['yas'] ?? 25, $o['fiyat'] ?? 5000000, $o['ilk_11'] ?? 0, $o['yedek'] ?? 0]);
                }
            }
        } catch(Throwable $e) {}
    }

    // 5. OYUNCULARI YAŞLANDIR VE DURUMLARI SIFIRLA
    $pdo->exec("UPDATE pl_oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    $pdo->exec("DELETE FROM pl_oyuncular WHERE yas >= 38"); // 38 yaşında emeklilik

    // 6. İSTATİSTİKLERİ VE FİKSTÜRÜ SIFIRLA
    $pdo->exec("UPDATE pl_takimlar SET puan = 0, galibiyet = 0, beraberlik = 0, malubiyet = 0, atilan_gol = 0, yenilen_gol = 0");
    $pdo->exec("TRUNCATE TABLE pl_maclar"); 
    
    // 7. YILI VE HAFTAYI İLERLET
    $yeni_sezon_yili = $guncel_sezon + 1;
    $pdo->exec("UPDATE pl_ayar SET hafta = 1, sezon_yil = $yeni_sezon_yili");
    
    // YENİ SEZONA BAŞLA
    header("Location: premier_lig.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sezon Sonu | Premier League</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --pl-primary: #3d195b; 
            --pl-secondary: #e2f89c; 
            --bg-body: #1a0b2e;
        }
        body { 
            background-color: var(--bg-body); color: #fff; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 30%, rgba(226, 248, 156, 0.15) 0%, transparent 70%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .celebration-card {
            background: rgba(61, 25, 91, 0.9); border: 2px solid var(--pl-secondary);
            border-radius: 20px; padding: 50px; text-align: center; box-shadow: 0 0 50px rgba(226, 248, 156, 0.2);
            max-width: 800px; width: 100%; position: relative; overflow: hidden;
        }

        .champ-logo { width: 150px; height: 150px; object-fit: contain; filter: drop-shadow(0 0 20px var(--pl-secondary)); margin-bottom: 20px; animation: float 3s ease-in-out infinite;}
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        .btn-pl { background: var(--pl-secondary); color: var(--pl-primary); font-weight: 900; font-size: 1.2rem; padding: 15px 40px; border: none; border-radius: 50px; margin-top: 30px; transition: 0.3s; text-transform: uppercase;}
        .btn-pl:hover { box-shadow: 0 0 30px var(--pl-secondary); transform: scale(1.05); color: #000;}

        .season-summary { display: flex; justify-content: center; gap: 30px; margin-top: 30px; border-top: 1px solid rgba(226,248,156,0.3); padding-top: 30px;}
        .summary-item { background: rgba(0,0,0,0.5); padding: 15px 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);}
        .summary-title { font-size: 0.8rem; color: #a78bfa; font-weight: 700; letter-spacing: 1px;}
        .summary-val { font-family: 'Oswald'; font-size: 1.5rem; color: var(--pl-secondary);}
    </style>
</head>
<body>
    <div class="container">
        <div class="celebration-card mx-auto">
            <div class="text-muted fw-bold mb-2"><?= $guncel_sezon ?> - <?= $guncel_sezon + 1 ?> SEZONU ŞAMPİYONU</div>
            <img src="<?= $sampiyon['logo'] ?>" class="champ-logo">
            <h1 class="font-oswald" style="font-size: 4rem; color: var(--pl-secondary);"><?= $sampiyon['takim_adi'] ?></h1>
            <p class="fs-5 mt-3 text-light">İngiltere'nin en büyüğü <?= $sampiyon['puan'] ?> Puan ile zafere ulaştı!</p>
            
            <div class="season-summary">
                <div class="summary-item">
                    <div class="summary-title">CL BİLETİ ALANLAR</div>
                    <div class="summary-val mt-1" style="font-size: 1rem; color:#fff;">İlk 4 Takım Avrupa Sahnesinde</div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">ŞAMPİYONLUK YAYIN GELİRİ</div>
                    <div class="summary-val text-success">€80.0M</div>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="yeni_sezona_gec" class="btn-pl" onclick="return confirm('Tüm istatistikler sıfırlanacak ve yeni sezon fikstürü çekilecek. Emin misiniz?');">
                    <i class="fa-solid fa-forward"></i> Yeni Sezona Başla
                </button>
            </form>
        </div>
    </div>
</body>
</html>