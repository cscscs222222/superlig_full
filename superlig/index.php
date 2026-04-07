<?php
// ==============================================================================
// ULTIMATE MANAGER - NEXT-GEN MERKEZ HUB (ULTRA MODERN GLASSMORPHISM THEME)
// ==============================================================================
include 'db.php';

// Kullanıcı takim seçimi (oturum tabanlı)
if (session_status() === PHP_SESSION_NONE) session_start();

// Tüm lig ayar tablolarına gecen_sezon_sampiyon sütunu ekle (eğer yoksa)
$ayar_tablosu_migrasyonlari = [
    'ayar'     => 'Galatasaray',
    'pl_ayar'  => 'Manchester City',
    'es_ayar'  => 'Real Madrid',
    'de_ayar'  => 'Bayern München',
    'it_ayar'  => 'Inter Milan',
    'fr_ayar'  => 'Paris Saint-Germain',
    'pt_ayar'  => 'Sporting CP',
];
foreach ($ayar_tablosu_migrasyonlari as $tbl => $default_sampiyon) {
    try {
        if ($pdo->query("SHOW COLUMNS FROM `$tbl` LIKE 'gecen_sezon_sampiyon'")->rowCount() == 0) {
            $pdo->exec("ALTER TABLE `$tbl` ADD `gecen_sezon_sampiyon` VARCHAR(100) DEFAULT " . $pdo->quote($default_sampiyon));
        }
    } catch (Throwable $e) {}
}

// Takım seçim formu işle
if (isset($_POST['takim_sec'])) {
    // Format: "tablo:id" (e.g. "fr_takimlar:583")
    $raw = $_POST['takim_id'] ?? '';
    $parts = explode(':', $raw, 2);
    $tablo_secilen = $parts[0] ?? 'takimlar';
    $tid = (int)($parts[1] ?? $raw);

    // Güvenli tablo listesi
    $allowed_takimlar = ['takimlar','pl_takimlar','es_takimlar','de_takimlar','it_takimlar','fr_takimlar','pt_takimlar'];
    if (!in_array($tablo_secilen, $allowed_takimlar, true)) { $tablo_secilen = 'takimlar'; }

    // Süper Lig ayar tablosuna da yaz (geriye dönük uyumluluk)
    try { $pdo->prepare("UPDATE ayar SET kullanici_takim_id = ? WHERE id = 1")->execute([$tid]); } catch (Throwable $e) {}

    // Oturuma kaydet
    $_SESSION['kullanici_takim_tablo'] = $tablo_secilen;
    $_SESSION['kullanici_takim_id'] = $tid;
    header("Location: index.php"); exit;
}

// Mevcut kullanıcı takımını al
$kullanici_takim_id = 0;
$kullanici_takim    = null;
$kullanici_tablo    = 'takimlar';
$lig_siralama       = null;
$sonraki_mac        = null;
$avrupa_durum       = null;
$transfer_butce     = 0;

// Önce session'dan, yoksa Süper Lig ayar tablosundan al
if (!empty($_SESSION['kullanici_takim_id'])) {
    $kullanici_takim_id = (int)$_SESSION['kullanici_takim_id'];
    $allowed_takimlar_sess = ['takimlar','pl_takimlar','es_takimlar','de_takimlar','it_takimlar','fr_takimlar','pt_takimlar'];
    $kullanici_tablo = in_array($_SESSION['kullanici_takim_tablo'] ?? '', $allowed_takimlar_sess, true)
        ? $_SESSION['kullanici_takim_tablo']
        : 'takimlar';
} else {
    try {
        $kullanici_takim_id = (int)$pdo->query("SELECT kullanici_takim_id FROM ayar LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {}
}

// Lig-spesifik ayar/maç tablosu mapping
$lig_tablo_map = [
    'takimlar'    => ['ayar' => 'ayar',     'maclar' => 'maclar'],
    'pl_takimlar' => ['ayar' => 'pl_ayar',  'maclar' => 'pl_maclar'],
    'es_takimlar' => ['ayar' => 'es_ayar',  'maclar' => 'es_maclar'],
    'de_takimlar' => ['ayar' => 'de_ayar',  'maclar' => 'de_maclar'],
    'it_takimlar' => ['ayar' => 'it_ayar',  'maclar' => 'it_maclar'],
    'fr_takimlar' => ['ayar' => 'fr_ayar',  'maclar' => 'fr_maclar'],
    'pt_takimlar' => ['ayar' => 'pt_ayar',  'maclar' => 'pt_maclar'],
];
$kullanici_ayar_tbl = $lig_tablo_map[$kullanici_tablo]['ayar'] ?? 'ayar';
$kullanici_maclar_tbl = $lig_tablo_map[$kullanici_tablo]['maclar'] ?? 'maclar';

if ($kullanici_takim_id) {
    // Takım bilgisi (doğru tablodan)
    try {
        $stmt = $pdo->prepare(
            "SELECT t.*, a.hafta, a.sezon_yil
               FROM `$kullanici_tablo` t, `$kullanici_ayar_tbl` a
              WHERE t.id = ?
              LIMIT 1"
        );
        $stmt->execute([$kullanici_takim_id]);
        $kullanici_takim = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // Puan tablosundaki sıra
    try {
        $siralar = $pdo->query(
            "SELECT id, ROW_NUMBER() OVER (ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC) as sira
               FROM `$kullanici_tablo`"
        )->fetchAll(PDO::FETCH_KEY_PAIR);
        $lig_siralama = $siralar[$kullanici_takim_id] ?? null;
    } catch (Throwable $e) {
        try {
            $all = $pdo->query("SELECT id FROM `$kullanici_tablo` ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_COLUMN);
            $lig_siralama = array_search($kullanici_takim_id, $all) + 1;
        } catch (Throwable $e2) {}
    }

    // Sonraki maç
    try {
        $stmt = $pdo->prepare(
            "SELECT m.*, t1.takim_adi AS ev_ad, t2.takim_adi AS dep_ad
               FROM `$kullanici_maclar_tbl` m
               JOIN `$kullanici_tablo` t1 ON t1.id = m.ev
               JOIN `$kullanici_tablo` t2 ON t2.id = m.dep
              WHERE (m.ev = ? OR m.dep = ?)
                AND m.ev_skor IS NULL
              ORDER BY m.hafta ASC LIMIT 1"
        );
        $stmt->execute([$kullanici_takim_id, $kullanici_takim_id]);
        $sonraki_mac = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}

    // Şampiyonlar Ligi durumu
    try {
        $takim_adi_sql = $pdo->query("SELECT takim_adi FROM `$kullanici_tablo` WHERE id=$kullanici_takim_id LIMIT 1")->fetchColumn();
        if ($takim_adi_sql) {
            foreach (['cl_takimlar'=>'UCL','uel_takimlar'=>'UEL','uecl_takimlar'=>'UECL'] as $tbl=>$tur) {
                $check = $pdo->prepare("SELECT id FROM $tbl WHERE takim_adi=? LIMIT 1");
                $check->execute([$takim_adi_sql]);
                if ($check->fetchColumn()) { $avrupa_durum = ['tur'=>$tur]; break; }
            }
        }
    } catch (Throwable $e) {}

    // Transfer bütçesi
    try {
        $stmt = $pdo->prepare("SELECT butce FROM `$kullanici_tablo` WHERE id=? LIMIT 1");
        $stmt->execute([$kullanici_takim_id]);
        $transfer_butce = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {}
}

// Tüm liglerdeki takımlar (seçim için) — her lig kendi tablosundan
$tum_takimlar = [];
$takim_ligleri = [
    ['tablo' => 'takimlar',    'lig_adi' => 'Süper Lig'],
    ['tablo' => 'pl_takimlar', 'lig_adi' => 'Premier League'],
    ['tablo' => 'es_takimlar', 'lig_adi' => 'La Liga'],
    ['tablo' => 'de_takimlar', 'lig_adi' => 'Bundesliga'],
    ['tablo' => 'it_takimlar', 'lig_adi' => 'Serie A'],
    ['tablo' => 'fr_takimlar', 'lig_adi' => 'Ligue 1'],
    ['tablo' => 'pt_takimlar', 'lig_adi' => 'Liga NOS'],
];
foreach ($takim_ligleri as $l) {
    try {
        $rows = $pdo->query("SELECT id, takim_adi, logo FROM {$l['tablo']} ORDER BY takim_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['lig_adi'] = $l['lig_adi']; $r['tablo'] = $l['tablo']; }
        unset($r);
        $tum_takimlar = array_merge($tum_takimlar, $rows);
    } catch (Throwable $e) {}
}

// Son sezon şampiyonları (dashboard için)
$sampiyonlar = [];
$sampiyon_ligler = [
    'Süper Lig'      => 'ayar',
    'Premier League' => 'pl_ayar',
    'La Liga'        => 'es_ayar',
    'Bundesliga'     => 'de_ayar',
    'Serie A'        => 'it_ayar',
    'Ligue 1'        => 'fr_ayar',
    'Liga NOS'       => 'pt_ayar',
];
foreach ($sampiyon_ligler as $lig_adi => $ayar_tablosu) {
    try {
        $s = $pdo->query("SELECT gecen_sezon_sampiyon FROM `$ayar_tablosu` LIMIT 1")->fetchColumn();
        if ($s) $sampiyonlar[$lig_adi] = $s;
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ultimate Manager | Ana Merkez</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- YENİ NESİL ARKA PLAN VE GENEL AYARLAR --- */
        body, html {
            margin: 0; padding: 0; min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            background-color: #050505;
            color: #fff;
            overflow-x: hidden;
        }

        /* Dinamik ve sinematik stadyum arka planı */
        .bg-image {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://images.unsplash.com/photo-1508344928928-7137b29de216?q=80&w=2000') no-repeat center center;
            background-size: cover;
            z-index: -2;
            animation: slowZoom 20s infinite alternate;
        }
        @keyframes slowZoom { 0% { transform: scale(1); } 100% { transform: scale(1.05); } }

        /* Karanlık Filtre (Cam Efekti için zemin) */
        .bg-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: linear-gradient(135deg, rgba(5,5,5,0.9) 0%, rgba(20,20,30,0.75) 100%);
            backdrop-filter: blur(8px);
            z-index: -1;
        }

        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* --- MODERN NAVBAR --- */
        .modern-nav {
            padding: 25px 50px;
            display: flex; justify-content: center; align-items: center;
            background: transparent;
        }
        .nav-brand {
            font-size: 2rem; font-weight: 900; color: #fff; text-decoration: none; letter-spacing: 3px;
            text-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
            display: flex; align-items: center; gap: 15px;
        }
        .nav-brand i { color: #d4af37; font-size: 2.2rem; }

        /* --- HERO (BAŞLIK) KISMI --- */
        .hero-section {
            text-align: center; padding: 40px 20px;
        }
        .hero-title {
            font-size: 4.5rem; font-weight: 900; color: #fff; line-height: 1.1; margin-bottom: 10px;
            text-shadow: 0 10px 30px rgba(0,0,0,0.8);
        }
        .hero-subtitle {
            font-size: 1.2rem; font-weight: 300; color: #cbd5e1; letter-spacing: 2px;
        }
        .gold-text { background: linear-gradient(45deg, #d4af37, #fde047); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

        /* --- GLASSMORPHISM KART GRID --- */
        .game-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 32px;
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px 30px;
        }

        /* 3D Etkileşimli Kartlar */
        .mode-card {
            position: relative;
            border-radius: 28px;
            overflow: hidden;
            text-decoration: none;
            height: 300px;
            display: flex; flex-direction: column; justify-content: flex-end;
            padding: 28px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: all 0.45s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 20px 40px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* Kart Arka Plan Resimleri (Saydam ve Sinematik) */
        .mode-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-size: cover; background-position: center;
            opacity: 0.25; transition: 0.6s ease; z-index: 0; filter: grayscale(60%) blur(1px);
        }
        .mode-card.tr::before { background-image: url('https://images.unsplash.com/photo-1518605368461-1e1e38ce8ba4?q=80&w=800'); }
        .mode-card.pl::before { background-image: url('https://images.unsplash.com/photo-1489944440615-453fc2b6a9a9?q=80&w=800'); }
        .mode-card.cl::before { background-image: url('https://images.unsplash.com/photo-1556816214-cb3ce168c076?q=80&w=800'); }
        .mode-card.gl::before { background-image: url('https://images.unsplash.com/photo-1522778526097-ce0a22ceb253?q=80&w=800'); }
        .mode-card.es::before { background-image: url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=800'); }
        .mode-card.de::before { background-image: url('https://images.unsplash.com/photo-1551958219-acbc595d0b17?q=80&w=800'); }
        .mode-card.fr::before { background-image: url('https://images.unsplash.com/photo-1508344928928-7137b29de216?q=80&w=800'); }
        .mode-card.it::before { background-image: url('https://images.unsplash.com/photo-1516382799247-87df95d790b7?q=80&w=800'); }
        .mode-card.pt::before { background-image: url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?q=80&w=800'); }

        /* Kart Rengi Glow Efektleri (Hover) */
        .mode-card:hover {
            transform: translateY(-18px) scale(1.02);
            background: rgba(255, 255, 255, 0.09);
            border-color: rgba(255, 255, 255, 0.3);
        }
        .mode-card:hover::before { opacity: 0.65; filter: grayscale(0%) blur(0px); transform: scale(1.08); }

        .mode-card.tr:hover { box-shadow: 0 25px 60px rgba(225, 29, 72, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(225, 29, 72, 0.6); }
        .mode-card.pl:hover { box-shadow: 0 25px 60px rgba(123, 44, 191, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(123, 44, 191, 0.6); }
        .mode-card.cl:hover { box-shadow: 0 25px 60px rgba(0, 229, 255, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(0, 229, 255, 0.6); }
        .mode-card.gl:hover { box-shadow: 0 25px 60px rgba(212, 175, 55, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(212, 175, 55, 0.6); }
        .mode-card.es:hover { box-shadow: 0 25px 60px rgba(245, 158, 11, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(245, 158, 11, 0.6); }
        .mode-card.de:hover { box-shadow: 0 25px 60px rgba(229, 57, 53, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(229, 57, 53, 0.6); }
        .mode-card.fr:hover { box-shadow: 0 25px 60px rgba(59, 130, 246, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(59, 130, 246, 0.6); }
        .mode-card.it:hover { box-shadow: 0 25px 60px rgba(16, 185, 129, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(16, 185, 129, 0.6); }
        .mode-card.pt:hover { box-shadow: 0 25px 60px rgba(139, 92, 246, 0.45), inset 0 1px 0 rgba(255,255,255,0.15); border-color: rgba(139, 92, 246, 0.6); }

        /* --- LİG LOGO ALANI --- */
        .card-logo-wrapper {
            position: absolute; top: 0; left: 0; width: 100%; height: 60%;
            display: flex; align-items: center; justify-content: center; z-index: 1;
        }
        .card-logo-bg {
            width: 110px; height: 110px; border-radius: 50%;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(10px);
            transition: all 0.4s ease;
            box-shadow: 0 8px 32px rgba(0,0,0,0.4);
        }
        .mode-card:hover .card-logo-bg {
            transform: scale(1.12);
            background: rgba(255,255,255,0.14);
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 12px 40px rgba(0,0,0,0.5);
        }
        .card-logo-bg img {
            width: 72px; height: 72px; object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.5)) brightness(1.05);
            transition: all 0.4s ease;
        }
        .mode-card:hover .card-logo-bg img { filter: drop-shadow(0 6px 20px rgba(0,0,0,0.6)) brightness(1.15); transform: scale(1.05); }

        /* Glow renkleri logo çevresinde (hover) */
        .mode-card.tr:hover .card-logo-bg { box-shadow: 0 0 30px rgba(225,29,72,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(225,29,72,0.5); }
        .mode-card.pl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(123,44,191,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(123,44,191,0.5); }
        .mode-card.cl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(0,229,255,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(0,229,255,0.5); }
        .mode-card.gl:hover .card-logo-bg { box-shadow: 0 0 30px rgba(212,175,55,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(212,175,55,0.5); }
        .mode-card.es:hover .card-logo-bg { box-shadow: 0 0 30px rgba(245,158,11,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(245,158,11,0.5); }
        .mode-card.de:hover .card-logo-bg { box-shadow: 0 0 30px rgba(229,57,53,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(229,57,53,0.5); }
        .mode-card.it:hover .card-logo-bg { box-shadow: 0 0 30px rgba(16,185,129,0.5), 0 8px 32px rgba(0,0,0,0.4); border-color: rgba(16,185,129,0.5); }

        /* Kart İçi İçerik (Metinler) */
        .card-content { position: relative; z-index: 2; transition: 0.3s; }
        .card-title {
            font-family: 'Oswald', sans-serif; font-size: 1.9rem; font-weight: 800; color: #fff; margin: 0; line-height: 1.2; text-shadow: 0 2px 10px rgba(0,0,0,0.8);
        }
        .card-desc {
            font-size: 0.88rem; font-weight: 400; color: #94a3b8; margin-top: 6px; text-shadow: 0 1px 5px rgba(0,0,0,0.8); opacity: 0.85; transition: 0.3s;
        }
        .mode-card:hover .card-desc { opacity: 1; color: #e2e8f0; }

        /* Play CTA okı */
        .card-cta {
            display: inline-flex; align-items: center; gap: 6px;
            margin-top: 12px; font-size: 0.8rem; font-weight: 700; letter-spacing: 1.5px;
            text-transform: uppercase; opacity: 0; transform: translateY(8px); transition: 0.35s;
        }
        .mode-card:hover .card-cta { opacity: 1; transform: translateY(0); }
        .mode-card.tr .card-cta { color: #e11d48; }
        .mode-card.pl .card-cta { color: #a855f7; }
        .mode-card.cl .card-cta { color: #00e5ff; }
        .mode-card.gl .card-cta { color: #d4af37; }
        .mode-card.es .card-cta { color: #f59e0b; }
        .mode-card.de .card-cta { color: #ef4444; }
        .mode-card.it .card-cta { color: #10b981; }

        /* Alt Futbol Bilgisi */
        .footer-note { text-align: center; margin-top: 60px; color: rgba(255,255,255,0.3); font-size: 0.85rem; letter-spacing: 1px; padding-bottom: 20px; }

        /* Yakında Gelecek Rozeti */
        .coming-soon-badge {
            position: absolute; top: 15px; right: 15px; z-index: 3;
            background: rgba(0,0,0,0.7); color: rgba(255,255,255,0.6);
            font-size: 0.65rem; font-weight: 800; padding: 4px 10px;
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.2);
            text-transform: uppercase; letter-spacing: 1px;
        }

        /* --- DASHBOARD PANEL --- */
        .dashboard-panel { max-width: 1100px; margin: 0 auto 32px; padding: 0 30px; }
        .dashboard-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); border-radius: 22px; backdrop-filter: blur(20px); padding: 28px 32px; }
        .dash-team-name { font-size: 1.8rem; font-weight: 900; margin: 0; }
        .dash-stat { display: flex; flex-direction: column; align-items: center; text-align: center; }
        .dash-stat-val { font-size: 2rem; font-weight: 900; color: #d4af37; font-family: 'Oswald', sans-serif; }
        .dash-stat-lbl { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 2px; }
        .dash-next-match { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 14px 20px; margin-top: 20px; display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
        .dash-badge { font-size: 0.72rem; font-weight: 700; padding: 3px 12px; border-radius: 20px; }
        .dash-badge.ucl { background: rgba(0,229,255,0.15); color: #00e5ff; border: 1px solid rgba(0,229,255,0.3); }
        .dash-badge.uel { background: rgba(240,78,35,0.15); color: #f04e23; border: 1px solid rgba(240,78,35,0.3); }
        .dash-badge.uecl { background: rgba(46,204,113,0.15); color: #2ecc71; border: 1px solid rgba(46,204,113,0.3); }
        .btn-play-week { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg,#1e3a5f,#2563eb); color: #fff; border: none; border-radius: 12px; padding: 12px 28px; font-size: 1rem; font-weight: 800; font-family: 'Oswald', sans-serif; letter-spacing: 1px; text-decoration: none; text-transform: uppercase; box-shadow: 0 4px 20px rgba(37,99,235,0.4); transition: all .2s; cursor: pointer; }
        .btn-play-week:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(37,99,235,0.6); color: #fff; }
        .btn-sim-season { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg,#7f1d1d,#dc2626); color: #fff; border: none; border-radius: 12px; padding: 12px 28px; font-size: 1rem; font-weight: 800; font-family: 'Oswald', sans-serif; letter-spacing: 1px; text-decoration: none; text-transform: uppercase; box-shadow: 0 4px 20px rgba(220,38,38,0.4); transition: all .2s; cursor: pointer; }
        .btn-sim-season:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(220,38,38,0.6); color: #fff; }
        .team-select-section { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 20px; }
        .team-select-section select { background: rgba(255,255,255,0.07); color: #fff; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 10px 16px; font-size: 0.95rem; width: 100%; }
        .team-select-section select option { background: #1a1a2e; color: #fff; }

    </style>
</head>
<body>

    <div class="bg-image"></div>
    <div class="bg-overlay"></div>

    <div class="modern-nav">
        <a href="index.php" class="nav-brand font-oswald">
            <i class="fa-solid fa-chess-knight"></i> 
            ULTIMATE <span class="gold-text">MANAGER</span>
        </a>
    </div>

    <div class="hero-section">
        <h1 class="hero-title font-oswald">SAHNEYE ÇIKMA VAKTİ</h1>
        <p class="hero-subtitle">Dünyanın en iyi futbol arenasında kulübünün kaderini belirle.</p>
    </div>

    <!-- ======================= DASHBOARD PANEL ======================= -->
    <div class="dashboard-panel">
        <?php if ($kullanici_takim): ?>
        <div class="dashboard-card">
            <div class="row align-items-center g-3 mb-3">
                <div class="col-auto">
                    <?php if (!empty($kullanici_takim['logo'])): ?>
                    <img src="<?= htmlspecialchars($kullanici_takim['logo']) ?>" alt="logo"
                         style="width:60px;height:60px;object-fit:contain;filter:drop-shadow(0 0 8px rgba(255,255,255,0.3));"
                         onerror="this.style.display='none'">
                    <?php else: ?>
                    <i class="fa-solid fa-shield-halved" style="font-size:3rem;color:#d4af37;"></i>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <div class="dash-team-name"><?= htmlspecialchars($kullanici_takim['takim_adi']) ?></div>
                    <div style="color:#94a3b8;font-size:0.85rem;margin-top:4px;">
                        <?= htmlspecialchars($kullanici_takim['sezon_yil'] ?? '') ?> Sezonu
                        <?php if ($avrupa_durum): ?>
                        &bull;
                        <span class="dash-badge <?= strtolower($avrupa_durum['tur']) ?>">
                            <?= htmlspecialchars($avrupa_durum['tur']) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-auto">
                    <form method="POST" style="margin:0;">
                        <?php if (!empty($tum_takimlar)):
                            $gruplar_dash = [];
                            foreach ($tum_takimlar as $t) { $gruplar_dash[$t['lig_adi']][] = $t; }
                            $mevcut_val = ($kullanici_tablo ?? 'takimlar') . ':' . $kullanici_takim_id;
                        ?>
                        <select name="takim_id" style="background:rgba(255,255,255,0.07);color:#fff;border:1px solid rgba(255,255,255,0.15);border-radius:10px;padding:6px 12px;font-size:0.85rem;">
                            <?php foreach ($gruplar_dash as $lig => $takimlar): ?>
                            <optgroup label="<?= htmlspecialchars($lig) ?>" style="background:#1a1a2e;color:#94a3b8;">
                                <?php foreach ($takimlar as $t):
                                    $val = htmlspecialchars(($t['tablo'] ?? 'takimlar') . ':' . $t['id']);
                                ?>
                                <option value="<?= $val ?>" <?= $val == $mevcut_val ? 'selected' : '' ?> style="background:#1a1a2e;color:#fff;">
                                    <?= htmlspecialchars($t['takim_adi']) ?>
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="takim_sec" style="background:rgba(212,175,55,0.15);color:#d4af37;border:1px solid rgba(212,175,55,0.3);border-radius:8px;padding:6px 14px;font-size:0.82rem;cursor:pointer;margin-left:6px;">Değiştir</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- Stat Chips -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><div class="dash-stat"><div class="dash-stat-val"><?= $lig_siralama ? $lig_siralama . '.' : '—' ?></div><div class="dash-stat-lbl">Lig Sırası</div></div></div>
                <div class="col-6 col-md-3"><div class="dash-stat"><div class="dash-stat-val"><?= (int)($kullanici_takim['puan'] ?? 0) ?></div><div class="dash-stat-lbl">Puan</div></div></div>
                <div class="col-6 col-md-3"><div class="dash-stat"><div class="dash-stat-val"><?= number_format($transfer_butce / 1e6, 1) ?>M€</div><div class="dash-stat-lbl">Transfer Bütçesi</div></div></div>
                <div class="col-6 col-md-3"><div class="dash-stat"><div class="dash-stat-val"><?= htmlspecialchars($kullanici_takim['hafta'] ?? '—') ?></div><div class="dash-stat-lbl">Mevcut Hafta</div></div></div>
            </div>
            <!-- Sonraki Maç -->
            <?php if ($sonraki_mac): ?>
            <div class="dash-next-match">
                <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;white-space:nowrap;">Sonraki Maç</div>
                <div style="font-weight:700;font-size:1.05rem;">
                    <?= htmlspecialchars($sonraki_mac['ev_ad']) ?>
                    <span style="color:#d4af37;margin:0 8px;">VS</span>
                    <?= htmlspecialchars($sonraki_mac['dep_ad']) ?>
                </div>
                <div style="color:#94a3b8;font-size:0.8rem;">Hafta <?= $sonraki_mac['hafta'] ?></div>
            </div>
            <?php endif; ?>
            <!-- Aksiyon Butonları -->
            <div class="d-flex gap-3 flex-wrap mt-4">
                <a href="play_week.php" class="btn-play-week">
                    <i class="fa-solid fa-play"></i> GLOBAL HAFTAYI OYNA
                </a>
                <form method="POST" action="play_week.php" style="margin:0;" onsubmit="return confirm('Tüm sezon simüle edilecek! Devam?');">
                    <button type="submit" name="tum_sezonu_simule" class="btn-sim-season">
                        <i class="fa-solid fa-forward-fast"></i> TÜM SEZONU SİMÜLE ET
                    </button>
                </form>
                <a href="super_lig/superlig.php" class="btn-play-week" style="background:linear-gradient(135deg,#7f1d48,#e11d48);">
                    <i class="fa-solid fa-futbol"></i> KENDİ MAÇIMI OYNA
                </a>
            </div>
        </div>
        <?php else: ?>
        <!-- KARŞILAMA VE TAKIM SEÇİMİ -->
        <div class="dashboard-card text-center">
            <div style="font-size:3rem;margin-bottom:12px;">⚽</div>
            <h2 class="font-oswald" style="font-size:2rem;margin-bottom:8px;">HOŞGELDİNİZ!</h2>
            <p style="color:#94a3b8;margin-bottom:6px;font-size:1rem;max-width:600px;margin-left:auto;margin-right:auto;">
                Yönetmek istediğiniz takımı seçin ve tüm sezon boyunca sadece onu yönetin.
            </p>
            <p style="color:#64748b;font-size:0.85rem;margin-bottom:24px;">Süper Lig, Premier League, La Liga, Bundesliga, Serie A, Ligue 1 veya Liga NOS'tan bir takım seçebilirsiniz.</p>
            <?php if (!empty($tum_takimlar)):
                // Liglere göre grupla
                $gruplar = [];
                foreach ($tum_takimlar as $t) {
                    $gruplar[$t['lig_adi']][] = $t;
                }
            ?>
            <form method="POST" class="team-select-section mx-auto" style="max-width:500px;">
                <select name="takim_id" class="mb-3" required>
                    <option value="">— Takımınızı Seçin —</option>
                    <?php foreach ($gruplar as $lig => $takimlar): ?>
                    <optgroup label="🏆 <?= htmlspecialchars($lig) ?>">
                        <?php foreach ($takimlar as $t): ?>
                        <option value="<?= htmlspecialchars(($t['tablo'] ?? 'takimlar') . ':' . $t['id']) ?>">
                            <?= htmlspecialchars($t['takim_adi']) ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="takim_sec" class="btn-play-week w-100 justify-content-center" style="margin-top:12px;">
                    <i class="fa-solid fa-check"></i> TAKIM SEÇ VE BAŞLA
                </button>
            </form>
            <?php else: ?>
            <p style="color:#94a3b8;margin-bottom:20px;">Önce en az bir ligi kurmanız gerekiyor.</p>
            <a href="super_lig/sl_kurulum.php" class="btn-play-week">
                <i class="fa-solid fa-database me-2"></i>Süper Lig'i Kur
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <!-- ======================= /DASHBOARD PANEL ====================== -->

    <?php if (!empty($sampiyonlar)): ?>
    <!-- ====== GEÇEN SEZON ŞAMPİYONLARI ====== -->
    <div style="max-width:1400px;margin:0 auto 20px;padding:0 30px;">
        <div style="background:rgba(255,255,255,0.04);border:1px solid rgba(212,175,55,0.2);border-radius:18px;padding:20px 28px;">
            <div style="font-size:0.75rem;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;margin-bottom:14px;font-weight:700;">
                <i class="fa-solid fa-crown" style="color:#d4af37;margin-right:6px;"></i> Geçen Sezon Şampiyonları
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:14px;">
                <?php foreach($sampiyonlar as $lig => $sampiyon): ?>
                <div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:10px 16px;display:flex;align-items:center;gap:10px;min-width:200px;">
                    <i class="fa-solid fa-trophy" style="color:#d4af37;font-size:1.1rem;"></i>
                    <div>
                        <div style="font-size:0.65rem;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;"><?= htmlspecialchars($lig) ?></div>
                        <div style="font-weight:800;color:#fff;font-size:0.9rem;"><?= htmlspecialchars($sampiyon) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="game-grid">
            
            <a href="super_lig/superlig.php" class="mode-card tr">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/S%C3%BCper_Lig_logo.svg/200px-S%C3%BCper_Lig_logo.svg.png"
                             alt="Süper Lig Logo"
                             onerror="logoFallback(this,'fa-solid fa-moon','#e11d48')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SÜPER LİG</h2>
                    <div class="card-desc">Türkiye'nin en iyisi olmak için kıyasıya rekabet et.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="premier_lig/premier_lig.php" class="mode-card pl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/f/f2/Premier_League_Logo.svg/200px-Premier_League_Logo.svg.png"
                             alt="Premier League Logo"
                             onerror="logoFallback(this,'fa-solid fa-crown','#a855f7')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">PREMIER LEAGUE</h2>
                    <div class="card-desc">Ada futbolunun sert ve yüksek bütçeli dünyasına gir.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="champions_league/cl.php" class="mode-card cl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/b/bf/UEFA_Champions_League_logo_2.svg/200px-UEFA_Champions_League_logo_2.svg.png"
                             alt="Champions League Logo"
                             onerror="logoFallback(this,'fa-solid fa-trophy','#00e5ff')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">CHAMPIONS LEAGUE</h2>
                    <div class="card-desc">Avrupa devleriyle şampiyonluk ve ülke puanı için savaş.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="global_transfer.php" class="mode-card gl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-globe" style="font-size:3rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">GLOBAL PAZAR</h2>
                    <div class="card-desc">Tüm dünya yıldızlarını tara ve kulübüne transfer et.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> GİT</div>
                </div>
            </a>

            <a href="la_liga/la_liga.php" class="mode-card es">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/a/a7/LaLiga_logo_2023.svg/200px-LaLiga_logo_2023.svg.png"
                             alt="La Liga Logo"
                             onerror="logoFallback(this,'fa-solid fa-sun','#f59e0b')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LA LIGA</h2>
                    <div class="card-desc">İspanya'nın zirvesine ulaş. Klasik derbiler seni bekliyor.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="bundesliga/bundesliga.php" class="mode-card de">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/d/df/Bundesliga_logo_%282017%29.svg/200px-Bundesliga_logo_%282017%29.svg.png"
                             alt="Bundesliga Logo"
                             onerror="logoFallback(this,'fa-solid fa-bolt','#ef4444')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">BUNDESLIGA</h2>
                    <div class="card-desc">Alman futbolunun gücü ve disiplini ile şampiyonluğa yürü.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="ligue_1/ligue1.php" class="mode-card fr">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/b/b1/Ligue_1_Uber_Eats_logo.svg/200px-Ligue_1_Uber_Eats_logo.svg.png"
                             alt="Ligue 1 Logo"
                             onerror="logoFallback(this,'fa-solid fa-tower-observation','#3b82f6')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LIGUE 1</h2>
                    <div class="card-desc">Fransa'nın elit sahalarında stil ve zarafeti birleştir.</div>
                    <div class="card-cta" style="color:#3b82f6;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="serie_a/serie_a.php" class="mode-card it">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/e/e9/Serie_A_logo_2022.svg/200px-Serie_A_logo_2022.svg.png"
                             alt="Serie A Logo"
                             onerror="logoFallback(this,'fa-solid fa-shield-halved','#10b981')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SERIE A</h2>
                    <div class="card-desc">İtalya'nın taktiksel derin futbolunda bir efsane ol.</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="liga_nos/liga_nos.php" class="mode-card pt">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-star" style="font-size:3rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">LIGA NOS</h2>
                    <div class="card-desc">Portekiz'in yeteneğini keşfet ve Avrupa'ya taşı.</div>
                    <div class="card-cta" style="color:#8b5cf6;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- YERLİ KUPALAR -->
            <a href="coupe_de_france/coupe_de_france.php" class="mode-card" style="--card-color:#003f8a;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-shield-halved" style="font-size:3rem; color:#003f8a;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">COUPE DE FRANCE</h2>
                    <div class="card-desc">Fransa'nın ulusal kupasında eleme usulü şampiyonluk mücadelesi.</div>
                    <div class="card-cta" style="color:#3b82f6;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <a href="taca_de_portugal/taca_de_portugal.php" class="mode-card" style="--card-color:#006600;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-shield-halved" style="font-size:3rem; color:#006600;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">TAÇA DE PORTUGAL</h2>
                    <div class="card-desc">Portekiz'in ulusal kupasında efsanevi kulüplerle karşılaş.</div>
                    <div class="card-cta" style="color:#4ade80;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: AVRUPA LİGİ -->
            <a href="uel/uel.php" class="mode-card" style="--card-color:#f04e23;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/3/35/UEFA_Europa_League_logo_%282.0%29.svg/200px-UEFA_Europa_League_logo_%282.0%29.svg.png"
                             alt="Europa League Logo"
                             onerror="logoFallback(this,'fa-solid fa-fire','#f04e23')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">EUROPA LEAGUE</h2>
                    <div class="card-desc">5. ve 6. sıra takımların Perşembe gecesi yarışması.</div>
                    <div class="card-cta" style="color:#f04e23;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: KONFERANS LİGİ -->
            <a href="uecl/uecl.php" class="mode-card" style="--card-color:#2ecc71;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <img src="https://upload.wikimedia.org/wikipedia/en/thumb/4/45/UEFA_Europa_Conference_League_logo.svg/200px-UEFA_Europa_Conference_League_logo.svg.png"
                             alt="Conference League Logo"
                             onerror="logoFallback(this,'fa-solid fa-earth-europe','#2ecc71')">
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">CONFERENCE LEAGUE</h2>
                    <div class="card-desc">7. ve 8. sıraların Avrupa serüveni başlıyor.</div>
                    <div class="card-cta" style="color:#2ecc71;"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- FAZ 1: UEFA SÜPER KUPA -->
            <a href="super_cup/super_cup.php" class="mode-card gl">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-trophy" style="font-size:3rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SÜPER KUPA</h2>
                    <div class="card-desc">UCL şampiyonu × UEL şampiyonu — Sezonun açılış maçı!</div>
                    <div class="card-cta"><i class="fa-solid fa-play"></i> OYNA</div>
                </div>
            </a>

            <!-- GLOBAL HAFTA OYNAT -->
            <a href="play_week.php" class="mode-card" style="--card-color:#2563eb;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-forward-fast" style="font-size:2.8rem; color:#2563eb;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">GLOBAL HAFTA OYNAT</h2>
                    <div class="card-desc">Tüm ligler aynı anda ilerler. Sezonun tamamını hızla simüle et!</div>
                    <div class="card-cta" style="color:#2563eb;"><i class="fa-solid fa-play"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 1: GLOBAL TAKVİM -->
            <a href="takvim.php" class="mode-card" style="--card-color:#94a3b8;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-calendar-days" style="font-size:2.8rem; color:#94a3b8;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">GLOBAL TAKVİM</h2>
                    <div class="card-desc">Tüm liglerin ve Avrupa kupalarının haftalık özeti.</div>
                    <div class="card-cta" style="color:#94a3b8;"><i class="fa-solid fa-arrow-right"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: DEADLINE DAY -->
            <a href="deadline_day.php" class="mode-card" style="--card-color:#ef4444;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-clock-rotate-left" style="font-size:2.8rem; color:#ef4444;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">DEADLINE DAY</h2>
                    <div class="card-desc">Transfer penceresinin son 24 saati! Panik tekliflerini yönet.</div>
                    <div class="card-cta" style="color:#ef4444;"><i class="fa-solid fa-bolt"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: SCOUT AĞI -->
            <a href="scout.php" class="mode-card" style="--card-color:#10b981;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-binoculars" style="font-size:2.8rem; color:#10b981;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SCOUT AĞI</h2>
                    <div class="card-desc">Brezilya, Afrika, Balkanlar'a gözlemci gönder. Wonderkid keşfet!</div>
                    <div class="card-cta" style="color:#10b981;"><i class="fa-solid fa-plane-departure"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: BOSMAN / SERBEST OYUNCULAR -->
            <a href="serbest_oyuncular.php" class="mode-card" style="--card-color:#8b5cf6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-handshake" style="font-size:2.8rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">SERBEST OYUNCULAR</h2>
                    <div class="card-desc">Sözleşmesi biten yıldızları bonservis ödemeden kadrana kat!</div>
                    <div class="card-cta" style="color:#8b5cf6;"><i class="fa-solid fa-file-contract"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 4: KİRALIK SİSTEMİ -->
            <a href="kiralik.php" class="mode-card" style="--card-color:#3b82f6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-arrows-rotate" style="font-size:2.8rem; color:#3b82f6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">KİRALIK SİSTEMİ</h2>
                    <div class="card-desc">Genç oyuncuları kiralık gönder, maaş kazan, gelişimlerini hızlandır.</div>
                    <div class="card-cta" style="color:#3b82f6;"><i class="fa-solid fa-arrow-right-arrow-left"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 5: CEO KULÜP YÖNETİMİ -->
            <a href="kulup_yonetimi.php" class="mode-card" style="--card-color:#d4af37;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-building-columns" style="font-size:2.8rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">CEO YÖNETİMİ</h2>
                    <div class="card-desc">FFP, bilet fiyatı, sponsorluk seçimi ve forma satışlarını yönet!</div>
                    <div class="card-cta" style="color:#d4af37;"><i class="fa-solid fa-chart-pie"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 5: MENAJER KARİYER -->
            <a href="menajer_kariyer.php" class="mode-card" style="--card-color:#8b5cf6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-user-tie" style="font-size:2.8rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">MENAJER KARİYER</h2>
                    <div class="card-desc">Yönetim güvenini koru, kovulmaktan kaç veya yeni takımdan teklif al!</div>
                    <div class="card-cta" style="color:#8b5cf6;"><i class="fa-solid fa-briefcase"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 6: BALLON D'OR -->
            <a href="ballon_dor.php" class="mode-card" style="--card-color:#d4af37;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-award" style="font-size:2.8rem; color:#d4af37;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">BALLON D'OR</h2>
                    <div class="card-desc">Avrupa'nın en iyi oyuncusunu seç. Tüm ligler karşılaştırılır!</div>
                    <div class="card-cta" style="color:#d4af37;"><i class="fa-solid fa-trophy"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 6: ALTIN AYAKKABI -->
            <a href="altin_ayakkabi.php" class="mode-card" style="--card-color:#10b981;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-shoe-prints" style="font-size:2.8rem; color:#10b981;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">ALTIN AYAKKABI</h2>
                    <div class="card-desc">Tüm Avrupa'nın golcü krallığı — Lig katsayısıyla ağırlıklı sıralama!</div>
                    <div class="card-cta" style="color:#10b981;"><i class="fa-solid fa-futbol"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 6: ŞÖHRETLER MÜZESİ -->
            <a href="hall_of_fame.php" class="mode-card" style="--card-color:#8b5cf6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-monument" style="font-size:2.8rem; color:#8b5cf6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">ŞÖHRETLER MÜZESİ</h2>
                    <div class="card-desc">200+ maç veya 100+ gol yapan efsaneleri kulüp tarihine kazı!</div>
                    <div class="card-cta" style="color:#8b5cf6;"><i class="fa-solid fa-star"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 6: TOTW & TOTS -->
            <a href="totw.php" class="mode-card" style="--card-color:#3b82f6;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-users" style="font-size:2.8rem; color:#3b82f6;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">HAFTANIN & SEZONUN 11'İ</h2>
                    <div class="card-desc">TOTW & TOTS — Maç puanlarıyla en iyi 11'i seç ve kaydet!</div>
                    <div class="card-cta" style="color:#3b82f6;"><i class="fa-solid fa-wand-magic-sparkles"></i> GİT</div>
                </div>
            </a>

            <!-- FAZ 6: DÜNYA KUPASI -->
            <a href="dunya_kupasi.php" class="mode-card" style="--card-color:#06b6d4;">
                <div class="card-logo-wrapper">
                    <div class="card-logo-bg">
                        <i class="fa-solid fa-earth-americas" style="font-size:2.8rem; color:#06b6d4;"></i>
                    </div>
                </div>
                <div class="card-content">
                    <h2 class="card-title">DÜNYA KUPASI</h2>
                    <div class="card-desc">Her 4 sezonda bir! Milli takımını yönet ve Dünya Kupası'nı kazan!</div>
                    <div class="card-cta" style="color:#06b6d4;"><i class="fa-solid fa-globe"></i> GİT</div>
                </div>
            </a>

        </div>
    </div>

    <div class="footer-note font-oswald">
        V6.0.0 PHASE 6 — GÖRKEMLİ ÖDÜLLER AKTİF • BALLON D'OR / ALTIN AYAKKABI / HALL OF FAME / TOTW / DÜNYA KUPASI
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function logoFallback(img, iconClass, color) {
            img.style.display = 'none';
            var el = document.createElement('i');
            el.className = iconClass;
            el.style.fontSize = '2.8rem';
            el.style.color = color;
            img.parentElement.appendChild(el);
        }
    </script>
</body>
</html>