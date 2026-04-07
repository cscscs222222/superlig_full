<?php
// ==============================================================================
// LIGA NOS - TRANSFER BORSASI (GREEN & RED PORTUGUESE THEME)
// ==============================================================================
include '../db.php';

$ayar = $pdo->query("SELECT * FROM pt_ayar LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$kullanici_takim_id = $ayar['kullanici_takim_id'] ?? null;
$hafta = $ayar['hafta'] ?? 1;

if (!$kullanici_takim_id) { header("Location: liga_nos.php"); exit; }

$benim_takim = $pdo->query("SELECT * FROM pt_takimlar WHERE id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
$mesaj = "";
$mesaj_tipi = "";

// SATIN ALMA
if (isset($_POST['satin_al'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $hedef_oyuncu = $pdo->query("SELECT * FROM pt_oyuncular WHERE id = $oyuncu_id")->fetch(PDO::FETCH_ASSOC);
    if ($hedef_oyuncu) {
        $fiyat = $hedef_oyuncu['fiyat'];
        $eski_takim_id = $hedef_oyuncu['takim_id'];
        if ($benim_takim['butce'] >= $fiyat) {
            $pdo->exec("UPDATE pt_takimlar SET butce = butce - $fiyat WHERE id = $kullanici_takim_id");
            $pdo->exec("UPDATE pt_takimlar SET butce = butce + $fiyat WHERE id = $eski_takim_id");
            $pdo->exec("UPDATE pt_oyuncular SET takim_id = $kullanici_takim_id, ilk_11 = 0, yedek = 1 WHERE id = $oyuncu_id");
            $fiyat_milyon = number_format($fiyat/1000000, 1);
            $haber_metni = "PORTEKİZ FUTBOLUNDA TRANSFER! " . $benim_takim['takim_adi'] . " " . $hedef_oyuncu['isim'] . "'i €{$fiyat_milyon}M'e transfer etti.";
            try { $pdo->exec("INSERT INTO pt_haberler (hafta, metin, tip) VALUES ($hafta, " . $pdo->quote($haber_metni) . ", 'transfer')"); } catch(Throwable $e){}
            $mesaj = "İmza Atıldı! " . $hedef_oyuncu['isim'] . " artık Portekiz'de takımımızda.";
            $mesaj_tipi = "success";
            $benim_takim['butce'] -= $fiyat;
        } else {
            $mesaj = "Finansal Uyarı! Bu transfer için Portekiz kasamızda yeterli bütçe yok.";
            $mesaj_tipi = "danger";
        }
    }
}

// SATIŞ
if (isset($_POST['sat'])) {
    $oyuncu_id = (int)$_POST['oyuncu_id'];
    $benim_oyuncu = $pdo->query("SELECT * FROM pt_oyuncular WHERE id = $oyuncu_id AND takim_id = $kullanici_takim_id")->fetch(PDO::FETCH_ASSOC);
    if ($benim_oyuncu) {
        $fiyat = $benim_oyuncu['fiyat'];
        $ai_takim = $pdo->query("SELECT id FROM pt_takimlar WHERE id != $kullanici_takim_id ORDER BY RAND() LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $yeni_takim_id = $ai_takim['id'];
        $pdo->exec("UPDATE pt_takimlar SET butce = butce + $fiyat WHERE id = $kullanici_takim_id");
        $pdo->exec("UPDATE pt_takimlar SET butce = GREATEST(0, butce - $fiyat) WHERE id = $yeni_takim_id");
        $pdo->exec("UPDATE pt_oyuncular SET takim_id = $yeni_takim_id, ilk_11 = 0, yedek = 1 WHERE id = $oyuncu_id");
        $mesaj = $benim_oyuncu['isim'] . " satıldı. Kasamıza " . number_format($fiyat/1000000, 1) . " Milyon Euro girdi.";
        $mesaj_tipi = "success";
        $benim_takim['butce'] += $fiyat;
    }
}

// ARAMA
$arama = trim($_GET['q'] ?? '');
$mevki_filtre = $_GET['mevki'] ?? '';
$where = "takim_id != $kullanici_takim_id";
if ($arama) { $safe_arama = $pdo->quote("%$arama%"); $where .= " AND isim LIKE $safe_arama"; }
if ($mevki_filtre) { $safe_mevki = $pdo->quote($mevki_filtre); $where .= " AND mevki = $safe_mevki"; }

$pazar_oyuncular = $pdo->query("SELECT o.*, t.takim_adi FROM pt_oyuncular o JOIN pt_takimlar t ON o.takim_id=t.id WHERE $where ORDER BY ovr DESC LIMIT 60")->fetchAll(PDO::FETCH_ASSOC);
$benim_kadrom = $pdo->query("SELECT * FROM pt_oyuncular WHERE takim_id = $kullanici_takim_id ORDER BY ovr DESC")->fetchAll(PDO::FETCH_ASSOC);

function paraLn($sayi) {
    if($sayi >= 1000000) return "€" . number_format($sayi/1000000, 1) . "M";
    if($sayi >= 1000) return "€" . number_format($sayi/1000, 1) . "K";
    return "€" . $sayi;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Borsası | Liga NOS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Oswald:wght@500;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pt-primary:#006600; --pt-secondary:#cc0000; --pt-gold:#d4af37; --bg:#0d0d0d; --panel:#1a1a1a; --border:rgba(0,102,0,0.25); --text:#f9fafb; --muted:#94a3b8; }
        body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; }
        .font-oswald { font-family:'Oswald',sans-serif; text-transform:uppercase; }
        .pro-navbar { background:rgba(10,10,10,0.97); backdrop-filter:blur(24px); border-bottom:2px solid var(--pt-secondary); position:sticky; top:0; z-index:1000; padding:0 2rem; height:75px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:10px; font-size:1.4rem; font-weight:900; color:#fff; text-decoration:none; }
        .nav-brand i { color:var(--pt-secondary); }
        .nav-link-item { color:var(--muted); font-weight:600; font-size:0.95rem; padding:8px 16px; text-decoration:none; transition:0.2s; }
        .nav-link-item:hover { color:#fff; }
        .btn-ap { background:var(--pt-primary); color:#fff; font-weight:800; padding:8px 20px; border-radius:4px; text-decoration:none; border:none; transition:0.3s; cursor:pointer; }
        .btn-ap:hover { background:var(--pt-secondary); color:#fff; }
        .panel-card { background:var(--panel); border:1px solid var(--border); border-radius:12px; overflow:hidden; margin-bottom:24px; }
        .panel-header { padding:1rem 1.5rem; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.3); }
        .panel-header h5 { color:var(--pt-gold); margin:0; font-family:'Oswald',sans-serif; font-size:1rem; text-transform:uppercase; }
        .player-row { display:flex; align-items:center; gap:12px; padding:10px 16px; border-bottom:1px solid rgba(255,255,255,0.04); }
        .player-row:hover { background:rgba(0,102,0,0.07); }
        .ovr-badge { width:38px; height:38px; border-radius:8px; background:var(--pt-primary); color:#fff; display:flex; align-items:center; justify-content:center; font-family:'Oswald',sans-serif; font-size:1.1rem; font-weight:900; flex-shrink:0; }
        .pos-badge { font-size:0.7rem; font-weight:800; padding:3px 8px; border-radius:4px; background:rgba(204,0,0,0.2); color:var(--pt-secondary); border:1px solid rgba(204,0,0,0.3); }
        .player-name { flex:1; font-weight:700; color:#fff; font-size:0.9rem; }
        .player-club { font-size:0.78rem; color:var(--muted); }
        .player-price { font-family:'Oswald',sans-serif; color:var(--pt-gold); font-weight:700; white-space:nowrap; }
        .budget-bar { background:rgba(0,102,0,0.2); border:1px solid rgba(0,102,0,0.3); border-radius:10px; padding:14px 20px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
        .search-input { background:rgba(255,255,255,0.07); color:#fff; border:1px solid var(--border); border-radius:8px; padding:8px 14px; width:100%; }
        .search-input::placeholder { color:var(--muted); }
        .filter-btn { background:rgba(255,255,255,0.07); color:#fff; border:1px solid var(--border); border-radius:6px; padding:6px 12px; font-size:0.8rem; text-decoration:none; transition:0.2s; }
        .filter-btn:hover,.filter-btn.active { background:var(--pt-primary); color:#fff; border-color:var(--pt-primary); }
        .btn-buy { background:rgba(0,102,0,0.3); color:#4ade80; border:1px solid rgba(74,222,128,0.3); border-radius:6px; padding:5px 12px; font-size:0.75rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .btn-buy:hover { background:var(--pt-primary); color:#fff; }
        .btn-sell { background:rgba(204,0,0,0.2); color:var(--pt-secondary); border:1px solid rgba(204,0,0,0.3); border-radius:6px; padding:5px 12px; font-size:0.75rem; font-weight:700; cursor:pointer; transition:0.2s; }
        .btn-sell:hover { background:var(--pt-secondary); color:#fff; }
    </style>
</head>
<body>
<nav class="pro-navbar">
    <a href="liga_nos.php" class="nav-brand font-oswald"><i class="fa-solid fa-star"></i> LIGA NOS</a>
    <div class="d-none d-lg-flex gap-2">
        <a href="../index.php" class="nav-link-item"><i class="fa-solid fa-house"></i> Merkez</a>
        <a href="liga_nos.php" class="nav-link-item"><i class="fa-solid fa-tv"></i> Fikstür</a>
        <a href="ln_kadro.php" class="nav-link-item"><i class="fa-solid fa-users"></i> Kadro</a>
        <a href="ln_transfer.php" class="nav-link-item" style="color:#fff;"><i class="fa-solid fa-comments-dollar"></i> Transfer</a>
        <a href="ln_puan.php" class="nav-link-item"><i class="fa-solid fa-chart-bar"></i> Puan</a>
        <a href="ln_basin.php" class="nav-link-item"><i class="fa-solid fa-microphone"></i> Basın</a>
    </div>
    <a href="liga_nos.php" class="btn-ap"><i class="fa-solid fa-arrow-left"></i> Fikstüre Dön</a>
</nav>

<div class="container py-4" style="max-width:1300px;">

    <?php if($mesaj): ?>
    <div class="alert alert-<?=$mesaj_tipi?> alert-dismissible fade show"><i class="fa-solid fa-<?=$mesaj_tipi=='success'?'check':'exclamation-triangle'?> me-2"></i><?=$mesaj?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="budget-bar">
        <div>
            <div style="font-size:0.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">Transfer Bütçesi</div>
            <div class="font-oswald" style="font-size:1.8rem;color:var(--pt-gold);"><?=paraLn($benim_takim['butce']??0)?></div>
        </div>
        <div class="text-end">
            <div style="font-size:0.75rem;color:var(--muted);">Yönettiğin Takım</div>
            <div style="font-weight:800;color:#fff;"><?=htmlspecialchars($benim_takim['takim_adi'])?></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="panel-card">
                <div class="panel-header d-flex justify-content-between align-items-center">
                    <h5><i class="fa-solid fa-store me-2"></i>Portekiz Transfer Pazarı</h5>
                </div>
                <div class="p-3">
                    <form method="GET" class="d-flex gap-2 mb-3 flex-wrap">
                        <input type="text" name="q" value="<?=htmlspecialchars($arama)?>" class="search-input flex-grow-1" placeholder="Oyuncu adı ara...">
                        <?php foreach(['','K','D','OS','F'] as $mv): ?>
                        <a href="?mevki=<?=$mv?>&q=<?=htmlspecialchars($arama)?>" class="filter-btn<?=$mevki_filtre==$mv?' active':''?>"><?=$mv?:' Hepsi'?></a>
                        <?php endforeach; ?>
                        <button type="submit" class="btn-ap" style="padding:6px 14px;">Ara</button>
                    </form>
                    <?php foreach($pazar_oyuncular as $o): ?>
                    <div class="player-row">
                        <div class="ovr-badge"><?=$o['ovr']?></div>
                        <span class="pos-badge"><?=$o['mevki']?></span>
                        <div>
                            <div class="player-name"><?=htmlspecialchars($o['isim'])?></div>
                            <div class="player-club"><?=htmlspecialchars($o['takim_adi'])?> &bull; <?=$o['yas']?> yaş</div>
                        </div>
                        <div class="player-price"><?=paraLn($o['fiyat'])?></div>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="oyuncu_id" value="<?=$o['id']?>">
                            <button type="submit" name="satin_al" class="btn-buy"
                                onclick="return confirm('<?=htmlspecialchars($o['isim'])?> için <?=paraLn($o['fiyat'])?> ödenecek. Onaylıyor musunuz?')">
                                Satın Al
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                    <?php if(empty($pazar_oyuncular)): ?>
                    <div class="p-4 text-center" style="color:var(--muted);">Oyuncu bulunamadı.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="panel-card">
                <div class="panel-header"><h5><i class="fa-solid fa-users me-2"></i>Benim Kadrom (<?=count($benim_kadrom)?>)</h5></div>
                <?php foreach($benim_kadrom as $o): ?>
                <div class="player-row">
                    <div class="ovr-badge" style="background:#5b3e00;"><?=$o['ovr']?></div>
                    <span class="pos-badge"><?=$o['mevki']?></span>
                    <div>
                        <div class="player-name"><?=htmlspecialchars($o['isim'])?></div>
                        <div class="player-club"><?=$o['yas']?> yaş</div>
                    </div>
                    <div class="player-price" style="color:#4ade80;"><?=paraLn($o['fiyat'])?></div>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="oyuncu_id" value="<?=$o['id']?>">
                        <button type="submit" name="sat" class="btn-sell"
                            onclick="return confirm('<?=htmlspecialchars($o['isim'])?> için <?=paraLn($o['fiyat'])?> alacaksınız. Onaylıyor musunuz?')">
                            Sat
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php if(empty($benim_kadrom)): ?>
                <div class="p-4 text-center" style="color:var(--muted);">Kadronuzda oyuncu yok.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
