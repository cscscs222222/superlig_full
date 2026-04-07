<?php
// ==============================================================================
// CORE MATCH ENGINE V4.0 - FAZ 3: OYUNCU PSİKOLOJİSİ VE YAPAY ZEKA
// Yeni Özellikler: PlayStyles, Oyuncu Kimyası, Derin Sakatlık
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

        // --- FAZ 3: OYUNCU KİMYASI ---
        // Aynı uyruklu/ligden oyuncular takıma bonus OVR katar
        $ev_kimya  = $this->kimya_hesapla($ev_id);
        $dep_kimya = $this->kimya_hesapla($dep_id);

        // 3. Nihai Gücü Hesapla
        $ev_guc  = ($ev_ort  !== null && $ev_ort  !== false ? (float)$ev_ort  : $ev_takim_guc)  + 3 + $ev_kimya['bonus']; // Ev sahibi taraftar avantajı (+3)
        $dep_guc = ($dep_ort !== null && $dep_ort !== false ? (float)$dep_ort : $dep_takim_guc) + $dep_kimya['bonus'];

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

                // --- FAZ 3: DERİN SAKATLIK SİSTEMİ ---
                // Her maçta %8 sakatlık ihtimali; yağmurda %12'ye çıkar
                $sakatlik_esik = ($hava === 'Yağmurlu') ? 12 : 8;
                if (rand(1, 100) <= $sakatlik_esik && ($o['sakatlik_hafta'] ?? 0) == 0) {
                    // Takımın Sağlık Merkezi seviyesini bul
                    $smtbl = $this->prefix . 'takimlar';
                    try {
                        $sm_stmt = $this->pdo->prepare("SELECT saglik_merkezi_seviye FROM $smtbl WHERE id=? LIMIT 1");
                        $sm_stmt->execute([$t['id']]);
                        $sm_seviye = (int)($sm_stmt->fetchColumn() ?: 1);
                    } catch (Throwable $e) { $sm_seviye = 1; }

                    $sakatlik = $this->derin_sakatlik_uret($o['yas'], $sm_seviye);
                    try {
                        $this->pdo->prepare(
                            "UPDATE $tbl SET sakatlik_hafta=?, sakatlik_turu=?, ilk_11=0, yedek=1 WHERE id=?"
                        )->execute([$sakatlik['hafta'], $sakatlik['tur'], $o['id']]);
                    } catch (Throwable $e) {
                        // sakatlik_turu sütunu yoksa sadece hafta güncelle
                        try {
                            $this->pdo->prepare("UPDATE $tbl SET sakatlik_hafta=?, ilk_11=0, yedek=1 WHERE id=?")
                                ->execute([$sakatlik['hafta'], $o['id']]);
                        } catch (Throwable $e2) {}
                    }
                }
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

        // --- FAZ 3: PLAY STYLES - FRİKİK USTASI ---
        // Eğer takımda Frikik Ustası varsa, serbest vuruş golü üret
        $this->playstyle_serbest_vurus($takim_id, $olaylar);

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

    // =========================================================================
    // FAZ 3: OYUNCU KİMYASI (PLAYER CHEMISTRY)
    // =========================================================================
    // Aynı uyruklu veya aynı ligden oyuncular için bonus OVR hesaplar.
    // Başlangıç XI'ndeki oyunculara bakarak takım performansına +5 OVR ekler.
    // =========================================================================
    public function kimya_hesapla($takim_id) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        try {
            $stmt = $this->pdo->prepare(
                "SELECT ulke, lig FROM $tbl_oyuncular WHERE takim_id=? AND ilk_11=1"
            );
            $stmt->execute([$takim_id]);
            $oyuncular = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return ['bonus' => 0, 'aciklama' => ''];
        }

        if (count($oyuncular) < 2) return ['bonus' => 0, 'aciklama' => ''];

        $ulkeler = []; $ligler = [];
        foreach ($oyuncular as $o) {
            if (!empty($o['ulke'])) $ulkeler[] = $o['ulke'];
            if (!empty($o['lig']))  $ligler[]  = $o['lig'];
        }

        $bonus     = 0;
        $aciklama  = '';

        // Aynı ulkeden en az 2 oyuncu → +5 OVR
        $ulke_sayim = array_count_values($ulkeler);
        arsort($ulke_sayim);
        foreach ($ulke_sayim as $ulke => $sayi) {
            if ($sayi >= 2) {
                $bonus += 5;
                $aciklama .= "$sayi $ulke oyuncusu kimya yarattı (+5 OVR). ";
                break; // Tek bonus yeterli
            }
        }

        // Aynı ligden en az 3 oyuncu → ek +5 OVR
        $lig_sayim = array_count_values($ligler);
        arsort($lig_sayim);
        foreach ($lig_sayim as $lig => $sayi) {
            if ($sayi >= 3) {
                $bonus += 5;
                $aciklama .= "$sayi oyuncu aynı lig geçmişiyle kimya katkısı (+5 OVR). ";
                break;
            }
        }

        return ['bonus' => $bonus, 'aciklama' => trim($aciklama)];
    }

    // =========================================================================
    // FAZ 3: DERİN SAKATLIK SİSTEMİ
    // =========================================================================
    // Sakatlık olduğunda basit "X hafta" yerine gerçekçi bir teşhis üretir.
    // Sağlık Merkezi seviyesi iyileşme süresini kısaltır.
    // =========================================================================
    public function derin_sakatlik_uret($oyuncu_yas, $saglik_merkezi_seviye = 1) {
        // Olası sakatlık türleri: [ad, hafta_min, hafta_max]
        $sakatlıklar_listesi = [
            // Hafif (1-2 hafta)
            ['Kas Krampı',               1,  2],
            ['Ayak Bileği Burkması',     1,  3],
            ['Hafif Kas Çekmesi',        1,  2],
            // Orta (3-8 hafta)
            ['Arka Adale Çekmesi',       3,  5],
            ['Hamstring Zorlanması',     3,  6],
            ['Kasık Ağrısı',             2,  4],
            ['Diz Şişliği',              4,  8],
            ['Köprücük Kemiği Kırığı',   6,  8],
            // Ağır (10+ hafta)
            ['Menisküs Yırtığı',        10, 16],
            ['Aşil Tendonu Yırtığı',    16, 24],
            ['Çapraz Bağ Kopması',      20, 26],
        ];

        // Yaşlı oyuncularda ağır sakatlık ihtimali artar
        if ($oyuncu_yas >= 32) {
            // Ağır sakatlıklar listede ağırlıklı
            $weights = [1,1,1, 2,2,2,2,2, 3,3,3];
        } else {
            $weights = [3,3,3, 2,2,2,2,2, 1,1,1];
        }

        // Ağırlıklı rastgele seçim
        $toplam = array_sum($weights);
        $rastgele = rand(1, $toplam);
        $kumulatif = 0;
        $secilen = $sakatlıklar_listesi[0];
        foreach ($sakatlıklar_listesi as $i => $s) {
            $kumulatif += $weights[$i];
            if ($rastgele <= $kumulatif) {
                $secilen = $s;
                break;
            }
        }

        // Sağlık Merkezi indirimi: her seviye %5 süre kısaltır (max %45)
        $indirim_oran = min(0.45, ($saglik_merkezi_seviye - 1) * 0.05);
        $hafta_min = (int)ceil($secilen[1] * (1 - $indirim_oran));
        $hafta_max = (int)ceil($secilen[2] * (1 - $indirim_oran));
        $hafta = rand(max(1, $hafta_min), max(1, $hafta_max));

        return [
            'tur'   => $secilen[0],
            'hafta' => $hafta,
            'etiket' => $secilen[0] . ' (' . $hafta . ' Hafta)',
        ];
    }

    // =========================================================================
    // FAZ 3: PLAY STYLES ENTEGRASYONU (SERBEST VUR / HIZLI / KASAP)
    // =========================================================================
    // Serbest vuruş kazanıldığında "Frikik Ustası" rozetiyle gol ihtimali artar.
    // Hız canavarı olan oyuncular kontra atakta gol üretir.
    // =========================================================================
    public function playstyle_serbest_vurus($takim_id, &$olaylar) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        try {
            // Frikik Ustası rozetine sahip oyuncular
            $stmt = $this->pdo->prepare(
                "SELECT isim FROM $tbl_oyuncular
                 WHERE takim_id=? AND ilk_11=1 AND play_styles LIKE '%Frikik Ustası%'"
            );
            $stmt->execute([$takim_id]);
            $ustalar = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return;
        }

        if (empty($ustalar)) return;

        // %35 ihtimalle Frikik Ustası gol atar
        if (rand(1, 100) <= 35) {
            $usta = $ustalar[array_rand($ustalar)];
            $olaylar[] = [
                'tip'     => 'gol',
                'dakika'  => rand(15, 88),
                'oyuncu'  => $usta,
                'asist'   => '-',
                'ozel'    => '⚡ Frikik Ustası Rozeti ile İnanılmaz Frikik Golü!',
            ];
        }
    }

    // =========================================================================
    // FAZ 3: MEDYA SIZINTISI KONTROLÜ
    // =========================================================================
    // Yüksek OVR'li (>=75) yıldızlar yedekte kalırsa takım moralini düşürür.
    // Bu metot sezon geçişinden veya superlig.php'den çağrılabilir.
    // =========================================================================
    public function medya_sizintisi_kontrol($takim_id, $hafta, $sezon_yil) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        $tbl_takimlar  = $this->prefix . 'takimlar';

        try {
            // OVR>=75, yedekte (yedek=1, ilk_11=0) ve morali düşmüş oyuncular
            $stmt = $this->pdo->prepare(
                "SELECT id, isim, ovr, moral FROM $tbl_oyuncular
                 WHERE takim_id=? AND ilk_11=0 AND yedek=1 AND ovr>=75 AND moral < 40"
            );
            $stmt->execute([$takim_id]);
            $yildizlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }

        if (empty($yildizlar)) return null;

        // En mutsuz yıldızı seç
        usort($yildizlar, fn($a, $b) => $a['moral'] <=> $b['moral']);
        $yildiz = $yildizlar[0];

        // Takımın form / moralini -10 düşür
        try {
            $this->pdo->prepare(
                "UPDATE $tbl_oyuncular SET moral = GREATEST(10, moral - 10) WHERE takim_id=?"
            )->execute([$takim_id]);

            // Log kaydet
            $this->pdo->prepare(
                "INSERT IGNORE INTO medya_sizinti_log
                    (sezon_yil, hafta, takim_id, oyuncu_id, oyuncu_isim, ovr, etki)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $sezon_yil, $hafta, $takim_id,
                $yildiz['id'], $yildiz['isim'], $yildiz['ovr'],
                'Takım moralini -10 düşürdü. Kadro dışı seçeneği değerlendirin.'
            ]);
        } catch (Throwable $e) { /* Sessizce geç */ }

        return $yildiz;
    }

    // =========================================================================
    // FAZ 3: EMEKLİLİK VE REGEN SİSTEMİ
    // =========================================================================
    // 35+ yaşındaki oyuncular sezon sonunda emekliye ayrılabilir.
    // Emekli olan yıldız (OVR>=75) için 16-17 yaşında Regen üretilir.
    // =========================================================================
    public function emeklilik_ve_regen($sezon_yil, $lig = 'Süper Lig') {
        $tbl_oyuncular = $this->prefix . 'oyuncular';

        // Emeklilik Adayları: yaş >= 35
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM $tbl_oyuncular WHERE yas >= 35 ORDER BY yas DESC"
            );
            $stmt->execute();
            $adaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        $emekliler = [];
        foreach ($adaylar as $o) {
            // Emeklilik şansı: yaş 35→%30, 36→%50, 37→%70, 38→%100
            $sans = min(100, ($o['yas'] - 34) * 30);
            if (rand(1, 100) > $sans) continue;

            $emekliler[] = $o;

            // Oyuncuyu veritabanından kaldır (emekli)
            try {
                $this->pdo->prepare("DELETE FROM $tbl_oyuncular WHERE id=?")->execute([$o['id']]);
            } catch (Throwable $e) {}

            // Yıldız emekliyse (OVR>=75) → Regen üret
            if ($o['ovr'] >= 75) {
                $this->regen_uret($o, $sezon_yil, $lig);
            }
        }

        return $emekliler;
    }

    private function regen_uret($emekli, $sezon_yil, $lig) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';

        // Regen adı (emeklinin ülkesine göre)
        $ulke = $emekli['ulke'] ?? 'Türkiye';
        $isim_havuzu = $this->regen_isim_havuzu($ulke);
        $yeni_isim = $isim_havuzu['isim'][array_rand($isim_havuzu['isim'])]
                   . ' '
                   . $isim_havuzu['soyad'][array_rand($isim_havuzu['soyad'])];

        // Regen yaşı 16-17
        $regen_yas = rand(16, 17);
        // OVR: emeklinin OVR * 0.8 civarında başlar (potansiyel yüksek)
        $regen_ovr        = max(55, (int)($emekli['ovr'] * 0.8));
        $regen_potansiyel = min(95, $emekli['ovr'] + rand(0, 5));
        $regen_fiyat      = ($regen_ovr * $regen_ovr) * 1200;

        // Regen'ı boş ajanlar havuzuna ekle (takim_id=0) veya emeklinin eski takımına
        $takim_id = $emekli['takim_id'] ?? 0;

        try {
            $this->pdo->prepare(
                "INSERT INTO $tbl_oyuncular
                    (takim_id, isim, mevki, ovr, yas, fiyat, lig, ilk_11, yedek, ulke, play_styles)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, NULL)"
            )->execute([
                $takim_id, $yeni_isim, $emekli['mevki'],
                $regen_ovr, $regen_yas, $regen_fiyat,
                $lig, $ulke
            ]);

            // Log kaydet
            $this->pdo->prepare(
                "INSERT INTO regen_log
                    (sezon_yil, lig, emekli_isim, emekli_ovr, emekli_ulke,
                     regen_isim, regen_yas, regen_ovr, regen_potansiyel)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $sezon_yil, $lig,
                $emekli['isim'], $emekli['ovr'], $ulke,
                $yeni_isim, $regen_yas, $regen_ovr, $regen_potansiyel,
            ]);
        } catch (Throwable $e) { /* Sessizce geç */ }
    }

    private function regen_isim_havuzu($ulke) {
        $havuzlar = [
            'Türkiye'   => ['isim' => ['Kerem','Arda','Cengiz','Orkun','Ozan','Emirhan','Ferdi','Halil','Barış','Uğur'],
                            'soyad'=> ['Aktürkoğlu','Güler','Ünder','Kökçü','Kabak','Demir','Yılmaz','Kaya','Çelik','Doğan']],
            'Brezilya'  => ['isim' => ['Gabriel','Rodrygo','Endrick','Lucas','Vini','Matheus','Igor','Pedro','Kauan','Felipe'],
                            'soyad'=> ['Santos','Silva','Pereira','Oliveira','Costa','Ferreira','Souza','Rodrigues','Lima','Alves']],
            'Arjantin'  => ['isim' => ['Lautaro','Julian','Enzo','Thiago','Alejandro','Franco','Matias','Nahuel','Santiago','Rodrigo'],
                            'soyad'=> ['Martinez','Alvarez','Fernandez','Moreno','Gomez','Lopez','Sanchez','Romero','Torres','Diaz']],
            'İspanya'   => ['isim' => ['Pedri','Gavi','Yamal','Fermin','Pau','Marc','Javi','Alejandro','Nico','Brahim'],
                            'soyad'=> ['Garcia','Martinez','Lopez','Sanchez','Fernandez','Gonzalez','Rodriguez','Perez','Torres','Ruiz']],
            'Fransa'    => ['isim' => ['Warren','Bradley','Mathys','Ilyes','Loris','Tom','Noah','Hugo','Théo','Axel'],
                            'soyad'=> ['Zaïre-Emery','Barcola','Tel','Camavinga','Tchouameni','Dembélé','Henry','Giroud','Martin','Dupont']],
            'Almanya'   => ['isim' => ['Florian','Jamal','Xavi','Felix','Leroy','Jonas','Kai','Niclas','Timo','Anton'],
                            'soyad'=> ['Wirtz','Musiala','Simons','Nmecha','Sane','Hofmann','Havertz','Fullkrug','Werner','Anton']],
            'İtalya'    => ['isim' => ['Lorenzo','Sandro','Davide','Nicolò','Manuel','Giacomo','Giovanni','Federico','Matteo','Samuele'],
                            'soyad'=> ['Pellegrini','Tonali','Frattesi','Barella','Locatelli','Raspadori','Di Lorenzo','Chiesa','Gatti','Ricci']],
            'İngiltere' => ['isim' => ['Jude','Phil','Bukayo','Mason','Marcus','Jack','Emile','Harvey','Cole','Liam'],
                            'soyad'=> ['Bellingham','Foden','Saka','Mount','Rashford','Grealish','Smith Rowe','Elliott','Palmer','Delap']],
        ];
        return $havuzlar[$ulke] ?? $havuzlar['Türkiye'];
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