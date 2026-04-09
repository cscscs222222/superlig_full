<?php
// ==============================================================================
// GLOBAL HAFTA OYNATICI - TÜM LİGLERİ EŞZAMANLI İLERLET
// Dünya genelindeki tüm ligleri ve Avrupa kupalarını tek seferde simüle eder.
// ==============================================================================
include 'db.php';
include 'MatchEngine.php';

set_time_limit(300); // Sezon simülasyonu için yeterli süre

// ================================================================
// YARDIMCI FONKSİYON: Tek bir lig haftasını simüle et
// ================================================================
function simulate_league_week(
    $pdo, $engine,
    string $maclar_tbl,
    string $takimlar_tbl,
    string $ayar_tbl,
    int    $hafta,
    int    $max_hafta,
    int    $kullanici_takim_id = 0
): int {
    // Validate table names against the known allowlist to prevent SQL injection
    static $allowed_tables = [
        'maclar', 'takimlar', 'ayar',
        'pl_maclar', 'pl_takimlar', 'pl_ayar',
        'es_maclar', 'es_takimlar', 'es_ayar',
        'de_maclar', 'de_takimlar', 'de_ayar',
        'it_maclar', 'it_takimlar', 'it_ayar',
        'fr_maclar', 'fr_takimlar', 'fr_ayar',
        'pt_maclar', 'pt_takimlar', 'pt_ayar',
        'cl_maclar', 'cl_takimlar', 'cl_ayar',
        'uel_maclar', 'uel_takimlar', 'uel_ayar',
        'uecl_maclar', 'uecl_takimlar', 'uecl_ayar',
    ];
    if (!in_array($maclar_tbl, $allowed_tables, true) ||
        !in_array($takimlar_tbl, $allowed_tables, true) ||
        !in_array($ayar_tbl, $allowed_tables, true)) {
        return 0;
    }

    $simulated = 0;
    try {
        $stmt = $pdo->prepare(
            "SELECT m.id, m.ev, m.dep
               FROM $maclar_tbl m
              WHERE m.hafta = ? AND m.ev_skor IS NULL"
        );
        $stmt->execute([$hafta]);
        $maclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return 0;
    }

    foreach ($maclar as $m) {
        // Kullanıcı takımının maçını otomatik simüle etme (oyuncu kendisi oynasın)
        if ($kullanici_takim_id && ($m['ev'] == $kullanici_takim_id || $m['dep'] == $kullanici_takim_id)) {
            continue;
        }
        try {
            // Auto-select Starting XI for both teams before simulation
            $engine->auto_ilk_11((int)$m['ev']);
            $engine->auto_ilk_11((int)$m['dep']);

            $skorlar  = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor  = $skorlar['ev'];
            $dep_skor = $skorlar['dep'];
            $ev_det   = $engine->mac_olay_uret($m['ev'],  $ev_skor);
            $dep_det  = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $pdo->prepare(
                "UPDATE $maclar_tbl
                    SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=?
                  WHERE id=?"
            )->execute([
                $ev_skor, $dep_skor,
                $ev_det['olaylar'],  $dep_det['olaylar'],
                $ev_det['kartlar'],  $dep_det['kartlar'],
                $m['id'],
            ]);

            $ev_id  = (int)$m['ev'];
            $dep_id = (int)$m['dep'];
            $ev_s   = (int)$ev_skor;
            $dep_s  = (int)$dep_skor;

            $pdo->prepare("UPDATE $takimlar_tbl SET atilan_gol = atilan_gol + ?, yenilen_gol = yenilen_gol + ? WHERE id = ?")
                ->execute([$ev_s, $dep_s, $ev_id]);
            $pdo->prepare("UPDATE $takimlar_tbl SET atilan_gol = atilan_gol + ?, yenilen_gol = yenilen_gol + ? WHERE id = ?")
                ->execute([$dep_s, $ev_s, $dep_id]);

            if ($ev_s > $dep_s) {
                $pdo->prepare("UPDATE $takimlar_tbl SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=?")->execute([$ev_id]);
                $pdo->prepare("UPDATE $takimlar_tbl SET malubiyet=malubiyet+1 WHERE id=?")->execute([$dep_id]);
            } elseif ($ev_s === $dep_s) {
                $pdo->prepare("UPDATE $takimlar_tbl SET puan=puan+1, beraberlik=beraberlik+1 WHERE id=?")->execute([$ev_id]);
                $pdo->prepare("UPDATE $takimlar_tbl SET puan=puan+1, beraberlik=beraberlik+1 WHERE id=?")->execute([$dep_id]);
            } else {
                $pdo->prepare("UPDATE $takimlar_tbl SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=?")->execute([$dep_id]);
                $pdo->prepare("UPDATE $takimlar_tbl SET malubiyet=malubiyet+1 WHERE id=?")->execute([$ev_id]);
            }
            $simulated++;
        } catch (Throwable $e) {}
    }

    // Hafta bittiyse sayacı artır
    try {
        $kalan = $pdo->prepare("SELECT COUNT(*) FROM $maclar_tbl WHERE hafta=? AND ev_skor IS NULL");
        $kalan->execute([$hafta]);
        if ($kalan->fetchColumn() == 0 && $hafta < $max_hafta) {
            $pdo->exec("UPDATE $ayar_tbl SET hafta = hafta + 1");
        }
    } catch (Throwable $e) {}

    return $simulated;
}

// ================================================================
// YARDIMCI FONKSİYON: Puan tablosu özeti (Top 8 + Bottom 3)
// ================================================================
function get_puan_tablosu_ozet($pdo, $takimlar_tbl, $toplam_takim = 0): string {
    static $allowed_tables = [
        'takimlar', 'pl_takimlar', 'es_takimlar', 'de_takimlar',
        'it_takimlar', 'fr_takimlar', 'pt_takimlar',
        'cl_takimlar', 'uel_takimlar', 'uecl_takimlar',
    ];
    if (!in_array($takimlar_tbl, $allowed_tables, true)) return '';
    try {
        $rows = $pdo->query(
            "SELECT takim_adi, puan, atilan_gol, yenilen_gol FROM $takimlar_tbl
              ORDER BY puan DESC, (atilan_gol-yenilen_gol) DESC, atilan_gol DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return ''; }
    if (empty($rows)) return '';
    $total = count($rows);
    $top8 = array_slice($rows, 0, 8);
    $bottom3 = $total > 8 ? array_slice($rows, -3) : [];
    $out = '<div style="font-size:0.78rem;margin-top:6px;background:rgba(255,255,255,0.05);border-radius:6px;padding:8px 10px;">';
    $out .= '<div style="color:#d4af37;font-weight:700;font-size:0.72rem;text-transform:uppercase;margin-bottom:4px;">Puan Tablosu (İlk 8)</div>';
    foreach ($top8 as $i => $t) {
        $av = (int)$t['atilan_gol'] - (int)$t['yenilen_gol'];
        $avStr = ($av > 0 ? '+' : '') . $av;
        $out .= '<div style="display:flex;gap:8px;padding:2px 0;border-bottom:1px solid rgba(255,255,255,0.05);">'
             . '<span style="width:20px;color:#94a3b8;">' . ($i+1) . '</span>'
             . '<span style="flex:1;">' . htmlspecialchars($t['takim_adi']) . '</span>'
             . '<span style="color:#d4af37;font-weight:700;">' . $t['puan'] . 'P</span>'
             . '<span style="color:#94a3b8;font-size:0.7rem;margin-left:6px;">AV:' . $avStr . '</span>'
             . '</div>';
    }
    if (!empty($bottom3)) {
        $out .= '<div style="color:#6b7280;font-weight:700;font-size:0.72rem;text-transform:uppercase;margin:6px 0 4px;">— Son 3 —</div>';
        $startIdx = $total - count($bottom3);
        foreach ($bottom3 as $i => $t) {
            $av = (int)$t['atilan_gol'] - (int)$t['yenilen_gol'];
            $avStr = ($av > 0 ? '+' : '') . $av;
            $out .= '<div style="display:flex;gap:8px;padding:2px 0;border-bottom:1px solid rgba(255,255,255,0.05);opacity:0.75;">'
                 . '<span style="width:20px;color:#94a3b8;">' . ($startIdx + $i + 1) . '</span>'
                 . '<span style="flex:1;">' . htmlspecialchars($t['takim_adi']) . '</span>'
                 . '<span style="color:#d4af37;font-weight:700;">' . $t['puan'] . 'P</span>'
                 . '<span style="color:#94a3b8;font-size:0.7rem;margin-left:6px;">AV:' . $avStr . '</span>'
                 . '</div>';
        }
    }
    $out .= '</div>';
    return $out;
}

// ================================================================
// YARDIMCI FONKSİYON: Şampiyon UI bloğu oluştur
// ================================================================
function sampiyon_blok_olustur(string $lig_adi, string $takim_adi, string $lig_kod, array $top5, int $sezon_yili = 2025): string {
    $sezon_str = $sezon_yili . '/' . ($sezon_yili + 1);
    $blok = '<div style="background:linear-gradient(135deg,#1e3a5f,#7f1d1d);border:2px solid #d4af37;border-radius:12px;padding:20px 24px;margin:12px 0;text-align:center;">';
    $blok .= '<div style="font-family:monospace;color:#d4af37;font-size:0.82rem;letter-spacing:0;">=============================================</div>';
    $blok .= '<div style="font-family:\'Oswald\',sans-serif;font-size:1.3rem;font-weight:900;color:#fff;margin:8px 0;">'
           . '🏆 ' . htmlspecialchars($lig_adi) . ' ' . htmlspecialchars($sezon_str) . ' SEZONU ŞAMPİYONU 🏆</div>';
    $blok .= '<div style="font-family:\'Oswald\',sans-serif;font-size:1.6rem;font-weight:900;color:#d4af37;margin:6px 0;">★ ' . htmlspecialchars($takim_adi) . ' ★</div>';
    $blok .= '<div style="font-family:monospace;color:#d4af37;font-size:0.82rem;letter-spacing:0;">=============================================</div>';
    $blok .= '<div style="color:#d1fae5;font-size:0.9rem;margin-top:10px;">Tebrikler! ' . htmlspecialchars($takim_adi) . ', ligi şampiyon olarak tamamladı.</div>';
    if (!empty($top5)) {
        $blok .= '<div style="margin-top:12px;font-size:0.8rem;text-align:left;">';
        $blok .= '<div style="color:#d4af37;font-weight:700;margin-bottom:6px;text-transform:uppercase;font-size:0.72rem;">Final Tablosu — İlk 5</div>';
        foreach ($top5 as $i => $t) {
            $av = (int)$t['atilan_gol'] - (int)$t['yenilen_gol'];
            $avStr = ($av >= 0 ? '+' : '') . $av;
            $blok .= '<div style="display:flex;gap:8px;padding:3px 0;color:#fff;">'
                   . '<span style="width:20px;color:#d4af37;font-weight:700;">' . ($i+1) . '</span>'
                   . '<span style="flex:1;">' . htmlspecialchars($t['takim_adi']) . '</span>'
                   . '<span style="color:#94a3b8;font-size:0.7rem;margin-right:4px;">AV:' . $avStr . '</span>'
                   . '<span style="color:#d4af37;font-weight:900;">' . $t['puan'] . 'P</span>'
                   . '</div>';
        }
        $blok .= '</div>';
    }
    $blok .= '<div style="background:rgba(0,0,0,0.4);border:1px solid rgba(212,175,55,0.3);border-radius:6px;padding:10px 14px;margin-top:12px;font-family:monospace;font-size:0.78rem;color:#a3e635;text-align:left;">';
    $blok .= '<div style="color:#94a3b8;margin-bottom:4px;">// index.php için son şampiyon güncellemesi</div>';
    $blok .= '$son_sampiyon[\'' . htmlspecialchars($lig_kod) . '\'] = "' . htmlspecialchars($takim_adi) . '";';
    $blok .= '</div>';
    $blok .= '</div>';
    return $blok;
}

// ================================================================
// LİG / TURNUVA TANIMI
// ================================================================
$ligler = [
    ['prefix' => '',     'maclar' => 'maclar',     'takimlar' => 'takimlar',     'ayar' => 'ayar',     'max' => 34, 'ad' => 'Süper Lig',       'kod' => 'superlig'],
    ['prefix' => 'pl_',  'maclar' => 'pl_maclar',  'takimlar' => 'pl_takimlar',  'ayar' => 'pl_ayar',  'max' => 38, 'ad' => 'Premier League',   'kod' => 'premier_league'],
    ['prefix' => 'es_',  'maclar' => 'es_maclar',  'takimlar' => 'es_takimlar',  'ayar' => 'es_ayar',  'max' => 38, 'ad' => 'La Liga',           'kod' => 'la_liga'],
    ['prefix' => 'de_',  'maclar' => 'de_maclar',  'takimlar' => 'de_takimlar',  'ayar' => 'de_ayar',  'max' => 34, 'ad' => 'Bundesliga',        'kod' => 'bundesliga'],
    ['prefix' => 'it_',  'maclar' => 'it_maclar',  'takimlar' => 'it_takimlar',  'ayar' => 'it_ayar',  'max' => 38, 'ad' => 'Serie A',           'kod' => 'serie_a'],
    ['prefix' => 'fr_',  'maclar' => 'fr_maclar',  'takimlar' => 'fr_takimlar',  'ayar' => 'fr_ayar',  'max' => 34, 'ad' => 'Ligue 1',           'kod' => 'ligue1'],
    ['prefix' => 'pt_',  'maclar' => 'pt_maclar',  'takimlar' => 'pt_takimlar',  'ayar' => 'pt_ayar',  'max' => 34, 'ad' => 'Liga NOS',          'kod' => 'liga_nos'],
    ['prefix' => 'cl_',  'maclar' => 'cl_maclar',  'takimlar' => 'cl_takimlar',  'ayar' => 'cl_ayar',  'max' => 17, 'ad' => 'Champions League',  'kod' => 'champions_league'],
    ['prefix' => 'uel_', 'maclar' => 'uel_maclar', 'takimlar' => 'uel_takimlar', 'ayar' => 'uel_ayar', 'max' => 15, 'ad' => 'Europa League',     'kod' => 'europa_league'],
    ['prefix' => 'uecl_','maclar' => 'uecl_maclar','takimlar' => 'uecl_takimlar','ayar' => 'uecl_ayar','max' => 15, 'ad' => 'Conference League', 'kod' => 'conference_league'],
];

// Kullanıcı takımı (Süper Lig ayarından)
$kullanici_takim_id = 0;
try {
    $kullanici_takim_id = (int)$pdo->query("SELECT kullanici_takim_id FROM ayar LIMIT 1")->fetchColumn();
} catch (Throwable $e) {}

// ================================================================
// AKSİYON: GLOBAL HAFTAYI OYNA
// ================================================================
$sonuc_mesajlari = [];

if (isset($_POST['global_hafta_oyna'])) {
    foreach ($ligler as $lig) {
        // Ligue 1 destek mesajı
        if ($lig['ad'] === 'Ligue 1') {
            error_log("Ligue 1 destekleniyor");
        }
        try {
            $hafta = (int)$pdo->query("SELECT hafta FROM {$lig['ayar']} LIMIT 1")->fetchColumn();
        } catch (Throwable $e) { continue; }

        $engine = new MatchEngine($pdo, $lig['prefix']);
        $simulated = simulate_league_week(
            $pdo, $engine,
            $lig['maclar'], $lig['takimlar'], $lig['ayar'],
            $hafta, $lig['max'], $kullanici_takim_id
        );
        if ($simulated > 0) {
            $msg = "<strong>{$lig['ad']}</strong> — Hafta {$hafta}: {$simulated} maç oynandı";
            // Puan tablosu özeti ekle (Top 8 + Bottom 3)
            $msg .= get_puan_tablosu_ozet($pdo, $lig['takimlar']);
            $sonuc_mesajlari[] = $msg;
        }

        // Sezon tamamlandı mı? Şampiyon belirle
        try {
            $kalan_tum = $pdo->prepare("SELECT COUNT(*) FROM {$lig['maclar']} WHERE ev_skor IS NULL");
            $kalan_tum->execute();
            $kalan_tum_sayisi = (int)$kalan_tum->fetchColumn();
            $yeni_hafta = (int)$pdo->query("SELECT hafta FROM {$lig['ayar']} LIMIT 1")->fetchColumn();
            if ($kalan_tum_sayisi == 0 && $yeni_hafta >= $lig['max']) {
                // Şampiyonu belirle
                $top_rows = $pdo->query(
                    "SELECT takim_adi, puan, atilan_gol, yenilen_gol FROM {$lig['takimlar']}
                      ORDER BY puan DESC, (atilan_gol-yenilen_gol) DESC, atilan_gol DESC LIMIT 5"
                )->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($top_rows)) {
                    $sampiyon_adi = $top_rows[0]['takim_adi'];
                    $lig_kod = $lig['kod'] ?? strtolower(str_replace(' ', '_', $lig['ad']));
                    $sezon_y = 0;
                    try { $sezon_y = (int)$pdo->query("SELECT sezon_yil FROM {$lig['ayar']} LIMIT 1")->fetchColumn(); } catch(Throwable $e) {}
                    $sonuc_mesajlari[] = sampiyon_blok_olustur($lig['ad'], $sampiyon_adi, $lig_kod, $top_rows, $sezon_y ?: 2025);
                }
            }
        } catch (Throwable $e) {}
    }
    // Bildirim için session
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['play_week_sonuc'] = $sonuc_mesajlari;
    header("Location: play_week.php?done=1");
    exit;
}

// ================================================================
// AKSİYON: TÜM SEZONU SİMÜLE ET
// ================================================================
if (isset($_POST['tum_sezonu_simule'])) {
    $sezon_sampiyonlar = [];
    foreach ($ligler as $lig) {
        // Ligue 1 destek mesajı
        if ($lig['ad'] === 'Ligue 1') {
            error_log("Ligue 1 destekleniyor");
        }
        try {
            $hafta_row = $pdo->query("SELECT hafta FROM {$lig['ayar']} LIMIT 1")->fetchColumn();
            $hafta_baslangic = (int)$hafta_row;
        } catch (Throwable $e) { continue; }

        // İlerleme mesajı
        $sezon_sampiyonlar[] = '<div style="color:#94a3b8;font-size:0.85rem;padding:4px 0;">'
            . '<i class="fa-solid fa-rotate fa-spin me-2"></i>'
            . htmlspecialchars($lig['ad']) . ' sezonu simüle ediliyor…</div>';

        $engine = new MatchEngine($pdo, $lig['prefix']);
        for ($h = $hafta_baslangic; $h <= $lig['max']; $h++) {
            simulate_league_week(
                $pdo, $engine,
                $lig['maclar'], $lig['takimlar'], $lig['ayar'],
                $h, $lig['max'], 0  // Kullanıcı takımı dahil her maç simüle edilir
            );
        }
        // Hafta sayacını sona al (table name is from hardcoded allowlist)
        try {
            $max_son = (int)$lig['max'];
            $pdo->prepare("UPDATE {$lig['ayar']} SET hafta = ?")->execute([$max_son]);
        } catch (Throwable $e) {}

        // Şampiyonu belirle
        try {
            $sezon_y = 2025;
            try { $sezon_y = (int)$pdo->query("SELECT sezon_yil FROM {$lig['ayar']} LIMIT 1")->fetchColumn(); } catch(Throwable $e2) {}
            $top_rows = $pdo->query(
                "SELECT takim_adi, puan, atilan_gol, yenilen_gol FROM {$lig['takimlar']}
                  ORDER BY puan DESC, (atilan_gol-yenilen_gol) DESC, atilan_gol DESC LIMIT 5"
            )->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($top_rows)) {
                $lig_kod = $lig['kod'] ?? strtolower(str_replace(' ', '_', $lig['ad']));
                $sezon_sampiyonlar[] = sampiyon_blok_olustur($lig['ad'], $top_rows[0]['takim_adi'], $lig_kod, $top_rows, $sezon_y);
            }
        } catch (Throwable $e) {}
    }
    // Şampiyon listesini session'a kaydet
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['play_week_sonuc'] = $sezon_sampiyonlar;
    // Sezon sonu ekranına yönlendir (Super Lig sezon geçişi)
    header("Location: super_lig/sezon_gecisi.php");
    exit;
}

// ================================================================
// VERİ ÇEKİMİ (MEVCUT DURUM)
// ================================================================
$durum_listesi = [];
foreach ($ligler as $lig) {
    try {
        $row = $pdo->query("SELECT hafta, sezon_yil FROM {$lig['ayar']} LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) continue;
        $hafta_val = (int)$row['hafta'];
        $kalan_stmt = $pdo->prepare("SELECT COUNT(*) FROM {$lig['maclar']} WHERE hafta=? AND ev_skor IS NULL");
        $kalan_stmt->execute([$hafta_val]);
        $kalan = (int)$kalan_stmt->fetchColumn();
        $toplam_stmt = $pdo->prepare("SELECT COUNT(*) FROM {$lig['maclar']} WHERE hafta=?");
        $toplam_stmt->execute([$hafta_val]);
        $toplam = (int)$toplam_stmt->fetchColumn();
        $durum_listesi[] = [
            'ad'     => $lig['ad'],
            'hafta'  => (int)$row['hafta'],
            'max'    => $lig['max'],
            'sezon'  => (int)$row['sezon_yil'],
            'kalan'  => $kalan,
            'toplam' => $toplam,
            'tamam'  => $toplam > 0 && $kalan === 0,
        ];
    } catch (Throwable $e) {}
}

// Session mesajları
if (session_status() === PHP_SESSION_NONE) session_start();
$flash = $_SESSION['play_week_sonuc'] ?? [];
unset($_SESSION['play_week_sonuc']);
$sifirla_mesaj = $_SESSION['sezon_sifirla_mesaj'] ?? '';
unset($_SESSION['sezon_sifirla_mesaj']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Global Hafta Oynat | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root { --gold:#d4af37; --bg:#050505; --panel:rgba(255,255,255,0.05); --border:rgba(255,255,255,0.1); }
    /* --- YÜKSEK KONTRAST / DARK MODE OVERRIDE --- */
    body, p, h1, h2, h3, h4, h5, h6, span, label, li, td, th { color: #ffffff !important; }
    body, table, td, th, .puan-tablosu, .sira, .puan { color: #ffffff !important; background-color: #1a1a1a; }
    body { background:var(--bg) !important; color:#fff !important; font-family:'Poppins',sans-serif; min-height:100vh; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    /* Navbar */
    .pro-navbar { background:rgba(5,5,5,0.95); backdrop-filter:blur(20px); border-bottom:1px solid var(--border); position:sticky; top:0; z-index:1000; padding:0 2rem; height:68px; display:flex; align-items:center; justify-content:space-between; }
    .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.3rem; font-weight:700; color:#fff; text-decoration:none; }
    .nav-brand i { color:var(--gold); }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.88rem; display:flex; align-items:center; gap:6px; }
    .back-btn:hover { color:#fff; }
    /* Hero */
    .hero { text-align:center; padding:52px 20px 28px; }
    .hero h1 { font-size:2.8rem; font-weight:900; line-height:1.1; margin-bottom:8px; }
    .hero p { color:#94a3b8; font-size:0.95rem; letter-spacing:1px; }
    .gold { color:var(--gold); }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    /* Glass panels */
    .glass { background:var(--panel); border:1px solid var(--border); border-radius:18px; padding:24px; }
    /* League status cards */
    .lig-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:16px; margin-bottom:32px; }
    .lig-kart { background:var(--panel); border:1px solid var(--border); border-radius:14px; padding:18px 20px; position:relative; }
    .lig-kart.bitti { border-color:rgba(16,185,129,0.3); }
    .lig-ad { font-family:'Oswald',sans-serif; font-size:1.15rem; font-weight:700; }
    .lig-meta { font-size:0.78rem; color:#94a3b8; margin-top:4px; }
    .lig-bar-bg { height:6px; background:rgba(255,255,255,0.07); border-radius:3px; margin-top:10px; overflow:hidden; }
    .lig-bar { height:6px; border-radius:3px; background:linear-gradient(90deg,var(--gold),#7c5c00); }
    .hafta-pill { font-size:0.7rem; font-weight:700; padding:2px 10px; border-radius:20px; background:rgba(212,175,55,0.15); color:var(--gold); border:1px solid rgba(212,175,55,0.3); white-space:nowrap; }
    .done-badge { position:absolute; top:14px; right:14px; font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:20px; background:rgba(16,185,129,0.2); color:#10b981; border:1px solid rgba(16,185,129,0.4); }
    /* Action buttons */
    .btn-global { display:block; width:100%; padding:18px; border-radius:14px; font-family:'Oswald',sans-serif; font-size:1.35rem; font-weight:800; letter-spacing:1.5px; text-align:center; cursor:pointer; border:none; transition:all .25s; }
    .btn-play { background:linear-gradient(135deg,#1e3a5f,#2563eb); color:#fff; box-shadow:0 4px 24px rgba(37,99,235,0.4); }
    .btn-play:hover { transform:translateY(-3px); box-shadow:0 8px 32px rgba(37,99,235,0.6); }
    .btn-simulate { background:linear-gradient(135deg,#7f1d1d,#dc2626); color:#fff; box-shadow:0 4px 24px rgba(220,38,38,0.5); margin-top:16px; }
    .btn-simulate:hover { transform:translateY(-3px); box-shadow:0 10px 36px rgba(220,38,38,0.7); }
    .btn-simulate i { font-size:1.3rem; }
    /* Flash messages */
    .flash-box { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:14px; padding:16px 20px; margin-bottom:24px; }
    .flash-item { font-size:0.88rem; color:#d1fae5; padding:3px 0; }
    .btn-reset { display:block; width:100%; padding:14px; border-radius:14px; font-family:'Oswald',sans-serif; font-size:1.1rem; font-weight:800; letter-spacing:1.5px; text-align:center; cursor:pointer; border:1px solid rgba(220,38,38,0.4); transition:all .25s; background:rgba(127,29,29,0.3); color:#fca5a5; margin-top:16px; }
    .btn-reset:hover { background:linear-gradient(135deg,#7f1d1d,#dc2626); color:#fff; border-color:#dc2626; transform:translateY(-2px); box-shadow:0 8px 28px rgba(220,38,38,0.4); }
    /* Per-league reset buttons */
    .lig-reset-group { display:flex; gap:6px; margin-top:10px; }
    .btn-lig-reset-sezon { flex:1; padding:5px 8px; border-radius:7px; font-size:0.68rem; font-weight:700; text-align:center; cursor:pointer; border:1px solid rgba(251,191,36,0.4); background:rgba(120,80,0,0.3); color:#fde047 !important; transition:all .2s; }
    .btn-lig-reset-sezon:hover { background:rgba(202,138,4,0.5); border-color:#ca8a04; }
    .btn-lig-reset-tum { flex:1; padding:5px 8px; border-radius:7px; font-size:0.68rem; font-weight:700; text-align:center; cursor:pointer; border:1px solid rgba(220,38,38,0.4); background:rgba(127,29,29,0.3); color:#fca5a5 !important; transition:all .2s; }
    .btn-lig-reset-tum:hover { background:rgba(185,28,28,0.5); border-color:#b91c1c; }
    /* Reset success message */
    .reset-mesaj { background:rgba(251,191,36,0.1); border:1px solid rgba(251,191,36,0.4); border-radius:10px; padding:12px 16px; margin-bottom:20px; font-size:0.88rem; color:#fde047; }
    /* Section label */
    .section-lbl { font-family:'Oswald',sans-serif; font-size:0.85rem; color:#94a3b8; letter-spacing:2px; text-transform:uppercase; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--border); }
</style>
</head>
<body>

<nav class="pro-navbar">
    <a href="index.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Ana Menü</a>
    <a href="index.php" class="nav-brand font-oswald">
        <i class="fa-solid fa-chess-knight"></i> ULTIMATE <span style="color:var(--gold);">MANAGER</span>
    </a>
    <a href="takvim.php" class="back-btn"><i class="fa-solid fa-calendar-days"></i> Takvim</a>
</nav>

<div class="container pb-5" style="max-width:900px;">

    <!-- HERO -->
    <div class="hero">
        <div style="font-size:3rem; margin-bottom:12px;">🌍</div>
        <h1 class="font-oswald"><span class="gold-gradient">GLOBAL HAFTA OYNAT</span></h1>
        <p>Tek tıkla tüm dünya ligleri ve Avrupa kupaları eşzamanlı ilerler</p>
    </div>

    <?php if (!empty($flash)): ?>
    <div class="flash-box mb-4">
        <div class="fw-bold text-success mb-2"><i class="fa-solid fa-check-circle me-2"></i>Hafta başarıyla oynandı!</div>
        <?php foreach ($flash as $msg): ?>
            <div class="flash-item">• <?= $msg ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sifirla_mesaj)): ?>
    <div class="reset-mesaj mb-4">
        <i class="fa-solid fa-rotate-left me-2"></i><?= $sifirla_mesaj ?>
    </div>
    <?php endif; ?>

    <!-- LİG DURUM KARTLARI -->
    <div class="section-lbl"><i class="fa-solid fa-earth-europe me-2"></i>Mevcut Hafta Durumu</div>
    <?php
    // Lig kodu eşleştirmesi (lig adı → sezon_sifirla.php lig kodu)
    $lig_kod_map = [
        'Süper Lig'      => 'superlig',
        'Premier League' => 'premier_league',
        'La Liga'        => 'la_liga',
        'Bundesliga'     => 'bundesliga',
        'Serie A'        => 'serie_a',
        'Ligue 1'        => 'ligue1',
        'Liga NOS'       => 'liga_nos',
    ];
    ?>
    <div class="lig-grid mb-4">
        <?php foreach ($durum_listesi as $d): ?>
        <?php $lig_kod_d = $lig_kod_map[$d['ad']] ?? ''; ?>
        <div class="lig-kart <?= $d['tamam'] ? 'bitti' : '' ?>">
            <?php if ($d['tamam']): ?><div class="done-badge"><i class="fa-solid fa-check me-1"></i>Tamamlandı</div><?php endif; ?>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="lig-ad"><?= htmlspecialchars($d['ad']) ?></div>
            </div>
            <div class="lig-meta">
                Hafta <?= $d['hafta'] ?> / <?= $d['max'] ?>
                &bull; <?= $d['kalan'] ?> maç kaldı
                &bull; <?= $d['sezon'] ?> Sezonu
            </div>
            <div class="lig-bar-bg">
                <div class="lig-bar" style="width:<?= round($d['hafta'] / $d['max'] * 100) ?>%;"></div>
            </div>
            <?php if (!empty($lig_kod_d)): ?>
            <!-- Per-league reset buttons -->
            <div class="lig-reset-group">
                <form method="POST" action="sezon_sifirla.php" onsubmit="return confirm('Bu sezonu sıfırla?\n\n<?= htmlspecialchars($d['ad'], ENT_QUOTES) ?> — Mevcut fikstür, puan tablosu ve maç sonuçları silinecek. Geçmiş sezon şampiyonları korunacak.');" style="flex:1;">
                    <input type="hidden" name="lig" value="<?= htmlspecialchars($lig_kod_d) ?>">
                    <input type="hidden" name="mod" value="bu_sezon">
                    <input type="hidden" name="onay" value="1">
                    <button type="submit" class="btn-lig-reset-sezon w-100">
                        <i class="fa-solid fa-broom me-1"></i>Bu Sezonu Sıfırla
                    </button>
                </form>
                <form method="POST" action="sezon_sifirla.php" onsubmit="return confirm('⚠️ TÜM SEZONLARI SIFIRLA!\n\n<?= htmlspecialchars($d['ad'], ENT_QUOTES) ?> — LİGİN TÜM GEÇMİŞİ, ŞAMPİYON KAYITLARI ve TÜM VERİLER silinecek.\n\nBu işlem geri alınamaz! Emin misiniz?');" style="flex:1;">
                    <input type="hidden" name="lig" value="<?= htmlspecialchars($lig_kod_d) ?>">
                    <input type="hidden" name="mod" value="tum_sezon">
                    <input type="hidden" name="onay" value="1">
                    <button type="submit" class="btn-lig-reset-tum w-100">
                        <i class="fa-solid fa-fire me-1"></i>Tüm Sezonları Sıfırla
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($durum_listesi)): ?>
        <div class="glass text-center py-4 text-secondary">
            <i class="fa-solid fa-circle-info me-2"></i>Ligler henüz kurulmamış. Lütfen önce ligleri başlatın.
        </div>
        <?php endif; ?>
    </div>

    <!-- EYLEM BUTONLARI -->
    <div class="glass">
        <div class="section-lbl"><i class="fa-solid fa-bolt me-2"></i>Eylemler</div>

        <!-- GLOBAL HAFTAYI OYNA -->
        <form method="POST">
            <button type="submit" name="global_hafta_oyna" class="btn-global btn-play">
                <i class="fa-solid fa-play me-2"></i>
                GLOBAL HAFTAYI OYNA
                <span style="font-size:0.85rem; font-weight:400; display:block; margin-top:4px; letter-spacing:0; text-transform:none; opacity:0.8;">
                    Tüm ligler ve Avrupa kupaları bu haftaki maçları aynı anda oynar
                </span>
            </button>
        </form>

        <!-- TÜM SEZONU SİMÜLE ET -->
        <form method="POST" onsubmit="return confirm('Tüm sezon simüle edilecek! Kullanıcı takımınızın maçları dahil hepsi otomatik oynanacak. Devam edilsin mi?');">
            <button type="submit" name="tum_sezonu_simule" class="btn-global btn-simulate">
                <i class="fa-solid fa-forward-fast me-2"></i>
                TÜM SEZONU SİMÜLE ET
                <span style="font-size:0.85rem; font-weight:400; display:block; margin-top:4px; letter-spacing:0; text-transform:none; opacity:0.85;">
                    Kalan tüm haftalar saniyeler içinde oynanır → Sezon Sonu Ekranına geçilir
                </span>
            </button>
        </form>

        <!-- TÜM SİSTEMİ SIFIRLA -->
        <form method="POST" action="reset_system.php" onsubmit="return confirm('⚠️ DİKKAT!\n\nTÜM SİSTEM SIFIRLANACAK!\n\nTüm maç verileri, puan tabloları ve hafta sayaçları silinecek. Bu işlem geri alınamaz!\n\nDevam etmek istediğinizden EMİN MİSİNİZ?');">
            <button type="submit" name="reset_onayli" class="btn-reset">
                <i class="fa-solid fa-rotate-left me-2"></i>
                TÜM SİSTEMİ SIFIRLA
                <span style="font-size:0.78rem; font-weight:400; display:block; margin-top:4px; letter-spacing:0; text-transform:none; opacity:0.7;">
                    Tüm maçlar, puan tabloları ve sezon verileri silinir — Başa dön
                </span>
            </button>
        </form>
    </div>

    <!-- NAVİGASYON -->
    <div class="d-flex gap-3 flex-wrap justify-content-center mt-4">
        <a href="super_lig/superlig.php" class="back-btn" style="color:#e11d48;"><i class="fa-solid fa-moon me-1"></i> Süper Lig</a>
        <a href="premier_lig/premier_lig.php" class="back-btn" style="color:#a855f7;"><i class="fa-solid fa-crown me-1"></i> Premier League</a>
        <a href="la_liga/la_liga.php" class="back-btn" style="color:#f59e0b;"><i class="fa-solid fa-sun me-1"></i> La Liga</a>
        <a href="champions_league/cl.php" class="back-btn" style="color:#00e5ff;"><i class="fa-solid fa-trophy me-1"></i> Champions League</a>
        <a href="takvim.php" class="back-btn" style="color:#94a3b8;"><i class="fa-solid fa-calendar-days me-1"></i> Global Takvim</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
