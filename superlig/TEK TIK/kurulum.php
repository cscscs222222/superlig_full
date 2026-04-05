<?php
include 'db.php';

// 1. ESKİ TABLOLARI TEMİZLE VE YENİLERİNİ OLUŞTUR
$pdo->exec("DROP TABLE IF EXISTS maclar, oyuncular, takimlar, ayar");

$pdo->exec("CREATE TABLE ayar (id INT PRIMARY KEY, hafta INT DEFAULT 1)");
$pdo->exec("INSERT INTO ayar (id, hafta) VALUES (1, 1)");

$pdo->exec("CREATE TABLE takimlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    takim_adi VARCHAR(100),
    logo VARCHAR(255),
    hucum INT,
    savunma INT
)");

$pdo->exec("CREATE TABLE oyuncular (
    id INT AUTO_INCREMENT PRIMARY KEY,
    takim_id INT,
    isim VARCHAR(100),
    mevki VARCHAR(10),
    guc INT
)");

// Maçlarda kimin gol/asist yaptığını JSON formatında tutmak için olaylar sütunu ekledik
$pdo->exec("CREATE TABLE maclar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hafta INT,
    ev INT,
    dep INT,
    ev_skor INT DEFAULT NULL,
    dep_skor INT DEFAULT NULL,
    ev_olaylar TEXT DEFAULT NULL, 
    dep_olaylar TEXT DEFAULT NULL
)");

// 2. TAKIMLARI VE GÜÇLERİNİ EKLE (Gerçekçi FM Dengesi)
$takimlar_data = [
    ['Galatasaray', 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f6/Galatasaray_Sports_Club_Logo.png/150px-Galatasaray_Sports_Club_Logo.png', 88, 82],
    ['Fenerbahçe', 'https://upload.wikimedia.org/wikipedia/tr/8/86/Fenerbah%C3%A7e_SK.png', 87, 83],
    ['Beşiktaş', 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2a/Besiktas_JK_logotype.svg/150px-Besiktas_JK_logotype.svg.png', 84, 80],
    ['Trabzonspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/a/ab/TrabzonsporAmblemi.png/150px-TrabzonsporAmblemi.png', 80, 78],
    ['Başakşehir', 'https://upload.wikimedia.org/wikipedia/tr/thumb/4/4b/%C4%B0stanbul_Ba%C5%9Fak%C5%9Fehir_FK.png/150px-%C4%B0stanbul_Ba%C5%9Fak%C5%9Fehir_FK.png', 78, 79],
    ['Adana Demirspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/7/75/Adana_Demirspor_1940.png/150px-Adana_Demirspor_1940.png', 76, 72],
    ['Kasımpaşa', 'https://upload.wikimedia.org/wikipedia/tr/thumb/e/e9/KASIMPA%C5%9EA_SK_LOGO.png/150px-KASIMPA%C5%9EA_SK_LOGO.png', 75, 70],
    ['Sivasspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/8/88/Sivasspor.png/150px-Sivasspor.png', 72, 75],
    ['Antalyaspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/2/23/Antalyaspor_logo.png/150px-Antalyaspor_logo.png', 74, 73],
    ['Kayserispor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/5/5f/Kayserispor_logo_2022.png/150px-Kayserispor_logo_2022.png', 71, 74],
    ['Konyaspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/3/37/Konyaspor_1922_logo.png/150px-Konyaspor_1922_logo.png', 70, 76],
    ['Alanyaspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/3/30/Alanyaspor_logo.png/150px-Alanyaspor_logo.png', 75, 71],
    ['Gaziantep FK', 'https://upload.wikimedia.org/wikipedia/tr/thumb/2/28/Gaziantep_FK.png/150px-Gaziantep_FK.png', 70, 72],
    ['Rizespor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/8/87/%C3%87aykur_Rizespor_logo.png/150px-%C3%87aykur_Rizespor_logo.png', 73, 70],
    ['Ankaragücü', 'https://upload.wikimedia.org/wikipedia/tr/thumb/2/22/MKE_Ankarag%C3%BCc%C3%BC_logo.png/150px-MKE_Ankarag%C3%BCc%C3%BC_logo.png', 72, 71],
    ['Samsunspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/1/1a/Samsunspor_logo_2.png/150px-Samsunspor_logo_2.png', 71, 73],
    ['Hatayspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/5/51/Hatayspor.png/150px-Hatayspor.png', 70, 70],
    ['Pendikspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/9/94/Pendikspor_logo.png/150px-Pendikspor_logo.png', 68, 65],
    ['İstanbulspor', 'https://upload.wikimedia.org/wikipedia/tr/thumb/e/ef/%C4%B0stanbulspor_Amblemi.png/150px-%C4%B0stanbulspor_Amblemi.png', 67, 66],
    ['Karagümrük', 'https://upload.wikimedia.org/wikipedia/tr/thumb/7/7b/Fatih_Karag%C3%BCmr%C3%BCk_SK.png/150px-Fatih_Karag%C3%BCmr%C3%BCk_SK.png', 74, 69]
];

$stmt_takim = $pdo->prepare("INSERT INTO takimlar (takim_adi, logo, hucum, savunma) VALUES (?, ?, ?, ?)");
foreach ($takimlar_data as $t) {
    $stmt_takim->execute($t);
}

// 3. OYUNCULARI OTOMATİK OLUŞTUR (İsim havuzundan)
$ad_havuzu = ["Ahmet", "Can", "Burak", "Emre", "Mehmet", "Hakan", "Ozan", "Cengiz", "Kerem", "İrfan", "Ferdi", "Altay", "Uğurcan", "Mert", "Enes", "Kenan", "Arda", "Salih", "Cenk", "Semih"];
$soyad_havuzu = ["Yılmaz", "Kılıç", "Demir", "Çelik", "Şahin", "Öztürk", "Yıldız", "Aydın", "Özdemir", "Arslan", "Doğan", "Kaya", "Tekin", "Turan", "Güneş", "Köse", "Aksoy", "Yüksel", "Koç"];

$takimlar_id = $pdo->query("SELECT id FROM takimlar")->fetchAll(PDO::FETCH_COLUMN);

$stmt_oyuncu = $pdo->prepare("INSERT INTO oyuncular (takim_id, isim, mevki, guc) VALUES (?, ?, ?, ?)");

foreach ($takimlar_id as $tid) {
    // Her takıma 1 Kaleci, 4 Defans, 4 Orta Saha, 2 Forvet ekleyelim
    $kadro = [
        ['mevki' => 'K', 'adet' => 1],
        ['mevki' => 'D', 'adet' => 4],
        ['mevki' => 'OS', 'adet' => 4],
        ['mevki' => 'F', 'adet' => 2]
    ];

    foreach ($kadro as $k) {
        for ($i = 0; $i < $k['adet']; $i++) {
            $isim = $ad_havuzu[array_rand($ad_havuzu)] . " " . $soyad_havuzu[array_rand($soyad_havuzu)];
            $guc = rand(65, 88); // Rastgele oyuncu gücü
            $stmt_oyuncu->execute([$tid, $isim, $k['mevki'], $guc]);
        }
    }
}

echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
echo "<h2>✅ Kurulum Başarılı!</h2>";
echo "<p>Veritabanı, takımlar, logolar ve oyuncular kusursuz şekilde oluşturuldu.</p>";
echo "<a href='fikstur.php' style='padding:10px 20px; background:blue; color:white; text-decoration:none; border-radius:5px;'>Fikstürü Oluştur'a Git</a>";
echo "</div>";
?>