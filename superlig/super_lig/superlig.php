<?php
// ==============================================================================
// SÜPER LİG - MERKEZ FİKSTÜR VE MAÇ MOTORU (DARK THEME) - SAF MENAJERLİK SÜRÜMÜ
// ==============================================================================
include '../db.php';

// Merkez Maç Motorunu Bağla
if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, ''); // Süper lig için ön ek yok
} else {
    die("<h2 style='color:red; text-align:center; padding:50px;'>HATA: MatchEngine.php bulunamadı!</h2>");
}

$ayar = $pdo->query("SELECT * FROM ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta = $ayar['hafta'] ?? 1;
$sezon_yili = $ayar['sezon_yil'] ?? 2025;
$kullanici_takim = $ayar['kullanici_takim_id'] ?? null;
$max_hafta = 34; // Süper Lig: 18 takım, çift devreli = 34 hafta

// --- FİKSTÜR OLUŞTUR (Süper Lig - 18 takım, çift devreli = 34 hafta) ---
$mac_sayisi = 0;
try { $mac_sayisi = $pdo->query("SELECT COUNT(*) FROM maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e) {}
if($mac_sayisi == 0) {
    $takimlar_fix = $pdo->query("SELECT id FROM takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar_fix) > 1) {
        if(count($takimlar_fix) % 2 != 0) $takimlar_fix[] = 0;
        $t_sayisi = count($takimlar_fix); $yari = $t_sayisi - 1; $m_sayisi = $t_sayisi / 2;
        for($h=1; $h<=$yari; $h++) {
            for($i=0; $i<$m_sayisi; $i++) {
                $ev = $takimlar_fix[$i]; $dep = $takimlar_fix[$t_sayisi-1-$i];
                if($ev != 0 && $dep != 0) {
                    if($i % 2 == 0) {
                        $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($ev,$dep,$h,$sezon_yili)");
                        $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($dep,$ev,".($h+$yari).",$sezon_yili)");
                    } else {
                        $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($dep,$ev,$h,$sezon_yili)");
                        $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($ev,$dep,".($h+$yari).",$sezon_yili)");
                    }
                }
            }
            $son = array_pop($takimlar_fix); array_splice($takimlar_fix, 1, 0, [$son]);
        }
        header("Location: superlig.php"); exit;
    }
}

// --- AKSİYON YÖNETİMİ (MAÇ SİMÜLASYONLARI) ---
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // TAKIM SEÇİMİ (Kısıtlama Yok, Saf Menajerlik)
    if($action == 'takim_sec' && isset($_GET['tid'])) {
        $tid = (int)$_GET['tid'];
        $pdo->exec("UPDATE ayar SET kullanici_takim_id = $tid WHERE id=1");
        header("Location: superlig.php"); exit;
    }

    // BU SEZONU SIFIRLA
    if($action == 'bu_sezon_sifirla') {
        $pdo->exec("TRUNCATE TABLE maclar");
        $pdo->exec("UPDATE takimlar SET puan=0, galibiyet=0, beraberlik=0, malubiyet=0, atilan_gol=0, yenilen_gol=0");
        $pdo->exec("UPDATE oyuncular SET sezon_gol=0, sezon_asist=0, form=6, fitness=100, moral=80, ceza_hafta=0, sakatlik_hafta=0, mac_puani_ort=6.00");
        $pdo->exec("UPDATE ayar SET hafta=1");
        header("Location: superlig.php?sifirla=bu_sezon"); exit;
    }

    // TÜM SEZONLARI SIFIRLA
    if($action == 'tum_sezon_sifirla') {
        $pdo->exec("TRUNCATE TABLE maclar");
        $pdo->exec("UPDATE takimlar SET puan=0, galibiyet=0, beraberlik=0, malubiyet=0, atilan_gol=0, yenilen_gol=0");
        $pdo->exec("UPDATE oyuncular SET sezon_gol=0, sezon_asist=0, toplam_mac=0, toplam_gol=0, form=6, fitness=100, moral=80, ceza_hafta=0, sakatlik_hafta=0, mac_puani_ort=6.00");
        try { $pdo->exec("UPDATE ayar SET hafta=1, sezon_yil=2025, gecen_sezon_sampiyon=NULL"); } catch(Throwable $e) { $pdo->exec("UPDATE ayar SET hafta=1, sezon_yil=2025"); }
        try { $pdo->exec("TRUNCATE TABLE haberler"); } catch(Throwable $e) {}
        header("Location: superlig.php?sifirla=tum_sezon"); exit;
    }
    
    // SEZONU SİMÜLE ET
    if($action == 'sezonu_simule') {
        // Fikstür yoksa otomatik oluştur
        $mac_sayisi_kontrol = 0;
        try { $mac_sayisi_kontrol = (int)$pdo->query("SELECT COUNT(*) FROM maclar WHERE sezon_yil = $sezon_yili")->fetchColumn(); } catch(Throwable $e) {}
        if ($mac_sayisi_kontrol == 0) {
            $takimlar_fix = $pdo->query("SELECT id FROM takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
            if(count($takimlar_fix) > 1) {
                if(count($takimlar_fix) % 2 != 0) $takimlar_fix[] = 0;
                $t_sayisi = count($takimlar_fix); $yari = $t_sayisi - 1; $m_sayisi = $t_sayisi / 2;
                for($h=1; $h<=$yari; $h++) {
                    for($i=0; $i<$m_sayisi; $i++) {
                        $ev_t = $takimlar_fix[$i]; $dep_t = $takimlar_fix[$t_sayisi-1-$i];
                        if($ev_t != 0 && $dep_t != 0) {
                            if($i % 2 == 0) {
                                $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($ev_t,$dep_t,$h,$sezon_yili)");
                                $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($dep_t,$ev_t,".($h+$yari).",$sezon_yili)");
                            } else {
                                $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($dep_t,$ev_t,$h,$sezon_yili)");
                                $pdo->exec("INSERT INTO maclar (ev,dep,hafta,sezon_yil) VALUES ($ev_t,$dep_t,".($h+$yari).",$sezon_yili)");
                            }
                        }
                    }
                    $son = array_pop($takimlar_fix); array_splice($takimlar_fix, 1, 0, [$son]);
                }
            }
        }
        for($h = $hafta; $h <= $max_hafta; $h++) {
            $maclar_h = $pdo->query("SELECT m.id, m.ev, m.dep FROM maclar m WHERE m.hafta = $h AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
            foreach($maclar_h as $m) {
                // Auto-select Starting XI before simulation
                $engine->auto_ilk_11((int)$m['ev']);
                $engine->auto_ilk_11((int)$m['dep']);
                $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
                $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
                $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
                $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);
                $pdo->prepare("UPDATE maclar SET ev_skor=?,dep_skor=?,ev_olaylar=?,dep_olaylar=?,ev_kartlar=?,dep_kartlar=? WHERE id=?")
                    ->execute([$ev_skor,$dep_skor,$ev_detay['olaylar'],$dep_detay['olaylar'],$ev_detay['kartlar'],$dep_detay['kartlar'],$m['id']]);
                $pdo->exec("UPDATE takimlar SET atilan_gol=atilan_gol+$ev_skor,yenilen_gol=yenilen_gol+$dep_skor WHERE id={$m['ev']}");
                $pdo->exec("UPDATE takimlar SET atilan_gol=atilan_gol+$dep_skor,yenilen_gol=yenilen_gol+$ev_skor WHERE id={$m['dep']}");
                if($ev_skor > $dep_skor){ $pdo->exec("UPDATE takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
                elseif($ev_skor==$dep_skor){ $pdo->exec("UPDATE takimlar SET puan=puan+1,beraberlik=beraberlik+1 WHERE id IN ({$m['ev']},{$m['dep']})"); }
                else{ $pdo->exec("UPDATE takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
            }
        }
        $pdo->exec("UPDATE ayar SET hafta = $max_hafta");
        header("Location: superlig.php?sezon_bitti=1"); exit;
    }

    // TEK MAÇ SİMÜLE ET
    if($action == 'tek_mac_simule' && isset($_GET['mac_id'])) {
        $mac_id = (int)$_GET['mac_id'];
        $hedef_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta; 
        
        $m = $pdo->query("SELECT m.id, m.ev, m.dep FROM maclar m WHERE m.id = $mac_id AND m.ev_skor IS NULL")->fetch(PDO::FETCH_ASSOC);
        if($m) {
            // Auto-select Starting XI before simulation
            $engine->auto_ilk_11((int)$m['ev']);
            $engine->auto_ilk_11((int)$m['dep']);
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $stmt = $pdo->prepare("UPDATE maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac_kontrol = $pdo->query("SELECT COUNT(*) FROM maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac_kontrol == 0 && $hafta < $max_hafta) { 
            $pdo->exec("UPDATE ayar SET hafta = hafta + 1"); 
        }
        header("Location: superlig.php?hafta=$hedef_hafta"); exit;
    }

    // HAFTAYI OYNA
    if($action == 'hafta') {
        $maclar = $pdo->query("SELECT m.id, m.ev, m.dep FROM maclar m WHERE m.hafta = $hafta AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($maclar as $m) {
            if($kullanici_takim && ($m['ev'] == $kullanici_takim || $m['dep'] == $kullanici_takim)) continue; 
            
            // Auto-select Starting XI before simulation
            $engine->auto_ilk_11((int)$m['ev']);
            $engine->auto_ilk_11((int)$m['dep']);
            $skorlar = $engine->gercekci_skor_hesapla($m['ev'], $m['dep'], $m);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];
            $ev_detay = $engine->mac_olay_uret($m['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($m['dep'], $dep_skor);

            $stmt = $pdo->prepare("UPDATE maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $m['id']]);
            
            $pdo->exec("UPDATE takimlar SET atilan_gol = atilan_gol + $ev_skor, yenilen_gol = yenilen_gol + $dep_skor WHERE id = {$m['ev']}");
            $pdo->exec("UPDATE takimlar SET atilan_gol = atilan_gol + $dep_skor, yenilen_gol = yenilen_gol + $ev_skor WHERE id = {$m['dep']}");
            
            if($ev_skor > $dep_skor) { $pdo->exec("UPDATE takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['ev']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}"); }
            elseif($ev_skor == $dep_skor) { $pdo->exec("UPDATE takimlar SET puan=puan+1, beraberlik=beraberlik+1 WHERE id IN ({$m['ev']}, {$m['dep']})"); }
            else { $pdo->exec("UPDATE takimlar SET puan=puan+3, galibiyet=galibiyet+1 WHERE id={$m['dep']}"); $pdo->exec("UPDATE takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}"); }
        }
        
        $kalan_mac_kontrol = $pdo->query("SELECT COUNT(*) FROM maclar WHERE hafta = $hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan_mac_kontrol == 0 && $hafta < $max_hafta) { 
            $pdo->exec("UPDATE ayar SET hafta = hafta + 1"); 
        }
        header("Location: superlig.php"); exit;
    }
}

// --- VERİ ÇEKİMİ ---
$puan_durumu = $pdo->query("SELECT * FROM takimlar ORDER BY puan DESC, (atilan_gol - yenilen_gol) DESC, atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);

$goster_hafta = isset($_GET['hafta']) ? (int)$_GET['hafta'] : $hafta;
if ($goster_hafta < 1) $goster_hafta = 1;
if ($goster_hafta > $max_hafta) $goster_hafta = $max_hafta;

$haftanin_fiksturu = $pdo->query("SELECT m.*, t1.takim_adi as ev_ad, t1.logo as ev_logo, t2.takim_adi as dep_ad, t2.logo as dep_logo FROM maclar m JOIN takimlar t1 ON m.ev = t1.id JOIN takimlar t2 ON m.dep = t2.id WHERE m.hafta = $goster_hafta")->fetchAll(PDO::FETCH_ASSOC);
$yayinlanacak_maclar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] === NULL; });

$benim_macim_id = null;
if($kullanici_takim) {
    $benim_macim_id = $pdo->query("SELECT id FROM maclar WHERE hafta=$goster_hafta AND ev_skor IS NULL AND (ev=$kullanici_takim OR dep=$kullanici_takim)")->fetchColumn();
}

$kalan_tum_maclar = $pdo->query("SELECT COUNT(*) FROM maclar WHERE ev_skor IS NULL")->fetchColumn();

// Sezon tamamlandı mı?
$sezon_tamam = false;
$sampiyon_takim = null;
try {
    $toplam_mac = $pdo->query("SELECT COUNT(*) FROM maclar WHERE sezon_yil=$sezon_yili")->fetchColumn();
    $kalan_sezon = $pdo->query("SELECT COUNT(*) FROM maclar WHERE sezon_yil=$sezon_yili AND ev_skor IS NULL")->fetchColumn();
    if($toplam_mac > 0 && $kalan_sezon == 0 && $hafta >= $max_hafta) {
        $sezon_tamam = true;
        $sampiyon_takim = $puan_durumu[0] ?? null;
    }
} catch(Throwable $e) {}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Süper Lig | Ultimate Manager</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --sl-primary: #1e1b4b; 
            --sl-secondary: #e11d48; 
            --sl-accent: #facc15; 
            --bg-body: #0f172a;
            --bg-panel: #1e293b;
            --border-color: rgba(255,255,255,0.1);
        }

        /* --- YÜKSEK KONTRAST / DARK MODE OVERRIDE --- */
        body, p, h1, h2, h3, h4, h5, h6, span, label, li { color: #f8fafc !important; }
        td, th { color: #f8fafc !important; }
        /* Puan tablosu kontrast düzeltmesi */
        body, table, td, th, .puan-tablosu, .sira, .puan { color: #ffffff !important; background-color: #1a1a1a; }

        body { background-color: var(--bg-body) !important; color: #f8fafc !important; font-family: 'Inter', sans-serif; }
        .font-oswald { font-family: 'Oswald', sans-serif; text-transform: uppercase; }

        .pro-navbar { background: rgba(15,23,42,0.95); border-bottom: 2px solid var(--sl-secondary); padding: 0 2rem; height: 75px; display: flex; justify-content: space-between; align-items: center; position: sticky; top:0; z-index:1000;}
        .nav-brand { font-size: 1.4rem; font-weight: 900; color: #fff; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .nav-brand img.league-logo { height: 38px; width: auto; object-fit: contain; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.5)); transition: transform 0.3s ease; }
        .nav-brand:hover img.league-logo { transform: scale(1.1); }
        .nav-brand i { color: var(--sl-secondary); }
        .nav-link-item { color: #94a3b8; font-weight: 600; padding: 8px 16px; text-decoration: none; transition: 0.2s; }
        .nav-link-item:hover { color: #fff; }

        .btn-action-primary { background: var(--sl-secondary); color: #fff; font-weight: 800; padding: 8px 20px; border-radius: 4px; text-decoration: none; border: none;}
        .btn-action-primary:hover { background: #be123c; color: #fff; }
        .btn-action-outline { background: transparent; border: 1px solid var(--sl-secondary); color: var(--sl-secondary); font-weight: 700; padding: 8px 20px; border-radius: 4px; text-decoration: none;}
        .btn-action-outline:hover { background: var(--sl-secondary); color: #fff; }

        .panel-card { background: var(--bg-panel); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.5); }
        .panel-header { padding: 1.2rem; border-bottom: 1px solid var(--border-color); background: rgba(0,0,0,0.2); font-weight: 700; display: flex; align-items: center; justify-content: space-between;}

        .data-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.9rem; }
        .data-table th { padding: 1rem; color: #94a3b8 !important; font-weight: 700; text-transform: uppercase; font-size: 0.75rem; border-bottom: 1px solid var(--border-color); text-align: center;}
        .data-table th:nth-child(2) { text-align: left; }
        .data-table td { padding: 0.8rem 1rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; font-weight: 500;}
        .data-table tbody tr:hover td { background: rgba(255,255,255,0.05); }
        .cell-club { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff !important; font-weight: 700; text-align: left; }
        .cell-club img { width: 28px; height: 28px; object-fit: contain; }
        
        .zone-cl td:first-child { border-left: 4px solid #3b82f6; }
        .zone-el td:first-child { border-left: 4px solid #f59e0b; }
        .zone-rel td:first-child { border-left: 4px solid #ef4444; }
        .my-team-row td { background: rgba(255,255,255,0.1); font-weight: 800; color: var(--sl-accent) !important;}

        .fixture-wrapper { display: flex; flex-direction: column; gap: 12px; padding: 1rem; overflow-y: auto; }
        .scorebug-container { background: rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; transition: 0.3s;}
        .scorebug-container:hover { border-color: var(--sl-secondary); box-shadow: 0 5px 15px rgba(225,29,72,0.2);}
        
        .score-grid { display: flex; width: 100%; min-height: 60px; align-items: stretch; }
        .team-block { display: flex; align-items: center; gap: 10px; padding: 0 15px; flex: 1; min-width: 0; }
        .team-block.home { justify-content: flex-end; }
        .team-name { font-weight: 600; font-size: 0.95rem; color: #f8fafc !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .team-logo { width: 32px; height: 32px; object-fit: contain; flex-shrink: 0; }
        .center-block { width: 80px; flex-shrink: 0; background: rgba(0,0,0,0.5); display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .match-score { font-family: 'Oswald'; font-size: 1.5rem; font-weight: 700; color: #fff !important; }
        
        .match-actions { display: flex; background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.05); }
        .action-btn { flex: 1; padding: 8px; text-align: center; text-decoration: none; color: var(--sl-accent) !important; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .action-btn:hover { background: var(--sl-accent); color: #000 !important; }

        .team-card { background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center; transition: 0.3s;}
        .team-card:hover { transform: translateY(-5px); border-color: var(--sl-secondary);}
        
        @keyframes blink { 50% { opacity: 0.5; box-shadow: 0 0 15px #ef4444; } }
        .blink-effect { animation: blink 1.5s infinite; }

        /* Reset button styles */
        .btn-reset-sezon { background: rgba(120,60,0,0.35); border: 1px solid rgba(251,191,36,0.5); color: #fde047 !important; font-weight: 700; padding: 7px 14px; border-radius: 5px; font-size: 0.78rem; text-decoration: none; transition: 0.2s; }
        .btn-reset-sezon:hover { background: rgba(202,138,4,0.5); border-color: #ca8a04; }
        .btn-reset-tum { background: rgba(127,29,29,0.35); border: 1px solid rgba(220,38,38,0.5); color: #fca5a5 !important; font-weight: 700; padding: 7px 14px; border-radius: 5px; font-size: 0.78rem; text-decoration: none; transition: 0.2s; }
        .btn-reset-tum:hover { background: rgba(185,28,28,0.5); border-color: #b91c1c; }
        /* Reset success/info alert */
        .reset-alert { padding: 10px 16px; border-radius: 8px; margin: 0 0 12px; font-size: 0.85rem; font-weight: 600; }
        .reset-alert.success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); color: #6ee7b7 !important; }
        .reset-alert.warning { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.4); color: #fde047 !important; }

    </style>
</head>
<body>

    <nav class="pro-navbar">
        <div class="d-flex align-items-center gap-4">
            <a href="superlig.php" class="nav-brand">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/5/55/S%C3%BCper_Lig_logo.svg/200px-S%C3%BCper_Lig_logo.svg.png"
                     alt="Süper Lig" class="league-logo"
                     onerror="this.style.display='none'">
                <span class="font-oswald">SÜPER LİG</span>
            </a>
            <div class="nav-menu d-none d-lg-flex gap-2">
                <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez Hub</a>
                <a href="kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Taktik Odası</a>
                <a href="transfer.php" class="nav-link-item"><i class="fa-solid fa-money-bill-transfer"></i> Pazar</a>
                <a href="tesisler.php" class="nav-link-item"><i class="fa-solid fa-building"></i> Tesisler</a>
                <a href="basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Medya & Psikoloji</a>
                <a href="../la_liga/la_liga.php" class="nav-link-item" style="color:#f5c518;"><i class="fa-solid fa-sun"></i> La Liga</a>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <?php if($kullanici_takim): ?>
                <?php if($benim_macim_id): ?>
                    <a href="?action=tek_mac_simule&mac_id=<?= $benim_macim_id ?>&hafta=<?= $goster_hafta ?>" class="btn-action-primary"><i class="fa-solid fa-play"></i> Maçına Çık</a>
                <?php endif; ?>
                <a href="?action=hafta" class="btn-action-outline"><i class="fa-solid fa-forward-fast"></i> Simüle Et</a>
                <?php if(!$sezon_tamam): ?>
                <a href="?action=sezonu_simule" class="btn-action-outline" style="background:#7f1d1d;border-color:#dc2626;color:#fca5a5;" onclick="return confirm('Süper Lig sezonu simüle edilecek. Devam?')"><i class="fa-solid fa-forward-step"></i> Sezonu Simüle Et</a>
                <?php endif; ?>
                <!-- Season Reset Buttons -->
                <a href="?action=bu_sezon_sifirla" class="btn-reset-sezon" onclick="return confirm('Bu sezonu sıfırla?\n\nMevcut fikstür, puan tablosu ve maç sonuçları silinecek. Geçmiş sezon şampiyonları korunacak.')"><i class="fa-solid fa-broom me-1"></i>Bu Sezonu Sıfırla</a>
                <a href="?action=tum_sezon_sifirla" class="btn-reset-tum" onclick="return confirm('⚠️ TÜM SEZONLARI SIFIRLA!\n\nSÜPER LİG TÜM GEÇMİŞİ ve ŞAMPİYON KAYITLARI silinecek.\n\nBu işlem geri alınamaz! Emin misiniz?')"><i class="fa-solid fa-fire me-1"></i>Tüm Sezonları Sıfırla</a>
            <?php endif; ?>
        </div>
    </nav>

    <?php if(isset($_GET['sifirla'])): ?>
    <div class="container-fluid px-4 pt-3">
        <?php if($_GET['sifirla'] === 'bu_sezon'): ?>
        <div class="reset-alert success"><i class="fa-solid fa-check-circle me-2"></i>✅ Bu sezon başarıyla sıfırlandı. Fikstür, puan tablosu ve maç sonuçları temizlendi. Geçmiş sezon şampiyonları korundu.</div>
        <?php elseif($_GET['sifirla'] === 'tum_sezon'): ?>
        <div class="reset-alert warning"><i class="fa-solid fa-rotate-left me-2"></i>🔄 Tüm sezonlar sıfırlandı. Süper Lig geçmişi ve tüm veriler fabrika ayarlarına döndürüldü.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($sezon_tamam && $sampiyon_takim): ?>
    <div class="container-fluid py-3 px-4">
        <div style="background:linear-gradient(135deg,#1e1b4b,#7f1d1d);border:2px solid #d4af37;border-radius:12px;padding:20px 24px;margin:0 0 16px;text-align:center;">
            <div style="font-family:monospace;color:#d4af37;font-size:0.82rem;">=============================================</div>
            <div style="font-family:'Oswald',sans-serif;font-size:1.3rem;font-weight:900;color:#fff;margin:8px 0;">
                🏆 SÜPER LİG <?= $sezon_yili ?>/<?= $sezon_yili+1 ?> SEZONU ŞAMPİYONU 🏆</div>
            <div style="font-family:'Oswald',sans-serif;font-size:1.6rem;font-weight:900;color:#d4af37;margin:6px 0;">★ <?= htmlspecialchars($sampiyon_takim['takim_adi']) ?> ★</div>
            <div style="font-family:monospace;color:#d4af37;font-size:0.82rem;">=============================================</div>
            <div style="color:#d1fae5;font-size:0.9rem;margin-top:8px;">Tebrikler! <?= htmlspecialchars($sampiyon_takim['takim_adi']) ?>, ligi şampiyon olarak tamamladı.</div>
            <div style="margin-top:12px;font-size:0.8rem;max-width:400px;margin-left:auto;margin-right:auto;text-align:left;">
                <div style="color:#d4af37;font-weight:700;margin-bottom:6px;text-transform:uppercase;font-size:0.72rem;">Final Tablosu — İlk 5</div>
                <?php foreach(array_slice($puan_durumu,0,5) as $idx=>$st): $ag=$st['atilan_gol']-$st['yenilen_gol']; ?>
                <div style="display:flex;gap:8px;padding:3px 0;color:#fff;border-bottom:1px solid rgba(255,255,255,0.1);">
                    <span style="width:20px;color:#d4af37;font-weight:700;"><?=$idx+1?></span>
                    <span style="flex:1;"><?=htmlspecialchars($st['takim_adi'])?></span>
                    <span style="color:#94a3b8;font-size:0.7rem;margin-right:4px;">AV:<?=($ag>=0?'+':'')?><?=$ag?></span>
                    <span style="color:#d4af37;font-weight:900;"><?=$st['puan']?>P</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="background:rgba(0,0,0,0.4);border:1px solid rgba(212,175,55,0.3);border-radius:6px;padding:10px 14px;margin-top:12px;font-family:monospace;font-size:0.78rem;color:#a3e635;text-align:left;max-width:400px;margin-left:auto;margin-right:auto;">
                <div style="color:#94a3b8;margin-bottom:4px;">// index.php için son şampiyon güncellemesi</div>
                $son_sampiyon['superlig'] = "<?= htmlspecialchars($sampiyon_takim['takim_adi']) ?>";
            </div>
            <div class="mt-3">
                <a href="sezon_gecisi.php" style="display:inline-block;background:#d4af37;color:#000;font-family:'Oswald',sans-serif;font-weight:900;padding:10px 28px;border-radius:8px;text-decoration:none;text-transform:uppercase;">
                    <i class="fa-solid fa-forward-fast me-2"></i>Yeni Sezona Geç
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!$kullanici_takim): ?>
        <div class="container py-5 text-center" style="max-width: 1200px;">
            <h1 class="font-oswald mb-3" style="color: #fff;">KULÜBÜNÜ SEÇ</h1>
            <p class="text-muted mb-5 fs-5">Süper Lig'de yönetmek istediğin kulübü özgürce seçebilirsin.</p>
            
            <div class="row g-4 justify-content-center">
                <?php 
                $secilebilir = $pdo->query("SELECT * FROM takimlar ORDER BY takim_adi ASC")->fetchAll(PDO::FETCH_ASSOC);
                foreach($secilebilir as $t): 
                ?>
                <div class="col-md-3 col-sm-4 col-6">
                    <div class="team-card" style="cursor:pointer;" onclick="window.location='?action=takim_sec&tid=<?= $t['id'] ?>';">
                        <img src="<?= $t['logo'] ?>" style="width:60px; height:60px; object-fit:contain; margin-bottom:15px; filter:drop-shadow(0 2px 5px rgba(0,0,0,0.5));">
                        <h6 class="font-oswald text-white mb-1"><?= $t['takim_adi'] ?></h6>
                        <span class="badge bg-success mt-2">Sözleşme İmzala</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="container-fluid py-4" style="max-width: 1600px;">
            <div class="row g-4">
                
                <div class="col-xl-8">
                    <div class="panel-card" style="height: 100%;">
                        <div class="panel-header text-white">
                            <span class="font-oswald"><i class="fa-solid fa-list-ol text-danger me-2"></i> LİG TABLOSU</span>
                            <span class="badge bg-dark border text-muted">2025 - 2026 Sezonu</span>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="data-table">
                                <thead>
                                    <tr><th>Sıra</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AV</th><th>P</th></tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    foreach($puan_durumu as $index => $t): 
                                        $sira = $index + 1; 
                                        $row_class = "";
                                        if($sira <= 2) $row_class = "zone-cl"; 
                                        elseif($sira <= 4) $row_class = "zone-el"; 
                                        elseif($sira >= 16) $row_class = "zone-rel"; 
                                        
                                        if($t['id'] == $kullanici_takim) $row_class .= " my-team-row";
                                        
                                        $av = $t['atilan_gol'] - $t['yenilen_gol'];
                                    ?>
                                    <tr class="<?= $row_class ?>">
                                        <td class="text-muted fw-bold"><?= $sira ?></td>
                                        <td>
                                            <a href="takim.php?id=<?= $t['id'] ?>" class="cell-club">
                                                <img src="<?= $t['logo'] ?>"> 
                                                <span><?= $t['takim_adi'] ?></span>
                                            </a>
                                        </td>
                                        <td><?= $t['galibiyet'] + $t['beraberlik'] + $t['malubiyet'] ?></td>
                                        <td><?= $t['galibiyet'] ?></td>
                                        <td><?= $t['beraberlik'] ?></td>
                                        <td><?= $t['malubiyet'] ?></td>
                                        <td style="color: <?= $av>0?'#10b981':($av<0?'#ef4444':'') ?>;"><?= $av>0?'+'.$av:$av ?></td>
                                        <td class="font-oswald fs-5 text-white"><?= $t['puan'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="panel-card" style="height: 100%; max-height:850px;">
                        <div class="panel-header d-flex justify-content-between align-items-center">
                            <a href="?hafta=<?= max(1, $goster_hafta-1) ?>" class="text-muted"><i class="fa-solid fa-chevron-left"></i></a>
                            
                            <div class="d-flex align-items-center">
                                <span class="font-oswald text-white fs-5">HAFTA <?= $goster_hafta ?></span>
                                
                                <?php if($kalan_tum_maclar == 0 && $hafta >= $max_hafta): ?>
                                    <a href="sezon_gecisi.php" class="badge bg-danger text-white text-decoration-none p-2 ms-3 blink-effect" style="border: 1px solid #fff;"><i class="fa-solid fa-trophy"></i> SEZONU BİTİR</a>
                                <?php endif; ?>
                            </div>
                            
                            <a href="?hafta=<?= min($max_hafta, $goster_hafta+1) ?>" class="text-muted"><i class="fa-solid fa-chevron-right"></i></a>
                        </div>
                        
                        <div class="fixture-wrapper">
                            <?php 
                            // Pre-match team analysis for upcoming matches (first upcoming match only)
                            $prv_shown = false;
                            foreach($yayinlanacak_maclar as $mac): 
                                if (!$prv_shown): 
                                    $prv_shown = true;
                                    echo $engine->mac_on_izleme_html((int)$mac['ev'], (int)$mac['dep']);
                                    break;
                                endif;
                            endforeach;
                            foreach($yayinlanacak_maclar as $mac): ?>
                                <div class="scorebug-container">
                                    <div class="score-grid">
                                        <div class="team-block home"><span class="team-name"><?= $mac['ev_ad'] ?></span> <img src="<?= $mac['ev_logo'] ?>" class="team-logo"></div>
                                        <div class="center-block"><span class="match-score text-muted fs-5">v</span></div>
                                        <div class="team-block away"><img src="<?= $mac['dep_logo'] ?>" class="team-logo"> <span class="team-name"><?= $mac['dep_ad'] ?></span></div>
                                    </div>
                                    <div class="match-actions"><a href="../canli_mac.php?id=<?= $mac['id'] ?>&lig=tr&hafta=<?= $goster_hafta ?>" class="action-btn text-success"><i class="fa-solid fa-satellite-dish"></i> CANLI İZLE</a>
                                </div>
                            <?php endforeach; ?>

                            <?php 
                            $oynananlar = array_filter($haftanin_fiksturu, function($m) { return $m['ev_skor'] !== NULL; });
                            foreach($oynananlar as $mac): 
                            ?>
                                <div class="scorebug-container" style="border-color:rgba(255,255,255,0.1);">
                                    <div class="score-grid">
                                        <div class="team-block home"><span class="team-name"><?= $mac['ev_ad'] ?></span> <img src="<?= $mac['ev_logo'] ?>" class="team-logo"></div>
                                        <div class="center-block"><span class="match-score"><?= $mac['ev_skor'] ?>-<?= $mac['dep_skor'] ?></span><span class="match-status text-muted" style="font-size:0.7rem; font-weight:bold;">MS</span></div>
                                        <div class="team-block away"><img src="<?= $mac['dep_logo'] ?>" class="team-logo"> <span class="team-name"><?= $mac['dep_ad'] ?></span></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if(empty($haftanin_fiksturu)): ?>
                                <div class="text-center p-4 text-muted font-oswald fs-5">Bu haftaya ait fikstür bulunamadı.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>