<?php
include 'db.php';
// Maçlara kart olaylarını kaydedeceğimiz sütunları ekliyoruz
$pdo->exec("ALTER TABLE maclar ADD COLUMN ev_kartlar TEXT DEFAULT NULL");
$pdo->exec("ALTER TABLE maclar ADD COLUMN dep_kartlar TEXT DEFAULT NULL");
echo "Veritabanı kart sistemi için başarıyla güncellendi!";
?>