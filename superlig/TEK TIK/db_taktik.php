<?php
include 'db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try { $pdo->exec("ALTER TABLE ayar ADD COLUMN dizilis VARCHAR(10) DEFAULT '4-3-3'"); echo "Diziliş sütunu eklendi.<br>"; } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE oyuncular ADD COLUMN saha_pozisyon INT DEFAULT NULL"); echo "Saha pozisyonu eklendi.<br>"; } catch (Exception $e) {}

// Eski kariyer verilerini temizleyelim ki yeni sisteme temiz başlayalım
$pdo->exec("UPDATE ayar SET kullanici_takim_id = NULL WHERE id=1");
$pdo->exec("UPDATE oyuncular SET ilk11 = 0, saha_pozisyon = NULL");

echo "<h3>✅ Taktik Tahtası veritabanı hazır! Bu dosyayı silebilirsin.</h3>";
?>