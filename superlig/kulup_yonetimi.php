<?php
// ==============================================================================
// FAZ 5: CEO KULÜP YÖNETİMİ (Kulüp Yönetimi ve Finansal Strateji)
// Özellikler: FFP, Bilet Fiyatlandırma, Sponsorluk Seçimi, Forma Satışları
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

// --- Tablo Haritalaması ---
$tbl_takim = [
    'tr' => 'takimlar',    'cl' => 'cl_takimlar', 'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar', 'fr' => 'fr_takimlar',
    'it' => 'it_takimlar', 'pt' => 'pt_takimlar',
];
$tbl_oyuncu = [
    'tr' => 'oyuncular',    'cl' => 'cl_oyuncular', 'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular', 'de' => 'de_oyuncular', 'fr' => 'fr_oyuncular',
    'it' => 'it_oyuncular', 'pt' => 'pt_oyuncular',
];
$ayar_tablosu = [
    'tr' => 'ayar',    'pl' => 'pl_ayar',   'es' => 'es_ayar',
    'de' => 'de_ayar', 'it' => 'it_ayar',   'cl' => 'cl_ayar',
    'fr' => 'fr_ayar', 'pt' => 'pt_ayar',
];
$gecerli_liglar = array_keys($tbl_takim);

// --- Kullanıcı takımını çek ---
function fetch_kullanici_ceo($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    try {
        $ayar_stmt = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1");
        $ayar_id   = $ayar_stmt ? $ayar_stmt->fetchColumn() : false;
        if (!$ayar_id) return null;
        $stmt = $pdo->prepare("SELECT * FROM $takim_tablo WHERE id = ?");
        $stmt->execute([$ayar_id]);
        $takim = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($takim) { $takim['kaynak'] = $lig_kodu; return $takim; }
    } catch (Throwable $e) {}
    return null;
}

$benim_takimlarim = [];
foreach ($ayar_tablosu as $kod => $ayar_tbl) {
    $t = fetch_kullanici_ceo($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
    if ($t) $benim_takimlarim[] = $t;
}

$guncel_hafta = 1;
$guncel_sezon = 2025;
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// Aktif takım seçimi
$aktif_lig  = $_GET['lig']  ?? ($benim_takimlarim[0]['kaynak'] ?? 'tr');
if (!in_array($aktif_lig, $gecerli_liglar, true)) $aktif_lig = 'tr';
$aktif_takim = null;
foreach ($benim_takimlarim as $t) { if ($t['kaynak'] === $aktif_lig) { $aktif_takim = $t; break; } }
if (!$aktif_takim && !empty($benim_takimlarim)) { $aktif_takim = $benim_takimlarim[0]; $aktif_lig = $aktif_takim['kaynak']; }

// --- FFP EŞİĞİ ---
// Bakiye -15M€ altı: transfer yasağı | -30M€ altı: puan silme (3 puan)
define('FFP_ESIK_YASAK',    -15000000);
define('FFP_ESIK_PUAN',     -30000000);

// ==============================================================================
// EYLEMLER (POST Handler)
// ==============================================================================

// 1. BİLET FİYATI KAYDET
if (isset($_POST['bilet_kaydet']) && $aktif_takim) {
    $fiyat = max(10, min(5000, (int)($_POST['bilet_fiyati'] ?? 200)));
    $takim_id  = (int)$aktif_takim['id'];
    $tbl = $tbl_takim[$aktif_lig];

    // Doluluk ve atmosfer hesapla (düşük fiyat = yüksek doluluk)
    // 10 TL → %100, 200 TL → %80, 500 TL → %55, 1000+ TL → %30
    if ($fiyat <= 50)         { $doluluk = 100; $atmosfer = 1.25; }
    elseif ($fiyat <= 150)    { $doluluk = 92;  $atmosfer = 1.15; }
    elseif ($fiyat <= 300)    { $doluluk = 80;  $atmosfer = 1.00; }
    elseif ($fiyat <= 600)    { $doluluk = 62;  $atmosfer = 0.90; }
    elseif ($fiyat <= 1000)   { $doluluk = 45;  $atmosfer = 0.80; }
    else                       { $doluluk = 28;  $atmosfer = 0.70; }

    try {
        // takimlar tablosunu güncelle (sütunlar varsa)
        $pdo->prepare("UPDATE $tbl SET bilet_fiyati = ? WHERE id = ?")->execute([$fiyat, $takim_id]);

        // stadyum_ayar tablosunu güncelle/oluştur
        $pdo->prepare("INSERT INTO stadyum_ayar
            (takim_id, takim_lig, bilet_fiyati, beklenen_doluluk, atmosfer_carpani)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            bilet_fiyati = VALUES(bilet_fiyati),
            beklenen_doluluk = VALUES(beklenen_doluluk),
            atmosfer_carpani = VALUES(atmosfer_carpani)"
        )->execute([$takim_id, $aktif_lig, $fiyat, $doluluk, $atmosfer]);

        $mesaj = "Bilet fiyatı ₺$fiyat olarak ayarlandı. Beklenen doluluk: %$doluluk | Atmosfer çarpanı: {$atmosfer}x";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj = "Bilet fiyatı kaydedilirken hata: " . $e->getMessage(); $mesaj_tipi = "danger";
    }
}

// 2. SPONSOR SEÇ
if (isset($_POST['sponsor_sec']) && $aktif_takim) {
    $secilen_tip = $_POST['sponsor_tip'] ?? 'A';
    if (!in_array($secilen_tip, ['A','B','C'], true)) $secilen_tip = 'A';
    $takim_id = (int)$aktif_takim['id'];
    $tbl = $tbl_takim[$aktif_lig];

    // Önce tüm mevcut aktif sponsorları pasifleştir
    try {
        $pdo->prepare("UPDATE kulup_sponsor SET aktif = 0
            WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ?"
        )->execute([$takim_id, $aktif_lig, $guncel_sezon]);
    } catch (Throwable $e) {}

    // Teklifleri oluştur (ya da tekrar seç)
    $teklifler = _sponsor_teklifleri_olustur($takim_id, $aktif_lig, $guncel_sezon);
    $secilen   = $teklifler[$secilen_tip] ?? $teklifler['A'];

    try {
        $pdo->prepare("INSERT INTO kulup_sponsor
            (takim_id, takim_lig, sezon_yil, sponsor_adi, teklif_tipi, garantili_odeme, sampiyon_bonusu, ucl_sf_bonusu, aktif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
            sponsor_adi = VALUES(sponsor_adi),
            garantili_odeme = VALUES(garantili_odeme),
            sampiyon_bonusu = VALUES(sampiyon_bonusu),
            ucl_sf_bonusu   = VALUES(ucl_sf_bonusu),
            aktif = 1"
        )->execute([
            $takim_id, $aktif_lig, $guncel_sezon,
            $secilen['adi'], $secilen_tip,
            $secilen['garanti'], $secilen['sampiyon'], $secilen['ucl_sf']
        ]);

        // Garantili ödemeyi kasaya ekle
        if (!isset($secilen['garantili_odendi']) || !$secilen['garantili_odendi']) {
            $pdo->prepare("UPDATE $tbl SET butce = butce + ? WHERE id = ?")->execute([$secilen['garanti'], $takim_id]);
            $pdo->prepare("UPDATE kulup_sponsor SET garantili_odendi = 1
                WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ? AND teklif_tipi = ?"
            )->execute([$takim_id, $aktif_lig, $guncel_sezon, $secilen_tip]);
        }

        $garanti_fmt = number_format($secilen['garanti'] / 1000000, 1) . 'M';
        $mesaj = "✅ {$secilen['adi']} sponsorluğu aktif! Garantili ödeme: €{$garanti_fmt} kasaya eklendi.";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj = "Sponsor seçiminde hata: " . $e->getMessage(); $mesaj_tipi = "danger";
    }
}

// 3. HAFTALIK FORMA GELİRİ HESAPLA & KASAYA EKLE
if (isset($_POST['forma_geliri_hesapla']) && $aktif_takim) {
    $takim_id  = (int)$aktif_takim['id'];
    $tbl       = $tbl_takim[$aktif_lig];
    $tbl_oy    = $tbl_oyuncu[$aktif_lig];

    try {
        // Ortalama OVR hesapla
        $ort_ovr = (int)$pdo->query("SELECT COALESCE(AVG(ovr),70) FROM $tbl_oy WHERE takim_id = $takim_id")->fetchColumn();
        // Süperstar (90+ OVR) var mı?
        $superstar_sayisi = (int)$pdo->query("SELECT COUNT(*) FROM $tbl_oy WHERE takim_id = $takim_id AND ovr >= 90")->fetchColumn();

        // Haftalık gelir hesaplama formülü
        $base      = 50000 + ($ort_ovr - 60) * 8000;      // OVR 70 → 130K, OVR 85 → 250K
        $superstar_bonus = $superstar_sayisi * 200000;      // Her süperstar için +200K
        $haftalik_gelir  = max(10000, $base + $superstar_bonus);

        $pdo->prepare("UPDATE $tbl SET butce = butce + ? WHERE id = ?")->execute([$haftalik_gelir, $takim_id]);
        $pdo->prepare("INSERT INTO forma_satis_log
            (takim_id, takim_lig, sezon_yil, hafta, gelir, aciklama)
            VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$takim_id, $aktif_lig, $guncel_sezon, $guncel_hafta, $haftalik_gelir,
            "Haftalık forma/merch geliri (OVR ortalaması: {$ort_ovr}, Süperstar: {$superstar_sayisi})"]);

        $gelir_fmt = number_format($haftalik_gelir / 1000, 0, ',', '.') . 'K';
        $mesaj     = "👕 Forma satış geliri €{$gelir_fmt} kasaya eklendi! (OVR ort.: {$ort_ovr} | {$superstar_sayisi} süperstar)";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj = "Forma geliri hesaplanırken hata: " . $e->getMessage(); $mesaj_tipi = "danger";
    }
}

// 4. FFP KONTROL & CEZA UYGULA
if (isset($_POST['ffp_kontrol']) && $aktif_takim) {
    $takim_id = (int)$aktif_takim['id'];
    $tbl      = $tbl_takim[$aktif_lig];

    try {
        $butce      = (int)$pdo->query("SELECT butce FROM $tbl WHERE id = $takim_id")->fetchColumn();
        $ffp_bakiye = $butce; // Basit versiyon: anlık bakiyeyi kullan

        $ceza = 'yok'; $puan_silme = 0; $transfer_yasagi = 0;
        if ($ffp_bakiye < FFP_ESIK_PUAN) {
            $ceza = 'puan_silme'; $puan_silme = 3; $transfer_yasagi = 1;
            // Puan düşürme: sadece takimlar tablosunda puan sütunu varsa
            try { $pdo->prepare("UPDATE $tbl SET puan = GREATEST(0, puan - 3) WHERE id = ?")->execute([$takim_id]); } catch (Throwable $e2) {}
        } elseif ($ffp_bakiye < FFP_ESIK_YASAK) {
            $ceza = 'transfer_yasagi'; $transfer_yasagi = 1;
        }

        $pdo->prepare("UPDATE $tbl SET ffp_ceza = ?, transfer_yasagi = ? WHERE id = ?"
        )->execute([$ceza, $transfer_yasagi, $takim_id]);

        $pdo->prepare("INSERT INTO kulup_finans
            (takim_id, takim_lig, sezon_yil, ffp_bakiye, ffp_ceza, puan_silme_miktari)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            ffp_bakiye = VALUES(ffp_bakiye),
            ffp_ceza   = VALUES(ffp_ceza),
            puan_silme_miktari = VALUES(puan_silme_miktari)"
        )->execute([$takim_id, $aktif_lig, $guncel_sezon, $ffp_bakiye, $ceza, $puan_silme]);

        if ($ceza === 'puan_silme') {
            $mesaj = "⚠️ FFP İHLALİ KRİTİK! Transfer yasağı + 3 puan silme cezası uygulandı! Acil önlem alın.";
            $mesaj_tipi = "danger";
        } elseif ($ceza === 'transfer_yasagi') {
            $mesaj = "⚠️ FFP Uyarısı: Transfer yasağı uygulandı. Bakiye eşiği: " . number_format(FFP_ESIK_YASAK / 1000000, 0) . "M€";
            $mesaj_tipi = "warning";
        } else {
            $mesaj = "✅ FFP kontrolü tamamlandı. Kulüp mali durumu sağlıklı!";
            $mesaj_tipi = "success";
        }
    } catch (Throwable $e) {
        $mesaj = "FFP kontrolü sırasında hata: " . $e->getMessage(); $mesaj_tipi = "danger";
    }
}

// ==============================================================================
// YARDIMCI FONKSIYONLAR
// ==============================================================================

function _sponsor_teklifleri_olustur($takim_id, $lig, $sezon) {
    return [
        'A' => [
            'adi'       => 'TechGlobal Bank',
            'aciklama'  => 'Yüksek garantili, performans bonusu yok.',
            'garanti'   => 12000000,
            'sampiyon'  => 0,
            'ucl_sf'    => 0,
        ],
        'B' => [
            'adi'       => 'SkyBet Sports',
            'aciklama'  => 'Düşük garanti ama şampiyonluk & UCL yarı final büyük bonusu!',
            'garanti'   => 3000000,
            'sampiyon'  => 25000000,
            'ucl_sf'    => 15000000,
        ],
        'C' => [
            'adi'       => 'EuroMart',
            'aciklama'  => 'Orta garanti + küçük şampiyonluk bonusu.',
            'garanti'   => 7000000,
            'sampiyon'  => 8000000,
            'ucl_sf'    => 5000000,
        ],
    ];
}

// --- Veri Çekme ---
$aktif_sponsor     = null;
$stadyum_bilgi     = null;
$ffp_bilgi         = null;
$forma_son_loglar  = [];
$sponsor_teklifleri = [];

if ($aktif_takim) {
    $takim_id = (int)$aktif_takim['id'];

    try { $aktif_sponsor = $pdo->prepare("SELECT * FROM kulup_sponsor WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ? AND aktif = 1");
        $aktif_sponsor->execute([$takim_id, $aktif_lig, $guncel_sezon]);
        $aktif_sponsor = $aktif_sponsor->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

    try { $s = $pdo->prepare("SELECT * FROM stadyum_ayar WHERE takim_id = ? AND takim_lig = ?");
        $s->execute([$takim_id, $aktif_lig]);
        $stadyum_bilgi = $s->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

    try { $f = $pdo->prepare("SELECT * FROM kulup_finans WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ?");
        $f->execute([$takim_id, $aktif_lig, $guncel_sezon]);
        $ffp_bilgi = $f->fetch(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

    try { $fl = $pdo->prepare("SELECT * FROM forma_satis_log WHERE takim_id = ? AND takim_lig = ? AND sezon_yil = ? ORDER BY hafta DESC LIMIT 5");
        $fl->execute([$takim_id, $aktif_lig, $guncel_sezon]);
        $forma_son_loglar = $fl->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e) {}

    $sponsor_teklifleri = _sponsor_teklifleri_olustur($takim_id, $aktif_lig, $guncel_sezon);
}

$mevcut_bilet_fiyati = $stadyum_bilgi['bilet_fiyati'] ?? ($aktif_takim['bilet_fiyati'] ?? 200);
$mevcut_atmosfer     = $stadyum_bilgi['atmosfer_carpani'] ?? 1.00;
$mevcut_doluluk      = $stadyum_bilgi['beklenen_doluluk'] ?? 80;
$kapasitesi          = $stadyum_bilgi['stadyum_kapasitesi'] ?? ($aktif_takim['stadyum_kapasitesi'] ?? 40000);
$mevcut_butce        = $aktif_takim['butce'] ?? 0;
$ffp_durum           = $aktif_takim['ffp_ceza'] ?? 'yok';
$transfer_yasagi     = !empty($aktif_takim['transfer_yasagi']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Kulüp Yönetimi | Ultimate Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body, html { margin:0; padding:0; min-height:100vh; font-family:'Poppins',sans-serif; background:#050505; color:#fff; }
        .bg-image { position:fixed; top:0; left:0; width:100vw; height:100vh; background:url('https://images.unsplash.com/photo-1560272564-c83b66b1ad12?q=80&w=2000') no-repeat center center; background-size:cover; z-index:-2; animation:slowZoom 20s infinite alternate; }
        @keyframes slowZoom { 0%{transform:scale(1);} 100%{transform:scale(1.05);} }
        .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:linear-gradient(135deg,rgba(5,5,5,0.92) 0%,rgba(10,15,30,0.82) 100%); backdrop-filter:blur(6px); z-index:-1; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .gold-text { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .modern-nav { padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:1.6rem; font-weight:900; color:#fff; text-decoration:none; letter-spacing:2px; display:flex; align-items:center; gap:12px; }
        .nav-brand i { color:#d4af37; }
        .hero-section { text-align:center; padding:30px 20px 10px; }
        .hero-title { font-size:3.2rem; font-weight:900; color:#fff; line-height:1.1; margin-bottom:8px; text-shadow:0 10px 30px rgba(0,0,0,0.8); }
        .hero-subtitle { font-size:1rem; font-weight:300; color:#94a3b8; letter-spacing:2px; }

        /* --- GENEL KART STILI --- */
        .ceo-card {
            background:rgba(255,255,255,0.05);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:20px;
            padding:28px;
            backdrop-filter:blur(12px);
            transition:all 0.3s ease;
            margin-bottom:28px;
        }
        .ceo-card:hover { border-color:rgba(212,175,55,0.3); box-shadow:0 8px 32px rgba(212,175,55,0.1); }
        .section-title { font-size:1.15rem; font-weight:700; color:#d4af37; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
        .section-title i { font-size:1.3rem; }

        /* --- LİG SEKMELERİ --- */
        .lig-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:24px; }
        .lig-tab { padding:8px 18px; border-radius:30px; font-size:0.8rem; font-weight:700; text-decoration:none; border:1px solid rgba(255,255,255,0.15); color:#94a3b8; transition:all 0.3s; text-transform:uppercase; }
        .lig-tab.active, .lig-tab:hover { background:rgba(212,175,55,0.2); border-color:#d4af37; color:#d4af37; }

        /* --- BİLET FİYATI SLIDER --- */
        .ticket-slider-wrap { padding:10px 0; }
        input[type=range].ticket-slider { width:100%; accent-color:#d4af37; height:6px; }
        .ticket-value { font-size:2rem; font-weight:900; color:#d4af37; }
        .ticket-indicators { display:flex; justify-content:space-between; font-size:0.72rem; color:#64748b; margin-top:4px; }
        .atmosfer-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.82rem; font-weight:700; }
        .atm-high  { background:rgba(16,185,129,0.2); color:#10b981; border:1px solid #10b981; }
        .atm-mid   { background:rgba(212,175,55,0.2);  color:#d4af37; border:1px solid #d4af37; }
        .atm-low   { background:rgba(239,68,68,0.2);   color:#ef4444; border:1px solid #ef4444; }

        /* --- SPONSOR KARTLARI --- */
        .sponsor-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:16px; }
        .sponsor-card { background:rgba(255,255,255,0.04); border:2px solid rgba(255,255,255,0.1); border-radius:16px; padding:20px; cursor:pointer; transition:all 0.3s; }
        .sponsor-card:hover, .sponsor-card.selected { border-color:#d4af37; background:rgba(212,175,55,0.1); }
        .sponsor-card.selected { box-shadow:0 0 20px rgba(212,175,55,0.3); }
        .sponsor-type-badge { display:inline-block; padding:3px 12px; border-radius:12px; font-size:0.72rem; font-weight:800; margin-bottom:10px; }
        .badge-a { background:#1e40af; color:#93c5fd; }
        .badge-b { background:#7c2d12; color:#fca5a5; }
        .badge-c { background:#14532d; color:#86efac; }
        .sponsor-name { font-size:1.1rem; font-weight:700; color:#fff; margin-bottom:6px; }
        .sponsor-money { font-size:0.85rem; color:#94a3b8; }
        .sponsor-money span { color:#d4af37; font-weight:700; }

        /* --- FFP DURUM --- */
        .ffp-bar-wrap { background:rgba(255,255,255,0.07); border-radius:10px; height:14px; overflow:hidden; margin:10px 0; }
        .ffp-bar { height:100%; border-radius:10px; transition:width 1s ease; }
        .ffp-ok    { background:linear-gradient(90deg,#10b981,#34d399); }
        .ffp-warn  { background:linear-gradient(90deg,#f59e0b,#fbbf24); }
        .ffp-danger{ background:linear-gradient(90deg,#ef4444,#f87171); }
        .ffp-stat  { font-size:0.85rem; color:#94a3b8; }
        .ffp-stat span { color:#fff; font-weight:700; }
        .ceza-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.78rem; font-weight:800; }
        .ceza-yok     { background:rgba(16,185,129,0.2); color:#10b981; }
        .ceza-yasak   { background:rgba(239,68,68,0.2);  color:#ef4444; }
        .ceza-puan    { background:rgba(220,38,38,0.4);  color:#fca5a5; }

        /* --- FORMA SATIŞ --- */
        .forma-log-row { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:rgba(255,255,255,0.04); border-radius:10px; margin-bottom:8px; font-size:0.87rem; }
        .forma-gelir-val { font-weight:700; color:#10b981; }

        /* --- STAT KUTUCUKLARI --- */
        .stat-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:20px; }
        .stat-box { background:rgba(255,255,255,0.05); border-radius:14px; padding:16px; text-align:center; border:1px solid rgba(255,255,255,0.1); }
        .stat-val  { font-size:1.5rem; font-weight:800; color:#d4af37; }
        .stat-lbl  { font-size:0.72rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; }

        /* --- BUTON --- */
        .btn-gold { background:linear-gradient(135deg,#d4af37,#92731b); color:#000; font-weight:800; border:none; border-radius:12px; padding:10px 24px; font-size:0.9rem; transition:all 0.3s; }
        .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(212,175,55,0.4); color:#000; }
        .btn-danger-dark { background:rgba(239,68,68,0.2); color:#ef4444; font-weight:700; border:1px solid #ef4444; border-radius:12px; padding:10px 24px; }
        .btn-danger-dark:hover { background:rgba(239,68,68,0.35); color:#fca5a5; }
        .btn-green-dark { background:rgba(16,185,129,0.2); color:#10b981; font-weight:700; border:1px solid #10b981; border-radius:12px; padding:10px 24px; }
        .btn-green-dark:hover { background:rgba(16,185,129,0.35); color:#6ee7b7; }

        .alert-custom { border-radius:14px; padding:14px 20px; margin-bottom:20px; font-weight:600; font-size:0.9rem; }
        .footer-note { text-align:center; padding:30px; font-size:0.75rem; color:#334155; font-family:'Oswald',sans-serif; letter-spacing:2px; }

        /* no team */
        .no-team-box { text-align:center; padding:60px 20px; color:#475569; }
        .no-team-box i { font-size:4rem; margin-bottom:20px; display:block; color:#1e293b; }
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
    <div style="color:#94a3b8; font-size:0.85rem;">
        <i class="fa-solid fa-calendar-week"></i>
        Hafta <strong style="color:#d4af37;"><?= $guncel_hafta ?></strong> &nbsp;|&nbsp;
        Sezon <strong style="color:#d4af37;"><?= $guncel_sezon ?></strong>
    </div>
</div>

<div class="hero-section">
    <h1 class="hero-title font-oswald">CEO <span class="gold-text">KULÜP YÖNETİMİ</span></h1>
    <p class="hero-subtitle">Finansal Fair Play · Bilet Fiyatı · Sponsorluk · Forma Satışları</p>
</div>

<div class="container" style="max-width:1100px; padding-bottom:40px;">

<?php if (empty($benim_takimlarim)): ?>
    <div class="no-team-box">
        <i class="fa-solid fa-building-circle-xmark"></i>
        <h4>Yönetilecek takım bulunamadı.</h4>
        <p>Önce bir liga girip takım seçin.</p>
        <a href="index.php" class="btn btn-gold mt-2"><i class="fa-solid fa-arrow-left"></i> Ana Menüye Dön</a>
    </div>
<?php else: ?>

    <!-- Lig Sekmeleri -->
    <div class="lig-tabs">
        <?php foreach ($benim_takimlarim as $t): $kod = $t['kaynak']; ?>
        <a href="?lig=<?= $kod ?>" class="lig-tab <?= $aktif_lig === $kod ? 'active' : '' ?>">
            <i class="fa-solid fa-flag"></i> <?= htmlspecialchars(strtoupper($kod)) ?> — <?= htmlspecialchars($t['takim_adi']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert-custom alert alert-<?= $mesaj_tipi ?>">
        <?= htmlspecialchars($mesaj) ?>
    </div>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- STAT KUTUCUKLARI                                             -->
    <!-- ============================================================ -->
    <div class="stat-grid">
        <div class="stat-box">
            <div class="stat-val">€<?= number_format(($mevcut_butce / 1000000), 1) ?>M</div>
            <div class="stat-lbl">Mevcut Bütçe</div>
        </div>
        <div class="stat-box">
            <div class="stat-val">₺<?= number_format($mevcut_bilet_fiyati) ?></div>
            <div class="stat-lbl">Bilet Fiyatı</div>
        </div>
        <div class="stat-box">
            <div class="stat-val">%<?= $mevcut_doluluk ?></div>
            <div class="stat-lbl">Beklenen Doluluk</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $mevcut_atmosfer ?>x</div>
            <div class="stat-lbl">Atmosfer Çarpanı</div>
        </div>
        <div class="stat-box">
            <div class="stat-val <?= $ffp_durum === 'yok' ? '' : 'text-danger' ?>">
                <?= $ffp_durum === 'yok' ? '✅' : '⚠️' ?> <?= htmlspecialchars(strtoupper($ffp_durum)) ?>
            </div>
            <div class="stat-lbl">FFP Durumu</div>
        </div>
        <div class="stat-box">
            <div class="stat-val"><?= $transfer_yasagi ? '🚫 YASAK' : '✅ SERBEST' ?></div>
            <div class="stat-lbl">Transfer</div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- 1. BİLET FİYATI & STADYUM YÖNETİMİ                         -->
    <!-- ============================================================ -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-ticket"></i> Stadyum Bilet Fiyatlandırması
        </div>
        <p style="color:#94a3b8; font-size:0.88rem; margin-bottom:18px;">
            Bilet fiyatını belirleyin. Yüksek fiyat = yüksek gelir ama düşük doluluk ve ev sahibi avantajı.
            Düşük fiyat = tribünler dolu, atmosfer yüksek ama bilet geliri azalır.
        </p>

        <form method="post">
            <div class="ticket-slider-wrap">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="ticket-value" id="ticket-display">₺<?= $mevcut_bilet_fiyati ?></span>
                    <div>
                        <span class="atmosfer-badge <?= $mevcut_atmosfer >= 1.10 ? 'atm-high' : ($mevcut_atmosfer >= 0.90 ? 'atm-mid' : 'atm-low') ?>">
                            <?php if ($mevcut_atmosfer >= 1.10) echo '🔥 Yüksek Atmosfer'; elseif ($mevcut_atmosfer >= 0.90) echo '👥 Normal Atmosfer'; else echo '😴 Düşük Atmosfer'; ?>
                        </span>
                    </div>
                </div>
                <input type="range" name="bilet_fiyati" id="bilet_slider"
                       class="ticket-slider form-range"
                       min="10" max="2000" step="10"
                       value="<?= $mevcut_bilet_fiyati ?>"
                       oninput="updateTicket(this.value)">
                <div class="ticket-indicators">
                    <span>₺10 (Maks Doluluk)</span>
                    <span>₺200 (Dengeli)</span>
                    <span>₺500</span>
                    <span>₺2000 (Boş Tribün)</span>
                </div>
            </div>

            <div class="row mt-3 text-center" id="ticket-preview">
                <div class="col-4">
                    <div style="font-size:1.3rem; font-weight:800; color:#10b981;" id="prev-doluluk">%<?= $mevcut_doluluk ?></div>
                    <div style="font-size:0.72rem; color:#64748b;">TAHMİNİ DOLULUK</div>
                </div>
                <div class="col-4">
                    <div style="font-size:1.3rem; font-weight:800; color:#d4af37;" id="prev-gelir">
                        <?php
                            $prev_cap = $kapasitesi;
                            echo '€' . number_format(round($prev_cap * $mevcut_doluluk / 100 * $mevcut_bilet_fiyati / 1000), 0, ',', '.') . 'K';
                        ?>
                    </div>
                    <div style="font-size:0.72rem; color:#64748b;">MAÇ BAŞI GELİR (TAHMİN)</div>
                </div>
                <div class="col-4">
                    <div style="font-size:1.3rem; font-weight:800;" id="prev-atm" class="<?= $mevcut_atmosfer >= 1.10 ? 'text-success' : ($mevcut_atmosfer >= 0.90 ? 'text-warning' : 'text-danger') ?>">
                        <?= $mevcut_atmosfer ?>x
                    </div>
                    <div style="font-size:0.72rem; color:#64748b;">ATMOSFER ÇARPANI</div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" name="bilet_kaydet" class="btn btn-gold">
                    <i class="fa-solid fa-floppy-disk"></i> Bilet Fiyatını Kaydet
                </button>
                <span style="font-size:0.78rem; color:#475569; margin-left:14px;">
                    Stadyum kapasitesi: <?= number_format($kapasitesi) ?> kişi
                </span>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- 2. SPONSORLUK SEÇİMİ                                        -->
    <!-- ============================================================ -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-handshake-simple"></i> Sponsorluk Seçimi
            <?php if ($aktif_sponsor): ?>
                <span style="font-size:0.78rem; font-weight:400; color:#10b981; margin-left:8px;">
                    ✅ Aktif: <?= htmlspecialchars($aktif_sponsor['sponsor_adi']) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if ($aktif_sponsor): ?>
        <div style="background:rgba(16,185,129,0.1); border:1px solid #10b981; border-radius:12px; padding:14px 18px; margin-bottom:18px;">
            <strong style="color:#10b981;">Aktif Sponsor: <?= htmlspecialchars($aktif_sponsor['sponsor_adi']) ?></strong><br>
            <span style="color:#94a3b8; font-size:0.85rem;">
                Garanti: <strong style="color:#d4af37;">€<?= number_format($aktif_sponsor['garantili_odeme']/1000000,1) ?>M</strong> &nbsp;|&nbsp;
                Şampiyonluk Bonusu: <strong style="color:#d4af37;">€<?= number_format($aktif_sponsor['sampiyon_bonusu']/1000000,1) ?>M</strong> &nbsp;|&nbsp;
                UCL Yarı Final: <strong style="color:#d4af37;">€<?= number_format($aktif_sponsor['ucl_sf_bonusu']/1000000,1) ?>M</strong>
            </span>
        </div>
        <?php endif; ?>

        <p style="color:#94a3b8; font-size:0.87rem; margin-bottom:16px;">
            Sezon için sponsorunuzu seçin. Garanti ödeme sezon başında kasaya girer.
            Bonus ödemeler şampiyonluk / UCL yarı final sonunda otomatik eklenir.
        </p>
        <form method="post">
            <div class="sponsor-grid">
                <?php
                $badge_class = ['A'=>'badge-a','B'=>'badge-b','C'=>'badge-c'];
                $badge_label = ['A'=>'Teklif A','B'=>'Teklif B','C'=>'Teklif C'];
                foreach ($sponsor_teklifleri as $tip => $sp):
                    $secili = $aktif_sponsor && $aktif_sponsor['teklif_tipi'] === $tip;
                ?>
                <label class="sponsor-card <?= $secili ? 'selected' : '' ?>" style="cursor:pointer;">
                    <input type="radio" name="sponsor_tip" value="<?= $tip ?>" <?= $secili ? 'checked' : '' ?> style="display:none;" onclick="selectSponsor(this)">
                    <div class="sponsor-type-badge <?= $badge_class[$tip] ?>"><?= $badge_label[$tip] ?></div>
                    <div class="sponsor-name"><?= htmlspecialchars($sp['adi']) ?></div>
                    <div class="sponsor-money">
                        Garanti: <span>€<?= number_format($sp['garanti']/1000000,1) ?>M</span><br>
                        Şampiyonluk Bonusu: <span>€<?= number_format($sp['sampiyon']/1000000,1) ?>M</span><br>
                        UCL Yarı Final: <span>€<?= number_format($sp['ucl_sf']/1000000,1) ?>M</span>
                    </div>
                    <p style="font-size:0.75rem; color:#64748b; margin-top:8px; margin-bottom:0;"><?= htmlspecialchars($sp['aciklama']) ?></p>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="mt-3">
                <button type="submit" name="sponsor_sec" class="btn btn-gold">
                    <i class="fa-solid fa-check-circle"></i> Sponsoru Onayla & Garantiyi Al
                </button>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- 3. FORMA SATIŞLARI (Merchandising)                           -->
    <!-- ============================================================ -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-shirt"></i> Forma Satışları & Merchandising
        </div>
        <p style="color:#94a3b8; font-size:0.87rem; margin-bottom:16px;">
            Kadronuzun ortalama OVR'sine ve süperstar (90+ OVR) oyuncu sayısına göre
            haftalık merchandising geliri hesaplanır. Her haftanın sonunda bu düğmeye basarak kasanıza ekleyin.
            90+ OVR'li bir yıldız transfer ettiğinizde o hafta masif gelir sizi bekler!
        </p>

        <?php if (!empty($forma_son_loglar)): ?>
        <div style="margin-bottom:18px;">
            <div style="font-size:0.8rem; color:#64748b; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Son Forma Gelirleri</div>
            <?php foreach ($forma_son_loglar as $log): ?>
            <div class="forma-log-row">
                <div>
                    <span style="color:#94a3b8;">H<?= $log['hafta'] ?></span>
                    <?php if ($log['tetikleyen']): ?>
                        <span style="background:rgba(212,175,55,0.2); color:#d4af37; font-size:0.72rem; padding:2px 8px; border-radius:8px; margin-left:8px;">
                            ⭐ <?= htmlspecialchars($log['tetikleyen']) ?>
                        </span>
                    <?php endif; ?>
                    <span style="color:#475569; font-size:0.78rem; margin-left:8px;"><?= htmlspecialchars($log['aciklama'] ?? '') ?></span>
                </div>
                <div class="forma-gelir-val">+€<?= number_format($log['gelir']/1000,0,',','.') ?>K</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="post" class="d-inline">
            <button type="submit" name="forma_geliri_hesapla" class="btn btn-green-dark">
                <i class="fa-solid fa-cash-register"></i> Haftalık Forma Gelirini Kasaya Ekle
            </button>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- 4. FİNANSAL FAIR PLAY (FFP)                                 -->
    <!-- ============================================================ -->
    <div class="ceo-card">
        <div class="section-title font-oswald">
            <i class="fa-solid fa-scale-balanced"></i> Finansal Fair Play (FFP)
        </div>
        <p style="color:#94a3b8; font-size:0.87rem; margin-bottom:16px;">
            UEFA Finansal Fair Play kurallarına göre kulübünüzün mali durumu denetlenir.
            Bütçe <strong style="color:#f59e0b;">-15M€</strong> altına düşerse transfer yasağı,
            <strong style="color:#ef4444;">-30M€</strong> altına düşerse ek olarak 3 puan silme cezası uygulanır.
        </p>

        <?php
        $ffp_yuzdesi  = 100;
        if ($mevcut_butce < 0) {
            // -30M = 0%, -15M = 50%, 0 = 100%
            $ffp_yuzdesi = max(0, min(100, 100 + ($mevcut_butce / 300000)));
        }
        $ffp_renk = $ffp_yuzdesi >= 60 ? 'ffp-ok' : ($ffp_yuzdesi >= 30 ? 'ffp-warn' : 'ffp-danger');
        ?>

        <div style="margin-bottom:14px;">
            <div class="d-flex justify-content-between mb-1">
                <span class="ffp-stat">Mevcut Bütçe: <span>€<?= number_format($mevcut_butce/1000000,2) ?>M</span></span>
                <span class="ffp-stat">FFP Yasak Eşiği: <span style="color:#f59e0b;">-15M€</span> | Puan Eşiği: <span style="color:#ef4444;">-30M€</span></span>
            </div>
            <div class="ffp-bar-wrap">
                <div class="ffp-bar <?= $ffp_renk ?>" style="width:<?= $ffp_yuzdesi ?>%;"></div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="ffp-stat">FFP Durumu:</span>
            <?php
            $ceza_label = ['yok'=>'✅ Temiz','transfer_yasagi'=>'🚫 Transfer Yasağı','puan_silme'=>'⛔ Puan Silme'];
            $ceza_class = ['yok'=>'ceza-yok','transfer_yasagi'=>'ceza-yasak','puan_silme'=>'ceza-puan'];
            $ceza_k = $ffp_durum ?: 'yok';
            ?>
            <span class="ceza-badge <?= $ceza_class[$ceza_k] ?? 'ceza-yok' ?>"><?= $ceza_label[$ceza_k] ?? '✅ Temiz' ?></span>
        </div>

        <?php if ($ffp_bilgi): ?>
        <div style="font-size:0.82rem; color:#475569; margin-bottom:14px;">
            Son FFP kontrolü: Sezon <?= $ffp_bilgi['sezon_yil'] ?> |
            Kayıt edilen bakiye: €<?= number_format($ffp_bilgi['ffp_bakiye']/1000000,2) ?>M
        </div>
        <?php endif; ?>

        <form method="post" class="d-inline">
            <button type="submit" name="ffp_kontrol" class="btn btn-danger-dark">
                <i class="fa-solid fa-magnifying-glass-chart"></i> FFP Kontrolü Yap & Ceza Uygula
            </button>
        </form>

        <a href="menajer_kariyer.php?lig=<?= $aktif_lig ?>" class="btn btn-gold ms-2">
            <i class="fa-solid fa-user-tie"></i> Menajer Kariyer Paneline Git
        </a>
    </div>

<?php endif; ?>
</div><!-- /container -->

<div class="footer-note font-oswald">
    V5.0.0 PHASE 5 — CEO KULÜP YÖNETİMİ · FFP · BİLET · SPONSORLUK · FORMA SATIŞLARI · KARİYER
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
var stadyumKapasitesi = <?= (int)$kapasitesi ?>;

function updateTicket(val) {
    val = parseInt(val);
    document.getElementById('ticket-display').textContent = '₺' + val.toLocaleString('tr-TR');

    var doluluk, atmosfer;
    if (val <= 50)         { doluluk = 100; atmosfer = 1.25; }
    else if (val <= 150)   { doluluk = 92;  atmosfer = 1.15; }
    else if (val <= 300)   { doluluk = 80;  atmosfer = 1.00; }
    else if (val <= 600)   { doluluk = 62;  atmosfer = 0.90; }
    else if (val <= 1000)  { doluluk = 45;  atmosfer = 0.80; }
    else                   { doluluk = 28;  atmosfer = 0.70; }

    var gelir = Math.round(stadyumKapasitesi * doluluk / 100 * val / 1000);

    document.getElementById('prev-doluluk').textContent = '%' + doluluk;
    document.getElementById('prev-gelir').textContent   = '€' + gelir.toLocaleString('tr-TR') + 'K';

    var atmEl = document.getElementById('prev-atm');
    atmEl.textContent = atmosfer.toFixed(2) + 'x';
    atmEl.className = atmosfer >= 1.10 ? 'text-success' : (atmosfer >= 0.90 ? 'text-warning' : 'text-danger');
}

function selectSponsor(radio) {
    document.querySelectorAll('.sponsor-card').forEach(function(c) { c.classList.remove('selected'); });
    radio.closest('.sponsor-card').classList.add('selected');
}

// Süperstar transferi formu bildirimi (başka sayfadan gelirse)
var urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('superstar') === '1') {
    var pName = urlParams.get('oyuncu') || 'Süperstar Oyuncu';
    alert('🌟 ' + pName + ' forma satışlarına katkısı bu haftadan başlıyor! Forma Geliri Hesapla butonuna basın.');
}
</script>
</body>
</html>
