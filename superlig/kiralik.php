<?php
// ==============================================================================
// FAZ 4: KİRALIK SİSTEMİ - OYUNCU KİRALAMA VE TAKİP
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
function fetch_kullanici_kiralik($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
    $ayar_stmt = $pdo->query("SELECT kullanici_takim_id FROM $ayar_tablo LIMIT 1");
    $ayar_id   = $ayar_stmt ? $ayar_stmt->fetchColumn() : false;
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
        $t = fetch_kullanici_kiralik($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
        if ($t) $benim_takimlarim[] = $t;
    } catch (Throwable $e) {}
}

$guncel_hafta = 1;
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// Kiralık süresi seçenekleri
$sure_secenekleri = [
    'yarim' => ['etiket' => 'Yarım Sezon (~19 Hafta)', 'hafta' => 19],
    'tam'   => ['etiket' => 'Tam Sezon (~38 Hafta)',   'hafta' => 38],
];

// Kiralayan takım isimleri (yapay zeka)
$kiralayan_takimlar = [
    'Trabzonspor', 'Sivasspor', 'Gaziantep FK', 'Alanyaspor',
    'Hatayspor', 'Konyaspor', 'Kasımpaşa', 'Antalyaspor',
    'Burnley', 'Sheffield Utd', 'Luton Town', 'Middlesbrough',
    'Lecce', 'Monza', 'Salernitana', 'Brescia',
    'Osasuna', 'Celta Vigo', 'Getafe', 'Mallorca',
    'Augsburg', 'Mainz', 'Heidenheim', 'Darmstadt',
];

// --- Kiralığa Gönder ---
if (isset($_POST['kiraliga_gonder'])) {
    $oyuncu_id   = (int)($_POST['oyuncu_id'] ?? 0);
    $kaynak_lig  = $_POST['kaynak_lig'] ?? '';
    $sure_tipi   = $_POST['sure_tipi'] ?? 'yarim';
    $maas_katki  = (int)($_POST['maas_katki'] ?? 50);

    if (!in_array($kaynak_lig, $gecerli_liglar, true)) {
        $mesaj = "Geçersiz lig kodu."; $mesaj_tipi = "danger";
    } elseif (!array_key_exists($sure_tipi, $sure_secenekleri)) {
        $mesaj = "Geçersiz süre."; $mesaj_tipi = "danger";
    } elseif ($maas_katki < 0 || $maas_katki > 100) {
        $mesaj = "Maaş katkısı 0-100 arasında olmalı."; $mesaj_tipi = "danger";
    } else {
        $oyuncu_tbl = $tbl_oyuncu[$kaynak_lig];
        try {
            $o_stmt = $pdo->prepare("SELECT * FROM $oyuncu_tbl WHERE id = ?");
            $o_stmt->execute([$oyuncu_id]);
            $oyuncu = $o_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oyuncu) {
                $mesaj = "Oyuncu bulunamadı."; $mesaj_tipi = "danger";
            } else {
                // Zaten kiralıkta mı?
                $zaten = $pdo->prepare("SELECT id FROM kiralik_oyuncular WHERE oyuncu_id = ? AND kaynak_lig = ? AND durum = 'aktif'");
                $zaten->execute([$oyuncu_id, $kaynak_lig]);
                if ($zaten->fetchColumn()) {
                    $mesaj = "Bu oyuncu zaten kiralıkta!"; $mesaj_tipi = "warning";
                } else {
                    $bitis_hafta   = $guncel_hafta + $sure_secenekleri[$sure_tipi]['hafta'];
                    $kiralik_takim = $kiralayan_takimlar[array_rand($kiralayan_takimlar)];

                    $pdo->prepare(
                        "INSERT INTO kiralik_oyuncular
                         (oyuncu_id, oyuncu_isim, mevki, ovr, yas, kaynak_lig, kaynak_takim_id, kiralik_takim_adi, kiralik_lig, baslangic_hafta, bitis_hafta, sezon_yil, maas_katki_yuzde, mac_sayisi, durum)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'diger', ?, ?, ?, ?, 0, 'aktif')"
                    )->execute([
                        $oyuncu_id, $oyuncu['isim'], $oyuncu['mevki'], $oyuncu['ovr'], $oyuncu['yas'],
                        $kaynak_lig, $oyuncu['takim_id'], $kiralik_takim,
                        $guncel_hafta, $bitis_hafta, $guncel_sezon, $maas_katki,
                    ]);

                    // Oyuncuyu kadrodan geçici olarak gizle (fitness sıfırla = kiralık)
                    $pdo->prepare("UPDATE $oyuncu_tbl SET ilk_11 = 0, yedek = 0 WHERE id = ?")->execute([$oyuncu_id]);

                    $mesaj = "✈️ " . htmlspecialchars($oyuncu['isim']) . " " . htmlspecialchars($kiralik_takim) . "'e kiralık gönderildi! (Hafta $guncel_hafta – $bitis_hafta)";
                    $mesaj_tipi = "success";
                }
            }
        } catch (Throwable $e) {
            $mesaj = "İşlem hatası."; $mesaj_tipi = "danger";
        }
    }
}

// --- Kiralık Oyuncunun Maç Sayısını / OVR'yi Güncelle (Sezon ilerledikçe gelişim) ---
if (isset($_POST['kiralik_guncelle'])) {
    $kiralik_id = (int)($_POST['kiralik_id'] ?? 0);
    try {
        $k_stmt = $pdo->prepare("SELECT * FROM kiralik_oyuncular WHERE id = ? AND durum = 'aktif'");
        $k_stmt->execute([$kiralik_id]);
        $k = $k_stmt->fetch(PDO::FETCH_ASSOC);

        if ($k) {
            $yeni_mac   = $k['mac_sayisi'] + mt_rand(1, 3);
            $ovr_artis  = $yeni_mac >= 10 ? 1 : 0; // 10 maç sonrası +1 OVR

            $pdo->prepare("UPDATE kiralik_oyuncular SET mac_sayisi = ? WHERE id = ?")->execute([$yeni_mac, $kiralik_id]);

            if ($ovr_artis > 0) {
                $oyuncu_tbl = $tbl_oyuncu[$k['kaynak_lig']];
                try {
                    $pdo->prepare("UPDATE $oyuncu_tbl SET ovr = ovr + ? WHERE id = ? AND ovr < 99")->execute([$ovr_artis, $k['oyuncu_id']]);
                } catch (Throwable $e) {}
            }
            $mesaj = "📊 " . htmlspecialchars($k['oyuncu_isim']) . " güncellendi. Toplam maç: $yeni_mac";
            $mesaj_tipi = "info";
        }
    } catch (Throwable $e) {
        $mesaj = "Güncelleme hatası."; $mesaj_tipi = "danger";
    }
}

// --- Kiralığı Geri Çağır ---
if (isset($_POST['geri_cagir'])) {
    $kiralik_id = (int)($_POST['kiralik_id'] ?? 0);
    try {
        $pdo->prepare("UPDATE kiralik_oyuncular SET durum = 'geri_cagrildi' WHERE id = ?")->execute([$kiralik_id]);
        $mesaj = "Oyuncu geri çağrıldı."; $mesaj_tipi = "info";
    } catch (Throwable $e) {
        $mesaj = "Geri çağırma hatası."; $mesaj_tipi = "danger";
    }
}

// --- Kiralık Bitişini Kontrol Et ---
try {
    $pdo->query(
        "UPDATE kiralik_oyuncular SET durum = 'bitti'
         WHERE durum = 'aktif' AND bitis_hafta <= $guncel_hafta"
    );
} catch (Throwable $e) {}

// --- Kullanıcı Oyuncularını Çek ---
$kendi_oyuncular = [];
foreach ($benim_takimlarim as $bt) {
    $oyuncu_tbl = $tbl_oyuncu[$bt['kaynak']];
    try {
        $rows = $pdo->prepare(
            "SELECT o.id, o.isim, o.mevki, o.ovr, o.yas, t.takim_adi, '{$bt['kaynak']}' AS kaynak
             FROM $oyuncu_tbl o JOIN {$tbl_takim[$bt['kaynak']]} t ON o.takim_id = t.id
             WHERE o.takim_id = ?
             ORDER BY o.yas ASC, o.ovr ASC LIMIT 30"
        );
        $rows->execute([$bt['id']]);
        $kendi_oyuncular = array_merge($kendi_oyuncular, $rows->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {}
}

// --- Aktif Kiralıkları Çek ---
$aktif_kiraliklar = [];
try {
    $aktif_kiraliklar = $pdo->query(
        "SELECT * FROM kiralik_oyuncular WHERE durum = 'aktif' ORDER BY bitis_hafta ASC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

// --- Biten Kiralıkları Çek ---
$biten_kiraliklar = [];
try {
    $biten_kiraliklar = $pdo->query(
        "SELECT * FROM kiralik_oyuncular WHERE durum != 'aktif' ORDER BY created_at DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

function paraFormatlaKiralik($sayi) {
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
    <title>Kiralık Sistemi | Loan Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --ln-bg: #080c14; --ln-panel: #0e1520; --ln-blue: #3b82f6;
            --ln-gold: #d4af37; --border: rgba(59,130,246,0.25);
        }
        body { background: var(--ln-bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .ln-navbar { background: rgba(8,12,20,0.96); border-bottom: 2px solid var(--ln-blue); padding: 0 2rem; height: 70px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }

        .ln-hero { background: linear-gradient(135deg, #071428 0%, #080c14 100%); border-bottom: 1px solid var(--border); padding: 2.5rem 2rem; text-align: center; }
        .ln-hero h1 { font-family: 'Oswald', sans-serif; font-size: clamp(2rem,5vw,3.5rem); color: #fff; text-shadow: 0 0 25px rgba(59,130,246,0.4); }

        .panel-card { background: var(--ln-panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
        .panel-hdr  { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .panel-hdr h5 { margin: 0; font-family: 'Oswald', sans-serif; font-size: 1.1rem; letter-spacing: 1px; }

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.88rem; }
        .data-table th { padding: 0.8rem 1rem; color: var(--ln-blue); font-weight: 700; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.4); }
        .data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .data-table tbody tr:hover td { background: rgba(59,130,246,0.04); }

        .ovr-box { background: rgba(212,175,55,0.1); color: var(--ln-gold); font-weight: 800; padding: 3px 8px; border-radius: 4px; font-family: 'Oswald'; font-size: 1rem; }
        .btn-loan { background: var(--ln-blue); border: none; color: #fff; font-weight: 700; padding: 6px 14px; border-radius: 6px; font-size: 0.8rem; transition: 0.2s; white-space: nowrap; }
        .btn-loan:hover { background: #2563eb; transform: scale(1.03); }
        .btn-recall { background: transparent; border: 1px solid #555; color: #888; font-weight: 700; padding: 5px 12px; border-radius: 5px; font-size: 0.78rem; }
        .btn-recall:hover { border-color: #ef4444; color: #ef4444; }
        .btn-update { background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.4); color: #10b981; font-weight: 700; padding: 5px 12px; border-radius: 5px; font-size: 0.78rem; }
        .btn-update:hover { background: #10b981; color: #fff; }

        .select-sm { background: rgba(0,0,0,0.4); border: 1px solid var(--border); color: #fff; border-radius: 5px; padding: 4px 8px; font-size: 0.78rem; }
        .btn-action-outline { background: transparent; border: 1px solid var(--ln-gold); color: var(--ln-gold); font-weight: 700; padding: 8px 18px; border-radius: 4px; text-decoration: none; transition: 0.3s; font-size: 0.9rem; }
        .btn-action-outline:hover { background: var(--ln-gold); color: #000; }
        .empty-state { padding: 2.5rem; text-align: center; color: #444; font-family: 'Oswald', sans-serif; font-size: 1rem; }

        .durum-badge { font-size: 0.7rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; font-family:'Oswald'; }
        .durum-aktif { background: rgba(59,130,246,0.2); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
        .durum-bitti { background: rgba(100,100,100,0.15); color: #888; border: 1px solid rgba(100,100,100,0.2); }
        .durum-geri { background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
    </style>
</head>
<body>

<nav class="ln-navbar">
    <div class="font-oswald fs-5 fw-bold" style="color:var(--ln-blue);">
        <i class="fa-solid fa-arrow-right-arrow-left me-2"></i>KİRALIK SİSTEMİ
    </div>
    <div class="d-flex gap-2">
        <a href="global_transfer.php" class="btn-action-outline"><i class="fa-solid fa-globe me-1"></i>Transfer Borsası</a>
        <a href="index.php" class="btn-action-outline"><i class="fa-solid fa-house me-1"></i>Ana Merkez</a>
    </div>
</nav>

<!-- HERO -->
<div class="ln-hero">
    <h1><i class="fa-solid fa-arrows-rotate me-3" style="color:var(--ln-blue);"></i>KİRALIK SİSTEMİ</h1>
    <p style="color:#aaa; font-size:1rem;">Genç oyuncuları kiralık gönder, düzenli maç süreleriyle gelişmelerini hızlandır!</p>
</div>

<div class="container py-4" style="max-width:1300px;">

    <?php if ($mesaj): ?>
        <div class="alert border-0 fw-bold text-center mb-4" style="background:<?= $mesaj_tipi=='success'?'#10b981':($mesaj_tipi=='danger'?'#ef4444':($mesaj_tipi=='info'?'#3b82f6':'#f59e0b')) ?>; color:#fff;">
            <?= htmlspecialchars($mesaj) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Sol: Kiralığa Gönder -->
        <div class="col-lg-5">
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-plane-departure" style="color:var(--ln-blue);"></i>
                    <h5 style="color:var(--ln-blue);">Kiralığa Gönder</h5>
                </div>
                <div class="p-3">
                    <?php if (empty($kendi_oyuncular)): ?>
                        <div class="empty-state">Kiralığa gönderebileceğiniz oyuncu bulunamadı.</div>
                    <?php else: ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="text-muted small fw-bold mb-1 d-block">OYUNCU SEÇ</label>
                            <select name="oyuncu_id" class="form-select" style="background:#1a1a1a; border-color:rgba(59,130,246,0.3); color:#fff;" required>
                                <option value="">Oyuncu Seç...</option>
                                <?php foreach ($kendi_oyuncular as $o): ?>
                                <option value="<?= $o['id'] ?>" data-lig="<?= $o['kaynak'] ?>">
                                    <?= htmlspecialchars($o['isim']) ?> (OVR <?= $o['ovr'] ?>, <?= $o['yas'] ?> yaş)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kaynak lig - ilk takımın ligi varsayılan -->
                        <input type="hidden" name="kaynak_lig" value="<?= htmlspecialchars($benim_takimlarim[0]['kaynak'] ?? 'tr') ?>" id="kaynak_lig_input">

                        <div class="mb-3">
                            <label class="text-muted small fw-bold mb-1 d-block">KİRALIK SÜRESİ</label>
                            <select name="sure_tipi" class="form-select" style="background:#1a1a1a; border-color:rgba(59,130,246,0.3); color:#fff;">
                                <option value="yarim">Yarım Sezon (~19 Hafta)</option>
                                <option value="tam">Tam Sezon (~38 Hafta)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small fw-bold mb-1 d-block">KİRALAYAN TAKIMIN MAAS KATKISI (%)</label>
                            <select name="maas_katki" class="form-select" style="background:#1a1a1a; border-color:rgba(59,130,246,0.3); color:#fff;">
                                <option value="25">%25 (Kulübünüz maaşın %75'ini öder)</option>
                                <option value="50" selected>%50 (Maaş yarı yarıya bölünür)</option>
                                <option value="75">%75 (Kiralayan kulüp çoğunu öder)</option>
                                <option value="100">%100 (Kiralayan kulüp tamamını öder)</option>
                            </select>
                        </div>

                        <button type="submit" name="kiraliga_gonder" class="btn-loan w-100" style="font-family:'Oswald'; font-size:1.05rem; padding:10px;">
                            <i class="fa-solid fa-paper-plane me-2"></i>KİRALIĞA GÖNDER
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kiralık Gelişim Bilgisi -->
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-chart-line" style="color:#10b981;"></i>
                    <h5 style="color:#10b981;">Gelişim Sistemi</h5>
                </div>
                <div class="p-3 text-muted small" style="line-height:1.8;">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="fa-solid fa-circle-check mt-1" style="color:#10b981;"></i>
                        <span>Kiralık gönderilen oyuncuların <strong style="color:#fff;">maç sayısı</strong> her hafta artar.</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="fa-solid fa-circle-check mt-1" style="color:#10b981;"></i>
                        <span><strong style="color:#fff;">10 veya daha fazla maç</strong> oynayan oyuncular +1 OVR kazanır.</span>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="fa-solid fa-circle-check mt-1" style="color:#10b981;"></i>
                        <span>Kiralayan takım <strong style="color:#fff;">maaşın belirlenen yüzdesini</strong> öder.</span>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <i class="fa-solid fa-circle-info mt-1" style="color:#3b82f6;"></i>
                        <span>Kiralık biterken oyuncu kadronuza geri döner.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sağ: Aktif Kiralıklar -->
        <div class="col-lg-7">
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-list-check" style="color:var(--ln-blue);"></i>
                    <h5 style="color:var(--ln-blue);">Aktif Kiralıklar</h5>
                    <?php if (!empty($aktif_kiraliklar)): ?>
                        <span class="badge rounded-pill ms-1" style="background:var(--ln-blue); font-family:'Oswald';"><?= count($aktif_kiraliklar) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (empty($aktif_kiraliklar)): ?>
                    <div class="empty-state"><i class="fa-solid fa-inbox fa-2x mb-3" style="color:#222;"></i><br>Aktif kiralık yok.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Oyuncu</th>
                                <th>Kiralık Kulüp</th>
                                <th>OVR</th>
                                <th>Maç</th>
                                <th>Bitiş</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aktif_kiraliklar as $k): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($k['oyuncu_isim']) ?></div>
                                    <div style="color:#666; font-size:0.75rem;"><?= htmlspecialchars($k['mevki']) ?> · <?= $k['yas'] ?> yaş</div>
                                </td>
                                <td style="color:#aaa; font-size:0.85rem;"><?= htmlspecialchars($k['kiralik_takim_adi']) ?></td>
                                <td><span class="ovr-box"><?= $k['ovr'] ?></span></td>
                                <td>
                                    <span style="color:#10b981; font-weight:700;"><?= $k['mac_sayisi'] ?></span>
                                    <?php if ($k['mac_sayisi'] >= 10): ?>
                                        <span style="color:#d4af37; font-size:0.7rem; margin-left:4px;">★ Gelişti</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:<?= ($k['bitis_hafta'] - $guncel_hafta) <= 3 ? '#ef4444' : '#aaa' ?>; font-size:0.85rem;">
                                    Hafta <?= $k['bitis_hafta'] ?>
                                    <?php if (($k['bitis_hafta'] - $guncel_hafta) <= 3): ?>
                                        <br><small style="color:#ef4444;">⚠ Yakında bitiyor</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <form method="POST">
                                            <input type="hidden" name="kiralik_id" value="<?= $k['id'] ?>">
                                            <button type="submit" name="kiralik_guncelle" class="btn-update w-100">
                                                <i class="fa-solid fa-rotate me-1"></i>Güncelle
                                            </button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="kiralik_id" value="<?= $k['id'] ?>">
                                            <button type="submit" name="geri_cagir" class="btn-recall w-100">
                                                <i class="fa-solid fa-rotate-left me-1"></i>Geri Çağır
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Biten Kiralıklar -->
            <?php if (!empty($biten_kiraliklar)): ?>
            <div class="panel-card">
                <div class="panel-hdr">
                    <i class="fa-solid fa-clock-rotate-left" style="color:#888;"></i>
                    <h5 style="color:#888;">Geçmiş Kiralıklar</h5>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Oyuncu</th><th>Kiralık Kulüp</th><th>Maç</th><th>Durum</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($biten_kiraliklar as $k): ?>
                            <tr>
                                <td class="fw-bold" style="color:#aaa;"><?= htmlspecialchars($k['oyuncu_isim']) ?></td>
                                <td style="color:#666; font-size:0.85rem;"><?= htmlspecialchars($k['kiralik_takim_adi']) ?></td>
                                <td style="color:#10b981;"><?= $k['mac_sayisi'] ?></td>
                                <td>
                                    <span class="durum-badge <?= 'durum-' . str_replace('_', '', $k['durum']) ?>">
                                        <?= $k['durum'] === 'bitti' ? 'BİTTİ' : 'GERİ ÇAĞIRILDI' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Oyuncu seçildiğinde lig bilgisini güncelle
document.querySelector('select[name="oyuncu_id"]')?.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const lig = opt.dataset.lig || 'tr';
    document.getElementById('kaynak_lig_input').value = lig;
});
</script>
</body>
</html>
