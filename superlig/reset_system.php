<?php
// ==============================================================================
// TÜM SİSTEMİ SIFIRLA - Full database reset handler
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Only handle POST for security
if (!isset($_POST['reset_onayli'])) {
    header("Location: play_week.php");
    exit;
}

set_time_limit(120);

// --- Tables to reset ---
$mac_tablolari = [
    'maclar', 'pl_maclar', 'es_maclar', 'de_maclar',
    'it_maclar', 'fr_maclar', 'pt_maclar',
    'cl_maclar', 'uel_maclar', 'uecl_maclar',
    'super_cup_maclar',
    'coupe_maclar', 'taca_maclar',
];

$takim_tablolari = [
    'takimlar', 'pl_takimlar', 'es_takimlar', 'de_takimlar',
    'it_takimlar', 'fr_takimlar', 'pt_takimlar',
];

$oyuncu_tablolari = [
    'oyuncular', 'pl_oyuncular', 'es_oyuncular', 'de_oyuncular',
    'it_oyuncular', 'fr_oyuncular', 'pt_oyuncular',
];

$ayar_tablolari = [
    'ayar', 'pl_ayar', 'es_ayar', 'de_ayar',
    'it_ayar', 'fr_ayar', 'pt_ayar',
    'cl_ayar', 'uel_ayar', 'uecl_ayar',
];

$haber_tablolari = [
    'haberler', 'pl_haberler', 'es_haberler', 'de_haberler',
    'it_haberler', 'fr_haberler', 'pt_haberler',
];

// Truncate match tables
foreach ($mac_tablolari as $tbl) {
    try { $pdo->exec("TRUNCATE TABLE `$tbl`"); } catch (Throwable $e) {}
}

// Reset team standings (keep team info, zero out stats)
foreach ($takim_tablolari as $tbl) {
    try {
        $pdo->exec("UPDATE `$tbl` SET
            puan=0, galibiyet=0, beraberlik=0, malubiyet=0,
            atilan_gol=0, yenilen_gol=0");
    } catch (Throwable $e) {}
}

// Reset player season stats
foreach ($oyuncu_tablolari as $tbl) {
    try {
        $pdo->exec("UPDATE `$tbl` SET
            sezon_gol=0, sezon_asist=0,
            toplam_mac=0, toplam_gol=0,
            form=6, fitness=100, moral=80,
            ceza_hafta=0, sakatlik_hafta=0,
            mac_puani_ort=6.00");
    } catch (Throwable $e) {}
}

// Reset week counters to 1
foreach ($ayar_tablolari as $tbl) {
    try { $pdo->exec("UPDATE `$tbl` SET hafta=1"); } catch (Throwable $e) {}
}

// Clear news
foreach ($haber_tablolari as $tbl) {
    try { $pdo->exec("TRUNCATE TABLE `$tbl`"); } catch (Throwable $e) {}
}

// Clear any Avrupa tournament team tables (keep structure but clear rosters/stats)
$avrupa_takim_tblleri = ['cl_takimlar', 'uel_takimlar', 'uecl_takimlar'];
foreach ($avrupa_takim_tblleri as $tbl) {
    try { $pdo->exec("TRUNCATE TABLE `$tbl`"); } catch (Throwable $e) {}
}

// Clear session (team selection)
unset($_SESSION['kullanici_takim_id']);
unset($_SESSION['kullanici_takim_tablo']);
unset($_SESSION['play_week_sonuc']);

// Reset kullanici_takim_id in main ayar table
try { $pdo->exec("UPDATE ayar SET kullanici_takim_id=NULL WHERE id=1"); } catch (Throwable $e) {}

// Redirect to home with success message
$_SESSION['reset_success'] = 1;
header("Location: index.php");
exit;
