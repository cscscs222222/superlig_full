<?php
// ==============================================================================
// FAZ 4: SERBEST OYUNCU / BOSMAN KURALI - 6 AY KALAN SÖZLEŞMELİ OYUNCULAR
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
function fetch_kullanici_bosman($pdo, $ayar_tablo, $takim_tablo, $lig_kodu) {
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
        $t = fetch_kullanici_bosman($pdo, $ayar_tbl, $tbl_takim[$kod], $kod);
        if ($t) $benim_takimlarim[] = $t;
    } catch (Throwable $e) {}
}

$guncel_hafta = 1;
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// --- Ön Anlaşma İmzala (Bosman Transfer) ---
if (isset($_POST['on_anlasma_imzala'])) {
    $oyuncu_id   = (int)($_POST['oyuncu_id'] ?? 0);
    $eski_lig    = $_POST['eski_lig'] ?? '';
    $takim_secim = explode('_', $_POST['alici_takim'] ?? '');

    if (!in_array($eski_lig, $gecerli_liglar, true)) {
        $mesaj = "Geçersiz kaynak lig."; $mesaj_tipi = "danger";
    } elseif (count($takim_secim) !== 2 || !in_array($takim_secim[0], $gecerli_liglar, true)) {
        $mesaj = "Geçersiz hedef takım."; $mesaj_tipi = "danger";
    } else {
        $yeni_lig     = $takim_secim[0];
        $yeni_takim   = (int)$takim_secim[1];
        $oyuncu_tbl   = $tbl_oyuncu[$eski_lig];
        $yeni_takim_t = $tbl_takim[$yeni_lig];

        try {
            // Oyuncuyu çek ve sözleşme kontrolü yap
            $o_stmt = $pdo->prepare("SELECT * FROM $oyuncu_tbl WHERE id = ?");
            $o_stmt->execute([$oyuncu_id]);
            $oyuncu = $o_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oyuncu) {
                $mesaj = "Oyuncu bulunamadı."; $mesaj_tipi = "danger";
            } elseif (($oyuncu['sozlesme_ay'] ?? 36) > 6) {
                $mesaj = "Bu oyuncunun sözleşmesi 6 aydan fazla devam ediyor. Bosman kuralı uygulanamaz."; $mesaj_tipi = "warning";
            } else {
                // Zaten ön anlaşma var mı?
                $mevcut = $pdo->prepare(
                    "SELECT id FROM on_anlasma WHERE oyuncu_id = ? AND eski_lig = ? AND sezon_yil = ? AND gecerli = 1"
                );
                $mevcut->execute([$oyuncu_id, $eski_lig, $guncel_sezon]);
                if ($mevcut->fetchColumn()) {
                    $mesaj = "Bu oyuncuyla zaten bu sezon ön anlaşma yapıldı."; $mesaj_tipi = "warning";
                } else {
                    $pdo->beginTransaction();
                    // Ön anlaşma kaydı
                    $pdo->prepare(
                        "INSERT INTO on_anlasma
                         (oyuncu_id, oyuncu_isim, mevki, ovr, yas, eski_takim_id, eski_lig, yeni_takim_id, yeni_lig, imzalandi_hafta, sezon_yil, gecerli)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)"
                    )->execute([
                        $oyuncu_id, $oyuncu['isim'], $oyuncu['mevki'], $oyuncu['ovr'], $oyuncu['yas'],
                        $oyuncu['takim_id'], $eski_lig, $yeni_takim, $yeni_lig,
                        $guncel_hafta, $guncel_sezon,
                    ]);

                    // Oyuncuyu yeni takıma aktar (bonservis yok, ücretsiz)
                    $pdo->prepare(
                        "INSERT INTO {$tbl_oyuncu[$yeni_lig]}
                         (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek, form, fitness, moral, ceza_hafta, sakatlik_hafta, sozlesme_ay)
                         VALUES (?, ?, ?, ?, ?, ?, (SELECT lig FROM $yeni_takim_t WHERE id = ? LIMIT 1), 0, 1, ?, ?, ?, 0, 0, 24)"
                    )->execute([
                        $yeni_takim, $oyuncu['isim'], $oyuncu['mevki'], $oyuncu['ovr'], $oyuncu['yas'],
                        $oyuncu['fiyat'], $yeni_takim,
                        $oyuncu['form'] ?? 6, $oyuncu['fitness'] ?? 100, $oyuncu['moral'] ?? 80,
                    ]);

                    // Eski ligden sil
                    $pdo->prepare("DELETE FROM $oyuncu_tbl WHERE id = ?")->execute([$oyuncu_id]);
                    $pdo->commit();

                    $mesaj = "✅ " . htmlspecialchars($oyuncu['isim']) . " ücretsiz olarak kadronuza katıldı! (Bosman Kuralı)";
                    $mesaj_tipi = "success";
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $mesaj = "İşlem hatası: " . $e->getMessage(); $mesaj_tipi = "danger";
        }
    }
}

// --- Sözleşmesi 6 Ay veya Daha Az Kalan Oyuncuları Çek ---
$serbest_oyuncular = [];
foreach ($gecerli_liglar as $lig) {
    $oyuncu_tbl = $tbl_oyuncu[$lig];
    $takim_tbl  = $tbl_takim[$lig];
    try {
        // Sütun yoksa hata almamak için COALESCE kullan
        $rows = $pdo->query(
            "SELECT o.id, o.takim_id, o.isim, o.mevki, o.ovr, o.yas, o.fiyat,
                    COALESCE(o.sozlesme_ay, 36) AS sozlesme_ay,
                    t.takim_adi, t.logo, '$lig' AS kaynak
             FROM $oyuncu_tbl o
             JOIN $takim_tbl t ON o.takim_id = t.id
             WHERE COALESCE(o.sozlesme_ay, 36) <= 6
             ORDER BY o.ovr DESC
             LIMIT 30"
        )->fetchAll(PDO::FETCH_ASSOC);
        $serbest_oyuncular = array_merge($serbest_oyuncular, $rows);
    } catch (Throwable $e) {}
}

// Kendi takımlarındaki oyuncuları dışla
$kendi_takim_ids = [];
foreach ($benim_takimlarim as $bt) {
    $kendi_takim_ids[$bt['kaynak'] . '_' . $bt['id']] = true;
}
$serbest_oyuncular = array_filter($serbest_oyuncular, function ($o) use ($kendi_takim_ids) {
    return !isset($kendi_takim_ids[$o['kaynak'] . '_' . $o['takim_id']]);
});

// OVR'ye göre sırala
usort($serbest_oyuncular, fn($a, $b) => $b['ovr'] <=> $a['ovr']);

function paraFormatlaBosman($sayi) {
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
    <title>Serbest Oyuncular | Bosman Kuralı</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bos-bg: #0a0a12; --bos-panel: #10101e; --bos-purple: #8b5cf6;
            --bos-gold: #d4af37; --border: rgba(139,92,246,0.25);
        }
        body { background: var(--bos-bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .bos-navbar { background: rgba(10,10,18,0.96); border-bottom: 2px solid var(--bos-purple); padding: 0 2rem; height: 70px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1000; }

        .bos-hero { background: linear-gradient(135deg, #120a28 0%, #0a0a12 100%); border-bottom: 1px solid var(--border); padding: 2.5rem 2rem; text-align: center; background-image: radial-gradient(circle at 50% 0%, rgba(139,92,246,0.12) 0%, transparent 60%); }
        .bos-hero h1 { font-family: 'Oswald', sans-serif; font-size: clamp(2rem,5vw,3.5rem); color: #fff; text-shadow: 0 0 25px rgba(139,92,246,0.4); }

        .panel-card { background: var(--bos-panel); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; margin-bottom: 1.5rem; }
        .panel-hdr  { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .panel-hdr h5 { margin: 0; font-family: 'Oswald', sans-serif; font-size: 1.1rem; letter-spacing: 1px; }

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 0.9rem 1rem; color: var(--bos-purple); font-weight: 700; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.5); }
        .data-table td { padding: 0.8rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.04); vertical-align: middle; }
        .data-table tbody tr:hover td { background: rgba(139,92,246,0.05); }

        .ovr-box { background: rgba(212,175,55,0.12); color: var(--bos-gold); font-weight: 800; padding: 4px 9px; border-radius: 4px; font-family: 'Oswald'; font-size: 1.1rem; border: 1px solid rgba(212,175,55,0.3); }
        .kontrat-badge { background: rgba(239,68,68,0.15); color: #ef4444; font-weight: 700; padding: 3px 9px; border-radius: 4px; font-size: 0.8rem; border: 1px solid rgba(239,68,68,0.3); }
        .btn-imzala-bos { background: var(--bos-purple); border: none; color: #fff; font-weight: 700; padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; transition: 0.2s; white-space: nowrap; }
        .btn-imzala-bos:hover { background: #7c3aed; transform: scale(1.04); color: #fff; }
        .select-small { background: rgba(0,0,0,0.4); border: 1px solid var(--border); color: #fff; border-radius: 5px; padding: 5px 8px; font-size: 0.8rem; min-width: 140px; }
        .btn-action-outline { background: transparent; border: 1px solid var(--bos-gold); color: var(--bos-gold); font-weight: 700; padding: 8px 18px; border-radius: 4px; text-decoration: none; transition: 0.3s; font-size: 0.9rem; }
        .btn-action-outline:hover { background: var(--bos-gold); color: #000; }
        .empty-state { padding: 3rem; text-align: center; color: #444; font-family: 'Oswald', sans-serif; font-size: 1.2rem; }

        .league-badge { font-size: 0.65rem; padding: 3px 6px; border-radius: 4px; font-weight: 800; text-transform: uppercase; }
        .l-tr { background:#e11d48; color:#fff; } .l-pl { background:#a855f7; color:#fff; }
        .l-es { background:#f59e0b; color:#000; } .l-de { background:#ef4444; color:#fff; }
        .l-it { background:#10b981; color:#fff; } .l-cl { background:#00e5ff; color:#000; }
        .l-fr { background:#3b82f6; color:#fff; } .l-pt { background:#8b5cf6; color:#fff; }
    </style>
</head>
<body>

<nav class="bos-navbar">
    <div class="font-oswald fs-5 fw-bold" style="color:var(--bos-purple);">
        <i class="fa-solid fa-handshake me-2"></i>BOSMAN KURALI / SERBEST OYUNCULAR
    </div>
    <div class="d-flex gap-2">
        <a href="global_transfer.php" class="btn-action-outline"><i class="fa-solid fa-globe me-1"></i>Transfer Borsası</a>
        <a href="index.php" class="btn-action-outline"><i class="fa-solid fa-house me-1"></i>Ana Merkez</a>
    </div>
</nav>

<!-- HERO -->
<div class="bos-hero">
    <h1><i class="fa-solid fa-file-contract me-3" style="color:var(--bos-purple);"></i>SERBEST OYUNCU PAZARI</h1>
    <p style="color:#aaa; font-size:1rem; max-width:650px; margin:0.5rem auto 0;">
        Sözleşmesinin bitmesine <strong style="color:#ef4444;">6 ay veya daha az</strong> kalan oyuncularla
        <strong style="color:var(--bos-purple);">bonservis bedeli ödemeden</strong> doğrudan ön anlaşma yap.
    </p>
</div>

<div class="container py-4" style="max-width:1300px;">

    <?php if ($mesaj): ?>
        <div class="alert border-0 fw-bold text-center mb-4" style="background:<?= $mesaj_tipi=='success'?'#10b981':($mesaj_tipi=='danger'?'#ef4444':($mesaj_tipi=='warning'?'#f59e0b':'#3b82f6')) ?>; color:<?= $mesaj_tipi=='warning'?'#000':'#fff' ?>;">
            <?= htmlspecialchars($mesaj) ?>
        </div>
    <?php endif; ?>

    <div class="panel-card">
        <div class="panel-hdr">
            <i class="fa-solid fa-users" style="color:var(--bos-purple);"></i>
            <h5 style="color:var(--bos-purple);">Sözleşmesi Dolmak Üzere Olan Yıldızlar</h5>
            <span class="badge rounded-pill ms-2" style="background:var(--bos-purple); font-family:'Oswald';"><?= count($serbest_oyuncular) ?></span>
        </div>

        <?php if (empty($serbest_oyuncular)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-face-smile fa-2x mb-3" style="color:#333;"></i><br>
                Şu an sözleşmesi 6 ay veya daha az kalan oyuncu yok.
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Lig</th>
                        <th>Oyuncu</th>
                        <th>Kulüp</th>
                        <th>OVR</th>
                        <th>Yaş</th>
                        <th>Sözleşme</th>
                        <th>Piyasa Değeri</th>
                        <th>Ön Anlaşma</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($serbest_oyuncular as $o): ?>
                    <tr>
                        <td><span class="league-badge l-<?= $o['kaynak'] ?>"><?= strtoupper($o['kaynak']) ?></span></td>
                        <td class="fw-bold text-white"><?= htmlspecialchars($o['isim']) ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:7px;">
                                <img src="<?= htmlspecialchars($o['logo']) ?>" style="width:22px;height:22px;object-fit:contain;">
                                <span style="color:#aaa; font-size:0.85rem;"><?= htmlspecialchars($o['takim_adi']) ?></span>
                            </div>
                        </td>
                        <td><span class="ovr-box"><?= $o['ovr'] ?></span></td>
                        <td style="color:#aaa;"><?= $o['yas'] ?></td>
                        <td><span class="kontrat-badge"><i class="fa-solid fa-clock me-1"></i><?= $o['sozlesme_ay'] ?> Ay</span></td>
                        <td style="color:var(--bos-gold); font-family:'Oswald'; font-size:1.05rem;"><?= paraFormatlaBosman($o['fiyat']) ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-1">
                                <input type="hidden" name="oyuncu_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="eski_lig"  value="<?= htmlspecialchars($o['kaynak']) ?>">
                                <select name="alici_takim" class="select-small" required>
                                    <option value="">Kulüp Seç</option>
                                    <?php foreach ($benim_takimlarim as $bt): ?>
                                    <option value="<?= $bt['kaynak'] . '_' . $bt['id'] ?>">
                                        <?= htmlspecialchars($bt['takim_adi']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="on_anlasma_imzala" class="btn-imzala-bos">
                                    <i class="fa-solid fa-pen me-1"></i>Ücretsiz Al
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bilgi Kutusu -->
    <div class="panel-card">
        <div class="panel-hdr">
            <i class="fa-solid fa-circle-info" style="color:#3b82f6;"></i>
            <h5 style="color:#3b82f6;">Bosman Kuralı Hakkında</h5>
        </div>
        <div class="p-4" style="color:#aaa; line-height:1.8;">
            <p><strong style="color:#fff;">Bosman Kuralı (1995)</strong>, sözleşmesi sona eren futbolcuların mevcut kulüplerine herhangi bir bonservis bedeli ödenmeksizin başka bir kulübe transfer olabilmesini sağlar.</p>
            <ul>
                <li>Sözleşmesine <strong style="color:#ef4444;">6 ay veya daha az</strong> kalan oyuncularla doğrudan görüşme ve ön anlaşma yapılabilir.</li>
                <li>Transfer ücreti <strong style="color:var(--bos-purple);">sıfır (€0)</strong> — yalnızca maaş anlaşması gerekir.</li>
                <li>Anlaşma imzalandığında oyuncu kadronuza katılır; mevcut kulübüne para ödenmez.</li>
            </ul>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
