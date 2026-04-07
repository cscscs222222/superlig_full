<?php
// ==============================================================================
// LIGA NOS - ANA MERKEZ VE FİKSTÜR (GREEN & RED PORTUGUESE THEME)
// ==============================================================================
include '../db.php';

if(file_exists('../MatchEngine.php')) {
    include '../MatchEngine.php';
    $engine = new MatchEngine($pdo, 'pt_');
} else {
    die("<h2 style='color:red;text-align:center;padding:50px;'>HATA: MatchEngine.php bulunamadı!</h2>");
}

function sutunEklePt($pdo, $tablo, $sutun, $tip) {
    try {
        if($pdo->query("SHOW COLUMNS FROM `$tablo` LIKE '$sutun'")->rowCount() == 0)
            $pdo->exec("ALTER TABLE `$tablo` ADD `$sutun` $tip");
    } catch(Throwable $e) {}
}

// TABLOLARI KUR
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pt_ayar (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT DEFAULT 1, sezon_yil INT DEFAULT 2025, kullanici_takim_id INT DEFAULT NULL, gecen_sezon_sampiyon VARCHAR(100) DEFAULT 'Sporting CP')");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pt_takimlar (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_adi VARCHAR(100), logo VARCHAR(255), hucum INT DEFAULT 75, savunma INT DEFAULT 75, butce BIGINT DEFAULT 80000000, lig VARCHAR(50) DEFAULT 'Liga NOS',
        puan INT DEFAULT 0, galibiyet INT DEFAULT 0, beraberlik INT DEFAULT 0, malubiyet INT DEFAULT 0, atilan_gol INT DEFAULT 0, yenilen_gol INT DEFAULT 0,
        kimya INT DEFAULT 50, oyun_tarzi VARCHAR(50) DEFAULT 'Dengeli', dizilis VARCHAR(20) DEFAULT '4-3-3', pres VARCHAR(50) DEFAULT 'Orta', tempo VARCHAR(50) DEFAULT 'Normal',
        stadyum_seviye INT DEFAULT 1, altyapi_seviye INT DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pt_oyuncular (
        id INT AUTO_INCREMENT PRIMARY KEY, takim_id INT, isim VARCHAR(100), mevki VARCHAR(10), ovr INT DEFAULT 75, yas INT DEFAULT 24, fiyat BIGINT DEFAULT 8000000, lig VARCHAR(50) DEFAULT 'Liga NOS',
        ilk_11 TINYINT(1) DEFAULT 0, yedek TINYINT(1) DEFAULT 0, form INT DEFAULT 6, fitness INT DEFAULT 100, moral INT DEFAULT 80, ceza_hafta INT DEFAULT 0, sakatlik_hafta INT DEFAULT 0, saha_pozisyon VARCHAR(50) DEFAULT '50,50',
        toplam_mac INT DEFAULT 0, toplam_gol INT DEFAULT 0, sezon_gol INT DEFAULT 0, sezon_asist INT DEFAULT 0, mac_puani_ort DECIMAL(4,2) DEFAULT 6.00
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pt_maclar (
        id INT AUTO_INCREMENT PRIMARY KEY, ev INT, dep INT, hafta INT, sezon_yil INT DEFAULT 2025,
        ev_skor INT DEFAULT NULL, dep_skor INT DEFAULT NULL, ev_olaylar TEXT, dep_olaylar TEXT, ev_kartlar TEXT, dep_kartlar TEXT, ev_sakatlar TEXT, dep_sakatlar TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pt_haberler (id INT AUTO_INCREMENT PRIMARY KEY, hafta INT, metin TEXT, tip VARCHAR(50))");
    if($pdo->query("SELECT COUNT(*) FROM pt_ayar")->fetchColumn() == 0)
        $pdo->exec("INSERT INTO pt_ayar (hafta, sezon_yil, gecen_sezon_sampiyon) VALUES (1, 2025, 'Sporting CP')");
} catch(Throwable $e) {}

sutunEklePt($pdo, 'pt_ayar', 'gecen_sezon_sampiyon', "VARCHAR(100) DEFAULT 'Sporting CP'");
sutunEklePt($pdo, 'pt_takimlar', 'lig', "VARCHAR(50) DEFAULT 'Liga NOS'");
sutunEklePt($pdo, 'pt_oyuncular', 'lig', "VARCHAR(50) DEFAULT 'Liga NOS'");

// LIGA NOS - 18 TAKIM
$pt_takim_sayisi = 0;
try { $pt_takim_sayisi = $pdo->query("SELECT COUNT(*) FROM pt_takimlar")->fetchColumn(); } catch(Throwable $e) {}
if($pt_takim_sayisi < 18) {
    $portekiz_devleri = [
        ['Sporting CP',         'https://tmssl.akamaized.net/images/wappen/head/1100.png',  88, 86],
        ['SL Benfica',          'https://tmssl.akamaized.net/images/wappen/head/294.png',   89, 87],
        ['FC Porto',            'https://tmssl.akamaized.net/images/wappen/head/720.png',   88, 87],
        ['SC Braga',            'https://tmssl.akamaized.net/images/wappen/head/2693.png',  82, 81],
        ['Vitória SC',          'https://tmssl.akamaized.net/images/wappen/head/2301.png',  77, 77],
        ['Boavista FC',         'https://tmssl.akamaized.net/images/wappen/head/1063.png',  76, 76],
        ['Gil Vicente FC',      'https://tmssl.akamaized.net/images/wappen/head/4910.png',  74, 75],
        ['Santa Clara',         'https://tmssl.akamaized.net/images/wappen/head/4948.png',  74, 74],
        ['Moreirense FC',       'https://tmssl.akamaized.net/images/wappen/head/3731.png',  73, 74],
        ['Famalicão',           'https://tmssl.akamaized.net/images/wappen/head/1660.png',  73, 73],
        ['Estoril Praia',       'https://tmssl.akamaized.net/images/wappen/head/3469.png',  72, 73],
        ['CD Arouca',           'https://tmssl.akamaized.net/images/wappen/head/7474.png',  72, 72],
        ['Rio Ave FC',          'https://tmssl.akamaized.net/images/wappen/head/1084.png',  72, 72],
        ['Portimonense',        'https://tmssl.akamaized.net/images/wappen/head/3588.png',  71, 72],
        ['Casa Pia AC',         'https://tmssl.akamaized.net/images/wappen/head/8638.png',  71, 71],
        ['Vizela',              'https://tmssl.akamaized.net/images/wappen/head/9413.png',  70, 71],
        ['GD Chaves',           'https://tmssl.akamaized.net/images/wappen/head/1103.png',  69, 70],
        ['FC Paços de Ferreira','https://tmssl.akamaized.net/images/wappen/head/8638.png',  69, 70],
    ];
    foreach($portekiz_devleri as $d) {
        $ad=$d[0]; $logo=$d[1]; $huc=$d[2]; $sav=$d[3];
        $var_mi=$pdo->query("SELECT COUNT(*) FROM pt_takimlar WHERE takim_adi=".$pdo->quote($ad))->fetchColumn();
        if($var_mi==0) {
            $butce=rand(15000000,150000000);
            $stmt=$pdo->prepare("INSERT INTO pt_takimlar (takim_adi,logo,hucum,savunma,butce,lig) VALUES (?,?,?,?,?,'Liga NOS')");
            $stmt->execute([$ad,$logo,$huc,$sav,$butce]);
            $yeni_id=$pdo->lastInsertId();
            for($i=0;$i<22;$i++) {
                $mevkiler=['K','K','D','D','D','D','D','D','OS','OS','OS','OS','OS','OS','F','F','F','F','D','OS','F','K'];
                $mvk=$mevkiler[$i]; $ovr=rand($sav-6,$huc+4);
                $stmt2=$pdo->prepare("INSERT INTO pt_oyuncular (takim_id,isim,mevki,ovr,yas,fiyat,lig,ilk_11,yedek) VALUES (?,?,?,?,?,?,'Liga NOS',?,?)");
                $stmt2->execute([$yeni_id,$ad.' Oyuncusu '.($i+1),$mvk,$ovr,rand(18,34),($ovr*$ovr)*3000,($i<11)?1:0,($i>=11&&$i<18)?1:0]);
            }
        }
    }
}

// Varolan takımlar için oyuncu eksikse tamamla
try {
    $tl=$pdo->query("SELECT id,takim_adi,hucum,savunma FROM pt_takimlar")->fetchAll(PDO::FETCH_ASSOC);
    foreach($tl as $t) {
        if($pdo->query("SELECT COUNT(*) FROM pt_oyuncular WHERE takim_id={$t['id']}")->fetchColumn()==0) {
            for($i=0;$i<22;$i++) {
                $mevkiler=['K','K','D','D','D','D','D','D','OS','OS','OS','OS','OS','OS','F','F','F','F','D','OS','F','K'];
                $mvk=$mevkiler[$i]; $huc=$t['hucum']??75; $sav=$t['savunma']??75;
                $ovr=rand(max(60,$sav-6),min(99,$huc+4));
                $stmt=$pdo->prepare("INSERT INTO pt_oyuncular (takim_id,isim,mevki,ovr,yas,fiyat,lig,ilk_11,yedek) VALUES(?,?,?,?,?,?,'Liga NOS',?,?)");
                $stmt->execute([$t['id'],$t['takim_adi'].' Oyuncusu '.($i+1),$mvk,$ovr,rand(18,34),($ovr*$ovr)*3000,($i<11)?1:0,($i>=11&&$i<18)?1:0]);
            }
        }
    }
} catch(Throwable $e) {}

function garanti_olay_uret_pt($pdo, $takim_id, $skor) {
    $oyuncular=$pdo->query("SELECT isim FROM pt_oyuncular WHERE takim_id=$takim_id AND ilk_11=1")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular=$pdo->query("SELECT isim FROM pt_oyuncular WHERE takim_id=$takim_id")->fetchAll(PDO::FETCH_COLUMN);
    if(empty($oyuncular)) $oyuncular=['Bilinmeyen Oyuncu'];
    $olaylar=[];
    for($i=0;$i<$skor;$i++) {
        $golcu=$oyuncular[array_rand($oyuncular)];
        $asistci=(rand(1,100)>50)?$oyuncular[array_rand($oyuncular)]:'-';
        if($golcu==$asistci) $asistci='-';
        $olaylar[]=['tip'=>'gol','oyuncu'=>$golcu,'asist'=>$asistci,'dakika'=>rand(1,90)];
    }
    usort($olaylar,fn($a,$b)=>$a['dakika']<=>$b['dakika']);
    $kartlar=[];
    for($i=0;$i<rand(0,3);$i++) $kartlar[]=['tip'=>(rand(1,100)>85?'Kırmızı':'Sarı'),'oyuncu'=>$oyuncular[array_rand($oyuncular)],'dakika'=>rand(1,90)];
    usort($kartlar,fn($a,$b)=>$a['dakika']<=>$b['dakika']);
    return ['olaylar'=>json_encode($olaylar,JSON_UNESCAPED_UNICODE),'kartlar'=>json_encode($kartlar,JSON_UNESCAPED_UNICODE)];
}

$ayar=$pdo->query("SELECT * FROM pt_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hafta=$ayar['hafta']??1;
$sezon_yili=$ayar['sezon_yil']??2025;
$kullanici_takim=$ayar['kullanici_takim_id']??null;
$gecen_sezon_sampiyon=$ayar['gecen_sezon_sampiyon']??'Sporting CP';
$max_hafta=34; // Liga NOS: 18 takım, 34 hafta

// FİKSTÜR OLUŞTUR (18 takım, 34 hafta)
$mac_sayisi=0;
try { $mac_sayisi=$pdo->query("SELECT COUNT(*) FROM pt_maclar WHERE sezon_yil=$sezon_yili")->fetchColumn(); } catch(Throwable $e) {}
if($mac_sayisi==0) {
    $takimlar=$pdo->query("SELECT id FROM pt_takimlar ORDER BY RAND()")->fetchAll(PDO::FETCH_COLUMN);
    if(count($takimlar)>1) {
        if(count($takimlar)%2!=0) $takimlar[]=0;
        $t_sayisi=count($takimlar); $yari=$t_sayisi-1; $m_sayisi=$t_sayisi/2;
        for($h=1;$h<=$yari;$h++) {
            for($i=0;$i<$m_sayisi;$i++) {
                $ev=$takimlar[$i]; $dep=$takimlar[$t_sayisi-1-$i];
                if($ev!=0&&$dep!=0) {
                    if($i%2==0) {
                        $pdo->exec("INSERT INTO pt_maclar (ev,dep,hafta,sezon_yil) VALUES ($ev,$dep,$h,$sezon_yili)");
                        $pdo->exec("INSERT INTO pt_maclar (ev,dep,hafta,sezon_yil) VALUES ($dep,$ev,".($h+$yari).",$sezon_yili)");
                    } else {
                        $pdo->exec("INSERT INTO pt_maclar (ev,dep,hafta,sezon_yil) VALUES ($dep,$ev,$h,$sezon_yili)");
                        $pdo->exec("INSERT INTO pt_maclar (ev,dep,hafta,sezon_yil) VALUES ($ev,$dep,".($h+$yari).",$sezon_yili)");
                    }
                }
            }
            $son=array_pop($takimlar); array_splice($takimlar,1,0,[$son]);
        }
        $pdo->exec("INSERT INTO pt_haberler (hafta,metin,tip) VALUES (1,'Liga NOS başlıyor! Portekiz''in üç büyükleri sahada.','sistem')");
        header("Location: liga_nos.php"); exit;
    }
}

// AKSİYON YÖNETİMİ
if(isset($_GET['action'])) {
    $action=$_GET['action'];

    if($action=='takim_sec'&&isset($_GET['tid'])) {
        $tid=(int)$_GET['tid'];
        $pdo->exec("UPDATE pt_ayar SET kullanici_takim_id=$tid WHERE id=1");
        header("Location: liga_nos.php"); exit;
    }

    if($action=='tek_mac_simule'&&isset($_GET['mac_id'])) {
        $mac_id=(int)$_GET['mac_id'];
        $hedef_hafta=isset($_GET['hafta'])?(int)$_GET['hafta']:$hafta;
        $m=$pdo->query("SELECT m.*,t1.takim_adi as ev_ad,t2.takim_adi as dep_ad,t1.hucum as ev_hucum,t1.savunma as ev_savunma,t2.hucum as dep_hucum,t2.savunma as dep_savunma FROM pt_maclar m JOIN pt_takimlar t1 ON m.ev=t1.id JOIN pt_takimlar t2 ON m.dep=t2.id WHERE m.id=$mac_id AND m.ev_skor IS NULL")->fetch(PDO::FETCH_ASSOC);
        if($m) {
            $s=$engine->gercekci_skor_hesapla($m['ev'],$m['dep'],$m);
            $es=$s['ev']; $ds=$s['dep'];
            $ed=$engine->mac_olay_uret($m['ev'],$es); $dd=$engine->mac_olay_uret($m['dep'],$ds);
            $ek=json_decode($ed['olaylar'],true); if(empty($ek)&&$es>0){$g=garanti_olay_uret_pt($pdo,$m['ev'],$es);$ed=$g;}
            $dk=json_decode($dd['olaylar'],true); if(empty($dk)&&$ds>0){$g=garanti_olay_uret_pt($pdo,$m['dep'],$ds);$dd=$g;}
            $pdo->prepare("UPDATE pt_maclar SET ev_skor=?,dep_skor=?,ev_olaylar=?,dep_olaylar=?,ev_kartlar=?,dep_kartlar=? WHERE id=?")->execute([$es,$ds,$ed['olaylar'],$dd['olaylar'],$ed['kartlar'],$dd['kartlar'],$m['id']]);
            $pdo->exec("UPDATE pt_takimlar SET atilan_gol=atilan_gol+$es,yenilen_gol=yenilen_gol+$ds WHERE id={$m['ev']}");
            $pdo->exec("UPDATE pt_takimlar SET atilan_gol=atilan_gol+$ds,yenilen_gol=yenilen_gol+$es WHERE id={$m['dep']}");
            if($es>$ds){$pdo->exec("UPDATE pt_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['ev']}");$pdo->exec("UPDATE pt_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}");}
            elseif($es==$ds){$pdo->exec("UPDATE pt_takimlar SET puan=puan+1,beraberlik=beraberlik+1 WHERE id IN ({$m['ev']},{$m['dep']})");}
            else{$pdo->exec("UPDATE pt_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['dep']}");$pdo->exec("UPDATE pt_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}");}
        }
        $kalan=$pdo->query("SELECT COUNT(*) FROM pt_maclar WHERE hafta=$hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan==0) $pdo->exec("UPDATE pt_ayar SET hafta=LEAST($max_hafta,hafta+1)");
        header("Location: liga_nos.php?hafta=$hedef_hafta"); exit;
    }

    if($action=='hafta') {
        $maclar=$pdo->query("SELECT m.*,t1.takim_adi as ev_ad,t2.takim_adi as dep_ad,t1.hucum as ev_hucum,t1.savunma as ev_savunma,t2.hucum as dep_hucum,t2.savunma as dep_savunma FROM pt_maclar m JOIN pt_takimlar t1 ON m.ev=t1.id JOIN pt_takimlar t2 ON m.dep=t2.id WHERE m.hafta=$hafta AND m.ev_skor IS NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach($maclar as $m) {
            if($kullanici_takim&&($m['ev']==$kullanici_takim||$m['dep']==$kullanici_takim)) continue;
            $s=$engine->gercekci_skor_hesapla($m['ev'],$m['dep'],$m);
            $es=$s['ev']; $ds=$s['dep'];
            $ed=$engine->mac_olay_uret($m['ev'],$es); $dd=$engine->mac_olay_uret($m['dep'],$ds);
            $ek=json_decode($ed['olaylar'],true); if(empty($ek)&&$es>0){$g=garanti_olay_uret_pt($pdo,$m['ev'],$es);$ed=$g;}
            $dk=json_decode($dd['olaylar'],true); if(empty($dk)&&$ds>0){$g=garanti_olay_uret_pt($pdo,$m['dep'],$ds);$dd=$g;}
            $pdo->prepare("UPDATE pt_maclar SET ev_skor=?,dep_skor=?,ev_olaylar=?,dep_olaylar=?,ev_kartlar=?,dep_kartlar=? WHERE id=?")->execute([$es,$ds,$ed['olaylar'],$dd['olaylar'],$ed['kartlar'],$dd['kartlar'],$m['id']]);
            $pdo->exec("UPDATE pt_takimlar SET atilan_gol=atilan_gol+$es,yenilen_gol=yenilen_gol+$ds WHERE id={$m['ev']}");
            $pdo->exec("UPDATE pt_takimlar SET atilan_gol=atilan_gol+$ds,yenilen_gol=yenilen_gol+$es WHERE id={$m['dep']}");
            if($es>$ds){$pdo->exec("UPDATE pt_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['ev']}");$pdo->exec("UPDATE pt_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['dep']}");}
            elseif($es==$ds){$pdo->exec("UPDATE pt_takimlar SET puan=puan+1,beraberlik=beraberlik+1 WHERE id IN ({$m['ev']},{$m['dep']})");}
            else{$pdo->exec("UPDATE pt_takimlar SET puan=puan+3,galibiyet=galibiyet+1 WHERE id={$m['dep']}");$pdo->exec("UPDATE pt_takimlar SET malubiyet=malubiyet+1 WHERE id={$m['ev']}");}
        }
        $kalan=$pdo->query("SELECT COUNT(*) FROM pt_maclar WHERE hafta=$hafta AND ev_skor IS NULL")->fetchColumn();
        if($kalan==0) $pdo->exec("UPDATE pt_ayar SET hafta=LEAST($max_hafta,hafta+1)");
        header("Location: liga_nos.php"); exit;
    }
}

// VERİ ÇEKİMİ
$puan_durumu=$pdo->query("SELECT * FROM pt_takimlar ORDER BY puan DESC,(atilan_gol-yenilen_gol) DESC,atilan_gol DESC")->fetchAll(PDO::FETCH_ASSOC);
$goster_hafta=isset($_GET['hafta'])?(int)$_GET['hafta']:$hafta;
if($goster_hafta<1) $goster_hafta=1; if($goster_hafta>$max_hafta) $goster_hafta=$max_hafta;
$haftanin_fiksturu=$pdo->query("SELECT m.*,t1.takim_adi as ev_ad,t1.logo as ev_logo,t2.takim_adi as dep_ad,t2.logo as dep_logo FROM pt_maclar m JOIN pt_takimlar t1 ON m.ev=t1.id JOIN pt_takimlar t2 ON m.dep=t2.id WHERE m.hafta=$goster_hafta")->fetchAll(PDO::FETCH_ASSOC);
$benim_macim_id=null;
if($kullanici_takim) {
    try { $benim_macim_id=$pdo->query("SELECT id FROM pt_maclar WHERE hafta=$goster_hafta AND ev_skor IS NULL AND (ev=$kullanici_takim OR dep=$kullanici_takim)")->fetchColumn(); } catch(Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Liga NOS | Ultimate Manager</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root { --pt-primary:#006600; --pt-secondary:#cc0000; --pt-gold:#d4af37; --bg:#0d0d0d; --panel:#1a1a1a; --border:rgba(0,102,0,0.25); --text:#f9fafb; --muted:#94a3b8; }
body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; background-image:radial-gradient(circle at 0% 0%,rgba(0,102,0,0.12) 0%,transparent 50%),radial-gradient(circle at 100% 100%,rgba(204,0,0,0.08) 0%,transparent 50%); }
.font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
.pro-navbar { background:rgba(10,10,10,0.97); backdrop-filter:blur(24px); border-bottom:2px solid var(--pt-secondary); position:sticky; top:0; z-index:1000; padding:0 2rem; height:75px; display:flex; justify-content:space-between; align-items:center; }
.nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:900; color:#fff; text-decoration:none; }
.nav-brand i { color:var(--pt-secondary); }
.nav-link-item { color:var(--muted); font-weight:600; font-size:0.95rem; padding:8px 16px; text-decoration:none; transition:0.2s; }
.nav-link-item:hover { color:#fff; }
.btn-ap { background:var(--pt-primary); color:#fff; font-weight:800; padding:8px 20px; border-radius:4px; text-decoration:none; border:none; transition:0.3s; }
.btn-ap:hover { background:var(--pt-secondary); color:#fff; }
.btn-ao { background:transparent; border:1px solid var(--pt-primary); color:var(--pt-primary); font-weight:700; padding:8px 20px; border-radius:4px; text-decoration:none; transition:0.3s; }
.btn-ao:hover { background:var(--pt-primary); color:#fff; }
.panel-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden; box-shadow:0 8px 24px rgba(0,0,0,0.5); }
.panel-header { padding:1.2rem 1.5rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.2); }
.panel-header h5 { color:#fff; margin:0; font-weight:700; font-family:'Oswald',sans-serif; font-size:1rem; text-transform:uppercase; }
.data-table { width:100%; border-collapse:separate; border-spacing:0; font-size:0.9rem; }
.data-table th { padding:1rem; color:var(--pt-gold); font-weight:700; text-transform:uppercase; font-size:0.75rem; border-bottom:1px solid var(--border); text-align:center; }
.data-table th:nth-child(2) { text-align:left; }
.data-table td { padding:0.8rem 1rem; text-align:center; border-bottom:1px solid rgba(255,255,255,0.03); vertical-align:middle; color:#fff; }
.data-table tbody tr:hover td { background:rgba(0,102,0,0.08); }
.cell-club { display:flex; align-items:center; gap:12px; text-decoration:none; color:#fff; font-weight:700; text-align:left; }
.cell-club img { width:28px; height:28px; object-fit:contain; }
.data-table tbody tr td:first-child { border-left:4px solid transparent; }
.zone-cl td:first-child { border-left-color:var(--pt-primary)!important; background:rgba(0,102,0,0.07); }
.zone-el td:first-child { border-left-color:var(--pt-secondary)!important; }
.zone-rel td:first-child { border-left-color:#6b7280!important; opacity:0.8; }
.scorebug-container { background:rgba(0,0,0,0.5); border:1px solid rgba(0,102,0,0.2); border-radius:10px; overflow:hidden; transition:0.3s; margin-bottom:14px; }
.scorebug-container:hover { border-color:var(--pt-primary); box-shadow:0 5px 15px rgba(0,102,0,0.15); transform:translateY(-2px); }
.score-grid { display:flex; width:100%; min-height:80px; align-items:stretch; }
.team-block { display:flex; align-items:center; gap:10px; padding:0 15px; flex:1; min-width:0; }
.team-block.home { justify-content:flex-end; }
.team-block.away { justify-content:flex-start; }
.team-name { font-weight:700; font-size:1rem; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.team-block.home .team-name { text-align:right; }
.team-logo { width:38px!important; height:38px!important; object-fit:contain; flex-shrink:0; }
.center-block { width:100px; flex-shrink:0; background:rgba(0,102,0,0.3); border-left:1px solid rgba(0,102,0,0.2); border-right:1px solid rgba(0,102,0,0.2); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:10px 0; }
.match-score { font-family:'Oswald',sans-serif; font-size:1.8rem; font-weight:900; color:var(--pt-gold); line-height:1; }
.match-status { font-size:0.75rem; color:#fff; font-weight:700; letter-spacing:1px; margin-top:4px; }
.match-actions { display:flex; background:rgba(0,0,0,0.3); border-top:1px solid rgba(255,255,255,0.1); }
.action-btn { flex:1; padding:10px; text-align:center; text-decoration:none; color:var(--pt-primary); font-size:0.85rem; font-weight:700; text-transform:uppercase; transition:0.2s; display:flex; justify-content:center; align-items:center; gap:8px; }
.action-btn:hover { background:var(--pt-primary); color:#fff!important; }
.events-grid { display:flex; width:100%; background:rgba(0,0,0,0.8); border-top:1px solid rgba(0,102,0,0.2); padding:8px 0; font-size:0.8rem; }
.event-col { display:flex; flex-direction:column; gap:6px; padding:0 15px; flex:1; min-width:0; }
.event-col.home { align-items:flex-end; text-align:right; }
.event-col.away { align-items:flex-start; }
.event-col.center { width:100px; flex:none; }
.event-item { display:flex; align-items:center; gap:8px; font-weight:600; max-width:100%; }
.event-time { font-family:'Oswald'; font-weight:700; color:var(--pt-secondary); flex-shrink:0; min-width:25px; }
.event-player { color:#fff; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.event-assist { color:var(--muted); font-size:0.7rem; font-style:italic; white-space:nowrap; }
.champion-banner { background:linear-gradient(135deg,rgba(0,102,0,0.3),rgba(204,0,0,0.2)); border:1px solid rgba(212,175,55,0.4); border-radius:12px; padding:16px 24px; margin-bottom:20px; display:flex; align-items:center; gap:16px; }
.champion-banner i { font-size:2rem; color:var(--pt-gold); }
.champion-banner .title { font-size:0.75rem; color:var(--muted); text-transform:uppercase; letter-spacing:1px; }
.champion-banner .name { font-size:1.2rem; font-weight:900; color:#fff; font-family:'Oswald',sans-serif; }
</style>
</head>
<body>
<nav class="pro-navbar">
    <a href="liga_nos.php" class="nav-brand font-oswald"><i class="fa-solid fa-star"></i> LIGA NOS</a>
    <div class="d-none d-lg-flex gap-3">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="liga_nos.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-tv"></i> Fikstür</a>
        <a href="ln_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-bar"></i> Puan Tablosu</a>
        <a href="ln_sezon_gecisi.php" class="nav-link-item"><i class="fa-solid fa-trophy"></i> Sezon Sonu</a>
    </div>
    <div class="d-flex gap-2">
        <?php if($kullanici_takim&&$benim_macim_id): ?>
        <a href="?action=tek_mac_simule&mac_id=<?=$benim_macim_id?>&hafta=<?=$goster_hafta?>" class="btn-ap"><i class="fa-solid fa-play"></i> Maçıma Çık</a>
        <?php endif; ?>
        <a href="?action=hafta" class="btn-ao"><i class="fa-solid fa-forward"></i> Haftayı Oyna</a>
    </div>
</nav>

<div class="container-fluid py-4 px-4">
    <!-- GEÇEN SEZON ŞAMPİYONU -->
    <div class="champion-banner">
        <i class="fa-solid fa-crown"></i>
        <div>
            <div class="title">🏆 Geçen Sezon Liga NOS Şampiyonu</div>
            <div class="name"><?=htmlspecialchars($gecen_sezon_sampiyon)?></div>
        </div>
    </div>

    <div class="row g-4">
        <!-- PUAN TABLOSU -->
        <div class="col-lg-5">
            <div class="panel-card">
                <div class="panel-header">
                    <h5><i class="fa-solid fa-table-list me-2"></i>Puan Durumu</h5>
                    <a href="ln_puan.php" class="btn-ao" style="padding:4px 12px;font-size:0.8rem;">Detay</a>
                </div>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead><tr><th>#</th><th>Takım</th><th>O</th><th>G</th><th>B</th><th>M</th><th>AV</th><th>P</th></tr></thead>
                        <tbody>
                        <?php foreach($puan_durumu as $i=>$t): $s=$i+1;
                            $cls=$s<=4?'zone-cl':($s<=5?'zone-el':($s>=16?'zone-rel':''));
                            $o=($t['galibiyet']+$t['beraberlik']+$t['malubiyet']);
                            $av=$t['atilan_gol']-$t['yenilen_gol'];
                        ?>
                        <tr class="<?=$cls?>">
                            <td style="color:var(--muted);font-weight:700;"><?=$s?></td>
                            <td><div class="cell-club"><img src="<?=htmlspecialchars($t['logo']??'')?>" onerror="this.style.display='none'"><span><?=htmlspecialchars($t['takim_adi'])?></span></div></td>
                            <td><?=$o?></td><td><?=$t['galibiyet']?></td><td><?=$t['beraberlik']?></td><td><?=$t['malubiyet']?></td>
                            <td style="color:<?=$av>0?'#22c55e':($av<0?'#ef4444':'#fff')?>"><?=($av>0?'+':'')?><?=$av?></td>
                            <td style="font-weight:900;color:var(--pt-gold);"><?=$t['puan']?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="padding:12px 16px;font-size:0.75rem;color:var(--muted);border-top:1px solid var(--border);">
                    <span style="border-left:3px solid var(--pt-primary);padding-left:6px;margin-right:12px;">UCL</span>
                    <span style="border-left:3px solid var(--pt-secondary);padding-left:6px;margin-right:12px;">UEL</span>
                    <span style="border-left:3px solid #6b7280;padding-left:6px;">Küme Düşme</span>
                </div>
            </div>
        </div>

        <!-- FİKSTÜR -->
        <div class="col-lg-7">
            <div class="panel-card" style="height:100%;">
                <div class="panel-header">
                    <h5><i class="fa-solid fa-calendar-check me-2"></i>Hafta <?=$goster_hafta?> / <?=$max_hafta?></h5>
                    <div class="d-flex gap-2">
                        <?php if($goster_hafta>1): ?><a href="?hafta=<?=$goster_hafta-1?>" class="btn-ao" style="padding:4px 10px;font-size:0.8rem;"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
                        <a href="?hafta=<?=$hafta?>" class="btn-ap" style="padding:4px 12px;font-size:0.8rem;">Güncel</a>
                        <?php if($goster_hafta<$max_hafta): ?><a href="?hafta=<?=$goster_hafta+1?>" class="btn-ao" style="padding:4px 10px;font-size:0.8rem;"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
                    </div>
                </div>
                <div style="overflow-y:auto;max-height:600px;padding:16px;">
                <?php if(empty($haftanin_fiksturu)): ?>
                    <div class="text-center py-5" style="color:var(--muted);">Bu hafta için fikstür bulunamadı.</div>
                <?php else: foreach($haftanin_fiksturu as $m):
                    $oynandi=$m['ev_skor']!==null;
                    $benim=$kullanici_takim&&($m['ev']==$kullanici_takim||$m['dep']==$kullanici_takim);
                    $ev_olaylar=json_decode($m['ev_olaylar']??'[]',true)?:[];
                    $dep_olaylar=json_decode($m['dep_olaylar']??'[]',true)?:[];
                ?>
                <div class="scorebug-container" <?=$benim?'style="border-color:var(--pt-gold);box-shadow:0 0 15px rgba(212,175,55,0.3);"':''?>>
                    <div class="score-grid">
                        <div class="team-block home">
                            <span class="team-name"><?=htmlspecialchars($m['ev_ad'])?></span>
                            <img src="<?=htmlspecialchars($m['ev_logo']??'')?>" class="team-logo" onerror="this.style.display='none'">
                        </div>
                        <div class="center-block">
                            <?php if($oynandi): ?>
                            <div class="match-score"><?=$m['ev_skor']?> - <?=$m['dep_skor']?></div>
                            <div class="match-status">OYNANDI</div>
                            <?php else: ?>
                            <div class="match-score" style="font-size:1.2rem;color:var(--muted);">VS</div>
                            <div class="match-status" style="color:var(--pt-secondary);">BEKLEMEDE</div>
                            <?php endif; ?>
                        </div>
                        <div class="team-block away">
                            <img src="<?=htmlspecialchars($m['dep_logo']??'')?>" class="team-logo" onerror="this.style.display='none'">
                            <span class="team-name"><?=htmlspecialchars($m['dep_ad'])?></span>
                        </div>
                    </div>
                    <?php if($oynandi&&!empty($ev_olaylar)): ?>
                    <div class="events-grid">
                        <div class="event-col home">
                        <?php foreach($ev_olaylar as $o): if(strtolower($o['tip']??'')=='gol'): ?>
                            <div class="event-item justify-content-end">
                                <span class="event-assist"><?=$o['asist']!='-'?'(as: '.htmlspecialchars($o['asist']??'').')':''?></span>
                                <span class="event-player"><?=htmlspecialchars($o['oyuncu']??'')?></span>
                                <span class="event-time"><?=$o['dakika']??''?>'⚽</span>
                            </div>
                        <?php endif; endforeach; ?>
                        </div>
                        <div class="event-col center"></div>
                        <div class="event-col away">
                        <?php foreach($dep_olaylar as $o): if(strtolower($o['tip']??'')=='gol'): ?>
                            <div class="event-item">
                                <span class="event-time"><?=$o['dakika']??''?>'⚽</span>
                                <span class="event-player"><?=htmlspecialchars($o['oyuncu']??'')?></span>
                                <span class="event-assist"><?=$o['asist']!='-'?'(as: '.htmlspecialchars($o['asist']??'').')':''?></span>
                            </div>
                        <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if(!$oynandi): ?>
                    <div class="match-actions">
                        <a href="?action=tek_mac_simule&mac_id=<?=$m['id']?>&hafta=<?=$goster_hafta?>" class="action-btn"><i class="fa-solid fa-bolt me-1"></i>Simüle Et</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
