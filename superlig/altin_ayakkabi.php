<?php
// ==============================================================================
// FAZ 6: AVRUPA ALTIN AYAKKABI (EUROPEAN GOLDEN SHOE)
// Tüm liglerden en çok gol atan oyuncuyu seçer, lig katsayısıyla ağırlıklandırır.
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

// Lig katsayıları (UEFA lig katsayılarına dayalı)
$lig_katsayilari = [
    'pl' => ['adi' => 'Premier League', 'katsayi' => 2.0, 'renk' => '#a855f7'],
    'es' => ['adi' => 'La Liga',        'katsayi' => 2.0, 'renk' => '#f59e0b'],
    'de' => ['adi' => 'Bundesliga',     'katsayi' => 1.8, 'renk' => '#ef4444'],
    'it' => ['adi' => 'Serie A',        'katsayi' => 1.8, 'renk' => '#10b981'],
    'tr' => ['adi' => 'Süper Lig',      'katsayi' => 1.5, 'renk' => '#e11d48'],
    'fr' => ['adi' => 'Ligue 1',        'katsayi' => 1.6, 'renk' => '#3b82f6'],
    'pt' => ['adi' => 'Liga NOS',       'katsayi' => 1.4, 'renk' => '#8b5cf6'],
];

$tbl_oyuncu = [
    'tr' => 'oyuncular',    'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular', 'de' => 'de_oyuncular',
    'it' => 'it_oyuncular', 'fr' => 'fr_oyuncular',
    'pt' => 'pt_oyuncular',
];
$tbl_takim = [
    'tr' => 'takimlar',    'pl' => 'pl_takimlar',
    'es' => 'es_takimlar', 'de' => 'de_takimlar',
    'it' => 'it_takimlar', 'fr' => 'fr_takimlar',
    'pt' => 'pt_takimlar',
];

$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// ================================================================
// TÜM LİGLERDEN GOLCÜ LİSTESİ OLUŞTUR
// ================================================================
$golculer = [];
foreach ($tbl_oyuncu as $lig => $tbl) {
    $takim_tbl = $tbl_takim[$lig];
    $lig_info  = $lig_katsayilari[$lig];
    try {
        $stmt = $pdo->query(
            "SELECT o.isim, o.ovr, o.mevki,
                    COALESCE(o.sezon_gol, 0) AS gol,
                    t.takim_adi
             FROM $tbl o
             JOIN $takim_tbl t ON t.id = o.takim_id
             WHERE COALESCE(o.sezon_gol, 0) > 0
             ORDER BY o.sezon_gol DESC
             LIMIT 10"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $ham_gol       = (int)$r['gol'];
            $agirlikli_gol = round($ham_gol * $lig_info['katsayi'], 1);
            $golculer[] = [
                'isim'          => $r['isim'],
                'takim_adi'     => $r['takim_adi'],
                'lig_kodu'      => $lig,
                'lig_adi'       => $lig_info['adi'],
                'lig_renk'      => $lig_info['renk'],
                'katsayi'       => $lig_info['katsayi'],
                'ovr'           => (int)$r['ovr'],
                'ham_gol'       => $ham_gol,
                'agirlikli_gol' => $agirlikli_gol,
            ];
        }
    } catch (Throwable $e) {}
}

// Ağırlıklı gole göre sırala
usort($golculer, fn($a, $b) => $b['agirlikli_gol'] <=> $a['agirlikli_gol']);
$kazanan = $golculer[0] ?? null;

// ================================================================
// ALTIN AYAKKABI VERME
// ================================================================
if (isset($_POST['odul_ver']) && $kazanan) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO altin_ayakkabi_log
                (sezon_yil, oyuncu_isim, takim_adi, lig_kodu, lig_adi, ham_gol, katsayi, agirlikli_gol)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $guncel_sezon,
            $kazanan['isim'],
            $kazanan['takim_adi'],
            $kazanan['lig_kodu'],
            $kazanan['lig_adi'],
            $kazanan['ham_gol'],
            $kazanan['katsayi'],
            $kazanan['agirlikli_gol'],
        ]);
        // awards_history'e de kaydet
        $pdo->prepare(
            "INSERT INTO awards_history
                (sezon_yil, odul_turu, oyuncu_isim, takim_adi, takim_lig, ovr, gol, toplam_puan)
             VALUES (?, 'altin_ayakkabi', ?, ?, ?, ?, ?, ?)"
        )->execute([
            $guncel_sezon,
            $kazanan['isim'],
            $kazanan['takim_adi'],
            $kazanan['lig_kodu'],
            $kazanan['ovr'],
            $kazanan['ham_gol'],
            $kazanan['agirlikli_gol'],
        ]);
        $mesaj      = "👟 " . htmlspecialchars($kazanan['isim']) . " " . $guncel_sezon . " Avrupa Altın Ayakkabısını kazandı!";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// Geçmiş kazananlar
$gecmis = [];
try {
    $gecmis = $pdo->query("SELECT * FROM altin_ayakkabi_log ORDER BY sezon_yil DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Altın Ayakkabı | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#050505; color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
    .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:radial-gradient(ellipse at top, #001a00 0%, #050505 70%); z-index:-1; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    .gold { color:#d4af37; }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .glass-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:20px; backdrop-filter:blur(20px); padding:24px; margin-bottom:24px; }
    .winner-card { background:linear-gradient(135deg,rgba(16,185,129,0.2),rgba(5,150,105,0.05)); border:2px solid #10b981; border-radius:24px; padding:40px; text-align:center; box-shadow:0 0 60px rgba(16,185,129,0.25); }
    .shoe-icon { font-size:5rem; }
    .winner-name { font-size:2.2rem; font-weight:800; margin-top:10px; }
    .winner-info { color:#94a3b8; font-size:1rem; margin-top:6px; }
    .big-gol { font-size:5rem; font-weight:900; color:#10b981; line-height:1; }
    .stat-badge { display:inline-block; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:8px 18px; margin:5px; font-size:0.9rem; }
    .table-dark-custom { background:transparent; }
    .table-dark-custom th { color:#10b981; border-color:rgba(255,255,255,0.08); font-family:'Oswald',sans-serif; letter-spacing:1px; }
    .table-dark-custom td { border-color:rgba(255,255,255,0.06); vertical-align:middle; }
    .table-dark-custom tbody tr:hover { background:rgba(16,185,129,0.06); }
    .btn-green { background:linear-gradient(135deg,#10b981,#34d399); color:#000; font-weight:800; border:none; border-radius:12px; padding:14px 40px; font-size:1.1rem; letter-spacing:1px; }
    .btn-green:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.4); color:#000; }
    .lig-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; }
    .lig-badge { font-size:0.75rem; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,0.1); }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.9rem; }
    .back-btn:hover { color:#10b981; }
    .section-title { font-size:1.5rem; font-weight:800; margin-bottom:20px; border-left:3px solid #10b981; padding-left:14px; }
    .progress-bar-wrapper { height:8px; background:rgba(255,255,255,0.08); border-radius:4px; overflow:hidden; margin-top:4px; }
    .progress-fill { height:100%; border-radius:4px; }
</style>
</head>
<body>
<div class="bg-overlay"></div>
<div class="container-fluid px-4 pb-5">
    <div class="d-flex align-items-center py-4">
        <a href="index.php" class="back-btn me-auto"><i class="fa-solid fa-arrow-left me-2"></i>Ana Menü</a>
        <span class="font-oswald gold" style="font-size:1.1rem;letter-spacing:2px;">ULTIMATE MANAGER</span>
    </div>

    <!-- HERO -->
    <div class="text-center py-4 mb-4">
        <div style="font-size:4rem; margin-bottom:10px;">👟</div>
        <h1 class="font-oswald" style="font-size:3rem;"><span class="gold-gradient">AVRUPA ALTIN AYAKKABI</span></h1>
        <p class="text-secondary mt-2">Tüm Avrupa liglerinin golcü krallığı — <?= $guncel_sezon ?> Sezonu</p>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj_tipi ?> glass-card mb-4"><?= $mesaj ?></div>
    <?php endif; ?>

    <!-- LİG KATSAYI BİLGİSİ -->
    <div class="glass-card mb-4">
        <div class="section-title">⚖️ Lig Katsayıları</div>
        <div class="row g-2">
        <?php foreach ($lig_katsayilari as $k => $l): ?>
            <div class="col-md-3 col-6">
                <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:12px; text-align:center;">
                    <div style="font-size:0.75rem; color:#94a3b8;"><?= $l['adi'] ?></div>
                    <div style="font-size:1.5rem; font-weight:900; color:<?= $l['renk'] ?>;">×<?= $l['katsayi'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- KAZANAN KARTI -->
    <?php if ($kazanan): ?>
    <div class="row justify-content-center mb-5">
        <div class="col-lg-6">
            <div class="winner-card">
                <div class="shoe-icon">👟</div>
                <div class="big-gol"><?= $kazanan['agirlikli_gol'] ?></div>
                <div style="color:#94a3b8; font-size:0.85rem;">Ağırlıklı Gol (<?= $kazanan['ham_gol'] ?> gol × <?= $kazanan['katsayi'] ?> katsayı)</div>
                <div class="winner-name font-oswald"><?= htmlspecialchars($kazanan['isim']) ?></div>
                <div class="winner-info"><?= htmlspecialchars($kazanan['takim_adi']) ?> &bull; <span class="lig-badge"><?= htmlspecialchars($kazanan['lig_adi']) ?></span></div>
                <form method="POST" class="mt-4">
                    <button type="submit" name="odul_ver" class="btn btn-green font-oswald">
                        <i class="fa-solid fa-shoe-prints me-2"></i>ALTIN AYAKKABIYI VER!
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SIRALAMANIN TAMAMI -->
    <div class="glass-card">
        <div class="section-title">⚽ Golcüler Sıralaması — <?= $guncel_sezon ?></div>
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>OYUNCU</th>
                        <th>KULÜP</th>
                        <th>LİG</th>
                        <th>HAM GOL</th>
                        <th>KATSAYI</th>
                        <th>AĞIRLIKLI GOL</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $max_ag = $golculer[0]['agirlikli_gol'] ?? 1;
                foreach (array_slice($golculer, 0, 25) as $i => $g):
                    $yuzde = $max_ag > 0 ? ($g['agirlikli_gol'] / $max_ag * 100) : 0;
                ?>
                <tr<?= $i===0 ? ' style="background:rgba(16,185,129,0.1);"' : '' ?>>
                    <td class="fw-bold"><?= $i===0 ? '👟' : ($i===1 ? '🥈' : ($i===2 ? '🥉' : ($i+1))) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($g['isim']) ?></td>
                    <td><?= htmlspecialchars($g['takim_adi']) ?></td>
                    <td>
                        <span class="lig-dot" style="background:<?= $g['lig_renk'] ?>;"></span>
                        <?= htmlspecialchars($g['lig_adi']) ?>
                    </td>
                    <td><strong><?= $g['ham_gol'] ?></strong></td>
                    <td><span style="color:#94a3b8;">×<?= $g['katsayi'] ?></span></td>
                    <td>
                        <strong style="color:#10b981;"><?= $g['agirlikli_gol'] ?></strong>
                        <div class="progress-bar-wrapper mt-1" style="max-width:100px;">
                            <div class="progress-fill" style="width:<?= $yuzde ?>%; background:<?= $g['lig_renk'] ?>;"></div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card text-center py-5">
        <i class="fa-solid fa-shoe-prints" style="font-size:4rem; color:#10b981; opacity:0.4;"></i>
        <p class="mt-4 text-secondary">Gol verisi bulunamadı. Sezon maçları başladığında liste otomatik güncellenir.</p>
    </div>
    <?php endif; ?>

    <!-- GEÇMİŞ KAZANANLAR -->
    <?php if (!empty($gecmis)): ?>
    <div class="glass-card mt-4">
        <div class="section-title">📜 Altın Ayakkabı Tarihçesi</div>
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr><th>SEZON</th><th>OYUNCU</th><th>KULÜP</th><th>LİG</th><th>GOL</th><th>AĞIRLIKLI</th></tr>
                </thead>
                <tbody>
                <?php foreach ($gecmis as $g): ?>
                <tr>
                    <td class="fw-bold" style="color:#10b981;"><?= $g['sezon_yil'] ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($g['oyuncu_isim']) ?></td>
                    <td><?= htmlspecialchars($g['takim_adi']) ?></td>
                    <td><span class="lig-badge"><?= htmlspecialchars($g['lig_adi']) ?></span></td>
                    <td><?= $g['ham_gol'] ?></td>
                    <td style="color:#10b981; font-weight:bold;"><?= $g['agirlikli_gol'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
