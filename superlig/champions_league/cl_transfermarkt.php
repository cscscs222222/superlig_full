<?php
include '../db.php';

// --- AJAX İSTEĞİ: TEK BİR OYUNCUYU TARAMA ---
if(isset($_GET['ajax_oyuncu_id'])) {
    header('Content-Type: application/json');
    $oyuncu_id = (int)$_GET['ajax_oyuncu_id'];
    $oyuncu = $pdo->query("SELECT * FROM cl_oyuncular WHERE id = $oyuncu_id")->fetch();
    
    if(!$oyuncu) { echo json_encode(['durum' => 'hata', 'mesaj' => 'Oyuncu bulunamadı']); exit; }

    $isim = $oyuncu['isim'];
    $yeni_fiyat = 0;
    $kaynak = "Algoritma"; // Varsayılan

    // 1. TRANSFERMARKT ARAMASI (Gelişmiş cURL)
    $ch = curl_init();
    $search_url = "https://www.transfermarkt.com.tr/schnellsuche/ergebnis/schnellsuche?query=" . urlencode($isim);
    curl_setopt($ch, CURLOPT_URL, $search_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3 saniyede bulamazsa geç
    
    // SSL Doğrulamasını Kapat! (Localhost için çok önemli)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    // Bot korumasını aşmak için gerçek tarayıcı başlıkları taklidi
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7',
        'Connection: keep-alive'
    ]);
    
    $html = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 2. VERİYİ PARÇALAMA (Regex)
    if($http_code == 200 && $html) {
        if(preg_match('/class="rechts hauptlink">(.*?)€<\/td>/is', $html, $matches)) {
            $deger_str = trim(strip_tags($matches[1]));
            
            if(strpos($deger_str, 'mil.') !== false) {
                $yeni_fiyat = (float)str_replace(['mil.', ','], ['', '.'], $deger_str) * 1000000;
                $kaynak = "Transfermarkt";
            } elseif(strpos($deger_str, 'Bin') !== false || strpos($deger_str, 'bin') !== false) {
                $yeni_fiyat = (float)str_replace(['Bin', 'bin', ','], ['', '', '.'], $deger_str) * 1000;
                $kaynak = "Transfermarkt";
            }
        }
    }

    // 3. EĞER TM BULAMAZSA (VEYA ENGELLERSE) -> YEDEK ALGORİTMA DEVREYE GİRER
    if($yeni_fiyat == 0) {
        $yeni_fiyat = ($oyuncu['ovr'] * $oyuncu['ovr'] * 2500) * ($oyuncu['form'] / 5) * ($oyuncu['fitness'] / 100);
        $yeni_fiyat = round($yeni_fiyat);
    }

    // 4. VERİTABANINA KAYDET
    $pdo->exec("UPDATE cl_oyuncular SET fiyat = $yeni_fiyat WHERE id = $oyuncu_id");

    // Para birimini formata çevir
    $fiyat_gorsel = "€" . $yeni_fiyat;
    if ($yeni_fiyat >= 1000000) $fiyat_gorsel = "€" . number_format($yeni_fiyat / 1000000, 1) . "M";
    elseif ($yeni_fiyat >= 1000) $fiyat_gorsel = "€" . number_format($yeni_fiyat / 1000, 0) . "K";

    echo json_encode([
        'durum' => 'basarili',
        'isim' => $isim,
        'fiyat' => $fiyat_gorsel,
        'kaynak' => $kaynak
    ]);
    exit;
}

// --- NORMAL SAYFA YÜKLENMESİ (ARAYÜZ) ---
$takim_id = isset($_GET['takim_id']) ? (int)$_GET['takim_id'] : 0;

// EĞER TAKIM SEÇİLMEDİYSE TAKIM SEÇİM EKRANINI GÖSTER
if(!$takim_id) {
    $takimlar = $pdo->query("SELECT * FROM cl_takimlar ORDER BY takim_adi ASC")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html lang="tr" data-bs-theme="dark">
    <head>
        <meta charset="UTF-8">
        <title>Takım Seç - Scout Ağı</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>body{background:#020614; color:#fff; font-family:'Segoe UI',sans-serif;}</style>
    </head>
    <body class="d-flex align-items-center justify-content-center vh-100">
        <div class="text-center">
            <h2 class="text-info mb-4"><i class="fa-solid fa-satellite-dish"></i> GLOBAL SCOUT AĞI</h2>
            <h5 class="mb-4">Hangi takımın oyuncu değerlerini güncellemek istiyorsunuz?</h5>
            <div class="d-flex flex-wrap justify-content-center gap-3" style="max-width: 800px;">
                <?php foreach($takimlar as $t): ?>
                    <a href="?takim_id=<?= $t['id'] ?>" class="btn btn-outline-light d-flex align-items-center gap-2 px-4 py-2">
                        <img src="<?= $t['logo'] ?>" width="30"> <?= $t['takim_adi'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <a href="cl_admin.php" class="btn btn-secondary mt-5">Admin Paneline Dön</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// TAKIM SEÇİLDİYSE TARAMA TERMİNALİNİ GÖSTER
$takim = $pdo->query("SELECT * FROM cl_takimlar WHERE id = $takim_id")->fetch();
$oyuncular = $pdo->query("SELECT id, isim, ovr FROM cl_oyuncular WHERE takim_id = $takim_id ORDER BY ovr DESC")->fetchAll();
$oyuncu_idler_json = json_encode(array_column($oyuncular, 'id'));
?>

<!DOCTYPE html>
<html lang="tr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>Transfermarkt Veri Tarayıcı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body { background-color: #020614; color: #fff; font-family: 'JetBrains Mono', monospace; }
        .terminal-box { background: #0a0f1c; border: 1px solid #1a2a4f; border-radius: 12px; padding: 30px; box-shadow: 0 0 30px rgba(0,242,254,0.1); max-width: 800px; margin: 50px auto;}
        
        .progress { height: 25px; background-color: #111; border: 1px solid #333; border-radius: 12px; }
        .progress-bar { background: linear-gradient(90deg, #00f2fe, #4facfe); font-weight: bold; font-size: 1rem;}
        
        .log-box { background: #000; border: 1px solid #222; border-radius: 8px; height: 350px; overflow-y: auto; padding: 15px; margin-top: 20px; font-size: 0.9rem;}
        .log-line { border-bottom: 1px dashed #222; padding: 8px 0; display: flex; justify-content: space-between; align-items: center;}
        
        .source-tm { color: #00f2fe; font-weight: bold; background: rgba(0,242,254,0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;}
        .source-algo { color: #f1c40f; font-weight: bold; background: rgba(241,196,15,0.1); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;}
        
        .blink { animation: blinker 1s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #1a2a4f; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="terminal-box">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold m-0 text-info"><i class="fa-solid fa-satellite-dish"></i> GLOBAL SCOUT AĞI</h3>
            <img src="<?= $takim['logo'] ?>" width="50">
        </div>
        
        <p class="text-secondary"><strong><?= $takim['takim_adi'] ?></strong> oyuncuları için Transfermarkt veritabanı taranıyor...</p>
        
        <div class="progress mb-2">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%;">0%</div>
        </div>
        <div class="text-end text-info fw-bold small" id="statusText">Sistem Hazırlanıyor...</div>

        <div class="log-box" id="logBox">
            </div>

        <div class="mt-4 text-center" id="actionButtons" style="display:none;">
            <h5 class="text-success fw-bold mb-3"><i class="fa-solid fa-check-circle"></i> Tarama Tamamlandı!</h5>
            <a href="cl_admin.php?takim_id=<?= $takim_id ?>" class="btn btn-outline-light px-5 fw-bold rounded-pill">Taktik Paneline Dön</a>
        </div>
    </div>
</div>

<script>
const oyuncuIdleri = <?= $oyuncu_idler_json ?>;
const toplamOyuncu = oyuncuIdleri.length;
let islenenOyuncu = 0;

function logYaz(isim, fiyat, kaynak) {
    const box = document.getElementById('logBox');
    const badge = kaynak === 'Transfermarkt' ? '<span class="source-tm">Transfermarkt</span>' : '<span class="source-algo">Algoritma</span>';
    const satir = document.createElement('div');
    satir.className = 'log-line';
    satir.innerHTML = `
        <div><i class="fa-solid fa-magnifying-glass text-secondary me-2"></i> ${isim}</div>
        <div class="text-end">
            <span class="text-success fw-bold me-3">${fiyat}</span>
            ${badge}
        </div>
    `;
    box.appendChild(satir);
    box.scrollTop = box.scrollHeight; // Otomatik aşağı kaydır
}

async function oyunculariTara() {
    for (let i = 0; i < toplamOyuncu; i++) {
        let id = oyuncuIdleri[i];
        document.getElementById('statusText').innerHTML = `<span class="blink">Aranıyor [${i+1}/${toplamOyuncu}]...</span>`;
        
        try {
            let response = await fetch(`cl_transfermarkt.php?ajax_oyuncu_id=${id}`);
            let data = await response.json();
            
            if(data.durum === 'basarili') {
                logYaz(data.isim, data.fiyat, data.kaynak);
            }
        } catch (error) {
            console.error("Fetch Hatası: ", error);
        }

        // İlerleme Çubuğunu Güncelle
        islenenOyuncu++;
        let yuzde = Math.round((islenenOyuncu / toplamOyuncu) * 100);
        document.getElementById('progressBar').style.width = yuzde + '%';
        document.getElementById('progressBar').innerText = yuzde + '%';
    }

    // Bittiğinde
    document.getElementById('statusText').innerText = "Veritabanı Güncellendi.";
    document.getElementById('actionButtons').style.display = "block";
}

// Sayfa açılır açılmaz taramayı başlat
window.onload = function() {
    if(toplamOyuncu > 0) {
        oyunculariTara();
    } else {
        document.getElementById('logBox').innerHTML = "<div class='text-center mt-5 text-danger'>Takımda oyuncu bulunamadı.</div>";
    }
};
</script>

</body>
</html>