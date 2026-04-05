<?php
include 'db.php';
$pdo->exec("ALTER TABLE oyuncular ADD COLUMN yuz_url VARCHAR(255) DEFAULT 'https://cdn.sofifa.net/player_0.svg'");
echo "Yüz sütunu eklendi!";
?>