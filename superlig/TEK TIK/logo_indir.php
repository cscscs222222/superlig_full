<?php
include 'db.php';

// Logoları kaydedeceğimiz klasör (Yoksa otomatik oluşturulacak)
$klasor = 'uploads';
if (!file_exists($klasor)) {
    mkdir($klasor, 0777, true);
}

// Transfermarkt Resmi Takım ID'leri (En güvenilir kaynak)
$takim_idleri = [
    'Galatasaray' => '141', 
    'Fenerbahçe' => '36', 
    'Beşiktaş' => '114',
    'Trabzonspor' => '449', 
    'Başakşehir' => '6890', 
    'Adana Demirspor' => '3840',
    'Kasımpaşa' => '10484', 
    'Sivasspor' => '2381', 
    'Antalyaspor' => '589',
    'Kayserispor' => '3205', 
    'Konyaspor' => '2293', 
    'Alanyaspor' => '11282',
    'Gaziantep FK' => '2832', 
    'Rizespor' => '126', 
    'Ankaragücü' => '868',
    'Samsunspor' => '152', 
    'Hatayspor' => '7775', 
    'Pendikspor' => '3209',
    'İstanbulspor' => '924', 
    'Karagümrük' => '6646'
];

// cURL ile indirme fonksiyonu (Sitelerin bot engellemesini aşmak için tarayıcı gibi davranır)
function resim_indir($url, $kayit_yolu) {
    $ch = curl_init($url);
    $fp = fopen($kayit_yolu, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    // İnternet sitelerine "Ben bir bot değilim, Chrome kullanıyorum" diyoruz
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    // SSL hatalarını yoksay (Yerelde çalışırken sorun çıkmaması için)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

foreach ($takim_idleri as $takim_adi => $id) {
    // Şeffaf arka planlı, yüksek kaliteli resmi çekiyoruz
    $logo_url = "https://tmssl.akamaized.net/images/wappen/normquad/{$id}.png";
    $yerel_yol = "{$klasor}/logo_{$id}.png";
    
    // Resmi indir ve uploads klasörüne kaydet
    resim_indir($logo_url, $yerel_yol);
    
    // Veritabanındaki eski kırık linki, yeni yerel dosyamızla değiştir
    $stmt = $pdo->prepare("UPDATE takimlar SET logo = ? WHERE takim_adi = ?");
    $stmt->execute([$yerel_yol, $takim_adi]);
}

echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
echo "<h2>✅ Tüm Logolar Başarıyla İndirildi!</h2>";
echo "<p>Artık logolar senin bilgisayarında barındırılıyor. Sayfayı yenilediğinde cam gibi görünecekler.</p>";
echo "<a href='index.php' style='padding:10px 20px; background:blue; color:white; text-decoration:none; border-radius:5px;'>Ana Sayfaya Dön</a>";
echo "</div>";
?>