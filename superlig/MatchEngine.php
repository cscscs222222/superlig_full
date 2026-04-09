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

    // --- POISSON DAĞILIMI (Knuth algoritması) ---
    // Gerçek futbol istatistiklerine dayalı gol üretimi için kullanılır.
    // Lambda: beklenen gol sayısı (xG). Dönüş: rastgele Poisson sayısı.
    private function poisson_rand(float $lambda): int {
        if ($lambda <= 0.0) return 0;
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= mt_rand() / mt_getrandmax();
        } while ($p > $L);
        return $k - 1;
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

    // --- 1. xG (BEKLENEN GOL) TABANLI GERÇEKÇİ SKOR HESAPLAYICI ---
    // MAKSİMUM GERÇEKÇİLİK: Poisson dağılımı + gerçek futbol istatistikleri
    // Ortalama maç başına gol: 2.6 – 3.3; ev sahibi avantajı: ~+0.35 gol
    public function gercekci_skor_hesapla($ev_id, $dep_id, $mac_bilgisi = null) {
        $tbl_oyuncular = $this->prefix . 'oyuncular';
        $tbl_takimlar  = $this->prefix . 'takimlar';

        // 1. Takımların hücum / savunma güçlerini al
        $stmt = $this->pdo->prepare("SELECT hucum, savunma FROM $tbl_takimlar WHERE id=?");
        $stmt->execute([$ev_id]);
        $ev_takim  = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->execute([$dep_id]);
        $dep_takim = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kayıt yoksa varsayılan güç 70
        $ev_hucum    = ($ev_takim  && isset($ev_takim['hucum']))    ? (float)$ev_takim['hucum']    : 70.0;
        $ev_savunma  = ($ev_takim  && isset($ev_takim['savunma']))  ? (float)$ev_takim['savunma']  : 70.0;
        $dep_hucum   = ($dep_takim && isset($dep_takim['hucum']))   ? (float)$dep_takim['hucum']   : 70.0;
        $dep_savunma = ($dep_takim && isset($dep_takim['savunma'])) ? (float)$dep_takim['savunma'] : 70.0;

        // 2. İlk 11'in form ve fitness'a göre anlık OVR ortalaması
        $stmt2 = $this->pdo->prepare(
            "SELECT AVG(ovr + (form-5) + ((fitness-100)*0.1)) FROM $tbl_oyuncular WHERE takim_id=? AND ilk_11=1"
        );
        $stmt2->execute([$ev_id]);  $ev_ort  = (float)($stmt2->fetchColumn() ?: $ev_hucum);
        $stmt2->execute([$dep_id]); $dep_ort = (float)($stmt2->fetchColumn() ?: $dep_hucum);
        if ($ev_ort  < 10) $ev_ort  = $ev_hucum;
        if ($dep_ort < 10) $dep_ort = $dep_hucum;

        // 3. Oyuncu kimyası bonusu (FAZ 3)
        $ev_kimya  = $this->kimya_hesapla($ev_id);
        $dep_kimya = $this->kimya_hesapla($dep_id);
        $ev_ort  += $ev_kimya['bonus'];
        $dep_ort += $dep_kimya['bonus'];

        // 4. Hava durumu etkisi
        $hava = isset($mac_bilgisi['hava_durumu']) ? $mac_bilgisi['hava_durumu'] : 'Güneşli';
        if ($hava === 'Yağmurlu') {
            $ev_ort  = max(40.0, $ev_ort  - 2);
            $dep_ort = max(40.0, $dep_ort - 2);
        }

        // 5. Derbi atmosferi (deplasman -5 OVR baskısı)
        if ($this->is_derbi($ev_id, $dep_id)) {
            $dep_ort = max(40.0, $dep_ort - 5);
        }

        // 6. xG Hesabı: Gerçekçi Oran Formülü
        //    xG_Ev  = (Hücum_Ev  / Savunma_Dep) × 1.62  [ev avantajı dahil]
        //    xG_Dep = (Hücum_Dep / Savunma_Ev)  × 1.27
        //    Eşit takımlarda (ort=70): xG_Ev=1.62, xG_Dep=1.27, toplam=2.89 ✓
        $ev_atk  = $ev_hucum   * 0.6 + $ev_ort  * 0.4;
        $dep_def = $dep_savunma * 0.6 + $dep_ort * 0.4;
        $dep_atk = $dep_hucum  * 0.6 + $dep_ort * 0.4;
        $ev_def  = $ev_savunma  * 0.6 + $ev_ort  * 0.4;

        $xg_ev  = max(0.30, min(3.20, ($ev_atk  / max(30.0, $dep_def)) * 1.62));
        $xg_dep = max(0.30, min(2.80, ($dep_atk / max(30.0, $ev_def))  * 1.27));

        // Karlı hava: biraz daha rastgele (lambda'yı ±%10 oynat)
        if ($hava === 'Karlı') {
            $xg_ev  *= mt_rand(90, 110) / 100.0;
            $xg_dep *= mt_rand(90, 110) / 100.0;
        }

        // 7. Poisson dağılımı ile skor üret
        $ev_skor  = $this->poisson_rand($xg_ev);
        $dep_skor = $this->poisson_rand($xg_dep);

        // 8. Gerçekçilik Kapağı: OVR farkına göre maksimum skor sınırı
        //    Saçma skorları (8-0, 9-2) önler; fark <35 → max 5, ≥35 → max 6
        $ovr_farki  = abs($ev_ort - $dep_ort);
        $maks_skor  = ($ovr_farki >= 35) ? 6 : 5;
        $ev_skor  = max(0, min($ev_skor,  $maks_skor));
        $dep_skor = max(0, min($dep_skor, $maks_skor));

        // 9. Dinamik maç sonu etkisi (form, fitness, sakatlık)
        $this->dinamik_mac_sonu_etkisi($ev_id, $dep_id, $ev_skor, $dep_skor, $hava);

        return [
            'ev'      => $ev_skor,
            'dep'     => $dep_skor,
            'xg_ev'   => round($xg_ev,  2),
            'xg_dep'  => round($xg_dep, 2),
        ];
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

    // --- 3. MAKSİMUM GERÇEKÇİLİK: MAÇ OLAYI ÜRETİCİSİ ---
    // Kart, gol, VAR ve maç sonu istatistikleri gerçek futbol verilerine göre hesaplanır.
    // Kırmızı kart: 0.25-0.40/maç · Sarı kart: 3.8-5.2/maç · Penaltı: 0.25-0.35/maç
    public function mac_olay_uret($takim_id, $skor) {
        // Validate prefix against known league prefixes to prevent SQL injection
        $allowed_prefixes = ['', 'pl_', 'es_', 'de_', 'it_', 'fr_', 'pt_', 'cl_', 'uel_', 'uecl_'];
        $safe_prefix = in_array($this->prefix, $allowed_prefixes, true) ? $this->prefix : '';
        $tbl_oyuncular = $safe_prefix . 'oyuncular';

        $oyuncular = $this->pdo->prepare("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=? AND ilk_11=1");
        $oyuncular->execute([$takim_id]);
        $oyuncular = $oyuncular->fetchAll(PDO::FETCH_ASSOC);
        if (!$oyuncular || count($oyuncular) == 0) {
            $stmt_all = $this->pdo->prepare("SELECT isim, mevki FROM $tbl_oyuncular WHERE takim_id=?");
            $stmt_all->execute([$takim_id]);
            $oyuncular = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!$oyuncular || count($oyuncular) == 0) {
            $oyuncular = [['isim' => 'Altyapı (AI)', 'mevki' => 'F']];
        }

        // Mevkiye göre ağırlıklı gol atıcı listesi (F=3x, OS=2x, D/K=1x)
        $golculer = [];
        foreach ($oyuncular as $o) {
            if ($o['mevki'] == 'F')       { $golculer[] = $o; $golculer[] = $o; $golculer[] = $o; }
            elseif ($o['mevki'] == 'OS')  { $golculer[] = $o; $golculer[] = $o; }
            else                          { $golculer[] = $o; }
        }

        // Kalecileri tespit et
        $kaleciler = array_filter($oyuncular, function($o){ return $o['mevki'] == 'K'; });

        // ----------------------------------------------------------------
        // GOL OLAYLARI — dakika gerçekçi dağıtılır
        // ----------------------------------------------------------------
        $olaylar = [];
        $sakatlar = [];
        $kullanilan_dakikalar = [];
        for ($i = 0; $i < $skor; $i++) {
            $gol_atan    = $golculer[array_rand($golculer)]['isim'];
            $asist_yapan = (mt_rand(0, 10) > 3) ? $oyuncular[array_rand($oyuncular)]['isim'] : '-';
            if ($asist_yapan === $gol_atan) $asist_yapan = '-';
            // Benzersiz dakika ata (skor yüksekse üst üste gelebilir)
            $deneme = 0;
            do {
                $dk = mt_rand(3, 93);
                $deneme++;
            } while (in_array($dk, $kullanilan_dakikalar, true) && $deneme < 20);
            $kullanilan_dakikalar[] = $dk;
            $olaylar[] = [
                'tip'    => 'gol',
                'dakika' => $dk,
                'oyuncu' => $gol_atan,
                'asist'  => $asist_yapan,
            ];
        }

        // ----------------------------------------------------------------
        // KART OLAYLARI — GERÇEKÇİ ORANLAR
        // Sarı kart: Poisson(2.25)/takım → toplam ~4.5/maç ✓ (3.8-5.2 arası)
        // Kırmızı kart: %16 ihtimal/takım → toplam ~0.32/maç ✓ (0.25-0.40 arası)
        // Doğrudan kırmızı kart: ~%8 (çok vahşi müdahale); geri kalanı ikinci sarı
        // ----------------------------------------------------------------
        $kartlar = [];
        $kart_oyuncular = []; // Aynı oyuncuya iki kez kart çıkmasın

        $sari_sayisi = min(5, $this->poisson_rand(2.25));
        for ($i = 0; $i < $sari_sayisi; $i++) {
            // Kalecilere kart az çıkar
            $aday = $oyuncular[array_rand($oyuncular)];
            if ($aday['mevki'] === 'K' && mt_rand(1, 100) > 15) {
                $aday = $oyuncular[array_rand($oyuncular)];
            }
            $kartlar[] = [
                'tip'    => 'kart',
                'detay'  => 'Sarı',
                'dakika' => mt_rand(5, 88),
                'oyuncu' => $aday['isim'],
            ];
            $kart_oyuncular[] = $aday['isim'];
        }

        // Kırmızı kart: %16 ihtimal; maksimum 1 per takım
        if (mt_rand(1, 100) <= 16) {
            $kirmizi_aday = $oyuncular[array_rand($oyuncular)];
            // Kaleci kırmızı çok nadir
            if ($kirmizi_aday['mevki'] === 'K' && mt_rand(1, 100) > 5) {
                $kirmizi_aday = $oyuncular[array_rand($oyuncular)];
            }
            // İkinci sarı mı yoksa direk kırmızı mı?
            if (in_array($kirmizi_aday['isim'], $kart_oyuncular, true) && mt_rand(1, 100) <= 60) {
                // İkinci sarı → kırmızı: var olan sarı kartı güncelle
                foreach ($kartlar as &$k) {
                    if ($k['oyuncu'] === $kirmizi_aday['isim'] && $k['detay'] === 'Sarı') {
                        $k['detay']  = 'Sarı → Kırmızı';
                        $k['dakika'] = max($k['dakika'] + mt_rand(5, 30), mt_rand(50, 88));
                        break;
                    }
                }
                unset($k);
            } else {
                // Doğrudan kırmızı (ciddi faul / DOGSO)
                $kartlar[] = [
                    'tip'    => 'kart',
                    'detay'  => 'Kırmızı',
                    'dakika' => mt_rand(10, 85),
                    'oyuncu' => $kirmizi_aday['isim'],
                ];
            }
        }

        // ----------------------------------------------------------------
        // PENALTİ OLAYLARI — 0.15 ihtimal/takım → ~0.30/maç ✓ (0.25-0.35 arası)
        // Penaltıların %75'i gole dönüşür (zaten $skor içinde sayılır);
        // burada sadece 'penalti' tipi bir bilgi etiketi ekliyoruz.
        // ----------------------------------------------------------------
        if (mt_rand(1, 100) <= 15) {
            $pen_atici = $golculer[array_rand($golculer)]['isim'];
            $pen_dk    = mt_rand(15, 90);
            $pen_gol   = (mt_rand(1, 100) <= 75);
            $olaylar[] = [
                'tip'    => 'penalti',
                'dakika' => $pen_dk,
                'oyuncu' => $pen_atici,
                'gol'    => $pen_gol,
                'ozel'   => $pen_gol ? '⚽ Penaltı Golü!' : '🧤 Penaltı Kurtarıldı!',
            ];
        }

        // ----------------------------------------------------------------
        // BÜYÜK FIRSAT KAÇIRILDI — Big Chance Miss; %30 ihtimalle eklenir
        // ----------------------------------------------------------------
        if (mt_rand(1, 100) <= 30) {
            $kaciran  = $golculer[array_rand($golculer)]['isim'];
            $olaylar[] = [
                'tip'    => 'big_miss',
                'dakika' => mt_rand(5, 90),
                'oyuncu' => $kaciran,
                'ozel'   => '😱 Büyük fırsat kaçtı!',
            ];
        }

        // --- FAZ 3: PLAY STYLES - FRİKİK USTASI ---
        $this->playstyle_serbest_vurus($takim_id, $olaylar);

        usort($olaylar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });
        usort($kartlar, function($a, $b) { return $a['dakika'] <=> $b['dakika']; });

        // ----------------------------------------------------------------
        // VAR SİSTEMİ — GERÇEKÇİ SINIRLAR
        // Sadece marginal ofsaytlar veya kırmızı kart incelemeleri.
        // İhtimal: %5/takım çağrısı → %10/maç.
        // VAR kırmızı kart eklemez; sadece inceleme olayı.
        // ----------------------------------------------------------------
        $var_olaylar = [];
        if (mt_rand(1, 100) <= 5) {
            $var_dk  = mt_rand(15, 88);
            $var_tip = (mt_rand(0, 1) == 0) ? 'var_gol_iptal' : 'var_kirmizi_inceleme';

            if ($var_tip === 'var_gol_iptal' && count($olaylar) > 0) {
                // Gol içeren son olayı bul ve VAR ile iptal et
                $iptal_idx = null;
                for ($vi = count($olaylar) - 1; $vi >= 0; $vi--) {
                    if ($olaylar[$vi]['tip'] === 'gol') { $iptal_idx = $vi; break; }
                }
                if ($iptal_idx !== null) {
                    $iptal_gol = $olaylar[$iptal_idx];
                    unset($olaylar[$iptal_idx]);
                    $olaylar = array_values($olaylar);
                    $var_olaylar[] = [
                        'tip'    => 'var_gol_iptal',
                        'dakika' => max($iptal_gol['dakika'] + 1, $var_dk),
                        'oyuncu' => $iptal_gol['oyuncu'],
                        'neden'  => 'marginal_ofsayt',
                    ];
                }
            } elseif ($var_tip === 'var_kirmizi_inceleme' && !empty($oyuncular)) {
                // VAR inceleme: kırmızı kart riskli faul review (kart eklenmez, sadece bilgi)
                $hedef = $oyuncular[array_rand($oyuncular)]['isim'];
                $var_olaylar[] = [
                    'tip'    => 'var_kirmizi_inceleme',
                    'dakika' => $var_dk,
                    'oyuncu' => $hedef,
                    'neden'  => 'kirmizi_kart_inceleme',
                ];
            }
        }

        // ----------------------------------------------------------------
        // KALECİ KURTARIŞLARI
        // Şut isabet oranı %30-35; gol dönüşüm oranı %9-12
        // ----------------------------------------------------------------
        $toplam_sut   = max($skor, mt_rand(9, 14));    // toplam şut ~9-14/takım
        $isabetli_sut = (int)round($toplam_sut * (mt_rand(28, 36) / 100));  // %28-36 isabet
        $kurtaris_say = max(0, $isabetli_sut - $skor);

        if (!empty($kaleciler)) {
            $kaleci = array_values($kaleciler)[0];
            try {
                $stmt_k = $this->pdo->prepare(
                    "UPDATE {$tbl_oyuncular} SET kurtaris = kurtaris + ? WHERE takim_id = ? AND mevki = 'K' AND isim = ? LIMIT 1"
                );
                $stmt_k->execute([$kurtaris_say, $takim_id, $kaleci['isim']]);
            } catch (Throwable $e) { /* kurtaris sütunu henüz yoksa sessizce geç */ }
        }

        // ----------------------------------------------------------------
        // BALLON D'OR / ALTIN AYAKKABI: SEZON GOL VE ASİST SAYAÇLARI
        // ----------------------------------------------------------------
        $gol_sayac   = [];
        $asist_sayac = [];
        foreach ($olaylar as $olay) {
            if ($olay['tip'] === 'gol') {
                $gol_sayac[$olay['oyuncu']] = ($gol_sayac[$olay['oyuncu']] ?? 0) + 1;
                if (!empty($olay['asist']) && $olay['asist'] !== '-') {
                    $asist_sayac[$olay['asist']] = ($asist_sayac[$olay['asist']] ?? 0) + 1;
                }
            }
        }
        try {
            $stmt_g = $this->pdo->prepare(
                "UPDATE {$tbl_oyuncular} SET sezon_gol = sezon_gol + ? WHERE takim_id = ? AND isim = ? LIMIT 1"
            );
            foreach ($gol_sayac as $isim => $adet) { $stmt_g->execute([$adet, $takim_id, $isim]); }
            $stmt_a = $this->pdo->prepare(
                "UPDATE {$tbl_oyuncular} SET sezon_asist = sezon_asist + ? WHERE takim_id = ? AND isim = ? LIMIT 1"
            );
            foreach ($asist_sayac as $isim => $adet) { $stmt_a->execute([$adet, $takim_id, $isim]); }
        } catch (Throwable $e) { /* sütunlar yoksa sessizce geç */ }

        // ----------------------------------------------------------------
        // MAÇ SONU İSTATİSTİKLERİ — Gerçekçi post-match istatistik paketi
        // ----------------------------------------------------------------
        $sari_kart_sayisi   = count(array_filter($kartlar, fn($k) => in_array($k['detay'], ['Sarı', 'Sarı → Kırmızı'])));
        $kirmizi_kart_sayisi = count(array_filter($kartlar, fn($k) => in_array($k['detay'], ['Kırmızı', 'Sarı → Kırmızı'])));
        $mac_istatistik = [
            'sut'          => $toplam_sut,
            'isabetli_sut' => $isabetli_sut,
            'xg'           => round($skor > 0 ? $skor * mt_rand(90, 130) / 100 : mt_rand(40, 90) / 100, 2),
            'korner'       => mt_rand(3, 8),
            'faul'         => $sari_kart_sayisi * mt_rand(2, 4) + mt_rand(2, 5),
            'ofsayt'       => mt_rand(1, 5),
            'sari_kart'    => $sari_kart_sayisi,
            'kirmizi_kart' => $kirmizi_kart_sayisi,
        ];

        // ----------------------------------------------------------------
        // MAÇIN ADAMI (Man of the Match)
        // ----------------------------------------------------------------
        $motm = null;
        arsort($gol_sayac);
        if (!empty($gol_sayac)) {
            $motm = array_key_first($gol_sayac); // En çok gol atan
        } else {
            // Gol yok: savunma / orta saha oyuncusu seç
            $diger = array_filter($oyuncular, fn($o) => in_array($o['mevki'], ['D', 'OS']));
            if (!empty($diger)) {
                $diger = array_values($diger);
                $motm = $diger[array_rand($diger)]['isim'];
            } elseif (!empty($oyuncular)) {
                $motm = $oyuncular[array_rand($oyuncular)]['isim'];
            }
        }

        return [
            'olaylar'        => json_encode($olaylar,        JSON_UNESCAPED_UNICODE),
            'kartlar'        => json_encode($kartlar,         JSON_UNESCAPED_UNICODE),
            'sakatlar'       => json_encode($sakatlar,        JSON_UNESCAPED_UNICODE),
            'var_olaylar'    => json_encode($var_olaylar,     JSON_UNESCAPED_UNICODE),
            'kurtaris'       => $kurtaris_say,
            'mac_istatistik' => json_encode($mac_istatistik, JSON_UNESCAPED_UNICODE),
            'motm'           => $motm,
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