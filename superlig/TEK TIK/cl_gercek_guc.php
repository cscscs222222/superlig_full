<?php
include 'db.php';
set_time_limit(0); 
error_reporting(0);

// Sadece bu dosyaya özel, 36 takımın KESİN VE GÜNCEL Transfermarkt ID'leri
$tm_idler = [
    'Arsenal' => 11, 'Bayern München' => 27, 'Liverpool' => 31, 'Tottenham Hotspur' => 148,
    'Barcelona' => 131, 'Chelsea' => 631, 'Sporting CP' => 336, 'Manchester City' => 281,
    'Real Madrid' => 418, 'Inter' => 46, 'Paris SG' => 583, 'Newcastle United' => 762,
    'Juventus' => 506, 'Atletico Madrid' => 13, 'Atalanta' => 800, 'B. Leverkusen' => 15,
    'B. Dortmund' => 16, 'Olimpiakos' => 683, 'Club Brugge' => 2282, 'Galatasaray' => 141,
    'Monaco' => 162, 'Karabağ' => 10625, 'Bodø/Glimt' => 6099, 'Benfica' => 294,
    'Marsilya' => 244, 'Pafos FC' => 45457, 'Union SG' => 3948, 'PSV Eindhoven' => 383,
    'Athletic Bilbao' => 621, 'Napoli' => 6195, 'Kopenhag' => 190, 'Ajax' => 610,
    'E. Frankfurt' => 24, 'Slavia Prag' => 62, 'Villarreal' => 1050, 'Kairat' => 10482
];

// Sayfalama (Zaman aşımını engellemek için her sayfada 1 takım işlenir)
$takimlar = $pdo->query("SELECT id, takim_adi FROM cl_takimlar ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$idx = isset($_GET['idx']) ? (int)$_GET['idx'] : 0;

echo "<body style='background:#0a0b0d; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";

if ($idx >= count($takimlar)) {
    echo "<h1 style='color:#2ecc71;'>✅ BÜYÜK GÜNCELLEME TAMAMLANDI!</h1>";
    echo "<p>Tüm 36 takımın 2025 kadroları, güncel Piyasa Değerleri ve OVR güçleri sisteme yüklendi.</p>";
    echo "<a href='cl_puan.php' style='display:inline-block; padding:15px 30px; background:#00f2fe; color:#000; font-weight:bold; border-radius:8px; text-decoration:none;'>Puan Tablosuna Dön</a>";
    exit;
}

$takim = $takimlar[$idx];
$takim_adi = $takim['takim_adi'];
$takim_id = $takim['id'];
$tm_id = $tm_idler[$takim_adi] ?? 0;

echo "<h2 style='color:#00f2fe;'>⚙️ TRANSFERMARKT YAPAY ZEKASI ÇALIŞIYOR...</h2>";
echo "<p style='color:#a0a5b1;'>Bot korumasına takılmamak için takımlar tek tek işleniyor. Lütfen sekmeyi kapatmayın.</p>";
echo "<div style='background:#1e2229; padding:20px; border-radius:10px; display:inline-block; margin-top:20px; text-align:left; border:1px solid #2a2f38;'>";
echo "<h3 style='margin-top:0;'>İşlenen Takım: <span style='color:#f1c40f;'>$takim_adi</span> (ID: $tm_id)</h3>";

if ($tm_id > 0) {
    // Eski oyuncuları tamamen temizle (Sadece yeni transferler kalsın)
    $pdo->exec("DELETE FROM cl_oyuncular WHERE takim_id = $takim_id");

    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$tm_id}/saison_id/2025";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    $html = curl_exec($ch);
    curl_close($ch);

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $rows = $xpath->query("//table[@class='items']//tbody/tr");
        
        $stmt_oyuncu = $pdo->prepare("INSERT INTO cl_oyuncular (takim_id, isim, mevki, guc, ovr, form) VALUES (?, ?, ?, ?, ?, ?)");
        $oyuncu_sayisi = 0;
        
        foreach($rows as $row) {
            $nameNode = $xpath->query(".//td[@class='hauptlink']//a", $row)->item(0);
            $posNode = $xpath->query(".//table[@class='inline-table']//tr[2]//td", $row)->item(0);
            $valNode = $xpath->query(".//td[@class='rechts hauptlink']", $row)->item(0);
            
            if ($nameNode && $posNode) {
                $isim = trim($nameNode->nodeValue);
                $mevki_metin = mb_strtolower(trim($posNode->nodeValue), 'UTF-8');
                
                // MEVKİ DÖNÜŞÜMÜ
                $mevki = 'OS'; 
                if(strpos($mevki_metin, 'kaleci') !== false) $mevki = 'K';
                elseif(strpos($mevki_metin, 'bek') !== false || strpos($mevki_metin, 'stoper') !== false || strpos($mevki_metin, 'defans') !== false) $mevki = 'D';
                elseif(strpos($mevki_metin, 'santrafor') !== false || strpos($mevki_metin, 'forvet') !== false || strpos($mevki_metin, 'kanat') !== false) $mevki = 'F';

                // PİYASA DEĞERİ (MARKET VALUE) PARÇALAYICI
                $deger = 0;
                if ($valNode) {
                    $val_metin = mb_strtolower(trim($valNode->nodeValue), 'UTF-8');
                    $carpan = 1;
                    if(strpos($val_metin, 'mil') !== false || strpos($val_metin, 'm') !== false) $carpan = 1000000;
                    elseif(strpos($val_metin, 'bin') !== false || strpos($val_metin, 'k') !== false) $carpan = 1000;
                    
                    preg_match('/[0-9,\.]+/', $val_metin, $eslesme);
                    if(!empty($eslesme)) {
                        $sayi = (float)str_replace(',', '.', $eslesme[0]);
                        $deger = $sayi * $carpan;
                    }
                }

                // PİYASA DEĞERİNİ EA FC REYTİNGİNE (OVR) ÇEVİRME
                $ovr = 65;
                if($deger >= 100000000) $ovr = rand(89, 94);     // 100M+ Euro
                elseif($deger >= 70000000) $ovr = rand(86, 88);  // 70M+ Euro
                elseif($deger >= 50000000) $ovr = rand(84, 85);  // 50M+ Euro
                elseif($deger >= 30000000) $ovr = rand(81, 83);  // 30M+ Euro
                elseif($deger >= 15000000) $ovr = rand(78, 80);  // 15M+ Euro
                elseif($deger >= 8000000) $ovr = rand(75, 77);   // 8M+ Euro
                elseif($deger >= 3000000) $ovr = rand(71, 74);   // 3M+ Euro
                elseif($deger >= 1000000) $ovr = rand(67, 70);   // 1M+ Euro
                else $ovr = rand(62, 66);                        // Altı

                $form = rand(5, 9); // Standart başlangıç formu
                
                // Eski 'guc' sütununu da ovr ile aynı yapıyoruz ki uyum sorunu olmasın
                $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $ovr, $ovr, $form]);
                $oyuncu_sayisi++;
            }
        }
        
        echo "<div style='color:#2ecc71;'>↳ $oyuncu_sayisi oyuncu çekildi ve OVR değerleri hesaplandı.</div>";

        // İLK 11'İ BELİRLE (OVR'si en yüksek olanlar oynar)
        // 1 Kaleci, 4 Defans, 3 Orta Saha, 3 Forvet
        $mevkiler_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($mevkiler_limit as $mvk => $limit) {
            $en_iyiler = $pdo->query("SELECT id FROM cl_oyuncular WHERE takim_id = $takim_id AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $limit")->fetchAll();
            foreach($en_iyiler as $iyi) {
                $pdo->exec("UPDATE cl_oyuncular SET ilk_11 = 1 WHERE id = " . $iyi['id']);
            }
        }
        echo "<div style='color:#f1c40f;'>↳ Takımın En Güçlü İlk 11'i otomatik belirlendi.</div>";

    } else {
        echo "<div style='color:#e74c3c;'>⚠️ Siteye bağlanılamadı. (Bot engeli olabilir)</div>";
    }
} else {
    echo "<div style='color:#e74c3c;'>⚠️ Takım ID'si listede bulunamadı, atlanıyor.</div>";
}

echo "</div>";

// SONRAKİ TAKIMA GEÇMEK İÇİN SAYFAYI 2 SANİYE SONRA OTOMATİK YENİLE
$sonraki_idx = $idx + 1;
echo "<meta http-equiv='refresh' content='2;url=?idx=$sonraki_idx'>";
echo "<p style='margin-top:20px; color:#a0a5b1;'>2 Saniye içinde sıradaki takıma geçiliyor... Yüzde: " . round(($sonraki_idx/36)*100) . "%</p>";

echo "</body>";
?>