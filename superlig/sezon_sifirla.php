<?php
// ==============================================================================
// SEZON SIFIRLA - Per-league season reset handler
// Supports two reset modes:
//   'bu_sezon'   - Reset only current season (fixtures, standings, results)
//   'tum_sezon'  - Total factory reset for the league (history + current)
// Usage: POST with 'lig', 'mod', 'onay' parameters
// ==============================================================================
if (session_status() === PHP_SESSION_NONE) session_start();
include 'db.php';

// Security: only handle confirmed POST
if (!isset($_POST['onay']) || $_POST['onay'] !== '1') {
    header("Location: play_week.php");
    exit;
}

$mod = $_POST['mod'] ?? '';
$lig = $_POST['lig'] ?? '';

// Validate allowed league codes against known leagues
$lig_config = [
    'superlig'        => ['prefix' => '',     'maclar' => 'maclar',     'takimlar' => 'takimlar',     'ayar' => 'ayar',     'oyuncular' => 'oyuncular',     'haberler' => 'haberler',     'ad' => 'Süper Lig'],
    'premier_league'  => ['prefix' => 'pl_',  'maclar' => 'pl_maclar',  'takimlar' => 'pl_takimlar',  'ayar' => 'pl_ayar',  'oyuncular' => 'pl_oyuncular',  'haberler' => 'pl_haberler',  'ad' => 'Premier League'],
    'la_liga'         => ['prefix' => 'es_',  'maclar' => 'es_maclar',  'takimlar' => 'es_takimlar',  'ayar' => 'es_ayar',  'oyuncular' => 'es_oyuncular',  'haberler' => 'es_haberler',  'ad' => 'La Liga'],
    'bundesliga'      => ['prefix' => 'de_',  'maclar' => 'de_maclar',  'takimlar' => 'de_takimlar',  'ayar' => 'de_ayar',  'oyuncular' => 'de_oyuncular',  'haberler' => 'de_haberler',  'ad' => 'Bundesliga'],
    'serie_a'         => ['prefix' => 'it_',  'maclar' => 'it_maclar',  'takimlar' => 'it_takimlar',  'ayar' => 'it_ayar',  'oyuncular' => 'it_oyuncular',  'haberler' => 'it_haberler',  'ad' => 'Serie A'],
    'ligue1'          => ['prefix' => 'fr_',  'maclar' => 'fr_maclar',  'takimlar' => 'fr_takimlar',  'ayar' => 'fr_ayar',  'oyuncular' => 'fr_oyuncular',  'haberler' => 'fr_haberler',  'ad' => 'Ligue 1'],
    'liga_nos'        => ['prefix' => 'pt_',  'maclar' => 'pt_maclar',  'takimlar' => 'pt_takimlar',  'ayar' => 'pt_ayar',  'oyuncular' => 'pt_oyuncular',  'haberler' => 'pt_haberler',  'ad' => 'Liga NOS'],
];

if (!array_key_exists($lig, $lig_config) || !in_array($mod, ['bu_sezon', 'tum_sezon'], true)) {
    header("Location: play_week.php");
    exit;
}

$cfg   = $lig_config[$lig];
$mesaj = '';

set_time_limit(60);

if ($mod === 'bu_sezon') {
    // -----------------------------------------------------------------------
    // BU SEZONU SIFIRLA
    // Sadece bu sezonun fikstürünü, puan tablosunu ve maç sonuçlarını sil.
    // Geçmiş sezon geçmişi ve şampiyonlar korunur.
    // -----------------------------------------------------------------------
    try {
        $pdo->exec("TRUNCATE TABLE `{$cfg['maclar']}`");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['takimlar']}` SET
            puan=0, galibiyet=0, beraberlik=0, malubiyet=0,
            atilan_gol=0, yenilen_gol=0");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['oyuncular']}` SET
            sezon_gol=0, sezon_asist=0,
            form=6, fitness=100, moral=80,
            ceza_hafta=0, sakatlik_hafta=0,
            mac_puani_ort=6.00");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['ayar']}` SET hafta=1");
    } catch (Throwable $e) {}

    $mesaj = "✅ <strong>{$cfg['ad']}</strong> — Bu sezon sıfırlandı. Fikstür, puan tablosu ve maç sonuçları temizlendi. Geçmiş sezon şampiyonları korundu.";

} elseif ($mod === 'tum_sezon') {
    // -----------------------------------------------------------------------
    // TÜM SEZONLARI SIFIRLA
    // Ligin tüm geçmişini, eski şampiyonları ve mevcut verileri sil.
    // -----------------------------------------------------------------------
    try {
        $pdo->exec("TRUNCATE TABLE `{$cfg['maclar']}`");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['takimlar']}` SET
            puan=0, galibiyet=0, beraberlik=0, malubiyet=0,
            atilan_gol=0, yenilen_gol=0");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['oyuncular']}` SET
            sezon_gol=0, sezon_asist=0,
            toplam_mac=0, toplam_gol=0,
            form=6, fitness=100, moral=80,
            ceza_hafta=0, sakatlik_hafta=0,
            mac_puani_ort=6.00");
    } catch (Throwable $e) {}

    try {
        $pdo->exec("UPDATE `{$cfg['ayar']}` SET hafta=1, sezon_yil=2025, gecen_sezon_sampiyon=NULL");
    } catch (Throwable $e) {
        try {
            $pdo->exec("UPDATE `{$cfg['ayar']}` SET hafta=1, sezon_yil=2025");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->exec("TRUNCATE TABLE `{$cfg['haberler']}`");
    } catch (Throwable $e) {}

    $mesaj = "🔄 <strong>{$cfg['ad']}</strong> — Tüm sezonlar sıfırlandı. Lig geçmişi, şampiyon kayıtları ve tüm veriler silindi. Fabrika ayarlarına döndürüldü.";
}

$_SESSION['sezon_sifirla_mesaj'] = $mesaj;
header("Location: play_week.php?sifirla=1");
exit;
