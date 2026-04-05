<?php
// ==============================================================================
// LA LIGA - SEZON SONU KUTLAMASI VE YENİ SEZON GEÇİŞİ (RED & GOLD SPANISH THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM es_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

// Şampiyonu ve sıralamayı belirle
$puan_durumu = $pdo->query("SELECT * FROM es_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
$sampiyon = $puan_durumu[0];

if(isset($_POST['yeni_sezona_gec'])) {
    
    // 1. ÖDÜL DAĞITIMI (LA LİGA YAYIN GELİRLERİ)
    foreach($puan_durumu as $index => $t) {
        $sira = $index + 1;
        $odul = 0;
        if($sira == 1) $odul = 60000000;      // Şampiyon: 60 Milyon Euro
        elseif($sira == 2) $odul = 45000000;   // İkinci: 45 Milyon Euro
        elseif($sira <= 4) $odul = 35000000;   // CL Kotası: 35 Milyon Euro
        elseif($sira <= 10) $odul = 15000000;  // İlk 10: 15 Milyon Euro
        else $odul = 8000000;                  // Diğerleri: 8 Milyon Euro
        
        $pdo->exec("UPDATE es_takimlar SET butce = butce + $odul WHERE id = {$t['id']}");
    }

    // 2. ŞAMPİYONLAR LİGİ'NE İHRAÇ (İLK 4 TAKIM İSPANYA'DAN GİDER)
    $cl_gidenler = array_slice($puan_durumu, 0, 4);
    foreach($cl_gidenler as $cl_takim) {
        $var_mi = false;
        try {
            $stmt_check = $pdo->prepare("SELECT id FROM cl_takimlar WHERE takim_adi = ?");
            $stmt_check->execute([$cl_takim['takim_adi']]);
            $var_mi = $stmt_check->fetchColumn();
        } catch(Throwable $e) {}
        
        if(!$var_mi) {
            $stmt = $pdo->prepare("INSERT INTO cl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'La Liga')");
            $stmt->execute([$cl_takim['takim_adi'], $cl_takim['logo'], $cl_takim['hucum'], $cl_takim['savunma'], 50000000]);
            $yeni_cl_id = $pdo->lastInsertId();
            
            $oyuncular = $pdo->query("SELECT * FROM es_oyuncular WHERE takim_id = {$cl_takim['id']}")->fetchAll(PDO::FETCH_ASSOC);
            foreach($oyuncular as $o) {
                $stmt_o = $pdo->prepare("INSERT INTO cl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'La Liga', ?, ?)");
                $stmt_o->execute([$yeni_cl_id, $o['isim'], $o['mevki'], $o['ovr'], $o['yas'], $o['fiyat'], $o['ilk_11'], $o['yedek']]);
            }
        }
    }

    // 3. OYUNCULARI YAŞLANDIR VE DURUMLARI SIFIRLA
    $pdo->exec("UPDATE es_oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    $pdo->exec("DELETE FROM es_oyuncular WHERE yas >= 38");

    // 4. İSTATİSTİKLERİ VE FİKSTÜRÜ SIFIRLA
    $pdo->exec("UPDATE es_takimlar SET puan = 0, galibiyet = 0, beraberlik = 0, malubiyet = 0, atilan_gol = 0, yenilen_gol = 0");
    $pdo->exec("TRUNCATE TABLE es_maclar"); 
    
    // 5. YILI VE HAFTAYI İLERLET
    $yeni_sezon_yili = $guncel_sezon + 1;
    $pdo->exec("UPDATE es_ayar SET hafta = 1, sezon_yil = $yeni_sezon_yili");
    
    header("Location: la_liga.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sezon Sonu | La Liga</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --es-primary: #c8102e;
            --es-secondary: #f5c518;
            --bg-body: #150003;
        }
        body { 
            background-color: var(--bg-body); color: #fff; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 30%, rgba(245, 197, 24, 0.15) 0%, transparent 70%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .celebration-card {
            background: rgba(42, 0, 8, 0.9); border: 2px solid var(--es-secondary);
            border-radius: 20px; padding: 50px; text-align: center; box-shadow: 0 0 50px rgba(245, 197, 24, 0.2);
            max-width: 800px; width: 100%; position: relative; overflow: hidden;
        }
        .celebration-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--es-primary), var(--es-secondary), var(--es-primary));
        }

        .champ-logo { width: 150px; height: 150px; object-fit: contain; filter: drop-shadow(0 0 20px var(--es-secondary)); margin-bottom: 20px; animation: float 3s ease-in-out infinite;}
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        .btn-es { background: var(--es-secondary); color: var(--es-primary); font-weight: 900; font-size: 1.2rem; padding: 15px 40px; border: none; border-radius: 50px; margin-top: 30px; transition: 0.3s; text-transform: uppercase;}
        .btn-es:hover { box-shadow: 0 0 30px var(--es-secondary); transform: scale(1.05); color: #000;}

        .season-summary { display: flex; justify-content: center; gap: 30px; margin-top: 30px; border-top: 1px solid rgba(245,197,24,0.3); padding-top: 30px; flex-wrap: wrap;}
        .summary-item { background: rgba(0,0,0,0.5); padding: 15px 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);}
        .summary-title { font-size: 0.8rem; color: var(--es-primary); font-weight: 700; letter-spacing: 1px;}
        .summary-val { font-family: 'Oswald'; font-size: 1.5rem; color: var(--es-secondary);}
    </style>
</head>
<body>
    <div class="container">
        <div class="celebration-card mx-auto">
            <div style="font-size: 3rem; margin-bottom: 10px;">🏆</div>
            <div class="text-muted fw-bold mb-2" style="color: var(--es-secondary) !important;"><?= $guncel_sezon ?> - <?= $guncel_sezon + 1 ?> LA LİGA ŞAMPİYONU</div>
            <img src="<?= htmlspecialchars($sampiyon['logo']) ?>" class="champ-logo" alt="Şampiyon">
            <h1 class="font-oswald" style="font-size: 4rem; color: var(--es-secondary);"><?= htmlspecialchars($sampiyon['takim_adi']) ?></h1>
            <p class="fs-5 mt-3 text-light">¡Campeones! <?= $sampiyon['puan'] ?> Puan ile La Liga zirvesine ulaştı!</p>
            
            <div class="season-summary">
                <div class="summary-item">
                    <div class="summary-title">CL BİLETİ ALANLAR</div>
                    <div class="summary-val mt-1" style="font-size: 1rem; color:#fff;">İlk 4 Takım Avrupa'da</div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">ŞAMPİYONLUK ÖDÜLÜ</div>
                    <div class="summary-val text-success">€60.0M</div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">YENİ SEZON</div>
                    <div class="summary-val"><?= $guncel_sezon + 1 ?> - <?= $guncel_sezon + 2 ?></div>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="yeni_sezona_gec" class="btn-es" onclick="return confirm('Tüm istatistikler sıfırlanacak ve yeni La Liga sezonu başlayacak. ¿Confirmar?');">
                    <i class="fa-solid fa-forward"></i> Nueva Temporada (Yeni Sezon)
                </button>
            </form>
        </div>
    </div>
</body>
</html>
