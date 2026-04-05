<?php
// ==============================================================================
// SÜPER LİG - SEZON SONU KUTLAMASI VE YENİ SEZON GEÇİŞİ (GOLD & DARK THEME)
// ==============================================================================
include '../db.php';

// Güncel ayarları ve puan durumunu çek
$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$guncel_sezon = $ayar['sezon_yil'] ?? 2025;

// Şampiyonu ve sıralamayı belirle
$puan_durumu = $pdo->query("SELECT * FROM takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
$sampiyon = $puan_durumu[0];
$ikinci = $puan_durumu[1];

$mesaj = "";

if(isset($_POST['yeni_sezona_gec'])) {
    
    // 1. ÖDÜL DAĞITIMI (KASALARA PARA GİRİŞİ)
    // Şampiyona 30M, İkinciye 15M, diğerlerine performanslarına göre para
    foreach($puan_durumu as $index => $t) {
        $sira = $index + 1;
        $odul = 0;
        if($sira == 1) $odul = 30000000;
        elseif($sira == 2) $odul = 15000000;
        elseif($sira <= 4) $odul = 8000000;
        else $odul = 2000000; // Katılım / Teselli payı
        
        $pdo->exec("UPDATE takimlar SET butce = butce + $odul WHERE id = {$t['id']}");
    }

    // 2. ŞAMPİYONLAR LİGİ'NE İHRAÇ (İLK 2 TAKIM)
    $cl_gidenler = [$sampiyon, $ikinci];
    foreach($cl_gidenler as $cl_takim) {
        // Takım CL'de var mı kontrol et
        $var_mi = $pdo->query("SELECT id FROM cl_takimlar WHERE takim_adi = '" . addslashes($cl_takim['takim_adi']) . "'")->fetchColumn();
        
        if(!$var_mi) {
            // Takımı CL veritabanına ekle
            $stmt = $pdo->prepare("INSERT INTO cl_takimlar (takim_adi, logo, hucum, savunma, butce, lig) VALUES (?, ?, ?, ?, ?, 'Süper Lig')");
            $stmt->execute([$cl_takim['takim_adi'], $cl_takim['logo'], $cl_takim['hucum'], $cl_takim['savunma'], 25000000]);
            $yeni_cl_id = $pdo->lastInsertId();
            
            // Oyuncularını da CL veritabanına kopyala
            $oyuncular = $pdo->query("SELECT * FROM oyuncular WHERE takim_id = {$cl_takim['id']}")->fetchAll(PDO::FETCH_ASSOC);
            foreach($oyuncular as $o) {
                $stmt_o = $pdo->prepare("INSERT INTO cl_oyuncular (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek) VALUES (?, ?, ?, ?, ?, ?, 'Süper Lig', ?, ?)");
                $stmt_o->execute([$yeni_cl_id, $o['isim'], $o['mevki'], $o['ovr'], $o['yas'], $o['fiyat'], $o['ilk_11'], $o['yedek']]);
            }
        }
    }

    // 3. OYUNCULARI YAŞLANDIR VE DURUMLARI SIFIRLA
    $pdo->exec("UPDATE oyuncular SET yas = yas + 1, form = 6, fitness = 100, ceza_hafta = 0, sakatlik_hafta = 0");
    
    // (Opsiyonel) 38 Yaşına gelenleri emekli et
    $pdo->exec("DELETE FROM oyuncular WHERE yas >= 38");

    // 4. İSTATİSTİKLERİ VE FİKSTÜRÜ SIFIRLA
    $pdo->exec("UPDATE takimlar SET puan = 0, galibiyet = 0, beraberlik = 0, malubiyet = 0, atilan_gol = 0, yenilen_gol = 0");
    $pdo->exec("TRUNCATE TABLE maclar"); // Bütün eski maçları siler (Fikstür jeneratörü yeniden çalışacak)
    
    // 5. YILI VE HAFTAYI İLERLET
    $yeni_sezon_yili = $guncel_sezon + 1;
    $pdo->exec("UPDATE ayar SET hafta = 1, sezon_yil = $yeni_sezon_yili");
    
    // YENİ SEZONA BAŞLA
    header("Location: superlig.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sezon Sonu | Süper Lig</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-body: #0a0a0a;
            --gold-primary: #d4af37;
            --gold-secondary: #997a00;
        }

        body { 
            background-color: var(--bg-body); color: #fff; font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 30%, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .celebration-card {
            background: rgba(20, 20, 20, 0.9); border: 2px solid var(--gold-primary);
            border-radius: 20px; padding: 50px; text-align: center; box-shadow: 0 0 50px rgba(212, 175, 55, 0.3);
            max-width: 800px; width: 100%; position: relative; overflow: hidden;
        }

        /* Konfeti Efekti */
        .celebration-card::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('https://i.gifer.com/7I1S.gif'); opacity: 0.1; pointer-events: none; z-index: 0;
        }

        .content-z { position: relative; z-index: 2; }

        .champ-logo { width: 150px; height: 150px; object-fit: contain; filter: drop-shadow(0 0 20px var(--gold-primary)); margin-bottom: 20px; animation: float 3s ease-in-out infinite;}
        
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        .btn-gold { background: linear-gradient(45deg, var(--gold-secondary), var(--gold-primary)); color: #000; font-weight: 900; font-size: 1.2rem; padding: 15px 40px; border: none; border-radius: 50px; margin-top: 30px; transition: 0.3s; text-transform: uppercase;}
        .btn-gold:hover { box-shadow: 0 0 30px var(--gold-primary); transform: scale(1.05); color: #fff;}

        .season-summary { display: flex; justify-content: center; gap: 30px; margin-top: 30px; border-top: 1px solid rgba(212,175,55,0.3); padding-top: 30px;}
        .summary-item { background: rgba(0,0,0,0.5); padding: 15px 25px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1);}
        .summary-title { font-size: 0.8rem; color: #94a3b8; font-weight: 700; letter-spacing: 1px;}
        .summary-val { font-family: 'Oswald'; font-size: 1.5rem; color: var(--gold-primary);}
    </style>
</head>
<body>

    <div class="container">
        <div class="celebration-card mx-auto">
            <div class="content-z">
                <div class="text-muted fw-bold mb-2 letter-spacing-2"><?= $guncel_sezon ?> - <?= $guncel_sezon + 1 ?> SEZONU ŞAMPİYONU</div>
                
                <img src="<?= $sampiyon['logo'] ?>" class="champ-logo" alt="Şampiyon Logo">
                
                <h1 class="font-oswald" style="font-size: 4rem; color: var(--gold-primary); line-height:1;"><?= $sampiyon['takim_adi'] ?></h1>
                
                <p class="fs-5 mt-3 text-light">
                    <?= $sampiyon['puan'] ?> Puan ile zafere ulaştılar! 
                    <?php if($sampiyon['id'] == $kullanici_takim_id): ?>
                        <br><span class="text-success fw-bold"><i class="fa-solid fa-trophy"></i> TEBRİKLER MENAJER! TARİH YAZDINIZ!</span>
                    <?php endif; ?>
                </p>

                <div class="season-summary">
                    <div class="summary-item">
                        <div class="summary-title">CL BİLETİ ALANLAR</div>
                        <div class="summary-val mt-1" style="font-size: 1.1rem; color:#fff;">1. <?= $sampiyon['takim_adi'] ?><br>2. <?= $ikinci['takim_adi'] ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-title">ŞAMPİYONLUK PRİMİ</div>
                        <div class="summary-val text-success">€30.0M</div>
                    </div>
                </div>

                <form method="POST">
                    <button type="submit" name="yeni_sezona_gec" class="btn-gold" onclick="return confirm('Tüm istatistikler sıfırlanacak, oyuncular yaşlanacak ve yeni sezon fikstürü çekilecek. Emin misiniz?');">
                        <i class="fa-solid fa-forward"></i> <?= $guncel_sezon + 1 ?>-<?= $guncel_sezon + 2 ?> Sezonuna Başla
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>