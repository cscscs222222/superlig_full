<?php
// ==============================================================================
// CHAMPIONS LEAGUE - SEZON SONU KUTLAMASI VE KUPA TÖRENİ (CYAN & GOLD THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM cl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

// Şampiyonu ve finalistı SADECE final maçından (Hafta 17) belirle
$sezon_yili = (int)($ayar['sezon_yil'] ?? 2025);
$final_mac = $pdo->query(
    "SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t1.id as ev_id,
            t2.takim_adi as dep_ad, t2.logo as dep_logo, t2.id as dep_id
     FROM cl_maclar m
     JOIN cl_takimlar t1 ON m.ev = t1.id
     JOIN cl_takimlar t2 ON m.dep = t2.id
     WHERE m.hafta = 17 AND m.ev_skor IS NOT NULL AND m.sezon_yil = $sezon_yili
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$sampiyon = null;
$finalist = null;
$final_skor = null;

if ($final_mac) {
    if ((int)$final_mac['ev_skor'] > (int)$final_mac['dep_skor']) {
        $sampiyon_id = (int)$final_mac['ev_id'];
        $finalist_id = (int)$final_mac['dep_id'];
    } elseif ((int)$final_mac['dep_skor'] > (int)$final_mac['ev_skor']) {
        $sampiyon_id = (int)$final_mac['dep_id'];
        $finalist_id = (int)$final_mac['ev_id'];
    } else {
        // Berabere biten final: mevcut sistemde ev sahibi kazanır (simülasyon limiti)
        $sampiyon_id = (int)$final_mac['ev_id'];
        $finalist_id = (int)$final_mac['dep_id'];
    }
    $stmt = $pdo->prepare("SELECT * FROM cl_takimlar WHERE id = ?");
    $stmt->execute([$sampiyon_id]);
    $sampiyon = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->execute([$finalist_id]);
    $finalist = $stmt->fetch(PDO::FETCH_ASSOC);
    $final_skor = $final_mac['ev_skor'] . ' - ' . $final_mac['dep_skor'];
}

// Tüm takım sıralaması (ödül hesabı için hâlâ gerekli)
$puan_durumu = $pdo->query("SELECT * FROM cl_takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
if (empty($puan_durumu) || !$sampiyon) { header("Location: cl.php"); exit; }

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

    // 2. TOURNAMENTS TABLOSUNA UCL ŞAMPİYONUNU KAYDET (Super Cup için)
    if ($sampiyon) {
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
            $stmt = $pdo->prepare("INSERT INTO tournaments (turnuva, sezon_yil, sampiyon_id, sampiyon_adi, sampiyon_lig) VALUES ('UCL', ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE sampiyon_id=VALUES(sampiyon_id), sampiyon_adi=VALUES(sampiyon_adi), sampiyon_lig=VALUES(sampiyon_lig)");
            $stmt->execute([$guncel_sezon, $sampiyon['id'], $sampiyon['takim_adi'], $sampiyon['lig'] ?? 'Avrupa']);
        } catch(Throwable $e) {}
    }

    // 3. UCL ŞAMPİYONUNUN ÜLKESİNE EKSTRA UEFA KATSAYISI
    if ($sampiyon && !empty($sampiyon['lig'])) {
        $ulke_map = ['Süper Lig'=>'Türkiye','Premier Lig'=>'İngiltere','La Liga'=>'İspanya','Bundesliga'=>'Almanya','Serie A'=>'İtalya'];
        $ulke = $ulke_map[$sampiyon['lig']] ?? null;
        if ($ulke) {
            try {
                $pdo->exec("UPDATE uefa_coefficients SET toplam_puan = toplam_puan + 5.0, sezon_puan = sezon_puan + 5.0 WHERE ulke_adi = '$ulke'");
                $pdo->exec("UPDATE uefa_siralamasi SET toplam_puan = toplam_puan + 5000, guncel_sezon_puan = guncel_sezon_puan + 5000 WHERE ulke_adi = '$ulke'");
            } catch(Throwable $e) {}
        }
    }

    // 4. OYUNCULARI YAŞLANDIR VE DURUMLARI SIFIRLA
    $pdo->exec("UPDATE cl_oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    $pdo->exec("DELETE FROM cl_oyuncular WHERE yas >= 38"); // 38 yaşında emeklilik

    // 5. İSTATİSTİKLERİ VE FİKSTÜRÜ SIFIRLA
    $pdo->exec("UPDATE cl_takimlar SET puan = 0, galibiyet = 0, beraberlik = 0, malubiyet = 0, atilan_gol = 0, yenilen_gol = 0");
    $pdo->exec("TRUNCATE TABLE cl_maclar"); 
    
    // 6. YILI VE HAFTAYI İLERLET
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
            
            <img src="<?= htmlspecialchars($sampiyon['logo']) ?>" class="champ-logo">
            
            <h1 class="font-oswald" style="font-size: 4.5rem; color: var(--gold); line-height: 1; text-shadow: 0 5px 15px rgba(0,0,0,0.8);">🏆 <?= htmlspecialchars($sampiyon['takim_adi']) ?></h1>
            <p class="fs-5 mt-3 text-light">Final maçını kazanarak Avrupa'nın en büyüğü oldular!</p>

            <?php if($final_skor): ?>
            <div style="margin: 15px auto; background: rgba(0,0,0,0.6); border: 1px solid rgba(0,229,255,0.3); border-radius: 12px; padding: 14px 30px; display: inline-block;">
                <div style="color:#94a3b8; font-size:0.8rem; letter-spacing:2px; margin-bottom:6px;">FİNAL SKORU</div>
                <div style="font-family:'Oswald',sans-serif; font-size:2rem; color:#fff; font-weight:900;"><?= htmlspecialchars($final_skor) ?></div>
            </div>
            <?php endif; ?>
            
            <div class="season-summary">
                <div class="summary-item">
                    <div class="summary-title">🏆 ŞAMPİYON</div>
                    <div class="summary-val mt-1" style="color:var(--gold);"><?= htmlspecialchars($sampiyon['takim_adi']) ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">🥈 FİNALİST</div>
                    <div class="summary-val mt-1 text-white" style="font-size: 1.2rem;"><?= $finalist ? htmlspecialchars($finalist['takim_adi']) : 'Bilinmiyor' ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-title">UEFA ŞAMPİYONLUK ÖDÜLÜ</div>
                    <div class="summary-val mt-1">€100.0M</div>
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