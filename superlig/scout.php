<?php
// ==============================================================================
// FAZ 4: GLOBAL SCOUT AĞI - GÖZLEMCİ GÖNDERMİ VE WONDERKID SİSTEMİ
// ==============================================================================
include 'db.php';

$mesaj = "";
$mesaj_tipi = "";

// --- Tablo Haritalaması ---
$tbl_oyuncu = [
    'tr' => 'oyuncular',    'cl' => 'cl_oyuncular', 'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular', 'de' => 'de_oyuncular', 'fr' => 'fr_oyuncular',
    'it' => 'it_oyuncular', 'pt' => 'pt_oyuncular',
];
$tbl_takim = [
    'tr' => 'takimlar',    'cl' => 'cl_takimlar', 'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar', 'fr' => 'fr_takimlar',
    'it' => 'it_takimlar', 'pt' => 'pt_takimlar',
];
$gecerli_liglar = array_keys($tbl_oyuncu);
$ayar_tablosu = [
    'tr' => 'ayar',    'pl' => 'pl_ayar',   'es' => 'es_ayar',
    'de' => 'de_ayar', 'it' => 'it_ayar',   'cl' => 'cl_ayar',
    'fr' => 'fr_ayar', 'pt' => 'pt_ayar',
];

// Kullanıcı takımını çek
function fetch_kullanici_takimi_scout($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    $ayar_stmt = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1");
    $ayar_id = $ayar_stmt ? $ayar_stmt->fetchColumn() : false;
    if (!$ayar_id) return null;
    $stmt = $pdo->prepare("SELECT id, takim_adi, logo, butce FROM $takim_tablo WHERE id = ?");
    $stmt->execute([$ayar_id]);
    $takim = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($takim) { $takim['kaynak'] = $lig_kodu; return $takim; }
    return null;
}

$benim_takimlarim = [];
foreach ($ayar_tablosu as $kod => $ayar_tbl) {
    try {
        $t = fetch_kullanici_takimi_scout($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
        if ($t) $benim_takimlarim[] = $t;
    } catch (Throwable $e) {}
}

// Mevcut hafta
$guncel_hafta = 1;
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// Bölge Ayarları
$bolgeler = [
    'Güney Amerika' => ['maliyet' => 600000, 'ikon' => 'fa-globe-americas', 'renk' => '#10b981',
        'ulkeler' => ['Brezilya', 'Arjantin', 'Kolombiya', 'Uruguay', 'Şili', 'Peru', 'Paraguay'],
        'aciklama' => 'Brezilyalı ve Arjantinli genç yıldızlar'],
    'Afrika'        => ['maliyet' => 400000, 'ikon' => 'fa-globe-africa', 'renk' => '#f59e0b',
        'ulkeler' => ['Nijerya', 'Gana', 'Senegal', 'Fildişi Sahili', 'Mali', 'Kamerun', 'Mozambik'],
        'aciklama' => 'Ham yetenekler ve olağanüstü fizik kapasitesi'],
    'Doğu Avrupa'   => ['maliyet' => 500000, 'ikon' => 'fa-globe-europe', 'renk' => '#3b82f6',
        'ulkeler' => ['Sırbistan', 'Hırvatistan', 'Bosna', 'Slovakya', 'Polonya', 'Çekya', 'Ukrayna'],
        'aciklama' => 'Teknik ve taktik açıdan güçlü genç oyuncular'],
    'Asya'          => ['maliyet' => 350000, 'ikon' => 'fa-globe-asia', 'renk' => '#8b5cf6',
        'ulkeler' => ['Japonya', 'Güney Kore', 'Çin', 'Avustralya', 'İran', 'Özbekistan'],
        'aciklama' => 'Hızlı ve disiplinli Asya yıldız adayları'],
    'Kuzey Amerika' => ['maliyet' => 450000, 'ikon' => 'fa-flag-usa', 'renk' => '#e11d48',
        'ulkeler' => ['ABD', 'Meksika', 'Kanada', 'Jamaika', 'Trinidad'],
        'aciklama' => 'MLS gelişim sistemi ve Karayip yetenekleri'],
];

// Wonderkid isimleri (havuz)
$isim_havuzu = [
    'Güney Amerika' => ['Lucas', 'Gabriel', 'Mateo', 'Nicolas', 'Diego', 'Rodrigo', 'Felipe', 'Alejandro', 'Thiago', 'Eduardo'],
    'Afrika'        => ['Kwame', 'Seun', 'Amadou', 'Ibrahim', 'Moussa', 'Youssouf', 'Boubacar', 'Cheikh', 'Sadio', 'Mamadou'],
    'Doğu Avrupa'   => ['Nikola', 'Stefan', 'Luka', 'Milan', 'Tomáš', 'Jakub', 'Patrik', 'Jan', 'Marek', 'Viktor'],
    'Asya'          => ['Hiroki', 'Junya', 'Takuma', 'Ji-sung', 'Hwang', 'Yuki', 'Kaoru', 'Ao', 'Daichi', 'Reo'],
    'Kuzey Amerika' => ['Tyler', 'Jordan', 'Chase', 'Marcus', 'Ethan', 'Cameron', 'Aaron', 'Dante', 'Miles', 'Hunter'],
];
$soyisim_havuzu = [
    'Güney Amerika' => ['Silva', 'Santos', 'Pereira', 'Costa', 'Ferreira', 'Rodrigues', 'Garcia', 'Martinez', 'Lopez', 'Oliveira'],
    'Afrika'        => ['Diallo', 'Traoré', 'Konaté', 'Coulibaly', 'Touré', 'Diop', 'Ndiaye', 'Keita', 'Bah', 'Camara'],
    'Doğu Avrupa'   => ['Novak', 'Kovač', 'Horváth', 'Müller', 'Jović', 'Vlahović', 'Milinković', 'Kostić', 'Lazović', 'Babić'],
    'Asya'          => ['Ito', 'Tanaka', 'Kamada', 'Ueda', 'Minamino', 'Furuhashi', 'Maeda', 'Soma', 'Kubo', 'Endo'],
    'Kuzey Amerika' => ['Johnson', 'Williams', 'Smith', 'Davis', 'Turner', 'Carter', 'Adams', 'Parker', 'Rivera', 'Reyes'],
];
$mevkiler = ['KAL', 'SAĞ', 'SOL', 'STK', 'ORT', 'FWD', 'OFM', 'DFM'];

function uret_wonderkidler($bolge, $bolge_data, $isim_havuzu, $soyisim_havuzu, $mevkiler) {
    $sayisi = mt_rand(3, 5);
    $liste = [];
    $ulkeler = $bolge_data['ulkeler'];
    for ($i = 0; $i < $sayisi; $i++) {
        $isim    = $isim_havuzu[$bolge][array_rand($isim_havuzu[$bolge])];
        $soyisim = $soyisim_havuzu[$bolge][array_rand($soyisim_havuzu[$bolge])];
        $liste[] = [
            'isim'       => $isim . ' ' . $soyisim,
            'yas'        => mt_rand(15, 17),
            'mevki'      => $mevkiler[array_rand($mevkiler)],
            'ovr'        => mt_rand(55, 68),
            'potansiyel' => mt_rand(80, 95),
            'ulke'       => $ulkeler[array_rand($ulkeler)],
        ];
    }
    return $liste;
}

// --- Scout Görevi Başlat ---
if (isset($_POST['scout_gonder'])) {
    $bolge       = $_POST['bolge'] ?? '';
    $takim_secim = explode('_', $_POST['takim_secim'] ?? ''); // lig_id

    if (!array_key_exists($bolge, $bolgeler)) {
        $mesaj = "Geçersiz bölge."; $mesaj_tipi = "danger";
    } elseif (count($takim_secim) !== 2 || !in_array($takim_secim[0], $gecerli_liglar, true)) {
        $mesaj = "Geçersiz takım seçimi."; $mesaj_tipi = "danger";
    } else {
        $lig       = $takim_secim[0];
        $takim_id  = (int)$takim_secim[1];
        $maliyet   = $bolgeler[$bolge]['maliyet'];
        $takim_tbl = $tbl_takim[$lig];

        try {
            $pdo->beginTransaction();
            $t_stmt = $pdo->prepare("SELECT butce FROM $takim_tbl WHERE id = ?");
            $t_stmt->execute([$takim_id]);
            $butce = (int)$t_stmt->fetchColumn();

            if ($butce < $maliyet) {
                $pdo->rollBack();
                $mesaj = "Bütçe yetersiz! Gerekli: " . paraFormatlaScout($maliyet); $mesaj_tipi = "danger";
            } else {
                // Bütçeden düş
                $pdo->prepare("UPDATE $takim_tbl SET butce = butce - ? WHERE id = ?")->execute([$maliyet, $takim_id]);

                $donus_hafta = $guncel_hafta + mt_rand(3, 4);
                $pdo->prepare(
                    "INSERT INTO scout_gorevleri (takim_id, takim_lig, bolge, maliyet, gonderildi_hafta, donus_hafta, sezon_yil, durum)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'yolda')"
                )->execute([$takim_id, $lig, $bolge, $maliyet, $guncel_hafta, $donus_hafta, $guncel_sezon]);

                $pdo->commit();
                $mesaj = "✈️ Gözlemci $bolge bölgesine gönderildi! Hafta $donus_hafta'da raporuyla döner.";
                $mesaj_tipi = "success";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mesaj = "İşlem hatası."; $mesaj_tipi = "danger";
        }
    }
}

// --- Gözlemci Dönüşü Simüle Et (Hafta geldiyse wonderkidleri oluştur) ---
try {
    $yolda = $pdo->query("SELECT * FROM scout_gorevleri WHERE durum = 'yolda'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($yolda as $gorev) {
        if ($guncel_hafta >= $gorev['donus_hafta']) {
            $bolge = $gorev['bolge'];
            if (array_key_exists($bolge, $bolgeler)) {
                $wonderkidler = uret_wonderkidler($bolge, $bolgeler[$bolge], $isim_havuzu, $soyisim_havuzu, $mevkiler);
                $pdo->prepare("UPDATE scout_gorevleri SET durum = 'tamamlandi', wonderkidler = ? WHERE id = ?")
                    ->execute([json_encode($wonderkidler, JSON_UNESCAPED_UNICODE), $gorev['id']]);
            }
        }
    }
} catch (Throwable $e) {}

// --- Akademiye Kaydol (Wonderkid İmzala) ---
if (isset($_POST['imzala'])) {
    $gorev_id    = (int)($_POST['gorev_id'] ?? 0);
    $kid_index   = (int)($_POST['kid_index'] ?? 0);
    $takim_secim = explode('_', $_POST['takim_secim_imza'] ?? '');

    if ($gorev_id > 0 && count($takim_secim) === 2 && in_array($takim_secim[0], $gecerli_liglar, true)) {
        $lig      = $takim_secim[0];
        $takim_id = (int)$takim_secim[1];

        try {
            $gorev_stmt = $pdo->prepare("SELECT * FROM scout_gorevleri WHERE id = ? AND durum = 'tamamlandi'");
            $gorev_stmt->execute([$gorev_id]);
            $gorev = $gorev_stmt->fetch(PDO::FETCH_ASSOC);

            if ($gorev) {
                $liste = json_decode($gorev['wonderkidler'], true) ?? [];
                if (isset($liste[$kid_index])) {
                    $kid = $liste[$kid_index];
                    $oyuncu_tbl = $tbl_oyuncu[$lig];

                    $pdo->prepare(
                        "INSERT INTO $oyuncu_tbl
                         (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek, form, fitness, moral, ceza_hafta, sakatlik_hafta, sozlesme_ay)
                         VALUES (?, ?, ?, ?, ?, ?, (SELECT lig FROM {$tbl_takim[$lig]} WHERE id = ? LIMIT 1), 0, 1, 6, 100, 80, 0, 0, 48)"
                    )->execute([
                        $takim_id,
                        $kid['isim'],
                        $kid['mevki'],
                        $kid['ovr'],
                        $kid['yas'],
                        max(100000, $kid['ovr'] * 10000),
                        $takim_id,
                    ]);

                    // Wonderkidi listeden çıkar
                    unset($liste[$kid_index]);
                    $liste = array_values($liste);
                    if (empty($liste)) {
                        $pdo->prepare("UPDATE scout_gorevleri SET durum = 'imzalandi', wonderkidler = '[]' WHERE id = ?")
                            ->execute([$gorev_id]);
                    } else {
                        $pdo->prepare("UPDATE scout_gorevleri SET wonderkidler = ? WHERE id = ?")
                            ->execute([json_encode($liste, JSON_UNESCAPED_UNICODE), $gorev_id]);
                    }

                    $mesaj = "🌟 " . htmlspecialchars($kid['isim']) . " akademiye katıldı!"; $mesaj_tipi = "success";
                }
            }
        } catch (Throwable $e) {
            $mesaj = "İmzalama hatası."; $mesaj_tipi = "danger";
        }
    }
}

// --- Görevleri Çek ---
$aktif_gorevler = [];
try {
    $aktif_gorevler = $pdo->query(
        "SELECT * FROM scout_gorevleri WHERE durum = 'yolda' ORDER BY created_at DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$tamamlanan_gorevler = [];
try {
    $tamamlanan_gorevler = $pdo->query(
        "SELECT * FROM scout_gorevleri WHERE durum = 'tamamlandi' ORDER BY created_at DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

function paraFormatlaScout($sayi) {
    if ($sayi >= 1000000) return "€" . number_format($sayi / 1000000, 1) . "M";
    if ($sayi >= 1000)    return "€" . number_format($sayi / 1000, 1) . "K";
    return "€" . $sayi;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scout Ağı | Gözlemci Sistemi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sc-bg: #080c10; --sc-panel: #0f1923; --sc-gold: #d4af37;
            --sc-green: #10b981; --border: rgba(212,175,55,0.2);
        }
        body { background: var(--sc-bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .sc-navbar { background: rgba(8,12,16,0.96); border-bottom: 2px solid var(--sc-gold); padding: 0 2rem; height: 70px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }

        .sc-hero { background: linear-gradient(135deg, #0a1628 0%, #080c10 100%); border-bottom: 1px solid var(--border); padding: 3rem 2rem; text-align: center; background-image: radial-gradient(circle at 50% 0%, rgba(16,185,129,0.1) 0%, transparent 60%); }
        .sc-hero h1 { font-family: 'Oswald', sans-serif; font-size: clamp(2rem,5vw,3.5rem); color: #fff; text-shadow: 0 0 25px rgba(16,185,129,0.4); }

        .panel-card { background: var(--sc-panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
        .panel-hdr  { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .panel-hdr h5 { margin: 0; font-family: 'Oswald', sans-serif; font-size: 1.1rem; letter-spacing: 1px; }

        /* Bölge Kartları */
        .bolge-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: 1rem; padding: 1.25rem; }
        .bolge-card { border-radius: 10px; padding: 1.25rem; cursor: pointer; border: 2px solid transparent; transition: 0.2s; text-align: center; }
        .bolge-card:hover, .bolge-card.selected { border-color: var(--bolge-renk, var(--sc-gold)); background: rgba(255,255,255,0.04); }
        .bolge-card i { font-size: 2rem; margin-bottom: 0.5rem; }
        .bolge-card h6 { font-family: 'Oswald', sans-serif; font-size: 1rem; margin: 0; }
        .bolge-card small { color: #888; font-size: 0.78rem; }

        /* Gözlemci görev kartları */
        .gorev-card { margin: 0.75rem 1.25rem; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.07); border-radius: 10px; padding: 1rem 1.25rem; }
        .progress-thin { height: 5px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden; }
        .progress-bar-colored { height: 100%; border-radius: 3px; }

        /* Wonderkid kartlar */
        .kid-card { background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(0,0,0,0.3)); border: 1px solid rgba(16,185,129,0.25); border-radius: 10px; padding: 1rem; text-align: center; }
        .kid-ovr  { font-family: 'Oswald', sans-serif; font-size: 2rem; color: var(--sc-gold); }
        .kid-pot  { background: rgba(16,185,129,0.2); color: var(--sc-green); border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .btn-imzala { background: var(--sc-green); border: none; color: #fff; font-weight: 700; border-radius: 6px; padding: 7px 16px; font-size: 0.85rem; transition: 0.2s; }
        .btn-imzala:hover { background: #059669; transform: scale(1.04); }

        .btn-action-outline { background: transparent; border: 1px solid var(--sc-gold); color: var(--sc-gold); font-weight: 700; padding: 8px 18px; border-radius: 4px; text-decoration: none; transition: 0.3s; font-size: 0.9rem; }
        .btn-action-outline:hover { background: var(--sc-gold); color: #000; }
        .btn-scout-send { background: linear-gradient(45deg, var(--sc-gold), #997a00); border: none; color: #000; font-family: 'Oswald', sans-serif; font-weight: 700; font-size: 1.05rem; padding: 10px 28px; border-radius: 6px; letter-spacing: 1px; transition: 0.2s; }
        .btn-scout-send:hover { box-shadow: 0 0 15px rgba(212,175,55,0.5); transform: scale(1.03); }
        .empty-state { padding: 2.5rem; text-align: center; color: #444; font-family: 'Oswald', sans-serif; font-size: 1.1rem; }
    </style>
</head>
<body>

<nav class="sc-navbar">
    <div class="font-oswald fs-5 fw-bold" style="color:var(--sc-gold);">
        <i class="fa-solid fa-binoculars me-2"></i>GLOBAL SCOUT AĞI
    </div>
    <div class="d-flex gap-2">
        <a href="global_transfer.php" class="btn-action-outline"><i class="fa-solid fa-globe me-1"></i>Transfer Borsası</a>
        <a href="index.php" class="btn-action-outline"><i class="fa-solid fa-house me-1"></i>Ana Merkez</a>
    </div>
</nav>

<!-- HERO -->
<div class="sc-hero">
    <h1><i class="fa-solid fa-binoculars me-3" style="color:var(--sc-green);"></i>GLOBAL SCOUT AĞI</h1>
    <p style="color:#aaa; font-size:1.1rem;">Dünyaya gözlemci gönder. 3-4 hafta sonra 15-17 yaşında Wonderkid'ler keşfedilsin!</p>
    <div class="d-flex justify-content-center gap-3 flex-wrap mt-3">
        <div class="badge p-2" style="background:rgba(212,175,55,0.1); border:1px solid rgba(212,175,55,0.3); font-size:0.9rem;">
            📅 Mevcut Hafta: <strong style="color:var(--sc-gold);"><?= $guncel_hafta ?></strong>
        </div>
        <?php foreach ($benim_takimlarim as $bt): ?>
        <div class="badge p-2" style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); font-size:0.9rem;">
            💰 <?= htmlspecialchars($bt['takim_adi']) ?>: <strong style="color:var(--sc-green);"><?= paraFormatlaScout($bt['butce']) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="container py-4" style="max-width:1300px;">

    <?php if ($mesaj): ?>
        <div class="alert border-0 fw-bold text-center mb-4" style="background:<?= $mesaj_tipi=='success'?'#10b981':($mesaj_tipi=='danger'?'#ef4444':'#f59e0b') ?>; color:<?= $mesaj_tipi=='warning'?'#000':'#fff' ?>;">
            <?= htmlspecialchars($mesaj) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sol: Scout Gönder -->
        <div class="col-lg-5">
            <div class="panel-card">
                <div class="panel-hdr" style="border-bottom-color:rgba(16,185,129,0.3);">
                    <i class="fa-solid fa-paper-plane" style="color:var(--sc-green);"></i>
                    <h5 style="color:var(--sc-green);">Gözlemci Gönder</h5>
                </div>

                <form method="POST" id="scout-form">
                    <!-- Bölge Seçimi -->
                    <div class="bolge-grid">
                        <?php foreach ($bolgeler as $bolge_adi => $b): ?>
                        <label class="bolge-card" style="--bolge-renk:<?= $b['renk'] ?>; background:rgba(0,0,0,0.2);">
                            <input type="radio" name="bolge" value="<?= htmlspecialchars($bolge_adi) ?>" class="visually-hidden" required>
                            <div><i class="fa-solid <?= $b['ikon'] ?>" style="color:<?= $b['renk'] ?>;"></i></div>
                            <h6 style="color:<?= $b['renk'] ?>;"><?= htmlspecialchars($bolge_adi) ?></h6>
                            <small><?= htmlspecialchars($b['aciklama']) ?></small>
                            <div class="mt-2" style="color:var(--sc-gold); font-family:'Oswald'; font-size:1rem;">
                                <?= paraFormatlaScout($b['maliyet']) ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Takım Seçimi -->
                    <div class="p-3 pt-0">
                        <label class="text-muted small fw-bold mb-1 d-block">HANGİ TAKIMIN ADINA GÖNDERİYORSUN?</label>
                        <select name="takim_secim" class="form-select mb-3" style="background:#1a1a1a; border-color:rgba(212,175,55,0.3); color:#fff;" required>
                            <option value="">Takım Seç...</option>
                            <?php foreach ($benim_takimlarim as $bt): ?>
                            <option value="<?= $bt['kaynak'] . '_' . $bt['id'] ?>">
                                <?= htmlspecialchars($bt['takim_adi']) ?> (<?= paraFormatlaScout($bt['butce']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="scout_gonder" class="btn-scout-send w-100">
                            <i class="fa-solid fa-plane-departure me-2"></i>GÖZLEMCİ GÖNDER
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sağ: Aktif Görevler + Tamamlananlar -->
        <div class="col-lg-7">
            <!-- Yolda Olan Gözlemciler -->
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-plane" style="color:#3b82f6;"></i>
                    <h5>Yoldaki Gözlemciler</h5>
                    <?php if (!empty($aktif_gorevler)): ?>
                        <span class="badge rounded-pill ms-1" style="background:#3b82f6; font-family:'Oswald';"><?= count($aktif_gorevler) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (empty($aktif_gorevler)): ?>
                    <div class="empty-state"><i class="fa-solid fa-plane-slash fa-2x mb-3" style="color:#222;"></i><br>Yolda gözlemci yok.</div>
                <?php else: ?>
                    <?php foreach ($aktif_gorevler as $g): ?>
                    <div class="gorev-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <span class="font-oswald" style="color:var(--sc-gold); font-size:1rem;"><?= htmlspecialchars($g['bolge']) ?></span>
                                <span class="badge ms-2" style="background:rgba(255,255,255,0.06); color:#888; font-size:0.7rem;"><?= strtoupper($g['takim_lig']) ?></span>
                            </div>
                            <div style="color:var(--sc-gold); font-size:0.85rem;">
                                Dönüş: <strong>Hafta <?= $g['donus_hafta'] ?></strong>
                            </div>
                        </div>
                        <?php
                            $toplam = max(1, $g['donus_hafta'] - $g['gonderildi_hafta']);
                            $gecen  = max(0, min($toplam, $guncel_hafta - $g['gonderildi_hafta']));
                            $yuzde  = min(100, (int)(($gecen / $toplam) * 100));
                        ?>
                        <div class="progress-thin">
                            <div class="progress-bar-colored" style="width:<?= $yuzde ?>%; background:#3b82f6;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-1">
                            <small style="color:#555;">Gönderildi: Hafta <?= $g['gonderildi_hafta'] ?></small>
                            <small style="color:#3b82f6;"><?= $yuzde ?>% Tamamlandı</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Tamamlanan Görevler / Wonderkidler -->
            <?php if (!empty($tamamlanan_gorevler)): ?>
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-star" style="color:var(--sc-gold);"></i>
                    <h5 style="color:var(--sc-gold);">Bulunan Wonderkid'ler</h5>
                </div>
                <?php foreach ($tamamlanan_gorevler as $g):
                    $liste = json_decode($g['wonderkidler'], true) ?? [];
                    if (empty($liste)) continue;
                ?>
                <div class="p-3 border-bottom" style="border-color:rgba(255,255,255,0.05) !important;">
                    <h6 class="font-oswald mb-3" style="color:var(--sc-green); font-size:0.9rem;">
                        <i class="fa-solid fa-location-dot me-2"></i><?= htmlspecialchars($g['bolge']) ?> Raporu
                        <span class="text-muted" style="font-size:0.75rem; font-weight:400;">— <?= strtoupper($g['takim_lig']) ?></span>
                    </h6>
                    <div class="row g-2">
                        <?php foreach ($liste as $idx => $kid): ?>
                        <div class="col-sm-6 col-md-4">
                            <div class="kid-card">
                                <div class="kid-ovr"><?= $kid['ovr'] ?></div>
                                <div style="font-weight:700; font-size:0.95rem;"><?= htmlspecialchars($kid['isim']) ?></div>
                                <div style="color:#aaa; font-size:0.8rem;"><?= htmlspecialchars($kid['mevki']) ?> · <?= $kid['yas'] ?> yaş · <?= htmlspecialchars($kid['ulke']) ?></div>
                                <div class="mt-1 mb-2"><span class="kid-pot">POT: <?= $kid['potansiyel'] ?></span></div>
                                <form method="POST">
                                    <input type="hidden" name="gorev_id" value="<?= $g['id'] ?>">
                                    <input type="hidden" name="kid_index" value="<?= $idx ?>">
                                    <select name="takim_secim_imza" class="form-select form-select-sm mb-2" style="background:#0f1923; border-color:rgba(16,185,129,0.3); color:#fff; font-size:0.78rem;" required>
                                        <option value="">Takım Seç</option>
                                        <?php foreach ($benim_takimlarim as $bt): ?>
                                        <option value="<?= $bt['kaynak'] . '_' . $bt['id'] ?>"><?= htmlspecialchars($bt['takim_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="imzala" class="btn-imzala w-100">
                                        <i class="fa-solid fa-pen-nib me-1"></i>Akademiye Al
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Bölge kartı seçim görseli
document.querySelectorAll('.bolge-card input[type=radio]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.bolge-card').forEach(function(card) { card.classList.remove('selected'); });
        radio.closest('.bolge-card').classList.add('selected');
    });
});
</script>
</body>
</html>
