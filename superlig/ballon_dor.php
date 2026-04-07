<?php
// ==============================================================================
// FAZ 6: BALLON D'OR (ALTIN TOP) GALASI
// Avrupa'nın tüm liglerindeki oyuncuları karşılaştırır, sezon en iyisini seçer
// ve awards_history tablosuna kaydeder.
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

// Lig katsayıları (Ballon d'Or hesaplamasında lig ağırlıkları)
$lig_katsayi = [
    'pl' => 2.0,  // Premier Lig
    'es' => 2.0,  // La Liga
    'de' => 1.8,  // Bundesliga
    'it' => 1.8,  // Serie A
    'tr' => 1.5,  // Süper Lig
    'fr' => 1.6,  // Ligue 1
    'pt' => 1.4,  // Liga NOS
    'cl' => 0.0,  // CL oyuncuları kendi liglerinden değerlendirilir
];

$tbl_oyuncu = [
    'tr' => 'oyuncular',
    'pl' => 'pl_oyuncular',
    'es' => 'es_oyuncular',
    'de' => 'de_oyuncular',
    'it' => 'it_oyuncular',
    'fr' => 'fr_oyuncular',
    'pt' => 'pt_oyuncular',
];
$tbl_takim = [
    'tr' => 'takimlar',
    'pl' => 'pl_takimlar',
    'es' => 'es_takimlar',
    'de' => 'de_takimlar',
    'it' => 'it_takimlar',
    'fr' => 'fr_takimlar',
    'pt' => 'pt_takimlar',
];
$lig_etiket = [
    'tr' => 'Süper Lig',
    'pl' => 'Premier League',
    'es' => 'La Liga',
    'de' => 'Bundesliga',
    'it' => 'Serie A',
    'fr' => 'Ligue 1',
    'pt' => 'Liga NOS',
];

$guncel_sezon = 2025;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// ================================================================
// BALLON D'OR HESAPLAMA FONKSİYONU
// Puan = (sezon_gol * 3) + (sezon_asist * 1.5) + (mac_puani_ort * 5) + (ovr * 0.5) + kupa_bonusu
// ================================================================
function ballon_dor_puan(int $gol, int $asist, float $mac_puan, int $ovr, int $kupa_bonus): float {
    return ($gol * 3) + ($asist * 1.5) + ($mac_puan * 5) + ($ovr * 0.5) + ($kupa_bonus * 10);
}

// ================================================================
// TÜM LİGLERDEKİ ADAY LİSTESİNİ OLUŞTUR
// ================================================================
$adaylar = [];

foreach ($tbl_oyuncu as $lig => $tbl) {
    $takim_tbl = $tbl_takim[$lig];
    try {
        $stmt = $pdo->query(
            "SELECT o.isim, o.ovr, o.mevki,
                    COALESCE(o.sezon_gol, 0)     AS gol,
                    COALESCE(o.sezon_asist, 0)   AS asist,
                    COALESCE(o.mac_puani_ort, 6) AS mac_puan,
                    t.takim_adi
             FROM $tbl o
             JOIN $takim_tbl t ON t.id = o.takim_id
             WHERE o.ovr >= 75
             ORDER BY o.ovr DESC
             LIMIT 30"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            // Kupa bonusu: UCL şampiyonu takım oyuncularına +3 puan
            $kupa_bonus = 0;
            try {
                $ucl = $pdo->query(
                    "SELECT COUNT(*) FROM cl_maclar WHERE (ev_takim = '{$r['takim_adi']}' OR dep_takim = '{$r['takim_adi']}') AND tur = 'final' AND oynandi = 1 LIMIT 1"
                )->fetchColumn();
                if ($ucl) $kupa_bonus += 3;
            } catch (Throwable $e) {}

            $puan = ballon_dor_puan(
                (int)$r['gol'],
                (int)$r['asist'],
                (float)$r['mac_puan'],
                (int)$r['ovr'],
                $kupa_bonus
            );
            $adaylar[] = [
                'isim'        => $r['isim'],
                'takim_adi'   => $r['takim_adi'],
                'lig_kodu'    => $lig,
                'lig_adi'     => $lig_etiket[$lig],
                'ovr'         => (int)$r['ovr'],
                'mevki'       => $r['mevki'],
                'gol'         => (int)$r['gol'],
                'asist'       => (int)$r['asist'],
                'mac_puan'    => (float)$r['mac_puan'],
                'kupa_bonus'  => $kupa_bonus,
                'toplam_puan' => $puan,
            ];
        }
    } catch (Throwable $e) {}
}

// En yüksek puana göre sırala
usort($adaylar, fn($a, $b) => $b['toplam_puan'] <=> $a['toplam_puan']);
$kazanan = $adaylar[0] ?? null;

// ================================================================
// BALLON D'OR VERME (Sezon sonu aksiyonu)
// ================================================================
if (isset($_POST['odul_ver']) && $kazanan) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO awards_history
                (sezon_yil, odul_turu, oyuncu_isim, takim_adi, takim_lig, ovr, gol, asist, mac_puani, kupa_bonusu, toplam_puan)
             VALUES (?, 'ballon_dor', ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE toplam_puan = VALUES(toplam_puan)"
        );
        $stmt->execute([
            $guncel_sezon,
            $kazanan['isim'],
            $kazanan['takim_adi'],
            $kazanan['lig_kodu'],
            $kazanan['ovr'],
            $kazanan['gol'],
            $kazanan['asist'],
            $kazanan['mac_puan'],
            $kazanan['kupa_bonus'],
            $kazanan['toplam_puan'],
        ]);
        $mesaj      = "🏆 " . htmlspecialchars($kazanan['isim']) . " " . $guncel_sezon . " Ballon d'Or ödülünü kazandı!";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// ================================================================
// GEÇMİŞ KAZANANLAR
// ================================================================
$gecmis = [];
try {
    $gecmis = $pdo->query(
        "SELECT * FROM awards_history WHERE odul_turu = 'ballon_dor' ORDER BY sezon_yil DESC LIMIT 10"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ballon d'Or | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background: #050505; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; }
    .bg-overlay { position: fixed; top:0; left:0; width:100vw; height:100vh; background: radial-gradient(ellipse at top, #1a0a00 0%, #050505 70%); z-index:-1; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    .gold { color:#d4af37; }
    .gold-gradient { background: linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .glass-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:20px; backdrop-filter:blur(20px); padding:24px; transition:all .3s; }
    .glass-card:hover { border-color:rgba(212,175,55,0.4); box-shadow:0 0 30px rgba(212,175,55,0.15); }
    .hero { text-align:center; padding:60px 20px 30px; }
    .hero h1 { font-size:3.5rem; font-weight:900; line-height:1.1; }
    .winner-card { background:linear-gradient(135deg,rgba(212,175,55,0.25),rgba(253,224,71,0.08)); border:2px solid #d4af37; border-radius:24px; padding:40px; text-align:center; box-shadow:0 0 60px rgba(212,175,55,0.3); }
    .winner-ovr { font-size:5rem; font-weight:900; color:#d4af37; line-height:1; }
    .winner-name { font-size:2.2rem; font-weight:800; margin-top:10px; }
    .winner-info { color:#94a3b8; font-size:1rem; margin-top:6px; }
    .stat-badge { display:inline-block; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:12px; padding:8px 18px; margin:5px; font-size:0.9rem; }
    .rank-1 { color:#d4af37; }
    .rank-2 { color:#94a3b8; }
    .rank-3 { color:#cd7f32; }
    .table-dark-custom { background:transparent; }
    .table-dark-custom th { color:#d4af37; border-color:rgba(255,255,255,0.08); font-family:'Oswald',sans-serif; letter-spacing:1px; }
    .table-dark-custom td { border-color:rgba(255,255,255,0.06); vertical-align:middle; }
    .table-dark-custom tbody tr:hover { background:rgba(212,175,55,0.06); }
    .btn-gold { background:linear-gradient(135deg,#d4af37,#fde047); color:#000; font-weight:800; border:none; border-radius:12px; padding:14px 40px; font-size:1.1rem; letter-spacing:1px; }
    .btn-gold:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(212,175,55,0.5); color:#000; }
    .lig-badge { font-size:0.75rem; padding:3px 10px; border-radius:20px; background:rgba(255,255,255,0.1); }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.9rem; }
    .back-btn:hover { color:#d4af37; }
    .section-title { font-size:1.5rem; font-weight:800; margin-bottom:20px; border-left:3px solid #d4af37; padding-left:14px; }
</style>
</head>
<body>
<div class="bg-overlay"></div>

<div class="container-fluid px-4 pb-5">
    <!-- NAVBAR -->
    <div class="d-flex align-items-center py-4">
        <a href="index.php" class="back-btn me-auto"><i class="fa-solid fa-arrow-left me-2"></i>Ana Menü</a>
        <span class="font-oswald gold" style="font-size:1.1rem;letter-spacing:2px;">ULTIMATE MANAGER</span>
    </div>

    <!-- HERO -->
    <div class="hero">
        <div style="font-size:4rem; margin-bottom:10px;">🏆</div>
        <h1 class="font-oswald"><span class="gold-gradient">BALLON D'OR</span></h1>
        <p class="text-secondary mt-2">Avrupa'nın En İyi Oyuncusu — <?= $guncel_sezon ?> Sezonu</p>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj_tipi ?> glass-card mb-4"><?= $mesaj ?></div>
    <?php endif; ?>

    <!-- KAZANAN KARTI -->
    <?php if ($kazanan): ?>
    <div class="row justify-content-center mb-5">
        <div class="col-lg-6">
            <div class="winner-card">
                <div class="mb-2"><i class="fa-solid fa-star" style="color:#d4af37; font-size:1.5rem;"></i><i class="fa-solid fa-star" style="color:#d4af37; font-size:2rem;"></i><i class="fa-solid fa-star" style="color:#d4af37; font-size:1.5rem;"></i></div>
                <div class="winner-ovr"><?= $kazanan['ovr'] ?></div>
                <div class="winner-name font-oswald"><?= htmlspecialchars($kazanan['isim']) ?></div>
                <div class="winner-info"><?= htmlspecialchars($kazanan['takim_adi']) ?> &bull; <span class="lig-badge"><?= htmlspecialchars($kazanan['lig_adi']) ?></span> &bull; <?= htmlspecialchars($kazanan['mevki']) ?></div>
                <div class="mt-4">
                    <span class="stat-badge"><i class="fa-solid fa-futbol me-1 text-success"></i> <?= $kazanan['gol'] ?> Gol</span>
                    <span class="stat-badge"><i class="fa-solid fa-shoe-prints me-1 text-info"></i> <?= $kazanan['asist'] ?> Asist</span>
                    <span class="stat-badge"><i class="fa-solid fa-star me-1 text-warning"></i> <?= number_format($kazanan['mac_puan'], 2) ?> Maç Puanı</span>
                    <span class="stat-badge"><i class="fa-solid fa-trophy me-1 text-danger"></i> +<?= $kazanan['kupa_bonus'] * 10 ?> Kupa</span>
                </div>
                <div class="mt-3" style="font-size:1.8rem; font-weight:900; color:#d4af37;"><?= number_format($kazanan['toplam_puan'], 1) ?> <span style="font-size:1rem; color:#94a3b8;">Ballon d'Or Puanı</span></div>
                <form method="POST" class="mt-4">
                    <button type="submit" name="odul_ver" class="btn btn-gold font-oswald">
                        <i class="fa-solid fa-award me-2"></i>ÖDÜLÜ VER!
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ADAY LİSTESİ -->
    <div class="glass-card mb-5">
        <div class="section-title">🏅 Tüm Adaylar — <?= $guncel_sezon ?></div>
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>OYUNCU</th>
                        <th>KULÜP</th>
                        <th>LİG</th>
                        <th>OVR</th>
                        <th>GOL</th>
                        <th>ASİST</th>
                        <th>MAÇ PUANI</th>
                        <th>TOPLAM PUAN</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($adaylar, 0, 20) as $i => $a): ?>
                    <tr<?= $i === 0 ? ' style="background:rgba(212,175,55,0.1);"' : '' ?>>
                        <td class="<?= $i===0 ? 'rank-1' : ($i===1 ? 'rank-2' : ($i===2 ? 'rank-3' : '')) ?> fw-bold">
                            <?= $i===0 ? '🏆' : ($i===1 ? '🥈' : ($i===2 ? '🥉' : ($i+1))) ?>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($a['isim']) ?></td>
                        <td><?= htmlspecialchars($a['takim_adi']) ?></td>
                        <td><span class="lig-badge"><?= htmlspecialchars($a['lig_adi']) ?></span></td>
                        <td><strong class="gold"><?= $a['ovr'] ?></strong></td>
                        <td><?= $a['gol'] ?></td>
                        <td><?= $a['asist'] ?></td>
                        <td><?= number_format($a['mac_puan'], 2) ?></td>
                        <td><strong style="color:#d4af37;"><?= number_format($a['toplam_puan'], 1) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="glass-card text-center py-5 mb-5">
        <i class="fa-solid fa-trophy" style="font-size:4rem; color:#d4af37; opacity:0.4;"></i>
        <p class="mt-4 text-secondary">Aday bulunamadı. Oyuncuların sezon istatistikleri oluştuğunda bu sayfa otomatik güncellenir.</p>
    </div>
    <?php endif; ?>

    <!-- GEÇMİŞ KAZANANLAR -->
    <?php if (!empty($gecmis)): ?>
    <div class="glass-card">
        <div class="section-title">📜 Ballon d'Or Tarihçesi</div>
        <div class="table-responsive">
            <table class="table table-dark-custom table-hover">
                <thead>
                    <tr><th>SEZON</th><th>OYUNCU</th><th>KULÜP</th><th>LİG</th><th>GOL</th><th>ASİST</th><th>PUAN</th></tr>
                </thead>
                <tbody>
                <?php foreach ($gecmis as $g): ?>
                    <tr>
                        <td class="gold fw-bold"><?= $g['sezon_yil'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars($g['oyuncu_isim']) ?></td>
                        <td><?= htmlspecialchars($g['takim_adi']) ?></td>
                        <td><span class="lig-badge"><?= htmlspecialchars($g['takim_lig']) ?></span></td>
                        <td><?= (int)($g['gol'] ?? 0) ?></td>
                        <td><?= (int)($g['asist'] ?? 0) ?></td>
                        <td><?= number_format($g['toplam_puan'], 1) ?></td>
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
