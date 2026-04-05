<?php
// ==============================================================================
// ULTIMATE MANAGER - ULTRA MODERN CANLI TV YAYINI V4.0 (BROADCAST HUD)
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
elseif($lig_kodu == 'es') { $prefix = "es_"; $geridon_link = "index.php"; } 
elseif($lig_kodu == 'de') { $prefix = "de_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'fr') { $prefix = "fr_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'it') { $prefix = "it_"; $geridon_link = "index.php"; }
elseif($lig_kodu == 'pt') { $prefix = "pt_"; $geridon_link = "index.php"; }

$tbl_maclar = $prefix . "maclar";
$tbl_takimlar = $prefix . "takimlar";
$tbl_ayar = $prefix . "ayar";

$engine = new MatchEngine($pdo, $prefix);

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
        // Skoru hâlâ NULL olan maçı kilitle; başka istek zaten güncellemiş olabilir
        $lock = $pdo->prepare("SELECT ev_skor FROM $tbl_maclar WHERE id = ? FOR UPDATE");
        $lock->execute([$mac_id]);
        $kontrol = $lock->fetchColumn();

        if ($kontrol === null) {
            $skorlar = $engine->gercekci_skor_hesapla($mac['ev'], $mac['dep'], $mac);
            $ev_skor = $skorlar['ev']; $dep_skor = $skorlar['dep'];

            $ev_detay = $engine->mac_olay_uret($mac['ev'], $ev_skor);
            $dep_detay = $engine->mac_olay_uret($mac['dep'], $dep_skor);

            $stmt = $pdo->prepare("UPDATE $tbl_maclar SET ev_skor=?, dep_skor=?, ev_olaylar=?, dep_olaylar=?, ev_kartlar=?, dep_kartlar=? WHERE id=?");
            $stmt->execute([$ev_skor, $dep_skor, $ev_detay['olaylar'], $dep_detay['olaylar'], $ev_detay['kartlar'], $dep_detay['kartlar'], $mac_id]);

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

// ZAMAN ÇİZELGESİ
$tum_olaylar = [];
$ev_olaylar = json_decode($mac['ev_olaylar'] ?? '[]', true) ?: [];
$dep_olaylar = json_decode($mac['dep_olaylar'] ?? '[]', true) ?: [];
$ev_kartlar = json_decode($mac['ev_kartlar'] ?? '[]', true) ?: [];
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
usort($tum_olaylar, function($a, $b) { return $a['dk'] <=> $b['dk']; });

// İSTATİSTİKLER
$ev_topla_oynama = ($mac['ev_skor'] > $mac['dep_skor']) ? rand(52, 65) : (($mac['ev_skor'] == $mac['dep_skor']) ? rand(45, 55) : rand(35, 48));
$dep_topla_oynama = 100 - $ev_topla_oynama;
$ev_sut = ($mac['ev_skor'] * rand(2,4)) + rand(2,5);
$dep_sut = ($mac['dep_skor'] * rand(2,4)) + rand(2,5);

$json_olaylar = json_encode($tum_olaylar, JSON_UNESCAPED_UNICODE);
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

        /* HUD Karartma Efekti (Üst ve Alt kısımlar için) */
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
        
        .comm-line.gol { border-left-color: #facc15; background: linear-gradient(90deg, rgba(250,204,21,0.15), rgba(15,23,42,0.85)); font-weight: 800; border: 1px solid rgba(250,204,21,0.3);}
        .comm-line.sari { border-left-color: #facc15; }
        .comm-line.kirmizi { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.15), rgba(15,23,42,0.85));}
        
        .dk-badge { font-family: 'Oswald'; font-weight: 900; font-size: 1.5rem; color: #fff; width: 50px; text-align: center;}
        .comm-text { flex: 1; }

        /* Gol Animasyonu Kutusu (Ekranda beliren dev grafik) */
        .goal-popup {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.5);
            opacity: 0; pointer-events: none; z-index: 100; text-align: center;
            transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .goal-popup.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .goal-word { font-family: 'Oswald'; font-size: 8rem; font-weight: 900; color: #facc15; text-shadow: 0 10px 30px rgba(0,0,0,0.9), 0 0 50px rgba(250,204,21,0.5); line-height: 1; margin:0;}
        .goal-scorer { font-size: 2.5rem; font-weight: 800; background: rgba(0,0,0,0.8); padding: 10px 30px; border-radius: 50px; display: inline-block; margin-top: 10px; border: 2px solid #facc15;}

        /* --- MAÇ SONU MODAL --- */
        .stats-modal {
            position: absolute; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(15px);
            display: flex; align-items: center; justify-content: center;
            z-index: 200; opacity: 0; pointer-events: none; transition: 0.5s;
        }
        .stats-modal.show { opacity: 1; pointer-events: all; }
        
        .stats-box { background: rgba(15,23,42,0.9); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 40px; width: 100%; max-width: 600px; box-shadow: 0 25px 60px rgba(0,0,0,0.9); transform: translateY(50px); transition: 0.5s; }
        .stats-modal.show .stats-box { transform: translateY(0); }
        
        .stat-row { display: flex; justify-content: space-between; margin-bottom: 10px; align-items: center; font-weight: 700; font-size: 1.2rem;}
        .stat-bar-bg { flex: 1; margin: 0 20px; height: 12px; background: rgba(255,255,255,0.05); border-radius: 6px; display: flex; overflow: hidden;}
        .stat-bar-ev { background: #3b82f6; height: 100%; transition: 1.5s ease-out; width: 0%;}
        .stat-bar-dep { background: #ef4444; height: 100%; transition: 1.5s ease-out; width: 0%;}

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

    <div class="live-badge">
        <div class="live-dot"></div> CANLI YAYIN
    </div>

    <div class="broadcast-hud">
        <div class="team-hud home">
            <span class="name font-oswald" id="name_ev"><?= $mac['ev_ad'] ?></span>
            <img src="<?= $mac['ev_logo'] ?>">
        </div>
        
        <div class="score-hud">
            <span id="score_ev">0</span>
            <span class="dash">-</span>
            <span id="score_dep">0</span>
            <div class="time-hud" id="match_time">00:00</div>
        </div>
        
        <div class="team-hud away">
            <img src="<?= $mac['dep_logo'] ?>">
            <span class="name font-oswald" id="name_dep"><?= $mac['dep_ad'] ?></span>
        </div>
    </div>

    <div class="goal-popup" id="goal_anim">
        <div class="goal-word">GOOOOL!</div>
        <div class="goal-scorer" id="goal_scorer_name">Oyuncu Adı</div>
    </div>

    <div class="bottom-ticker" id="commentary_container">
        </div>

    <div class="stats-modal" id="stats_modal">
        <div class="stats-box">
            <h2 class="font-oswald text-center mb-1">MAÇ SONUCU</h2>
            <div class="text-center font-oswald text-muted mb-4 fs-5" id="final_score_text"></div>
            
            <div class="stat-row">
                <span style="color:#3b82f6;">%<?= $ev_topla_oynama ?></span>
                <div class="stat-bar-bg"><div class="stat-bar-ev" id="bar_ev_pos"></div><div class="stat-bar-dep" id="bar_dep_pos"></div></div>
                <span style="color:#ef4444;">%<?= $dep_topla_oynama ?></span>
            </div>
            <div class="text-center text-muted small mb-4 text-uppercase">Topla Oynama</div>

            <div class="stat-row">
                <span style="color:#3b82f6;"><?= $ev_sut ?></span>
                <div class="stat-bar-bg"><div class="stat-bar-ev" id="bar_ev_sut"></div><div class="stat-bar-dep" id="bar_dep_sut"></div></div>
                <span style="color:#ef4444;"><?= $dep_sut ?></span>
            </div>
            <div class="text-center text-muted small mb-2 text-uppercase">Toplam Şut</div>

            <a href="../<?= $geridon_link ?>?hafta=<?= $hafta_yonlendirme ?>" class="btn-end">
                İLERLE <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <script>
        const events = <?= $json_olaylar ?>;
        const evName = "<?= addslashes($mac['ev_ad']) ?>";
        const depName = "<?= addslashes($mac['dep_ad']) ?>";
        
        let currentMinute = 0;
        let scoreEv = 0;
        let scoreDep = 0;
        let eventIndex = 0;
        
        const timeEl = document.getElementById('match_time');
        const scoreEvEl = document.getElementById('score_ev');
        const scoreDepEl = document.getElementById('score_dep');
        const ticker = document.getElementById('commentary_container');
        
        const goalAnim = document.getElementById('goal_anim');
        const goalScorer = document.getElementById('goal_scorer_name');

        const golCümleleri = [
            "Muhteşem bir vuruş, kaleci çaresiz!",
            "Defans büyük bir hata yaptı ve cezayı kestiler.",
            "Klas bir vuruşla meşin yuvarlağı ağlara gönderiyor.",
            "Tribünler ayakta! Harika bir organizasyon."
        ];

        // Sadece son 3 yorumu ekranda tut
        function addCommentary(text, type = '') {
            const line = document.createElement('div');
            line.className = `comm-line ${type}`;
            
            let icon = "";
            if(type=='gol') icon = `<i class="fa-solid fa-futbol" style="color:#facc15; font-size:1.5rem;"></i>`;
            else if(type=='sari') icon = `<i class="fa-solid fa-clone" style="color:#facc15; font-size:1.5rem;"></i>`;
            else if(type=='kirmizi') icon = `<i class="fa-solid fa-clone" style="color:#ef4444; font-size:1.5rem;"></i>`;
            else icon = `<i class="fa-solid fa-microphone text-muted" style="font-size:1.5rem;"></i>`;

            line.innerHTML = `
                <div class="dk-badge">${currentMinute}'</div>
                <div>${icon}</div>
                <div class="comm-text">${text}</div>
            `;
            
            ticker.appendChild(line);
            
            // Eğer 3'ten fazla satır olduysa en üsttekini (eskiyi) sil
            if(ticker.children.length > 3) {
                ticker.removeChild(ticker.firstElementChild);
            }
        }

        // İlk Yorum
        addCommentary(`Karşılaşma hakemin düdüğüyle başlıyor. İki takıma da başarılar...`);

        // MAÇ DÖNGÜSÜ
        const matchInterval = setInterval(() => {
            currentMinute++;
            let minStr = currentMinute < 10 ? '0'+currentMinute : currentMinute;
            timeEl.innerText = `${minStr}:00`;

            while(eventIndex < events.length && events[eventIndex].dk <= currentMinute) {
                let e = events[eventIndex];
                let isEv = (e.kim === 'ev');
                let takimAdi = isEv ? evName : depName;
                
                if(e.tur === 'gol') {
                    if(isEv) { 
                        scoreEv++; scoreEvEl.innerText = scoreEv; 
                        scoreEvEl.classList.add('score-update'); setTimeout(()=>scoreEvEl.classList.remove('score-update'), 500);
                    } else { 
                        scoreDep++; scoreDepEl.innerText = scoreDep; 
                        scoreDepEl.classList.add('score-update'); setTimeout(()=>scoreDepEl.classList.remove('score-update'), 500);
                    }
                    
                    // Dev Ekranda Gol Animasyonu
                    goalScorer.innerText = `${e.oyuncu} (${takimAdi})`;
                    goalAnim.classList.add('show');
                    setTimeout(()=> goalAnim.classList.remove('show'), 3000);

                    let cumb = golCümleleri[Math.floor(Math.random() * golCümleleri.length)];
                    let text = `<strong>GOOOOOOLLL!</strong> ${e.oyuncu} sahneye çıktı! ${cumb}`;
                    addCommentary(text, 'gol');
                }
                else if(e.tur === 'sari') {
                    addCommentary(`Sert müdahale. <strong>${e.oyuncu}</strong> (${takimAdi}) sarı kart görüyor.`, 'sari');
                }
                else if(e.tur === 'kirmizi') {
                    addCommentary(`KIRMIZI KART! <strong>${e.oyuncu}</strong> (${takimAdi}) oyundan atıldı! Takım eksik kaldı.`, 'kirmizi');
                }
                eventIndex++;
            }

            if(eventIndex >= events.length || events[eventIndex].dk > currentMinute) {
                if(currentMinute === 45) addCommentary("İlk yarı sonucu. Takımlar soyunma odasına gidiyor.");
                else if(currentMinute === 46) addCommentary("İkinci yarı başladı!");
            }

            // MAÇ BİTİŞİ
            if(currentMinute >= 90) {
                clearInterval(matchInterval);
                timeEl.innerText = "MS";
                timeEl.style.background = "#10b981"; 
                addCommentary("Maç sona erdi! 90 dakikalık mücadele bitti.");
                
                // 1 Saniye Sonra Modal Açılsın ve Barlar Dolsun
                setTimeout(() => {
                    document.getElementById('stats_modal').classList.add('show');
                    document.getElementById('final_score_text').innerText = `${evName} ${scoreEv} - ${scoreDep} ${depName}`;
                    
                    // CSS Progress Barları doldur (Animasyonlu)
                    document.getElementById('bar_ev_pos').style.width = "<?= $ev_topla_oynama ?>%";
                    document.getElementById('bar_dep_pos').style.width = "<?= $dep_topla_oynama ?>%";
                    
                    let totalSut = <?= $ev_sut + $dep_sut ?>;
                    let ev_sut_yuzde = totalSut > 0 ? (<?= $ev_sut ?> / totalSut) * 100 : 50;
                    let dep_sut_yuzde = totalSut > 0 ? 100 - ev_sut_yuzde : 50;
                    document.getElementById('bar_ev_sut').style.width = ev_sut_yuzde + "%";
                    document.getElementById('bar_dep_sut').style.width = dep_sut_yuzde + "%";
                }, 1500);
            }
        }, 150); 
    </script>
</body>
</html>