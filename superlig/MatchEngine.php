<?php
// ==============================================================================
// CORE MATCH ENGINE V3.0 - FAZ 2: CANLI MAÇ MOTORU 2.0
// Yeni Özellikler: Hava Durumu, VAR Sistemi, Derbi Atmosferi, Kaleci Zekası
// Tüm ligler (tr, pl, cl, es, de, fr, it, pt) için ortaktır.
// ==============================================================================

class MatchEngine {
    private $pdo;
    private $prefix;

    // --- FAZ 2: DERBİ ÇİFTLERİ ---
    // Takım adlarının bir kısmı eşleşse yeterli (kısmi eşleşme kullanılır)
    private $derbiler = [
        // Türkiye
        ['Galatasaray',       'Fenerbahçe'],
        ['Galatasaray',       'Beşiktaş'],
        ['Fenerbahçe',        'Beşiktaş'],
        ['Galatasaray',       'Trabzonspor'],
        ['Fenerbahçe',        'Trabzonspor'],
        // İspanya
        ['Real Madrid',       'Barcelona'],
        ['Real Madrid',       'Atlético'],
        ['Barcelona',         'Español'],
        // İngiltere
        ['Manchester United', 'Manchester City'],
        ['Liverpool',         'Everton'],
        ['Arsenal',           'Tottenham'],
        ['Chelsea',           'Arsenal'],
        ['Liverpool',         'Manchester United'],
        // Almanya
        ['Bayern',            'Dortmund'],
        ['Schalke',           'Dortmund'],
        // İtalya
        ['Juventus',          'Inter'],
        ['AC Milan',          'Inter'],
        ['Juventus',          'AC Milan'],
        ['Roma',              'Lazio'],
        ['Napoli',            'Juventus'],
        // Fransa
        ['PSG',               'Marseille'],
        ['Lyon',              'Saint-Etienne'],
        // Portekiz
        ['Benfica',           'Porto'],
        ['Benfica',           'Sporting'],
        // Avrupa
        ['Celtic',            'Rangers'],
        ['Ajax',              'Feyenoord'],
    ];

    public function __construct($pdo, $prefix = '') {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    // --- FAZ 2: DERBİ KONTROL ---
    // Verilen iki takım id'sinin derbi olup olmadığını döndürür.
    public function is_derbi($ev_id, $dep_id) {
        $tbl = $this->prefix . 'takimlar';
        try {
            $stmt = $this->pdo->prepare("SELECT id, takim_adi FROM $tbl WHERE id IN (?, ?)");
            $stmt->execute([$ev_id, $dep_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable $e) {
            return false;
        }
        if (count($rows) < 2) return false;

        foreach ($this->derbiler as $cift) {
            $eslesen = 0;
            foreach ($rows as $takim_adi) {
                foreach ($cift as $anahtar) {
                    if (mb_stripos($takim_adi, $anahtar) !== false) {
                        $eslesen++;
                        break;
                    }
                }
            }
            if ($eslesen >= 2) return true;
        }
        return false;
    }

    // --- FAZ 2: HAVa DURUMU ATAMA ---
    // Oynanmamış bir maça rastgele hava koşulu atar ve veritabanına kaydeder.
    public function hava_ata($mac_id) {
        // Ağırlıklı dağılım: güneşli %50, yağmurlu %35, karlı %15
        $secenekler = ['Güneşli','Güneşli','Güneşli','Yağmurlu','Yağmurlu','Karlı'];
        $hava = $secenekler[array_rand($secenekler)];
        $tbl = $this->prefix . 'maclar';
        try {
            $this->pdo->prepare("UPDATE $tbl SET hava_durumu=? WHERE id=?")->execute([$hava, $mac_id]);
        } catch (Throwable $e) { /* Sütun henüz eklenmemişse sessizce geç */ }
        return $hava;
    }

    // --- 1. xG (BEKLENEN GOL) TABANLI GERÇEKÇİ SKOR HESAPLAYICI (FAZ 2 GELİŞTİRİLMİŞ) ---
    public function gercekci_skor_hesapla($ev_id, $dep_id, $mac_bilgisi = null) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        $tbl_takimlar = $this->prefix . 'takimlar';

        // 1. Sahadaki ilk 11'in form ve yorgunluğa göre anlık ortalamasını al
        $stmt = $this->pdo->prepare("SELECT AVG(ovr + (form-5) + ((fitness-100)*0.1)) FROM $tbl_oyuncular WHERE takim_id=? AND ilk_11=1");
        $stmt->execute([$ev_id]);
        $ev_ort = $stmt->fetchColumn();
        $stmt->execute([$dep_id]);
        $dep_ort = $stmt->fetchColumn();

        // 2. Takımların veritabanındaki sabit hücum/savunma güçlerini al (Hata vermemesi için motor kendi bulur)
        $stmt2 = $this->pdo->prepare("SELECT hucum, savunma FROM $tbl_takimlar WHERE id=?");
        $stmt2->execute([$ev_id]);
        $ev_takim = $stmt2->fetch(PDO::FETCH_ASSOC);
        $stmt2->execute([$dep_id]);
        $dep_takim = $stmt2->fetch(PDO::FETCH_ASSOC);

        // Yeni/eksik liglerde takım kaydı olmayabilir; varsayılan güç 70 kullan
        $ev_takim_guc  = ($ev_takim  && isset($ev_takim['hucum']))  ? (($ev_takim['hucum']  + $ev_takim['savunma'])  / 2) : 70;
        $dep_takim_guc = ($dep_takim && isset($dep_takim['hucum'])) ? (($dep_takim['hucum'] + $dep_takim['savunma']) / 2) : 70;

        // 3. Nihai Gücü Hesapla
        $ev_guc  = ($ev_ort  !== null && $ev_ort  !== false ? (float)$ev_ort  : $ev_takim_guc)  + 3; // Ev sahibi taraftar avantajı (+3)
        $dep_guc = ($dep_ort !== null && $dep_ort !== false ? (float)$dep_ort : $dep_takim_guc);

        // --- FAZ 2: DERBİ ATMOSFER SİSTEMİ ---
        // Derbi maçlarda deplasman takımı taraftar baskısından -5 moral penaltısı alır.
        if ($this->is_derbi($ev_id, $dep_id)) {
            $dep_guc = max(40, $dep_guc - 5);
        }

        // --- FAZ 2: HAVa DURUMU MODİFİKATÖRLERİ ---
        $hava = isset($mac_bilgisi['hava_durumu']) ? $mac_bilgisi['hava_durumu'] : 'Güneşli';
        $rasgellik_katsayi = 1.0; // Karlı havada artar
        if ($hava === 'Yağmurlu') {
            // Yağmurlu: kondisyon daha hızlı düşer → güç hafifçe azalır, beraberlik olasılığı artar
            $ev_guc  = max(40, $ev_guc  - 2);
            $dep_guc = max(40, $dep_guc - 2);
        } elseif ($hava === 'Karlı') {
            // Karlı: sürpriz goller artar (rastgelelik katsayısı yükselir)
            $rasgellik_katsayi = 1.8;
        }

        // 4. xG Algoritması
        $guc_farki = $ev_guc - $dep_guc;
        $ev_beklenen = max(0.1, 1.4 + ($guc_farki * 0.08));
        $dep_beklenen = max(0.1, 1.2 - ($guc_farki * 0.08));

        $ev_skor = 0; $dep_skor = 0;
        for($i=0; $i<6; $i++) { if((rand(0,100)/100) < ($ev_beklenen / 4.5)) $ev_skor++; }
        for($i=0; $i<6; $i++) { if((rand(0,100)/100) < ($dep_beklenen / 4.5)) $dep_skor++; }

        // Sürpriz/Şans Faktörü (karlı havada daha sık tetiklenir)
        $surpriz_esik = (int)(12 * $rasgellik_katsayi);
        if(rand(1, 100) <= $surpriz_esik) {
            if($ev_guc < $dep_guc) $ev_skor += rand(1, 2);
            else $dep_skor += rand(1, 2);
        }

        // MAÇ SONUCU BELLİ OLDU! ŞİMDİ OYUNCULARI GELİŞTİR/YOR/DÜŞÜR
        $this->dinamik_mac_sonu_etkisi($ev_id, $dep_id, $ev_skor, $dep_skor, $hava);

        return ['ev' => $ev_skor, 'dep' => $dep_skor];
    }

    // --- 2. DİNAMİK GELİŞİM, YAŞLANMA VE FİTNESS (FAZ 2: Hava Etkisi Dahil) ---
    private function dinamik_mac_sonu_etkisi($ev_id, $dep_id, $ev_skor, $dep_skor, $hava = 'Güneşli') {
        $tbl = $this->prefix . 'oyuncular';
        $takimlar = [
            ['id' => $ev_id, 'kazandi' => ($ev_skor > $dep_skor), 'skor' => $ev_skor],
            ['id' => $dep_id, 'kazandi' => ($dep_skor > $ev_skor), 'skor' => $dep_skor]
        ];

        foreach($takimlar as $t) {
            $oyuncular = $this->pdo->prepare("SELECT * FROM $tbl WHERE takim_id = ? AND ilk_11 = 1");
            $oyuncular->execute([$t['id']]);
            $oyuncular = $oyuncular->fetchAll(PDO::FETCH_ASSOC);

            foreach($oyuncular as $o) {
                // --- FAZ 2: Yağmurlu havada kondisyon daha hızlı düşer ---
                $ekstra_yorgunluk = ($hava === 'Yağmurlu') ? rand(3, 7) : 0;
                $yorgunluk = ($o['yas'] >= 32) ? rand(15, 22) + $ekstra_yorgunluk : rand(8, 15) + $ekstra_yorgunluk;
                $yeni_fitness = max(30, $o['fitness'] - $yorgunluk);
                
                $yeni_form = $o['form'];
                if($t['kazandi']) { $yeni_form = min(9, $yeni_form + rand(0, 1)); }
                else { $yeni_form = max(1, $yeni_form - rand(0, 1)); }

                $yeni_ovr = $o['ovr'];
                $yeni_fiyat = $o['fiyat'];

                // Gençlerin Gelişimi
                if($o['yas'] <= 23 && $t['skor'] >= 0) {
                    if(rand(1, 100) <= 8) { 
                        $yeni_ovr++;
                        $yeni_fiyat = intval($yeni_fiyat * 1.15); 
                    }
                }
                // Yaşlıların Düşüşü
                elseif($o['yas'] >= 32) {
                    if(rand(1, 100) <= 4) { 
                        $yeni_ovr--;
                        $yeni_fiyat = intval($yeni_fiyat * 0.85); 
                    }
                }

                $stmt = $this->pdo->prepare("UPDATE $tbl SET fitness=?, form=?, ovr=?, fiyat=? WHERE id=?");
                $stmt->execute([$yeni_fitness, $yeni_form, $yeni_ovr, $yeni_fiyat, $o['id']]);
            }
            // Yedekleri dinlendir
            $stmt2 = $this->pdo->prepare("UPDATE $tbl SET fitness = LEAST(100, fitness + 20) WHERE takim_id = ? AND ilk_11 = 0");
            $stmt2->execute([$t['id']]);
        }
    }

    // --- 3. AKILLI MAÇ OLAYI (GOL, KART, ASİST, VAR, KALECİ KURTARIŞI) ÜRETİCİSİ ---
    public function mac_olay_uret($takim_id, $skor) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';

        $oyuncular = $this->pdo->prepare("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=? AND ilk_11=1");
        $oyuncular->execute([$takim_id]);
        $oyuncular = $oyuncular->fetchAll(PDO::FETCH_ASSOC);
        if(!$oyuncular || count($oyuncular) == 0) {
            $stmt_all = $this->pdo->prepare("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=?");
            $stmt_all->execute([$takim_id]);
            $oyuncular = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        }
        if(!$oyuncular || count($oyuncular) == 0) { $oyuncular = [['isim' => 'Altyapı (AI)', 'mevki' => 'F']]; }

        $golculer = [];
        foreach($oyuncular as $o) {
            if($o['mevki'] == 'F') { $golculer[] = $o; $golculer[] = $o; $golculer[] = $o; }
            elseif($o['mevki'] == 'OS') { $golculer[] = $o; $golculer[] = $o; }
            else { $golculer[] = $o; }
        }

        // Kalecileri ayrıca tespit et
        $kaleciler = array_filter($oyuncular, function($o){ return $o['mevki'] == 'K'; });

        $olaylar = []; $kartlar = []; $sakatlar = [];

        for($i=0; $i<$skor; $i++) {
            $gol_atan = $golculer[array_rand($golculer)]['isim'];
            $asist_yapan = (rand(0,10) > 3) ? $oyuncular[array_rand($oyuncular)]['isim'] : '-';
            if($gol_atan == $asist_yapan) $asist_yapan = '-';
            $olaylar[] = ['tip' => 'gol', 'dakika' => rand(2, 95), 'oyuncu' => $gol_atan, 'asist' => $asist_yapan];
        }

        $kart_sayisi = rand(0, 3);
        for($i=0; $i<$kart_sayisi; $i++) {
            $kart_goren = $oyuncular[array_rand($oyuncular)]['isim'];
            $tip = (rand(0, 10) > 8) ? 'Kırmızı' : 'Sarı';
            $kartlar[] = ['tip' => 'kart', 'detay' => $tip, 'dakika' => rand(5, 90), 'oyuncu' => $kart_goren];
        }

        usort($olaylar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });
        usort($kartlar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });

        // --- FAZ 2: VAR SİSTEMİ ---
        // Her maçta %20 ihtimalle VAR olayı tetiklenir.
        // Gol iptali: en son golü sil, VAR olayı ekle.
        // Kırmızı kart: VAR sonucu kırmızı kart (kartlara eklenir).
        $var_olaylar = [];
        if (rand(1, 100) <= 20) {
            $var_dk = rand(15, 88);
            $var_tip = (rand(0, 1) == 0) ? 'var_gol_iptal' : 'var_kirmizi';

            if ($var_tip === 'var_gol_iptal' && count($olaylar) > 0) {
                // Son golü iptal et
                $iptal_gol = array_pop($olaylar);
                $var_olaylar[] = [
                    'tip'    => 'var_gol_iptal',
                    'dakika' => max($iptal_gol['dakika'] + 1, $var_dk),
                    'oyuncu' => $iptal_gol['oyuncu'],
                    'neden'  => (rand(0,1) ? 'ofsayt' : 'faul'),
                ];
            } elseif ($var_tip === 'var_kirmizi') {
                $hedef = $oyuncular[array_rand($oyuncular)]['isim'];
                $var_olaylar[] = [
                    'tip'    => 'var_kirmizi',
                    'dakika' => $var_dk,
                    'oyuncu' => $hedef,
                    'neden'  => (rand(0,1) ? 'sert_faul' : 'kol_hareketi'),
                ];
                // Kart listesine de ekle
                $kartlar[] = ['tip' => 'kart', 'detay' => 'Kırmızı', 'dakika' => $var_dk + 2, 'oyuncu' => $hedef];
            }
        }

        // --- FAZ 2: KALECİ KURTARIŞLARI ---
        // Rakip takım $skor gol attıysa, kaleci bir kısım şutu kurtardı demektir.
        // Kurtarış = (atılan toplam şut) - (yenilen goller)
        $toplam_sut   = $skor + rand(2, 6); // Maçta atılan şut sayısı tahmini
        $kurtaris_say = max(0, $toplam_sut - $skor);

        // Kalecinin DB'de kurtarış sayısını güncelle
        if (!empty($kaleciler)) {
            $kaleci = array_values($kaleciler)[0];
            try {
                $stmt_k = $this->pdo->prepare(
                    "UPDATE {$tbl_oyuncular} SET kurtaris = kurtaris + ? WHERE takim_id = ? AND mevki = 'K' AND isim = ? LIMIT 1"
                );
                $stmt_k->execute([$kurtaris_say, $takim_id, $kaleci['isim']]);
            } catch (Throwable $e) { /* kurtaris sütunu henüz yoksa sessizce geç */ }
        }

        return [
            'olaylar'     => json_encode($olaylar,     JSON_UNESCAPED_UNICODE),
            'kartlar'     => json_encode($kartlar,      JSON_UNESCAPED_UNICODE),
            'sakatlar'    => json_encode($sakatlar,     JSON_UNESCAPED_UNICODE),
            'var_olaylar' => json_encode($var_olaylar,  JSON_UNESCAPED_UNICODE),
            'kurtaris'    => $kurtaris_say,
        ];
    }

    // --- FAZ 2: ALTIN ELDİVEN - SEZON SONU ÖDÜL LOJİĞİ ---
    // Her sezon gecişinde çağrılır; tüm kaleicilerin kurtarış istatistiklerini
    // golden_glove_stats tablosuna kaydeder ve en iyisini ödüllendirir.
    public function golden_glove_guncelle($sezon_yil, $lig_kodu) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        $tbl_takimlar  = $this->prefix . 'takimlar';
        try {
            $stmt = $this->pdo->prepare(
                "SELECT o.id, o.isim, o.takim_id, o.kurtaris, t.takim_adi
                 FROM $tbl_oyuncular o
                 JOIN $tbl_takimlar t ON o.takim_id = t.id
                 WHERE o.mevki = 'K' AND o.kurtaris > 0
                 ORDER BY o.kurtaris DESC"
            );
            $stmt->execute();
            $kaleciler = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($kaleciler as $k) {
                $this->pdo->prepare(
                    "INSERT INTO golden_glove_stats
                        (oyuncu_id, oyuncu_isim, takim_id, takim_adi, lig, sezon_yil, kurtaris)
                     VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE kurtaris = kurtaris + VALUES(kurtaris)"
                )->execute([
                    $k['id'], $k['isim'], $k['takim_id'],
                    $k['takim_adi'], $lig_kodu, $sezon_yil, $k['kurtaris']
                ]);
            }

            // En fazla kurtarış yapanı ödüllendir
            if (!empty($kaleciler)) {
                $sampiyon = $kaleciler[0];
                $this->pdo->prepare(
                    "UPDATE golden_glove_stats SET odul_kazandi = 1
                     WHERE oyuncu_id = ? AND lig = ? AND sezon_yil = ?"
                )->execute([$sampiyon['id'], $lig_kodu, $sezon_yil]);
            }

            // Kurtarış sayaçlarını sıfırla (yeni sezon için)
            $this->pdo->prepare("UPDATE $tbl_oyuncular SET kurtaris = 0 WHERE mevki = 'K'")->execute();
        } catch (Throwable $e) { /* Tablo yoksa sessizce geç */ }
    }
}
?>