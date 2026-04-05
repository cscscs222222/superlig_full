<?php
// ==============================================================================
// GLOBAL TRANSFER NETWORK (KÜRESEL TRANSFER AĞI) - %100 HATASIZ SÜRÜM
// ==============================================================================
include 'db.php';

$mesaj = "";
$mesaj_tipi = "";

// 1. KULLANICININ YÖNETTİĞİ TAKIMLARI GÜVENLİ ŞEKİLDE ÇEK
$benim_takimlarim = [];

// Süper Lig
try {
    $ayar_tr = $pdo->query("SELECT kullanici_takim_id FROM ayar LIMIT 1")->fetchColumn();
    if($ayar_tr) {
        $takim = $pdo->query("SELECT id, takim_adi, logo, butce, lig FROM takimlar WHERE id = $ayar_tr")->fetch(PDO::FETCH_ASSOC);
        if($takim) { $takim['kaynak'] = 'tr'; $benim_takimlarim[] = $takim; }
    }
} catch(Throwable $e) {}

// Şampiyonlar Ligi
try {
    $ayar_cl = $pdo->query("SELECT kullanici_takim_id FROM cl_ayar LIMIT 1")->fetchColumn();
    if($ayar_cl) {
        $takim = $pdo->query("SELECT id, takim_adi, logo, butce, lig FROM cl_takimlar WHERE id = $ayar_cl")->fetch(PDO::FETCH_ASSOC);
        if($takim) { $takim['kaynak'] = 'cl'; $benim_takimlarim[] = $takim; }
    }
} catch(Throwable $e) {}

// Premier Lig
try {
    $ayar_pl = $pdo->query("SELECT kullanici_takim_id FROM pl_ayar LIMIT 1")->fetchColumn();
    if($ayar_pl) {
        $takim = $pdo->query("SELECT id, takim_adi, logo, butce, lig FROM pl_takimlar WHERE id = $ayar_pl")->fetch(PDO::FETCH_ASSOC);
        if($takim) { $takim['kaynak'] = 'pl'; $benim_takimlarim[] = $takim; }
    }
} catch(Throwable $e) {}


// Tablo Haritalaması
$tbl_oyuncu = ['tr' => 'oyuncular', 'cl' => 'cl_oyuncular', 'pl' => 'pl_oyuncular'];
$tbl_takim = ['tr' => 'takimlar', 'cl' => 'cl_takimlar', 'pl' => 'pl_takimlar'];

// --- KÜRESEL SATIN ALMA İŞLEMİ ---
if (isset($_POST['satin_al'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $kaynak_db = $_POST['kaynak_db']; // tr, cl veya pl
    $hedef_takim_verisi = explode('_', $_POST['alici_takim']); // Örn: tr_5
    
    if(count($hedef_takim_verisi) == 2) {
        $hedef_db = $hedef_takim_verisi[0];
        $hedef_takim_id = (int)$hedef_takim_verisi[1];
        
        $src_o_tbl = $tbl_oyuncu[$kaynak_db]; $src_t_tbl = $tbl_takim[$kaynak_db];
        $tgt_o_tbl = $tbl_oyuncu[$hedef_db];  $tgt_t_tbl = $tbl_takim[$hedef_db];
        
        try {
            $hedef_oyuncu = $pdo->query("SELECT * FROM $src_o_tbl WHERE id = $oyuncu_id")->fetch(PDO::FETCH_ASSOC);
            $alici_takim = $pdo->query("SELECT * FROM $tgt_t_tbl WHERE id = $hedef_takim_id")->fetch(PDO::FETCH_ASSOC);
            
            if ($hedef_oyuncu && $alici_takim) {
                $fiyat = $hedef_oyuncu['fiyat'];
                $eski_takim_id = $hedef_oyuncu['takim_id'];
                
                if ($eski_takim_id == $hedef_takim_id && $kaynak_db == $hedef_db) {
                    $mesaj = "Bu oyuncu zaten takımınızda!"; $mesaj_tipi = "warning";
                } elseif ($alici_takim['butce'] >= $fiyat) {
                    
                    // Bütçe Güncelleme
                    $pdo->exec("UPDATE $tgt_t_tbl SET butce = butce - $fiyat WHERE id = $hedef_takim_id");
                    $pdo->exec("UPDATE $src_t_tbl SET butce = butce + $fiyat WHERE id = $eski_takim_id");
                    
                    // Oyuncuyu Yeni Lige Kopyala (Güvenli Prepare Statement ile)
                    $stmt = $pdo->prepare("INSERT INTO $tgt_o_tbl (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek, form, fitness, moral, ceza_hafta, sakatlik_hafta, saha_pozisyon) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, 6, 100, 80, 0, 0, '50,50')");
                    $stmt->execute([$hedef_takim_id, $hedef_oyuncu['isim'], $hedef_oyuncu['mevki'], $hedef_oyuncu['ovr'], $hedef_oyuncu['yas'], $fiyat, $alici_takim['lig']]);
                    
                    // Oyuncuyu Eski Liginden Sil
                    $pdo->exec("DELETE FROM $src_o_tbl WHERE id = $oyuncu_id");
                    
                    $mesaj = "BOMBA TRANSFER! " . htmlspecialchars($hedef_oyuncu['isim']) . " resmen " . htmlspecialchars($alici_takim['takim_adi']) . " kadrosuna katıldı!";
                    $mesaj_tipi = "success";
                    
                    // Güncel bütçeyi yansıtmak için diziyi de güncelle
                    foreach($benim_takimlarim as &$bt) {
                        if($bt['id'] == $hedef_takim_id && $bt['kaynak'] == $hedef_db) { $bt['butce'] -= $fiyat; }
                    }
                } else {
                    $mesaj = "Finansal Uyarı! " . htmlspecialchars($alici_takim['takim_adi']) . " kasasında yeterli bütçe yok.";
                    $mesaj_tipi = "danger";
                }
            }
        } catch(Throwable $e) {
            $mesaj = "Transfer sırasında bir veritabanı hatası oluştu."; $mesaj_tipi = "danger";
        }
    }
}

// --- TÜM DÜNYA OYUNCULARINI GÜVENLİ ŞEKİLDE ÇEK VE BİRLEŞTİR (ÇÖKMEYİ ENGELLEYEN YÖNTEM) ---
$tum_oyuncular = [];

// Süper Lig Oyuncuları
try {
    $tr_oyuncular = $pdo->query("SELECT o.id, o.takim_id, o.isim, o.mevki, o.ovr, o.yas, o.fiyat, o.lig, 'tr' AS kaynak, t.takim_adi, t.logo 
                                 FROM oyuncular o JOIN takimlar t ON o.takim_id = t.id")->fetchAll(PDO::FETCH_ASSOC);
    $tum_oyuncular = array_merge($tum_oyuncular, $tr_oyuncular);
} catch(Throwable $e) {}

// Şampiyonlar Ligi Oyuncuları
try {
    $cl_oyuncular = $pdo->query("SELECT o.id, o.takim_id, o.isim, o.mevki, o.ovr, o.yas, o.fiyat, o.lig, 'cl' AS kaynak, t.takim_adi, t.logo 
                                 FROM cl_oyuncular o JOIN cl_takimlar t ON o.takim_id = t.id")->fetchAll(PDO::FETCH_ASSOC);
    $tum_oyuncular = array_merge($tum_oyuncular, $cl_oyuncular);
} catch(Throwable $e) {}

// Premier Lig Oyuncuları
try {
    $pl_oyuncular = $pdo->query("SELECT o.id, o.takim_id, o.isim, o.mevki, o.ovr, o.yas, o.fiyat, o.lig, 'pl' AS kaynak, t.takim_adi, t.logo 
                                 FROM pl_oyuncular o JOIN pl_takimlar t ON o.takim_id = t.id")->fetchAll(PDO::FETCH_ASSOC);
    $tum_oyuncular = array_merge($tum_oyuncular, $pl_oyuncular);
} catch(Throwable $e) {}

// --- PHP TARAFINDA ARAMA VE FİLTRELEME ---
$arama = strtolower($_GET['q'] ?? '');
$min_ovr = (int)($_GET['min_ovr'] ?? 0);

$filtrelenmis_oyuncular = [];
foreach($tum_oyuncular as $o) {
    // Arama Filtresi
    if($arama != '') {
        $isim_uygun = strpos(strtolower($o['isim']), $arama) !== false;
        $takim_uygun = strpos(strtolower($o['takim_adi']), $arama) !== false;
        if(!$isim_uygun && !$takim_uygun) continue; 
    }
    // OVR Filtresi
    if($min_ovr > 0 && $o['ovr'] < $min_ovr) continue;
    
    $filtrelenmis_oyuncular[] = $o;
}

// OVR'ye Göre Büyükten Küçüğe Sırala
usort($filtrelenmis_oyuncular, function($a, $b) {
    return $b['ovr'] <=> $a['ovr'];
});

// Ekrana ilk 150 kişiyi bas (Performans için)
$pazardaki_oyuncular = array_slice($filtrelenmis_oyuncular, 0, 150);

function paraFormatla($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    if ($sayi >= 1000) return "€" . number_format($sayi / 1000, 1) . "K";
    return "€" . $sayi;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Global Transfer Ağı | Dünya Pazarı</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --gl-primary: #121212; 
            --gl-gold: #d4af37; 
            --gl-darkgold: #997a00;
            --gl-panel: #1a1a1a;
            --border-color: rgba(212, 175, 55, 0.3);
            --text-primary: #f8fafc;
            --text-muted: #94a3b8;
        }

        body { 
            background-color: var(--gl-primary); color: var(--text-primary); font-family: 'Inter', sans-serif;
            background-image: radial-gradient(circle at 50% 0%, rgba(212, 175, 55, 0.1) 0%, transparent 60%);
            min-height: 100vh;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(18,18,18,0.95); backdrop-filter: blur(24px); border-bottom: 2px solid var(--gl-gold); position: sticky; top: 0; z-index: 1000; padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center;}
        .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; text-shadow: 0 0 10px var(--gl-gold); }
        .nav-brand i { color: var(--gl-gold); }
        
        .btn-action-outline { background: transparent; border: 1px solid var(--gl-gold); color: var(--gl-gold); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none; transition: 0.3s;}
        .btn-action-outline:hover { background: var(--gl-gold); color: #000; box-shadow: 0 0 15px var(--gl-gold); }

        .hero-banner { padding: 3rem 2rem; border-bottom: 1px solid var(--border-color); background: rgba(0, 0, 0, 0.4); text-align: center; }

        .panel-card { background: var(--gl-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.8); }
        
        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: var(--gl-gold); font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center; background: rgba(0,0,0,0.6);}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); vertical-align: middle; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(212, 175, 55, 0.05); }
        
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 700; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.8));}
        
        .ovr-box { background: rgba(212, 175, 55, 0.1); color: var(--gl-gold); font-weight: 800; padding: 4px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(212, 175, 55, 0.3);}
        
        .btn-buy { background: linear-gradient(45deg, var(--gl-darkgold), var(--gl-gold)); color: #000; font-weight: 800; border: none; border-radius: 6px; padding: 6px 15px; font-size: 0.85rem; transition: 0.2s;}
        .btn-buy:hover { box-shadow: 0 0 15px var(--gl-gold); transform: scale(1.05); color: #fff;}

        .filter-bar { background: rgba(0,0,0,0.5); border-bottom: 1px solid var(--border-color); padding: 15px; display: flex; gap: 10px; align-items: center;}
        .filter-input { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: #fff; border-radius: 6px; padding: 8px 15px; width: 100%; transition: 0.3s; font-weight:600;}
        .filter-input:focus { outline: none; border-color: var(--gl-gold); background: rgba(212, 175, 55, 0.05); }
        .btn-search { background: var(--gl-gold); color: #000; border: none; font-weight: 800; padding: 8px 20px; border-radius: 6px; transition: 0.2s;}
        .btn-search:hover { background: #fff; }

        .league-badge { font-size: 0.65rem; padding: 3px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase;}
        .l-tr { background: #e11d48; color: #fff; }
        .l-cl { background: #00e5ff; color: #000; }
        .l-pl { background: #e2f89c; color: #000; }
    </style>
</head>
<body>

    <nav class="pro-navbar">
        <a href="index.php" class="nav-brand"><i class="fa-solid fa-globe"></i> <span class="font-oswald">GLOBAL TRANSFER HUB</span></a>
        
        <div class="d-flex gap-3">
            <a href="index.php" class="btn-action-outline">
                <i class="fa-solid fa-house"></i> Ana Merkeze Dön
            </a>
        </div>
    </nav>

    <div class="hero-banner">
        <h1 class="font-oswald m-0 text-white" style="font-size: 3.5rem; text-shadow: 0 0 20px rgba(212, 175, 55, 0.5);">KÜRESEL TRANSFER AĞI</h1>
        <p class="fs-5 mt-2 fw-bold" style="color: var(--text-muted);">Tüm dünya ligleri tek bir pazarda. İstediğin yıldızı, istediğin kulübüne bağla.</p>
        
        <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
            <?php foreach($benim_takimlarim as $bt): ?>
                <div class="badge border p-2" style="background: rgba(0,0,0,0.5); border-color:var(--gl-gold) !important; font-size:0.9rem;">
                    <img src="<?= $bt['logo'] ?>" style="width:20px; height:20px; object-fit:contain; margin-right:5px;">
                    <span class="text-white"><?= htmlspecialchars($bt['takim_adi']) ?> Bütçesi:</span> 
                    <span style="color:var(--gl-gold); font-family:'Oswald'; font-size:1.1rem;"><?= paraFormatla($bt['butce']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if(empty($benim_takimlarim)): ?>
                <div class="badge bg-danger p-2 fs-6">Henüz hiçbir ligde takım yönetmiyorsunuz! Önce bir lige girip takım seçin.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-5" style="max-width: 1500px;">
        
        <?php if($mesaj): ?>
            <div class="alert fw-bold text-center border-0 shadow-lg mb-4" style="background: <?= $mesaj_tipi == 'success' ? '#10b981' : ($mesaj_tipi == 'danger' ? '#ef4444' : '#f59e0b') ?>; color: <?= $mesaj_tipi == 'warning' ? '#000' : '#fff' ?>;">
                <?= $mesaj ?>
            </div>
        <?php endif; ?>

        <div class="panel-card shadow-lg">
            <div class="filter-bar justify-content-between flex-wrap">
                <form method="GET" class="d-flex gap-2 flex-grow-1" style="max-width: 600px;">
                    <input type="text" name="q" class="filter-input" placeholder="Dünya yıldızlarını veya kulüp ara..." value="<?= htmlspecialchars($arama) ?>">
                    <select name="min_ovr" class="filter-input" style="width:130px;">
                        <option value="0">Tüm Güçler</option>
                        <option value="80" <?= $min_ovr==80?'selected':'' ?>>OVR 80+</option>
                        <option value="85" <?= $min_ovr==85?'selected':'' ?>>OVR 85+</option>
                        <option value="90" <?= $min_ovr==90?'selected':'' ?>>OVR 90+</option>
                    </select>
                    <button type="submit" class="btn-search"><i class="fa-solid fa-magnifying-glass"></i> Bul</button>
                </form>
            </div>
            
            <div class="table-responsive" style="max-height: 700px; overflow-y: auto;">
                <table class="data-table">
                    <thead style="position: sticky; top: 0; z-index: 10;">
                        <tr>
                            <th width="5%">Lig</th>
                            <th>Oyuncu İsmi</th>
                            <th>Kulüp</th>
                            <th>OVR</th>
                            <th class="text-end">Piyasa Değeri</th>
                            <th class="text-center" style="width:300px;">Hangi Takımına Alacaksın?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pazardaki_oyuncular as $o): ?>
                        <tr>
                            <td><span class="league-badge l-<?= $o['kaynak'] ?>"><?= strtoupper($o['kaynak']) ?></span></td>
                            <td class="fw-bold text-start text-white" style="font-size:1rem;"><?= htmlspecialchars($o['isim']) ?></td>
                            <td class="text-start">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <img src="<?= $o['logo'] ?>" style="width:24px; height:24px; object-fit:contain;">
                                    <span style="color:var(--text-muted); font-weight:600;"><?= htmlspecialchars($o['takim_adi']) ?></span>
                                </div>
                            </td>
                            <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                            <td class="text-end font-oswald fs-5" style="color: var(--gl-gold); letter-spacing:0.5px;"><?= paraFormatla($o['fiyat']) ?></td>
                            <td class="text-center">
                                <form method="POST" class="d-flex gap-2 justify-content-center">
                                    <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                    <input type="hidden" name="kaynak_db" value="<?= $o['kaynak'] ?>">
                                    
                                    <select name="alici_takim" class="filter-input py-1 px-2" style="width:150px; font-size:0.8rem;" required>
                                        <option value="">Kulüp Seç</option>
                                        <?php foreach($benim_takimlarim as $bt): 
                                            // Kendi oyuncunu kendine alma
                                            if($o['kaynak'] == $bt['kaynak'] && $o['takim_id'] == $bt['id']) continue;
                                        ?>
                                            <option value="<?= $bt['kaynak'] . '_' . $bt['id'] ?>" <?= $bt['butce'] < $o['fiyat'] ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($bt['takim_adi']) ?> (<?= $bt['butce'] < $o['fiyat'] ? 'Bütçe Yetersiz' : 'Uygun' ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="satin_al" class="btn-buy py-1 px-2"><i class="fa-solid fa-check"></i> Al</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($pazardaki_oyuncular)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted font-oswald fs-5">Dünya pazarında oyuncu bulunamadı. Lütfen liglere girip takım seçerek veritabanını tetikleyin.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>