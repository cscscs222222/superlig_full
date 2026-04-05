<?php
include 'db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try { $pdo->exec("ALTER TABLE takimlar ADD COLUMN butce BIGINT DEFAULT 25000000"); echo "Bütçe eklendi.<br>"; } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE oyuncular ADD COLUMN deger BIGINT DEFAULT 1000000"); echo "Oyuncu değeri eklendi.<br>"; } catch (Exception $e) {}

// Oyuncuların güçlerine göre gerçekçi piyasa değeri hesaplama (FM Mantığı)
$oyuncular = $pdo->query("SELECT id, guc FROM oyuncular")->fetchAll();
foreach($oyuncular as $o) {
    $guc = $o['guc'];
    // Güç arttıkça değer katlanarak artar
    if($guc >= 85) $deger = rand(40, 80) * 1000000; // 40M - 80M Euro
    elseif($guc >= 80) $deger = rand(15, 35) * 1000000;
    elseif($guc >= 75) $deger = rand(5, 12) * 1000000;
    elseif($guc >= 70) $deger = rand(2, 4) * 1000000;
    else $deger = rand(500, 1500) * 1000; // 500B - 1.5M Euro
    
    $pdo->prepare("UPDATE oyuncular SET deger = ? WHERE id = ?")->execute([$deger, $o['id']]);
}

echo "<h3>✅ Transfer Altyapısı, Bütçeler ve Piyasa Değerleri Başarıyla Oluşturuldu! Bu dosyayı silebilirsin.</h3>";
?>