<?php
// ==============================================================================
// CHAMPIONS LEAGUE - SEZON SONU KUTLAMASI VE KUPA TÖRENİ (CYAN & GOLD THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

// Şampiyonu ve sıralamayı belirle
$puan_durumu = $pdo->query("SELECT * FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
if(empty($puan_durumu)) { header("Location: cl.php"); exit; }

$sampiyon = $puan_durumu[0];
$ikinci = $puan_durumu[1] ?? null;

if(isset($_POST['yeni_sezona_gec'])) {
    
    // 1. UEFA ÖDÜL DAĞITIMI (DEVASA BÜTÇELER)
    foreach($puan_durumu as $index => $t) {
        $sira = $index + 1;
        $odul = 0;
        if($sira == 1) $odul = 100000000;      // Şampiyon: 100 Milyon Euro
        elseif($sira == 2) $odul = 75000000;   // Finalist: 75 Milyon Euro
        elseif($sira <= 8) $odul = 50000000;   // Çeyrek Finalistler: 50 Milyon Euro
        elseif($sira <= 16) $odul = 30000000;  // Son 16: 30 Milyon Euro
        else $odul = 15000000;                 // Gruplarda Kalanlar: 15 Milyon Euro
        
        $pdo->exec("UPDATE cl_takimlar SET butce = butce + $odul WHERE id = {$t['id']}");
        
        // Eğer bu takım Süper Lig veya Premier Lig'de de varsa, ana kasalarını da güncelle (Küresel ekonomi)
        if($t['lig'] == 'Süper Lig') {
            $pdo->exec("UPDATE takimlar SET butce = butce + $odul WHERE takim_adi = '" . addslashes($t['takim_adi']) . "'");
        } elseif($t['lig'] == 'Premier Lig') {
            $pdo->exec("UPDATE pl_takimlar SET butce = butce + $odul WHERE takim_adi = '" . addslashes($t['takim_adi']) . "'");
        }
    }

    // 2. OYUNCULARI YAŞLANDIR VE DURUMLARI SIFIRLA
    $pdo->exec("UPDATE cl_oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    $pdo->exec("DELETE FROM cl_oyuncular WHERE yas >= 38"); // 38 yaşında emeklilik

    // 3. İSTATİSTİKLERİ VE FİKSTÜRÜ SIFIRLA
    $pdo->exec("UPDATE cl_takimlar SET puan = 0, galibiyet = 0, beraberlik = 0, malubiyet = 0, atilan_gol = 0, yenilen_gol = 0");
    $pdo->exec("TRUNCATE TABLE cl_maclar"); 
    
    // 4. YILI VE HAFTAYI İLERLET
    $yeni_sezon_yili = $guncel_sezon + 1;
    $pdo->exec("UPDATE cl_ayar SET hafta = 1, sezon_yil = $yeni_sezon_yili");
    
    // YENİ SEZONA BAŞLA
    header("Location: cl.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avrupa'nın En Büyüğü | CL Finali</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --cl-primary: #0a1c52; 
            --cl-secondary: #002878; 
            --cl-accent: #00e5ff; 
            --gold: #d4af37;
            --bg-body: #050b14;
        }
        body { 
            background-color: var(--bg-body); color: #fff; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 30%, rgba(0, 229, 255, 0.15) 0%, transparent 70%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .celebration-card {
            background: linear-gradient(135deg, rgba(10, 28, 82, 0.9), rgba(0, 0, 0, 0.9)); 
            border: 2px solid var(--cl-accent);
            border-radius: 20px; padding: 50px; text-align: center; box-shadow: 0 0 50px rgba(0, 229, 255, 0.3);
            max-width: 800px; width: 100%; position: relative; overflow: hidden;
        }

        .champ-logo { width: 180px; height: 180px; object-fit: contain; filter: drop-shadow(0 0 25px var(--gold)); margin-bottom: 20px; animation: float 3s ease-in-out infinite;}
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-15px); } 100% { transform: translateY(0px); } }

        .btn-cl { background: linear-gradient(45deg, var(--cl-secondary), var(--cl-accent)); color: #000; font-weight: 900; font-size: 1.2rem; padding: 15px 40px; border: none; border-radius: 50px; margin-top: 30px; transition: 0.3s; text-transform: uppercase;}
        .btn-cl:hover { box-shadow: 0 0 30px var(--cl-accent); transform: scale(1.05); color: #fff;}

        .season-summary { display: flex; justify-content: center; gap: 30px; margin-top: 30px; border-top: 1px solid rgba(0,229,255,0.3); padding-top: 30px;}
        .summary-item { background: rgba(0,0,0,0.5); padding: 15px 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);}
        .summary-title { font-size: 0.8rem; color: var(--cl-accent); font-weight: 700; letter-spacing: 1px;}
        .summary-val { font-family: 'Oswald'; font-size: 1.5rem; color: var(--gold);}
    </style>
</head>
<body>
    <div class="container">
        <div class="celebration-card mx-auto">
            <i class="fa-solid fa-trophy mb-3" style="font-size: 4rem; color: var(--gold);"></i>
            <div class="text-muted fw-bold mb-2" style="letter-spacing: 2px;"><?= $guncel_sezon ?> - <?= $guncel_sezon + 1 ?> AVRUPA ŞAMPİYONU</div>
            
            <img src="<?= $sampiyon['logo'] ?>" class="champ-logo">
            
            <h1 class="font-oswald" style="font-size: 4.5rem; color: var(--gold); line-height: 1; text-shadow: 0 5px 15px rgba(0,0,0,0.8);"><?= $sampiyon['takim_adi'] ?></h1>
            <p class="fs-5 mt-3 text-light">Kıtayı fethederek Avrupa'nın en büyüğü oldular!</p>
            
            <div class="season-summary">
                <div class="summary-item">
                    <div class="summary-title">UEFA ŞAMPİYONLUK ÖDÜLÜ</div>
                    <div class="summary-val mt-1">€100.0M</div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">FİNALİST (İKİNCİ)</div>
                    <div class="summary-val mt-1 text-white" style="font-size: 1.2rem;"><?= $ikinci ? $ikinci['takim_adi'] : 'Bilinmiyor' ?></div>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="yeni_sezona_gec" class="btn-cl" onclick="return confirm('Tüm istatistikler sıfırlanacak ve Şampiyonlar Ligi yeni sezona başlayacak. Emin misiniz?');">
                    <i class="fa-solid fa-earth-europe"></i> Avrupa'da Yeni Sezona Başla
                </button>
            </form>
        </div>
    </div>
</body>
</html>