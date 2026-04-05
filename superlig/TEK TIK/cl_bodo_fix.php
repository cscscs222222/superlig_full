<?php
include 'db.php';
error_reporting(0);

echo "<body style='background:#0a0b0d; color:#fff; font-family:sans-serif; text-align:center; padding-top:50px;'>";
echo "<h2 style='color:#f1c40f;'>🇳🇴 BODØ/GLIMT ÖZEL YAMASI YÜKLENİYOR...</h2>";
echo "<div style='background:#1e2229; padding:20px; border-radius:10px; display:inline-block; margin-top:20px; text-align:left; border:1px solid #2a2f38;'>";

// Veritabanından Bodø/Glimt'i bul
$stmt_takim = $pdo->prepare("SELECT id, takim_adi FROM cl_takimlar WHERE takim_adi = 'Bodø/Glimt' OR takim_adi LIKE '%Bodø%' LIMIT 1");
$stmt_takim->execute();
$takim = $stmt_takim->fetch(PDO::FETCH_ASSOC);

if($takim) {
    $takim_id = $takim['id'];
    $takim_adi = $takim['takim_adi'];
    $dogru_tm_id = 501; // Doğru A Takım ID'si

    echo "<h3 style='margin-top:0;'>Hedef: <span style='color:#00f2fe;'>$takim_adi</span> (Yeni TM ID: $dogru_tm_id)</h3>";

    // Önceki hatalı çekilen/boş kalan oyuncuları temizle
    $pdo->exec("DELETE FROM cl_oyuncular WHERE takim_id = $takim_id");
    echo "<div style='color:#a0a5b1; margin-bottom:10px;'>🧹 Eski kadro temizlendi. Yeni oyuncular çekiliyor...</div>";

    // Transfermarkt'a bağlan
    $url = "https://www.transfermarkt.com.tr/jumplist/kader/verein/{$dogru_tm_id}/saison_id/2025";
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
                if($deger >= 100000000) $ovr = rand(89, 94);
                elseif($deger >= 70000000) $ovr = rand(86, 88);
                elseif($deger >= 50000000) $ovr = rand(84, 85);
                elseif($deger >= 30000000) $ovr = rand(81, 83);
                elseif($deger >= 15000000) $ovr = rand(78, 80);
                elseif($deger >= 8000000) $ovr = rand(75, 77);
                elseif($deger >= 3000000) $ovr = rand(71, 74);
                elseif($deger >= 1000000) $ovr = rand(67, 70);
                else $ovr = rand(62, 66);

                $form = rand(5, 9);
                $stmt_oyuncu->execute([$takim_id, $isim, $mevki, $ovr, $ovr, $form]);
                $oyuncu_sayisi++;
            }
        }
        
        echo "<div style='color:#2ecc71; font-weight:bold; margin-bottom:10px;'>✅ $oyuncu_sayisi oyuncu başarıyla çekildi ve OVR güçleri atandı!</div>";

        // İLK 11'İ OTOMATİK BELİRLE (OVR'si en yüksek olanları seç)
        $mevkiler_limit = ['K'=>1, 'D'=>4, 'OS'=>3, 'F'=>3];
        foreach($mevkiler_limit as $mvk => $limit) {
            $en_iyiler = $pdo->query("SELECT id FROM cl_oyuncular WHERE takim_id = $takim_id AND mevki = '$mvk' ORDER BY ovr DESC LIMIT $limit")->fetchAll();
            foreach($en_iyiler as $iyi) {
                $pdo->exec("UPDATE cl_oyuncular SET ilk_11 = 1 WHERE id = " . $iyi['id']);
            }
        }
        echo "<div style='color:#f1c40f;'>⭐ Norveç ekibinin En Güçlü İlk 11'i sahaya dizildi.</div>";

    } else {
        echo "<div style='color:#e74c3c;'>⚠️ TM bağlantısı kurulamadı. Lütfen sayfayı yenileyin.</div>";
    }
} else {
    echo "<div style='color:#e74c3c;'>⚠️ Veritabanında Bodø/Glimt takımı bulunamadı!</div>";
}

echo "</div>";
echo "<br><br><a href='cl_puan.php' style='display:inline-block; padding:15px 30px; background:#00f2fe; color:#000; font-weight:bold; border-radius:8px; text-decoration:none;'>Puan Tablosuna Dön</a>";
echo "</body>";
?>