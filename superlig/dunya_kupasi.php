<?php
// ==============================================================================
// FAZ 6: DÜNYA KUPASI (WORLD CUP) — Her 4 Sezonda Bir
// 32 takımlı turnuva ağacı, milli takım teklifi, grup aşaması ve eleme turları.
// ==============================================================================
include 'db.php';

$mesaj      = "";
$mesaj_tipi = "";

$guncel_sezon = 2025;
$guncel_hafta = 1;
$sezon_sayaci = 1;
try { $guncel_sezon = (int)$pdo->query("SELECT sezon_yil FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
try { $guncel_hafta = (int)$pdo->query("SELECT hafta FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}
try { $sezon_sayaci = (int)$pdo->query("SELECT COALESCE(sezon_sayaci,1) FROM ayar LIMIT 1")->fetchColumn(); } catch (Throwable $e) {}

// Dünya Kupası sadece 4. sezonda aktiftir
$dk_aktif = ($sezon_sayaci % 4 === 0);
$dk_sonraki_sezon = $guncel_sezon + (4 - ($sezon_sayaci % 4 === 0 ? 4 : ($sezon_sayaci % 4)));

// ================================================================
// 32 MİLLİ TAKIM HAVUZU
// ================================================================
$milli_takimlar = [
    // Avrupa (UEFA - 13 takım)
    ['adi' => 'Fransa',     'kod' => 'FRA', 'grup' => 'A', 'guc' => 88],
    ['adi' => 'Brezilya',   'kod' => 'BRA', 'grup' => 'A', 'guc' => 90],
    ['adi' => 'Almanya',    'kod' => 'GER', 'grup' => 'B', 'guc' => 86],
    ['adi' => 'Arjantin',   'kod' => 'ARG', 'grup' => 'B', 'guc' => 88],
    ['adi' => 'İspanya',    'kod' => 'ESP', 'grup' => 'C', 'guc' => 87],
    ['adi' => 'Portekiz',   'kod' => 'POR', 'grup' => 'C', 'guc' => 85],
    ['adi' => 'İngiltere',  'kod' => 'ENG', 'grup' => 'D', 'guc' => 84],
    ['adi' => 'Hollanda',   'kod' => 'NED', 'grup' => 'D', 'guc' => 83],
    ['adi' => 'Belçika',    'kod' => 'BEL', 'grup' => 'E', 'guc' => 83],
    ['adi' => 'İtalya',     'kod' => 'ITA', 'grup' => 'E', 'guc' => 84],
    ['adi' => 'Hırvatistan','kod' => 'CRO', 'grup' => 'F', 'guc' => 82],
    ['adi' => 'Danimarka',  'kod' => 'DEN', 'grup' => 'F', 'guc' => 81],
    ['adi' => 'Türkiye',    'kod' => 'TUR', 'grup' => 'G', 'guc' => 78],
    ['adi' => 'Sırbistan',  'kod' => 'SRB', 'grup' => 'G', 'guc' => 79],
    // Amerika (CONMEBOL + CONCACAF - 8 takım)
    ['adi' => 'Uruguay',    'kod' => 'URU', 'grup' => 'H', 'guc' => 80],
    ['adi' => 'Kolombiya',  'kod' => 'COL', 'grup' => 'H', 'guc' => 79],
    ['adi' => 'Meksika',    'kod' => 'MEX', 'grup' => 'A', 'guc' => 78],
    ['adi' => 'ABD',        'kod' => 'USA', 'grup' => 'B', 'guc' => 77],
    ['adi' => 'Şili',       'kod' => 'CHI', 'grup' => 'C', 'guc' => 76],
    ['adi' => 'Peru',       'kod' => 'PER', 'grup' => 'D', 'guc' => 74],
    ['adi' => 'Ekvador',    'kod' => 'ECU', 'grup' => 'E', 'guc' => 73],
    ['adi' => 'Kanada',     'kod' => 'CAN', 'grup' => 'F', 'guc' => 75],
    // Afrika (CAF - 6 takım)
    ['adi' => 'Senegal',    'kod' => 'SEN', 'grup' => 'G', 'guc' => 79],
    ['adi' => 'Fas',        'kod' => 'MAR', 'grup' => 'H', 'guc' => 80],
    ['adi' => 'Nijerya',    'kod' => 'NGA', 'grup' => 'A', 'guc' => 76],
    ['adi' => 'Gana',       'kod' => 'GHA', 'grup' => 'B', 'guc' => 74],
    ['adi' => 'Kamerun',    'kod' => 'CMR', 'grup' => 'C', 'guc' => 73],
    ['adi' => 'Mısır',      'kod' => 'EGY', 'grup' => 'D', 'guc' => 75],
    // Asya + Okyanusya (AFC/OFC - 5 takım)
    ['adi' => 'Japonya',    'kod' => 'JPN', 'grup' => 'E', 'guc' => 77],
    ['adi' => 'G. Kore',    'kod' => 'KOR', 'grup' => 'F', 'guc' => 76],
    ['adi' => 'İran',       'kod' => 'IRN', 'grup' => 'G', 'guc' => 74],
    ['adi' => 'Avustralya', 'kod' => 'AUS', 'grup' => 'H', 'guc' => 73],
];

// ================================================================
// DÜNYA KUPASI OLUŞTUR (Yeni turnuva başlat)
// ================================================================
if (isset($_POST['dk_olustur'])) {
    try {
        $pdo->prepare("INSERT INTO dunya_kupasi (sezon_yil, durum, menajer_milli_takim) VALUES (?, 'grup_asamasi', ?)")
            ->execute([$guncel_sezon, $_POST['milli_takim'] ?? null]);
        $dk_id = $pdo->lastInsertId();

        // Takımları ekle
        $ins_takim = $pdo->prepare(
            "INSERT INTO dunya_kupasi_takim (kupasi_id, takim_adi, ulke_kodu, grup, guc) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($milli_takimlar as $mt) {
            $ins_takim->execute([$dk_id, $mt['adi'], $mt['kod'], $mt['grup'], $mt['guc']]);
        }

        // Grup maçlarını oluştur (her grupta 4 takım, 6 maç)
        $gruplardaki_takimlar = [];
        foreach ($milli_takimlar as $mt) {
            $gruplardaki_takimlar[$mt['grup']][] = $mt['adi'];
        }
        $ins_mac = $pdo->prepare(
            "INSERT INTO dunya_kupasi_mac (kupasi_id, tur, grup, ev_takim, dep_takim) VALUES (?, 'grup', ?, ?, ?)"
        );
        foreach ($gruplardaki_takimlar as $grup => $takimlar) {
            $n = count($takimlar);
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $ins_mac->execute([$dk_id, $grup, $takimlar[$i], $takimlar[$j]]);
                }
            }
        }

        // Menajer katılım bayrağı
        if (!empty($_POST['milli_takim'])) {
            $pdo->prepare("UPDATE dunya_kupasi SET menajer_katildi=1 WHERE id=?")->execute([$dk_id]);
        }

        $mesaj      = "🌍 " . $guncel_sezon . " Dünya Kupası oluşturuldu! Grup maçları hazırlandı.";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// ================================================================
// GRUP MAÇLARINI OYNA (Simüle et)
// ================================================================
if (isset($_POST['grup_oyna'])) {
    $dk_id = (int)$_POST['dk_id'];
    try {
        // Oynanmamış grup maçlarını çek
        $maclar = $pdo->prepare("SELECT * FROM dunya_kupasi_mac WHERE kupasi_id=? AND tur='grup' AND oynandi=0");
        $maclar->execute([$dk_id]);
        $maclar = $maclar->fetchAll(PDO::FETCH_ASSOC);

        // Takım güçlerini yükle
        $guc_map = [];
        $stmt = $pdo->prepare("SELECT takim_adi, guc FROM dunya_kupasi_takim WHERE kupasi_id=?");
        $stmt->execute([$dk_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $guc_map[$r['takim_adi']] = $r['guc'];

        foreach ($maclar as $mac) {
            $ev_guc  = $guc_map[$mac['ev_takim']]  ?? 70;
            $dep_guc = $guc_map[$mac['dep_takim']] ?? 70;
            // Güce dayalı gol simülasyonu
            $ev_gol  = max(0, round($ev_guc  / 30 + mt_rand(-1, 2) + ($ev_guc  - $dep_guc) / 40));
            $dep_gol = max(0, round($dep_guc / 30 + mt_rand(-1, 2) + ($dep_guc - $ev_guc)  / 40));

            $pdo->prepare("UPDATE dunya_kupasi_mac SET ev_gol=?, dep_gol=?, oynandi=1 WHERE id=?")
                ->execute([$ev_gol, $dep_gol, $mac['id']]);

            // Puan hesapla
            if ($ev_gol > $dep_gol)      { $ep = 3; $dp = 0; }
            elseif ($ev_gol === $dep_gol) { $ep = 1; $dp = 1; }
            else                          { $ep = 0; $dp = 3; }

            $pdo->prepare(
                "UPDATE dunya_kupasi_takim
                 SET puan=puan+?, atilan_gol=atilan_gol+?, yenilen_gol=yenilen_gol+?
                 WHERE kupasi_id=? AND takim_adi=?"
            )->execute([$ep, $ev_gol, $dep_gol, $dk_id, $mac['ev_takim']]);

            $pdo->prepare(
                "UPDATE dunya_kupasi_takim
                 SET puan=puan+?, atilan_gol=atilan_gol+?, yenilen_gol=yenilen_gol+?
                 WHERE kupasi_id=? AND takim_adi=?"
            )->execute([$dp, $dep_gol, $ev_gol, $dk_id, $mac['dep_takim']]);
        }

        $pdo->prepare("UPDATE dunya_kupasi SET durum='son_16' WHERE id=?")->execute([$dk_id]);
        $mesaj      = "✅ Tüm grup maçları oynandı! Son 16 turuna hazır.";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

// ================================================================
// ELEMİNASYON TURLARINI OLUŞTUR VE OYNATabi
// ================================================================
function eleme_turu_olustur($pdo, int $dk_id, string $mevcut_tur, string $sonraki_tur): void {
    // Mevcut turdan kazananları topla
    $maclar = $pdo->prepare("SELECT * FROM dunya_kupasi_mac WHERE kupasi_id=? AND tur=? AND oynandi=1");
    $maclar->execute([$dk_id, $mevcut_tur]);
    $kazananlar = [];
    foreach ($maclar->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if ($m['ev_gol'] > $m['dep_gol'])      $kazananlar[] = $m['ev_takim'];
        elseif ($m['dep_gol'] > $m['ev_gol'])  $kazananlar[] = $m['dep_takim'];
        else {
            // Beraberlik → penalti simülasyonu
            $kazananlar[] = mt_rand(0, 1) === 0 ? $m['ev_takim'] : $m['dep_takim'];
        }
    }
    shuffle($kazananlar);
    $ins = $pdo->prepare("INSERT INTO dunya_kupasi_mac (kupasi_id, tur, ev_takim, dep_takim) VALUES (?, ?, ?, ?)");
    for ($i = 0; $i + 1 < count($kazananlar); $i += 2) {
        $ins->execute([$dk_id, $sonraki_tur, $kazananlar[$i], $kazananlar[$i+1]]);
    }
}

function eleme_simule($pdo, int $dk_id, string $tur, array $guc_map, string $sonraki_durum): string {
    $maclar = $pdo->prepare("SELECT * FROM dunya_kupasi_mac WHERE kupasi_id=? AND tur=? AND oynandi=0");
    $maclar->execute([$dk_id, $tur]);
    $maclar = $maclar->fetchAll(PDO::FETCH_ASSOC);
    if (empty($maclar)) return "Bu turda oynanacak maç yok.";

    foreach ($maclar as $mac) {
        $ev_guc  = $guc_map[$mac['ev_takim']]  ?? 70;
        $dep_guc = $guc_map[$mac['dep_takim']] ?? 70;
        $ev_gol  = max(0, round($ev_guc  / 30 + mt_rand(0, 2) + ($ev_guc  - $dep_guc) / 50));
        $dep_gol = max(0, round($dep_guc / 30 + mt_rand(0, 2) + ($dep_guc - $ev_guc)  / 50));
        $penalti = 0;
        if ($ev_gol === $dep_gol) {
            $penalti = 1;
            mt_rand(0, 1) === 0 ? $ev_gol++ : $dep_gol++;
        }
        $pdo->prepare("UPDATE dunya_kupasi_mac SET ev_gol=?, dep_gol=?, oynandi=1, penalti=? WHERE id=?")
            ->execute([$ev_gol, $dep_gol, $penalti, $mac['id']]);
    }
    $pdo->prepare("UPDATE dunya_kupasi SET durum=? WHERE id=?")->execute([$sonraki_durum, $dk_id]);
    return "✅ $tur turundaki maçlar oynandı!";
}

if (isset($_POST['son16_olustur'])) {
    $dk_id = (int)$_POST['dk_id'];
    try {
        // Her gruptan ilk 2 takımı al
        $gruplardaki = [];
        $stmt = $pdo->prepare(
            "SELECT takim_adi, grup, puan, (atilan_gol - yenilen_gol) AS averaj, atilan_gol
             FROM dunya_kupasi_takim WHERE kupasi_id=? ORDER BY grup, puan DESC, averaj DESC, atilan_gol DESC"
        );
        $stmt->execute([$dk_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $gruplardaki[$r['grup']][] = $r['takim_adi'];
        }
        $son16 = [];
        foreach ($gruplardaki as $grup => $takimlar) {
            if (isset($takimlar[0])) $son16['birinci'][]  = $takimlar[0];
            if (isset($takimlar[1])) $son16['ikinci'][]   = $takimlar[1];
        }
        // Eşleştirme: A1-B2, B1-A2, C1-D2, D1-C2, E1-F2, F1-E2, G1-H2, H1-G2
        $gruplar = ['A','B','C','D','E','F','G','H'];
        $birinciler = array_combine($gruplar, $son16['birinci'] ?? array_fill(0, 8, ''));
        $ikinciler  = array_combine($gruplar, $son16['ikinci']  ?? array_fill(0, 8, ''));
        $eslesme = [
            ['A','B'],['C','D'],['E','F'],['G','H'],
            ['B','A'],['D','C'],['F','E'],['H','G'],
        ];
        $ins = $pdo->prepare("INSERT INTO dunya_kupasi_mac (kupasi_id, tur, ev_takim, dep_takim) VALUES (?, 'son_16', ?, ?)");
        foreach ($eslesme as [$g1, $g2]) {
            if (!empty($birinciler[$g1]) && !empty($ikinciler[$g2])) {
                $ins->execute([$dk_id, $birinciler[$g1], $ikinciler[$g2]]);
            }
        }
        $mesaj      = "✅ Son 16 eşleşmeleri oluşturuldu!";
        $mesaj_tipi = "success";
    } catch (Throwable $e) {
        $mesaj      = "Hata: " . $e->getMessage();
        $mesaj_tipi = "danger";
    }
}

if (isset($_POST['tur_oyna'])) {
    $dk_id = (int)$_POST['dk_id'];
    $tur   = $_POST['tur'];
    $gecerli_turlar = ['son_16', 'ceyrek', 'yari', 'final'];
    $sonraki_durum  = ['son_16'=>'ceyrek','ceyrek'=>'yari','yari'=>'final','final'=>'tamamlandi'];
    $sonraki_tur    = ['son_16'=>'ceyrek','ceyrek'=>'yari','yari'=>'final'];
    if (in_array($tur, $gecerli_turlar, true)) {
        try {
            $guc_stmt = $pdo->prepare("SELECT takim_adi, guc FROM dunya_kupasi_takim WHERE kupasi_id=?");
            $guc_stmt->execute([$dk_id]);
            $guc_map = [];
            foreach ($guc_stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $guc_map[$r['takim_adi']] = $r['guc'];

            $sonuc = eleme_simule($pdo, $dk_id, $tur, $guc_map, $sonraki_durum[$tur]);
            $mesaj = $sonuc;
            $mesaj_tipi = "success";

            // Final bittiyse şampiyon belirle
            if ($tur === 'final') {
                $final_mac = $pdo->prepare("SELECT * FROM dunya_kupasi_mac WHERE kupasi_id=? AND tur='final' AND oynandi=1 ORDER BY id DESC LIMIT 1");
                $final_mac->execute([$dk_id]);
                $final = $final_mac->fetch(PDO::FETCH_ASSOC);
                if ($final) {
                    $sampion = $final['ev_gol'] >= $final['dep_gol'] ? $final['ev_takim'] : $final['dep_takim'];
                    $pdo->prepare("UPDATE dunya_kupasi SET sampion=?, durum='tamamlandi' WHERE id=?")->execute([$sampion, $dk_id]);
                    $mesaj = "🏆 DÜNYA KUPASI ŞAMPIYONU: $sampion! Tebrikler!";
                }
            }

            // Sonraki tur maçlarını oluştur
            if ($tur !== 'final' && isset($sonraki_tur[$tur])) {
                eleme_turu_olustur($pdo, $dk_id, $tur, $sonraki_tur[$tur]);
            }
        } catch (Throwable $e) {
            $mesaj      = "Hata: " . $e->getMessage();
            $mesaj_tipi = "danger";
        }
    }
}

// ================================================================
// MEVCUT DÜNYA KUPASI VERİSİ ÇEK
// ================================================================
$guncel_dk = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM dunya_kupasi WHERE sezon_yil=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$guncel_sezon]);
    $guncel_dk = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$grup_puan = $maclar_listesi = [];
if ($guncel_dk) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM dunya_kupasi_takim WHERE kupasi_id=? ORDER BY grup, puan DESC, (atilan_gol - yenilen_gol) DESC");
        $stmt->execute([$guncel_dk['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $grup_puan[$r['grup']][] = $r;
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT * FROM dunya_kupasi_mac WHERE kupasi_id=? ORDER BY tur, id");
        $stmt->execute([$guncel_dk['id']]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $maclar_listesi[$r['tur']][] = $r;
    } catch (Throwable $e) {}
}

$tur_etiket = [
    'grup'    => 'Grup Aşaması',
    'son_16'  => 'Son 16',
    'ceyrek'  => 'Çeyrek Final',
    'yari'    => 'Yarı Final',
    'final'   => 'Final',
];
$dk_durum_etiket = [
    'kurulum'      => 'Kurulum',
    'grup_asamasi' => 'Grup Aşaması',
    'son_16'       => 'Son 16',
    'ceyrek'       => 'Çeyrek Final',
    'yari'         => 'Yarı Final',
    'final'        => 'Final',
    'tamamlandi'   => 'Tamamlandı',
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dünya Kupası | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;800&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { background:#050505; color:#fff; font-family:'Poppins',sans-serif; min-height:100vh; }
    .bg-overlay { position:fixed; top:0; left:0; width:100vw; height:100vh; background:radial-gradient(ellipse at top, #00101a 0%, #050505 70%); z-index:-1; }
    .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
    .gold { color:#d4af37; }
    .gold-gradient { background:linear-gradient(45deg,#d4af37,#fde047); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    .glass-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12); border-radius:20px; backdrop-filter:blur(20px); padding:24px; margin-bottom:24px; }
    .dk-banner { background:linear-gradient(135deg,rgba(6,78,59,0.3),rgba(5,5,5,0.8)); border:2px solid rgba(16,185,129,0.4); border-radius:24px; padding:40px; text-align:center; margin-bottom:30px; }
    .dk-banner h1 { font-size:4rem; margin-bottom:10px; }
    .grup-card { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:16px; margin-bottom:16px; }
    .grup-header { font-family:'Oswald',sans-serif; font-size:1rem; letter-spacing:2px; color:#d4af37; margin-bottom:10px; }
    .grup-tablo th { color:#94a3b8; font-size:0.75rem; border-color:rgba(255,255,255,0.06); padding:4px 8px; }
    .grup-tablo td { font-size:0.85rem; border-color:rgba(255,255,255,0.04); padding:5px 8px; vertical-align:middle; }
    .grup-tablo tbody tr:nth-child(-n+2) { background:rgba(16,185,129,0.08); }
    .mac-card { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:14px; margin-bottom:10px; text-align:center; }
    .mac-skor { font-size:1.8rem; font-weight:900; color:#d4af37; }
    .mac-takim { font-size:0.9rem; font-weight:600; }
    .btn-world { background:linear-gradient(135deg,#064e3b,#10b981); color:#fff; font-weight:800; border:none; border-radius:12px; padding:12px 30px; letter-spacing:1px; }
    .btn-world:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(16,185,129,0.4); color:#fff; }
    .btn-danger-custom { background:linear-gradient(135deg,#7f1d1d,#ef4444); color:#fff; font-weight:800; border:none; border-radius:12px; padding:12px 30px; }
    .btn-danger-custom:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(239,68,68,0.4); color:#fff; }
    .sampion-kart { background:linear-gradient(135deg,rgba(212,175,55,0.3),rgba(253,224,71,0.1)); border:2px solid #d4af37; border-radius:24px; padding:40px; text-align:center; box-shadow:0 0 60px rgba(212,175,55,0.4); margin-bottom:30px; }
    .form-control-dark { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#fff; border-radius:10px; }
    .form-control-dark:focus { background:rgba(255,255,255,0.1); border-color:#10b981; color:#fff; box-shadow:0 0 0 0.2rem rgba(16,185,129,0.25); }
    .back-btn { color:#94a3b8; text-decoration:none; font-size:0.9rem; }
    .back-btn:hover { color:#10b981; }
    .section-title { font-size:1.5rem; font-weight:800; margin-bottom:20px; border-left:3px solid #10b981; padding-left:14px; }
    .durum-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-size:0.8rem; font-weight:700; background:rgba(16,185,129,0.2); border:1px solid rgba(16,185,129,0.4); color:#6ee7b7; }
    .cycle-info { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:20px; text-align:center; }
    .cycle-num { font-size:3rem; font-weight:900; color:#d4af37; }
</style>
</head>
<body>
<div class="bg-overlay"></div>
<div class="container-fluid px-4 pb-5">
    <div class="d-flex align-items-center py-4">
        <a href="index.php" class="back-btn me-auto"><i class="fa-solid fa-arrow-left me-2"></i>Ana Menü</a>
        <span class="font-oswald gold" style="font-size:1.1rem;letter-spacing:2px;">ULTIMATE MANAGER</span>
    </div>

    <!-- HERO BANNER -->
    <div class="dk-banner">
        <div style="font-size:4rem; margin-bottom:10px;">🌍</div>
        <h1 class="font-oswald"><span class="gold-gradient">DÜNYA KUPASI</span></h1>
        <p class="text-secondary mt-2">Her 4 sezonda bir — Milli Takımını yönet, Dünya Kupası'nı kazan!</p>
    </div>

    <?php if ($mesaj): ?>
    <div class="alert alert-<?= $mesaj_tipi ?> glass-card mb-4"><?= $mesaj ?></div>
    <?php endif; ?>

    <!-- DÖNGÜ SAYACI -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="cycle-info">
                <div class="cycle-num"><?= $sezon_sayaci % 4 === 0 ? 4 : $sezon_sayaci % 4 ?>/4</div>
                <div style="color:#94a3b8; font-size:0.85rem;">Sezon Döngüsü</div>
                <div style="color:#10b981; font-size:0.9rem; margin-top:8px;">
                    <?php if ($dk_aktif): ?>
                        <i class="fa-solid fa-circle-check me-1"></i>🏆 DÜNYA KUPASI YILI!
                    <?php else: ?>
                        <i class="fa-solid fa-clock me-1"></i>Sonraki WC: <?= $dk_sonraki_sezon ?> Sezonu
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="cycle-info">
                <div class="cycle-num"><?= $guncel_sezon ?></div>
                <div style="color:#94a3b8; font-size:0.85rem;">Güncel Sezon</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="cycle-info">
                <div class="cycle-num">32</div>
                <div style="color:#94a3b8; font-size:0.85rem;">Takım / 8 Grup</div>
            </div>
        </div>
    </div>

    <!-- ŞAMPİYON KARTI -->
    <?php if ($guncel_dk && $guncel_dk['durum'] === 'tamamlandi' && $guncel_dk['sampion']): ?>
    <div class="sampion-kart">
        <div style="font-size:4rem;">🏆</div>
        <h2 class="font-oswald" style="font-size:3rem; color:#d4af37;"><?= htmlspecialchars($guncel_dk['sampion']) ?></h2>
        <p class="text-secondary"><?= $guncel_sezon ?> FIFA Dünya Kupası Şampiyonu!</p>
    </div>
    <?php endif; ?>

    <!-- MEVCUT TURNUVA -->
    <?php if ($guncel_dk): ?>
    <div class="glass-card mb-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="section-title mb-0"><?= $guncel_sezon ?> Dünya Kupası</div>
            <span class="durum-badge"><?= $dk_durum_etiket[$guncel_dk['durum']] ?? $guncel_dk['durum'] ?></span>
        </div>
        <?php if ($guncel_dk['menajer_milli_takim']): ?>
        <div class="mb-3" style="color:#10b981; font-size:0.9rem;">
            <i class="fa-solid fa-user-tie me-1"></i>
            Yönettiğiniz Milli Takım: <strong><?= htmlspecialchars($guncel_dk['menajer_milli_takim']) ?></strong>
        </div>
        <?php endif; ?>

        <!-- TUR BUTONLARI -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php if ($guncel_dk['durum'] === 'grup_asamasi'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="dk_id" value="<?= $guncel_dk['id'] ?>">
                    <button type="submit" name="grup_oyna" class="btn btn-world">
                        <i class="fa-solid fa-play me-1"></i>Grup Maçlarını Oyna
                    </button>
                </form>
            <?php elseif ($guncel_dk['durum'] === 'son_16'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="dk_id" value="<?= $guncel_dk['id'] ?>">
                    <?php $son16_var = !empty($maclar_listesi['son_16'] ?? []); ?>
                    <?php if (!$son16_var): ?>
                    <button type="submit" name="son16_olustur" class="btn btn-world">
                        <i class="fa-solid fa-list me-1"></i>Son 16 Eşleşmeleri Oluştur
                    </button>
                    <?php else: ?>
                    <input type="hidden" name="tur" value="son_16">
                    <button type="submit" name="tur_oyna" class="btn btn-world">
                        <i class="fa-solid fa-play me-1"></i>Son 16'yı Oyna
                    </button>
                    <?php endif; ?>
                </form>
            <?php elseif (in_array($guncel_dk['durum'], ['ceyrek','yari','final'])): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="dk_id" value="<?= $guncel_dk['id'] ?>">
                    <input type="hidden" name="tur" value="<?= $guncel_dk['durum'] ?>">
                    <button type="submit" name="tur_oyna" class="btn btn-world">
                        <i class="fa-solid fa-play me-1"></i><?= $dk_durum_etiket[$guncel_dk['durum']] ?>'yı Oyna
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- GRUP TABLOLARI -->
    <?php if (!empty($grup_puan)): ?>
    <div class="glass-card mb-4">
        <div class="section-title">📊 Grup Puan Tabloları</div>
        <div class="row g-3">
        <?php foreach ($grup_puan as $grup => $takimlar): ?>
            <div class="col-md-3 col-sm-6">
                <div class="grup-card">
                    <div class="grup-header"><i class="fa-solid fa-flag me-2"></i>GRUP <?= $grup ?></div>
                    <table class="table table-sm grup-tablo mb-0">
                        <thead><tr><th>Takım</th><th>P</th><th>Av</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($takimlar, 0, 4) as $i => $t): ?>
                        <tr>
                            <td>
                                <?php if ($i < 2): ?><strong><?php endif; ?>
                                <?= htmlspecialchars($t['takim_adi']) ?>
                                <?php if ($i < 2): ?></strong><?php endif; ?>
                            </td>
                            <td><strong style="color:<?= $i < 2 ? '#10b981' : '#94a3b8' ?>;"><?= $t['puan'] ?></strong></td>
                            <td style="color:#94a3b8;"><?= $t['atilan_gol'] - $t['yenilen_gol'] >= 0 ? '+' : '' ?><?= $t['atilan_gol'] - $t['yenilen_gol'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ELEME TURLAR MAÇ SONUÇLARI -->
    <?php foreach (['son_16','ceyrek','yari','final'] as $tur): ?>
    <?php if (!empty($maclar_listesi[$tur] ?? [])): ?>
    <div class="glass-card mb-4">
        <div class="section-title"><?= $tur_etiket[$tur] ?> Sonuçları</div>
        <div class="row g-3">
        <?php foreach ($maclar_listesi[$tur] as $mac): ?>
            <div class="col-md-3 col-sm-6">
                <div class="mac-card">
                    <?php if ($mac['oynandi']): ?>
                        <div class="mac-takim"><?= htmlspecialchars($mac['ev_takim']) ?></div>
                        <div class="mac-skor"><?= $mac['ev_gol'] ?> - <?= $mac['dep_gol'] ?><?= $mac['penalti'] ? ' <small style="font-size:0.7rem;color:#94a3b8;">(P)</small>' : '' ?></div>
                        <div class="mac-takim"><?= htmlspecialchars($mac['dep_takim']) ?></div>
                        <?php
                        $k = $mac['ev_gol'] >= $mac['dep_gol'] ? $mac['ev_takim'] : $mac['dep_takim'];
                        ?>
                        <div style="color:#10b981; font-size:0.75rem; margin-top:6px;">✅ <?= htmlspecialchars($k) ?> geçti</div>
                    <?php else: ?>
                        <div class="mac-takim"><?= htmlspecialchars($mac['ev_takim']) ?></div>
                        <div class="mac-skor" style="color:#94a3b8;">? - ?</div>
                        <div class="mac-takim"><?= htmlspecialchars($mac['dep_takim']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>

    <?php else: ?>
    <!-- YENİ TURNUVA OLUŞTUR -->
    <div class="glass-card">
        <div class="section-title">🚀 Yeni Dünya Kupası Başlat</div>
        <?php if (!$dk_aktif): ?>
        <div class="alert alert-warning" style="background:rgba(245,158,11,0.1); border-color:rgba(245,158,11,0.3); color:#fbbf24;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            Dünya Kupası normalde <strong>her 4. sezonda</strong> gerçekleşir (Döngü: <?= $sezon_sayaci % 4 ?>/4).
            Sonraki Dünya Kupası: <strong><?= $dk_sonraki_sezon ?> Sezonu</strong>. Yine de şimdi başlatabilirsiniz!
        </div>
        <?php endif; ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-secondary" style="font-size:0.85rem;">
                        <i class="fa-solid fa-user-tie me-1"></i>Yönetmek İstediğiniz Milli Takım (Opsiyonel)
                    </label>
                    <select name="milli_takim" class="form-control form-control-dark">
                        <option value="">— Milli Takım Yönetmek İstemiyorum —</option>
                        <?php foreach ($milli_takimlar as $mt): ?>
                        <option value="<?= htmlspecialchars($mt['adi']) ?>">
                            <?= htmlspecialchars($mt['adi']) ?> (GÜÇ: <?= $mt['guc'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" name="dk_olustur" class="btn btn-world">
                        <i class="fa-solid fa-globe me-2"></i>DÜNYA KUPASI'NI BAŞLAT!
                    </button>
                </div>
            </div>
        </form>
    </div>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
