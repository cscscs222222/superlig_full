<?php
// ==============================================================================
// ULTIMATE MANAGER - ULTRA MODERN CANLI TV YAYINI V5.0 (FAZ 2: BROADCAST HUD)
// Yeni: Hava Durumu, VAR Sistemi, Derbi Atmosferi, Canlı Taktik, Kaleci Kurtarış
// ==============================================================================
include 'db.php';
require_once 'MatchEngine.php';

$mac_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$lig_kodu = isset($_GET['lig']) ? $_GET['lig'] : 'tr';
$hafta_yonlendirme = isset($_GET['hafta']) ? (int)$_GET['hafta'] : 1;

$prefix = "";
$geridon_link = "superlig/super_lig/superlig.php";
if($lig_kodu == 'pl') { $prefix = "pl_"; $geridon_link = "superlig/premier_lig/premier_lig.php"; }
elseif($lig_kodu == 'cl') { $prefix = "cl_"; $geridon_link = "superlig/champions_league/cl.php"; }
elseif($lig_kodu == 'es') { $prefix = "es_"; $geridon_link = "superlig/la_liga/la_liga.php"; }
elseif($lig_kodu == 'de') { $prefix = "de_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'fr') { $prefix = "fr_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'it') { $prefix = "it_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'pt') { $prefix = "pt_"; $geridon_link = "index.php"; }

$tbl_maclar   = $prefix . "maclar";
$tbl_takimlar = $prefix . "takimlar";
$tbl_ayar     = $prefix . "ayar";
$tbl_oyuncular = $prefix . "oyuncular";

$engine = new MatchEngine($pdo, $prefix);

// --- FAZ 2: TAKTİK DEĞİŞTİRME AJAX HANDLER ---
if (isset($_POST['action']) && $_POST['action'] === 'taktik_degistir') {
    header('Content-Type: application/json');
    try {
        $yeni_dizilis = in_array($_POST['dizilis'] ?? '', ['4-3-3','4-4-2','4-5-1','3-5-2','5-3-2','4-2-3-1','4-3-3 Hücum','4-3-3 Defans'])
            ? $_POST['dizilis']
            : '4-3-3';
        $pdo->prepare("UPDATE $tbl_ayar SET dizilis=? WHERE id=1")->execute([$yeni_dizilis]);

        // Oyuncu değişikliği: eski → yeni (ilk_11 sütunu kullan)
        $cikan_id  = isset($_POST['cikan_id'])  ? (int)$_POST['cikan_id']  : 0;
        $giren_id  = isset($_POST['giren_id'])  ? (int)$_POST['giren_id']  : 0;
        $sub_mesaj = '';
        if ($cikan_id > 0 && $giren_id > 0 && $cikan_id !== $giren_id) {
            $pdo->prepare("UPDATE $tbl_oyuncular SET ilk_11=0, yedek=1 WHERE id=? LIMIT 1")->execute([$cikan_id]);
            $pdo->prepare("UPDATE $tbl_oyuncular SET ilk_11=1, yedek=0 WHERE id=? LIMIT 1")->execute([$giren_id]);
            $cikan_isim = $pdo->prepare("SELECT isim FROM $tbl_oyuncular WHERE id=? LIMIT 1");
            $cikan_isim->execute([$cikan_id]);
            $cikan_isim = $cikan_isim->fetchColumn();
            $giren_isim = $pdo->prepare("SELECT isim FROM $tbl_oyuncular WHERE id=? LIMIT 1");
            $giren_isim->execute([$giren_id]);
            $giren_isim = $giren_isim->fetchColumn();
            $sub_mesaj = ($cikan_isim ?: '?') . ' → ' . ($giren_isim ?: '?');
        }
        echo json_encode(['ok' => true, 'dizilis' => $yeni_dizilis, 'sub' => $sub_mesaj]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'hata' => $e->getMessage()]);
    }
    exit;
}

$stmt_mac = $pdo->prepare("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo
                    FROM $tbl_maclar m
                    JOIN $tbl_takimlar t1 ON m.ev = t1.id
                    JOIN $tbl_takimlar t2 ON m.dep = t2.id
                    WHERE m.id = ?");
$stmt_mac->execute([$mac_id]);
$mac = $stmt_mac->fetch(PDO::FETCH_ASSOC);

if(!$mac) { die("Maç bulunamadı."); }

if($mac['ev_skor'] === NULL) {
    // İki eş zamanlı yenileme isteğinin aynı maçı iki kez oynamasını önlemek için transaction + kilitli güncelleme
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare("SELECT ev_skor FROM $tbl_maclar WHERE id = ? FOR UPDATE");
        $lock->execute([$mac_id]);
        $kontrol = $lock->fetchColumn();

        if ($kontrol === null) {
            // --- FAZ 2: HAVA DURUMU ATA ---
            $hava = $engine->hava_ata($mac_id);
            $mac['hava_durumu'] = $hava;

            $skorlar = $engine->gercekci_skor_hesapla($mac['ev'], $mac['dep'], $mac);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];

            $ev_detay  = $engine->mac_olay_uret($mac['ev'],  $ev_skor);
            $dep_detay = $engine->mac_olay_uret($mac['dep'], $dep_skor);

            // --- FAZ 2: Yeni sütunlara kaydet (hata olursa eski yöntemle devam et) ---
            try {
                $stmt = $pdo->prepare("UPDATE $tbl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=?, var_olaylar=?, ev_kurtaris=?, dep_kurtaris=? WHERE id=?");
                // ev_kurtaris = ev kalecisinin kurtarışları (ev takımı goalkeeperi; ev_detay içinde izlendi)
                $stmt->execute([
                    $ev_skor, $dep_skor,
                    $ev_detay['olaylar'], $dep_detay['olaylar'],
                    $ev_detay['kartlar'], $dep_detay['kartlar'],
                    json_encode(array_merge(
                        json_decode($ev_detay['var_olaylar'], true) ?: [],
                        json_decode($dep_detay['var_olaylar'], true) ?: []
                    ), JSON_UNESCAPED_UNICODE),
                    $ev_detay['kurtaris'],  // ev kalecisinin kurtarışları
                    $dep_detay['kurtaris'], // dep kalecisinin kurtarışları
                    $mac_id
                ]);
            } catch (Throwable $e2) {
                // Faz 2 sütunları henüz eklenmemişse eski yöntemle kaydet
                $stmt = $pdo->prepare("UPDATE $tbl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
                $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $mac_id]);
            }

            $s = $pdo->prepare("UPDATE $tbl_takimlar SET atilan_gol = atilan_gol + ?, yenilen_gol = yenilen_gol + ? WHERE id = ?");
            $s->execute([$ev_skor, $dep_skor, $mac['ev']]);
            $s->execute([$dep_skor, $ev_skor, $mac['dep']]);

            if ($ev_skor > $dep_skor) {
                $pdo->prepare("UPDATE $tbl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=?")->execute([$mac['ev']]);
                $pdo->prepare("UPDATE $tbl_takimlar SET malubiyet=malubiyet+1 WHERE id=?")->execute([$mac['dep']]);
            } elseif ($ev_skor == $dep_skor) {
                $pdo->prepare("UPDATE $tbl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id=?")->execute([$mac['ev']]);
                $pdo->prepare("UPDATE $tbl_takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id=?")->execute([$mac['dep']]);
            } else {
                $pdo->prepare("UPDATE $tbl_takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id=?")->execute([$mac['dep']]);
                $pdo->prepare("UPDATE $tbl_takimlar SET malubiyet=malubiyet+1 WHERE id=?")->execute([$mac['ev']]);
            }

            $hafta = $mac['hafta'];
            $stmt_kalan = $pdo->prepare("SELECT COUNT(*) FROM $tbl_maclar WHERE hafta = ? AND ev_skor IS NULL");
            $stmt_kalan->execute([$hafta]);
            $kalan_mac = $stmt_kalan->fetchColumn();
            if ($kalan_mac == 0) { $pdo->prepare("UPDATE $tbl_ayar SET hafta = hafta + 1")->execute(); }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
    }

    $stmt_mac->execute([$mac_id]);
    $mac = $stmt_mac->fetch(PDO::FETCH_ASSOC);
}

// --- FAZ 2: HAVA DURUMU & DERBİ BİLGİSİ ---
$hava_durumu = $mac['hava_durumu'] ?? 'Güneşli';
$is_derbi    = $engine->is_derbi($mac['ev'], $mac['dep']);

// --- FAZ 2: KALECI KURTARIŞLARI ---
$ev_kurtaris  = (int)($mac['ev_kurtaris']  ?? 0); // ev kalecisinin kurtarışları
$dep_kurtaris = (int)($mac['dep_kurtaris'] ?? 0); // dep kalecisinin kurtarışları

// Ev kalecisini ve dep kalecisini bul
$ev_kaleci_isim  = '';
$dep_kaleci_isim = '';
try {
    $stmt_k = $pdo->prepare("SELECT isim FROM $tbl_oyuncular WHERE takim_id=? AND mevki='K' ORDER BY kurtaris DESC LIMIT 1");
    $stmt_k->execute([$mac['ev']]);
    $ev_kaleci_isim  = $stmt_k->fetchColumn() ?: '';
    $stmt_k->execute([$mac['dep']]);
    $dep_kaleci_isim = $stmt_k->fetchColumn() ?: '';
} catch (Throwable $e) {}

// --- FAZ 2: VAR OLAYLARI ---
$var_olaylar_raw = [];
try {
    $var_olaylar_raw = json_decode($mac['var_olaylar'] ?? '[]', true) ?: [];
} catch (Throwable $e) {}

// --- FAZ 2: KULLANICI TAKİMI VE YEDEK OYUNCULAR (TAKTİK PANELİ İÇİN) ---
$kullanici_takim_id  = null;
$kullanici_takim_kim = ''; // 'ev' veya 'dep'
$ayar_dizilis        = '4-3-3';
$yedek_oyuncular     = [];
$ilk11_oyuncular     = [];
try {
    $ayar_row = $pdo->query("SELECT * FROM $tbl_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($ayar_row) {
        $kullanici_takim_id = $ayar_row['kullanici_takim_id'] ?? null;
        $ayar_dizilis       = $ayar_row['dizilis'] ?? '4-3-3';
    }
    if ($kullanici_takim_id) {
        if ($kullanici_takim_id == $mac['ev'])       $kullanici_takim_kim = 'ev';
        elseif ($kullanici_takim_id == $mac['dep'])  $kullanici_takim_kim = 'dep';

        if ($kullanici_takim_kim) {
            $stmt_yedek = $pdo->prepare(
                "SELECT id, isim, mevki, ovr FROM $tbl_oyuncular
                 WHERE takim_id=? AND yedek=1
                 ORDER BY ovr DESC LIMIT 8"
            );
            $stmt_yedek->execute([$kullanici_takim_id]);
            $yedek_oyuncular = $stmt_yedek->fetchAll(PDO::FETCH_ASSOC);

            $stmt_ilk11 = $pdo->prepare(
                "SELECT id, isim, mevki, ovr FROM $tbl_oyuncular
                 WHERE takim_id=? AND ilk_11=1
                 ORDER BY CASE mevki WHEN 'K' THEN 1 WHEN 'D' THEN 2 WHEN 'OS' THEN 3 WHEN 'F' THEN 4 END, ovr DESC"
            );
            $stmt_ilk11->execute([$kullanici_takim_id]);
            $ilk11_oyuncular = $stmt_ilk11->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {}

// ZAMAN ÇİZELGESİ
$tum_olaylar = [];
$ev_olaylar  = json_decode($mac['ev_olaylar']  ?? '[]', true) ?: [];
$dep_olaylar = json_decode($mac['dep_olaylar'] ?? '[]', true) ?: [];
$ev_kartlar  = json_decode($mac['ev_kartlar']  ?? '[]', true) ?: [];
$dep_kartlar = json_decode($mac['dep_kartlar'] ?? '[]', true) ?: [];

$yazilan_ev = 0;
foreach($ev_olaylar as $o) {
    if($yazilan_ev >= $mac['ev_skor']) break;
    if(isset($o['tip']) && strtolower($o['tip']) == 'gol') {
        $tum_olaylar[] = ['dk' => $o['dakika'] ?? rand(1,90), 'tur' => 'gol', 'kim' => 'ev', 'oyuncu' => $o['oyuncu'] ?? 'Bilinmiyor', 'asist' => $o['asist'] ?? '-'];
        $yazilan_ev++;
    }
}
$yazilan_dep = 0;
foreach($dep_olaylar as $o) {
    if($yazilan_dep >= $mac['dep_skor']) break;
    if(isset($o['tip']) && strtolower($o['tip']) == 'gol') {
        $tum_olaylar[] = ['dk' => $o['dakika'] ?? rand(1,90), 'tur' => 'gol', 'kim' => 'dep', 'oyuncu' => $o['oyuncu'] ?? 'Bilinmiyor', 'asist' => $o['asist'] ?? '-'];
        $yazilan_dep++;
    }
}
foreach($ev_kartlar as $k) {
    $tur = (isset($k['detay']) && $k['detay'] == 'Kırmızı') ? 'kirmizi' : 'sari';
    $tum_olaylar[] = ['dk' => $k['dakika'] ?? rand(1,90), 'tur' => $tur, 'kim' => 'ev', 'oyuncu' => $k['oyuncu'] ?? 'Bilinmiyor'];
}
foreach($dep_kartlar as $k) {
    $tur = (isset($k['detay']) && $k['detay'] == 'Kırmızı') ? 'kirmizi' : 'sari';
    $tum_olaylar[] = ['dk' => $k['dakika'] ?? rand(1,90), 'tur' => $tur, 'kim' => 'dep', 'oyuncu' => $k['oyuncu'] ?? 'Bilinmiyor'];
}

// --- FAZ 2: VAR olaylarını zaman çizelgesine ekle ---
foreach($var_olaylar_raw as $v) {
    $tum_olaylar[] = [
        'dk'     => $v['dakika'] ?? rand(15,88),
        'tur'    => $v['tip']    ?? 'var_gol_iptal',
        'kim'    => 'var',
        'oyuncu' => $v['oyuncu'] ?? 'Bilinmiyor',
        'neden'  => $v['neden']  ?? 'ofsayt',
    ];
}

usort($tum_olaylar, function($a, $b) { return $a['dk'] <=> $b['dk']; });

// İSTATİSTİKLER
$ev_topla_oynama = ($mac['ev_skor'] > $mac['dep_skor']) ? rand(52, 65) : (($mac['ev_skor'] == $mac['dep_skor']) ? rand(45, 55) : rand(35, 48));
$dep_topla_oynama = 100 - $ev_topla_oynama;
$ev_sut  = ($mac['ev_skor']  * rand(2,4)) + rand(2,5);
$dep_sut = ($mac['dep_skor'] * rand(2,4)) + rand(2,5);

$json_olaylar    = json_encode($tum_olaylar, JSON_UNESCAPED_UNICODE);
$json_yedekler   = json_encode($yedek_oyuncular,  JSON_UNESCAPED_UNICODE);
$json_ilk11      = json_encode($ilk11_oyuncular,   JSON_UNESCAPED_UNICODE);

// Hava durumu ikon ve renk
$hava_icon  = '☀️'; $hava_renk = '#facc15'; $hava_etki = '';
if ($hava_durumu === 'Yağmurlu') { $hava_icon = '🌧️'; $hava_renk = '#60a5fa'; $hava_etki = 'Kondisyon daha hızlı düşüyor!'; }
elseif ($hava_durumu === 'Karlı') { $hava_icon = '❄️'; $hava_renk = '#e0f2fe'; $hava_etki = 'Sürpriz goller olabilir!'; }
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canlı Maç Yayını | Ultimate Manager</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { margin: 0; padding: 0; background-color: #000; color: #fff; font-family: 'Inter', sans-serif; overflow: hidden;}
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        /* Tam Ekran Sinematik Stadyum */
        .stadium-bg {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: url('https://images.unsplash.com/photo-1518605368461-1e1e38ce8ba4?q=80&w=2000') center/cover;
            filter: brightness(0.4) contrast(1.1); z-index: -2; animation: slowPan 40s linear infinite alternate;
        }
        @keyframes slowPan { 0% { transform: scale(1) translateX(0); } 100% { transform: scale(1.1) translateX(-20px); } }

        /* Karlı hava: kar efekti */
        .snow-overlay { position: fixed; top:0; left:0; width:100vw; height:100vh; pointer-events:none; z-index:1; }
        .snowflake { position:absolute; top:-10px; color:#e0f2fe; font-size:1.2em; animation: snowfall linear infinite; opacity:0.7; }
        @keyframes snowfall { to { transform: translateY(105vh) rotate(360deg); } }

        /* Yağmurlu hava: yağmur çizgileri */
        .rain-overlay { position: fixed; top:0; left:0; width:100vw; height:100vh; pointer-events:none; z-index:1; background: repeating-linear-gradient(90deg, transparent 0px, transparent 14px, rgba(96,165,250,0.07) 14px, rgba(96,165,250,0.07) 15px); animation: rainMove 0.3s linear infinite; }
        @keyframes rainMove { from { background-position-y: 0; } to { background-position-y: 20px; } }

        /* HUD Karartma Efekti */
        .vignette {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: radial-gradient(circle, transparent 40%, rgba(0,0,0,0.8) 100%); z-index: -1;
        }

        /* --- ÜST SKORBORD (TV BROADCAST STYLE) --- */
        .broadcast-hud {
            position: absolute; top: 40px; left: 50%; transform: translateX(-50%);
            display: flex; align-items: center; justify-content: center;
            z-index: 10;
        }

        .team-hud {
            display: flex; align-items: center; background: rgba(10,15,25,0.85);
            backdrop-filter: blur(10px); padding: 5px 20px; border: 1px solid rgba(255,255,255,0.1);
            height: 60px;
        }
        .team-hud.home { border-radius: 10px 0 0 10px; border-right: none; }
        .team-hud.away { border-radius: 0 10px 10px 0; border-left: none; }

        .team-hud img { width: 35px; height: 35px; object-fit: contain; filter: drop-shadow(0 2px 5px rgba(0,0,0,0.8)); margin: 0 15px;}
        .team-hud .name { font-size: 1.4rem; font-weight: 800; letter-spacing: 1px; width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}
        .team-hud.home .name { text-align: right; }
        .team-hud.away .name { text-align: left; }

        .score-hud {
            display: flex; align-items: center; justify-content: center;
            background: #e11d48; color: #fff; height: 60px; padding: 0 25px;
            font-family: 'Oswald'; font-size: 2.2rem; font-weight: 900; line-height: 1;
            box-shadow: 0 0 20px rgba(225,29,72,0.5); z-index: 11; position: relative;
        }
        .score-hud .dash { margin: 0 10px; opacity: 0.6; font-size: 1.8rem; }

        .time-hud {
            position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.9); padding: 4px 15px; border-radius: 0 0 8px 8px;
            font-family: 'Oswald'; font-size: 1.1rem; font-weight: 700; color: #facc15;
            border: 1px solid rgba(255,255,255,0.1); border-top: none; letter-spacing: 1px;
        }

        .live-badge {
            position: absolute; top: 40px; left: 40px;
            background: rgba(0,0,0,0.7); border: 1px solid rgba(255,255,255,0.1);
            padding: 8px 15px; border-radius: 5px; display: flex; align-items: center; gap: 10px;
            font-family: 'Oswald'; font-weight: 700; letter-spacing: 1px; backdrop-filter: blur(5px);
        }
        .live-dot { width: 10px; height: 10px; background: #e11d48; border-radius: 50%; animation: pulseRed 1.5s infinite; }
        @keyframes pulseRed { 0% { box-shadow: 0 0 0 0 rgba(225,29,72,0.7); } 70% { box-shadow: 0 0 0 10px rgba(225,29,72,0); } 100% { box-shadow: 0 0 0 0 rgba(225,29,72,0); } }

        /* --- FAZ 2: HAVA DURUMU ROZET --- */
        .weather-badge {
            position: absolute; top: 40px; right: 40px;
            background: rgba(0,0,0,0.7); border: 1px solid rgba(255,255,255,0.1);
            padding: 8px 15px; border-radius: 8px; display: flex; align-items: center; gap: 8px;
            font-family: 'Oswald'; font-weight: 700; letter-spacing: 1px; backdrop-filter: blur(5px);
            font-size: 1rem; z-index: 15;
        }

        /* --- FAZ 2: DERBİ BANNER --- */
        .derbi-banner {
            position: absolute; top: 115px; left: 50%; transform: translateX(-50%);
            background: linear-gradient(90deg, #dc2626, #b91c1c);
            padding: 6px 24px; border-radius: 20px; font-family: 'Oswald'; font-size: 0.9rem;
            font-weight: 700; letter-spacing: 2px; color: #fff; z-index: 10;
            box-shadow: 0 0 20px rgba(220,38,38,0.5); animation: derbiBlink 2s ease-in-out infinite;
        }
        @keyframes derbiBlink { 0%,100%{opacity:1;} 50%{opacity:0.7;} }

        /* --- ALT YORUM BANTI (TICKER) --- */
        .bottom-ticker {
            position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%);
            width: 80%; max-width: 1000px;
            display: flex; flex-direction: column; gap: 10px; z-index: 10;
        }

        .comm-line {
            background: rgba(15,23,42,0.85); border-left: 4px solid #475569;
            padding: 15px 25px; border-radius: 8px; backdrop-filter: blur(10px);
            font-size: 1.2rem; display: flex; align-items: center; gap: 20px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5); border-right: 1px solid rgba(255,255,255,0.05);
            animation: slideUp 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0; transform: translateY(20px);
        }
        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        .comm-line.gol      { border-left-color: #facc15; background: linear-gradient(90deg, rgba(250,204,21,0.15), rgba(15,23,42,0.85)); font-weight: 800; border: 1px solid rgba(250,204,21,0.3);}
        .comm-line.sari     { border-left-color: #facc15; }
        .comm-line.kirmizi  { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.15), rgba(15,23,42,0.85));}
        .comm-line.var-line { border-left-color: #a855f7; background: linear-gradient(90deg, rgba(168,85,247,0.15), rgba(15,23,42,0.85)); border: 1px solid rgba(168,85,247,0.3);}
        .comm-line.sub-line { border-left-color: #22c55e; background: linear-gradient(90deg, rgba(34,197,94,0.12), rgba(15,23,42,0.85));}

        .dk-badge { font-family: 'Oswald'; font-weight: 900; font-size: 1.5rem; color: #fff; width: 50px; text-align: center;}
        .comm-text { flex: 1; }

        /* Gol Animasyonu Kutusu */
        .goal-popup {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.5);
            opacity: 0; pointer-events: none; z-index: 100; text-align: center;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .goal-popup.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .goal-word { font-family: 'Oswald'; font-size: 8rem; font-weight: 900; color: #facc15; text-shadow: 0 10px 30px rgba(0,0,0,0.9), 0 0 50px rgba(250,204,21,0.5); line-height: 1; margin:0;}
        .goal-scorer { font-size: 2.5rem; font-weight: 800; background: rgba(0,0,0,0.8); padding: 10px 30px; border-radius: 50px; display: inline-block; margin-top: 10px; border: 2px solid #facc15;}

        /* --- FAZ 2: VAR ANİMASYON KUTUSU --- */
        .var-popup {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.7);
            opacity: 0; pointer-events: none; z-index: 100; text-align: center;
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .var-popup.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .var-word { font-family: 'Oswald'; font-size: 5rem; font-weight: 900; color: #a855f7; text-shadow: 0 0 40px rgba(168,85,247,0.8), 0 5px 15px rgba(0,0,0,0.9); line-height: 1; margin: 0; }
        .var-sub  { font-size: 1.6rem; font-weight: 700; background: rgba(0,0,0,0.85); padding: 8px 24px; border-radius: 30px; display: inline-block; margin-top: 12px; border: 2px solid #a855f7; color: #e9d5ff; }
        .var-stadium-silence { font-size: 1rem; color: rgba(255,255,255,0.5); margin-top: 8px; letter-spacing: 3px; animation: silencePulse 1s ease-in-out infinite; }
        @keyframes silencePulse { 0%,100%{opacity:0.4;} 50%{opacity:1;} }

        /* --- FAZ 2: TAKTİK PANELİ (60. Dakika) --- */
        .tactics-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(20px);
            z-index: 300; display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: 0.4s ease;
        }
        .tactics-overlay.show { opacity: 1; pointer-events: all; }
        .tactics-box {
            background: rgba(15,23,42,0.95); border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px; padding: 40px; width: 100%; max-width: 650px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.9); transform: scale(0.9); transition: 0.4s;
        }
        .tactics-overlay.show .tactics-box { transform: scale(1); }
        .tactics-title { font-family: 'Oswald'; font-size: 1.8rem; font-weight: 900; text-align: center; margin-bottom: 5px; color: #facc15; letter-spacing: 2px; }
        .tactics-subtitle { text-align: center; color: rgba(255,255,255,0.5); margin-bottom: 25px; font-size: 0.9rem; }
        .formation-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 20px; }
        .formation-btn {
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15);
            border-radius: 8px; padding: 10px; text-align: center; cursor: pointer;
            font-family: 'Oswald'; font-size: 0.85rem; font-weight: 700; transition: 0.2s; color: #fff;
        }
        .formation-btn:hover,.formation-btn.active { background: rgba(250,204,21,0.2); border-color: #facc15; color: #facc15; }
        .sub-section { margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; }
        .sub-label { font-family: 'Oswald'; font-size: 1rem; font-weight: 700; color: #22c55e; margin-bottom: 12px; letter-spacing: 1px; }
        .sub-select { width: 100%; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 8px 12px; color: #fff; font-size: 0.9rem; margin-bottom: 8px; }
        .sub-select option { background: #1e293b; }
        .btn-tactics-apply { background: linear-gradient(45deg, #22c55e, #16a34a); color: #fff; border: none; border-radius: 10px; padding: 12px 30px; font-family: 'Oswald'; font-size: 1rem; font-weight: 700; cursor: pointer; letter-spacing: 1px; transition: 0.2s; }
        .btn-tactics-apply:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(34,197,94,0.4); }
        .btn-devam { display: block; width: 100%; background: linear-gradient(45deg, #3b82f6, #1d4ed8); color: #fff; border: none; border-radius: 10px; padding: 14px; font-family: 'Oswald'; font-size: 1.1rem; font-weight: 700; cursor: pointer; letter-spacing: 1px; margin-top: 20px; transition: 0.2s; }
        .btn-devam:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.4); }

        /* --- MAÇ SONU MODAL --- */
        .stats-modal {
            position: absolute; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(15px);
            display: flex; align-items: center; justify-content: center;
            z-index: 200; opacity: 0; pointer-events: none; transition: 0.5s;
        }
        .stats-modal.show { opacity: 1; pointer-events: all; }

        .stats-box { background: rgba(15,23,42,0.9); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 620px; box-shadow: 0 25px 60px rgba(0,0,0,0.9); transform: translateY(50px); transition: 0.5s; }
        .stats-modal.show .stats-box { transform: translateY(0); }

        .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; font-weight: 700; font-size: 1.2rem;}
        .stat-bar-bg { flex: 1; margin: 0 20px; height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; overflow: hidden;}
        .stat-bar-ev  { background: #3b82f6; height: 100%; transition: 1.5s ease-out; width: 0%;}
        .stat-bar-dep { background: #ef4444; height: 100%; transition: 1.5s ease-out; width: 0%;}

        /* --- FAZ 2: Kaleci Kurtarış Kartları --- */
        .gk-saves-row { display: flex; justify-content: space-around; margin: 15px 0; gap: 10px; }
        .gk-card { flex: 1; background: rgba(255,255,255,0.05); border-radius: 12px; padding: 12px; text-align: center; border: 1px solid rgba(255,255,255,0.1); }
        .gk-card .gk-name { font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .gk-card .gk-saves { font-family: 'Oswald'; font-size: 2rem; font-weight: 900; color: #22c55e; }
        .gk-card .gk-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; }

        .btn-end { display: block; width: 100%; background: linear-gradient(45deg, #d4af37, #fde047); color: #000; font-weight: 900; font-size: 1.3rem; padding: 15px; border-radius: 10px; text-transform: uppercase; margin-top: 30px; text-decoration: none; text-align: center; border: none; box-shadow: 0 10px 20px rgba(212,175,55,0.3); transition: 0.3s;}
        .btn-end:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(212,175,55,0.5); color: #000;}

        /* Skor Güncelleme Efekti */
        .score-update { animation: scorePop 0.5s ease; color: #facc15 !important;}
        @keyframes scorePop { 0% {transform: scale(1);} 50% {transform: scale(1.5);} 100% {transform: scale(1);} }
    </style>
</head>
<body>

    <div class="stadium-bg"></div>
    <div class="vignette"></div>

    <?php if($hava_durumu === 'Karlı'): ?>
    <!-- Kar efekti -->
    <canvas class="snow-overlay" id="snowCanvas"></canvas>
    <?php elseif($hava_durumu === 'Yağmurlu'): ?>
    <div class="rain-overlay"></div>
    <?php endif; ?>

    <!-- Canlı badge -->
    <div class="live-badge">
        <div class="live-dot"></div> CANLI YAYIN
    </div>

    <!-- FAZ 2: Hava Durumu Rozeti -->
    <div class="weather-badge" style="border-color: <?= $hava_renk ?>44;">
        <span style="font-size:1.4rem;"><?= $hava_icon ?></span>
        <div>
            <div style="color: <?= $hava_renk ?>;"><?= htmlspecialchars($hava_durumu) ?></div>
            <?php if($hava_etki): ?>
            <div style="font-size:0.65rem; color:rgba(255,255,255,0.4); font-family:'Inter'; text-transform:none; font-weight:400;"><?= htmlspecialchars($hava_etki) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- FAZ 2: Derbi Banner -->
    <?php if($is_derbi): ?>
    <div class="derbi-banner">
        🔥 DERBİ MAÇI — DEPLASMAN TAKIMI BASKIDA! 🔥
    </div>
    <?php endif; ?>

    <!-- Skor HUD -->
    <div class="broadcast-hud">
        <div class="team-hud home">
            <span class="name font-oswald" id="name_ev"><?= htmlspecialchars($mac['ev_ad']) ?></span>
            <img src="<?= htmlspecialchars($mac['ev_logo']) ?>" alt="">
        </div>

        <div class="score-hud">
            <span id="score_ev">0</span>
            <span class="dash">-</span>
            <span id="score_dep">0</span>
            <div class="time-hud" id="match_time">00:00</div>
        </div>

        <div class="team-hud away">
            <img src="<?= htmlspecialchars($mac['dep_logo']) ?>" alt="">
            <span class="name font-oswald" id="name_dep"><?= htmlspecialchars($mac['dep_ad']) ?></span>
        </div>
    </div>

    <!-- Gol Animasyonu -->
    <div class="goal-popup" id="goal_anim">
        <div class="goal-word">GOOOOL!</div>
        <div class="goal-scorer" id="goal_scorer_name">Oyuncu Adı</div>
    </div>

    <!-- FAZ 2: VAR Animasyon Kutusu -->
    <div class="var-popup" id="var_anim">
        <div class="var-word">🎬 VAR</div>
        <div class="var-sub" id="var_sub_text">Hakem VAR'a gidiyor...</div>
        <div class="var-stadium-silence">— STADİUM SESSİZLEŞİYOR —</div>
    </div>

    <!-- Yorum Bandı -->
    <div class="bottom-ticker" id="commentary_container"></div>

    <!-- FAZ 2: TAKTİK PANELİ (60. Dakika) -->
    <?php if($kullanici_takim_kim): ?>
    <div class="tactics-overlay" id="tactics_overlay">
        <div class="tactics-box">
            <div class="tactics-title">⚙️ TAKTİK DEĞİŞTİR</div>
            <div class="tactics-subtitle">60. Dakika — Skora göre taktiğini belirle!</div>

            <div style="font-family:'Oswald'; font-size:0.85rem; color:rgba(255,255,255,0.5); margin-bottom:10px; letter-spacing:1px;">DİZİLİŞ SEÇ</div>
            <div class="formation-grid" id="formation_grid">
                <?php foreach(['4-3-3','4-4-2','4-5-1','3-5-2','5-3-2','4-2-3-1','4-3-3 Hücum','4-3-3 Defans'] as $f): ?>
                <div class="formation-btn <?= ($f == $ayar_dizilis ? 'active' : '') ?>"
                     onclick="secFormation('<?= htmlspecialchars($f) ?>', this)">
                    <?= htmlspecialchars($f) ?>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if(!empty($yedek_oyuncular) && !empty($ilk11_oyuncular)): ?>
            <div class="sub-section">
                <div class="sub-label">🔄 OYUNCU DEĞİŞİKLİĞİ</div>
                <div style="display:grid; grid-template-columns:1fr auto 1fr; gap:10px; align-items:center;">
                    <div>
                        <div style="font-size:0.75rem; color:rgba(255,255,255,0.4); margin-bottom:5px; text-transform:uppercase; letter-spacing:1px;">Çıkacak</div>
                        <select class="sub-select" id="cikan_oyuncu">
                            <option value="">-- Seç --</option>
                            <?php foreach($ilk11_oyuncular as $o): ?>
                            <option value="<?= $o['id'] ?>">[<?= htmlspecialchars($o['mevki']) ?>] <?= htmlspecialchars($o['isim']) ?> (<?= $o['ovr'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="font-size:1.5rem; text-align:center; color:rgba(255,255,255,0.3);">⇄</div>
                    <div>
                        <div style="font-size:0.75rem; color:rgba(255,255,255,0.4); margin-bottom:5px; text-transform:uppercase; letter-spacing:1px;">Girecek</div>
                        <select class="sub-select" id="giren_oyuncu">
                            <option value="">-- Seç --</option>
                            <?php foreach($yedek_oyuncular as $o): ?>
                            <option value="<?= $o['id'] ?>">[<?= htmlspecialchars($o['mevki']) ?>] <?= htmlspecialchars($o['isim']) ?> (<?= $o['ovr'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn-tactics-apply" onclick="uygulaIstek()">✅ UYGULA</button>
                <div id="sub_sonuc" style="margin-top:8px; font-size:0.85rem; color:#22c55e; display:none;"></div>
            </div>
            <?php endif; ?>

            <button class="btn-devam" onclick="devamEt()">▶ MAÇA DEVAM ET</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Maç Sonu Modal -->
    <div class="stats-modal" id="stats_modal">
        <div class="stats-box">
            <h2 class="font-oswald text-center mb-1">MAÇ SONUCU</h2>
            <div class="text-center font-oswald text-muted mb-4 fs-5" id="final_score_text"></div>

            <!-- FAZ 2: Hava Durumu bilgisi -->
            <div class="text-center mb-3" style="font-size:0.9rem; color:rgba(255,255,255,0.4);">
                <?= $hava_icon ?> <?= htmlspecialchars($hava_durumu) ?> hava koşullarında oynandı
                <?php if($is_derbi): ?> &nbsp;•&nbsp; 🔥 Derbi Maçı<?php endif; ?>
            </div>

            <div class="stat-row">
                <span style="color:#3b82f6;">%<?= $ev_topla_oynama ?></span>
                <div class="stat-bar-bg"><div class="stat-bar-ev" id="bar_ev_pos"></div><div class="stat-bar-dep" id="bar_dep_pos"></div></div>
                <span style="color:#ef4444;">%<?= $dep_topla_oynama ?></span>
            </div>
            <div class="text-center text-muted small mb-3 text-uppercase">Topla Oynama</div>

            <div class="stat-row">
                <span style="color:#3b82f6;"><?= $ev_sut ?></span>
                <div class="stat-bar-bg"><div class="stat-bar-ev" id="bar_ev_sut"></div><div class="stat-bar-dep" id="bar_dep_sut"></div></div>
                <span style="color:#ef4444;"><?= $dep_sut ?></span>
            </div>
            <div class="text-center text-muted small mb-4 text-uppercase">Toplam Şut</div>

            <!-- FAZ 2: Kaleci Kurtarış İstatistikleri -->
            <?php if($ev_kurtaris > 0 || $dep_kurtaris > 0): ?>
            <div style="font-family:'Oswald'; font-size:0.8rem; color:rgba(255,255,255,0.4); text-align:center; margin-bottom:8px; letter-spacing:1px;">🧤 KALECİ KURTARIŞLARI</div>
            <div class="gk-saves-row">
                <div class="gk-card">
                    <div class="gk-name"><?= htmlspecialchars($ev_kaleci_isim ?: $mac['ev_ad']) ?></div>
                    <div class="gk-saves"><?= $ev_kurtaris ?></div>
                    <div class="gk-label">Kurtarış</div>
                </div>
                <div class="gk-card">
                    <div class="gk-name"><?= htmlspecialchars($dep_kaleci_isim ?: $mac['dep_ad']) ?></div>
                    <div class="gk-saves"><?= $dep_kurtaris ?></div>
                    <div class="gk-label">Kurtarış</div>
                </div>
            </div>
            <?php endif; ?>

            <a href="../<?= $geridon_link ?>?hafta=<?= $hafta_yonlendirme ?>" class="btn-end">
                İLERLE <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <script>
        const events      = <?= $json_olaylar ?>;
        const evName      = "<?= addslashes($mac['ev_ad']) ?>";
        const depName     = "<?= addslashes($mac['dep_ad']) ?>";
        const isDerbi     = <?= $is_derbi ? 'true' : 'false' ?>;
        const havaDurumu  = "<?= addslashes($hava_durumu) ?>";
        const kullanici   = "<?= addslashes($kullanici_takim_kim) ?>"; // 'ev', 'dep', or ''
        const macId       = <?= $mac_id ?>;
        const ligKodu     = "<?= addslashes($lig_kodu) ?>";

        let currentMinute = 0;
        let scoreEv  = 0;
        let scoreDep = 0;
        let eventIndex = 0;
        let matchInterval = null;
        let tacticsPanelShown = false;
        let secilenFormation  = "<?= addslashes($ayar_dizilis) ?>";

        const timeEl    = document.getElementById('match_time');
        const scoreEvEl = document.getElementById('score_ev');
        const scoreDepEl= document.getElementById('score_dep');
        const ticker    = document.getElementById('commentary_container');

        const goalAnim   = document.getElementById('goal_anim');
        const goalScorer = document.getElementById('goal_scorer_name');
        const varAnim    = document.getElementById('var_anim');
        const varSubText = document.getElementById('var_sub_text');

        // --- FAZ 2: KAR EFEKTİ ---
        if (havaDurumu === 'Karlı') {
            const canvas = document.getElementById('snowCanvas');
            if (canvas) {
                canvas.width  = window.innerWidth;
                canvas.height = window.innerHeight;
                const ctx = canvas.getContext('2d');
                const flakes = Array.from({length: 80}, () => ({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    r: Math.random() * 3 + 1,
                    sp: Math.random() * 1.5 + 0.5
                }));
                function drawSnow() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    ctx.fillStyle = 'rgba(224,242,254,0.8)';
                    flakes.forEach(f => {
                        ctx.beginPath();
                        ctx.arc(f.x, f.y, f.r, 0, Math.PI * 2);
                        ctx.fill();
                        f.y += f.sp;
                        f.x += Math.sin(f.y / 30) * 0.5;
                        if (f.y > canvas.height) { f.y = -5; f.x = Math.random() * canvas.width; }
                    });
                    requestAnimationFrame(drawSnow);
                }
                drawSnow();
            }
        }

        const golCümleleri = [
            "Muhteşem bir vuruş, kaleci çaresiz!",
            "Defans büyük bir hata yaptı ve cezayı kestiler.",
            "Klas bir vuruşla meşin yuvarlağı ağlara gönderiyor.",
            "Tribünler ayakta! Harika bir organizasyon.",
            "Sert vuruş, kaleci hiçbir şey yapamadı.",
            "Müthiş bir bireysel aksiyonla rakip kaleye gönderdi!"
        ];

        // Sadece son 3 yorumu ekranda tut
        function addCommentary(text, type = '') {
            const line = document.createElement('div');
            line.className = `comm-line ${type}`;

            let icon = "";
            if(type === 'gol')      icon = `<i class="fa-solid fa-futbol" style="color:#facc15; font-size:1.5rem;"></i>`;
            else if(type === 'sari')  icon = `<i class="fa-solid fa-clone" style="color:#facc15; font-size:1.5rem;"></i>`;
            else if(type === 'kirmizi') icon = `<i class="fa-solid fa-clone" style="color:#ef4444; font-size:1.5rem;"></i>`;
            else if(type === 'var-line') icon = `<span style="font-size:1.5rem;">🎬</span>`;
            else if(type === 'sub-line') icon = `<span style="font-size:1.5rem;">🔄</span>`;
            else icon = `<i class="fa-solid fa-microphone text-muted" style="font-size:1.5rem;"></i>`;

            line.innerHTML = `
                <div class="dk-badge">${currentMinute}'</div>
                <div>${icon}</div>
                <div class="comm-text">${text}</div>
            `;

            ticker.appendChild(line);
            if(ticker.children.length > 3) ticker.removeChild(ticker.firstElementChild);
        }

        // --- FAZ 2: VAR ANİMASYON ---
        function showVAR(oyuncu, tip, neden) {
            return new Promise(resolve => {
                // Adım 1: VAR başlıyor
                varSubText.innerText = 'Hakem VAR\'a gidiyor...';
                varAnim.classList.add('show');

                setTimeout(() => {
                    // Adım 2: Karar
                    let karar = '';
                    if (tip === 'var_gol_iptal') {
                        const nedenMt = (neden === 'ofsayt') ? 'OFSAYTtan' : 'FAULDEN';
                        karar = `⛔ GOL İPTAL! ${oyuncu} ${nedenMt} iptal edildi.`;
                        varSubText.innerText = 'GOL İPTAL!';
                    } else {
                        karar = `🟥 VAR kararıyla KIRMIZI KART! ${oyuncu} oyundan çıkarılıyor.`;
                        varSubText.innerText = 'KIRMIZI KART!';
                    }

                    setTimeout(() => {
                        varAnim.classList.remove('show');
                        addCommentary(karar, 'var-line');
                        resolve();
                    }, 2500);
                }, 2500);
            });
        }

        // İlk Yorum
        if (isDerbi) {
            addCommentary(`🔥 DERBİ MAÇI! İki dev rakip karşı karşıya. Stadyum alev alev! ${evName} - ${depName}`);
        } else {
            addCommentary(`Karşılaşma hakemin düdüğüyle başlıyor. İki takıma da başarılar...`);
        }

        if (havaDurumu === 'Yağmurlu') {
            setTimeout(() => addCommentary(`🌧️ Yağmurlu hava koşulları oyunu zorlaştırıyor. Oyuncuların kondisyonu daha hızlı düşecek!`), 1000);
        } else if (havaDurumu === 'Karlı') {
            setTimeout(() => addCommentary(`❄️ Kar altında zorlu bir maç! Sahada kayganlaşma var, sürprizler kapıda olabilir.`), 1000);
        }

        // --- TAKTİK PANELİ FONKSİYONLARI ---
        function secFormation(f, btn) {
            secilenFormation = f;
            document.querySelectorAll('.formation-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function uygulaIstek() {
            const cikanId = document.getElementById('cikan_oyuncu')?.value;
            const girenId = document.getElementById('giren_oyuncu')?.value;

            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=taktik_degistir&dizilis=${encodeURIComponent(secilenFormation)}&cikan_id=${cikanId||0}&giren_id=${girenId||0}`
            })
            .then(r => r.json())
            .then(data => {
                const sonucEl = document.getElementById('sub_sonuc');
                if (sonucEl) {
                    sonucEl.style.display = 'block';
                    if (data.ok) {
                        sonucEl.innerHTML = `✅ Diziliş: <strong>${data.dizilis}</strong>` + (data.sub ? ` — Değişiklik: <strong>${data.sub}</strong>` : '');
                        if (data.sub) {
                            window._pendingSubMsg = data.sub;
                        }
                    } else {
                        sonucEl.style.color = '#ef4444';
                        sonucEl.innerText = '⚠️ Hata: ' + (data.hata || 'Bilinmeyen');
                    }
                }
            })
            .catch(() => {});
        }

        function devamEt() {
            const overlay = document.getElementById('tactics_overlay');
            if (overlay) overlay.classList.remove('show');

            // Değişiklik mesajı yorum bandına yansıt
            if (window._pendingSubMsg) {
                addCommentary(`🔄 <strong>OYUNCU DEĞİŞİKLİĞİ!</strong> ${window._pendingSubMsg}`, 'sub-line');
                window._pendingSubMsg = null;
            }
            addCommentary(`▶️ Maç devam ediyor... Yeni diziliş: <strong>${secilenFormation}</strong>`);

            // Interval'ı yeniden başlat
            startMatch();
        }

        // MAÇ DÖNGÜSÜ (async; VAR olaylarında duraklar)
        async function processMinute() {
            currentMinute++;
            let minStr = currentMinute < 10 ? '0' + currentMinute : currentMinute;
            timeEl.innerText = `${minStr}:00`;

            while (eventIndex < events.length && events[eventIndex].dk <= currentMinute) {
                let e = events[eventIndex];
                let isEv = (e.kim === 'ev');
                let takimAdi = isEv ? evName : depName;

                if (e.tur === 'gol') {
                    if (isEv) {
                        scoreEv++; scoreEvEl.innerText = scoreEv;
                        scoreEvEl.classList.add('score-update'); setTimeout(() => scoreEvEl.classList.remove('score-update'), 500);
                    } else {
                        scoreDep++; scoreDepEl.innerText = scoreDep;
                        scoreDepEl.classList.add('score-update'); setTimeout(() => scoreDepEl.classList.remove('score-update'), 500);
                    }
                    goalScorer.innerText = `${e.oyuncu} (${takimAdi})`;
                    goalAnim.classList.add('show');
                    setTimeout(() => goalAnim.classList.remove('show'), 3000);
                    let cumb = golCümleleri[Math.floor(Math.random() * golCümleleri.length)];
                    let asistText = (e.asist && e.asist !== '-') ? ` <span style='color:rgba(255,255,255,0.6);'>(Asist: ${e.asist})</span>` : '';
                    addCommentary(`<strong>GOOOOOOLLL!</strong> ${e.oyuncu} sahneye çıktı! ${cumb}${asistText}`, 'gol');
                }
                else if (e.tur === 'sari') {
                    addCommentary(`Sert müdahale. <strong>${e.oyuncu}</strong> (${takimAdi}) sarı kart görüyor.`, 'sari');
                }
                else if (e.tur === 'kirmizi') {
                    addCommentary(`KIRMIZI KART! <strong>${e.oyuncu}</strong> (${takimAdi}) oyundan atıldı! Takım eksik kaldı.`, 'kirmizi');
                }
                else if (e.tur === 'var_gol_iptal' || e.tur === 'var_kirmizi') {
                    // VAR olayında interval durdur, animasyon göster, sonra devam et
                    clearInterval(matchInterval);
                    await showVAR(e.oyuncu, e.tur, e.neden || 'ofsayt');
                    eventIndex++;
                    startMatch();
                    return; // Bu tick bitti, döngüden çık
                }
                eventIndex++;
            }

            if (currentMinute === 45) addCommentary("İlk yarı sonucu. Takımlar soyunma odasına gidiyor.");
            else if (currentMinute === 46) addCommentary("İkinci yarı başladı!");

            // --- FAZ 2: TAKTİK PANELİ — 60. dakikada göster (sadece kullanıcı takımı varsa) ---
            if (currentMinute === 60 && kullanici && !tacticsPanelShown) {
                tacticsPanelShown = true;
                clearInterval(matchInterval);

                let skText = `${scoreEv} - ${scoreDep}`;
                addCommentary(`⏸️ <strong>60. DAKİKA!</strong> Skor: ${evName} ${skText} ${depName} — Taktik değişikliği zamanı!`);

                setTimeout(() => {
                    const overlay = document.getElementById('tactics_overlay');
                    if (overlay) overlay.classList.add('show');
                }, 800);
                return; // devamEt() ile yeniden başlar
            }

            // MAÇ BİTİŞİ
            if (currentMinute >= 90) {
                clearInterval(matchInterval);
                timeEl.innerText = "MS";
                timeEl.style.background = "#10b981";
                addCommentary("Maç sona erdi! 90 dakikalık mücadele bitti.");

                setTimeout(() => {
                    document.getElementById('stats_modal').classList.add('show');
                    document.getElementById('final_score_text').innerText = `${evName} ${scoreEv} - ${scoreDep} ${depName}`;

                    document.getElementById('bar_ev_pos').style.width  = "<?= $ev_topla_oynama ?>%";
                    document.getElementById('bar_dep_pos').style.width = "<?= $dep_topla_oynama ?>%";

                    let totalSut = <?= $ev_sut + $dep_sut ?>;
                    let ev_sut_yuzde  = totalSut > 0 ? (<?= $ev_sut ?>  / totalSut) * 100 : 50;
                    let dep_sut_yuzde = totalSut > 0 ? 100 - ev_sut_yuzde : 50;
                    document.getElementById('bar_ev_sut').style.width  = ev_sut_yuzde  + "%";
                    document.getElementById('bar_dep_sut').style.width = dep_sut_yuzde + "%";
                }, 1500);
            }
        }

        function startMatch() {
            matchInterval = setInterval(() => {
                processMinute();
            }, 150);
        }

        // Oyunu başlat
        startMatch();
    </script>
</body>
</html>